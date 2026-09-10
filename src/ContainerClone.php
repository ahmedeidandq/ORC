<?php

class ContainerClone
{
    public static function create($name, $sourceContainer, array $volumes, array $branches = array())
    {
        $config = require __DIR__ . '/../config.php';

        $cleanName = self::sanitizeName($name);
        if ($cleanName === '') {
            throw new Exception('Invalid clone name. Use letters, digits, "-", "_", "." only.');
        }
        if ($cleanName !== $name) {
            Logger::log("CLONE START: name=$name -> sanitized=$cleanName, source=$sourceContainer, volumes=" . json_encode($volumes) . ", branches=" . json_encode($branches));
        } else {
            Logger::log("CLONE START: name=$name, source=$sourceContainer, volumes=" . json_encode($volumes) . ", branches=" . json_encode($branches));
        }

        $imageName = strtolower($config['containerPrefix']) . $cleanName . ':' . date('YmdHis');

        $resolved = self::resolveCommittedImage($sourceContainer, $cleanName);
        if ($resolved) {
            $imageName = $resolved;
            Logger::log("REUSE IMAGE: $imageName");
        } else {
            Logger::log("COMMIT: $sourceContainer -> $imageName");
            Docker::commit($sourceContainer, $imageName);
            Database::saveImageMapping($sourceContainer, $imageName);
        }

        $volumeMappings = array();

        foreach ($volumes as $containerPath) {
            $containerPath = trim($containerPath);
            if ($containerPath === '') continue;
            if ($containerPath[0] !== '/') $containerPath = '/' . ltrim($containerPath, '/');

            $uniqueId = uniqid('vol_', true);
            $hostPath = '/tmp/orc/clones/' . $uniqueId;
            Logger::log("COPY VOLUME: $sourceContainer:$containerPath -> $hostPath");
            Docker::copyFromContainer($sourceContainer, $containerPath, $hostPath);
            $volumeMappings[$hostPath] = $containerPath;
        }

        $containerName = $config['containerPrefix'] . $cleanName;

        $sourceInfo = Docker::inspect($sourceContainer);
        $sourceImage = isset($sourceInfo['Config']['Image']) ? $sourceInfo['Config']['Image'] : '';
        $runCmd = array();
        $entrypoint = '';
        if (preg_match('/apache/', $sourceImage)) {
            $runCmd = array('apache2-foreground');
        } elseif (preg_match('/nginx/', $sourceImage)) {
            $runCmd = array('nginx', '-g', 'daemon off;');
        } elseif (preg_match('/mysql|mariadb/', $sourceImage)) {
            $runCmd = array('mysqld');
        } elseif (preg_match('/postgres/', $sourceImage)) {
            $runCmd = array('postgres');
        } elseif (preg_match('/redis/', $sourceImage)) {
            $runCmd = array('redis-server');
        } elseif (preg_match('/node/', $sourceImage)) {
            $entrypoint = '/bin/sh';
            $runCmd = array('-c', 'while true; do sleep 1000; done');
        } else {
            $entrypoint = '/bin/sh';
            $runCmd = array('-c', 'while true; do sleep 1000; done');
        }

        Logger::log("CLONE: sourceImage=$sourceImage, entrypoint=$entrypoint, runCmd=" . json_encode($runCmd));
        $containerId = Docker::run($containerName, $imageName, $volumeMappings, array(), $entrypoint, $runCmd);

        $branchesJson = array();

        if ($containerId && !empty($branches)) {
            foreach ($branches as $containerPath => $info) {
                $containerPath = trim($containerPath);
                if ($containerPath === '' || $containerPath[0] !== '/') {
                    $containerPath = '/' . ltrim($containerPath, '/');
                }
                $branch = isset($info['branch']) ? trim($info['branch']) : '';
                $base = isset($info['base_branch']) ? trim($info['base_branch']) : '';
                if ($branch === '' && $base === '') continue;

                $hasGit = Docker::exec($containerName, "test -d " . escapeshellarg($containerPath . '/.git') . " && echo yes");
                if (trim($hasGit) !== 'yes') {
                    Logger::log("GIT: no .git at $containerPath, skipping");
                    continue;
                }

                Logger::log("GIT: repo=$containerPath, branch=" . ($branch ?: '(none)') . ", base=$base");
                Docker::exec($containerName, "cd " . escapeshellarg($containerPath) . " && git fetch --all 2>&1");
                if ($base !== '') {
                    $out = Docker::exec($containerName, "cd " . escapeshellarg($containerPath) . " && git checkout " . escapeshellarg($base) . " 2>&1");
                    Logger::log("GIT checkout base output: " . trim($out));
                }
                if ($branch !== '') {
                    $out = Docker::exec($containerName, "cd " . escapeshellarg($containerPath) . " && git checkout -b " . escapeshellarg($branch) . " 2>&1");
                    Logger::log("GIT checkout -b output: " . trim($out));
                }

                $branchesJson[$containerPath] = array(
                    'branch'      => $branch,
                    'base_branch' => $base,
                );
            }
        }

        $clone = array(
            'id'               => Database::nextCloneId(),
            'name'             => $cleanName,
            'source_container' => $sourceContainer,
            'container_id'     => $containerId,
            'container_name'   => $containerName,
            'image'            => $imageName,
            'volumes_json'     => json_encode($volumeMappings),
            'branches_json'    => json_encode($branchesJson),
            'status'           => $containerId ? 'running' : 'not_found',
            'created_at'       => date('Y-m-d H:i:s'),
        );

        Database::saveClone($clone);
        return $clone;
    }

