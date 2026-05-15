
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in, if not redirect to login page
if(!isLoggedIn()) {
    header("location: index.php");
    exit;
}

// Get user details
$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Get employee details if user is an employee
$employee = null;
if($role == 'employee') {
    $employee = getEmployeeByUserId($user_id);
}

// Get today's date
$today = date('Y-m-d');

// Function to get settings
function getSettings() {
    global $conn;
    
    // Check if settings table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'settings'");
    
    if($check_table->num_rows == 0) {
        // Return default settings
        return [
            'work_start_time' => '09:00:00',
            'work_end_time' => '18:00:00'
        ];
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

// Function to check if employee is on approved leave for today
function isOnLeaveToday($employee_id) {
    global $conn, $today;
    
    $sql = "SELECT COUNT(*) as count FROM leave_requests 
            WHERE employee_id = ? 
            AND status = 'approved' 
            AND ? BETWEEN start_date AND end_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employee_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

// Function to get today's attendance for an employee
function getTodayAttendance($employee_id) {
    global $conn, $today;
    $sql = "SELECT * FROM attendance WHERE employee_id = ? AND date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employee_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Function to get pending leave requests for managers
function getPendingLeaveRequests() {
    global $conn;
    $sql = "SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type 
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.status = 'pending'
            ORDER BY lr.applied_at DESC";
    $result = $conn->query($sql);
    $requests = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
    }
    return $requests;
}

// Function to get attendance stats
function getAttendanceStats() {
    global $conn;
    $stats = [];
    
    // Get total employees
    $sql = "SELECT COUNT(*) as total FROM employees WHERE status = 'active'";
    $result = $conn->query($sql);
    $stats['total_employees'] = $result->fetch_assoc()['total'];
    
    // Get present employees for today
    $sql = "SELECT COUNT(DISTINCT employee_id) as present FROM attendance WHERE date = CURDATE() AND status = 'present'";
    $result = $conn->query($sql);
    $stats['present_today'] = $result->fetch_assoc()['present'];
    
    // Get absent employees for today
    $sql = "SELECT COUNT(*) as absent FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = CURDATE()
            WHERE e.status = 'active' AND 
                  (a.id IS NULL OR a.status = 'absent')";
    $result = $conn->query($sql);
    $stats['absent_today'] = $result->fetch_assoc()['absent'];
    
    // Get late employees for today
    $sql = "SELECT COUNT(DISTINCT employee_id) as late FROM attendance WHERE date = CURDATE() AND status = 'late'";
    $result = $conn->query($sql);
    $stats['late_today'] = $result->fetch_assoc()['late'];
    
    return $stats;
}

// Get settings
$settings = getSettings();

// Processing attendance submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if($_POST['action'] == 'time_in') {
        // Handle time in
        $employee_id = $employee['id'];
        $time_in = date('H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $status = 'present';
        
        // Check if employee is on approved leave today
        if(isOnLeaveToday($employee_id)) {
            redirectWithMessage('dashboard.php', 'You are on approved leave today and cannot clock in.', 'warning');
            exit;
        }
        
        // Check if already clocked in
        $attendance = getTodayAttendance($employee_id);
        
        if($attendance) {
            redirectWithMessage('dashboard.php', 'You have already marked your attendance for today.', 'warning');
        } else {
            // Check if late
            $work_start_time = $settings['work_start_time']; // Get from settings
            if($time_in > $work_start_time) {
                $status = 'late';
            }
            
            $sql = "INSERT INTO attendance (employee_id, date, time_in, status, ip_address) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $employee_id, $today, $time_in, $status, $ip_address);
            
            if($stmt->execute()) {
                redirectWithMessage('dashboard.php', 'Time-in recorded successfully!', 'success');
            } else {
                redirectWithMessage('dashboard.php', 'Error: ' . $stmt->error, 'danger');
            }
        }
    } elseif($_POST['action'] == 'time_out') {
        // Handle time out
        $employee_id = $employee['id'];
        $time_out = date('H:i:s');
        
        // Check if employee is on approved leave today
        if(isOnLeaveToday($employee_id)) {
            redirectWithMessage('dashboard.php', 'You are on approved leave today and cannot clock out.', 'warning');
            exit;
        }
        
        // Check if already clocked in
        $attendance = getTodayAttendance($employee_id);
        
        if(!$attendance) {
            redirectWithMessage('dashboard.php', 'You have not clocked in yet for today.', 'warning');
        } elseif($attendance['time_out']) {
            redirectWithMessage('dashboard.php', 'You have already clocked out for today.', 'warning');
        } else {
            $sql = "UPDATE attendance SET time_out = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $time_out, $attendance['id']);
            
            if($stmt->execute()) {
                redirectWithMessage('dashboard.php', 'Time-out recorded successfully!', 'success');
            } else {
                redirectWithMessage('dashboard.php', 'Error: ' . $stmt->error, 'danger');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="dashboard">
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?></h1>
                <p>Today is <?php echo date("l, d F Y"); ?></p>
                <p>Current Time: <span id="current-time">Loading...</span></p>
                <p>Work Hours: <?php echo substr($settings['work_start_time'], 0, 5); ?> - <?php echo substr($settings['work_end_time'], 0, 5); ?></p>
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
            
            <?php if ($role == 'employee' && $employee): ?>
                <div class="attendance-actions">
                    <div class="card">
                        <div class="card-header">
                            <h2>Today's Attendance</h2>
                        </div>
                        <div class="card-body">
                            <?php 
                            $attendance = getTodayAttendance($employee['id']);
                            $on_leave = isOnLeaveToday($employee['id']);
                            
                            if($on_leave): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> You are on approved leave today.
                                </div>
                            <?php elseif($attendance): ?>
                                <div class="attendance-info">
                                    <p><strong>Status:</strong> <?php echo ucfirst($attendance['status']); ?></p>
                                    <p><strong>Time In:</strong> <?php echo $attendance['time_in']; ?></p>
                                    <p><strong>Time Out:</strong> <?php echo $attendance['time_out'] ? $attendance['time_out'] : 'Not clocked out yet'; ?></p>
                                </div>
                                
                                <?php if(!$attendance['time_out']): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="action" value="time_out">
                                        <button type="submit" class="btn btn-warning">Clock Out</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>You haven't marked your attendance for today.</p>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <input type="hidden" name="action" value="time_in">
                                    <button type="submit" class="btn btn-success">Clock In</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>Leave Requests</h2>
                        </div>
                        <div class="card-body">
                            <a href="leave_request.php" class="btn btn-primary">Apply for Leave</a>
                            <a href="my_leaves.php" class="btn btn-info">My Leave History</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($role == 'admin' || $role == 'manager'): ?>
                <?php $stats = getAttendanceStats(); ?>
                <div class="admin-dashboard">
                    <div class="stats-cards">
                        <div class="stat-card">
                            <i class="fas fa-users"></i>
                            <h3>Total Employees</h3>
                            <p><?php echo $stats['total_employees']; ?></p>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <h3>Present Today</h3>
                            <p><?php echo $stats['present_today']; ?></p>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-times-circle"></i>
                            <h3>Absent Today</h3>
                            <p><?php echo $stats['absent_today']; ?></p>
                        </div>
                        
                        <div class="stat-card">
                            <i class="fas fa-clock"></i>
                            <h3>Late Today</h3>
                            <p><?php echo $stats['late_today']; ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>Pending Leave Requests</h2>
                        </div>
                        <div class="card-body">
                            <?php 
                            $pendingRequests = getPendingLeaveRequests();
                            if($pendingRequests): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Leave Type</th>
                                                <th>From</th>
                                                <th>To</th>
                                                <th>Days</th>
                                                <th>Reason</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($pendingRequests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['days']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                                    <td>
                                                        <a href="manage_leave.php?id=<?php echo $request['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                                                        <a href="manage_leave.php?id=<?php echo $request['id']; ?>&action=reject" class="btn btn-sm btn-danger">Reject</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No pending leave requests.</p>
                            <?php endif; ?>
                            
                            <a href="leave_management.php" class="btn btn-info">View All Leave Requests</a>
                        </div>
                    </div>
                    
                    <div class="admin-actions">
                        <a href="employees.php" class="btn btn-primary"><i class="fas fa-users"></i> Manage Employees</a>
                        <a href="attendance_report.php" class="btn btn-info"><i class="fas fa-chart-bar"></i> Attendance Reports</a>
                        <a href="departments.php" class="btn btn-warning"><i class="fas fa-building"></i> Manage Departments</a>
                        <a href="absent_employees.php" class="btn btn-danger"><i class="fas fa-user-times"></i> Absent List</a>
                        <?php if($role == 'admin'): ?>
                            <a href="settings.php" class="btn btn-secondary"><i class="fas fa-cog"></i> System Settings</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            timeElement.textContent = now.toLocaleTimeString();
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        updateTime(); // Initial call
    </script>
</body>
</html>
