<?php
// Handle profile picture upload
$profile_picture_error = '';
$profile_picture_success = '';

if (isset($_POST['upload_profile_picture'])) {
    $target_dir = "../uploads/profile_pictures/";
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            $profile_picture_error = "Failed to create upload directory. Please contact administrator.";
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

// Get user profile information 
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, club_name, email, description, role, created_at, profile_picture FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Get club activities
$stmt = $conn->prepare("SELECT * FROM events WHERE created_by = ? ORDER BY event_date DESC LIMIT 5");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$club_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords first
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
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password_hash'])) {
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
?>

<div class="profile-container">
    <?php if (!empty($profile_picture_error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($profile_picture_error); ?></div>
    <?php endif; ?>
    <?php if (!empty($profile_picture_success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($profile_picture_success); ?></div>
    <?php endif; ?>

    <div class="profile-header">
        <div class="profile-avatar-container">
            <div class="profile-avatar" id="profile-avatar">
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
            <h2 class="profile-name"><?php echo htmlspecialchars($profile['club_name']); ?></h2>
            <p class="profile-email"><?php echo htmlspecialchars($profile['email']); ?></p>
            <p class="profile-member-since">Member since: <?php echo date('F d, Y', strtotime($profile['created_at'])); ?></p>
        </div>
    </div>
    
    <!-- Profile Picture Upload Form (Hidden by default) -->
    <div id="profile-picture-form" class="profile-picture-form" style="display: none;">
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="profile_picture">Select a new profile picture</label>
                <div class="file-input-wrapper">
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" required>
                    <div class="file-preview">
                        <img id="preview-image" src="#" alt="Preview" style="display:none;">
                        <span id="file-name">No file selected</span>
                    </div>
                </div>
                <div class="file-requirements">
                    <small>Supported formats: JPG, JPEG, PNG, GIF. Maximum size: 5MB.</small>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="upload_profile_picture" class="save-btn">Upload Picture</button>
                <button type="button" id="cancel-picture-upload" class="cancel-btn">Cancel</button>
            </div>
        </form>
    </div>
    
    <div class="profile-sections">
        <div class="profile-section">
            <h3 class="section-title">CLUB INFORMATION</h3>
            <div class="club-info-form">
                <form id="club-info-form" method="post" action="">
                    <div class="form-group">
                        <label for="club_name">Club Name</label>
                        <input type="text" id="club_name" name="club_name" value="<?php echo htmlspecialchars($profile['club_name']); ?>" readonly>
                        <button type="button" class="edit-btn" data-field="club_name">Edit</button>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                        <button type="button" class="edit-btn" data-field="email">Edit</button>
                    </div>
                    <div class="form-group">
                        <label for="description">Club Description</label>
                        <textarea id="description" name="description" rows="4" readonly><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>
                        <button type="button" class="edit-btn" data-field="description">Edit</button>
                    </div>
                    <div class="form-actions" style="display: none;">
                        <button type="submit" name="update_profile" class="save-btn">Save Changes</button>
                        <button type="button" class="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="profile-section">
            <h3 class="section-title">RECENT ACTIVITIES</h3>
            <?php if (empty($club_activities)): ?>
                <p class="no-activities">No recent activities found.</p>
            <?php else: ?>
                <div class="activities-list">
                    <?php foreach ($club_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-date">
                            <?php echo date('M d', strtotime($activity['event_date'])); ?>
                        </div>
                        <div class="activity-content">
                            <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                            <p><?php echo htmlspecialchars($activity['location']); ?> • <?php echo date('h:i A', strtotime($activity['event_time'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="profile-section">
            <h3 class="section-title">ACCOUNT SETTINGS</h3>
            <div class="account-settings">
                <button id="change-password-btn" class="settings-btn">Change Password</button>
                <form method="post" style="display:inline;">
                    <button class="settings-btn logout-btn" name="logout" type="submit">Logout</button>
                </form>
            </div>
            
            <!-- Password Change Form (Hidden by default) -->
            <div id="password-change-form" style="display: none;">
                <form method="post" action="" id="password-form">
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
                        <button type="submit" name="change_password" class="save-btn">Update Password</button>
                        <button type="button" id="cancel-password-change" class="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.profile-container {
    max-width: 900px;
    margin: 20px auto;
    padding: 20px;
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
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #ff9800;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: bold;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-picture-actions {
    margin-top: 8px;
    text-align: center;
}

.edit-picture-btn {
    background: none;
    border: none;
    color: #2196F3;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 3px 6px;
}

.edit-picture-btn:hover {
    text-decoration: underline;
}

.profile-picture-form {
    background: #f5f5f5;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.file-input-wrapper {
    margin-top: 10px;
    margin-bottom: 10px;
}

.file-preview {
    margin-top: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

#preview-image {
    max-width: 150px;
    max-height: 150px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.file-requirements {
    color: #777;
    margin-top: 8px;
}

.profile-info {
    flex: 1;
}

.profile-name {
    margin: 0 0 5px 0;
    font-size: 24px;
    color: #333;
}

.profile-email {
    margin: 0 0 5px 0;
    color: #666;
    font-size: 16px;
}

.profile-member-since {
    margin: 0;
    color: #888;
    font-size: 14px;
}

.profile-sections {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.profile-section {
    background: #f5f5f5;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.section-title {
    margin-top: 0;
    margin-bottom: 20px;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #ff9800;
    padding-bottom: 8px;
    display: inline-block;
}

.club-info-form .form-group {
    margin-bottom: 20px;
    position: relative;
}

.club-info-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
}

.club-info-form input,
.club-info-form textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    margin-bottom: 5px;
}

.club-info-form input:focus,
.club-info-form textarea:focus {
    border-color: #ff9800;
    outline: none;
    box-shadow: 0 0 3px rgba(255, 152, 0, 0.2);
}

.club-info-form input[readonly],
.club-info-form textarea[readonly] {
    background-color: #f8f8f8;
    cursor: not-allowed;
}

.edit-btn {
    background: #2196F3;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 5px 12px;
    cursor: pointer;
    font-size: 14px;
    position: absolute;
    right: 0;
    top: 30px;
}

.save-btn {
    background: linear-gradient(90deg, #f7c948 0%, #e2b007 100%);
    color: #222;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}

.save-btn:hover {
    background: linear-gradient(90deg, #e2b007 0%, #f7c948 100%);
}

.cancel-btn {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: 600;
    margin-left: 10px;
}

.cancel-btn:hover {
    background: #eee;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.activities-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.activity-date {
    background: #ff9800;
    color: white;
    border-radius: 6px;
    padding: 10px;
    min-width: 60px;
    text-align: center;
    font-weight: 600;
    margin-right: 15px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.activity-content {
    flex: 1;
}

.activity-content h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.activity-content p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.no-activities {
    color: #888;
    font-style: italic;
    text-align: center;
    padding: 20px;
    background: white;
    border-radius: 6px;
}

.account-settings {
    display: flex;
    gap: 15px;
}

.settings-btn {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px 16px;
    cursor: pointer;
    font-weight: 600;
    margin-right: 10px;
    transition: background 0.2s;
}

.settings-btn:hover {
    background: #eee;
}

.settings-btn.logout-btn {
    background: #d50000;
    color: white;
    border: none;
}

.settings-btn.logout-btn:hover {
    background: #b71c1c;
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

@media (max-width: 768px) {
    .profile-container {
        padding: 15px;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-avatar-container {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .account-settings {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit field functionality
    const editButtons = document.querySelectorAll('.edit-btn');
    const formActions = document.querySelector('.form-actions');
    const cancelBtn = document.querySelector('.cancel-btn');
    const clubInfoForm = document.getElementById('club-info-form');
    let initialFormData = new FormData(clubInfoForm);
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fieldName = this.getAttribute('data-field');
            const input = document.getElementById(fieldName);
            
            input.removeAttribute('readonly');
            input.focus();
            formActions.style.display = 'flex';
            
            // Store original values for cancel
            initialFormData = new FormData(clubInfoForm);
        });
    });
    
    cancelBtn.addEventListener('click', function() {
        // Reset form to original values
        const inputs = document.querySelectorAll('#club-info-form input, #club-info-form textarea');
        
        inputs.forEach(input => {
            input.value = initialFormData.get(input.name);
            input.setAttribute('readonly', true);
        });
        
        formActions.style.display = 'none';
    });
    
    // Password change form toggle
    const changePasswordBtn = document.getElementById('change-password-btn');
    const passwordForm = document.getElementById('password-change-form');
    const cancelPasswordChange = document.getElementById('cancel-password-change');
    
    changePasswordBtn.addEventListener('click', function() {
        passwordForm.style.display = 'block';
        changePasswordBtn.style.display = 'none';
    });
    
    cancelPasswordChange.addEventListener('click', function() {
        passwordForm.style.display = 'none';
        changePasswordBtn.style.display = 'inline-block';
        
        // Reset password form
        document.getElementById('password-form').reset();
    });
    
    // Password validation
    const passwordForm = document.getElementById('password-form');
    passwordForm.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match');
        }
    });
    
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
});
</script> 