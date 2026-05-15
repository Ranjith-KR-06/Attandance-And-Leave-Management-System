
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is a manager
if(!isLoggedIn() || !isManager()) {
    header("location: index.php");
    exit;
}

// Function to get all departments for filter
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

// Function to get leave requests
function getLeaveRequests($status = '', $department = '', $search = '') {
    global $conn;
    
    $sql = "SELECT lr.*, e.employee_id, e.first_name, e.last_name, e.department, 
            lt.name as leave_type, 
            CASE 
                WHEN lr.status = 'pending' THEN 'badge-warning'
                WHEN lr.status = 'approved' THEN 'badge-success'
                WHEN lr.status = 'rejected' THEN 'badge-danger'
                WHEN lr.status = 'cancelled' THEN 'badge-secondary'
                WHEN lr.status = 'declined' THEN 'badge-danger'
            END as status_class
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if(!empty($status)) {
        $sql .= " AND lr.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if(!empty($department)) {
        $sql .= " AND e.department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    if(!empty($search)) {
        $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
        $search = "%" . $search . "%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }
    
    $sql .= " ORDER BY lr.applied_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if(!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
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

// Initialize filters
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$department_filter = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

$departments = getDepartments();
$leave_requests = getLeaveRequests($status_filter, $department_filter, $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Leave Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Leave Management</li>
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
            </div>
            
            <div class="card-body">
                <div class="filters">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search by name or ID" value="<?php echo $search; ?>">
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
                                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="declined" <?php echo ($status_filter == 'declined') ? 'selected' : ''; ?>>Declined</option>
                                    <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <?php if(count($leave_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Leave Type</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Days</th>
                                    <th>Applied On</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($leave_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['department']); ?></td>
                                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                        <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                        <td><?php echo htmlspecialchars($request['days']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($request['applied_at'])); ?></td>
                                        <td><span class="badge <?php echo $request['status_class']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                        <td>
                                            <?php if($request['status'] == 'pending'): ?>
                                                <a href="manage_leave.php?id=<?php echo $request['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                                                <a href="manage_leave.php?id=<?php echo $request['id']; ?>&action=reject" class="btn btn-sm btn-danger">Reject</a>
                                                <a href="manage_leave.php?id=<?php echo $request['id']; ?>&action=decline" class="btn btn-sm btn-warning">Decline</a>
                                            <?php endif; ?>
                                            
                                            <?php if($request['status'] == 'rejected' && !empty($request['rejection_reason'])): ?>
                                                <button type="button" class="btn btn-sm btn-info view-reason" data-reason="<?php echo htmlspecialchars($request['rejection_reason']); ?>">View Reason</button>
                                            <?php endif; ?>
                                            
                                            <?php if($request['status'] == 'declined' && !empty($request['rejection_reason'])): ?>
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
