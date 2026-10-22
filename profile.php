<?php
/**
 * SineSugbo — User Profile Page
 * 
 * Allows logged-in users to view and edit their profile information.
 * Requires authentication.
 */

// --- Include foundation files ---
require_once 'includes/db.php';
require_once 'includes/auth.php';

// --- Require login ---
requireLogin();

// --- Page metadata ---
$pageTitle = 'My Profile';
$csrfToken = generateCsrfToken();

// --- Handle form submissions ---
$errors = [];
$submittedUsername = '';
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        // The form was not submitted by this session — tell the user
        // instead of failing silently.
        $errors[] = 'Your session expired while the page was open. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = getCurrentUserId();

        if ($action === 'update_profile') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $submittedUsername = $username;
            $submittedEmail = $email;

            // Validation
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

            // Check for duplicate username/email (excluding current user)
            if (empty($errors)) {
                $stmt = $conn->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
                $stmt->bind_param('ssi', $username, $email, $userId);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $checkStmt = $conn->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id != ?');
                    $checkStmt->bind_param('ssi', $username, $email, $userId);
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
                }
                $stmt->close();
            }

            // Update if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                $stmt->bind_param('ssi', $username, $email, $userId);

                if ($stmt->execute()) {
                    // Update session
                    $_SESSION['username'] = $username;
                    $_SESSION['user_email'] = $email;

                    setFlashMessage('Profile updated successfully.', 'success');
                    header('Location: profile.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update profile.';
                }
                $stmt->close();
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '') {
                $errors[] = 'Current password is required.';
            }
            if ($newPassword === '') {
                $errors[] = 'New password is required.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif (!preg_match('/[A-Z]/', $newPassword)) {
                $errors[] = 'New password must contain at least one uppercase letter.';
            } elseif (!preg_match('/[a-z]/', $newPassword)) {
                $errors[] = 'New password must contain at least one lowercase letter.';
            } elseif (!preg_match('/[0-9]/', $newPassword)) {
                $errors[] = 'New password must contain at least one number.';
            }
            if ($confirmPassword === '') {
                $errors[] = 'Please confirm your new password.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                // Verify current password
                $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($currentPassword, $user['password'])) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->bind_param('si', $newHash, $userId);

                    if ($stmt->execute()) {
                        // New session id after a credential change (session fixation)
                        session_regenerate_id(true);
                        setFlashMessage('Password changed successfully.', 'success');
                        header('Location: profile.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to change password.';
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Current password is incorrect.';
                }
            }
        }
    }
}

// --- Fetch current user data ---
$userId = getCurrentUserId();
$stmt = $conn->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// --- Fetch user stats ---
$stats = [];
$stmt = $conn->prepare('SELECT COUNT(*) as count FROM ratings WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats['ratings'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) as count FROM reviews WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats['reviews'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// --- Include Header ---
require_once 'includes/header.php';
?>

<!-- ===== USER PROFILE ===== -->
<div class="profile-container">
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
        </div>
        <div class="profile-info">
            <h1 class="profile-username"><?php echo sanitize($user['username']); ?></h1>
            <span class="profile-role role-<?php echo sanitize($user['role']); ?>">
                <?php echo ucfirst(sanitize($user['role'])); ?>
            </span>
            <p class="profile-member-since">
                Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
            </p>
        </div>
    </div>

    <!-- Validation Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" aria-live="assertive">
            <div class="alert-content">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="profile-content">
        <!-- Profile Stats -->
        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo (int) $stats['ratings']; ?></span>
                <span class="stat-label">Ratings</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo (int) $stats['reviews']; ?></span>
                <span class="stat-label">Reviews</span>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="profile-form-card">
            <h2 class="form-section-title">
                <i class="fa-solid fa-user-pen" aria-hidden="true"></i> Edit Profile
            </h2>

            <form action="profile.php" method="POST" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="form-label">Username <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user input-icon" aria-hidden="true"></i>
                            <input type="text" id="username" name="username" class="form-input"
                                   value="<?php echo sanitize($submittedUsername !== '' ? $submittedUsername : $user['username']); ?>" required maxlength="50" autocomplete="username">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon" aria-hidden="true"></i>
                            <input type="email" id="email" name="email" class="form-input"
                                   value="<?php echo sanitize($submittedEmail !== '' ? $submittedEmail : $user['email']); ?>" required maxlength="100" autocomplete="email">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save" aria-hidden="true"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="profile-form-card">
            <h2 class="form-section-title">
                <i class="fa-solid fa-lock" aria-hidden="true"></i> Change Password
            </h2>

            <form action="profile.php" method="POST" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="form-row">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                            <input type="password" id="current_password" name="current_password" class="form-input"
                                   placeholder="Enter current password" required autocomplete="current-password">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                            <input type="password" id="new_password" name="new_password" class="form-input"
                                   placeholder="Enter new password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <span class="form-hint">At least 8 characters, including uppercase, lowercase, and a number</span>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                                   placeholder="Confirm new password" required autocomplete="new-password">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key" aria-hidden="true"></i> Change Password
                </button>
            </form>
        </div>

        <!-- Account Info -->
        <div class="profile-form-card">
            <h2 class="form-section-title">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i> Account Information
            </h2>

            <div class="account-info-grid">
                <div class="info-item">
                    <span class="info-label">User ID</span>
                    <span class="info-value">#<?php echo (int) $user['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role</span>
                    <span class="info-value">
                        <span class="role-badge role-<?php echo sanitize($user['role']); ?>">
                            <?php echo ucfirst(sanitize($user['role'])); ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Registered</span>
                    <span class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Ratings</span>
                    <span class="info-value"><?php echo (int) $stats['ratings']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Reviews</span>
                    <span class="info-value"><?php echo (int) $stats['reviews']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// --- Include Footer ---
require_once 'includes/footer.php';

// --- Close database connection ---
$conn->close();
?>