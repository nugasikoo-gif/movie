<?php
/**
 * SineSugbo — Header Component
 * 
 * Outputs HTML document head, meta tags, favicon, Font Awesome icons,
 * top navigation bar with integrated search, and session flash messages.
 * 
 * Usage: Include at the top of each page.
 *   $pageTitle = "Home"; // Optional
 *   require_once 'includes/header.php';
 */

// Include authentication helpers
require_once __DIR__ . '/auth.php';

// Set default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = 'SineSugbo';
}

// Compute base path (supports root pages or admin/ subfolder)
$basePath = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
    $basePath = '../';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SineSugbo — A clean, minimalist movie information database. Discover, rate, and review films.">
    <title><?php echo sanitize($pageTitle); ?> — SineSugbo</title>

    <!-- Custom Minimalist Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo $basePath; ?>assets/images/favicon.svg">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons (Monochrome & Consistent) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>css/style.css">
</head>
<body>

<!-- ===== SKIP TO CONTENT (keyboard / screen reader shortcut) ===== -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<!-- ===== NAVIGATION BAR ===== -->
<nav class="navbar" id="main-navbar" aria-label="Main navigation">
    <div class="container navbar-container">
        <!-- Left Side: Brand & Main Navigation -->
        <div class="navbar-left">
            <a href="<?php echo $basePath; ?>index.php" class="navbar-brand" id="navbar-brand">
                <i class="fa-solid fa-film navbar-brand-icon" aria-hidden="true"></i>
                <span>SineSugbo</span>
            </a>

            <ul class="navbar-nav">
                <li>
                    <a href="<?php echo $basePath; ?>index.php" 
                       class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' && $basePath === '' ? 'active' : ''; ?>"
                       <?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' && $basePath === '' ? 'aria-current="page"' : ''; ?>
                       id="nav-home">
                        Home
                    </a>
                </li>
                <li>
                    <a href="<?php echo $basePath; ?>browse.php" 
                       class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'browse.php' ? 'active' : ''; ?>"
                       <?php echo basename($_SERVER['SCRIPT_NAME']) === 'browse.php' ? 'aria-current="page"' : ''; ?>
                       id="nav-movies">
                        Movies
                    </a>
                </li>
            </ul>
        </div>

        <!-- Center: Integrated Search Bar -->
        <div class="navbar-search-wrapper">
            <form action="<?php echo $basePath; ?>browse.php" method="GET" class="navbar-search" role="search">
                <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
                <input type="text" name="search" placeholder="Search movies by title..." 
                       value="<?php echo sanitize($_GET['search'] ?? ''); ?>" 
                       autocomplete="off" aria-label="Search movies by title">
                <button type="submit" class="search-submit" aria-label="Submit search">
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        </div>

        <!-- Right Side: User Links & Mobile Toggle -->
        <div class="navbar-right">
            <ul class="navbar-account">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li>
                            <a href="<?php echo $basePath; ?>admin/index.php" 
                               class="nav-admin <?php echo strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'active' : ''; ?>"
                               id="nav-admin">
                                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                <span>Admin</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a href="<?php echo $basePath; ?>profile.php" 
                           class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'profile.php' ? 'active' : ''; ?>"
                           <?php echo basename($_SERVER['SCRIPT_NAME']) === 'profile.php' ? 'aria-current="page"' : ''; ?>
                           id="nav-profile">
                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>logout.php" id="nav-logout" class="nav-logout">
                            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo $basePath; ?>login.php" 
                           class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'login.php' ? 'active' : ''; ?>"
                           id="nav-login">
                           Login
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>register.php" 
                           class="btn btn-primary btn-sm <?php echo basename($_SERVER['SCRIPT_NAME']) === 'register.php' ? 'active' : ''; ?>"
                           id="nav-register">
                            Sign Up
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Mobile Hamburger Toggle -->
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-menu">
                <span class="menu-icon"></span>
                <span class="menu-icon"></span>
                <span class="menu-icon"></span>
            </button>
        </div>
    </div>

    <!-- Mobile Dropdown Menu -->
    <div class="navbar-mobile-menu" id="mobile-menu">
        <form action="<?php echo $basePath; ?>browse.php" method="GET" class="mobile-search" role="search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <label for="mobile-search-input" class="sr-only">Search movies by title</label>
            <input type="text" id="mobile-search-input" name="search" placeholder="Search movies by title..." 
                   value="<?php echo sanitize($_GET['search'] ?? ''); ?>" 
                   autocomplete="off">
            <button type="submit" aria-label="Submit search">
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
        <ul class="mobile-nav-links">
            <li>
                <a href="<?php echo $basePath; ?>index.php" class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' && $basePath === '' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house" aria-hidden="true"></i> Home
                </a>
            </li>
            <li>
                <a href="<?php echo $basePath; ?>browse.php" class="<?php echo basename($_SERVER['SCRIPT_NAME']) === 'browse.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-film" aria-hidden="true"></i> Movies
                </a>
            </li>
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>admin/index.php">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Admin Panel
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo $basePath; ?>profile.php">
                        <i class="fa-regular fa-user" aria-hidden="true"></i> Profile
                    </a>
                </li>
                <li>
                    <a href="<?php echo $basePath; ?>logout.php" class="mobile-logout">
                        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="<?php echo $basePath; ?>login.php">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Login
                    </a>
                </li>
                <li>
                    <a href="<?php echo $basePath; ?>register.php">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Sign Up
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- ===== MAIN CONTENT CONTAINER ===== -->
<main class="main-content" id="main-content">
    <div class="container">
        <?php
        // Display one-time flash notification if set
        $flash = getFlashMessage();
        if ($flash): ?>
            <div class="alert alert-<?php echo sanitize($flash['type']); ?>" id="flash-message"
                 role="alert" aria-live="<?php echo $flash['type'] === 'error' ? 'assertive' : 'polite'; ?>">
                <div class="alert-content">
                    <?php if ($flash['type'] === 'success'): ?>
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <?php elseif ($flash['type'] === 'error'): ?>
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <?php endif; ?>
                    <span><?php echo sanitize($flash['message']); ?></span>
                </div>
                <button type="button" class="alert-close" aria-label="Dismiss notification">&times;</button>
            </div>
        <?php endif; ?>
