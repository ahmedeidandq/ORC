<div class="page-header">
    <h2>Docker Images</h2>
</div>

<?php if (empty($imageList)): ?>
    <div class="card">
        <p style="color: var(--muted); text-align: center; padding: 2rem;">No committed images found.</p>
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
                <?php foreach ($imageList as $img): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($img['repo']) ?></strong><code style="margin-left: 0.4rem;">:<?= htmlspecialchars($img['tag']) ?></code></td>
                        <td><code><?= htmlspecialchars($img['id']) ?></code></td>
                        <td><code><?= htmlspecialchars($img['size']) ?></code></td>
                        <td><?= htmlspecialchars($img['created']) ?></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-primary btn-sm" title="Create container from this image"
                                        onclick="document.getElementById('image-form-<?= htmlspecialchars($img['id']) ?>').style.display = document.getElementById('image-form-<?= htmlspecialchars($img['id']) ?>').style.display === 'none' ? 'block' : 'none'">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                    Create
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="image" value="<?= htmlspecialchars($img['repo'] . ':' . $img['tag']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Remove image"
                                            onclick="return confirm('Remove image <?= htmlspecialchars($img['repo'] . ':' . $img['tag']) ?>?')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="image-form-<?= htmlspecialchars($img['id']) ?>" style="display: none;">
                        <td colspan="5">
                            <form method="POST" action="?page=images" style="background: var(--card-bg); padding: 1rem; border-radius: 0.5rem;">
                                <input type="hidden" name="action" value="create_from_image">
                                <input type="hidden" name="image" value="<?= htmlspecialchars($img['repo'] . ':' . $img['tag']) ?>">

                                <div class="form-group" style="display: inline-block; width: 220px; margin-right: 1rem; margin-bottom: 1rem;">
                                    <label>Container Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. my-app" required>
                                </div>

                                <div style="margin-bottom: 0.75rem;">
                                    <label style="font-size: 0.75rem; color: var(--muted); display: block; margin-bottom: 0.375rem;">Volume mounts (optional):</label>
                                    <div id="vol-mounts-<?= htmlspecialchars($img['id']) ?>">
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.4rem; align-items: center;">
                                            <input type="text" name="host_path[]" class="form-control" placeholder="/host/path" style="width: 200px;">
                                            <span style="color: var(--muted);">→</span>
                                            <input type="text" name="container_path[]" class="form-control" placeholder="/container/path" style="width: 200px;">
                                            <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()">✕</button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="addVolumeMount('<?= htmlspecialchars($img['id']) ?>')">+ Add volume</button>
                                </div>

                                <div style="margin-top: 0.5rem;">
                                    <button type="submit" class="btn btn-primary btn-sm">Create Container</button>
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
function addVolumeMount(imageId) {
    var container = document.getElementById('vol-mounts-' + imageId);
    if (!container) return;
    var row = document.createElement('div');
    row.style.cssText = 'display: flex; gap: 0.5rem; margin-bottom: 0.4rem; align-items: center;';
    row.innerHTML = '<input type="text" name="host_path[]" class="form-control" placeholder="/host/path" style="width: 200px;">'
        + '<span style="color: var(--muted);">→</span>'
        + '<input type="text" name="container_path[]" class="form-control" placeholder="/container/path" style="width: 200px;">'
        + '<button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()">✕</button>';
    container.appendChild(row);
}
</script>
