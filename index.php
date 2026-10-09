<?php
/**
 * SineSugbo — Home Page (Stage 2)
 * 
 * Public landing page displaying:
 * - Minimalist hero banner
 * - Featured movie spotlight
 * - Popular movies responsive grid
 * - Recently added movies responsive grid
 * - Genre exploration tags
 */

// --- Include required foundation files ---
require_once 'includes/db.php';     // Database connection ($conn)
require_once 'includes/auth.php';   // Authentication and sanitization helpers

// --- Page metadata ---
$pageTitle = 'Home';

// --- Helper: Render a standardized movie card ---
if (!function_exists('renderMovieCard')) {
    function renderMovieCard(array $movie, string $basePath = ''): void {
        $id = (int) $movie['id'];
        $title = sanitize($movie['title']);
        $year = !empty($movie['release_date']) ? date('Y', strtotime($movie['release_date'])) : '—';
        $rating = !empty($movie['avg_rating']) && (float) $movie['avg_rating'] > 0 
            ? number_format((float) $movie['avg_rating'], 1) 
            : null;
        $poster = $movie['poster'] ?? '';
        $posterPath = 'uploads/posters/' . $poster;
        $hasPoster = !empty($poster) && file_exists(__DIR__ . '/' . $posterPath);
        ?>
        <article class="movie-card" id="movie-card-<?php echo $id; ?>">
            <a href="<?php echo $basePath; ?>movie.php?id=<?php echo $id; ?>" title="<?php echo $title; ?>">
                <div class="poster-container">
                    <?php if ($hasPoster): ?>
                        <img src="<?php echo $basePath . sanitize($posterPath); ?>" alt="<?php echo $title; ?> Poster" loading="lazy">
                    <?php else: ?>
                        <div class="poster-placeholder">
                            <i class="fa-solid fa-film"></i>
                            <span class="poster-placeholder-text"><?php echo $title; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?php echo $title; ?></h3>
                    <div class="card-meta">
                        <span class="card-year"><?php echo $year; ?></span>
                        <span class="card-rating">
                            <i class="fa-solid fa-star"></i>
                            <span><?php echo $rating !== null ? $rating : '—'; ?></span>
                        </span>
                    </div>
                </div>
            </a>
        </article>
        <?php
    }
}

// =====================================================
// 1. Fetch Featured Movie (Top pick with synopsis & genres)
// =====================================================
$featuredMovie = null;
$featuredSql = "
    SELECT m.id, m.title, m.synopsis, m.release_date, m.director, m.runtime, m.poster,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(r.id) AS rating_count,
           GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS genres
    FROM movies m
    LEFT JOIN ratings r ON m.id = r.movie_id
    LEFT JOIN movie_genres mg ON m.id = mg.movie_id
    LEFT JOIN genres g ON mg.genre_id = g.id
    GROUP BY m.id
    ORDER BY m.id ASC
    LIMIT 1
";
$featuredResult = $conn->query($featuredSql);
if ($featuredResult && $featuredResult->num_rows > 0) {
    $featuredMovie = $featuredResult->fetch_assoc();
    $featuredResult->free();
}

// =====================================================
// 2. Fetch Popular Movies (Ordered by rating or ID)
// =====================================================
$popularMovies = [];
$popularSql = "
    SELECT m.id, m.title, m.release_date, m.poster,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(r.id) AS rating_count
    FROM movies m
    LEFT JOIN ratings r ON m.id = r.movie_id
    GROUP BY m.id
    ORDER BY avg_rating DESC, m.id ASC
    LIMIT 5
";
$popularResult = $conn->query($popularSql);
if ($popularResult) {
    while ($row = $popularResult->fetch_assoc()) {
        $popularMovies[] = $row;
    }
    $popularResult->free();
}

// =====================================================
// 3. Fetch Recently Added Movies
// =====================================================
$recentMovies = [];
$recentSql = "
    SELECT m.id, m.title, m.release_date, m.poster,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(r.id) AS rating_count
    FROM movies m
    LEFT JOIN ratings r ON m.id = r.movie_id
    GROUP BY m.id
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT 5
";
$recentResult = $conn->query($recentSql);
if ($recentResult) {
    while ($row = $recentResult->fetch_assoc()) {
        $recentMovies[] = $row;
    }
    $recentResult->free();
}

// =====================================================
// 4. Fetch Available Genres
// =====================================================
$genres = [];
$genreSql = "SELECT id, name FROM genres ORDER BY name ASC";
$genreResult = $conn->query($genreSql);
if ($genreResult) {
    while ($row = $genreResult->fetch_assoc()) {
        $genres[] = $row;
    }
    $genreResult->free();
}

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== HERO BANNER ===== -->
<section class="hero" id="hero-section">
    <div class="hero-badge">
        <i class="fa-solid fa-film"></i>
        <span>Cinema Catalog &amp; Database</span>
    </div>
    <h1 class="hero-title">Welcome to <span class="text-accent">SineSugbo</span></h1>
    <p class="hero-subtitle">
        Explore curated motion pictures, discover timeless cinema classics, and browse films by genre, year, and director.
    </p>
</section>

