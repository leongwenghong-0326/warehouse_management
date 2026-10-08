<?php
/**
 * Admin User Management
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db();
$currentUser = current_user();

// 1. Handle POST Actions (Add, Edit, Delete, Reset Password)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf_or_abort();

    $action = $_POST['action'] ?? '';

    // Action: ADD USER
    if ($action === 'create') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'user'));
        $password = (string)($_POST['password'] ?? '');

        if ($fullName === '' || $username === '' || $password === '') {
            set_flash('error', 'All fields are required to create a new user.');
            redirect('admin/users.php');
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            $role = 'user';
        }

        if (strlen($password) < 6) {
            set_flash('error', 'Password must be at least 6 characters long.');
            redirect('admin/users.php');
        }

        // Check unique username
        $chk = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $chk->execute([$username]);
        if ($chk->fetch()) {
            set_flash('error', 'Username is already taken. Please choose another one.');
            redirect('admin/users.php');
        }

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('
                INSERT INTO users (full_name, username, password_hash, role, is_active)
                VALUES (?, ?, ?, ?, 1)
            ');
            $stmt->execute([$fullName, $username, $hash, $role]);

            set_flash('success', "User '{$username}' created successfully.");
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            set_flash('error', 'Failed to create user.');
        }

        redirect('admin/users.php');
    }

    // Action: EDIT USER
    if ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'user'));
        $password = (string)($_POST['password'] ?? '');

        if ($userId <= 0 || $fullName === '' || $username === '') {
            set_flash('error', 'Please provide required user details.');
            redirect('admin/users.php');
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            $role = 'user';
        }

        // Prevent admin from revoking their own admin role to prevent system lockout
        if ($userId === $currentUser['id'] && $role !== 'admin') {
            set_flash('error', 'You cannot downgrade your own administrator account role.');
            redirect('admin/users.php');
        }

        // Check unique username excluding current user
        $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $chk->execute([$username, $userId]);
        if ($chk->fetch()) {
            set_flash('error', 'Username is already registered to another account.');
            redirect('admin/users.php');
        }

        try {
            if ($password !== '') {
                if (strlen($password) < 6) {
                    set_flash('error', 'New password must be at least 6 characters long.');
                    redirect('admin/users.php');
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('
                    UPDATE users 
                    SET full_name = ?, username = ?, role = ?, password_hash = ?
                    WHERE id = ?
                ');
                $stmt->execute([$fullName, $username, $role, $hash, $userId]);
            } else {
                $stmt = $db->prepare('
                    UPDATE users 
                    SET full_name = ?, username = ?, role = ?
                    WHERE id = ?
                ');
                $stmt->execute([$fullName, $username, $role, $userId]);
            }

            set_flash('success', "User '{$username}' updated successfully.");
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            set_flash('error', 'Error updating user.');
        }

        redirect('admin/users.php');
    }

    // Action: DELETE USER
    if ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            set_flash('error', 'Invalid user selected.');
            redirect('admin/users.php');
        }

        // Rule: Administrator must not be allowed to delete their own account
        if ($userId === $currentUser['id']) {
            set_flash('error', 'Security Violation: You cannot delete your own currently logged-in administrator account.');
            redirect('admin/users.php');
        }

        // Check if user has associated stock movements
        $chk = $db->prepare('SELECT COUNT(*) AS total FROM stock_movements WHERE created_by = ?');
        $chk->execute([$userId]);
        $hasMovements = (int)($chk->fetch()['total'] ?? 0);

        if ($hasMovements > 0) {
            // Cannot delete due to foreign key integrity; deactivate or notify
            set_flash('error', "Cannot delete this user because they have recorded {$hasMovements} stock transactions. Foreign key constraint protects historical audit integrity.");
            redirect('admin/users.php');
        }

        try {
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            set_flash('success', 'User was successfully deleted.');
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            set_flash('error', 'Failed to delete user.');
        }

        redirect('admin/users.php');
    }
}

// 2. Fetch User List
$stmt = $db->query('SELECT id, full_name, username, role, is_active, created_at FROM users ORDER BY id ASC');
$usersList = $stmt->fetchAll();

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:18px; margin:0; font-weight:700;">System Accounts & Access Roles</h2>
        <p style="color:var(--text-secondary); margin:4px 0 0; font-size:13px;">Manage administrator privileges, staff access, and security credentials.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="addUserModal">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Add New User
    </button>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): ?>
                        <tr>
                            <td>#<?= (int)$u['id'] ?></td>
                            <td><strong><?= e($u['full_name']) ?></strong></td>
                            <td><code><?= e($u['username']) ?></code></td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-danger' : 'badge-secondary' ?>">
                                    <?= strtoupper(e($u['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success">Active</span>
                            </td>
                            <td><?= format_date($u['created_at'], 'Y-m-d') ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-secondary btn-edit-user"
                                    data-id="<?= (int)$u['id'] ?>"
                                    data-fullname="<?= e($u['full_name']) ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    data-role="<?= e($u['role']) ?>"
                                >
                                    Edit / Reset
                                </button>

                                <?php if ((int)$u['id'] !== $currentUser['id']): ?>
                                    <form method="POST" action="<?= url('admin/users.php') ?>" style="display:inline-block;" data-confirm="Are you sure you want to delete user '<?= e($u['username']) ?>'?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:12px; color:var(--text-muted); padding: 0 8px;">(Current Account)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal-backdrop" id="addUserModal">
    <div class="modal-box">
        <form method="POST" action="<?= url('admin/users.php') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h3 class="modal-title">Create New System User</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="add_full_name" name="full_name" class="form-control" required placeholder="e.g. John Doe">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_username">Username <span class="required">*</span></label>
                        <input type="text" id="add_username" name="username" class="form-control" required placeholder="e.g. jdoe">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_role">Role <span class="required">*</span></label>
                        <select id="add_role" name="role" class="form-control" required>
                            <option value="user">User (Read-only)</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_password">Initial Password <span class="required">*</span></label>
                    <input type="password" id="add_password" name="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal-backdrop" id="editUserModal">
    <div class="modal-box">
        <form method="POST" action="<?= url('admin/users.php') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="modal-header">
                <h3 class="modal-title">Edit User & Password</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_username">Username <span class="required">*</span></label>
                        <input type="text" id="edit_username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_role">Role <span class="required">*</span></label>
                        <select id="edit_role" name="role" class="form-control" required>
                            <option value="user">User (Read-only)</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_password">New Password</label>
                    <input type="password" id="edit_password" name="password" class="form-control" minlength="6" placeholder="Leave blank to keep current password">
                    <small style="color:var(--text-secondary); display:block; margin-top:4px;">
                        Only enter a password if you want to reset it for this user.
                    </small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-edit-user').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('edit_user_id').value = button.dataset.id;
            document.getElementById('edit_full_name').value = button.dataset.fullname;
            document.getElementById('edit_username').value = button.dataset.username;
            document.getElementById('edit_role').value = button.dataset.role;
            document.getElementById('edit_password').value = '';

            openModal('editUserModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
