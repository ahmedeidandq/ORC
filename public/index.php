<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Docker.php';
require_once __DIR__ . '/../src/ContainerClone.php';
require_once __DIR__ . '/../src/Opencode.php';

function orc_redirect($url, $error = '', $success = '')
{
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    if ($error !== '') {
        $url .= $sep . 'error=' . rawurlencode($error);
        $sep = '&';
    }
    if ($success !== '') {
        $url .= $sep . 'success=' . rawurlencode($success);
    }
    header('Location: ' . $url);
    exit;
}

Database::init();

$page = isset($_GET['page']) ? $_GET['page'] : 'containers';

$segments = explode('/', trim($page, '/'));
$basePage = isset($segments[0]) ? $segments[0] : 'containers';

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

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

    if ($action === 'docker_stop') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        if ($name === '') {
            $error = 'Container name is required.';
        } else {
            Docker::stop($name);
            $success = 'Container "' . htmlspecialchars($name) . '" stopped.';
        }
        header('Location: ?page=docker');
        exit;
    }

    if ($action === 'docker_start') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        if ($name === '') {
            $error = 'Container name is required.';
        } else {
            Docker::start($name);
            $success = 'Container "' . htmlspecialchars($name) . '" started.';
        }
        header('Location: ?page=docker');
        exit;
    }

    if ($action === 'docker_rm') {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        if ($name === '') {
            $error = 'Container name is required.';
        } else {
            Docker::remove($name);
            $success = 'Container "' . htmlspecialchars($name) . '" removed.';
        }
        header('Location: ?page=docker');
        exit;
    }

    if ($action === 'docker_rmi') {
        $imageName = trim(isset($_POST['image']) ? $_POST['image'] : '');
        if ($imageName === '') {
            $error = 'Image name is required.';
        } else {
            $output = Docker::removeImage($imageName);
            if ($output === '' || strpos($output, 'Error') !== false || strpos($output, 'No such image') !== false) {
                $error = 'Failed to remove image: ' . htmlspecialchars($output);
            } else {
                $success = 'Image "' . htmlspecialchars($imageName) . '" removed.';
            }
        }
        header('Location: ?page=docker');
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

                $workdir = '';
                $volumes = json_decode($clone['volumes_json'], true);
                if ($volumes) {
                    foreach ($volumes as $hostPath => $containerPath) {
                        $workdir = $containerPath;
                        $check = Docker::exec($clone['container_name'], "test -d " . escapeshellarg($containerPath . '/.git') . " && echo yes");
                        if (trim($check) === 'yes') {
                            break;
                        }
                    }
                }

                $execCmd = "docker exec -it";
                if ($workdir !== '') {
                    $execCmd .= " -w " . escapeshellarg($workdir);
                }
                $execCmd .= " $escaped bash";

                exec("tmux kill-session -t $detach 2>/dev/null");
                exec("tmux new-session -d -s $detach \"$execCmd\" 2>&1");
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

    if ($action === 'resume_session') {
        $sessionId = trim(isset($_POST['session_id']) ? $_POST['session_id'] : '');
        $container = trim(isset($_POST['container']) ? $_POST['container'] : '');
        $dir = trim(isset($_POST['directory']) ? $_POST['directory'] : '');
        $redirect = trim(isset($_POST['redirect']) ? $_POST['redirect'] : '?page=sessions');
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        $tmuxSession = Opencode::activeTmuxSession();

        if ($sessionId === '') {
            orc_redirect($redirect, 'Session id is required.');
        }

        $winName = '[ORC] ' . substr($sessionId, 0, 12);

        if ($container !== '') {
            if ($dir === '') {
                $dir = '/';
            }
            $cbin = Opencode::resolveContainerBinary($container);
            if ($cbin === '') {
                orc_redirect($redirect, 'opencode is not installed in container "' . $container . '".');
            }
            $inner = "docker exec -it -e TERM=screen-256color -e LANG=C.UTF-8 -w " . escapeshellarg($dir) . " " . escapeshellarg($container) . " " . escapeshellarg($cbin) . " --session " . escapeshellarg($sessionId) . "; exec bash";
            if ($tmuxSession !== '') {
                Opencode::tmuxNewWindow($tmuxSession, $inner, $winName);
                if ($cloneId) {
                    Opencode::trackWindow($cloneId, $tmuxSession, $winName);
                }
            } else {
                exec("nohup gnome-terminal -- bash -c \"$inner\" > /dev/null 2>&1 &");
            }
            Opencode::focusTerminal();
            orc_redirect($redirect, '', 'Opened session "' . substr($sessionId, 0, 12) . '" in container.');
        } else {
            $bin = Opencode::resolveHostBinary();
            if ($bin === '') {
                orc_redirect($redirect, 'opencode binary not found on host.');
            }
            if ($dir === '') {
                $dir = getenv('HOME');
            }
            $inner = "cd " . escapeshellarg($dir) . " && TERM=screen-256color " . escapeshellarg($bin) . " --session " . escapeshellarg($sessionId) . "; exec bash";
            if ($tmuxSession !== '') {
                Opencode::tmuxNewWindow($tmuxSession, $inner, $winName);
                if ($cloneId) {
                    Opencode::trackWindow($cloneId, $tmuxSession, $winName);
                }
            } else {
                exec("nohup gnome-terminal -- bash -c \"$inner\" > /dev/null 2>&1 &");
            }
            Opencode::focusTerminal();
            orc_redirect($redirect, '', 'Opened session "' . substr($sessionId, 0, 12) . '".');
        }
    }


    if ($action === 'start_session') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        $redirect = trim(isset($_POST['redirect']) ? $_POST['redirect'] : '?page=clones');
        $tmuxSession = Opencode::activeTmuxSession();

        if (!$cloneId) {
            orc_redirect($redirect, 'Clone id is required.');
        }

        $clone = ContainerClone::get($cloneId);
        if (!$clone) {
            orc_redirect($redirect, 'Clone not found.');
        }

        $container = $clone['container_name'];
        $status = Docker::getStatus($container);
        if ($status !== 'running') {
            orc_redirect($redirect, 'Clone container is not running.');
        }

        $cbin = Opencode::resolveContainerBinary($container);
        if ($cbin === '') {
            orc_redirect($redirect, 'opencode is not installed in container "' . $container . '".');
        }

        $dir = '/';
        $volumes = json_decode($clone['volumes_json'], true);
        if ($volumes) {
            foreach ($volumes as $containerPath => $hp) {
                $dir = $containerPath;
                break;
            }
        }

        $winName = '[ORC] ' . $clone['name'];
        $inner = "docker exec -it -e TERM=screen-256color -e LANG=C.UTF-8 -w " . escapeshellarg($dir) . " " . escapeshellarg($container) . " " . escapeshellarg($cbin) . "; exec bash";
        if ($tmuxSession !== '') {
            Opencode::tmuxNewWindow($tmuxSession, $inner, $winName);
            Opencode::trackWindow($cloneId, $tmuxSession, $winName);
        } else {
            exec("nohup gnome-terminal -- bash -c \"$inner\" > /dev/null 2>&1 &");
        }
        Opencode::focusTerminal();
        orc_redirect($redirect, '', 'Started new opencode session in "' . htmlspecialchars($clone['name']) . '".');
    }

    if ($action === 'set_session') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        $sessionId = trim(isset($_POST['session_id']) ? $_POST['session_id'] : '');
        $redirect = trim(isset($_POST['redirect']) ? $_POST['redirect'] : '?page=clones');
        $tmuxSession = Opencode::activeTmuxSession();

        if (!$cloneId) {
            orc_redirect($redirect, 'Clone id is required.');
        }
        if ($sessionId === '') {
            orc_redirect($redirect, 'Session id is required.');
        }

        $clone = ContainerClone::get($cloneId);
        if (!$clone) {
            orc_redirect($redirect, 'Clone not found.');
        }

        $container = $clone['container_name'];
        $status = Docker::getStatus($container);
        if ($status !== 'running') {
            orc_redirect($redirect, 'Clone container is not running.');
        }

        $cbin = Opencode::resolveContainerBinary($container);
        if ($cbin === '') {
            orc_redirect($redirect, 'opencode is not installed in container "' . $container . '".');
        }

        $dir = '/';
        $volumes = json_decode($clone['volumes_json'], true);
        if ($volumes) {
            foreach ($volumes as $containerPath => $hp) {
                $dir = $containerPath;
                break;
            }
        }

        $winName = '[ORC] ' . $clone['name'] . ' - ' . substr($sessionId, 0, 12);
        $inner = "docker exec -it -e TERM=screen-256color -e LANG=C.UTF-8 -w " . escapeshellarg($dir) . " " . escapeshellarg($container) . " " . escapeshellarg($cbin) . " --session " . escapeshellarg($sessionId) . "; exec bash";
        if ($tmuxSession !== '') {
            Opencode::tmuxNewWindow($tmuxSession, $inner, $winName);
            Opencode::trackWindow($cloneId, $tmuxSession, $winName);
        } else {
            exec("nohup gnome-terminal -- bash -c \"$inner\" > /dev/null 2>&1 &");
        }
        Opencode::focusTerminal();
        orc_redirect($redirect, '', 'Opened session "' . substr($sessionId, 0, 12) . '" in "' . htmlspecialchars($clone['name']) . '".');
    }

    if ($action === 'reconnect_session') {
        $cloneId = isset($_POST['clone_id']) ? (int) $_POST['clone_id'] : 0;
        $redirect = trim(isset($_POST['redirect']) ? $_POST['redirect'] : '?page=clones');

        if (!$cloneId) {
            orc_redirect($redirect, 'Clone id is required.');
        }

        $clone = ContainerClone::get($cloneId);
        if (!$clone) {
            orc_redirect($redirect, 'Clone not found.');
        }

        $tracked = Opencode::getTrackedWindow($cloneId);
        if (!$tracked) {
            $tracked = Opencode::findWindowByName('[ORC] ' . $clone['name']);
        }

        if ($tracked) {
            $ok = Opencode::focusWindow($tracked['tmux_session'], $tracked['window_name']);
            if ($ok) {
                orc_redirect($redirect, '', 'Switched to opencode window for "' . htmlspecialchars($clone['name']) . '".');
            }
            Opencode::removeTrackedWindow($cloneId);
        }

        orc_redirect($redirect, 'No active opencode window found. Start a new session instead.');
    }

    if ($action === 'session_start_server') {
        $container = trim(isset($_POST['container']) ? $_POST['container'] : '');
        if ($container === '') {
            orc_redirect('?page=sessions', 'Container name is required.');
        } else {
            $ok = Opencode::startServer($container);
            if ($ok) {
                orc_redirect('?page=sessions', '', 'Started opencode server in "' . $container . '".');
            } else {
                orc_redirect('?page=sessions', 'Could not start server: opencode not installed in "' . $container . '".');
            }
        }
    }

    if ($action === 'session_connect') {
        $container = trim(isset($_POST['container']) ? $_POST['container'] : '');
        $ip = trim(isset($_POST['ip']) ? $_POST['ip'] : '');
        $port = (int) (isset($_POST['port']) ? $_POST['port'] : 0);
        $bin = Opencode::resolveHostBinary();
        $config = require __DIR__ . '/../config.php';
        $password = isset($config['opencodePassword']) ? $config['opencodePassword'] : '';
        if ($container === '' || $ip === '' || $port <= 0) {
            orc_redirect('?page=sessions', 'Container, IP and port are required.');
        } else {
            $auth = ($password !== '') ? ' -u opencode -p ' . escapeshellarg($password) : '';
            $inner = "TERM=screen-256color " . escapeshellarg($bin) . " attach$auth http://$ip:$port; exec bash";
            $tmuxSession = Opencode::activeTmuxSession();
            if ($tmuxSession !== '') {
                Opencode::tmuxNewWindow($tmuxSession, $inner);
            } else {
                exec("nohup gnome-terminal -- bash -c \"$inner\" > /dev/null 2>&1 &");
            }
            orc_redirect('?page=sessions', '', 'Connecting to "' . $container . '" at http://' . $ip . ':' . $port);
        }
    }

    if ($action === 'delete_session') {
        $sessionId = trim(isset($_POST['session_id']) ? $_POST['session_id'] : '');
        $container = trim(isset($_POST['container']) ? $_POST['container'] : '');
        if ($sessionId === '') {
            orc_redirect('?page=sessions', 'Session id is required.');
        } else {
            $output = Opencode::deleteSession($sessionId, $container);
            if ($output === '' || strpos($output, 'Error') !== false || strpos($output, 'not found') !== false || strpos($output, 'not installed') !== false) {
                orc_redirect('?page=sessions', 'Failed to delete session: ' . $output);
            } else {
                orc_redirect('?page=sessions', '', 'Session "' . substr($sessionId, 0, 12) . '" deleted.');
            }
        }
    }

    if ($action === 'bulk_delete_sessions') {
        $ids = isset($_POST['session_ids']) ? $_POST['session_ids'] : array();
        $containers = isset($_POST['session_containers']) ? $_POST['session_containers'] : array();

        if (empty($ids)) {
            orc_redirect('?page=sessions', 'No sessions selected.');
        }

        $deleted = 0;
        $errors = array();
        foreach ($ids as $i => $id) {
            $id = trim($id);
            if ($id === '') continue;
            $container = isset($containers[$i]) ? trim($containers[$i]) : '';
            $output = Opencode::deleteSession($id, $container);
            if ($output === '' || strpos($output, 'Error') !== false || strpos($output, 'not found') !== false || strpos($output, 'not installed') !== false) {
                $errors[] = $id;
            } else {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            orc_redirect('?page=sessions', $errors ? ('Deleted ' . $deleted . ' session(s), failed: ' . count($errors)) : '', 'Deleted ' . $deleted . ' session(s).');
        } else {
            orc_redirect('?page=sessions', 'Failed to delete sessions: ' . implode(', ', array_map(function ($e) { return substr($e, 0, 12); }, $errors)));
        }
    }
}

