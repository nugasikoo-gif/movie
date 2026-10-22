<?php
/**
 * SineSugbo — Admin Dashboard (Stage 5)
 * 
 * Overview dashboard with site statistics and quick actions.
 * Requires admin privileges.
 */

// --- Include foundation files ---
require_once '../includes/db.php';
require_once '../includes/auth.php';

// --- Require admin access ---
requireAdmin();

// --- Page metadata ---
$pageTitle = 'Admin Dashboard';
$basePath = '../';

// --- Fetch Statistics ---
$stats = [];

// Total movies
$result = $conn->query("SELECT COUNT(*) AS total FROM movies");
$stats['movies'] = $result->fetch_assoc()['total'];
$result->free();

// Total users
$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$stats['users'] = $result->fetch_assoc()['total'];
$result->free();

// Total reviews
$result = $conn->query("SELECT COUNT(*) AS total FROM reviews");
$stats['reviews'] = $result->fetch_assoc()['total'];
$result->free();

// Total ratings
$result = $conn->query("SELECT COUNT(*) AS total FROM ratings");
$stats['ratings'] = $result->fetch_assoc()['total'];
$result->free();

// Recent movies (last 5)
$recentMovies = [];
$result = $conn->query("
    SELECT id, title, release_date, created_at 
    FROM movies 
    ORDER BY created_at DESC 
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentMovies[] = $row;
    }
    $result->free();
}

// Recent users (last 5)
$recentUsers = [];
$result = $conn->query("
    SELECT id, username, email, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
    $result->free();
}

// Recent reviews (last 5)
$recentReviews = [];
$result = $conn->query("
    SELECT rv.id, rv.movie_id, rv.review_text, rv.created_at, u.username, m.title
    FROM reviews rv
    JOIN users u ON rv.user_id = u.id
    JOIN movies m ON rv.movie_id = m.id
    ORDER BY rv.created_at DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentReviews[] = $row;
    }
    $result->free();
}

// --- Generate CSRF token ---
$csrfToken = generateCsrfToken();

// --- Include Header ---
require_once '../includes/header.php';
require_once __DIR__ . '/_sidebar.php';
?>
<div class="admin-layout">

