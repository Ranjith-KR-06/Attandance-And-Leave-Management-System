
<?php
// Include config file
require_once "config/config.php";

// Check if user is logged in and is admin
if(!isLoggedIn() || !isAdmin()) {
    header("location: index.php");
    exit;
}

// Check if we have employee ID
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('employees.php', 'Invalid employee ID.', 'danger');
}

$employee_id = sanitizeInput($_GET['id']);

// Function to get all departments
function getDepartments() {
    global $conn;
    $sql = "SELECT DISTINCT department FROM employees WHERE department != '' ORDER BY department";
    $result = $conn->query($sql);
    $departments = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $departments[] = $row['department'];
        }
    }
    
    return $departments;
}

// Function to get employee details
function getEmployeeDetails($id) {
    global $conn;
    
    $sql = "SELECT e.*, u.username, u.role 
            FROM employees e
            JOIN users u ON e.user_id = u.id
            WHERE e.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

$employee = getEmployeeDetails($employee_id);
if(!$employee) {
    redirectWithMessage('employees.php', 'Employee not found.', 'danger');
}

$departments = getDepartments();

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_employee'])) {
    // Sanitize and validate input
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $department = sanitizeInput($_POST['department']);
    $position = sanitizeInput($_POST['position']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $status = sanitizeInput($_POST['status']);
    $role = sanitizeInput($_POST['role']);
    
    // Update employee record
    $sql = "UPDATE employees SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            department = ?, 
            position = ?, 
            phone = ?, 
            address = ?, 
            status = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $department, $position, $phone, $address, $status, $employee_id);
    
    // Execute first update
    $employee_updated = $stmt->execute();
    
    // Update user role
    $sql = "UPDATE users SET role = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $role, $employee['user_id']);
    
    $role_updated = $stmt->execute();
    
    if($employee_updated && $role_updated) {
        redirectWithMessage('employee_view.php?id=' . $employee_id, 'Employee updated successfully!', 'success');
    } else {
        redirectWithMessage('employee_edit.php?id=' . $employee_id, 'Error updating employee.', 'danger');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Edit Employee</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
                    <li class="breadcrumb-item"><a href="employee_view.php?id=<?php echo $employee_id; ?>">View Employee</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Employee</li>
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
        
        <div class="card">
            <div class="card-header">
                <h2>Edit Employee Details</h2>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $employee_id); ?>" method="post">
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username (Read Only)</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['username']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department" class="form-control" required>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($employee['department'] == $dept) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Employee ID (Read Only)</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['employee_id']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="active" <?php echo ($employee['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($employee['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" class="form-control" required>
                                    <option value="employee" <?php echo ($employee['role'] == 'employee') ? 'selected' : ''; ?>>Employee</option>
                                    <option value="manager" <?php echo ($employee['role'] == 'manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="admin" <?php echo ($employee['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" name="update_employee" class="btn btn-primary" value="Update Employee">
                        <a href="employee_view.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
