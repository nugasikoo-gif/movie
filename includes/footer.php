    </div><!-- /.container -->
</main><!-- /.main-content -->

<!-- ===== FOOTER ===== -->
<footer class="footer" id="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-col footer-col-brand">
                <a href="<?php echo $basePath ?? ''; ?>index.php" class="footer-brand">
                    <i class="fa-solid fa-film footer-brand-icon" aria-hidden="true"></i>
                    <span>SineSugbo</span>
                </a>
                <p class="footer-desc">
                    A minimalist movie catalog and cinema discovery platform. Browse film synopses, explore genres, and discover classic and contemporary motion pictures.
                </p>
            </div>

            <!-- Navigation Column -->
            <div class="footer-col">
                <h2 class="footer-heading">Navigation</h2>
                <ul class="footer-links-list">
                    <li>
                        <a href="<?php echo $basePath ?? ''; ?>index.php">
                            <i class="fa-solid fa-chevron-right footer-link-icon" aria-hidden="true"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath ?? ''; ?>browse.php">
                            <i class="fa-solid fa-chevron-right footer-link-icon" aria-hidden="true"></i> Movies
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Project Details Column -->
            <div class="footer-col">
                <h2 class="footer-heading">About Project</h2>
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
                <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> School Project
            </div>
        </div>
    </div>
</footer>

<!-- ===== GLOBAL JAVASCRIPT ===== -->
<script src="<?php echo $basePath ?? ''; ?>js/main.js"></script>

<!-- Page-Specific Script if defined -->
<?php if (isset($pageScript)): ?>
    <script src="<?php echo $basePath ?? ''; ?>js/<?php echo $pageScript; ?>"></script>
<?php endif; ?>

</body>
</html>
