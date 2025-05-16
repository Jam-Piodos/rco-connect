<?php
// Handle profile picture upload
$profile_picture_error = '';
$profile_picture_success = '';

if (isset($_POST['upload_profile_picture'])) {
    $target_dir = "../uploads/profile_pictures/";
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            $profile_picture_error = "Failed to create upload directory. Please check server permissions.";
            $upload_ok = 0;
        }
    }
    
    // Only proceed if we have no errors
    if (empty($profile_picture_error)) {
        // Check if file was uploaded properly
        if (!isset($_FILES["profile_picture"]) || $_FILES["profile_picture"]["error"] != 0) {
            $profile_picture_error = "Error in file upload. Please try again.";
            $upload_ok = 0;
        } else {
            $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
            $new_filename = "profile_" . $_SESSION['user_id'] . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            $upload_ok = 1;
            
            // Check if image file is an actual image
            $check = @getimagesize($_FILES["profile_picture"]["tmp_name"]);
            if ($check === false) {
                $profile_picture_error = "File is not an image.";
                $upload_ok = 0;
            }
            
            // Check file size (limit to 5MB)
            if ($_FILES["profile_picture"]["size"] > 5000000) {
                $profile_picture_error = "Sorry, your file is too large. Max file size is 5MB.";
                $upload_ok = 0;
            }
            
            // Allow only certain file formats
            if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif") {
                $profile_picture_error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                $upload_ok = 0;
            }
            
            // If all checks passed, try to upload file
            if ($upload_ok == 1) {
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    // Update the profile_picture column in database
                    $conn = getDBConnection();
                    $relative_path = "uploads/profile_pictures/" . $new_filename;
                    
                    // Delete old profile picture if exists
                    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->bind_param('i', $_SESSION['user_id']);
                    $stmt->execute();
                    $old_picture = $stmt->get_result()->fetch_assoc();
                    if ($old_picture && $old_picture['profile_picture'] && file_exists('../' . $old_picture['profile_picture'])) {
                        @unlink('../' . $old_picture['profile_picture']);
                    }
                    
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->bind_param('si', $relative_path, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        // Set success message in session and redirect to refresh the page
                        $_SESSION['profile_picture_success'] = "Profile picture updated successfully!";
                        // Log activity for admin audit
                        $activity_log = "Admin updated profile picture";
                        error_log($activity_log);
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $profile_picture_error = "Error updating profile picture in database: " . $conn->error;
                    }
                    $conn->close();
                } else {
                    $profile_picture_error = "Sorry, there was an error uploading your file. Please check folder permissions.";
                }
            }
        }
    }
}

// Check for session success message (set after redirect)
if (isset($_SESSION['profile_picture_success'])) {
    $profile_picture_success = $_SESSION['profile_picture_success'];
    unset($_SESSION['profile_picture_success']); // Clear the message after use
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match!";
    } elseif (strlen($new_password) < 8) {
        $password_error = "New password must be at least 8 characters long!";
    } else {
        $conn = getDBConnection();
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password_hash'])) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $password_hash, $_SESSION['user_id']);
            $stmt->execute();
            
            // Set success message in session and redirect
            $_SESSION['password_success'] = "Password updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $password_error = "Current password is incorrect!";
        }
        $conn->close();
    }
}

// Check for session success message (set after redirect)
if (isset($_SESSION['password_success'])) {
    $password_success = $_SESSION['password_success'];
    unset($_SESSION['password_success']); // Clear the message after use
}

