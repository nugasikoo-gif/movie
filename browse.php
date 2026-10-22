<?php
/**
 * SineSugbo — Movie Listing & Catalog Page (Stage 2)
 * 
 * Features:
 * - Full movie catalog listing retrieved dynamically from MySQL
 * - Search by movie title
 * - Filter by genre
 * - Sort by release year, title (A-Z, Z-A), and ratings
 * - Empty state feedback when no records match
 * - Responsive pagination with preserved query parameters
 */

// --- Include foundation files ---
require_once 'includes/db.php';     // Database connection ($conn)
require_once 'includes/auth.php';   // Authentication and sanitization helpers

// --- Page title ---
$pageTitle = 'Browse Movies';

// --- Sanitize and validate request parameters ---
$search = requestString($_GET, 'search', 100);
$genreId = (int) ($_GET['genre'] ?? 0);
$sort = requestString($_GET, 'sort');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 12; // 12 items per page for clean 2/3/4/6 column responsive grid
$offset = ($page - 1) * $limit;

// Allowed sorting whitelist to prevent SQL injection
$allowedSorts = [
    'newest' => 'Newest First',
    'oldest' => 'Oldest First',
    'title_asc' => 'Title (A–Z)',
    'title_desc' => 'Title (Z–A)',
    'rating_desc' => 'Highest Rated',
    'popular' => 'Most Popular'
];

if ($sort === '' || !array_key_exists($sort, $allowedSorts)) {
    $sort = 'newest';
}

// Determine SQL ORDER BY clause based on validated sort key
$orderSql = match ($sort) {
    'oldest' => 'ORDER BY m.release_date ASC, m.id ASC',
    'title_asc' => 'ORDER BY m.title ASC',
    'title_desc' => 'ORDER BY m.title DESC',
    'rating_desc' => 'ORDER BY avg_rating DESC, m.title ASC',
    'popular' => 'ORDER BY rating_count DESC, avg_rating DESC, m.id DESC',
    default => 'ORDER BY m.release_date DESC, m.id DESC',
};

// =====================================================
// 1. Build Filter Conditions for Prepared Statements
// =====================================================
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "m.title LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($genreId > 0) {
    $where[] = "EXISTS (SELECT 1 FROM movie_genres mg WHERE mg.movie_id = m.id AND mg.genre_id = ?)";
    $params[] = $genreId;
    $types .= 'i';
}

$whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// =====================================================
// 2. Count Total Matching Movies (for Pagination)
// =====================================================
$countSql = "SELECT COUNT(DISTINCT m.id) AS total FROM movies m $whereClause";
$countStmt = $conn->prepare($countSql);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalMovies = (int) ($countResult->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int) ceil($totalMovies / $limit));
if ($page > $totalPages && $totalMovies > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// =====================================================
// 3. Fetch Movies with Ratings and Genres
// =====================================================
$dataSql = "
    SELECT m.id, m.title, m.release_date, m.director, m.poster,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(r.id) AS rating_count,
           GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS genres
    FROM movies m
    LEFT JOIN ratings r ON m.id = r.movie_id
    LEFT JOIN movie_genres mg_all ON m.id = mg_all.movie_id
    LEFT JOIN genres g ON mg_all.genre_id = g.id
    $whereClause
    GROUP BY m.id
    $orderSql
    LIMIT ? OFFSET ?
";

$dataParams = $params;
$dataParams[] = $limit;
$dataParams[] = $offset;
$dataTypes = $types . 'ii';

$stmt = $conn->prepare($dataSql);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$dataResult = $stmt->get_result();

$movies = [];
while ($row = $dataResult->fetch_assoc()) {
    $movies[] = $row;
}
$stmt->close();

// =====================================================
// 4. Fetch All Genres for Filter Dropdown
// =====================================================
$genresList = [];
$genresResult = $conn->query("SELECT id, name FROM genres ORDER BY name ASC");
if ($genresResult) {
    while ($g = $genresResult->fetch_assoc()) {
        $genresList[] = $g;
    }
    $genresResult->free();
}

// Check if any filters are actively applied
$hasActiveFilters = ($search !== '' || $genreId > 0 || $sort !== 'newest');

// Find active genre name if selected
$activeGenreName = '';
if ($genreId > 0) {
    foreach ($genresList as $g) {
        if ((int) $g['id'] === $genreId) {
            $activeGenreName = $g['name'];
            break;
        }
    }
}

// Helper: build pagination URL preserving existing query parameters
if (!function_exists('getPageUrl')) {
    function getPageUrl(int $targetPage): string {
        // Only carry over the parameters this page understands, so the
        // URL stays short and no untrusted value is echoed back.
        $params = [];

        $search = requestString($_GET, 'search', 100);
        if ($search !== '') {
            $params['search'] = $search;
        }

        $genre = (int) ($_GET['genre'] ?? 0);
        if ($genre > 0) {
            $params['genre'] = $genre;
        }

        $sort = requestString($_GET, 'sort');
        if ($sort !== '') {
            $params['sort'] = $sort;
        }

        $params['page'] = $targetPage;

        return 'browse.php?' . http_build_query($params);
    }
}

// Helper: page numbers to display, windowed around the current page
if (!function_exists('getPageWindow')) {
    function getPageWindow(int $current, int $total, int $window = 2): array {
        $start = max(1, $current - $window);
        $end = min($total, $current + $window);
        return range($start, $end);
    }
}

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== PAGE HEADER ===== -->
<div class="browse-header">
    <h1 class="browse-title">Movie Catalog</h1>
    <p class="browse-subtitle">
        Browse and discover motion pictures in the SineSugbo library. Use the search bar and filters below to refine your list.
    </p>
</div>

<!-- ===== SEARCH & FILTER PANEL ===== -->
<section class="filter-panel" aria-label="Search and filter movies">
    <form action="browse.php" method="GET" class="filter-form">
        <!-- Search Field -->
        <div class="filter-field field-search">
            <label for="filter-search">Search by Title</label>
            <input type="text" id="filter-search" name="search" placeholder="e.g. Inception, The Dark Knight..." 
                   value="<?php echo sanitize($search); ?>" autocomplete="off">
        </div>

        <!-- Genre Dropdown Filter -->
        <div class="filter-field">
            <label for="filter-genre">Genre</label>
            <select id="filter-genre" name="genre">
                <option value="0">All Genres</option>
                <?php foreach ($genresList as $g): ?>
                    <option value="<?php echo (int) $g['id']; ?>" <?php echo $genreId === (int) $g['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($g['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sort Option Dropdown -->
        <div class="filter-field">
            <label for="filter-sort">Sort By</label>
            <select id="filter-sort" name="sort">
                <?php foreach ($allowedSorts as $sortKey => $sortLabel): ?>
                    <option value="<?php echo $sortKey; ?>" <?php echo $sort === $sortKey ? 'selected' : ''; ?>>
                        <?php echo $sortLabel; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply Filters
            </button>
            <?php if ($hasActiveFilters): ?>
                <a href="browse.php" class="btn btn-secondary" title="Reset all filters">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- ===== RESULTS COUNT & ACTIVE FILTER META ===== -->
<div class="results-meta">
    <div class="results-count">
        Showing <strong><?php echo count($movies); ?></strong> of <strong><?php echo $totalMovies; ?></strong> movie<?php echo $totalMovies === 1 ? '' : 's'; ?>
        <?php if ($search !== ''): ?>
            for &ldquo;<strong><?php echo sanitize($search); ?></strong>&rdquo;
        <?php endif; ?>
        <?php if ($genreId > 0 && !empty($activeGenreName)): ?>
            in <strong><?php echo sanitize($activeGenreName); ?></strong>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="text-muted">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== MOVIES GRID OR EMPTY STATE ===== -->
<?php if (!empty($movies)): ?>
    <h2 class="sr-only">Movie results</h2>
    <div class="movie-grid">
        <?php foreach ($movies as $movie): 
            $mId = (int) $movie['id'];
            $mTitle = sanitize($movie['title']);
            $mYear = !empty($movie['release_date']) ? date('Y', strtotime($movie['release_date'])) : '—';
            $mRating = !empty($movie['avg_rating']) && (float) $movie['avg_rating'] > 0 
                ? number_format((float) $movie['avg_rating'], 1) 
                : null;
            $mPoster = $movie['poster'] ?? '';
            $mPosterPath = 'uploads/posters/' . $mPoster;
            $hasPoster = !empty($mPoster) && file_exists(__DIR__ . '/' . $mPosterPath);
        ?>
            <article class="movie-card" id="movie-card-<?php echo $mId; ?>">
                <a href="movie.php?id=<?php echo $mId; ?>" title="<?php echo $mTitle; ?>">
                    <div class="poster-container">
                        <?php if ($hasPoster): ?>
                            <img src="<?php echo sanitize($mPosterPath); ?>" alt="<?php echo $mTitle; ?> Poster" loading="lazy">
                        <?php else: ?>
                            <div class="poster-placeholder">
                                <i class="fa-solid fa-film" aria-hidden="true"></i>
                                <span class="poster-placeholder-text"><?php echo $mTitle; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><?php echo $mTitle; ?></h3>
                        <div class="card-meta">
                            <span class="card-year"><?php echo $mYear; ?></span>
                            <span class="card-rating">
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                <span><?php echo $mRating !== null ? $mRating : '—'; ?></span>
                            </span>
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- ===== PAGINATION CONTROLS ===== -->
    <?php if ($totalPages > 1): ?>
        <?php $pageWindow = getPageWindow($page, $totalPages); ?>
        <nav class="pagination" aria-label="Catalog pagination">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="<?php echo sanitize(getPageUrl($page - 1)); ?>" class="pagination-item" aria-label="Previous Page">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="pagination-item disabled" aria-disabled="true">
                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                </span>
            <?php endif; ?>

            <!-- Page Number Links (windowed so they never overflow on phones) -->
            <?php if ($pageWindow[0] > 1): ?>
                <a href="<?php echo sanitize(getPageUrl(1)); ?>" class="pagination-item">1</a>
                <?php if ($pageWindow[0] > 2): ?>
                    <span class="pagination-item disabled" aria-hidden="true">&hellip;</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php foreach ($pageWindow as $i): ?>
                <?php if ($i === $page): ?>
                    <span class="pagination-item active" aria-current="page"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo sanitize(getPageUrl($i)); ?>" class="pagination-item"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pageWindow[count($pageWindow) - 1] < $totalPages): ?>
                <?php if ($pageWindow[count($pageWindow) - 1] < $totalPages - 1): ?>
                    <span class="pagination-item disabled" aria-hidden="true">&hellip;</span>
                <?php endif; ?>
                <a href="<?php echo sanitize(getPageUrl($totalPages)); ?>" class="pagination-item"><?php echo $totalPages; ?></a>
            <?php endif; ?>

            <!-- Next Button -->
            <?php if ($page < $totalPages): ?>
                <a href="<?php echo sanitize(getPageUrl($page + 1)); ?>" class="pagination-item" aria-label="Next Page">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="pagination-item disabled" aria-disabled="true">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <!-- ===== EMPTY STATE ===== -->
    <div class="empty-state">
        <i class="fa-solid fa-film empty-icon" aria-hidden="true"></i>
        <h2 class="empty-title">No Movies Found</h2>
        <p class="empty-description">
            We couldn't find any films matching your search 
            <?php if ($search !== ''): ?>
                for &ldquo;<strong><?php echo sanitize($search); ?></strong>&rdquo;
            <?php endif; ?>
            <?php if ($genreId > 0 && !empty($activeGenreName)): ?>
                in the <strong><?php echo sanitize($activeGenreName); ?></strong> genre
            <?php endif; ?>. Try adjusting your query or resetting the filters.
        </p>
        <a href="browse.php" class="btn btn-primary">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Clear All Filters
        </a>
    </div>
<?php endif; ?>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>

