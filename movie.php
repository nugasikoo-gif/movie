<?php
/**
 * SineSugbo — Movie Details Page (Stage 2)
 * 
 * Displays comprehensive details for a single movie:
 * - High-resolution poster or clean placeholder
 * - Title, release year, runtime, and average rating
 * - Clickable genre tags
 * - Director and cast member specifications
 * - Full synopsis
 * - Structure prepared for Stage 4 user ratings and reviews
 * - Error handling for missing or invalid movie IDs
 */

// --- Include foundation files ---
require_once 'includes/db.php';     // Database connection ($conn)
require_once 'includes/auth.php';   // Authentication and sanitization helpers

// --- Validate incoming movie ID ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$movie = null;
$genres = [];
$reviews = [];

if ($id > 0) {
    // 1. Fetch movie details with aggregated rating
    $stmt = $conn->prepare("
        SELECT m.id, m.title, m.synopsis, m.release_date, m.director, m.cast_members, m.runtime, m.poster,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               COUNT(DISTINCT r.id) AS rating_count
        FROM movies m
        LEFT JOIN ratings r ON m.id = r.movie_id
        WHERE m.id = ?
        GROUP BY m.id
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $movie = $result->fetch_assoc();
    $stmt->close();

    // 2. If movie exists, fetch associated genres
    if ($movie) {
        $genreStmt = $conn->prepare("
            SELECT g.id, g.name
            FROM genres g
            JOIN movie_genres mg ON g.id = mg.genre_id
            WHERE mg.movie_id = ?
            ORDER BY g.name ASC
        ");
        $genreStmt->bind_param("i", $id);
        $genreStmt->execute();
        $genreResult = $genreStmt->get_result();
        while ($g = $genreResult->fetch_assoc()) {
            $genres[] = $g;
        }
        $genreStmt->close();

        // 3. Fetch existing user reviews (if any exist in database)
        $reviewStmt = $conn->prepare("
            SELECT rv.id, rv.review_text, rv.created_at, u.username,
                   r.rating
            FROM reviews rv
            JOIN users u ON rv.user_id = u.id
            LEFT JOIN ratings r ON r.movie_id = rv.movie_id AND r.user_id = rv.user_id
            WHERE rv.movie_id = ?
            ORDER BY rv.created_at DESC
        ");
        $reviewStmt->bind_param("i", $id);
        $reviewStmt->execute();
        $reviewResult = $reviewStmt->get_result();
        while ($rv = $reviewResult->fetch_assoc()) {
            $reviews[] = $rv;
        }
        $reviewStmt->close();
    }
}

// Set dynamic page title
$pageTitle = $movie ? $movie['title'] : 'Movie Not Found';

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== BACK NAVIGATION ===== -->
<div class="back-nav">
    <a href="browse.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Catalog</span>
    </a>
</div>

<?php if ($movie): 
    $title = sanitize($movie['title']);
    $year = !empty($movie['release_date']) ? date('Y', strtotime($movie['release_date'])) : '—';
    $releaseDateFormatted = !empty($movie['release_date']) ? date('F j, Y', strtotime($movie['release_date'])) : 'Not specified';
    $runtimeMinutes = !empty($movie['runtime']) ? (int) $movie['runtime'] . ' minutes' : 'Not specified';
    $director = !empty($movie['director']) ? sanitize($movie['director']) : 'Not specified';
    $cast = !empty($movie['cast_members']) ? sanitize($movie['cast_members']) : 'Not specified';
    $synopsis = !empty($movie['synopsis']) ? sanitize($movie['synopsis']) : 'No detailed synopsis available for this title.';
    $rating = !empty($movie['avg_rating']) && (float) $movie['avg_rating'] > 0 
        ? number_format((float) $movie['avg_rating'], 1) 
        : null;
    $ratingCount = (int) $movie['rating_count'];
    $poster = $movie['poster'] ?? '';
    $posterPath = 'uploads/posters/' . $poster;
    $hasPoster = !empty($poster) && file_exists(__DIR__ . '/' . $posterPath);
?>

<!-- ===== MOVIE DETAIL HERO CARD ===== -->
<article class="movie-detail-card" id="movie-detail-<?php echo (int) $movie['id']; ?>">
    <!-- Movie Poster Container -->
    <div class="movie-detail-poster">
        <?php if ($hasPoster): ?>
            <img src="<?php echo sanitize($posterPath); ?>" alt="<?php echo $title; ?> Poster">
        <?php else: ?>
            <div class="poster-placeholder">
                <i class="fa-solid fa-film"></i>
                <span class="poster-placeholder-text"><?php echo $title; ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Movie Information -->
    <div class="movie-detail-info">
        <h1 class="movie-detail-title"><?php echo $title; ?></h1>

        <!-- Quick Meta Info Pills -->
        <div class="movie-detail-meta">
            <span class="meta-pill">
                <i class="fa-regular fa-calendar"></i> <?php echo $year; ?>
            </span>
            <?php if (!empty($movie['runtime'])): ?>
                <span class="meta-pill">
                    <i class="fa-regular fa-clock"></i> <?php echo (int) $movie['runtime']; ?> min
                </span>
            <?php endif; ?>
            <span class="meta-pill text-accent">
                <i class="fa-solid fa-star"></i>
                <?php if ($rating !== null): ?>
                    <strong><?php echo $rating; ?></strong> / 10 (<?php echo $ratingCount; ?> vote<?php echo $ratingCount === 1 ? '' : 's'; ?>)
                <?php else: ?>
                    <span>Unrated</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Genre Tags Row -->
        <?php if (!empty($genres)): ?>
            <div class="movie-genres-row">
                <?php foreach ($genres as $g): ?>
                    <a href="browse.php?genre=<?php echo (int) $g['id']; ?>" class="genre-tag">
                        <i class="fa-solid fa-tag"></i>
                        <span><?php echo sanitize($g['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Synopsis Block -->
        <div class="movie-synopsis-block">
            <h2 class="movie-synopsis-heading">Synopsis</h2>
            <p class="movie-synopsis-text"><?php echo $synopsis; ?></p>
        </div>

        <!-- Specifications & Credits Grid -->
        <div class="movie-specs-grid">
            <div class="spec-item">
                <div class="spec-label">Director</div>
                <div class="spec-value"><?php echo $director; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Cast Members</div>
                <div class="spec-value"><?php echo $cast; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Release Date</div>
                <div class="spec-value"><?php echo $releaseDateFormatted; ?></div>
            </div>
            <div class="spec-item">
                <div class="spec-label">Runtime</div>
                <div class="spec-value"><?php echo $runtimeMinutes; ?></div>
            </div>
        </div>
    </div>
</article>

<!-- ===== RATINGS & REVIEWS SECTION (Prepared for Stage 4) ===== -->
<section class="reviews-section" id="reviews-section">
    <div class="section-header">
        <h2>
            <i class="fa-solid fa-comments section-header-icon"></i>
            User Ratings &amp; Reviews
        </h2>
        <span class="badge badge-subtle">
            <?php echo count($reviews); ?> Review<?php echo count($reviews) === 1 ? '' : 's'; ?>
        </span>
    </div>

    <!-- Stage 4 Future Feature Preparation Card -->
    <div class="stage-notice-card">
        <i class="fa-solid fa-circle-info stage-notice-icon"></i>
        <div class="stage-notice-content">
            <h4>Community Reviews &amp; Interactive Rating</h4>
            <p>
                Interactive rating submissions and user review writing are scheduled for <strong>Stage 4</strong>. Once authenticated (in Stage 3), logged-in users will be able to submit star ratings and detailed reviews directly from this section.
            </p>
        </div>
    </div>

    <!-- Existing Reviews List (if any exist in database) -->
    <?php if (!empty($reviews)): ?>
        <div class="mt-3">
            <?php foreach ($reviews as $rev): ?>
                <div class="review-card">
                    <div class="review-header">
                        <span class="review-author">
                            <i class="fa-regular fa-user text-accent"></i>
                            <strong><?php echo sanitize($rev['username']); ?></strong>
                        </span>
                        <span class="review-date">
                            <?php echo date('M d, Y', strtotime($rev['created_at'])); ?>
                        </span>
                    </div>
                    <?php if (!empty($rev['rating'])): ?>
                        <div class="card-rating mb-1">
                            <i class="fa-solid fa-star"></i>
                            <span><?php echo (int) $rev['rating']; ?> / 10</span>
                        </div>
                    <?php endif; ?>
                    <p class="review-body">
                        <?php echo nl2br(sanitize($rev['review_text'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php else: ?>
    <!-- ===== MOVIE NOT FOUND ERROR STATE ===== -->
    <div class="empty-state">
        <i class="fa-solid fa-triangle-exclamation empty-icon text-accent"></i>
        <h2 class="empty-title">Movie Not Found</h2>
        <p class="empty-description">
            The film you are searching for does not exist in our catalog or the movie ID is invalid.
        </p>
        <a href="browse.php" class="btn btn-primary">
            <i class="fa-solid fa-film"></i> Browse Movie Catalog
        </a>
    </div>
<?php endif; ?>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>

