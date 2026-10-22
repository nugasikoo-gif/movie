<?php
/**
 * SineSugbo — User Registration Page (Stage 3)
 * 
 * Handles new user account creation with:
 * - Server-side validation
 * - CSRF protection
 * - Secure password hashing
 * - Duplicate prevention
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
$pageTitle = 'Register';

// --- Initialize variables ---
$errors = [];
$username = '';
$email = '';

// --- Process form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Sanitize and validate inputs
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation rules
        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (strlen($username) > 50) {
            $errors[] = 'Username cannot exceed 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($email) > 100) {
            $errors[] = 'Email cannot exceed 100 characters.';
        }

        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Password cannot exceed 72 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if ($confirmPassword === '') {
            $errors[] = 'Please confirm your password.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        // Check for duplicate username/email if no validation errors so far
        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->bind_param('ss', $username, $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Check which one exists
                $stmt->bind_result($existingId);
                $stmt->fetch();
                $stmt->close();

                // Need to check specifically which field conflicts
                $checkStmt = $conn->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
                $checkStmt->bind_param('ss', $username, $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                while ($row = $checkResult->fetch_assoc()) {
                    if ($row['username'] === $username) {
                        $errors[] = 'This username is already taken.';
                    }
                    if ($row['email'] === $email) {
                        $errors[] = 'This email is already registered.';
                    }
                }
                $checkStmt->close();
            } else {
                $stmt->close();
            }
        }

        // If all validations pass, create the account
        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
            $role = 'user';
            $insertStmt->bind_param('ssss', $username, $email, $passwordHash, $role);

            if ($insertStmt->execute()) {
                setFlashMessage('Account created successfully! You can now log in.', 'success');
                $insertStmt->close();
                header('Location: login.php');
                exit;
            } else {
                $errors[] = 'Registration failed. Please try again later.';
                $insertStmt->close();
            }
        }
    }
}

// --- Generate CSRF token for the form ---
$csrfToken = generateCsrfToken();

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== REGISTRATION FORM ===== -->
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <i class="fa-solid fa-user-plus auth-icon" aria-hidden="true"></i>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join SineSugbo to rate and review movies</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <div class="alert-content">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Registration failed:</strong>
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <!-- Username Field -->
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-user input-icon" aria-hidden="true"></i>
                    <input type="text" id="username" name="username" class="form-input"
                           value="<?php echo sanitize($username); ?>"
                           placeholder="Choose a username"
                           autocomplete="username"
                           required aria-describedby="username-hint">
                </div>
                <span class="form-hint" id="username-hint">3–50 characters, letters, numbers, and underscores only</span>
            </div>

            <!-- Email Field -->
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-envelope input-icon" aria-hidden="true"></i>
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?php echo sanitize($email); ?>"
                           placeholder="you@example.com"
                           autocomplete="email"
                           required aria-describedby="email-hint">
                </div>
                <span class="form-hint" id="email-hint">We'll never share your email</span>
            </div>

            <!-- Password Field -->
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="Create a strong password"
                           autocomplete="new-password"
                           maxlength="72"
                           required aria-describedby="password-hint">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <span class="form-hint" id="password-hint">At least 8 characters, including uppercase, lowercase, and a number</span>
            </div>

            <!-- Confirm Password Field -->
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                           placeholder="Confirm your password"
                           autocomplete="new-password"
                           maxlength="72"
                           required>
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create Account
            </button>
        </form>

        <!-- Login Link -->
        <p class="auth-footer">
            Already have an account? <a href="login.php">Log in</a>
        </p>
    </div>
</div>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>