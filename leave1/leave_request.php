
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

// Get leave types
function getLeaveTypes() {
    global $conn;
    $sql = "SELECT * FROM leave_types";
    $result = $conn->query($sql);
    $types = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
    }
    return $types;
}

// Get employee leave balances
function getLeaveBalances($employee_id) {
    global $conn;
    $current_year = date('Y');
    
    $sql = "SELECT elb.*, lt.name as leave_type 
            FROM employee_leave_balances elb
            JOIN leave_types lt ON elb.leave_type_id = lt.id
            WHERE elb.employee_id = ? AND elb.year = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $employee_id, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $balances = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $balances[$row['leave_type_id']] = $row;
        }
    }
    
    // For leave types without a balance record, create default
    $leave_types = getLeaveTypes();
    foreach($leave_types as $type) {
        if(!isset($balances[$type['id']])) {
            // Check if we need to insert a new balance record
            $sql = "INSERT INTO employee_leave_balances (employee_id, leave_type_id, allocated_days, year) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiis", $employee_id, $type['id'], $type['allowed_days'], $current_year);
            $stmt->execute();
            
            $balances[$type['id']] = [
                'id' => $conn->insert_id,
                'employee_id' => $employee_id,
                'leave_type_id' => $type['id'],
                'allocated_days' => $type['allowed_days'],
                'used_days' => 0,
                'year' => $current_year,
                'leave_type' => $type['name']
            ];
        }
    }
    
    return $balances;
}

// Function to calculate business days between two dates
function getBusinessDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day');
    
    $interval = new DateInterval('P1D');
    $periods = new DatePeriod($start, $interval, $end);
    
    $days = 0;
    foreach ($periods as $period) {
        $dayOfWeek = $period->format('N');
        if ($dayOfWeek < 6) { // 6 = Saturday, 7 = Sunday
            $days++;
        }
    }
    
    return $days;
}

// Define variables and initialize with empty values
$leave_type_id = $start_date = $end_date = $reason = "";
$leave_type_id_err = $start_date_err = $end_date_err = $reason_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate leave type
    if(empty($_POST["leave_type_id"])) {
        $leave_type_id_err = "Please select a leave type.";
    } else {
        $leave_type_id = sanitizeInput($_POST["leave_type_id"]);
    }
    
    // Validate start date
    if(empty($_POST["start_date"])) {
        $start_date_err = "Please enter a start date.";
    } else {
        $start_date = sanitizeInput($_POST["start_date"]);
        // Check if start date is not in the past
        if(strtotime($start_date) < strtotime(date('Y-m-d'))) {
            $start_date_err = "Start date cannot be in the past.";
        }
    }
    
    // Validate end date
    if(empty($_POST["end_date"])) {
        $end_date_err = "Please enter an end date.";
    } else {
        $end_date = sanitizeInput($_POST["end_date"]);
        // Check if end date is not before start date
        if(strtotime($end_date) < strtotime($start_date)) {
            $end_date_err = "End date cannot be before start date.";
        }
    }
    
    // Validate reason
    if(empty($_POST["reason"])) {
        $reason_err = "Please enter a reason for the leave.";
    } else {
        $reason = sanitizeInput($_POST["reason"]);
    }
    
    // Check input errors before inserting into database
    if(empty($leave_type_id_err) && empty($start_date_err) && empty($end_date_err) && empty($reason_err)) {
        // Calculate number of leave days
        $days = getBusinessDays($start_date, $end_date);
        
        // Check if employee has enough leave balance
        $balances = getLeaveBalances($employee['id']);
        $available_balance = $balances[$leave_type_id]['allocated_days'] - $balances[$leave_type_id]['used_days'];
        
        if($days > $available_balance) {
            $leave_type_id_err = "You don't have enough leave balance. Available: " . $available_balance . " days.";
        } else {
            // Prepare an insert statement
            $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, days, reason) VALUES (?, ?, ?, ?, ?, ?)";
            
            if($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("iissds", $employee['id'], $leave_type_id, $start_date, $end_date, $days, $reason);
                
                // Attempt to execute the prepared statement
                if($stmt->execute()) {
                    redirectWithMessage('my_leaves.php', 'Your leave request has been submitted successfully.', 'success');
                } else {
                    $reason_err = "Oops! Something went wrong. Please try again later.";
                }
                
                // Close statement
                $stmt->close();
            }
        }
    }
}

$leave_types = getLeaveTypes();
$leave_balances = getLeaveBalances($employee['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Apply for Leave</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Apply for Leave</li>
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
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2>Leave Application Form</h2>
                    </div>
                    
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="form-group">
                                <label>Leave Type</label>
                                <select name="leave_type_id" class="form-control <?php echo (!empty($leave_type_id_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Leave Type</option>
                                    <?php foreach($leave_types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" <?php echo ($leave_type_id == $type['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="invalid-feedback"><?php echo $leave_type_id_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control <?php echo (!empty($start_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $start_date; ?>">
                                <span class="invalid-feedback"><?php echo $start_date_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control <?php echo (!empty($end_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $end_date; ?>">
                                <span class="invalid-feedback"><?php echo $end_date_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Reason</label>
                                <textarea name="reason" class="form-control <?php echo (!empty($reason_err)) ? 'is-invalid' : ''; ?>" rows="4"><?php echo $reason; ?></textarea>
                                <span class="invalid-feedback"><?php echo $reason_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <input type="submit" class="btn btn-primary" value="Submit Request">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h2>Leave Balances</h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Available</th>
                                        <th>Used</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($leave_balances as $balance): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($balance['leave_type']); ?></td>
                                            <td><?php echo $balance['allocated_days'] - $balance['used_days']; ?></td>
                                            <td><?php echo $balance['used_days']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
