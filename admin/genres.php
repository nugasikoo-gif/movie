<?php
/**
 * SineSugbo — Admin Genres Management (Stage 5)
 * 
 * List, add, edit, and delete genres.
 * Requires admin privileges.
 */

// --- Include foundation files ---
require_once '../includes/db.php';
require_once '../includes/auth.php';

// --- Require admin access ---
requireAdmin();

// --- Page metadata ---
$pageTitle = 'Manage Genres';
$basePath = '../';

// --- Generate CSRF token ---
$csrfToken = generateCsrfToken();

// --- Handle Actions ---
$action = $_GET['action'] ?? 'list';
$genreId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$message = null;
$messageType = 'info';

// --- POST: Add/Edit Genre ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $message = 'Genre name is required.';
        $messageType = 'error';
    } elseif (strlen($name) > 50) {
        $message = 'Genre name cannot exceed 50 characters.';
        $messageType = 'error';
    } else {
        if ($postAction === 'add') {
            $stmt = $conn->prepare('INSERT INTO genres (name) VALUES (?)');
            $stmt->bind_param('s', $name);
            if ($stmt->execute()) {
                setFlashMessage('Genre added successfully.', 'success');
                header('Location: genres.php');
                exit;
            } else {
                $message = 'Failed to add genre (may already exist).';
                $messageType = 'error';
            }
            $stmt->close();
        } elseif ($postAction === 'edit' && $genreId > 0) {
            $stmt = $conn->prepare('UPDATE genres SET name = ? WHERE id = ?');
            $stmt->bind_param('si', $name, $genreId);
            if ($stmt->execute()) {
                setFlashMessage('Genre updated successfully.', 'success');
                header('Location: genres.php');
                exit;
            } else {
                $message = 'Failed to update genre.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// --- GET: Delete Genre ---
if ($action === 'delete' && $genreId > 0 && validateCsrfToken($_GET['csrf'] ?? '')) {
    // Check if genre is used by any movies
    $checkStmt = $conn->prepare('SELECT COUNT(*) as count FROM movie_genres WHERE genre_id = ?');
    $checkStmt->bind_param('i', $genreId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $count = $checkResult->fetch_assoc()['count'];
    $checkStmt->close();

    if ($count > 0) {
        setFlashMessage("Cannot delete genre: it is assigned to $count movie(s). Remove assignments first.", 'error');
    } else {
        $stmt = $conn->prepare('DELETE FROM genres WHERE id = ?');
        $stmt->bind_param('i', $genreId);
        if ($stmt->execute()) {
            setFlashMessage('Genre deleted successfully.', 'success');
        } else {
            setFlashMessage('Failed to delete genre.', 'error');
        }
        $stmt->close();
    }
    header('Location: genres.php');
    exit;
}

// --- Fetch Genre for Edit ---
$genre = null;
if (($action === 'edit' || $action === 'delete') && $genreId > 0) {
    $stmt = $conn->prepare('SELECT * FROM genres WHERE id = ?');
    $stmt->bind_param('i', $genreId);
    $stmt->execute();
    $result = $stmt->get_result();
    $genre = $result->fetch_assoc();
    $stmt->close();
}

// --- Fetch All Genres ---
$genres = [];
$result = $conn->query('SELECT g.*, COUNT(mg.movie_id) as movie_count FROM genres g LEFT JOIN movie_genres mg ON g.id = mg.genre_id GROUP BY g.id ORDER BY g.name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $genres[] = $row;
    }
    $result->free();
}

// --- Include Header ---
require_once '../includes/header.php';
require_once __DIR__ . '/_sidebar.php';
?>
<div class="admin-layout">

<!-- ===== ADMIN GENRES ===== -->
<div class="admin-container">
    <!-- Page Header -->
    <div class="admin-header">
        <div>
            <h1 class="admin-title">
                <i class="fa-solid fa-tags"></i> Manage Genres
            </h1>
            <p class="admin-subtitle">Add, edit, and remove movie genres</p>
        </div>
        <?php if ($action === 'list'): ?>
            <a href="genres.php?action=add" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Genre
            </a>
        <?php else: ?>
            <a href="genres.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        <?php endif; ?>
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

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" id="flash-message">
            <div class="alert-content">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo $message; ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== LIST VIEW ===== -->
    <?php if ($action === 'list'): ?>
    
    <?php if (!empty($genres)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <caption class="sr-only">All genres</caption>
                <th scope="col"ead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Movies</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($genres as $g): ?>
                        <tr>
                            <td><?php echo (int) $g['id']; ?></td>
                            <td>
                                <span class="genre-tag" style="cursor: default;">
                                    <i class="fa-solid fa-tag"></i>
                                    <span><?php echo sanitize($g['name']); ?></span>
                                </span>
                            </td>
                            <td>
                                <span style="color: var(--text-secondary);"><?php echo (int) $g['movie_count']; ?></span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="genres.php?action=edit&id=<?php echo (int) $g['id']; ?>" class="btn btn-sm btn-secondary" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="genres.php?action=delete&id=<?php echo (int) $g['id']; ?>&csrf=<?php echo $csrfToken; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this genre? This cannot be undone.');" title="Delete" <?php echo (int) $g['movie_count'] > 0 ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-tags empty-icon"></i>
            <h2 class="empty-title">No Genres Yet</h2>
            <p class="empty-description">Add your first genre to start categorizing movies.</p>
            <a href="genres.php?action=add" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Genre
            </a>
        </div>
    <?php endif; ?>

    <!-- ===== ADD/EDIT FORM ===== -->
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    
    <div class="admin-form-card" style="max-width: 500px;">
        <div class="admin-form-header">
            <h2 class="admin-form-title">
                <i class="fa-solid fa-<?php echo $action === 'add' ? 'plus' : 'pen'; ?>"></i> <?php echo ucfirst($action); ?> Genre
            </h2>
            <p class="admin-form-subtitle"><?php echo $action === 'add' ? 'Create a new genre' : 'Edit genre name'; ?></p>
        </div>

        <form action="genres.php?action=<?php echo $action; ?><?php echo $genreId ? '&id=' . $genreId : ''; ?>" method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="<?php echo $action; ?>">

            <div class="form-group">
                <label for="name" class="form-label">Genre Name <span style="color: var(--danger);">*</span></label>
                <input type="text" id="name" name="name" class="form-input" value="<?php echo sanitize($genre['name'] ?? ''); ?>" required maxlength="50" autocomplete="off">
                <span class="form-hint">Maximum 50 characters. Must be unique.</span>
            </div>

            <div class="form-actions">
                <a href="genres.php" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-<?php echo $action === 'add' ? 'plus' : 'save'; ?>"></i> <?php echo ucfirst($action); ?> Genre
                </button>
            </div>
        </form>
    </div>

    <!-- ===== DELETE CONFIRMATION ===== -->
    <?php elseif ($action === 'delete' && $genre): ?>
    
    <div class="delete-confirmation">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2>Delete Genre</h2>
        <p>Are you sure you want to delete <strong><?php echo sanitize($genre['name']); ?></strong>?</p>
        <?php 
        $checkStmt = $conn->prepare('SELECT COUNT(*) as count FROM movie_genres WHERE genre_id = ?');
        $checkStmt->bind_param('i', $genre['id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $count = $checkResult->fetch_assoc()['count'];
        $checkStmt->close();
        ?>
        <?php if ($count > 0): ?>
            <p style="color: var(--warning);">This genre is assigned to <strong><?php echo $count; ?></strong> movie(s). It cannot be deleted until all assignments are removed.</p>
        <?php else: ?>
            <p>This genre is not assigned to any movies.</p>
        <?php endif; ?>
        <div class="form-actions">
            <a href="genres.php" class="btn btn-secondary">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
            <?php if ($count === 0): ?>
                <a href="genres.php?action=delete&id=<?php echo (int) $genre['id']; ?>&csrf=<?php echo $csrfToken; ?>" class="btn btn-danger">
                    <i class="fa-solid fa-trash"></i> Yes, Delete
                </a>
            <?php endif; ?>
        </div>
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
