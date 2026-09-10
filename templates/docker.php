<div class="page-header">
    <h2>All Docker Containers</h2>
</div>

<?php if (empty($dockerContainers)): ?>
    <div class="card">
        <p style="color: var(--muted); text-align: center; padding: 2rem;">No containers found.</p>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Image</th>
                    <th>Status</th>
                    <th>Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dockerContainers as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['name']) ?></strong>
                            <?php if ($c['is_clone']): ?>
                                <span class="badge badge-blue">orc</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($c['image']) ?></code></td>
                        <td>
                            <span class="status status-<?= $c['running'] ? 'running' : 'stopped' ?>">
                                <span class="status-dot"></span>
                                <?= htmlspecialchars($c['status']) ?>
                            </span>
                        </td>
                        <td><code><?= htmlspecialchars($c['size'] ?? '') ?></code></td>
                        <td>
                            <div class="actions">
                                <?php if ($c['running']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="docker_stop">
                                        <input type="hidden" name="name" value="<?= htmlspecialchars($c['name']) ?>">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Stop">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="docker_start">
                                        <input type="hidden" name="name" value="<?= htmlspecialchars($c['name']) ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="Start">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="docker_rm">
                                    <input type="hidden" name="name" value="<?= htmlspecialchars($c['name']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete container"
                                            onclick="return confirm('Remove container <?= htmlspecialchars($c['name']) ?>?')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color: var(--muted); font-size: 0.75rem; margin-top: 0.5rem;">
            Deleting an <code>orc</code> container here only removes the Docker container; its clone record and /tmp volume stay (manage from the Clones page).
        </p>
    </div>
<?php endif; ?>

<hr style="border-color: var(--border); margin: 2rem 0;">

<div class="page-header">
    <h2>All Docker Images</h2>
</div>

<?php if (empty($dockerImages)): ?>
    <div class="card">
        <p style="color: var(--muted); text-align: center; padding: 2rem;">No images found.</p>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Size</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dockerImages as $img): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($img['repo']) ?></strong><code style="margin-left: 0.4rem;">:<?= htmlspecialchars($img['tag']) ?></code>
                            <?php if ($img['is_clone']): ?>
                                <span class="badge badge-blue">orc</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($img['id']) ?></code></td>
                        <td><code><?= htmlspecialchars($img['size']) ?></code></td>
                        <td><?= htmlspecialchars($img['created']) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="docker_rmi">
                                <input type="hidden" name="image" value="<?= htmlspecialchars($img['repo'] . ':' . $img['tag']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete image"
                                        onclick="return confirm('Remove image <?= htmlspecialchars($img['repo'] . ':' . $img['tag']) ?>?')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>