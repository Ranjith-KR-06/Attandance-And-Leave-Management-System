
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in, if not redirect to login page
if(!isLoggedIn()) {
    header("location: index.php");
    exit;
}

// Get employee details
$user_id = $_SESSION["user_id"];
$employee = getEmployeeByUserId($user_id);

if(!$employee) {
    redirectWithMessage('dashboard.php', 'You do not have an employee profile.', 'warning');
}

// Function to get leave requests for an employee
function getEmployeeLeaveRequests($employee_id) {
    global $conn;
    
    $sql = "SELECT lr.*, lt.name as leave_type, 
            CASE 
                WHEN lr.status = 'pending' THEN 'badge-warning'
                WHEN lr.status = 'approved' THEN 'badge-success'
                WHEN lr.status = 'rejected' THEN 'badge-danger'
                WHEN lr.status = 'cancelled' THEN 'badge-secondary'
            END as status_class
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ?
            ORDER BY lr.applied_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
    }
    
    return $requests;
}

// Handle cancellation of leave request
if(isset($_GET['cancel']) && isset($_GET['id'])) {
    $leave_id = sanitizeInput($_GET['id']);
    
    // Check if leave request belongs to the employee and is in pending status
    $sql = "SELECT * FROM leave_requests WHERE id = ? AND employee_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $leave_id, $employee['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        // Cancel the leave request
        $sql = "UPDATE leave_requests SET status = 'cancelled' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leave_id);
        
        if($stmt->execute()) {
            redirectWithMessage('my_leaves.php', 'Leave request cancelled successfully.', 'success');
        } else {
            redirectWithMessage('my_leaves.php', 'Error: Unable to cancel leave request.', 'danger');
        }
    } else {
        redirectWithMessage('my_leaves.php', 'Error: You cannot cancel this leave request.', 'danger');
    }
}

$leave_requests = getEmployeeLeaveRequests($employee['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave History - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>My Leave History</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Leave History</li>
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
                <h2>Leave Requests</h2>
                <div class="card-actions">
                    <a href="leave_request.php" class="btn btn-primary"><i class="fas fa-plus"></i> Apply for Leave</a>
                </div>
            </div>
            
            <div class="card-body">
                <?php if(count($leave_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Applied On</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($leave_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                        <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                        <td><?php echo htmlspecialchars($request['days']); ?></td>
                                        <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($request['applied_at'])); ?></td>
                                        <td><span class="badge <?php echo $request['status_class']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                        <td>
                                            <?php if($request['status'] == 'pending'): ?>
                                                <a href="my_leaves.php?cancel=1&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this leave request?')">Cancel</a>
                                            <?php endif; ?>
                                            
                                            <?php if($request['status'] == 'rejected' && !empty($request['rejection_reason'])): ?>
                                                <button type="button" class="btn btn-sm btn-info view-reason" data-reason="<?php echo htmlspecialchars($request['rejection_reason']); ?>">View Reason</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No leave requests found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Rejection Reason Modal -->
    <div class="modal" id="reasonModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rejection Reason</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="rejectionReason"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // View rejection reason
        document.querySelectorAll('.view-reason').forEach(function(button) {
            button.addEventListener('click', function() {
                const reason = this.getAttribute('data-reason');
                document.getElementById('rejectionReason').textContent = reason;
                
                // Show modal
                const modal = document.getElementById('reasonModal');
                modal.style.display = 'block';
                
                // Close modal when clicking the close button
                document.querySelector('#reasonModal .close').addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                // Close modal when clicking the close button in footer
                document.querySelector('#reasonModal .btn-secondary').addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                // Close modal when clicking outside of it
                window.addEventListener('click', function(event) {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
