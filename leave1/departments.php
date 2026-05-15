
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is admin
if(!isLoggedIn() || !isAdmin()) {
    header("location: index.php");
    exit;
}

// Function to get all departments
function getDepartments() {
    global $conn;
    $sql = "SELECT DISTINCT department, COUNT(*) as employee_count FROM employees GROUP BY department ORDER BY department";
    $result = $conn->query($sql);
    $departments = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    return $departments;
}

// Handle adding new department
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    $department_name = sanitizeInput($_POST['department_name']);
    
    if(!empty($department_name)) {
        // Check if department already exists
        $check_sql = "SELECT DISTINCT department FROM employees WHERE department = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $department_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows == 0) {
            // Check if a dummy user exists for departments or create one
            $check_user_sql = "SELECT id FROM users WHERE username = 'department_dummy'";
            $user_result = $conn->query($check_user_sql);
            
            if($user_result->num_rows > 0) {
                // Use existing dummy user
                $user_row = $user_result->fetch_assoc();
                $user_id = $user_row['id'];
            } else {
                // Create a dummy user for departments
                $insert_user_sql = "INSERT INTO users (username, password, role) VALUES ('department_dummy', 'password', 'employee')";
                if($conn->query($insert_user_sql)) {
                    $user_id = $conn->insert_id;
                } else {
                    redirectWithMessage('departments.php', 'Error: Unable to create dummy user.', 'danger');
                    exit;
                }
            }
            
            // Generate unique department code and email
            $dept_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $department_name), 0, 3));
            $timestamp = time();
            $unique_email = 'dept_' . $dept_code . '_' . $timestamp . '@example.com';
            
            // Create a dummy employee entry with this department
            $sql = "INSERT INTO employees (user_id, employee_id, first_name, last_name, email, department, position, join_date, status) 
                    VALUES (?, 'DEPT-".$dept_code."', 'Department', 'Entry', 
                    ?, ?, 'Department Head', CURRENT_DATE, 'inactive')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $unique_email, $department_name);
            
            if($stmt->execute()) {
                redirectWithMessage('departments.php', 'Department added successfully!', 'success');
            } else {
                redirectWithMessage('departments.php', 'Error: Unable to add department. ' . $conn->error, 'danger');
            }
        } else {
            redirectWithMessage('departments.php', 'Department already exists.', 'warning');
        }
    } else {
        redirectWithMessage('departments.php', 'Department name cannot be empty.', 'warning');
    }
}

$departments = getDepartments();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Departments</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Departments</li>
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
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h2>Add New Department</h2>
                    </div>
                    
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="form-group">
                                <label>Department Name</label>
                                <input type="text" name="department_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" name="add_department" class="btn btn-primary" value="Add Department">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2>Departments List</h2>
                    </div>
                    
                    <div class="card-body">
                        <?php if (count($departments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Department Name</th>
                                            <th>Number of Employees</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($departments as $dept): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                <td><?php echo $dept['employee_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No departments found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
