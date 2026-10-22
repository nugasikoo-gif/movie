<?php
/**
 * SineSugbo — User Login Page (Stage 3)
 * 
 * Handles user authentication with:
 * - Server-side validation
 * - CSRF protection
 * - Secure password verification
 * - Session ID regeneration
 * - Flash message feedback
 */

// --- Include foundation files ---
require_once 'includes/db.php';
require_once 'includes/auth.php';

// --- Redirect if already logged in ---
if (isLoggedIn()) {
    setFlashMessage('You are already logged in.', 'info');
    header('Location: index.php');
    exit;
}

// --- Page metadata ---
$pageTitle = 'Login';

// --- Initialize variables ---
$errors = [];
$usernameOrEmail = '';
$redirectUrl = safeRedirectTarget($_GET['redirect'] ?? '', 'index.php');

// --- Process form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Sanitize and validate inputs
        $usernameOrEmail = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($usernameOrEmail === '') {
            $errors[] = 'Username or email is required.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        // Attempt authentication if no validation errors
        if (empty($errors)) {
            // Check if input is email or username
            $isEmail = filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL);

            $stmt = $conn->prepare(
                $isEmail
                    ? 'SELECT id, username, email, password, role FROM users WHERE email = ?'
                    : 'SELECT id, username, email, password, role FROM users WHERE username = ?'
            );
            $stmt->bind_param('s', $usernameOrEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                // Successful login - regenerate session ID for security
                session_regenerate_id(true);

                // Store user data in session
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                setFlashMessage('Welcome back, ' . sanitize($user['username']) . '!', 'success');

                // Redirect to the intended page (sanitised) or the home page
                $destination = $redirectUrl ?: 'index.php';
                header('Location: ' . $destination);
                exit;
            } else {
                // Generic error message to prevent account enumeration
                $errors[] = 'Invalid username/email or password.';
            }
        }
    }
}

// --- Generate CSRF token for the form ---
$csrfToken = generateCsrfToken();

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== LOGIN FORM ===== -->
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <i class="fa-solid fa-arrow-right-to-bracket auth-icon" aria-hidden="true"></i>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to rate and review movies</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <div class="alert-content">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Login failed:</strong>
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="login.php<?php echo $redirectUrl ? '?redirect=' . urlencode($redirectUrl) : ''; ?>" method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <!-- Username/Email Field -->
            <div class="form-group">
                <label for="username_email" class="form-label">Username or Email</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-user input-icon" aria-hidden="true"></i>
                    <input type="text" id="username_email" name="username_email" class="form-input"
                           value="<?php echo sanitize($usernameOrEmail); ?>"
                           placeholder="Enter your username or email"
                           autocomplete="username"
                           required>
                </div>
            </div>

            <!-- Password Field -->
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           required>
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Log In
            </button>
        </form>

        <!-- Register Link -->
        <p class="auth-footer">
            Don't have an account? <a href="register.php">Sign up</a>
        </p>
    </div>
</div>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>