
<?php
// Include config file
require_once "config/config.php";

// Check if user is logged in and is admin
if(!isLoggedIn() || !isAdmin()) {
    header("location: index.php");
    exit;
}

// Function to get all departments
function getDepartments() {
    global $conn;
    $sql = "SELECT DISTINCT department FROM employees ORDER BY department";
    $result = $conn->query($sql);
    $departments = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $departments[] = $row['department'];
        }
    }
    
    return $departments;
}

// Function to get all employees
function getEmployees($search = '', $department = '', $status = '') {
    global $conn;
    
    $sql = "SELECT * FROM employees WHERE 1=1";
    $params = [];
    $types = "";
    
    if(!empty($search)) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR employee_id LIKE ? OR email LIKE ?)";
        $search = "%" . $search . "%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "ssss";
    }
    
    if(!empty($department)) {
        $sql .= " AND department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    if(!empty($status)) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY first_name, last_name";
    
    $stmt = $conn->prepare($sql);
    
    if(!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    
    return $employees;
}

// Handle employee status toggle
if(isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $employee_id = sanitizeInput($_GET['id']);
    
    // Get current status
    $sql = "SELECT status FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $new_status = ($row['status'] == 'active') ? 'inactive' : 'active';
        
        // Update status
        $sql = "UPDATE employees SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $employee_id);
        
        if($stmt->execute()) {
            $status_text = ($new_status == 'active') ? 'activated' : 'deactivated';
            redirectWithMessage('employees.php', "Employee {$status_text} successfully!", 'success');
        } else {
            redirectWithMessage('employees.php', "Error: Unable to update employee status.", 'danger');
        }
    }
}

// Initialize filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$departments = getDepartments();
$employees = getEmployees($search, $department_filter, $status_filter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Employees</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Employees</li>
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
                <h2>Employees List</h2>
                <div class="card-actions">
                    <a href="employee_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Employee</a>
                </div>
            </div>
            
            <div class="card-body">
                <div class="filters">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, ID, or email" value="<?php echo $search; ?>">
                            </div>
                            
                            <div class="form-group col-md-3">
                                <select name="department" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-2">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <?php if (count($employees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Join Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($employee['join_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo ($employee['status'] == 'active') ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($employee['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="employee_view.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                            <a href="employee_edit.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                            <a href="employees.php?toggle_status=1&id=<?php echo $employee['id']; ?>" class="btn btn-sm <?php echo ($employee['status'] == 'active') ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('Are you sure you want to <?php echo ($employee['status'] == 'active') ? 'deactivate' : 'activate'; ?> this employee?')">
                                                <i class="fas <?php echo ($employee['status'] == 'active') ? 'fa-user-times' : 'fa-user-check'; ?>"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No employees found matching your criteria.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