    public static function createFromImage($name, $imageName, array $volumeMappings = array())
    {
        $config = require __DIR__ . '/../config.php';

        Logger::log("CREATE FROM IMAGE: name=$name, image=$imageName, volumes=" . json_encode($volumeMappings));

        $containerName = $config['containerPrefix'] . strtolower($name);

        $imageInfo = Docker::inspectImage($imageName);
        $baseImage = $imageInfo ? (isset($imageInfo['Config']['Image']) ? $imageInfo['Config']['Image'] : '') : $imageName;

        $runCmd = array();
        $entrypoint = '';
        if (preg_match('/apache/', $baseImage)) {
            $runCmd = array('apache2-foreground');
        } elseif (preg_match('/nginx/', $baseImage)) {
            $runCmd = array('nginx', '-g', 'daemon off;');
        } elseif (preg_match('/mysql|mariadb/', $baseImage)) {
            $runCmd = array('mysqld');
        } elseif (preg_match('/postgres/', $baseImage)) {
            $runCmd = array('postgres');
        } elseif (preg_match('/redis/', $baseImage)) {
            $runCmd = array('redis-server');
        } elseif (preg_match('/node/', $baseImage)) {
            $entrypoint = '/bin/sh';
            $runCmd = array('-c', 'while true; do sleep 1000; done');
        } else {
            $entrypoint = '/bin/sh';
            $runCmd = array('-c', 'while true; do sleep 1000; done');
        }

        Logger::log("FROM IMAGE: baseImage=$baseImage, entrypoint=$entrypoint, runCmd=" . json_encode($runCmd));
        $containerId = Docker::run($containerName, $imageName, $volumeMappings, array(), $entrypoint, $runCmd);

        $clone = array(
            'id'               => Database::nextCloneId(),
            'name'             => $name,
            'source_container' => $imageName,
            'container_id'     => $containerId,
            'container_name'   => $containerName,
            'image'            => $imageName,
            'volumes_json'     => json_encode($volumeMappings),
            'branches_json'    => json_encode(array()),
            'status'           => $containerId ? 'running' : 'not_found',
            'created_at'       => date('Y-m-d H:i:s'),
        );

        Database::saveClone($clone);
        return $clone;
    }

    public static function get($id)
    {
        return Database::getClone($id);
    }

    public static function all()
    {
        return Database::getAllClones();
    }