// Get user profile information 
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, club_name, email, description, role, created_at, profile_picture FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-avatar-container {
            position: relative;
            margin-right: 20px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #ff9800;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-picture-actions {
            margin-top: 10px;
            text-align: center;
        }
        
        .edit-picture-btn {
            background: none;
            border: none;
            color: #2196F3;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 5px 8px;
        }
        
        .edit-picture-btn:hover {
            text-decoration: underline;
        }
        
        .profile-info h1 {
            margin: 0 0 5px 0;
            font-size: 32px;
            color: #333;
        }
        
        .profile-info p {
            margin: 0 0 10px 0;
            color: #666;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            background-color: #ff9800;
            color: white;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .profile-picture-form {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: none;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .file-input-wrapper {
            margin-top: 10px;
        }
        
        .file-preview {
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        #preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: none;
        }
        
        .file-requirements {
            color: #777;
            margin-top: 10px;
            font-size: 14px;
        }
        
        input[type="file"] {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            width: 100%;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: #ff9800;
            color: white;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .settings-section {
            margin-top: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff9800;
            display: inline-block;
        }
        
        .password-form {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: none;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($profile_picture_error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($profile_picture_error); ?></div>
        <?php endif; ?>
        <?php if (!empty($profile_picture_success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($profile_picture_success); ?></div>
        <?php endif; ?>
        <?php if (!empty($password_error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($password_error); ?></div>
        <?php endif; ?>
        <?php if (!empty($password_success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($password_success); ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-avatar-container">
                <div class="profile-avatar">
                    <?php if (!empty($profile['profile_picture']) && file_exists('../' . $profile['profile_picture'])): ?>
                        <img src="<?php echo '../' . htmlspecialchars($profile['profile_picture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo strtoupper(substr($profile['club_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-picture-actions">
                    <button type="button" class="edit-picture-btn" id="edit-picture-btn">
                        <i class="fas fa-camera"></i> Change Picture
                    </button>
                </div>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($profile['club_name']); ?></h1>
                <p><?php echo htmlspecialchars($profile['email']); ?></p>
                <div class="badge">Administrator</div>
                <p>Account created: <?php echo date('F d, Y', strtotime($profile['created_at'])); ?></p>
            </div>
        </div>
        
        <!-- Profile Picture Upload Form -->
        <div id="profile-picture-form" class="profile-picture-form">
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_picture">Select a new profile picture</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                        <div class="file-preview">
                            <img id="preview-image" src="#" alt="Preview">
                            <span id="file-name">No file selected</span>
                        </div>
                    </div>
                    <div class="file-requirements">
                        <small>Supported formats: JPG, JPEG, PNG, GIF. Maximum size: 5MB.</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="upload_profile_picture" class="btn btn-primary">Upload Picture</button>
                    <button type="button" id="cancel-picture-upload" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
        
        <div class="settings-section">
            <h2 class="section-title">ACCOUNT SETTINGS</h2>
            <button id="change-password-btn" class="btn btn-primary">Change Password</button>
            
            <!-- Password Change Form -->
            <div id="password-form" class="password-form">
                <form method="post">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                        <button type="button" id="cancel-password" class="btn btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile Picture Upload Form Toggle
            const editPictureBtn = document.getElementById('edit-picture-btn');
            const profilePictureForm = document.getElementById('profile-picture-form');
            const cancelPictureUpload = document.getElementById('cancel-picture-upload');
            
            editPictureBtn.addEventListener('click', function() {
                profilePictureForm.style.display = 'block';
            });
            
            cancelPictureUpload.addEventListener('click', function() {
                profilePictureForm.style.display = 'none';
                document.getElementById('profile_picture').value = '';
                document.getElementById('preview-image').style.display = 'none';
                document.getElementById('file-name').textContent = 'No file selected';
            });
            
            // Image Preview
            const profilePictureInput = document.getElementById('profile_picture');
            const previewImage = document.getElementById('preview-image');
            const fileName = document.getElementById('file-name');
            
            profilePictureInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    fileName.textContent = file.name;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewImage.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    fileName.textContent = 'No file selected';
                    previewImage.style.display = 'none';
                }
            });
            
            // Password Form Toggle
            const changePasswordBtn = document.getElementById('change-password-btn');
            const passwordForm = document.getElementById('password-form');
            const cancelPasswordBtn = document.getElementById('cancel-password');
            
            changePasswordBtn.addEventListener('click', function() {
                passwordForm.style.display = 'block';
                changePasswordBtn.style.display = 'none';
            });
            
            cancelPasswordBtn.addEventListener('click', function() {
                passwordForm.style.display = 'none';
                changePasswordBtn.style.display = 'inline-block';
                document.getElementById('current_password').value = '';
                document.getElementById('new_password').value = '';
                document.getElementById('confirm_password').value = '';
            });
        });
    </script>
</body>
</html> 