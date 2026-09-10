<div class="page-header">
    <h2>Docker Containers</h2>
</div>

<?php if (empty($containerList)): ?>
    <div class="card">
        <p style="color: var(--muted); text-align: center; padding: 2rem;">No running containers found.</p>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Container</th>
                    <th>Image</th>
                    <th>Committed</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($containerList as $c): ?>
                    <?php $committedTags = isset($committedBySource[$c['name']]) ? $committedBySource[$c['name']] : array(); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($c['image']) ?></code></td>
                        <td>
                            <?php if (!empty($committedTags)): ?>
                                <code><?= htmlspecialchars($committedTags[0]) ?></code>
                                <?php if (count($committedTags) > 1): ?>
                                    <span class="badge badge-blue">+<?= count($committedTags) - 1 ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($c['size'] ?? '') ?></code></td>
                        <td><?= htmlspecialchars($c['status']) ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-primary btn-sm" title="Clone container"
                                        onclick="loadBranches('<?= htmlspecialchars($c['name']) ?>'); document.getElementById('clone-form-<?= htmlspecialchars($c['name']) ?>').style.display = document.getElementById('clone-form-<?= htmlspecialchars($c['name']) ?>').style.display === 'none' ? 'block' : 'none'">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_clones">
                                    <input type="hidden" name="container" value="<?= htmlspecialchars($c['name']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Remove all clones"
                                            onclick="return confirm('Remove all clones of <?= htmlspecialchars($c['name']) ?>?')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="clone-form-<?= htmlspecialchars($c['name']) ?>" style="display: none;">
                        <td colspan="6">
                            <form method="POST" action="?page=containers" style="background: var(--card-bg); padding: 1rem; border-radius: 0.5rem;">
                                <input type="hidden" name="action" value="create_clone">
                                <input type="hidden" name="container" value="<?= htmlspecialchars($c['name']) ?>">

                                <div class="form-group" style="display: inline-block; width: 220px; margin-right: 1rem; margin-bottom: 1rem;">
                                    <label>Clone Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. task-1" required
                                           pattern="[a-zA-Z0-9_.-]+" title="Lowercase letters, digits, -, _ and . only">
                                </div>

                                <div style="display: inline-block; margin-bottom: 1rem; vertical-align: top;">
                                    <label style="font-size: 0.75rem; color: var(--muted); display: block; margin-bottom: 0.375rem;">Committed image</label>
                                    <?php if (!empty($committedTags)): ?>
                                        <span class="badge badge-green">Reuse <?= htmlspecialchars($committedTags[0]) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-yellow">Will commit fresh</span>
                                    <?php endif; ?>
                                </div>

                                <div style="margin-bottom: 0.75rem;">
                                    <label style="font-size: 0.75rem; color: var(--muted); display: block; margin-bottom: 0.375rem;">Volumes to copy:</label>
                                    <div class="checkbox-group">
                                        <?php if (!empty($c['volumes'])): ?>
                                            <?php foreach ($c['volumes'] as $volIndex => $vol): ?>
                                                <div style="margin-bottom: 0.5rem;">
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" name="volumes[]" value="<?= htmlspecialchars($vol['destination']) ?>">
                                                        <code><?= htmlspecialchars($vol['destination']) ?></code>
                                                        <span style="color: var(--muted); font-size: 0.7rem;">
                                                            (<?= htmlspecialchars($vol['type']) ?>)
                                                        </span>
                                                    </label>
                                                    <div class="branch-fields" id="branch-<?= htmlspecialchars($c['name']) ?>-<?= $volIndex ?>" style="display: none; margin-top: 0.4rem; margin-left: 1.5rem;">
                                                        <div class="searchable-select" id="ss-<?= htmlspecialchars($c['name']) ?>-<?= $volIndex ?>">
                                                            <input type="text" class="searchable-input form-control" placeholder="Search branch..." autocomplete="off">
                                                            <input type="hidden" class="searchable-value" name="base_branch[<?= htmlspecialchars($vol['destination']) ?>]" value="">
                                                            <div class="searchable-list"></div>
                                                        </div>
                                                        <input type="text" name="new_branch[<?= htmlspecialchars($vol['destination']) ?>]"
                                                               class="form-control" style="width: 170px; display: inline-block;"
                                                               placeholder="new branch (optional)"
                                                               id="new-branch-<?= htmlspecialchars($c['name']) ?>-<?= $volIndex ?>">
                                                        <button type="button" class="btn btn-ghost btn-sm" title="Generate branch name with timestamp"
                                                                onclick="generateBranchName('new-branch-<?= htmlspecialchars($c['name']) ?>-<?= $volIndex ?>')">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M8 3H3v5"></path><path d="M21 8v5l-3.5-3.5L13 14l-4-4-3 3V3h13v5z"></path><path d="M3 13v8h8"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p style="color: var(--muted); font-size: 0.8rem;">No mounted volumes found.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="margin-top: 0.5rem;">
                                    <button type="submit" class="btn btn-primary btn-sm">Proceed</button>
                                    <button type="button" class="btn btn-ghost btn-sm"
                                            onclick="this.closest('tr').style.display='none'">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function generateBranchName(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var key = input.value.trim();
    var ts = new Date();
    function pad(n) { return n < 10 ? '0' + n : n; }
    var stamp = ts.getFullYear() + '-' + pad(ts.getMonth()+1) + '-' + pad(ts.getDate())
              + '_' + pad(ts.getHours()) + '-' + pad(ts.getMinutes()) + '-' + pad(ts.getSeconds());
    input.value = (key === '' ? '_' : key + '_') + stamp;
}

