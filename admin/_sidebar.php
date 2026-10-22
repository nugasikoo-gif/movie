<?php
// Admin sidebar partial — include from any admin/* page.
// Expects $basePath to be set (e.g. '../') and $pageTitle.
$adminCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
$adminNav = [
    ['file' => 'index.php', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high'],
    ['file' => 'movies.php', 'label' => 'Movies', 'icon' => 'fa-film'],
    ['file' => 'genres.php', 'label' => 'Genres', 'icon' => 'fa-tags'],
    ['file' => 'reviews.php', 'label' => 'Reviews', 'icon' => 'fa-comments'],
    ['file' => 'ratings.php', 'label' => 'Ratings', 'icon' => 'fa-star'],
    ['file' => 'users.php', 'label' => 'Users', 'icon' => 'fa-users'],
];
?>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
    <div class="admin-sidebar-brand">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <span>SineSugbo Admin</span>
    </div>
    <nav class="admin-sidebar-nav">
        <ul>
            <?php foreach ($adminNav as $item): ?>
                <li>
                    <a href="<?php echo $basePath; ?>admin/<?php echo $item['file']; ?>"
                       class="<?php echo $adminCurrent === $item['file'] ? 'active' : ''; ?>"
                       <?php echo $adminCurrent === $item['file'] ? 'aria-current="page"' : ''; ?>>
                        <i class="fa-solid <?php echo $item['icon']; ?>" aria-hidden="true"></i>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="admin-sidebar-footer">
        <a href="<?php echo $basePath; ?>index.php" class="admin-sidebar-link">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Site
        </a>
        <a href="<?php echo $basePath; ?>logout.php" class="admin-sidebar-link">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout
        </a>
    </div>
</aside>
<button type="button" class="admin-sidebar-toggle" id="admin-sidebar-toggle" aria-label="Toggle admin navigation" aria-controls="admin-sidebar" aria-expanded="false">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
</button>