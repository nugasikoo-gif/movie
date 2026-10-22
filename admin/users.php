<?php
/**
 * SineSugbo — Admin Users Management (Stage 5)
 * 
 * List, edit roles, and delete users.
 * Requires admin privileges.
 */

// --- Include foundation files ---
require_once '../includes/db.php';
require_once '../includes/auth.php';

// --- Require admin access ---
requireAdmin();

// --- Page metadata ---
$pageTitle = 'Manage Users';
$basePath = '../';

// --- Generate CSRF token ---
$csrfToken = generateCsrfToken();

// --- Handle Actions ---
$action = $_GET['action'] ?? 'list';
$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$currentUserId = getCurrentUserId();

// --- POST: Edit User ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'edit_role' && $userId > 0) {
        // Prevent admin from demoting themselves
        if ($userId === $currentUserId) {
            setFlashMessage('You cannot change your own role.', 'error');
        } else {
            $newRole = $_POST['role'] ?? 'user';
            if (in_array($newRole, ['user', 'admin'])) {
                $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->bind_param('si', $newRole, $userId);
                if ($stmt->execute()) {
                    setFlashMessage('User role updated.', 'success');
                } else {
                    setFlashMessage('Failed to update role.', 'error');
                }
                $stmt->close();
            }
        }
        header('Location: users.php');
        exit;
    }
}

// --- GET: Delete User ---
if ($action === 'delete' && $userId > 0 && validateCsrfToken($_GET['csrf'] ?? '')) {
    // Prevent admin from deleting themselves
    if ($userId === $currentUserId) {
        setFlashMessage('You cannot delete your own account.', 'error');
    } else {
        // Check if user has reviews/ratings (foreign keys will cascade delete)
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            setFlashMessage('User deleted successfully.', 'success');
        } else {
            setFlashMessage('Failed to delete user.', 'error');
        }
        $stmt->close();
    }
    header('Location: users.php');
    exit;
}

// --- Fetch Users List ---
$users = [];
$search = requestString($_GET, 'search', 100);
$where = '';
$params = [];
$types = '';

if ($search !== '') {
    $where = 'WHERE username LIKE ? OR email LIKE ?';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}

$sql = "SELECT id, username, email, role, created_at FROM users $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// --- Fetch User for Edit ---
$user = null;
if ($action === 'edit' && $userId > 0) {
    $stmt = $conn->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

// --- Include Header ---
require_once '../includes/header.php';
require_once __DIR__ . '/_sidebar.php';
?>
<div class="admin-layout">

<!-- ===== ADMIN USERS ===== -->
<div class="admin-container">
    <!-- Page Header -->
    <div class="admin-header">
        <div>
            <h1 class="admin-title">
                <i class="fa-solid fa-users"></i> Manage Users
            </h1>
            <p class="admin-subtitle">View and manage user accounts</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php
    $flash = getFlashMessage();
    if ($flash): ?>
        <div class="alert alert-<?php echo sanitize($flash['type']); ?>" id="flash-message">
            <div class="alert-content">
                <?php if ($flash['type'] === 'success'): ?>
                    <i class="fa-solid fa-circle-check"></i>
                <?php elseif ($flash['type'] === 'error'): ?>
                    <i class="fa-solid fa-circle-exclamation"></i>
                <?php else: ?>
                    <i class="fa-solid fa-circle-info"></i>
                <?php endif; ?>
                <span><?php echo sanitize($flash['message']); ?></span>
            </div>
            <button class="alert-close" onclick="this.closest('.alert').remove()" aria-label="Close">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ===== LIST VIEW ===== -->
    <?php if ($action === 'list'): ?>
    
    <!-- Search Form -->
    <form action="users.php" method="GET" class="filter-panel" style="margin-bottom: 24px;">
        <div class="filter-form" style="flex-wrap: wrap; gap: 16px;">
            <div class="filter-field field-search" style="flex: 1; min-width: 280px;">
                <label for="admin-search">Search Users</label>
                <input type="text" id="admin-search" name="search" class="form-input"
                       value="<?php echo sanitize($search); ?>" placeholder="Search by username or email...">
            </div>
            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="users.php" class="btn btn-secondary" style="align-self: flex-end;">
                    <i class="fa-solid fa-rotate-left"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Users Table -->
    <?php if (!empty($users)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <caption class="sr-only">All registered users</caption>
                <th scope="col"ead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Username</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Joined</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo (int) $u['id']; ?></td>
                            <td>
                                <strong><?php echo sanitize($u['username']); ?></strong>
                                <?php if ((int) $u['id'] === $currentUserId): ?>
                                    <span class="role-badge role-admin" style="margin-left: 8px; font-size: 0.65rem;">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize($u['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo sanitize($u['role']); ?>">
                                    <?php echo ucfirst(sanitize($u['role'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <?php if ((int) $u['id'] !== $currentUserId): ?>
                                        <a href="users.php?action=edit&id=<?php echo (int) $u['id']; ?>" class="btn btn-sm btn-secondary" title="Edit Role">
                                            <i class="fa-solid fa-user-gear"></i>
                                        </a>
                                        <a href="users.php?action=delete&id=<?php echo (int) $u['id']; ?>&csrf=<?php echo $csrfToken; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user? This cannot be undone.');" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;">(current user)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-users empty-icon"></i>
            <h2 class="empty-title">No Users Found</h2>
            <p class="empty-description">No users match your search criteria.</p>
        </div>
    <?php endif; ?>

    <!-- ===== EDIT ROLE FORM ===== -->
    <?php elseif ($action === 'edit' && $user): ?>
    
    <div class="admin-form-card" style="max-width: 500px;">
        <div class="admin-form-header">
            <h2 class="admin-form-title">
                <i class="fa-solid fa-user-gear"></i> Edit User Role
            </h2>
            <p class="admin-form-subtitle">Change role for <strong><?php echo sanitize($user['username']); ?></strong></p>
        </div>

        <form action="users.php?action=edit_role&id=<?php echo (int) $user['id']; ?>" method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="edit_role">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-input" value="<?php echo sanitize($user['username']); ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-input" value="<?php echo sanitize($user['email']); ?>" disabled>
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Role <span style="color: var(--danger);">*</span></label>
                <select id="role" name="role" class="form-select" required>
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="users.php" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Update Role
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>
</div>

</div>

<?php
// --- Include Footer ---
require_once '../includes/footer.php';

// --- Close database connection ---
$conn->close();
?>
