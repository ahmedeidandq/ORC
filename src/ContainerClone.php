<?php

class ContainerClone
{
    public static function create($name, $sourceContainer, array $volumes, array $branches = array())
    {
        $config = require __DIR__ . '/../config.php';

        Logger::log("CLONE START: name=$name, source=$sourceContainer, volumes=" . json_encode($volumes) . ", branches=" . json_encode($branches));

        $imageName = $config['containerPrefix'] . strtolower($name) . ':' . date('YmdHis');

        $existingImage = self::findExistingImage($sourceContainer);
        if ($existingImage) {
            $imageName = $existingImage;
            Logger::log("REUSE IMAGE: $imageName");
        } else {
            Logger::log("COMMIT: $sourceContainer -> $imageName");
            Docker::commit($sourceContainer, $imageName);
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

        $containerName = $config['containerPrefix'] . strtolower($name);

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
            'name'             => $name,
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

    private static function findExistingImage($sourceContainer)
    {
        $existingClones = Database::getAllClones();
        foreach ($existingClones as $clone) {
            if ($clone['source_container'] === $sourceContainer && !empty($clone['image'])) {
                $check = shell_exec("docker inspect --format='{{.Config.Image}}' " . escapeshellarg($clone['image']) . " 2>/dev/null");
                $check = trim($check ? $check : '');
                $sourceInfo = Docker::inspect($sourceContainer);
                $sourceImage = $sourceInfo ? (isset($sourceInfo['Config']['Image']) ? $sourceInfo['Config']['Image'] : '') : '';
                if ($check === $sourceImage) {
                    return $clone['image'];
                }
            }
        }

        return null;
    }
}
