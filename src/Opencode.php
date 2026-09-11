<?php

class Opencode
{
    private static $resolvedBin = null;

    public static function resolveHostBinary()
    {
        if (self::$resolvedBin !== null) {
            return self::$resolvedBin;
        }

        $config = require __DIR__ . '/../config.php';
        $bin = isset($config['opencodeBinary']) ? $config['opencodeBinary'] : 'opencode';

        if ($bin !== '' && $bin[0] === '/') {
            self::$resolvedBin = (is_executable($bin)) ? $bin : '';
            return self::$resolvedBin;
        }

        $path = getenv('PATH');
        $dirs = explode(':', $path);
        foreach ($dirs as $dir) {
            $full = rtrim($dir, '/') . '/' . $bin;
            if (is_executable($full)) {
                self::$resolvedBin = $full;
                return self::$resolvedBin;
            }
        }

        $common = array(
            '/home/' . get_current_user() . '/.opencode/bin/opencode',
            '/root/.opencode/bin/opencode',
            '/root/.local/share/opencode/bin/opencode',
            '/usr/local/bin/opencode',
            '/usr/bin/opencode',
        );
        foreach ($common as $path) {
            if (is_executable($path)) {
                self::$resolvedBin = $path;
                return self::$resolvedBin;
            }
        }

        $out = trim(shell_exec("which opencode 2>/dev/null"));
        if ($out !== '' && is_executable($out)) {
            self::$resolvedBin = $out;
            return self::$resolvedBin;
        }

        self::$resolvedBin = '';
        return self::$resolvedBin;
    }

    public static function dbQuery($sql)
    {
        $bin = self::resolveHostBinary();
        if ($bin === '') {
            return false;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'orc_db_');
        if ($tmp === false) {
            return false;
        }

        $cmd = escapeshellarg($bin) . ' db ' . escapeshellarg($sql) . ' --format json > ' . escapeshellarg($tmp) . ' 2>&1';
        $exitCode = 0;
        $line = shell_exec($cmd);
        if ($line === null) {
            $exitCode = 1;
        }

        $output = @file_get_contents($tmp);
        @unlink($tmp);

        if ($output === false || trim($output) === '') {
            return false;
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return false;
        }

        return $data;
    }

    public static function listSessions()
    {
        $rows = self::dbQuery(
            "SELECT id, title, directory, path, project_id, model, agent, cost, tokens_input, tokens_output, parent_id, time_created, time_updated, time_archived FROM session ORDER BY time_updated DESC"
        );

        if ($rows === false) {
            return false;
        }

        $sessions = array();
        foreach ($rows as $r) {
            $s = self::normalizeRow($r);
            if ($s) {
                $sessions[] = $s;
            }
        }

        return $sessions;
    }

    public static function getSession($id)
    {
        $escaped = str_replace("'", "''", $id);
        $rows = self::dbQuery(
            "SELECT id, title, directory, model, parent_id, time_archived FROM session WHERE id = '" . $escaped . "'"
        );
        if ($rows === false || empty($rows)) {
            return null;
        }
        return $rows[0];
    }

    public static function detectContainerDb($container)
    {
        $script = 'for p in /root/.local/share/opencode/opencode.db "$HOME/.local/share/opencode/opencode.db"; do if [ -f "$p" ]; then echo "$p"; break; fi; done';
        $cmd = "docker exec " . escapeshellarg($container) . " sh -c " . escapeshellarg($script) . " 2>&1";
        $out = trim(shell_exec($cmd));
        return ($out !== '' && strpos($out, 'No such') === false && strpos($out, 'Error') === false) ? $out : '';
    }

    public static function hasOpencode($container)
    {
        return (self::resolveContainerBinary($container) !== '');
    }

