
<?php
// Include config file
require_once "config/config.php";

// Check if user is logged in and is admin/manager
if(!isLoggedIn() || (!isAdmin() && !isManager())) {
    header("location: index.php");
    exit;
}

// Check if we have employee ID
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('employees.php', 'Invalid employee ID.', 'danger');
}

$employee_id = sanitizeInput($_GET['id']);

// Get employee details
function getEmployeeDetails($id) {
    global $conn;
    
    $sql = "SELECT e.*, u.username 
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

// Get employee attendance records
function getEmployeeAttendance($id, $limit = 10) {
    global $conn;
    
    $sql = "SELECT * FROM attendance 
            WHERE employee_id = ? 
            ORDER BY date DESC, time_in DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
    
    return $records;
}

// Get employee leave requests
function getEmployeeLeaves($id, $limit = 10) {
    global $conn;
    
    $sql = "SELECT lr.*, lt.name as leave_type 
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ? 
            ORDER BY lr.applied_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
    
    return $records;
}

$employee = getEmployeeDetails($employee_id);
if(!$employee) {
    redirectWithMessage('employees.php', 'Employee not found.', 'danger');
}

$attendance_records = getEmployeeAttendance($employee_id);
$leave_records = getEmployeeLeaves($employee_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Employee - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Employee Details</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
                    <li class="breadcrumb-item active" aria-current="page">View Employee</li>
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
                        <h2>Employee Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="profile-img text-center mb-3">
                            <?php if(!empty($employee['profile_image'])): ?>
                                <img src="uploads/profile/<?php echo $employee['profile_image']; ?>" alt="Profile Image">
                            <?php else: ?>
                                <img src="assets/img/default-avatar.png" alt="Default Avatar">
                            <?php endif; ?>
                        </div>
                        
                        <table class="table">
                            <tr>
                                <th>Employee ID:</th>
                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Name:</th>
                                <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td><?php echo htmlspecialchars($employee['username']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Department:</th>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                            </tr>
                            <tr>
                                <th>Position:</th>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                            </tr>
                            <tr>
                                <th>Join Date:</th>
                                <td><?php echo date('d M Y', strtotime($employee['join_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge badge-<?php echo ($employee['status'] == 'active') ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($employee['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if(!empty($employee['phone'])): ?>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if(!empty($employee['address'])): ?>
                            <tr>
                                <th>Address:</th>
                                <td><?php echo htmlspecialchars($employee['address']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                        <div class="text-center mt-3">
                            <a href="employee_edit.php?id=<?php echo $employee_id; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit Employee
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>Recent Attendance</h2>
                    </div>
                    <div class="card-body">
                        <?php if(count($attendance_records) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($attendance_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                                <td><?php echo $record['time_in']; ?></td>
                                                <td><?php echo $record['time_out'] ? $record['time_out'] : 'Not clocked out'; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                    echo ($record['status'] == 'present') ? 'success' : 
                                                        (($record['status'] == 'absent') ? 'danger' : 
                                                        (($record['status'] == 'late') ? 'warning' : 'info')); 
                                                    ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="employee_attendance.php?id=<?php echo $employee_id; ?>" class="btn btn-info btn-sm">View All Attendance</a>
                        <?php else: ?>
                            <div class="alert alert-info">No attendance records found.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Leave Requests</h2>
                    </div>
                    <div class="card-body">
                        <?php if(count($leave_records) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Leave Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($leave_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['leave_type']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($record['start_date'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($record['end_date'])); ?></td>
                                                <td><?php echo $record['days']; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                    echo ($record['status'] == 'approved') ? 'success' : 
                                                        (($record['status'] == 'rejected') ? 'danger' : 
                                                        (($record['status'] == 'cancelled') ? 'secondary' : 'warning')); 
                                                    ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="employee_leaves.php?id=<?php echo $employee_id; ?>" class="btn btn-info btn-sm">View All Leaves</a>
                        <?php else: ?>
                            <div class="alert alert-info">No leave records found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