<!-- ===== ADMIN DASHBOARD ===== -->
<div class="admin-container">
    <!-- Page Header -->
    <div class="admin-header">
        <div>
            <h1 class="admin-title">
                <i class="fa-solid fa-shield-halved"></i> Admin Dashboard
            </h1>
            <p class="admin-subtitle">Site overview and quick management actions</p>
        </div>
        <a href="../index.php" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Site
        </a>
    </div>

    <!-- Flash Messages are handled in header.php -->

    <!-- ===== STATS GRID ===== -->
    <div class="stats-grid">
        <article class="stat-card">
            <div class="stat-icon stat-movies">
                <i class="fa-solid fa-film"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo number_format($stats['movies']); ?></span>
                <span class="stat-label">Movies</span>
            </div>
            <a href="movies.php" class="stat-link" aria-label="Manage movies">
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="stat-card">
            <div class="stat-icon stat-users">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo number_format($stats['users']); ?></span>
                <span class="stat-label">Users</span>
            </div>
            <a href="users.php" class="stat-link" aria-label="Manage users">
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="stat-card">
            <div class="stat-icon stat-reviews">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo number_format($stats['reviews']); ?></span>
                <span class="stat-label">Reviews</span>
            </div>
            <a href="reviews.php" class="stat-link" aria-label="Manage reviews">
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>

        <article class="stat-card">
            <div class="stat-icon stat-ratings">
                <i class="fa-solid fa-star"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo number_format($stats['ratings']); ?></span>
                <span class="stat-label">Ratings</span>
            </div>
            <a href="ratings.php" class="stat-link" aria-label="View ratings">
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>
    </div>

    <!-- ===== QUICK ACTIONS ===== -->
    <section class="admin-section">
        <div class="section-header">
            <h2>
                <i class="fa-solid fa-bolt section-header-icon"></i>
                Quick Actions
            </h2>
        </div>
        <div class="quick-actions-grid">
            <a href="movies.php?action=add" class="quick-action-card">
                <i class="fa-solid fa-plus"></i>
                <span>Add New Movie</span>
            </a>
            <a href="movies.php" class="quick-action-card">
                <i class="fa-solid fa-list"></i>
                <span>Manage Movies</span>
            </a>
            <a href="users.php" class="quick-action-card">
                <i class="fa-solid fa-user-gear"></i>
                <span>Manage Users</span>
            </a>
            <a href="reviews.php" class="quick-action-card">
                <i class="fa-solid fa-comment-slash"></i>
                <span>Moderate Reviews</span>
            </a>
            <a href="genres.php" class="quick-action-card">
                <i class="fa-solid fa-tags"></i>
                <span>Manage Genres</span>
            </a>
        </div>
    </section>

    <!-- ===== RECENT ACTIVITY ===== -->
    <div class="admin-section-row">
        <!-- Recent Movies -->
        <section class="admin-section">
            <div class="section-header">
                <h2>
                    <i class="fa-solid fa-clock-rotate-left section-header-icon"></i>
                    Recent Movies
                </h2>
                <a href="movies.php" class="section-header-link">
                    <span>View All</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <?php if (!empty($recentMovies)): ?>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <caption class="sr-only">Recently added movies</caption>
                        <th scope="col"ead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Year</th>
                                <th scope="col">Added</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMovies as $movie): ?>
                                <tr>
                                    <td>
                                        <a href="../movie.php?id=<?php echo (int) $movie['id']; ?>" target="_blank" rel="noopener noreferrer" class="table-link">
                                            <?php echo sanitize($movie['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : '—'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($movie['created_at'])); ?></td>
                                    <td class="admin-table-actions">
                                        <a href="movies.php?action=edit&id=<?php echo (int) $movie['id']; ?>" class="btn btn-sm btn-secondary"
                                           aria-label="Edit <?php echo sanitize($movie['title']); ?>" title="Edit">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-film empty-icon" aria-hidden="true"></i>
                    <h3 class="empty-title">No movies yet</h3>
                    <p class="empty-description">Add your first movie to start building the catalog.</p>
                    <a href="movies.php?action=add" class="btn btn-primary">Add Movie</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent Users -->
        <section class="admin-section">
            <div class="section-header">
                <h2>
                    <i class="fa-solid fa-user-plus section-header-icon"></i>
                    Recent Users
                </h2>
                <a href="users.php" class="section-header-link">
                    <span>View All</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <?php if (!empty($recentUsers)): ?>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <caption class="sr-only">Recently registered users</caption>
                        <th scope="col"ead>
                            <tr>
                                <th scope="col">Username</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo sanitize($user['username']); ?></td>
                                    <td><?php echo sanitize($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo sanitize($user['role']); ?>">
                                            <?php echo ucfirst(sanitize($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-user-plus empty-icon" aria-hidden="true"></i>
                    <h3 class="empty-title">No users yet</h3>
                    <p class="empty-description">Registered accounts will appear here.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Recent Reviews -->
    <section class="admin-section">
        <div class="section-header">
            <h2>
                <i class="fa-solid fa-comment-dots section-header-icon"></i>
                Recent Reviews
            </h2>
            <a href="reviews.php" class="section-header-link">
                <span>Moderate All</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <?php if (!empty($recentReviews)): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <caption class="sr-only">Most recent user reviews</caption>
                    <th scope="col"ead>
                        <tr>
                            <th scope="col">User</th>
                            <th scope="col">Movie</th>
                            <th scope="col">Preview</th>
                            <th scope="col">Date</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReviews as $review): ?>
                            <tr>
                                <td><?php echo sanitize($review['username']); ?></td>
                                <td>
                                    <a href="../movie.php?id=<?php echo (int) $review['movie_id']; ?>" target="_blank" rel="noopener noreferrer" class="table-link">
                                        <?php echo sanitize($review['title']); ?>
                                    </a>
                                </td>
                                <td class="review-preview"><?php echo sanitize(mb_substr($review['review_text'], 0, 80)) . (mb_strlen($review['review_text']) > 80 ? '…' : ''); ?></td>
                                <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                <td class="admin-table-actions">
                                    <a href="reviews.php?action=delete&amp;id=<?php echo (int) $review['id']; ?>&amp;csrf=<?php echo urlencode($csrfToken); ?>"
                                       class="btn btn-sm btn-danger"
                                       aria-label="Delete review by <?php echo sanitize($review['username']); ?>"
                                       title="Delete"
                                       onclick="return confirm('Delete this review?');">
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
                <i class="fa-solid fa-comment-dots empty-icon" aria-hidden="true"></i>
                <h3 class="empty-title">No reviews yet</h3>
                <p class="empty-description">User reviews will appear here once people start sharing their thoughts.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

</div>

<?php
// --- Include Footer ---
require_once '../includes/footer.php';

// --- Close database connection ---
$conn->close();
?>