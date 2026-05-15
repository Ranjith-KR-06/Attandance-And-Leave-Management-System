
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in
if(!isLoggedIn()) {
    header("location: index.php");
    exit;
}

// Get user details
$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Get employee details if role is employee
$employee = null;
if($role == 'employee') {
    $employee = getEmployeeByUserId($user_id);
} else {
    // For admins and managers, find their employee record if it exists
    $employee = getEmployeeByUserId($user_id);
}

// Process profile update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    
    // Update employee profile
    $sql = "UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $employee['id']);
    
    if($stmt->execute()) {
        redirectWithMessage('profile.php', 'Profile updated successfully!', 'success');
    } else {
        redirectWithMessage('profile.php', 'Error updating profile.', 'danger');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Profile</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
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
        
        <?php if ($employee): ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h2>Employee Information</h2>
                        </div>
                        <div class="card-body text-center">
                            <div class="profile-img">
                                <?php if(!empty($employee['profile_image'])): ?>
                                    <img src="uploads/profile/<?php echo $employee['profile_image']; ?>" alt="Profile Image">
                                <?php else: ?>
                                    <img src="assets/img/default-avatar.png" alt="Default Avatar">
                                <?php endif; ?>
                            </div>
                            <h3><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
                            <p class="text-muted"><?php echo htmlspecialchars($employee['position']); ?></p>
                            <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                            <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['department']); ?></p>
                            <p><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('d M Y', strtotime($employee['join_date'])); ?></p>
                            <a href="change_password.php" class="btn btn-secondary mt-3">Change Password</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h2>Edit Profile</h2>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <input type="submit" name="update_profile" class="btn btn-primary" value="Update Profile">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No employee profile found for this user.</div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
