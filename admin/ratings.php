<?php
/**
 * SineSugbo — Admin Ratings Overview (Stage 5)
 * 
 * View all user ratings (read-only).
 * Requires admin privileges.
 */

// --- Include foundation files ---
require_once '../includes/db.php';
require_once '../includes/auth.php';

// --- Require admin access ---
requireAdmin();

// --- Page metadata ---
$pageTitle = 'View Ratings';
$basePath = '../';

// --- Generate CSRF token ---
$csrfToken = generateCsrfToken();

// --- Fetch Ratings List ---
$ratings = [];
$search = requestString($_GET, 'search', 100);
$where = '';
$params = [];
$types = '';

if ($search !== '') {
    $where = 'WHERE u.username LIKE ? OR m.title LIKE ?';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}

$sql = "
    SELECT r.id, r.rating, r.created_at, u.username, u.email, m.title as movie_title, m.id as movie_id
    FROM ratings r
    JOIN users u ON r.user_id = u.id
    JOIN movies m ON r.movie_id = m.id
    $where
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $ratings[] = $row;
}
$stmt->close();

// --- Statistics ---
$stats = [];
$result = $conn->query("
    SELECT 
        COUNT(*) as total,
        AVG(rating) as average,
        MIN(rating) as min_rating,
        MAX(rating) as max_rating
    FROM ratings
");
if ($result) {
    $stats = $result->fetch_assoc();
    $result->free();
}

// Rating distribution
$distribution = [];
for ($i = 1; $i <= 10; $i++) {
    $distribution[$i] = 0;
}
$result = $conn->query("SELECT rating, COUNT(*) as count FROM ratings GROUP BY rating");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $distribution[(int) $row['rating']] = (int) $row['count'];
    }
    $result->free();
}

// --- Include Header ---
require_once '../includes/header.php';
require_once __DIR__ . '/_sidebar.php';
?>
<div class="admin-layout">

<!-- ===== ADMIN RATINGS ===== -->
<div class="admin-container">
    <!-- Page Header -->
    <div class="admin-header">
        <div>
            <h1 class="admin-title">
                <i class="fa-solid fa-star"></i> User Ratings
            </h1>
            <p class="admin-subtitle">Overview of all user-submitted ratings</p>
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

    <!-- ===== STATS OVERVIEW ===== -->
    <div class="stats-grid" style="margin-bottom: 32px;">
        <article class="stat-card">
            <div class="stat-icon stat-ratings">
                <i class="fa-solid fa-star"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></span>
                <span class="stat-label">Total Ratings</span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-icon" style="background: var(--accent);">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['average'] ? number_format((float) $stats['average'], 1) : '—'; ?></span>
                <span class="stat-label">Average Rating</span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-icon" style="background: #10b981;">
                <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['max_rating'] ?? '—'; ?></span>
                <span class="stat-label">Highest Rating</span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-icon" style="background: #ef4444;">
                <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['min_rating'] ?? '—'; ?></span>
                <span class="stat-label">Lowest Rating</span>
            </div>
        </article>
    </div>

    <!-- ===== RATING DISTRIBUTION ===== -->
    <section class="admin-section" style="margin-bottom: 32px;">
        <div class="section-header">
            <h2>
                <i class="fa-solid fa-chart-bar section-header-icon"></i>
                Rating Distribution
            </h2>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <?php 
            $maxCount = max($distribution);
            for ($i = 10; $i >= 1; $i--): 
                $count = $distribution[$i];
                $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
            ?>
                <div style="flex: 1; min-width: 120px; max-width: 180px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="font-weight: 600; color: var(--accent); min-width: 30px; text-align: right;"><?php echo $i; ?></span>
                        <div style="flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $percentage; ?>%; height: 100%; background: var(--accent); border-radius: 4px; transition: width 0.3s ease;"></div>
                        </div>
                        <span style="color: var(--text-secondary); font-size: 0.8rem; min-width: 40px; text-align: right;"><?php echo $count; ?></span>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- ===== SEARCH FORM ===== -->
    <form action="ratings.php" method="GET" class="filter-panel" style="margin-bottom: 24px;">
        <div class="filter-form" style="flex-wrap: wrap; gap: 16px;">
            <div class="filter-field field-search" style="flex: 1; min-width: 280px;">
                <label for="admin-search">Search Ratings</label>
                <input type="text" id="admin-search" name="search" class="form-input"
                       value="<?php echo sanitize($search); ?>" placeholder="Search by user or movie...">
            </div>
            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="ratings.php" class="btn btn-secondary" style="align-self: flex-end;">
                    <i class="fa-solid fa-rotate-left"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ===== RATINGS TABLE ===== -->
    <?php if (!empty($ratings)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <caption class="sr-only">All user ratings</caption>
                <th scope="col"ead>
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Movie</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ratings as $r): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($r['username']); ?></strong><br>
                                <span style="font-size: 0.78rem; color: var(--text-muted);"><?php echo sanitize($r['email']); ?></span>
                            </td>
                            <td>
                                <a href="../movie.php?id=<?php echo (int) $r['movie_id']; ?>" target="_blank" class="table-link">
                                    <?php echo sanitize($r['movie_title']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="review-star-display" style="gap: 1px;">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <i class="fa-solid fa-star <?php echo $i <= (int) $r['rating'] ? 'filled' : ''; ?>" style="font-size: 0.85rem;"></i>
                                    <?php endfor; ?>
                                </div>
                                <span style="font-weight: 600; color: var(--accent); margin-left: 8px;"><?php echo (int) $r['rating']; ?>/10</span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-star empty-icon"></i>
            <h2 class="empty-title">No Ratings Found</h2>
            <p class="empty-description">No ratings match your search criteria.</p>
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
