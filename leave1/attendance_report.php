
<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is a manager
if(!isLoggedIn() || !isManager()) {
    header("location: index.php");
    exit;
}

// Get departments for filter
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

// Define variables
$from_date = isset($_GET['from_date']) ? sanitizeInput($_GET['from_date']) : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? sanitizeInput($_GET['to_date']) : date('Y-m-t');
$department = isset($_GET['department']) ? sanitizeInput($_GET['department']) : '';
$employee_id = isset($_GET['employee_id']) ? sanitizeInput($_GET['employee_id']) : '';

// Function to get employees by department
function getEmployeesByDepartment($department = '') {
    global $conn;
    
    $sql = "SELECT id, employee_id as emp_id, CONCAT(first_name, ' ', last_name) as name 
            FROM employees
            WHERE status = 'active'";
    
    if(!empty($department)) {
        $sql .= " AND department = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $department);
    } else {
        $stmt = $conn->prepare($sql);
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

// Function to get attendance data
function getAttendanceData($from_date, $to_date, $department = '', $employee_id = '') {
    global $conn;
    
    $sql = "SELECT a.*, e.employee_id as emp_id, e.first_name, e.last_name, e.department
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date BETWEEN ? AND ?";
    
    $params = [$from_date, $to_date];
    $types = "ss";
    
    if(!empty($department)) {
        $sql .= " AND e.department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    if(!empty($employee_id)) {
        $sql .= " AND e.id = ?";
        $params[] = $employee_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY a.date DESC, e.first_name, e.last_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
    }
    
    return $attendance;
}

// Function to get attendance summary
function getAttendanceSummary($from_date, $to_date, $department = '', $employee_id = '') {
    global $conn;
    
    $sql = "SELECT 
                e.id, e.employee_id as emp_id, e.first_name, e.last_name, e.department,
                COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.date END) as present_days,
                COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.date END) as absent_days,
                COUNT(DISTINCT CASE WHEN a.status = 'late' THEN a.date END) as late_days,
                COUNT(DISTINCT CASE WHEN a.status = 'half_day' THEN a.date END) as half_days
            FROM 
                employees e
            LEFT JOIN 
                attendance a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
            WHERE 
                e.status = 'active'";
    
    $params = [$from_date, $to_date];
    $types = "ss";
    
    if(!empty($department)) {
        $sql .= " AND e.department = ?";
        $params[] = $department;
        $types .= "s";
    }
    
    if(!empty($employee_id)) {
        $sql .= " AND e.id = ?";
        $params[] = $employee_id;
        $types .= "i";
    }
    
    $sql .= " GROUP BY e.id, e.employee_id, e.first_name, e.last_name, e.department
              ORDER BY e.first_name, e.last_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = [];
    
    if($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Calculate total working days in the date range
            $start = new DateTime($from_date);
            $end = new DateTime($to_date);
            $end->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end);
            
            $working_days = 0;
            foreach($period as $day) {
                $dayOfWeek = $day->format('N');
                if($dayOfWeek < 6) { // Mon-Fri are working days
                    $working_days++;
                }
            }
            
            $row['working_days'] = $working_days;
            $row['attendance_rate'] = $working_days > 0 ? round(($row['present_days'] / $working_days) * 100, 2) : 0;
            
            $summary[] = $row;
        }
    }
    
    return $summary;
}

$departments = getDepartments();
$employees = !empty($department) ? getEmployeesByDepartment($department) : [];

// Determine which report to show
$report_type = isset($_GET['report_type']) ? sanitizeInput($_GET['report_type']) : 'summary';

if($report_type == 'summary') {
    $report_data = getAttendanceSummary($from_date, $to_date, $department, $employee_id);
} else {
    $report_data = getAttendanceData($from_date, $to_date, $department, $employee_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Attendance Reports</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Attendance Reports</li>
                </ol>
            </nav>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Report Filters</h2>
            </div>
            
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>From Date</label>
                            <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>" required>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label>To Date</label>
                            <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>" required>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label>Department</label>
                            <select name="department" class="form-control" id="department" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department == $dept) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label>Employee</label>
                            <select name="employee_id" class="form-control" id="employee">
                                <option value="">All Employees</option>
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_id == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['emp_id'] . ' - ' . $emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Report Type</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="report_type" id="report_summary" value="summary" <?php echo ($report_type == 'summary') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="report_summary">Summary Report</label>
                            </div>
                            
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="report_type" id="report_detailed" value="detailed" <?php echo ($report_type == 'detailed') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="report_detailed">Detailed Report</label>
                            </div>
                        </div>
                        
                        <div class="form-group col-md-6 text-right">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate Report</button>
                            <button type="button" id="exportBtn" class="btn btn-success"><i class="fas fa-file-excel"></i> Export to Excel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2>
                    <?php echo ($report_type == 'summary') ? 'Attendance Summary Report' : 'Detailed Attendance Report'; ?>
                    <small><?php echo date('d M Y', strtotime($from_date)) . ' to ' . date('d M Y', strtotime($to_date)); ?></small>
                </h2>
            </div>
            
            <div class="card-body">
                <?php if (count($report_data) > 0): ?>
                    <?php if ($report_type == 'summary'): ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Working Days</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Half Day</th>
                                        <th>Attendance Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['emp_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo $row['working_days']; ?></td>
                                            <td><?php echo $row['present_days']; ?></td>
                                            <td><?php echo $row['absent_days']; ?></td>
                                            <td><?php echo $row['late_days']; ?></td>
                                            <td><?php echo $row['half_days']; ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo ($row['attendance_rate'] >= 90) ? 'success' : (($row['attendance_rate'] >= 75) ? 'warning' : 'danger'); ?>" 
                                                         style="width: <?php echo $row['attendance_rate']; ?>%">
                                                        <?php echo $row['attendance_rate']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['emp_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($row['status'] == 'present') ? 'success' : 
                                                        (($row['status'] == 'absent') ? 'danger' : 
                                                        (($row['status'] == 'late') ? 'warning' : 'info')); 
                                                ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['time_in'] ? $row['time_in'] : '-'; ?></td>
                                            <td><?php echo $row['time_out'] ? $row['time_out'] : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No data available for the selected criteria.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Handle department change to load employees
        document.getElementById('department').addEventListener('change', function() {
            document.querySelector('form').submit();
        });
        
        // Export to Excel functionality
        document.getElementById('exportBtn').addEventListener('click', function() {
            // Simple CSV export
            let table = document.getElementById('reportTable');
            let csv = [];
            
            // Add headers
            let headers = [];
            for (let i = 0; i < table.rows[0].cells.length; i++) {
                headers.push(table.rows[0].cells[i].textContent);
            }
            csv.push(headers.join(','));
            
            // Add rows
            for (let i = 1; i < table.rows.length; i++) {
                let row = [];
                for (let j = 0; j < table.rows[i].cells.length; j++) {
                    let cell = table.rows[i].cells[j].textContent.trim().replace(/,/g, ' ');
                    row.push(cell);
                }
                csv.push(row.join(','));
            }
            
            // Download CSV
            let csvContent = csv.join('\n');
            let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement('a');
            let url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'attendance_report_<?php echo date("Ymd"); ?>.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    </script>
</body>
</html>
