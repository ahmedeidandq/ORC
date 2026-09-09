<?php

class Docker
{
    public static function listContainers()
    {
        $config = require __DIR__ . '/../config.php';
        $prefix = isset($config['containerPrefix']) ? $config['containerPrefix'] : 'orc-';

        $output = shell_exec("docker ps --size --format '{\"id\":\"{{.ID}}\",\"name\":\"{{.Names}}\",\"image\":\"{{.Image}}\",\"status\":\"{{.Status}}\",\"size\":\"{{.Size}}\"}' 2>/dev/null");
        if ($output === null || $output === '') {
            return array();
        }

        $containers = array();
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $data = json_decode($line, true);
            if ($data) {
                if (strpos($data['name'], $prefix) === 0) {
                    continue;
                }
                $data['volumes'] = self::inspectVolumes($data['id']);
                $containers[] = $data;
            }
        }

        return $containers;
    }

    public static function commit($containerId, $imageName)
    {
        $escaped = escapeshellarg($containerId);
        $escapedName = escapeshellarg($imageName);
        shell_exec("docker commit $escaped $escapedName 2>&1");
        return true;
    }

    public static function copyFromContainer($containerId, $containerPath, $hostPath)
    {
        $dir = dirname($hostPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $escapedId = escapeshellarg($containerId);
        $escapedSrc = escapeshellarg($containerPath);
        $escapedDst = escapeshellarg($hostPath . '_tmp');

        shell_exec("docker cp $escapedId:$escapedSrc $escapedDst 2>&1");

        $tmpPath = $hostPath . '_tmp';
        if (is_dir($tmpPath)) {
            if (is_dir($hostPath)) {
                self::removeDir($hostPath);
            }
            rename($tmpPath, $hostPath);
        } elseif (is_file($tmpPath)) {
            $hostDir = dirname($hostPath);
            if (!is_dir($hostDir)) {
                mkdir($hostDir, 0755, true);
            }
            rename($tmpPath, $hostPath);
        }
    }

    public static function run($name, $image, array $volumeMounts, array $envVars = array(), $entrypoint = '', array $cmdArgs = array())
    {
        $escapedName = escapeshellarg($name);
        $escapedImage = escapeshellarg($image);

        $volumes = '';
        foreach ($volumeMounts as $hostPath => $containerPath) {
            $volumes .= ' -v ' . escapeshellarg($hostPath . ':' . $containerPath);
        }

        $env = '';
        foreach ($envVars as $key => $value) {
            $env .= ' -e ' . escapeshellarg($key . '=' . $value);
        }

        $entrypointArg = $entrypoint !== '' ? ' --entrypoint ' . escapeshellarg($entrypoint) : '';
        $cmdArg = '';
        foreach ($cmdArgs as $arg) {
            $cmdArg .= ' ' . escapeshellarg($arg);
        }

        $createCmd = "docker create --name $escapedName $entrypointArg $volumes $env $escapedImage $cmdArg 2>&1";
        Logger::log("CREATE CMD: $createCmd");
        $output = shell_exec($createCmd);
        Logger::log("CREATE OUTPUT: " . trim($output ? $output : '(empty)'));

        if ($output && strpos($output, 'Error') !== false) {
            return '';
        }

        $startCmd = "docker start $escapedName 2>&1";
        Logger::log("START CMD: $startCmd");
        $startOutput = shell_exec($startCmd);
        Logger::log("START OUTPUT: " . trim($startOutput ? $startOutput : '(empty)'));

        $idOutput = shell_exec("docker inspect --format='{{.Id}}' $escapedName 2>/dev/null");
        $id = trim($idOutput ? $idOutput : '');
        Logger::log("CONTAINER ID: " . ($id ? $id : '(empty)'));
        return $id;
    }

    public static function stop($containerName)
    {
        $escaped = escapeshellarg($containerName);
        shell_exec("docker stop $escaped 2>&1");
    }

    public static function start($containerName)
    {
        $escaped = escapeshellarg($containerName);
        shell_exec("docker start $escaped 2>&1");
    }

    public static function remove($containerName)
    {
        $escaped = escapeshellarg($containerName);
        shell_exec("docker rm -f $escaped 2>&1");
    }

    public static function logs($containerName, $lines = 100)
    {
        $escaped = escapeshellarg($containerName);
        $output = shell_exec("docker logs --tail $lines $escaped 2>&1");
        return $output ? $output : '';
    }

    public static function exec($containerName, $command)
    {
        $escaped = escapeshellarg($containerName);
        $cmd = "docker exec $escaped sh -c " . escapeshellarg($command) . " 2>&1";
        Logger::log("EXEC: $cmd");
        $output = shell_exec($cmd);
        return $output ? $output : '';
    }

    public static function inspect($containerName)
    {
        $escaped = escapeshellarg($containerName);
        $output = shell_exec("docker inspect $escaped 2>/dev/null");
        if ($output === null || $output === '') return null;

        $data = json_decode($output, true);
        return (is_array($data) && isset($data[0])) ? $data[0] : null;
    }

    public static function getStatus($containerName)
    {
        $info = self::inspect($containerName);
        if (!$info || !isset($info['State'])) return 'not_found';
        if ($info['State']['Running']) return 'running';
        if (isset($info['State']['Paused']) && $info['State']['Paused']) return 'paused';
        return 'stopped';
    }

    public static function getIpAddress($containerName)
    {
        $escaped = escapeshellarg($containerName);
        $output = shell_exec("docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $escaped 2>/dev/null");
        return trim($output ? $output : '');
    }

    public static function inspectVolumes($containerId)
    {
        $info = self::inspect($containerId);
        if (!$info || !isset($info['Mounts'])) return array();

        $volumes = array();
        foreach ($info['Mounts'] as $mount) {
            if ($mount['Type'] === 'volume' || $mount['Type'] === 'bind') {
                $volumes[] = array(
                    'type'        => $mount['Type'],
                    'source'      => isset($mount['Source']) ? $mount['Source'] : '',
                    'destination' => isset($mount['Destination']) ? $mount['Destination'] : '',
                    'rw'          => isset($mount['RW']) ? $mount['RW'] : true,
                );
            }
        }
        return $volumes;
    }

    public static function listImages()
    {
        $config = require __DIR__ . '/../config.php';
        $prefix = isset($config['containerPrefix']) ? $config['containerPrefix'] : 'orc-';

        $output = shell_exec("docker images --format '{\"repo\":\"{{.Repository}}\",\"tag\":\"{{.Tag}}\",\"id\":\"{{.ID}}\",\"size\":\"{{.Size}}\",\"created\":\"{{.CreatedSince}}\"}' 2>/dev/null");
        if ($output === null || $output === '') {
            return array();
        }

        $images = array();
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $data = json_decode($line, true);
            if ($data && strpos($data['repo'], $prefix) === 0) {
                $images[] = $data;
            }
        }

        return $images;
    }

    public static function inspectImage($image)
    {
        $escaped = escapeshellarg($image);
        $output = shell_exec("docker inspect $escaped 2>/dev/null");
        if ($output === null || $output === '') return null;

        $data = json_decode($output, true);
        return (is_array($data) && isset($data[0])) ? $data[0] : null;
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
}
