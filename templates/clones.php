<div class="page-header">
    <h2>Clones</h2>
</div>

<?php if (empty($clones)): ?>
    <div class="card">
        <p style="color: var(--muted); text-align: center; padding: 2rem;">No clones yet. Clone a container from the Containers page.</p>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>From</th>
                    <th>Status</th>
                    <th>Session</th>
                    <th>IP</th>
                    <th>Branches</th>
                    <th>Volumes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clones as $clone): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($clone['name']) ?></strong><br>
                            <span style="color: var(--muted); font-size: 0.75rem;">
                                <code><?= htmlspecialchars($clone['container_name']) ?></code>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($clone['source_container']) ?></td>
                        <td>
                            <span class="status status-<?= $clone['status'] ?>">
                                <span class="status-dot"></span>
                                <?= ucfirst(htmlspecialchars($clone['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($clone['status'] === 'running'): ?>
                                <?php if (!empty($clone['active_session'])): ?>
                                    <?php $ses = $clone['active_session']; ?>
                                    <strong title="<?= htmlspecialchars($ses['id']) ?>" style="font-size: 0.8rem;"><?= htmlspecialchars(strlen($ses['title']) > 30 ? substr($ses['title'], 0, 30) . '...' : $ses['title']) ?></strong>
                                    <br>
                                    <?php if (!empty($ses['is_active'])): ?>
                                        <span class="badge badge-blue">Running</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--badge-blue-bg); color: var(--badge-blue-fg); opacity: 0.6;">Idle</span>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline; margin-top: 0.25rem;">
                                        <input type="hidden" name="action" value="resume_session">
                                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($ses['id']) ?>">
                                        <input type="hidden" name="container" value="<?= htmlspecialchars($clone['container_name']) ?>">
                                        <input type="hidden" name="directory" value="<?= htmlspecialchars($ses['directory']) ?>">
                                        <input type="hidden" name="redirect" value="?page=clones">
                                        <button type="submit" class="btn btn-primary btn-sm" title="Resume session" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                            Resume
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <div style="margin-top: 0.3rem; display: flex; gap: 0.3rem; align-items: center; flex-wrap: wrap;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="start_session">
                                        <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                        <input type="hidden" name="redirect" value="?page=clones">
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Start new opencode session" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                            New
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline; display: flex; gap: 0.2rem; align-items: center;">
                                        <input type="hidden" name="action" value="set_session">
                                        <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                        <input type="hidden" name="redirect" value="?page=clones">
                                        <select name="session_id" class="form-control"
                                                style="width: 140px; font-size: 0.7rem; padding: 0.2rem 0.4rem;" required>
                                            <option value="">Select session...</option>
                                            <?php foreach ($clone['container_sessions'] as $cs): ?>
                                                <option value="<?= htmlspecialchars($cs['id']) ?>" title="<?= htmlspecialchars($cs['id']) ?>">
                                                    <?= htmlspecialchars(strlen($cs['title']) > 20 ? substr($cs['title'], 0, 20) . '...' : $cs['title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Set and resume session" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                            Set
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($clone['ip'])): ?>
                                <code><?= htmlspecialchars($clone['ip']) ?></code>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($clone['branches'])): ?>
                                <?php foreach ($clone['branches'] as $path => $info): ?>
                                    <span class="badge badge-blue"><?= htmlspecialchars($info['branch']) ?></span>
                                    <br>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($clone['volumes'])): ?>
                                <?php foreach ($clone['volumes'] as $hostPath => $containerPath): ?>
                                    <code><?= htmlspecialchars($containerPath) ?></code>
                                    <span style="color: var(--faint);">&larr;</span>
                                    <code style="color: var(--muted);"><?= htmlspecialchars(basename($hostPath)) ?></code>
                                    <br>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($clone['status'] === 'running'): ?>
                                    <button type="button" class="btn btn-ghost btn-sm" title="View logs"
                                            onclick="openLogs(<?= $clone['id'] ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="stop_clone">
                                        <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Stop">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="open_terminal">
                                        <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Open terminal">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="open_editor">
                                        <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Open in editor">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="start_clone">
                                        <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="Start">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_clone">
                                    <input type="hidden" name="clone_id" value="<?= $clone['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete clone"
                                            onclick="return confirm('Delete this clone?')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div id="logs-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center; padding:2rem;">
    <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:0.75rem; width:100%; max-width:800px; max-height:80vh; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem; border-bottom:1px solid var(--border);">
            <strong id="logs-title">Container Logs</strong>
            <button type="button" class="btn btn-ghost btn-sm" onclick="closeLogs()" title="Close">&#10005;</button>
        </div>
        <div id="logs-content" class="logs" style="border:none; border-radius:0; flex:1; max-height:none;"></div>
    </div>
</div>

<script>
function openLogs(cloneId) {
    var modal = document.getElementById('logs-modal');
    var content = document.getElementById('logs-content');
    content.textContent = 'Loading logs...';
    modal.style.display = 'flex';
    fetch('?page=logs&id=' + cloneId)
        .then(function(r) { return r.text(); })
        .then(function(text) {
            content.textContent = text === '' ? 'No logs available.' : text;
            content.scrollTop = content.scrollHeight;
        })
        .catch(function() { content.textContent = 'Failed to load logs.'; });
}

function closeLogs() {
    document.getElementById('logs-modal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLogs();
});
</script>