if ($basePage === 'branches') {
    header('Content-Type: application/json');
    $containerName = isset($_GET['container']) ? $_GET['container'] : '';
    if ($containerName === '') {
        echo json_encode(array('error' => 'container name required'));
        exit;
    }

    $status = Docker::getStatus($containerName);
    if ($status !== 'running') {
        Logger::log("BRANCHES: container '$containerName' is $status");
        echo json_encode(array());
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
        } else {
            Logger::log("BRANCHES: no .git at $dest in '$containerName'");
        }
    }

    if (empty($result)) {
        Logger::log("BRANCHES: no git repos found in '$containerName'");
    }

    echo json_encode($result);
    exit;
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

    if ($clones[$key]['status'] === 'running') {
        if (!empty($clones[$key]['branches'])) {
            foreach ($clones[$key]['branches'] as $path => &$branchInfo) {
                $check = Docker::exec($clone['container_name'], "test -d " . escapeshellarg($path . '/.git') . " && echo yes");
                if (trim($check) === 'yes') {
                    $output = Docker::exec($clone['container_name'], "cd " . escapeshellarg($path) . " && git branch --show-current 2>/dev/null");
                    $current = trim($output);
                    if ($current !== '') {
                        $branchInfo['branch'] = $current;
                    }
                }
            }
            unset($branchInfo);
        } else {
            foreach ($clones[$key]['volumes'] as $hostPath => $containerPath) {
                $check = Docker::exec($clone['container_name'], "test -d " . escapeshellarg($containerPath . '/.git') . " && echo yes");
                if (trim($check) === 'yes') {
                    $output = Docker::exec($clone['container_name'], "cd " . escapeshellarg($containerPath) . " && git branch --show-current 2>/dev/null");
                    $current = trim($output);
                    if ($current !== '') {
                        $clones[$key]['branches'][$containerPath] = array(
                            'branch'      => $current,
                            'base_branch' => '',
                        );
                    }
                }
            }
        }
    }
}