    public static function resolveContainerBinary($container)
    {
        $script = 'p="$(command -v opencode 2>/dev/null)"; if [ -n "$p" ]; then echo "$p"; exit 0; fi; for f in "$HOME/.opencode/bin/opencode" /root/.opencode/bin/opencode /root/.local/share/opencode/bin/opencode /usr/local/bin/opencode /usr/bin/opencode; do if [ -x "$f" ]; then echo "$f"; exit 0; fi; done';
        $cmd = "docker exec " . escapeshellarg($container) . " sh -c " . escapeshellarg($script) . " 2>&1";
        $out = trim(shell_exec($cmd));
        if ($out === '' || strpos($out, 'not found') !== false || strpos($out, 'Error') !== false || strpos($out, 'No such') !== false) {
            return '';
        }
        return $out;
    }

    public static function listContainerSessions($container, $dbPath)
    {
        $cacheDir = '/tmp/orc/opencode_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $container) . '.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 15) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $sessions = array();
        $dbPath = str_replace("'", "\\'", $dbPath);

        $script = "import sqlite3, json\n"
            . "con = sqlite3.connect('file:" . $dbPath . "?mode=ro', uri=True)\n"
            . "cur = con.execute(\"SELECT id, title, directory, path, project_id, model, agent, cost, tokens_input, tokens_output, parent_id, time_created, time_updated, time_archived FROM session ORDER BY time_updated DESC\")\n"
            . "out = []\n"
            . "for r in cur.fetchall():\n"
            . "    out.append({'id':r[0],'title':r[1],'directory':r[2],'path':r[3],'project_id':r[4],'model':r[5],'agent':r[6],'cost':r[7],'tokens_input':r[8],'tokens_output':r[9],'parent_id':r[10],'time_created':r[11],'time_updated':r[12],'time_archived':r[13]})\n"
            . "print(json.dumps(out))";

        $cmd = "docker exec " . escapeshellarg($container) . " python3 -c " . escapeshellarg($script) . " 2>&1";
        $output = shell_exec($cmd);
        $rows = json_decode(trim($output), true);

        if (is_array($rows)) {
            foreach ($rows as $r) {
                $s = self::normalizeRow($r);
                if ($s) {
                    $sessions[] = $s;
                }
            }
        }

