<?php
/**
 * SineSugbo — Admin Reviews Moderation (Stage 5)
 * 
 * List and delete reviews.
 * Requires admin privileges.
 */

// --- Include foundation files ---
require_once '../includes/db.php';
require_once '../includes/auth.php';

// --- Require admin access ---
requireAdmin();

// --- Page metadata ---
$pageTitle = 'Moderate Reviews';
$basePath = '../';

// --- Generate CSRF token ---
$csrfToken = generateCsrfToken();

// --- Handle Actions ---
$action = $_GET['action'] ?? 'list';
$reviewId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// --- GET: Delete Review ---
if ($action === 'delete' && $reviewId > 0 && validateCsrfToken($_GET['csrf'] ?? '')) {
    $stmt = $conn->prepare('DELETE FROM reviews WHERE id = ?');
    $stmt->bind_param('i', $reviewId);
    if ($stmt->execute()) {
        setFlashMessage('Review deleted successfully.', 'success');
    } else {
        setFlashMessage('Failed to delete review.', 'error');
    }
    $stmt->close();
    header('Location: reviews.php');
    exit;
}

// --- Fetch Reviews List ---
$reviews = [];
$search = requestString($_GET, 'search', 100);
$where = '';
$params = [];
$types = '';

if ($search !== '') {
    $where = 'WHERE u.username LIKE ? OR m.title LIKE ? OR rv.review_text LIKE ?';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'sss';
}

$sql = "
    SELECT rv.id, rv.review_text, rv.created_at, u.username, u.email, m.title as movie_title, m.id as movie_id,
           r.rating
    FROM reviews rv
    JOIN users u ON rv.user_id = u.id
    JOIN movies m ON rv.movie_id = m.id
    LEFT JOIN ratings r ON r.movie_id = rv.movie_id AND r.user_id = rv.user_id
    $where
    ORDER BY rv.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

// --- Include Header ---
require_once '../includes/header.php';
require_once __DIR__ . '/_sidebar.php';
?>
<div class="admin-layout">

<!-- ===== ADMIN REVIEWS ===== -->
<div class="admin-container">
    <!-- Page Header -->
    <div class="admin-header">
        <div>
            <h1 class="admin-title">
                <i class="fa-solid fa-comments"></i> Moderate Reviews
            </h1>
            <p class="admin-subtitle">Review and moderate user-submitted reviews</p>
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

    <!-- Search Form -->
    <form action="reviews.php" method="GET" class="filter-panel" style="margin-bottom: 24px;">
        <div class="filter-form" style="flex-wrap: wrap; gap: 16px;">
            <div class="filter-field field-search" style="flex: 1; min-width: 280px;">
                <label for="admin-search">Search Reviews</label>
                <input type="text" id="admin-search" name="search" class="form-input"
                       value="<?php echo sanitize($search); ?>" placeholder="Search by user, movie, or content...">
            </div>
            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="reviews.php" class="btn btn-secondary align-self-end">
                    <i class="fa-solid fa-rotate-left"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Reviews Table -->
    <?php if (!empty($reviews)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <caption class="sr-only">All user reviews</caption>
                <th scope="col"ead>
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Movie</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Review</th>
                        <th scope="col">Date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $rev): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($rev['username']); ?></strong><br>
                                <span class="table-subtext"><?php echo sanitize($rev['email']); ?></span>
                            </td>
                            <td>
                                <a href="../movie.php?id=<?php echo (int) $rev['movie_id']; ?>" target="_blank" rel="noopener noreferrer" class="table-link">
                                    <?php echo sanitize($rev['movie_title']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($rev['rating'])): ?>
                                    <div class="review-star-display review-star-display-compact">
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <i class="fa-solid fa-star <?php echo $i <= (int) $rev['rating'] ? 'filled' : ''; ?>" aria-hidden="true"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-value"><?php echo (int) $rev['rating']; ?>/10</span>
                                <?php else: ?>
                                    <span class="text-muted">No rating</span>
                                <?php endif; ?>
                            </td>
                            <td class="review-preview"><?php echo sanitize(mb_substr($rev['review_text'], 0, 120)) . (mb_strlen($rev['review_text']) > 120 ? '…' : ''); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($rev['created_at'])); ?></td>
                            <td>
                                <a href="reviews.php?action=delete&amp;id=<?php echo (int) $rev['id']; ?>&amp;csrf=<?php echo urlencode($csrfToken); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this review? This cannot be undone.');" aria-label="Delete review by <?php echo sanitize($rev['username']); ?>" title="Delete">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-comments empty-icon"></i>
            <h2 class="empty-title">No Reviews Found</h2>
            <p class="empty-description">No reviews match your search criteria.</p>
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