foreach ($clones as $key => $clone) {
    $clones[$key]['active_session'] = null;
    $clones[$key]['container_sessions'] = array();
    $clones[$key]['tracked_window'] = Opencode::getTrackedWindow($clone['id']);
    if ($clones[$key]['status'] !== 'running') continue;

    $dbPath = Opencode::detectContainerDb($clone['container_name']);
    if ($dbPath === '') continue;

    $containerSessions = Opencode::listContainerSessions($clone['container_name'], $dbPath);
    if (!is_array($containerSessions) || empty($containerSessions)) continue;

    $clones[$key]['container_sessions'] = $containerSessions;

    foreach ($containerSessions as $cs) {
        if (!empty($cs['archived'])) continue;
        $clones[$key]['active_session'] = $cs;
        break;
    }

    if ($clones[$key]['active_session']) {
        $activeMap = Opencode::getContainerActiveStatuses($clone['container_name'], $dbPath);
        $sid = $clones[$key]['active_session']['id'];
        $clones[$key]['active_session']['is_active'] = isset($activeMap[$sid]) && $activeMap[$sid];
    }
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

$dockerContainers = array();
$dockerImages = array();
if ($basePage === 'docker') {
    $dockerContainers = Docker::listAllContainers();
    $dockerImages = Docker::listAllImages();
}

$sessions = array();
$sessionsError = '';
$opencodeContainers = array();

if ($basePage === 'sessions') {
    $sessions = Opencode::listSessions();
    if ($sessions === false) {
        $sessions = array();
    }

    $seenIds = array();
    foreach ($sessions as $s) {
        if (!empty($s['id'])) {
            $seenIds[$s['id']] = true;
        }
    }

    $config = require __DIR__ . '/../config.php';
    $serverPort = isset($config['opencodePort']) ? (int) $config['opencodePort'] : 4096;

    foreach (Docker::listAllContainers() as $c) {
        if (!$c['running']) continue;

        $name = $c['name'];
        $dbPath = Opencode::detectContainerDb($name);
        $hasBin = Opencode::hasOpencode($name);
        $ip = Docker::getIpAddress($name);

        $info = array(
            'name'         => $name,
            'ip'           => $ip,
            'port'         => $serverPort,
            'has_db'       => ($dbPath !== ''),
            'has_opencode' => $hasBin,
            'server_up'    => ($ip !== '' && Opencode::portOpen($ip, $serverPort)),
            'sessions'     => array(),
        );

        if ($dbPath !== '') {
            $containerSessions = Opencode::listContainerSessions($name, $dbPath);
            if (is_array($containerSessions)) {
                $uniqueContainer = array();
                foreach ($containerSessions as $cs) {
                    if (!empty($cs['id']) && isset($seenIds[$cs['id']])) {
                        continue;
                    }
                    if (!empty($cs['id'])) {
                        $seenIds[$cs['id']] = true;
                    }
                    $uniqueContainer[] = $cs;
                    $cs['source'] = 'Container: ' . $name;
                    $cs['container'] = $name;
                    $sessions[] = $cs;
                }
                $info['sessions'] = $uniqueContainer;
            }
        }

        $opencodeContainers[] = $info;
    }

    $containerIps = array();
    foreach ($opencodeContainers as $oc) {
        $containerIps[$oc['name']] = $oc['ip'];
    }

    if (empty($sessions) && $sessionsError === '') {
        $sessionsError = 'No opencode sessions found. Make sure opencode is installed in a container.';
    }

    usort($sessions, function ($a, $b) {
        return $b['updated_ts'] - $a['updated_ts'];
    });

    $sessionTotal = count($sessions);
    $sessionPerPage = 10;
    $sessionPage = isset($_GET['sp']) ? max(1, (int) $_GET['sp']) : 1;
    $sessionPages = max(1, (int) ceil($sessionTotal / $sessionPerPage));
    if ($sessionPage > $sessionPages) $sessionPage = $sessionPages;
    $sessionOffset = ($sessionPage - 1) * $sessionPerPage;
    $sessions = array_slice($sessions, $sessionOffset, $sessionPerPage);

    $activeMap = Opencode::getActiveStatuses($sessions);
    foreach ($sessions as &$s) {
        $s['is_active'] = isset($activeMap[$s['id']]) && $activeMap[$s['id']];
    }
    unset($s);
}

ob_start();
switch ($basePage) {
    case 'containers':
        require __DIR__ . '/../templates/containers.php';
        break;
    case 'clones':
        require __DIR__ . '/../templates/clones.php';
        break;
    case 'docker':
        require __DIR__ . '/../templates/docker.php';
        break;
    case 'sessions':
        require __DIR__ . '/../templates/sessions.php';
        break;
    default:
        header('Location: ?page=containers');
        exit;
}
$content = ob_get_clean();

require __DIR__ . '/../templates/layout.php';