function fuzzyScore(query, text) {
    text = text.toLowerCase();
    if (query === '') return 0;
    var q = 0, t = 0, score = 0, prev = -1;
    while (q < query.length && t < text.length) {
        if (query[q] === text[t]) {
            score += (prev === t - 1) ? 2 : 1;
            prev = t;
            q++;
        }
        t++;
    }
    return (q === query.length) ? score : -1;
}

function initSearchableSelect(ss) {
    var input = ss.querySelector('.searchable-input');
    var hidden = ss.querySelector('.searchable-value');
    var list = ss.querySelector('.searchable-list');
    var options = ss.querySelectorAll('.searchable-option');

    input.value = hidden.value;

    function render(filter) {
        list.innerHTML = '';
        var matched = [];
        options.forEach(function(opt) {
            var value = opt.getAttribute('data-value');
            var score = fuzzyScore(filter, value);
            if (score >= 0) {
                matched.push({value: value, score: score});
            }
        });
        matched.sort(function(a, b) { return b.score - a.score; });

        var current = hidden.value;
        var currentIdx = -1;
        matched.forEach(function(m, i) {
            var div = document.createElement('div');
            div.className = 'searchable-option';
            div.textContent = m.value;
            div.setAttribute('data-value', m.value);
            if (m.value === current) currentIdx = i;
            div.addEventListener('click', function() {
                select(this.getAttribute('data-value'));
            });
            list.appendChild(div);
        });

        if (matched.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'searchable-option';
            empty.style.color = 'var(--muted)';
            empty.textContent = 'No branches found';
            list.appendChild(empty);
        }

        if (currentIdx >= 0) {
            setActive(currentIdx);
        } else if (matched.length > 0) {
            setActive(0);
        }
    }

    function select(value) {
        hidden.value = value;
        input.value = value;
        list.style.display = 'none';
    }

    function setActive(idx) {
        var items = list.querySelectorAll('.searchable-option');
        if (items.length === 0) return;
        if (idx < 0) idx = 0;
        if (idx >= items.length) idx = items.length - 1;
        items.forEach(function(el, i) {
            el.style.background = (i === idx) ? 'var(--search-hover)' : '';
            el.style.color = (i === idx) ? 'var(--accent)' : 'var(--fg)';
        });
        activeIdx = idx;
    }

    var activeIdx = 0;

    input.addEventListener('click', function() { render(input.value); list.style.display = 'block'; });
    input.addEventListener('input', function() { render(input.value); list.style.display = 'block'; });
    input.addEventListener('focus', function() { render(input.value); list.style.display = 'block'; });

    input.addEventListener('keydown', function(e) {
        var items = list.querySelectorAll('.searchable-option');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (list.style.display !== 'block') { render(input.value); list.style.display = 'block'; }
            setActive(activeIdx + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (list.style.display !== 'block') { render(input.value); list.style.display = 'block'; }
            setActive(activeIdx - 1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (items.length > 0 && activeIdx >= 0 && activeIdx < items.length) {
                select(items[activeIdx].getAttribute('data-value'));
            }
        } else if (e.key === 'Escape') {
            list.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) { if (!ss.contains(e.target)) list.style.display = 'none'; });
    render('');
}

function loadBranches(containerName) {
    var form = document.getElementById('clone-form-' + containerName);
    if (!form) return;

    var fields = form.querySelectorAll('.branch-fields');
    for (var i = 0; i < fields.length; i++) {
        fields[i].style.display = 'none';
    }

    var existingMsg = form.querySelector('.branches-status');
    if (existingMsg) existingMsg.remove();

    fetch('?page=branches&container=' + encodeURIComponent(containerName))
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(data) {
            if (!data || typeof data !== 'object') return;
            var foundAny = false;
            for (var path in data) {
                if (!data.hasOwnProperty(path)) continue;
                var hidden = form.querySelector('input.searchable-value[name="base_branch[' + path + ']"]');
                if (!hidden) continue;
                foundAny = true;
                var ss = hidden.closest('.searchable-select');
                var list = ss.querySelector('.searchable-list');
                var container = ss.closest('.branch-fields');
                list.innerHTML = '';
                var current = data[path].current || '';
                var branches = (data[path].branches && data[path].branches.length > 0) ? data[path].branches : [];
                if (!current) {
                    if (branches.indexOf('master') !== -1) {
                        current = 'master';
                    } else if (branches.indexOf('main') !== -1) {
                        current = 'main';
                    } else if (branches.length > 0) {
                        current = branches[0];
                    }
                }
                if (branches.length === 0 && current) {
                    branches.push(current);
                }
                branches.forEach(function(b) {
                    var opt = document.createElement('option');
                    opt.className = 'searchable-option';
                    opt.setAttribute('data-value', b);
                    list.appendChild(opt);
                });
                hidden.value = current;
                initSearchableSelect(ss);
                if (container) container.style.display = 'block';
            }
            if (!foundAny) {
                var msg = document.createElement('div');
                msg.className = 'branches-status';
                msg.style.cssText = 'color: var(--muted); font-size: 0.75rem; margin-top: 0.4rem; margin-left: 1.5rem;';
                msg.textContent = 'No git repositories found in volumes.';
                var checkboxGroup = form.querySelector('.checkbox-group');
                if (checkboxGroup) checkboxGroup.appendChild(msg);
            }
        })
        .catch(function(err) {
            console.error('Failed to load branches:', err);
            var msg = document.createElement('div');
            msg.className = 'branches-status';
            msg.style.cssText = 'color: var(--alert-error-fg); font-size: 0.75rem; margin-top: 0.4rem; margin-left: 1.5rem;';
            msg.textContent = 'Failed to load branches. Is the source container running?';
            var checkboxGroup = form.querySelector('.checkbox-group');
            if (checkboxGroup) checkboxGroup.appendChild(msg);
        });
}
</script>