        file_put_contents($cacheFile, json_encode($sessions));
        return $sessions;
    }

    public static function startServer($container)
    {
        $config = require __DIR__ . '/../config.php';
        $port = isset($config['opencodePort']) ? (int) $config['opencodePort'] : 4096;
        $password = isset($config['opencodePassword']) ? $config['opencodePassword'] : '';
        $bin = self::resolveContainerBinary($container);
        if ($bin === '') {
            return false;
        }

        $env = '';
        if ($password !== '') {
            $env = ' -e OPENCODE_SERVER_PASSWORD=' . escapeshellarg($password);
        }

        $cmd = "docker exec -d$env " . escapeshellarg($container) . " " . escapeshellarg($bin) . " serve --hostname 0.0.0.0 --port " . $port;
        shell_exec($cmd . ' 2>&1');
        Logger::log("START OPCODE SERVER: $cmd");
        return true;
    }

    public static function portOpen($ip, $port)
    {
        $port = (int) $port;
        $cmd = "timeout 1 bash -c \"</dev/tcp/" . escapeshellarg($ip) . "/" . $port . "\" 2>/dev/null && echo OPEN";
        $out = trim(shell_exec($cmd));
        return ($out === 'OPEN');
    }

    public static function activeTmuxSession()
    {
        $out = trim(shell_exec("tmux list-clients -F '#{session_name}' 2>/dev/null"));
        if ($out === '') {
            return '';
        }

        $sessions = array_values(array_filter(array_map('trim', explode("\n", $out)), 'strlen'));
        if (empty($sessions)) {
            return '';
        }

        $focused = trim(shell_exec("tmux list-clients -F '#{session_name} #{client_flags}' 2>/dev/null"));
        if ($focused !== '') {
            foreach (explode("\n", $focused) as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (isset($parts[1]) && strpos($parts[1], 'focused') !== false) {
                    return $parts[0];
                }
            }
        }

        return $sessions[0];
    }

    public static function tmuxNewWindow($session, $command)
    {
        if ($session === '') {
            return false;
        }
        exec("tmux new-window -t " . escapeshellarg($session . ':') . " " . escapeshellarg($command) . " 2>&1");
        return true;
    }

    public static function focusTerminal()
    {
        $out = trim(shell_exec("xdotool search --class gnome-terminal 2>/dev/null"));
        if ($out === '') {
            return false;
        }
        $ids = array_values(array_filter(array_map('trim', explode("\n", $out)), 'strlen'));
        foreach ($ids as $id) {
            $geo = trim(shell_exec("xdotool getwindowgeometry " . escapeshellarg($id) . " 2>/dev/null"));
            if (preg_match('/Geometry:\s*(\d+)x(\d+)/', $geo, $m)) {
                if ((int) $m[1] > 100 && (int) $m[2] > 50) {
                    exec("xdotool windowactivate " . escapeshellarg($id) . " 2>&1");
                    return true;
                }
            }
        }
        if (!empty($ids)) {
            exec("xdotool windowactivate " . escapeshellarg($ids[0]) . " 2>&1");
            return true;
        }
        return false;
    }

    public static function deleteSession($id, $container = '')
    {
        $id = str_replace("'", "''", $id);

        if ($container !== '') {
            $bin = self::resolveContainerBinary($container);
            if ($bin === '') {
                return 'opencode not installed in container';
            }
            $cmd = "docker exec " . escapeshellarg($container) . " " . escapeshellarg($bin) . " session delete " . escapeshellarg($id) . " 2>&1";
        } else {
            $config = require __DIR__ . '/../config.php';
            $bin = isset($config['opencodeBinary']) ? $config['opencodeBinary'] : 'opencode';
            $cmd = $bin . " session delete " . escapeshellarg($id) . " 2>&1";
        }

        $output = trim(shell_exec($cmd));
        Logger::log("DELETE SESSION: $cmd => " . ($output ?: '(empty)'));
        return $output;
    }

    private static function normalizeRow($r)
    {
        if (!is_array($r) || !isset($r['id'])) {
            return null;
        }

        $isSubagent = (isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '');
        if ($isSubagent) {
            return null;
        }

        $s = array(
            'id'             => isset($r['id']) ? $r['id'] : '',
            'short_id'       => substr($r['id'], 0, 12),
            'title'          => (isset($r['title']) && $r['title'] !== '') ? $r['title'] : $r['id'],
            'agent'          => isset($r['agent']) ? $r['agent'] : '',
            'directory'      => isset($r['directory']) ? $r['directory'] : '',
            'path'           => isset($r['path']) ? $r['path'] : '',
            'project_id'     => isset($r['project_id']) ? $r['project_id'] : '',
            'model_id'       => '',
            'model_provider' => '',
            'cost'           => isset($r['cost']) ? (float) $r['cost'] : 0,
            'tokens_input'   => isset($r['tokens_input']) ? (int) $r['tokens_input'] : 0,
            'tokens_output'  => isset($r['tokens_output']) ? (int) $r['tokens_output'] : 0,
            'is_subagent'    => false,
            'archived'       => (isset($r['time_archived']) && $r['time_archived'] !== null && $r['time_archived'] !== 0),
            'created'        => self::formatTime(isset($r['time_created']) ? $r['time_created'] : 0),
            'updated'        => self::formatTime(isset($r['time_updated']) ? $r['time_updated'] : 0),
            'updated_ts'     => isset($r['time_updated']) ? (int) $r['time_updated'] : 0,
            'source'         => 'Host',
        );

        if (isset($r['model']) && $r['model'] !== '') {
            $model = json_decode($r['model'], true);
            if (is_array($model)) {
                $s['model_id'] = isset($model['id']) ? $model['id'] : '';
                $s['model_provider'] = isset($model['providerID']) ? $model['providerID'] : '';
            }
        }

        return $s;
    }

    private static function formatTime($ms)
    {
        if (!$ms) return '-';
        return date('Y-m-d H:i', (int) ($ms / 1000));
    }
}