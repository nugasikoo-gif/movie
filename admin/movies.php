<?php
/**
 * SineSugbo — Admin Movies Management (Stage 5)
 * 
 * List, add, edit, and delete movies.
 * Requires admin privileges.
 */

// --- Include foundation files ---
require_once '../includes/db.php';
require_once '../includes/auth.php';

// --- Require admin access ---
requireAdmin();

// --- Page metadata ---
$pageTitle = 'Manage Movies';
$basePath = '../';

// --- Generate CSRF token ---
$csrfToken = generateCsrfToken();

// --- Upload configuration ---
const UPLOAD_DIR = __DIR__ . '/../uploads/posters/';
const UPLOAD_MAX_SIZE = 5 * 1024 * 1024; // 5 MB
const UPLOAD_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const UPLOAD_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];

/**
 * Validate and process uploaded poster file.
 * Returns array: ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function handlePosterUpload(array $file): array {
    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'filename' => null, 'error' => null]; // No file uploaded
        }
        return ['success' => false, 'filename' => null, 'error' => 'Upload failed with error code: ' . $file['error']];
    }

    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'filename' => null, 'error' => 'File exceeds 5 MB limit.'];
    }

    // Validate MIME type using finfo (not browser-provided)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Only JPEG, PNG, and WebP images are allowed.'];
    }

    // Validate extension matches MIME type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid file extension.'];
    }

    // Generate unique filename (cryptographically random, not time-based)
    $newFilename = 'poster_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $destPath = UPLOAD_DIR . $newFilename;

    // Ensure upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to save uploaded file.'];
    }

    // Set safe permissions
    chmod($destPath, 0644);

    return ['success' => true, 'filename' => $newFilename, 'error' => null];
}

/**
 * Delete a poster file if it exists and is not used by other movies.
 */
