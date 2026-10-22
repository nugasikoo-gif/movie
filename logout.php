<?php
/**
 * SineSugbo — User Logout (Stage 3)
 * 
 * Properly terminates the authenticated session:
 * - Clears all session data
 * - Destroys session cookie
 * - Redirects to home page with flash message
 */

// --- Include foundation files ---
require_once 'includes/db.php';
require_once 'includes/auth.php';

// --- Only process if user is logged in ---
if (isLoggedIn()) {
    // Store username for flash message
    $username = getCurrentUsername();

    // Clear all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();

    // Set flash message
    setFlashMessage(
        $username ? 'You have been logged out, ' . sanitize($username) . '. See you soon!' : 'You have been logged out.',
        'info'
    );
}

// --- Redirect to home page ---
header('Location: index.php');
exit;