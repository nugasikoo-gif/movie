<?php
/**
 * SineSugbo — Movie Details Page (Stage 4)
 * 
 * Displays comprehensive details for a single movie:
 * - High-resolution poster or clean placeholder
 * - Title, release year, runtime, and average rating
 * - Clickable genre tags
 * - Director and cast member specifications
 * - Full synopsis
 * - Interactive user ratings and reviews (Stage 4)
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
$userRating = null;
$userReview = null;

// --- Handle POST submissions (Rating & Review) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && $id > 0) {
    // Verify CSRF token
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $userId = getCurrentUserId();

        // --- Rating Submission ---
        if (isset($_POST['action']) && $_POST['action'] === 'rate') {
            $rating = (int) ($_POST['rating'] ?? 0);

            if ($rating >= 1 && $rating <= 10) {
                // Check if user already rated
                $checkStmt = $conn->prepare('SELECT id FROM ratings WHERE user_id = ? AND movie_id = ?');
                $checkStmt->bind_param('ii', $userId, $id);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    // Update existing rating
                    $updateStmt = $conn->prepare('UPDATE ratings SET rating = ?, created_at = CURRENT_TIMESTAMP WHERE user_id = ? AND movie_id = ?');
                    $updateStmt->bind_param('iii', $rating, $userId, $id);
                    $updateStmt->execute();
                    $updateStmt->close();
                    setFlashMessage('Your rating has been updated.', 'success');
                } else {
                    // Insert new rating
                    $insertStmt = $conn->prepare('INSERT INTO ratings (user_id, movie_id, rating) VALUES (?, ?, ?)');
                    $insertStmt->bind_param('iii', $userId, $id, $rating);
                    $insertStmt->execute();
                    $insertStmt->close();
                    setFlashMessage('Thank you for your rating!', 'success');
                }
                $checkStmt->close();

                // Redirect to prevent form resubmission
                header('Location: movie.php?id=' . $id);
                exit;
            } else {
                setFlashMessage('Invalid rating value.', 'error');
            }
        }

        // --- Review Submission ---
        if (isset($_POST['action']) && $_POST['action'] === 'review') {
            $reviewText = trim($_POST['review_text'] ?? '');

            if ($reviewText !== '' && strlen($reviewText) <= 5000) {
                // Check if user already reviewed
                $checkStmt = $conn->prepare('SELECT id FROM reviews WHERE user_id = ? AND movie_id = ?');
                $checkStmt->bind_param('ii', $userId, $id);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    // Update existing review
                    $updateStmt = $conn->prepare('UPDATE reviews SET review_text = ?, created_at = CURRENT_TIMESTAMP WHERE user_id = ? AND movie_id = ?');
                    $updateStmt->bind_param('sii', $reviewText, $userId, $id);
                    $updateStmt->execute();
                    $updateStmt->close();
                    setFlashMessage('Your review has been updated.', 'success');
                } else {
                    // Insert new review
                    $insertStmt = $conn->prepare('INSERT INTO reviews (user_id, movie_id, review_text) VALUES (?, ?, ?)');
                    $insertStmt->bind_param('iis', $userId, $id, $reviewText);
                    $insertStmt->execute();
                    $insertStmt->close();
                    setFlashMessage('Thank you for your review!', 'success');
                }
                $checkStmt->close();

                // Redirect to prevent form resubmission
                header('Location: movie.php?id=' . $id);
                exit;
            } else {
                setFlashMessage('Review cannot be empty or exceed 5000 characters.', 'error');
            }
        }

        // --- Review Deletion ---
        if (isset($_POST['action']) && $_POST['action'] === 'delete_review') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);

            if ($reviewId > 0) {
                // Verify ownership
                $checkStmt = $conn->prepare('SELECT id FROM reviews WHERE id = ? AND user_id = ? AND movie_id = ?');
                $checkStmt->bind_param('iii', $reviewId, $userId, $id);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $deleteStmt = $conn->prepare('DELETE FROM reviews WHERE id = ?');
                    $deleteStmt->bind_param('i', $reviewId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                    setFlashMessage('Your review has been deleted.', 'success');
                } else {
                    setFlashMessage('You can only delete your own reviews.', 'error');
                }
                $checkStmt->close();

                header('Location: movie.php?id=' . $id);
                exit;
            }
        }
    } else {
        setFlashMessage('Invalid form submission. Please try again.', 'error');
    }
}

// --- Fetch movie data ---
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

        // 3. Fetch existing user reviews (with ratings)
        $reviewStmt = $conn->prepare("
            SELECT rv.id, rv.user_id, rv.review_text, rv.created_at, u.username,
                   r.rating
            FROM reviews rv
            JOIN users u ON rv.user_id = u.id
            LEFT JOIN ratings r ON r.movie_id = rv.movie_id AND r.user_id = rv.user_id
            WHERE rv.movie_id = ?
            ORDER BY rv.created_at DESC
            LIMIT 100
        ");
        $reviewStmt->bind_param("i", $id);
        $reviewStmt->execute();
        $reviewResult = $reviewStmt->get_result();
        while ($rv = $reviewResult->fetch_assoc()) {
            $reviews[] = $rv;
        }
        $reviewStmt->close();

        // 4. Fetch current user's rating and review (if logged in)
        if (isLoggedIn()) {
            $userId = getCurrentUserId();

            // User's rating
            $ratingStmt = $conn->prepare('SELECT rating FROM ratings WHERE user_id = ? AND movie_id = ?');
            $ratingStmt->bind_param('ii', $userId, $id);
            $ratingStmt->execute();
            $ratingResult = $ratingStmt->get_result();
            if ($row = $ratingResult->fetch_assoc()) {
                $userRating = (int) $row['rating'];
            }
            $ratingStmt->close();

            // User's review
            $reviewStmt = $conn->prepare('SELECT id, review_text FROM reviews WHERE user_id = ? AND movie_id = ?');
            $reviewStmt->bind_param('ii', $userId, $id);
            $reviewStmt->execute();
            $reviewResult = $reviewStmt->get_result();
            if ($row = $reviewResult->fetch_assoc()) {
                $userReview = $row;
            }
            $reviewStmt->close();
        }
    }
}

// Set dynamic page title
$pageTitle = $movie ? $movie['title'] : 'Movie Not Found';

// --- Generate CSRF token for forms ---
$csrfToken = generateCsrfToken();

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== BACK NAVIGATION ===== -->
<div class="back-nav">
    <a href="browse.php" class="back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
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
                <i class="fa-solid fa-film" aria-hidden="true"></i>
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
                <i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo $year; ?>
            </span>
            <?php if (!empty($movie['runtime'])): ?>
                <span class="meta-pill">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i> <?php echo (int) $movie['runtime']; ?> min
                </span>
            <?php endif; ?>
            <span class="meta-pill text-accent">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
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
                        <i class="fa-solid fa-tag" aria-hidden="true"></i>
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

<!-- ===== RATINGS & REVIEWS SECTION (Stage 4) ===== -->
<section class="reviews-section" id="reviews-section">
    <div class="section-header">
        <h2>
            <i class="fa-solid fa-comments section-header-icon" aria-hidden="true"></i>
            User Ratings & Reviews
        </h2>
        <span class="badge badge-subtle">
            <?php echo count($reviews); ?> Review<?php echo count($reviews) === 1 ? '' : 's'; ?>
        </span>
    </div>

    <?php if (isLoggedIn()): ?>
        <!-- ===== USER RATING INPUT ===== -->
        <div class="user-rating-card">
            <h3 class="user-rating-title">
                <i class="fa-solid fa-star" aria-hidden="true"></i> Your Rating
            </h3>
            <form action="movie.php?id=<?php echo $id; ?>" method="POST" class="rating-form" id="rating-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="rate">
                
                <div class="star-rating-input" role="radiogroup" aria-label="Rate this movie 1 to 10 stars">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <input type="radio" name="rating" id="star-<?php echo $i; ?>" value="<?php echo $i; ?>" 
                               <?php echo $userRating === $i ? 'checked' : ''; ?> class="star-input" aria-label="<?php echo $i; ?> stars"
                               <?php echo $i === 1 ? 'required' : ''; ?>>
                        <label for="star-<?php echo $i; ?>" class="star-label" title="<?php echo $i; ?>/10">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                        </label>
                    <?php endfor; ?>
                </div>
                
                <?php if ($userRating !== null): ?>
                    <p class="current-rating-note">
                        Your current rating: <strong><?php echo $userRating; ?>/10</strong>
                        (select a different star to update)
                    </p>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit Rating
                </button>
            </form>
        </div>

        <!-- ===== USER REVIEW INPUT ===== -->
        <div class="user-review-card">
            <h3 class="user-review-title">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Your Review
            </h3>
            
            <?php if ($userReview !== null): ?>
                <!-- User has existing review - show edit/delete form -->
                <form action="movie.php?id=<?php echo $id; ?>" method="POST" class="review-form" id="review-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="review">
                    
                    <div class="form-group">
                        <label for="review_text" class="form-label">Edit your review</label>
                        <textarea id="review_text" name="review_text" class="form-textarea" rows="5" 
                                  maxlength="5000" required
                                  aria-describedby="review-char-count"><?php echo sanitize($userReview['review_text']); ?></textarea>
                        <span class="form-hint" id="review-char-count">Maximum 5000 characters. Current: <span id="char-count" aria-live="polite"><?php echo strlen($userReview['review_text']); ?></span>/5000</span>
                    </div>
                    
                    <div class="review-form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-save" aria-hidden="true"></i> Update Review
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" id="cancel-edit">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="delete-review-btn">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                        </button>
                    </div>
                </form>
                
                <!-- Delete confirmation form (hidden) -->
                <form action="movie.php?id=<?php echo $id; ?>" method="POST" id="delete-review-form" class="hidden-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" value="<?php echo (int) $userReview['id']; ?>">
                </form>
                
            <?php else: ?>
                <!-- User has no review yet - show create form -->
                <form action="movie.php?id=<?php echo $id; ?>" method="POST" class="review-form" id="review-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="review">
                    
                    <div class="form-group">
                        <label for="review_text" class="form-label">Write your review</label>
                        <textarea id="review_text" name="review_text" class="form-textarea" rows="5" 
                                  maxlength="5000" placeholder="Share your thoughts on this movie..." required
                                  aria-describedby="review-char-count-new"></textarea>
                        <span class="form-hint" id="review-char-count-new">Maximum 5000 characters. <span id="char-count" aria-live="polite">0</span>/5000</span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit Review
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Not logged in - prompt to log in -->
        <div class="auth-prompt-card">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <div>
                <h3>Join the Conversation</h3>
                <p>Log in or create an account to rate movies and write reviews.</p>
                <div class="auth-prompt-actions">
                    <a href="login.php?redirect=movie.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Log In
                    </a>
                    <a href="register.php" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Sign Up
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== EXISTING REVIEWS LIST ===== -->
    <?php if (!empty($reviews)): ?>
        <div class="reviews-list mt-4">
            <?php foreach ($reviews as $rev): ?>
                <article class="review-card" id="review-<?php echo (int) $rev['id']; ?>">
                    <div class="review-header">
                        <span class="review-author">
                            <i class="fa-regular fa-user text-accent" aria-hidden="true"></i>
                            <strong><?php echo sanitize($rev['username']); ?></strong>
                            <?php if (isLoggedIn() && getCurrentUserId() === (int) $rev['user_id']): ?>
                                <span class="review-own-badge">You</span>
                            <?php endif; ?>
                        </span>
                        <span class="review-date">
                            <?php echo date('M d, Y', strtotime($rev['created_at'])); ?>
                        </span>
                    </div>
                    <?php if (!empty($rev['rating'])): ?>
                        <div class="card-rating mb-2">
                            <div class="review-star-display">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <i class="fa-solid fa-star <?php echo $i <= (int) $rev['rating'] ? 'filled' : ''; ?>" aria-hidden="true"></i>
                                <?php endfor; ?>
                            </div>
                            <span><?php echo (int) $rev['rating']; ?> / 10</span>
                        </div>
                    <?php endif; ?>
                    <p class="review-body">
                        <?php echo nl2br(sanitize($rev['review_text'])); ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted text-center py-4">No reviews yet. Be the first to share your thoughts!</p>
    <?php endif; ?>
</section>

<?php else: ?>
    <!-- ===== MOVIE NOT FOUND ERROR STATE ===== -->
    <div class="empty-state">
        <i class="fa-solid fa-triangle-exclamation empty-icon text-accent" aria-hidden="true"></i>
        <h1 class="empty-title">Movie Not Found</h1>
        <p class="empty-description">
            The film you are searching for does not exist in our catalog or the movie ID is invalid.
        </p>
        <a href="browse.php" class="btn btn-primary">
            <i class="fa-solid fa-film" aria-hidden="true"></i> Browse Movie Catalog
        </a>
    </div>
<?php endif; ?>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>