function deletePosterIfUnused(mysqli $conn, string $filename, int $excludeMovieId = 0): void {
    if ($filename === '') return;

    // Defensive: only ever act on a plain filename inside the upload dir
    $filename = basename($filename);
    if ($filename === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
        return;
    }

    // Check if any other movie uses this poster
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM movies WHERE poster = ? AND id != ?');
    $stmt->bind_param('si', $filename, $excludeMovieId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = (int) $result->fetch_assoc()['count'];
    $stmt->close();

    if ($count === 0) {
        $filePath = UPLOAD_DIR . $filename;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}

// --- Handle Actions ---
$action = $_GET['action'] ?? 'list';
$movieId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = [];

// --- POST: Add/Edit Movie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $synopsis = trim($_POST['synopsis'] ?? '');
    $releaseDate = $_POST['release_date'] ?? null;
    $director = trim($_POST['director'] ?? '');
    $castMembers = trim($_POST['cast_members'] ?? '');
    $runtime = !empty($_POST['runtime']) ? (int) $_POST['runtime'] : null;
    $genreIds = $_POST['genres'] ?? [];

    // Only keep numeric genre ids
    $genreIds = array_values(array_filter(array_map('intval', (array) $genreIds), function ($id) {
        return $id > 0;
    }));

    // Handle poster upload
    $uploadResult = handlePosterUpload($_FILES['poster'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
    if (!$uploadResult['success']) {
        $errors = [$uploadResult['error']];
    } else {
        $newPosterFilename = $uploadResult['filename'];
        $errors = [];
    }

    // Validation
    if (!isset($errors)) {
        $errors = [];
    }
    if ($title === '') $errors[] = 'Title is required.';
    if (strlen($title) > 200) $errors[] = 'Title cannot exceed 200 characters.';

    if (empty($errors)) {
        if ($postAction === 'add') {
            $poster = $newPosterFilename ?? '';
            
            $stmt = $conn->prepare("
                INSERT INTO movies (title, synopsis, release_date, director, cast_members, runtime, poster)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssss', $title, $synopsis, $releaseDate, $director, $castMembers, $runtime, $poster);
            
            if ($stmt->execute()) {
                $newMovieId = $stmt->insert_id;
                $stmt->close();

                // Assign genres
                if (!empty($genreIds)) {
                    $genreStmt = $conn->prepare('INSERT INTO movie_genres (movie_id, genre_id) VALUES (?, ?)');
                    foreach ($genreIds as $gid) {
                        $genreStmt->bind_param('ii', $newMovieId, $gid);
                        $genreStmt->execute();
                    }
                    $genreStmt->close();
                }

                setFlashMessage('Movie added successfully.', 'success');
                header('Location: movies.php');
                exit;
            } else {
                // Clean up uploaded file on DB failure
                if ($newPosterFilename) {
                    @unlink(UPLOAD_DIR . $newPosterFilename);
                }
                $errors[] = 'Failed to add movie.';
            }
            $stmt->close();
        } elseif ($postAction === 'edit' && $movieId > 0) {
            // Fetch current poster
            $currentPoster = '';
            $stmt = $conn->prepare('SELECT poster FROM movies WHERE id = ?');
            $stmt->bind_param('i', $movieId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $currentPoster = $row['poster'] ?? '';
            }
            $stmt->close();

            // Determine final poster filename
            $finalPoster = $currentPoster;
            $removePoster = isset($_POST['remove_poster']) && $_POST['remove_poster'] === '1';
            
            if ($removePoster) {
                $finalPoster = '';
            } elseif ($newPosterFilename !== null && $newPosterFilename !== '') {
                $finalPoster = $newPosterFilename;
            }

            $stmt = $conn->prepare("
                UPDATE movies 
                SET title = ?, synopsis = ?, release_date = ?, director = ?, cast_members = ?, runtime = ?, poster = ?
                WHERE id = ?
            ");
            $stmt->bind_param('sssssssi', $title, $synopsis, $releaseDate, $director, $castMembers, $runtime, $finalPoster, $movieId);
            
            if ($stmt->execute()) {
                $stmt->close();

                // Handle poster cleanup
                if ($removePoster && $currentPoster !== '') {
                    deletePosterIfUnused($conn, $currentPoster, $movieId);
                } elseif ($newPosterFilename !== null && $newPosterFilename !== '' && $currentPoster !== '') {
                    deletePosterIfUnused($conn, $currentPoster, $movieId);
                }

                // Update genres (delete old, insert new)
                $deleteGenres = $conn->prepare('DELETE FROM movie_genres WHERE movie_id = ?');
                $deleteGenres->bind_param('i', $movieId);
                $deleteGenres->execute();
                $deleteGenres->close();

                if (!empty($genreIds)) {
                    $genreStmt = $conn->prepare('INSERT INTO movie_genres (movie_id, genre_id) VALUES (?, ?)');
                    foreach ($genreIds as $gid) {
                        $genreStmt->bind_param('ii', $movieId, $gid);
                        $genreStmt->execute();
                    }
                    $genreStmt->close();
                }

                setFlashMessage('Movie updated successfully.', 'success');
                header('Location: movies.php');
                exit;
            } else {
                // Clean up new upload on DB failure
                if ($newPosterFilename) {
                    @unlink(UPLOAD_DIR . $newPosterFilename);
                }
                $errors[] = 'Failed to update movie.';
            }
            $stmt->close();
        }
    }

    if (!empty($errors)) {
        // Errors are rendered by the validation-errors block above.
    }
}

// --- GET: Delete Movie ---
if ($action === 'delete' && $movieId > 0 && validateCsrfToken($_GET['csrf'] ?? '')) {
    // Fetch poster before deleting
    $posterToDelete = '';
    $stmt = $conn->prepare('SELECT poster FROM movies WHERE id = ?');
    $stmt->bind_param('i', $movieId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $posterToDelete = $row['poster'] ?? '';
    }
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM movies WHERE id = ?');
    $stmt->bind_param('i', $movieId);
    if ($stmt->execute()) {
        // Delete poster file if unused by other movies
        if ($posterToDelete !== '') {
            deletePosterIfUnused($conn, $posterToDelete);
        }
        setFlashMessage('Movie deleted successfully.', 'success');
    } else {
        setFlashMessage('Failed to delete movie.', 'error');
    }
    $stmt->close();
    header('Location: movies.php');
    exit;
}

// --- Fetch Data for Edit Form ---
$movie = null;
$movieGenres = [];
if (($action === 'edit' || $action === 'delete') && $movieId > 0) {
    $stmt = $conn->prepare('SELECT * FROM movies WHERE id = ?');
    $stmt->bind_param('i', $movieId);
    $stmt->execute();
    $result = $stmt->get_result();
    $movie = $result->fetch_assoc();
    $stmt->close();

    if ($movie) {
        $genreStmt = $conn->prepare('SELECT genre_id FROM movie_genres WHERE movie_id = ?');
        $genreStmt->bind_param('i', $movieId);
        $genreStmt->execute();
        $genreResult = $genreStmt->get_result();
        while ($g = $genreResult->fetch_assoc()) {
            $movieGenres[] = (int) $g['genre_id'];
        }
        $genreStmt->close();
    }
}

// --- Fetch All Genres for Form ---
$allGenres = [];
$result = $conn->query('SELECT id, name FROM genres ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allGenres[] = $row;
    }
    $result->free();
}

// --- Fetch Movies List (for list view) ---
$movies = [];
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $where = '';
    $params = [];
    $types = '';

    if ($search !== '') {
        $where = 'WHERE m.title LIKE ?';
        $params[] = '%' . $search . '%';
        $types .= 's';
    }

    $sql = "
        SELECT m.id, m.title, m.release_date, m.director, m.runtime, m.poster, m.created_at,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               COUNT(DISTINCT r.id) AS rating_count,
               GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS genres
        FROM movies m
        LEFT JOIN ratings r ON m.id = r.movie_id
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id
        LEFT JOIN genres g ON mg.genre_id = g.id
        $where
        GROUP BY m.id
        ORDER BY m.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
    $stmt->close();
}

// --- Include Header ---
require_once '../includes/header.php';
require_once __DIR__ . '/_sidebar.php';
?>
<div class="admin-layout">

<!-- ===== ADMIN MOVIES ===== -->
<div class="admin-container">
    <!-- Page Header -->
    <div class="admin-header">
        <div>
            <h1 class="admin-title">
                <i class="fa-solid fa-film"></i> Manage Movies
            </h1>
            <p class="admin-subtitle">Add, edit, and remove movies from the catalog</p>
        </div>
        <?php if ($action === 'list'): ?>
            <a href="movies.php?action=add" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add Movie
            </a>
        <?php else: ?>
            <a href="movies.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        <?php endif; ?>
    </div>

    <!-- Validation Errors (the shared flash banner is rendered by the header) -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" aria-live="assertive">
            <div class="alert-content">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== LIST VIEW ===== -->
    <?php if ($action === 'list'): ?>
        
        <!-- Search Form -->
        <form action="movies.php" method="GET" class="filter-panel" style="margin-bottom: 24px;">
            <div class="filter-form" style="flex-wrap: wrap; gap: 16px;">
                <div class="filter-field field-search" style="flex: 1; min-width: 280px;">
                    <label for="admin-search">Search Movies</label>
                    <input type="text" id="admin-search" name="search" class="form-input"
                           value="<?php echo sanitize($search); ?>" placeholder="Search by title...">
                </div>
                <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
                <?php if ($search !== ''): ?>
                    <a href="movies.php" class="btn btn-secondary" style="align-self: flex-end;">
                        <i class="fa-solid fa-rotate-left"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Movies Table -->
        <?php if (!empty($movies)): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                <caption class="sr-only">All movies in the catalog</caption>
                    <th scope="col"ead>
                        <tr>
                            <th scope="col">Poster</th>
                            <th scope="col">Title</th>
                            <th scope="col">Year</th>
                            <th scope="col">Director</th>
                            <th scope="col">Runtime</th>
                            <th scope="col">Genres</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Added</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movies as $m): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $posterPath = 'uploads/posters/' . ($m['poster'] ?? '');
                                    $hasPoster = !empty($m['poster']) && file_exists(__DIR__ . '/../' . $posterPath);
                                    ?>
                                    <?php if ($hasPoster): ?>
                                        <img src="../<?php echo sanitize($posterPath); ?>" alt="" style="width: 50px; height: 75px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 75px; background: var(--bg-tertiary); border-radius: 4px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 0.7rem;">
                                            <i class="fa-solid fa-film"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../movie.php?id=<?php echo (int) $m['id']; ?>" target="_blank" class="table-link">
                                        <?php echo sanitize($m['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo $m['release_date'] ? date('Y', strtotime($m['release_date'])) : '—'; ?></td>
                                <td><?php echo sanitize($m['director'] ?? '—'); ?></td>
                                <td><?php echo $m['runtime'] ? (int) $m['runtime'] . ' min' : '—'; ?></td>
                                <td><?php echo sanitize($m['genres'] ?? '—'); ?></td>
                                <td>
                                    <?php if ((float) $m['avg_rating'] > 0): ?>
                                        <span style="color: var(--accent); font-weight: 600;"><?php echo number_format((float) $m['avg_rating'], 1); ?>/10</span>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">(<?php echo (int) $m['rating_count']; ?>)</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Unrated</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($m['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="movies.php?action=edit&id=<?php echo (int) $m['id']; ?>" class="btn btn-sm btn-secondary" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="movies.php?action=delete&id=<?php echo (int) $m['id']; ?>&csrf=<?php echo $csrfToken; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this movie? This cannot be undone.');" title="Delete">
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
                <i class="fa-solid fa-film empty-icon"></i>
                <h2 class="empty-title">No Movies Found</h2>
                <p class="empty-description">Start building your catalog by adding the first movie.</p>
                <a href="movies.php?action=add" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Movie
                </a>
            </div>
        <?php endif; ?>

    <!-- ===== ADD/EDIT FORM ===== -->
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    
    <div class="admin-form-card">
        <div class="admin-form-header">
            <h2 class="admin-form-title">
                <i class="fa-solid fa-<?php echo $action === 'add' ? 'plus' : 'pen'; ?>"></i> <?php echo ucfirst($action); ?> Movie
            </h2>
            <p class="admin-form-subtitle"><?php echo $action === 'add' ? 'Add a new movie to the catalog' : 'Edit movie details'; ?></p>
        </div>

        <form action="movies.php?action=<?php echo $action; ?><?php echo $movieId ? '&id=' . $movieId : ''; ?>" method="POST" class="admin-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="<?php echo $action; ?>">

            <!-- Title -->
            <div class="form-group">
                <label for="title" class="form-label">Title <span style="color: var(--danger);">*</span></label>
                <input type="text" id="title" name="title" class="form-input" value="<?php echo sanitize($movie['title'] ?? ''); ?>" required maxlength="200" autocomplete="off">
            </div>

            <!-- Synopsis -->
            <div class="form-group full-width">
                <label for="synopsis" class="form-label">Synopsis</label>
                <textarea id="synopsis" name="synopsis" class="form-textarea" rows="4"><?php echo sanitize($movie['synopsis'] ?? ''); ?></textarea>
            </div>

            <!-- Release Date & Director -->
            <div class="form-row">
                <div class="form-group">
                    <label for="release_date" class="form-label">Release Date</label>
                    <input type="date" id="release_date" name="release_date" class="form-input" value="<?php echo $movie['release_date'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label for="director" class="form-label">Director</label>
                    <input type="text" id="director" name="director" class="form-input" value="<?php echo sanitize($movie['director'] ?? ''); ?>" maxlength="100">
                </div>
            </div>

            <!-- Cast & Runtime -->
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="cast_members" class="form-label">Cast Members (comma-separated)</label>
                    <input type="text" id="cast_members" name="cast_members" class="form-input" value="<?php echo sanitize($movie['cast_members'] ?? ''); ?>" placeholder="Actor One, Actor Two, Actor Three...">
                </div>
                <div class="form-group">
                    <label for="runtime" class="form-label">Runtime (minutes)</label>
                    <input type="number" id="runtime" name="runtime" class="form-input" value="<?php echo $movie['runtime'] ?? ''; ?>" min="1" max="500" step="1">
                </div>
            </div>

            <!-- Genres -->
            <div class="form-group full-width">
                <label class="form-label">Genres</label>
                <div class="checkbox-group">
                    <?php foreach ($allGenres as $genre): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="genres[]" value="<?php echo (int) $genre['id']; ?>" <?php echo in_array((int) $genre['id'], $movieGenres) ? 'checked' : ''; ?>>
                            <span><?php echo sanitize($genre['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <span class="form-hint">Select one or more genres</span>
            </div>

            <!-- Poster Upload -->
            <div class="form-group full-width">
                <label class="form-label">Poster Image</label>
                <div class="poster-upload-wrapper">
                    <div class="poster-preview" id="poster-preview">
                        <?php if ($action === 'edit' && !empty($movie['poster'])): 
                            $previewPath = '../uploads/posters/' . $movie['poster'];
                            $hasPreview = file_exists(__DIR__ . '/../uploads/posters/' . $movie['poster']);
                        ?>
                            <?php if ($hasPreview): ?>
                                <img src="<?php echo sanitize($previewPath); ?>" alt="Current poster" id="preview-img">
                                <p class="poster-preview-caption">Current poster</p>
                            <?php else: ?>
                                <div class="poster-placeholder-small">
                                    <i class="fa-solid fa-film"></i>
                                    <span>Missing file</span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="poster-placeholder-small" id="placeholder-initial">
                                <i class="fa-solid fa-film"></i>
                                <span>No poster selected</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="poster-upload-controls">
                        <input type="file" name="poster" id="poster-upload" class="form-input-file" accept="image/jpeg,image/png,image/webp" aria-describedby="poster-hint">
                        <div class="upload-hint" id="poster-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>JPEG, PNG, or WebP • Max 5 MB</span>
                            <?php if ($action === 'edit' && !empty($movie['poster'])): ?>
                                <span class="hint-separator">•</span>
                                <span>Leave empty to keep current poster</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($action === 'edit' && !empty($movie['poster'])): ?>
                            <label class="checkbox-remove">
                                <input type="checkbox" name="remove_poster" id="remove-poster" value="1">
                                <span>Remove current poster</span>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="movies.php" class="btn btn-secondary">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-<?php echo $action === 'add' ? 'plus' : 'save'; ?>"></i> <?php echo ucfirst($action); ?> Movie
                </button>
            </div>
        </form>
    </div>

    <!-- ===== DELETE CONFIRMATION ===== -->
    <?php elseif ($action === 'delete' && $movie): ?>
    
    <div class="delete-confirmation">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2>Delete Movie</h2>
        <p>Are you sure you want to delete <strong><?php echo sanitize($movie['title']); ?></strong>?</p>
        <p style="font-size: 0.88rem; color: var(--warning);">This will also delete all associated ratings and reviews. This action cannot be undone.</p>
        <div class="form-actions">
            <a href="movies.php" class="btn btn-secondary">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
            <a href="movies.php?action=delete&id=<?php echo (int) $movie['id']; ?>&csrf=<?php echo $csrfToken; ?>" class="btn btn-danger">
                <i class="fa-solid fa-trash"></i> Yes, Delete
            </a>
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
