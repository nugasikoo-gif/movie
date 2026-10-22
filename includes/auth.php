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
 * Get the current user's email.
 * 
 * @return string|null The email, or null if not logged in.
 */
function getCurrentUserEmail(): ?string {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Build the absolute base URL of the application.
 * Detected from the script location so the project keeps working
 * if the folder is renamed or moved.
 *
 * @return string The base URL with a trailing slash (e.g. "/moviedb/").
 */
function appBaseUrl(): string {
    static $baseUrl = null;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $appRoot = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');

    // For pages inside /admin/ the app root is one level higher.
    if (substr($appRoot, -6) === '/admin') {
        $appRoot = substr($appRoot, 0, -6);
    }

    $baseUrl = ($appRoot === '' ? '' : $appRoot) . '/';
    return $baseUrl;
}

/**
 * Require the user to be logged in.
 * If not logged in, redirect to the login page with a message.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlashMessage('Please log in to access this page.', 'error');
        header('Location: ' . appBaseUrl() . 'login.php');
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
        header('Location: ' . appBaseUrl() . 'index.php');
        exit;
    }
}

/**
 * Sanitise a redirect target so it can only point at this application.
 *
 * Only relative paths (no scheme, no host) are accepted. Anything
 * else falls back to the home page, which prevents open-redirect
 * attacks such as "login.php?redirect=https://example.org".
 *
 * @param mixed $target The requested redirect target.
 * @param string $fallback The page to use when the target is not safe.
 * @return string A safe relative URL.
 */
function safeRedirectTarget(mixed $target, string $fallback = 'index.php'): string {
    if (!is_string($target) || $target === '') {
        return $fallback;
    }

    $target = trim($target);

    // Reject absolute URLs, protocol-relative URLs and backslash tricks.
    if (str_contains($target, '//') || str_contains($target, '\\')) {
        return $fallback;
    }
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $target)) {
        return $fallback;
    }
    if ($target[0] !== '/') {
        return $fallback;
    }

    // Only allow simple file names plus optional query string.
    if (!preg_match('#^/[A-Za-z0-9_\-]+(?:\.php)?(?:\?[A-Za-z0-9_=&%.\-]*)?$#', $target)) {
        return $fallback;
    }

    return $target;
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
 * Sanitize a value for safe HTML output.
 * This prevents XSS (Cross-Site Scripting) attacks.
 *
 * Accepts any type so it is safe to call directly on request
 * parameters: arrays and non-scalar values are reduced to an
 * empty string instead of triggering a TypeError.
 *
 * @param mixed $value The value to sanitize.
 * @return string The sanitized string.
 */
function sanitize(mixed $value): string {
    if (is_array($value) || is_object($value)) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '';
    }
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Read a trimmed string parameter from a request array.
 * Returns an empty string when the parameter is missing or is
 * not a scalar value (for example "search[]=a").
 *
 * @param array  $source  The request array (usually $_GET or $_POST).
 * @param string $key     The parameter name.
 * @param int    $maxLen  Maximum length to keep (0 = unlimited).
 * @return string The trimmed parameter value.
 */
function requestString(array $source, string $key, int $maxLen = 0): string {
    if (!isset($source[$key]) || is_array($source[$key]) || is_object($source[$key])) {
        return '';
    }
    $value = trim((string) $source[$key]);
    if ($maxLen > 0 && strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}
