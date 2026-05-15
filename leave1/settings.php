
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is admin
if(!isLoggedIn() || !isAdmin()) {
    header("location: index.php");
    exit;
}

// Function to get current settings
function getSettings() {
    global $conn;
    
    // Check if settings table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'settings'");
    
    if($check_table->num_rows == 0) {
        // Create settings table if it doesn't exist
        $sql = "CREATE TABLE settings (
                    id INT NOT NULL AUTO_INCREMENT,
                    setting_name VARCHAR(50) NOT NULL,
                    setting_value VARCHAR(255) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE (setting_name)
                )";
        $conn->query($sql);
        
        // Insert default settings
        $conn->query("INSERT INTO settings (setting_name, setting_value) VALUES ('work_start_time', '09:00:00')");
        $conn->query("INSERT INTO settings (setting_name, setting_value) VALUES ('work_end_time', '18:00:00')");
    }
    
    // Get settings
    $result = $conn->query("SELECT * FROM settings");
    $settings = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    }
    
    // Set defaults if not present
    if(!isset($settings['work_start_time'])) $settings['work_start_time'] = '09:00:00';
    if(!isset($settings['work_end_time'])) $settings['work_end_time'] = '18:00:00';
    
    return $settings;
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $work_start_time = sanitizeInput($_POST['work_start_time']);
    $work_end_time = sanitizeInput($_POST['work_end_time']);
    
    // Update work start time
    $sql = "INSERT INTO settings (setting_name, setting_value) VALUES ('work_start_time', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $work_start_time, $work_start_time);
    $start_updated = $stmt->execute();
    
    // Update work end time
    $sql = "INSERT INTO settings (setting_name, setting_value) VALUES ('work_end_time', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $work_end_time, $work_end_time);
    $end_updated = $stmt->execute();
    
    if($start_updated && $end_updated) {
        redirectWithMessage('settings.php', 'Settings updated successfully!', 'success');
    } else {
        redirectWithMessage('settings.php', 'Error updating settings.', 'danger');
    }
}

$settings = getSettings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Additional styles to fix navigation bar alignment */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 15px;
        }
        
        .navbar-menu {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .navbar-menu a {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
        }
        
        .user-menu {
            position: relative;
            margin-left: 10px;
        }
        
        .dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .dropdown-menu {
            position: absolute;
            right: 0;
            z-index: 1000;
            min-width: 200px;
            background-color: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        
        .logout-link {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>System Settings</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Settings</li>
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
                        <h2>Work Hours Settings</h2>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="form-group">
                                <label for="work_start_time">Work Start Time</label>
                                <input type="time" class="form-control" id="work_start_time" name="work_start_time" value="<?php echo substr($settings['work_start_time'], 0, 5); ?>" required>
                                <small class="form-text text-muted">Employees clocking in after this time will be marked as late.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="work_end_time">Work End Time</label>
                                <input type="time" class="form-control" id="work_end_time" name="work_end_time" value="<?php echo substr($settings['work_end_time'], 0, 5); ?>" required>
                                <small class="form-text text-muted">Standard working hours end time.</small>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" name="update_settings" class="btn btn-primary" value="Update Settings">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Toggle dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            
            if(dropdownToggle && dropdownMenu) {
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if(!dropdownToggle.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>
