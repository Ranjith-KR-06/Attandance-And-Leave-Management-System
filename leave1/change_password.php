
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: index.php");
    exit;
}

// Define variables
$current_password = $new_password = $confirm_password = "";
$current_password_err = $new_password_err = $confirm_password_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate current password
    if(empty(trim($_POST["current_password"]))) {
        $current_password_err = "Please enter your current password.";     
    } else {
        $current_password = trim($_POST["current_password"]);
        
        // Check if the current password is correct
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION["user_id"]);
        $stmt->execute();
        $stmt->bind_result($stored_password);
        $stmt->fetch();
        $stmt->close();
        
        if($current_password !== $stored_password) {
            $current_password_err = "Current password is not correct.";
        }
    }
    
    // Validate new password
    if(empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter the new password.";     
    } elseif(strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Password must have at least 6 characters.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    // Check input errors before updating the database
    if(empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare an update statement
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $param_password, $param_id);
        
        // Set parameters
        $param_password = $new_password; // Store plaintext password
        $param_id = $_SESSION["user_id"];
        
        // Attempt to execute the prepared statement
        if($stmt->execute()) {
            // Password updated successfully. Redirect to dashboard
            redirectWithMessage('dashboard.php', 'Your password has been changed successfully!', 'success');
        } else {
            redirectWithMessage('change_password.php', 'Oops! Something went wrong. Please try again later.', 'danger');
        }

        // Close statement
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Change Password</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="profile.php">My Profile</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Change Password</li>
                </ol>
            </nav>
        </div>
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type']; ?>">
                <?php 
                    echo $_SESSION['flash_message']; 
                    unset($_SESSION['flash_message']);
                    unset($_SESSION['flash_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h2>Change Your Password</h2>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post"> 
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" class="form-control <?php echo (!empty($current_password_err)) ? 'is-invalid' : ''; ?>">
                                <span class="invalid-feedback"><?php echo $current_password_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
                                <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" class="btn btn-primary" value="Change Password">
                                <a class="btn btn-secondary" href="profile.php">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
