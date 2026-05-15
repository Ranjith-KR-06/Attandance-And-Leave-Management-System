
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is admin/manager
if(!isLoggedIn() || (!isAdmin() && !isManager())) {
    header("location: index.php");
    exit;
}

// Process date filter
$filter_date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : date('Y-m-d');

// Function to get absent employees for a specific date
function getAbsentEmployees($date) {
    global $conn;
    
    // Get all active employees who don't have an attendance record for the date
    // or have a record with status 'absent'
    $sql = "SELECT e.* FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
            WHERE e.status = 'active' AND 
                  (a.id IS NULL OR a.status = 'absent')
            ORDER BY e.department, e.first_name, e.last_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Check if employee is on approved leave for this date
            $on_leave = isEmployeeOnLeave($row['id'], $date);
            $row['on_leave'] = $on_leave;
            $employees[] = $row;
        }
    }
    
    return $employees;
}

// Function to check if employee is on approved leave for a specific date
function isEmployeeOnLeave($employee_id, $date) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as count FROM leave_requests 
            WHERE employee_id = ? 
            AND status = 'approved' 
            AND ? BETWEEN start_date AND end_date";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

// Mark employee absent
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_absent'])) {
    $employee_id = sanitizeInput($_POST['employee_id']);
    $date = sanitizeInput($_POST['date']);
    $note = sanitizeInput($_POST['note'] ?? '');
    
    // Check if attendance record exists
    $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        // Update existing record
        $row = $result->fetch_assoc();
        $sql = "UPDATE attendance SET status = 'absent', note = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $note, $row['id']);
    } else {
        // Insert new record
        $sql = "INSERT INTO attendance (employee_id, date, status, note) VALUES (?, ?, 'absent', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $employee_id, $date, $note);
    }
    
    if($stmt->execute()) {
        redirectWithMessage('absent_employees.php?date=' . $date, 'Employee marked as absent successfully!', 'success');
    } else {
        redirectWithMessage('absent_employees.php?date=' . $date, 'Error marking employee as absent.', 'danger');
    }
}

$absent_employees = getAbsentEmployees($filter_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absent Employees - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Absent Employees</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Absent Employees</li>
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
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Filter</h2>
            </div>
            <div class="card-body">
                <form method="get" action="absent_employees.php" class="form-inline">
                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" class="form-control ml-2" id="date" name="date" value="<?php echo $filter_date; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary ml-2">Filter</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Absent Employees for <?php echo date('d M Y', strtotime($filter_date)); ?></h2>
            </div>
            <div class="card-body">
                <?php if(count($absent_employees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($absent_employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td>
                                            <?php if($employee['on_leave']): ?>
                                                <span class="badge badge-info">On Approved Leave</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Absent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="employee_view.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if(!$employee['on_leave']): ?>
                                                <button type="button" class="btn btn-sm btn-warning mark-absent-btn" 
                                                        data-toggle="modal" 
                                                        data-target="#markAbsentModal" 
                                                        data-id="<?php echo $employee['id']; ?>" 
                                                        data-name="<?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>">
                                                    <i class="fas fa-user-times"></i> Mark Absent
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No absent employees found for this date.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Mark Absent Modal -->
    <div class="modal fade" id="markAbsentModal" tabindex="-1" role="dialog" aria-labelledby="markAbsentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="markAbsentModalLabel">Mark Employee as Absent</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to mark <span id="employeeName"></span> as absent for <?php echo date('d M Y', strtotime($filter_date)); ?>?</p>
                        <div class="form-group">
                            <label for="note">Note (Optional):</label>
                            <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="employee_id" id="employeeId">
                        <input type="hidden" name="date" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="mark_absent" class="btn btn-danger">Mark Absent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Set employee details in the modal
        document.querySelectorAll('.mark-absent-btn').forEach(button => {
            button.addEventListener('click', function() {
                const employeeId = this.getAttribute('data-id');
                const employeeName = this.getAttribute('data-name');
                
                document.getElementById('employeeId').value = employeeId;
                document.getElementById('employeeName').textContent = employeeName;
                
                // Display the modal
                const modal = document.getElementById('markAbsentModal');
                modal.style.display = 'block';
                modal.classList.add('show');
            });
        });
        
        // Close the modal
        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', function() {
                const modal = document.getElementById('markAbsentModal');
                modal.style.display = 'none';
                modal.classList.remove('show');
            });
        });
    </script>
</body>
</html>
