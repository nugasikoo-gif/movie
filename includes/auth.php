<?php
/**
 * Authentication Helper Functions
 * 
 * This file provides helper functions for user authentication:
 * - Starting sessions safely
 * - Checking if a user is logged in
 * - Checking if a user is an admin
 * - Generating and validating CSRF tokens
 * - Redirecting with messages
 * 
 * Usage: require_once 'includes/auth.php';
 */

// ----- Start Session -----
// session_start() must be called before any HTML output.
// We check if a session is already active to avoid errors.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in.
 * 
 * @return bool True if the user is logged in, false otherwise.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the current user has admin privileges.
 * 
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get the current user's ID.
 * 
 * @return int|null The user ID, or null if not logged in.
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get the current user's username.
 * 
 * @return string|null The username, or null if not logged in.
 */
function getCurrentUsername(): ?string {
    return $_SESSION['username'] ?? null;
}

/**
 * Require the user to be logged in.
 * If not logged in, redirect to the login page with a message.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlashMessage('Please log in to access this page.', 'error');
        header('Location: login.php');
        exit;
    }
}

/**
 * Require the user to be an admin.
 * If not an admin, redirect to the home page with an error message.
 */
function requireAdmin(): void {
    requireLogin(); // Must be logged in first
    if (!isAdmin()) {
        setFlashMessage('Access denied. Admin privileges required.', 'error');
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Generate a CSRF token and store it in the session.
 * Use this in forms to prevent cross-site request forgery attacks.
 * 
 * @return string The CSRF token.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token from a form submission.
 * 
 * @param string $token The token submitted with the form.
 * @return bool True if the token is valid, false otherwise.
 */
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set a flash message to display on the next page load.
 * Flash messages are shown once and then deleted.
 * 
 * @param string $message The message text.
 * @param string $type    The message type: 'success', 'error', or 'info'.
 */
function setFlashMessage(string $message, string $type = 'info'): void {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear the flash message.
 * Returns the message and its type, then removes them from the session.
 * 
 * @return array|null Array with 'message' and 'type', or null if no message.
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $result = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $result;
    }
    return null;
}

/**
 * Sanitize a string for safe HTML output.
 * This prevents XSS (Cross-Site Scripting) attacks.
 * 
 * @param string|null $string The string to sanitize.
 * @return string The sanitized string.
 */
function sanitize(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
