    </div><!-- /.container -->
</main><!-- /.main-content -->

<!-- ===== FOOTER ===== -->
<footer class="footer" id="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-col footer-col-brand">
                <a href="<?php echo $basePath ?? ''; ?>index.php" class="footer-brand">
                    <i class="fa-solid fa-film footer-brand-icon"></i>
                    <span>SineSugbo</span>
                </a>
                <p class="footer-desc">
                    A minimalist movie catalog and cinema discovery platform. Browse film synopses, explore genres, and discover classic and contemporary motion pictures.
                </p>
            </div>

            <!-- Navigation Column -->
            <div class="footer-col">
                <h4 class="footer-heading">Navigation</h4>
                <ul class="footer-links-list">
                    <li>
                        <a href="<?php echo $basePath ?? ''; ?>index.php">
                            <i class="fa-solid fa-chevron-right footer-link-icon"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath ?? ''; ?>browse.php">
                            <i class="fa-solid fa-chevron-right footer-link-icon"></i> Movies
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Project Details Column -->
            <div class="footer-col">
                <h4 class="footer-heading">About Project</h4>
                <p class="footer-meta-text">
                    SineSugbo is an educational web development project built with PHP, MySQLi, and minimalist CSS. Designed with focus on clarity and responsive design.
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copy">
                &copy; <?php echo date('Y'); ?> <strong>SineSugbo</strong>. All rights reserved.
            </div>
            <div class="footer-academic-badge">
                <i class="fa-solid fa-graduation-cap"></i> School Project
            </div>
        </div>
    </div>
</footer>

<!-- ===== GLOBAL JAVASCRIPT ===== -->
<script>
/**
 * Mobile Navigation Toggle & Flash Notification Dismissal
 */
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function () {
            const isExpanded = menuToggle.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            menuToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });

        // Close dropdown when clicking a nav link
        mobileMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                menuToggle.classList.remove('active');
                mobileMenu.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // Auto-dismiss flash notifications smoothly after 4.5 seconds
    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        setTimeout(function () {
            flashMessage.style.opacity = '0';
            flashMessage.style.transform = 'translateY(-8px)';
            setTimeout(function () {
                flashMessage.remove();
            }, 300);
        }, 4500);
    }
});
</script>

<!-- Page-Specific Script if defined -->
<?php if (isset($pageScript)): ?>
    <script src="<?php echo $basePath ?? ''; ?>js/<?php echo $pageScript; ?>"></script>
<?php endif; ?>

</body>
</html>