    public static function stop($id)
    {
        $clone = Database::getClone($id);
        if (!$clone || !$clone['container_name']) return;

        Docker::stop($clone['container_name']);
        $clone['status'] = 'stopped';
        Database::saveClone($clone);
    }

    public static function start($id)
    {
        $clone = Database::getClone($id);
        if (!$clone || !$clone['container_name']) return;

        Docker::start($clone['container_name']);
        $clone['status'] = 'running';
        Database::saveClone($clone);
    }

    public static function delete($id)
    {
        $clone = Database::getClone($id);
        if (!$clone) return;

        if ($clone['container_name']) {
            $status = Docker::getStatus($clone['container_name']);
            if ($status !== 'not_found') {
                Docker::stop($clone['container_name']);
                Docker::remove($clone['container_name']);
            }
        }

        $volumeMappings = json_decode($clone['volumes_json'], true);
        if ($volumeMappings) {
            foreach ($volumeMappings as $hostPath => $containerPath) {
                if (is_dir($hostPath)) {
                    self::removeDir($hostPath);
                } elseif (is_file($hostPath)) {
                    unlink($hostPath);
                }
            }
        }

        Database::deleteClone($id);
    }

    public static function refreshStatus($id)
    {
        $clone = Database::getClone($id);
        if (!$clone || !$clone['container_name']) return 'not_found';

        $status = Docker::getStatus($clone['container_name']);
        $clone['status'] = $status;
        Database::saveClone($clone);
        return $status;
    }

    public static function logs($id, $lines = 100)
    {
        $clone = Database::getClone($id);
        if (!$clone || !$clone['container_name']) return '';

        return Docker::logs($clone['container_name'], $lines);
    }

    public static function deleteBySource($sourceContainer)
    {
        $deleted = array();
        $clones = Database::getAllClones();
        foreach ($clones as $clone) {
            if ($clone['source_container'] === $sourceContainer) {
                self::delete($clone['id']);
                $deleted[] = $clone['name'];
            }
        }
        return $deleted;
    }

    private static function removeDir($dir)
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public static function sanitizeName($name)
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_.-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '.-');
        return $name;
    }

    public static function resolveCommittedImage($sourceContainer, $cleanName = '')
    {
        $config = require __DIR__ . '/../config.php';
        $prefix = isset($config['containerPrefix']) ? $config['containerPrefix'] : 'ORC-';
        $prefixLower = strtolower($prefix);

        $candidates = array();

        $mappings = Database::getImageMappingsBySource($sourceContainer);
        foreach ($mappings as $m) {
            if (!empty($m['image'])) {
                $candidates[] = $m['image'];
            }
        }

        if ($cleanName !== '') {
            foreach (Docker::listImages() as $img) {
                $repoTag = $img['repo'] . ':' . $img['tag'];
                if ($img['repo'] === $prefixLower . $cleanName) {
                    $candidates[] = $repoTag;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        usort($candidates, function ($a, $b) {
            return strcmp($b, $a);
        });

        foreach ($candidates as $tag) {
            if (Docker::imageExists($tag)) {
                return $tag;
            }
            Database::deleteImageMapping($sourceContainer, $tag);
        }

        return null;
    }

    public static function committedImagesBySource()
    {
        $result = array();
        $mappings = Database::getAllImageMappings();
        foreach ($mappings as $m) {
            $source = isset($m['source_container']) ? $m['source_container'] : '';
            $image = isset($m['image']) ? $m['image'] : '';
            if ($source === '' || $image === '' || !Docker::imageExists($image)) {
                if ($image !== '') {
                    Database::deleteImageMapping($source, $image);
                }
                continue;
            }
            if (!isset($result[$source])) {
                $result[$source] = array();
            }
            $result[$source][] = $image;
        }

        foreach ($result as $source => &$tags) {
            usort($tags, function ($a, $b) {
                return strcmp($b, $a);
            });
        }
        unset($tags);

        return $result;
    }
}
