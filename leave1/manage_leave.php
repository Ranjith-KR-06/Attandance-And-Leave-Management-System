
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is a manager
if(!isLoggedIn() || !isManager()) {
    header("location: index.php");
    exit;
}

// Check if we have request ID and action
if(!isset($_GET['id']) || !isset($_GET['action'])) {
    redirectWithMessage('dashboard.php', 'Invalid request.', 'danger');
}

$leave_id = sanitizeInput($_GET['id']);
$action = sanitizeInput($_GET['action']);

// Get leave request details
function getLeaveRequest($id) {
    global $conn;
    
    $sql = "SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type 
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.id = ? AND lr.status = 'pending'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

$leave_request = getLeaveRequest($leave_id);

if(!$leave_request) {
    redirectWithMessage('dashboard.php', 'Leave request not found or already processed.', 'warning');
}

// Handle approve, reject, or decline
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if($action == 'approve') {
        // Approve leave request
        $sql = "UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $_SESSION['user_id'], $leave_id);
        
        if($stmt->execute()) {
            // Update leave balance
            $sql = "UPDATE employee_leave_balances 
                    SET used_days = used_days + ?
                    WHERE employee_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dii", $leave_request['days'], $leave_request['employee_id'], $leave_request['leave_type_id']);
            $stmt->execute();
            
            // Mark as absent for all dates in the leave period
            markLeaveAbsence($leave_request['employee_id'], $leave_request['start_date'], $leave_request['end_date']);
            
            redirectWithMessage('dashboard.php', 'Leave request approved successfully.', 'success');
        } else {
            redirectWithMessage('manage_leave.php?id=' . $leave_id . '&action=' . $action, 'Error: Unable to approve leave request.', 'danger');
        }
    } elseif($action == 'reject') {
        $rejection_reason = sanitizeInput($_POST['rejection_reason']);
        
        // Reject leave request
        $sql = "UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $_SESSION['user_id'], $rejection_reason, $leave_id);
        
        if($stmt->execute()) {
            redirectWithMessage('dashboard.php', 'Leave request rejected successfully.', 'success');
        } else {
            redirectWithMessage('manage_leave.php?id=' . $leave_id . '&action=' . $action, 'Error: Unable to reject leave request.', 'danger');
        }
    } elseif($action == 'decline') {
        $rejection_reason = sanitizeInput($_POST['rejection_reason']);
        
        // Decline leave request
        $sql = "UPDATE leave_requests SET status = 'declined', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $_SESSION['user_id'], $rejection_reason, $leave_id);
        
        if($stmt->execute()) {
            redirectWithMessage('dashboard.php', 'Leave request declined successfully. The employee may revise and resubmit.', 'success');
        } else {
            redirectWithMessage('manage_leave.php?id=' . $leave_id . '&action=' . $action, 'Error: Unable to decline leave request.', 'danger');
        }
    }
}

// Function to mark absence for leave period
function markLeaveAbsence($employee_id, $start_date, $end_date) {
    global $conn;
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include end date
    
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $dt) {
        $current_date = $dt->format("Y-m-d");
        
        // Skip weekends if they're not working days
        $day_of_week = date('N', strtotime($current_date));
        if($day_of_week >= 6) { // 6 = Saturday, 7 = Sunday
            continue;
        }
        
        // Check if attendance record exists for this date
        $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("is", $employee_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            // Update existing record
            $row = $result->fetch_assoc();
            $sql = "UPDATE attendance SET status = 'absent', note = 'On approved leave' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $row['id']);
        } else {
            // Insert new record
            $sql = "INSERT INTO attendance (employee_id, date, status, note) VALUES (?, ?, 'absent', 'On approved leave')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $employee_id, $current_date);
        }
        
        $stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Request - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>
                <?php 
                    if ($action == 'approve') {
                        echo 'Approve';
                    } elseif ($action == 'reject') {
                        echo 'Reject';
                    } else {
                        echo 'Decline';
                    }
                ?> Leave Request
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php 
                            if ($action == 'approve') {
                                echo 'Approve';
                            } elseif ($action == 'reject') {
                                echo 'Reject';
                            } else {
                                echo 'Decline';
                            }
                        ?> Leave Request
                    </li>
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
                <h2>Leave Request Details</h2>
            </div>
            
            <div class="card-body">
                <div class="leave-details">
                    <div class="detail-row">
                        <span class="detail-label">Employee:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($leave_request['first_name'] . ' ' . $leave_request['last_name']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Leave Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($leave_request['leave_type']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">From:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($leave_request['start_date']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">To:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($leave_request['end_date']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Days:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($leave_request['days']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Applied On:</span>
                        <span class="detail-value"><?php echo date('d M Y H:i', strtotime($leave_request['applied_at'])); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Reason:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($leave_request['reason']); ?></span>
                    </div>
                </div>
                
                <div class="action-form">
                    <?php if($action == 'approve'): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $leave_id . '&action=' . $action); ?>" method="post">
                            <div class="form-group">
                                <p>Are you sure you want to approve this leave request?</p>
                                <p class="text-info"><i class="fas fa-info-circle"></i> Note: Approving this request will mark the employee as absent (on leave) for all dates in the leave period.</p>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" class="btn btn-success" value="Approve Leave">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php elseif($action == 'reject'): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $leave_id . '&action=' . $action); ?>" method="post">
                            <div class="form-group">
                                <label>Rejection Reason</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" class="btn btn-danger" value="Reject Leave">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $leave_id . '&action=' . $action); ?>" method="post">
                            <div class="form-group">
                                <label>Decline Reason (Employee can revise and resubmit)</label>
                                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                                <small class="form-text text-muted">Declining allows the employee to revise and resubmit the leave request.</small>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" class="btn btn-warning" value="Decline Leave">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