<!-- ===== 1. FEATURED MOVIE SECTION ===== -->
<?php if ($featuredMovie): 
    $featId = (int) $featuredMovie['id'];
    $featTitle = sanitize($featuredMovie['title']);
    $featYear = !empty($featuredMovie['release_date']) ? date('Y', strtotime($featuredMovie['release_date'])) : '—';
    $featRuntime = !empty($featuredMovie['runtime']) ? (int) $featuredMovie['runtime'] . ' mins' : null;
    $featDirector = sanitize($featuredMovie['director'] ?? '');
    $featGenres = sanitize($featuredMovie['genres'] ?? '');
    $featRating = !empty($featuredMovie['avg_rating']) && (float) $featuredMovie['avg_rating'] > 0 
        ? number_format((float) $featuredMovie['avg_rating'], 1) 
        : null;
    $featPoster = $featuredMovie['poster'] ?? '';
    $featPosterPath = 'uploads/posters/' . $featPoster;
    $hasFeatPoster = !empty($featPoster) && file_exists(__DIR__ . '/' . $featPosterPath);
?>
<section class="featured-section" id="featured-movie-section">
    <div class="section-header">
        <h2>
            <i class="fa-solid fa-star section-header-icon"></i>
            Featured Movie
        </h2>
        <a href="movie.php?id=<?php echo $featId; ?>" class="section-header-link">
            <span>Read More</span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <div class="featured-card">
        <div class="featured-poster">
            <?php if ($hasFeatPoster): ?>
                <img src="<?php echo sanitize($featPosterPath); ?>" alt="<?php echo $featTitle; ?> Poster">
            <?php else: ?>
                <div class="poster-placeholder">
                    <i class="fa-solid fa-film"></i>
                    <span class="poster-placeholder-text"><?php echo $featTitle; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="featured-content">
            <span class="featured-badge">
                <i class="fa-solid fa-award"></i> Spotlight Selection
            </span>
            <h3 class="featured-title"><?php echo $featTitle; ?></h3>

            <div class="featured-meta">
                <span class="featured-meta-item">
                    <i class="fa-regular fa-calendar"></i> <?php echo $featYear; ?>
                </span>
                <?php if ($featRuntime): ?>
                    <span class="featured-meta-item">
                        <i class="fa-regular fa-clock"></i> <?php echo $featRuntime; ?>
                    </span>
                <?php endif; ?>
                <?php if ($featDirector): ?>
                    <span class="featured-meta-item">
                        <i class="fa-solid fa-video"></i> <?php echo $featDirector; ?>
                    </span>
                <?php endif; ?>
                <?php if ($featRating !== null): ?>
                    <span class="featured-meta-item text-accent">
                        <i class="fa-solid fa-star"></i> <?php echo $featRating; ?>/10
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($featGenres)): ?>
                <p class="text-muted mb-2">
                    <i class="fa-solid fa-tags text-accent"></i> <?php echo $featGenres; ?>
                </p>
            <?php endif; ?>

            <p class="featured-synopsis">
                <?php echo sanitize($featuredMovie['synopsis'] ?? 'No synopsis currently available for this film.'); ?>
            </p>

            <div class="featured-actions">
                <a href="movie.php?id=<?php echo $featId; ?>" class="btn btn-primary">
                    <i class="fa-solid fa-circle-info"></i> View Movie Details
                </a>
                <a href="browse.php" class="btn btn-secondary">
                    <i class="fa-solid fa-list-ul"></i> Browse All Titles
                </a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===== 2. POPULAR MOVIES SECTION ===== -->
<section class="mt-4 mb-4" id="popular-movies-section">
    <div class="section-header">
        <h2>
            <i class="fa-solid fa-fire-flame-curved section-header-icon"></i>
            Popular Movies
        </h2>
        <a href="browse.php?sort=popular" class="section-header-link">
            <span>Explore Catalog</span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php if (!empty($popularMovies)): ?>
        <div class="movie-grid">
            <?php foreach ($popularMovies as $movie): ?>
                <?php renderMovieCard($movie); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No movies found in database.</p>
    <?php endif; ?>
</section>

<!-- ===== 3. RECENTLY ADDED MOVIES SECTION ===== -->
<section class="mt-4 mb-4" id="recent-movies-section">
    <div class="section-header">
        <h2>
            <i class="fa-solid fa-clock-rotate-left section-header-icon"></i>
            Recently Added Movies
        </h2>
        <a href="browse.php?sort=newest" class="section-header-link">
            <span>View All Recent</span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php if (!empty($recentMovies)): ?>
        <div class="movie-grid">
            <?php foreach ($recentMovies as $movie): ?>
                <?php renderMovieCard($movie); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No recently added movies found.</p>
    <?php endif; ?>
</section>

<!-- ===== 4. BROWSE BY GENRE SECTION ===== -->
<?php if (!empty($genres)): ?>
<section class="mt-4 mb-4" id="genres-section">
    <div class="section-header">
        <h2>
            <i class="fa-solid fa-tags section-header-icon"></i>
            Explore by Genre
        </h2>
        <a href="browse.php" class="section-header-link">
            <span>All Categories</span>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <div class="genre-list" id="genre-list">
        <?php foreach ($genres as $genre): ?>
            <a href="browse.php?genre=<?php echo (int) $genre['id']; ?>" class="genre-tag">
                <i class="fa-solid fa-tag"></i>
                <span><?php echo sanitize($genre['name']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>
