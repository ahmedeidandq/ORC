<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker Orchestrator</title>
    <style>
        :root {
            --bg: #0f172a;
            --fg: #e2e8f0;
            --muted: #94a3b8;
            --faint: #475569;
            --accent: #38bdf8;
            --card-bg: #1e293b;
            --border: #334155;
            --navbar-bg: #1e293b;
            --input-bg: #0f172a;
            --input-border: #475569;
            --hover-bg: #1a2332;
            --ghost-bg: #26324a;
            --ghost-fg: #cbd5e1;
            --ghost-border: #475569;
            --badge-blue-bg: #1e3a5f; --badge-blue-fg: #93c5fd;
            --badge-green-bg: #14532d; --badge-green-fg: #86efac;
            --badge-yellow-bg: #713f12; --badge-yellow-fg: #fde047;
            --th-color: #64748b;
            --td-border: #1e293b;
            --code-bg: #0f172a;
            --logs-bg: #0f172a;
            --logs-border: #334155;
            --logs-fg: #a5b4fc;
            --search-bg: #0f172a;
            --search-hover: #1e293b;
            --alert-success-bg: #065f46; --alert-success-border: #10b981; --alert-success-fg: #6ee7b7;
            --alert-error-bg: #7f1d1d; --alert-error-border: #ef4444; --alert-error-fg: #fca5a5;
        }

        [data-theme="light"] {
            --bg: #f1f5f9;
            --fg: #0f172a;
            --muted: #64748b;
            --faint: #94a3b8;
            --accent: #2563eb;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --navbar-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --hover-bg: #f1f5f9;
            --ghost-bg: #ffffff;
            --ghost-fg: #334155;
            --ghost-border: #cbd5e1;
            --badge-blue-bg: #dbeafe; --badge-blue-fg: #1d4ed8;
            --badge-green-bg: #dcfce7; --badge-green-fg: #15803d;
            --badge-yellow-bg: #fef3c7; --badge-yellow-fg: #b45309;
            --th-color: #64748b;
            --td-border: #f1f5f9;
            --code-bg: #f1f5f9;
            --logs-bg: #0f172a;
            --logs-border: #334155;
            --logs-fg: #a5b4fc;
            --search-bg: #ffffff;
            --search-hover: #f1f5f9;
            --alert-success-bg: #d1fae5; --alert-success-border: #10b981; --alert-success-fg: #065f46;
            --alert-error-bg: #fee2e2; --alert-error-border: #ef4444; --alert-error-fg: #991b1b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--fg); min-height: 100vh; }

        .navbar { background: var(--navbar-bg); border-bottom: 1px solid var(--border); padding: 0.75rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        .navbar h1 { font-size: 1.25rem; font-weight: 600; color: var(--accent); }
        .navbar h1 span { color: var(--muted); font-weight: 400; }
        .navbar nav { display: flex; align-items: center; gap: 0.5rem; }
        .navbar nav a { color: var(--muted); text-decoration: none; margin-left: 1.5rem; font-size: 0.875rem; transition: color 0.2s; }
        .navbar nav a:hover, .navbar nav a.active { color: var(--accent); }

        .theme-toggle { background: var(--ghost-bg); color: var(--ghost-fg); border: 1px solid var(--ghost-border); border-radius: 0.375rem; padding: 0.4rem 0.6rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; margin-left: 1.5rem; }
        .theme-toggle svg { width: 16px; height: 16px; }
        .theme-toggle:hover { background: var(--search-hover); }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }

        .alert { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-success { background: var(--alert-success-bg); border: 1px solid var(--alert-success-border); color: var(--alert-success-fg); }
        .alert-error { background: var(--alert-error-bg); border: 1px solid var(--alert-error-border); color: var(--alert-error-fg); }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; }
        .btn svg { width: 15px; height: 15px; flex-shrink: 0; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-warning { background: #d97706; color: #fff; }
        .btn-warning:hover { background: #b45309; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-ghost { background: var(--ghost-bg); color: var(--ghost-fg); border: 1px solid var(--ghost-border); }
        .btn-ghost:hover { background: var(--search-hover); color: var(--fg); }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }

        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; }

        .status { display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 500; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-running .status-dot { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
        .status-stopped .status-dot { background: #ef4444; }
        .status-paused .status-dot { background: #eab308; }
        .status-created .status-dot { background: #3b82f6; }
        .status-not_found .status-dot { background: #6b7280; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1rem; }

        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 500; }
        .badge-blue { background: var(--badge-blue-bg); color: var(--badge-blue-fg); }
        .badge-green { background: var(--badge-green-bg); color: var(--badge-green-fg); }
        .badge-yellow { background: var(--badge-yellow-bg); color: var(--badge-yellow-fg); }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem 1rem; font-size: 0.75rem; font-weight: 600; color: var(--th-color); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--td-border); font-size: 0.875rem; }
        tr:hover { background: var(--hover-bg); }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; color: var(--muted); }
        .form-control { width: 100%; padding: 0.5rem 0.75rem; background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 0.375rem; color: var(--fg); font-size: 0.875rem; }
        .form-control:focus { outline: none; border-color: var(--accent); }

        .checkbox-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .checkbox-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer; }
        .checkbox-label input[type="checkbox"] { accent-color: var(--accent); width: 1rem; height: 1rem; }

        .volume-list { list-style: none; padding: 0; }
        .volume-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0; font-size: 0.8rem; color: var(--muted); }
        .volume-item code { background: var(--code-bg); padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.75rem; color: var(--accent); }

        .logs { background: var(--logs-bg); border: 1px solid var(--logs-border); border-radius: 0.5rem; padding: 1rem; font-family: 'Fira Code', monospace; font-size: 0.75rem; color: var(--logs-fg); max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-header h2 { font-size: 1.5rem; font-weight: 600; }

        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        .source-info { display: flex; gap: 1rem; align-items: center; font-size: 0.8rem; color: var(--th-color); margin-top: 0.5rem; }
        .source-info code { color: var(--muted); }

        .searchable-select { position: relative; display: inline-block; }
        .searchable-input { width: 170px; }
        .searchable-list { position: absolute; z-index: 50; top: 100%; left: 0; right: 0; background: var(--search-bg); border: 1px solid var(--input-border); border-radius: 0.375rem; max-height: 180px; overflow-y: auto; display: none; }
        .searchable-option { padding: 0.4rem 0.6rem; font-size: 0.8rem; cursor: pointer; color: var(--fg); }
        .searchable-option:hover { background: var(--search-hover); color: var(--accent); }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Docker Orchestrator <span>/ Clone Manager</span></h1>
        <nav>
            <a href="?page=containers" class="<?= ($basePage ?? '') === 'containers' ? 'active' : '' ?>">Containers</a>
            <a href="?page=images" class="<?= ($basePage ?? '') === 'images' ? 'active' : '' ?>">Images</a>
            <a href="?page=clones" class="<?= ($basePage ?? '') === 'clones' ? 'active' : '' ?>">Clones</a>
            <button type="button" class="theme-toggle" id="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle theme">
                <svg id="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
        </nav>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </div>

    <script>
    (function() {
        var saved = localStorage.getItem('orc-theme');
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', saved || (prefersDark ? 'dark' : 'light'));
        updateIcons();
    })();

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', current);
        localStorage.setItem('orc-theme', current);
        updateIcons();
    }

    function updateIcons() {
        var isLight = document.documentElement.getAttribute('data-theme') === 'light';
        var sun = document.getElementById('icon-sun');
        var moon = document.getElementById('icon-moon');
        if (sun) sun.style.display = isLight ? 'none' : 'block';
        if (moon) moon.style.display = isLight ? 'block' : 'none';
    }
    </script>
</body>
</html>