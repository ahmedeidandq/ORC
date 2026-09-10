<div class="page-header">
    <h2>Opencode Sessions</h2>
</div>

<?php if (!empty($opencodeContainers)): ?>
    <div class="card">
        <div class="card-header">
            <span class="card-title">Opencode servers in containers</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Container</th>
                    <th>Address</th>
                    <th>Server</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($opencodeContainers as $oc): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($oc['name']) ?></strong>
                            <?php if ($oc['has_db']): ?>
                                <span class="badge badge-blue">db</span>
                            <?php endif; ?>
                            <?php if (!$oc['has_opencode']): ?>
                                <span class="badge badge-yellow">no binary</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($oc['ip'] !== ''): ?>
                                <code><?= htmlspecialchars($oc['ip']) ?>:<?= $oc['port'] ?></code>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($oc['has_opencode'] && $oc['ip'] !== ''): ?>
                                <?php if ($oc['server_up']): ?>
                                    <span class="status status-running"><span class="status-dot"></span> Running</span>
                                <?php else: ?>
                                    <span class="status status-stopped"><span class="status-dot"></span> Stopped</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--muted);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($oc['has_opencode']): ?>
                                    <?php if (!$oc['server_up']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="session_start_server">
                                            <input type="hidden" name="container" value="<?= htmlspecialchars($oc['name']) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Start opencode server">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                                Start
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($oc['ip'] !== ''): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="session_connect">
                                            <input type="hidden" name="container" value="<?= htmlspecialchars($oc['name']) ?>">
                                            <input type="hidden" name="ip" value="<?= htmlspecialchars($oc['ip']) ?>">
                                            <input type="hidden" name="port" value="<?= $oc['port'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Connect via opencode attach">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                                Connect
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="session_open_web">
                                            <input type="hidden" name="ip" value="<?= htmlspecialchars($oc['ip']) ?>">
                                            <input type="hidden" name="port" value="<?= $oc['port'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Open web UI">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                                                Web
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--muted); font-size: 0.75rem;">opencode not installed inside</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($sessionsError !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($sessionsError) ?></div>
<?php elseif (empty($sessions)): ?>
    <div class="card">
        <p style="color: var(--muted); text-align: center; padding: 2rem;">No opencode sessions found.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <span style="color: var(--muted); font-size: 0.8rem;">Select sessions to delete</span>
            <button type="button" class="btn btn-danger btn-sm" title="Delete selected sessions" onclick="bulkDeleteSessions()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Delete selected
            </button>
        </div>
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all" onchange="var cbs=document.querySelectorAll('.ses-cb'); for (var i=0;i<cbs.length;i++) cbs[i].checked=this.checked;"></th>
                    <th>Title</th>
                    <th>Source</th>
                    <th>Agent</th>
                    <th>Model</th>
                    <th>Directory</th>
                    <th>Updated</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s): ?>
                    <?php $isContainer = (strpos($s['source'], 'Container: ') === 0); ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="ses-cb"
                                   data-id="<?= htmlspecialchars($s['id']) ?>"
                                   data-container="<?= htmlspecialchars($isContainer ? ($s['container'] ?? substr($s['source'], 11)) : '') ?>">
                        </td>
                        <td>
                            <strong title="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($s['title']) ?></strong>
                            <?php if ($s['is_subagent']): ?>
                                <span class="badge badge-yellow">sub</span>
                            <?php endif; ?>
                            <br>
                            <span style="color: var(--muted); font-size: 0.7rem;"><?= htmlspecialchars($s['short_id']) ?></span>
                        </td>
                        <td>
                            <?php if (strpos($s['source'], 'Container: ') === 0): ?>
                                <span class="badge badge-blue"><?= htmlspecialchars(substr($s['source'], 11)) ?></span>
                            <?php else: ?>
                                <span class="badge badge-green">Host</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['agent'] ?? '') ?></td>
                        <td>
                            <code><?= htmlspecialchars($s['model_id']) ?></code>
                            <span style="color: var(--muted); font-size: 0.7rem;"><?= htmlspecialchars($s['model_provider']) ?></span>
                        </td>
                        <td><code style="font-size: 0.75rem;"><?= htmlspecialchars($s['directory']) ?></code></td>
                        <td><?= htmlspecialchars($s['updated']) ?></td>
                        <td>
                            <?php if ($s['archived']): ?>
                                <span class="badge badge-yellow">Archived</span>
                            <?php else: ?>
                                <span class="badge badge-green">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions" style="flex-wrap: nowrap;">
                                <?php $isContainer = (strpos($s['source'], 'Container: ') === 0); ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="resume_session">
                                    <input type="hidden" name="session_id" value="<?= htmlspecialchars($s['id']) ?>">
                                    <input type="hidden" name="directory" value="<?= htmlspecialchars($s['directory']) ?>">
                                    <?php if ($isContainer): ?>
                                        <input type="hidden" name="container" value="<?= htmlspecialchars($s['container'] ?? substr($s['source'], 11)) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary btn-sm" title="Open session in opencode">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-ghost btn-sm" title="Copy session id"
                                        onclick="navigator.clipboard.writeText('<?= htmlspecialchars($s['id']) ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_session">
                                    <input type="hidden" name="session_id" value="<?= htmlspecialchars($s['id']) ?>">
                                    <?php if ($isContainer): ?>
                                        <input type="hidden" name="container" value="<?= htmlspecialchars($s['container'] ?? substr($s['source'], 11)) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete session"
                                            onclick="return confirm('Delete session <?= htmlspecialchars($s['short_id']) ?>?')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
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

<script>
function bulkDeleteSessions() {
    var cbs = document.querySelectorAll('.ses-cb:checked');
    if (cbs.length === 0) { alert('No sessions selected.'); return; }
    if (!confirm('Delete the selected sessions?')) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = '?page=sessions';
    f.style.display = 'none';
    var a = document.createElement('input');
    a.type = 'hidden'; a.name = 'action'; a.value = 'bulk_delete_sessions';
    f.appendChild(a);
    cbs.forEach(function(cb) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'session_ids[]'; i.value = cb.getAttribute('data-id');
        f.appendChild(i);
        var c = document.createElement('input');
        c.type = 'hidden'; c.name = 'session_containers[]'; c.value = cb.getAttribute('data-container') || '';
        f.appendChild(c);
    });
    document.body.appendChild(f);
    f.submit();
}
</script>