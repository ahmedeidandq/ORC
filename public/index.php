<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Docker.php';
require_once __DIR__ . '/../src/ContainerClone.php';

Database::init();

$page = isset($_GET['page']) ? $_GET['page'] : 'containers';

$segments = explode('/', trim($page, '/'));
$basePage = isset($segments[0]) ? $segments[0] : 'containers';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create_clone') {
        Logger::log("POST create_clone: " . json_encode($_POST));

        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $sourceContainer = trim(isset($_POST['container']) ? $_POST['container'] : '');
        $volumes = isset($_POST['volumes']) ? $_POST['volumes'] : array();
        $customVolumes = isset($_POST['custom']) ? $_POST['custom'] : array();
        $baseBranches = isset($_POST['base_branch']) ? $_POST['base_branch'] : array();
        $newBranches = isset($_POST['new_branch']) ? $_POST['new_branch'] : array();

        if ($name === '') {
            $error = 'Clone name is required.';
        } elseif ($sourceContainer === '') {
            $error = 'Select a source container.';
        } else {
            $allVolumes = $volumes;
            if (!empty($customVolumes)) {
                foreach ($customVolumes as $cv) {
                    $allVolumes[] = $cv;
                }
            }
            $allVolumes = array_filter($allVolumes, function ($v) {
                return trim($v) !== '';
            });
            $allVolumes = array_values($allVolumes);

            $branchesMap = array();
            foreach ($allVolumes as $volPath) {
                $volPath = trim($volPath);
                if ($volPath === '') continue;

                $newBranch = isset($newBranches[$volPath]) ? trim($newBranches[$volPath]) : '';
                $baseBranch = isset($baseBranches[$volPath]) ? trim($baseBranches[$volPath]) : '';

                if ($newBranch !== '' || $baseBranch !== '') {
                    $branchesMap[$volPath] = array(
                        'branch'      => $newBranch,
                        'base_branch' => $baseBranch,
                    );
                }
            }

            try {
                ContainerClone::create($name, $sourceContainer, $allVolumes, $branchesMap);
                $success = 'Clone "' . htmlspecialchars($name) . '" created successfully.';
            } catch (Exception $e) {
                $error = 'Failed to create clone: ' . $e->getMessage();
            }
            header('Location: ?page=clones');
            exit;
        }
    }

    if ($action === 'stop_clone') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        if ($cloneId) {
            ContainerClone::stop($cloneId);
            $success = 'Container stopped.';
        }
        header('Location: ?page=clones');
        exit;
    }

    if ($action === 'start_clone') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        if ($cloneId) {
            ContainerClone::start($cloneId);
            $success = 'Container started.';
        }
        header('Location: ?page=clones');
        exit;
    }

    if ($action === 'delete_clone') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        if ($cloneId) {
            ContainerClone::delete($cloneId);
            $success = 'Clone deleted.';
        }
        header('Location: ?page=clones');
        exit;
    }

    if ($action === 'remove_clones') {
        $sourceContainer = trim(isset($_POST['container']) ? $_POST['container'] : '');
        if ($sourceContainer !== '') {
            $deleted = ContainerClone::deleteBySource($sourceContainer);
            if (empty($deleted)) {
                $error = 'No clones found for "' . htmlspecialchars($sourceContainer) . '".';
            } else {
                $success = 'Removed clones: ' . htmlspecialchars(implode(', ', $deleted));
            }
        }
        header('Location: ?page=containers');
        exit;
    }

    if ($action === 'open_terminal') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        if ($cloneId) {
            $clone = ContainerClone::get($cloneId);
            if ($clone && $clone['container_name']) {
                $escaped = escapeshellarg($clone['container_name']);
                $session = 'orc-' . $clone['id'];
                $detach = escapeshellarg($session);
                exec("tmux kill-session -t $detach 2>/dev/null");
                exec("tmux new-session -d -s $detach \"docker exec -it $escaped bash\" 2>&1");
                exec("nohup gnome-terminal -- tmux attach -t $detach > /dev/null 2>&1 &");
            }
        }
        header('Location: ?page=clones');
        exit;
    }

    if ($action === 'open_editor') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        if ($cloneId) {
            $clone = ContainerClone::get($cloneId);
            if ($clone) {
                $config = require __DIR__ . '/../config.php';
                $editor = isset($config['editor']) ? $config['editor'] : 'code';
                $volumeMappings = json_decode($clone['volumes_json'], true);
                if ($volumeMappings) {
                    $paths = array();
                    foreach ($volumeMappings as $hostPath => $containerPath) {
                        if (is_dir($hostPath)) {
                            $paths[] = $hostPath;
                        }
                    }
                    if (!empty($paths)) {
                        $args = implode(' ', array_map('escapeshellarg', $paths));
                        exec("nohup $editor $args > /dev/null 2>&1 &");
                    }
                }
            }
        }
        header('Location: ?page=clones');
        exit;
    }
}

$containerList = Docker::listContainers();
$clones = ContainerClone::all();

foreach ($clones as $key => $clone) {
    $clones[$key]['status'] = ContainerClone::refreshStatus($clone['id']);
    $clones[$key]['ip'] = ($clones[$key]['status'] === 'running') ? Docker::getIpAddress($clone['container_name']) : '';
    $clones[$key]['volumes'] = json_decode($clone['volumes_json'], true);
    if (!$clones[$key]['volumes']) {
        $clones[$key]['volumes'] = array();
    }
    $clones[$key]['branches'] = json_decode($clone['branches_json'], true);
    if (!$clones[$key]['branches']) {
        $clones[$key]['branches'] = array();
    }
}

if ($basePage === 'branches') {
    header('Content-Type: application/json');
    $containerName = isset($_GET['container']) ? $_GET['container'] : '';
    if ($containerName === '') {
        echo json_encode(array('error' => 'container name required'));
        exit;
    }

    $volumes = Docker::inspectVolumes($containerName);
    $result = array();

    foreach ($volumes as $vol) {
        $dest = isset($vol['destination']) ? $vol['destination'] : '';
        if ($dest === '') continue;

        $check = Docker::exec($containerName, "test -d " . escapeshellarg($dest . '/.git') . " && echo yes");
        if (trim($check) === 'yes') {
            $output = Docker::exec($containerName, "cd " . escapeshellarg($dest) . " && git branch 2>/dev/null");
            $branches = array();
            $current = '';
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, 'HEAD') !== false) continue;
                if ($line[0] === '*') {
                    $current = trim(substr($line, 1));
                    continue;
                }
                $line = ltrim($line, '* ');
                if ($line !== '') {
                    $branches[] = $line;
                }
            }
            $branches = array_values(array_unique($branches));
            $result[$dest] = array(
                'current'  => $current,
                'branches' => $branches,
            );
        }
    }

    echo json_encode($result);
    exit;
}

if ($basePage === 'logs') {
    header('Content-Type: text/plain');
    $cloneId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $lines = isset($_GET['lines']) ? (int) $_GET['lines'] : 100;
    if ($cloneId) {
        echo ContainerClone::logs($cloneId, $lines);
    }
    exit;
}

ob_start();
switch ($basePage) {
    case 'containers':
        require __DIR__ . '/../templates/containers.php';
        break;
    case 'clones':
        require __DIR__ . '/../templates/clones.php';
        break;
    default:
        header('Location: ?page=containers');
        exit;
}
$content = ob_get_clean();

require __DIR__ . '/../templates/layout.php';