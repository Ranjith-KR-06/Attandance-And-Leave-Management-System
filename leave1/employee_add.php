<?php
// Include config file
require_once "config/config.php";

// Check if the user is logged in and is admin
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

// Define variables and initialize with empty values
$employee_id = $first_name = $last_name = $email = $department = $position = $join_date = $phone = $address = "";
$employee_id_err = $first_name_err = $last_name_err = $email_err = $department_err = $position_err = $join_date_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate employee ID
    if(empty(trim($_POST["employee_id"]))) {
        $employee_id_err = "Please enter an employee ID.";
    } else {
        // Check if employee ID exists
        $sql = "SELECT id FROM employees WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $param_employee_id);
        $param_employee_id = trim($_POST["employee_id"]);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0) {
            $employee_id_err = "This employee ID is already taken.";
        } else {
            $employee_id = trim($_POST["employee_id"]);
        }
        
        $stmt->close();
    }
    
    // Validate first name
    if(empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }
    
    // Validate last name
    if(empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter email.";
    } else {
        // Check if email exists
        $sql = "SELECT id FROM employees WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $param_email);
        $param_email = trim($_POST["email"]);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0) {
            $email_err = "This email is already taken.";
        } else {
            $email = trim($_POST["email"]);
        }
        
        $stmt->close();
    }
    
    // Validate department
    if(empty(trim($_POST["department"]))) {
        $department_err = "Please enter department.";
    } else {
        $department = trim($_POST["department"]);
    }
    
    // Validate position
    if(empty(trim($_POST["position"]))) {
        $position_err = "Please enter position.";
    } else {
        $position = trim($_POST["position"]);
    }
    
    // Validate join date
    if(empty(trim($_POST["join_date"]))) {
        $join_date_err = "Please enter join date.";
    } else {
        $join_date = trim($_POST["join_date"]);
    }
    
    // Other optional fields
    $phone = !empty($_POST["phone"]) ? trim($_POST["phone"]) : NULL;
    $address = !empty($_POST["address"]) ? trim($_POST["address"]) : NULL;
    $password = trim($_POST["password"]) ? trim($_POST["password"]) : "password123"; // Default password
    
    // Check input errors before inserting into database
    if(empty($employee_id_err) && empty($first_name_err) && empty($last_name_err) && empty($email_err) && empty($department_err) && empty($position_err) && empty($join_date_err)) {
        // Create username from first name and last name
        $username = strtolower($first_name . "." . $last_name);
        
        // Check if username exists
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0) {
            // Append a number to make username unique
            $i = 1;
            $base_username = $username;
            while(true) {
                $username = $base_username . $i;
                $stmt->close();
                
                $sql = "SELECT id FROM users WHERE username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();
                
                if($stmt->num_rows == 0) {
                    break;
                }
                
                $i++;
            }
        }
        
        $stmt->close();
        
        // Prepare an insert statement for user
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'employee')";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ss", $username, $password);
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Now insert employee details
                $sql = "INSERT INTO employees (user_id, employee_id, first_name, last_name, email, department, position, join_date, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                if($stmt = $conn->prepare($sql)) {
                    // Bind variables to the prepared statement as parameters
                    $stmt->bind_param("isssssssss", $user_id, $employee_id, $first_name, $last_name, $email, $department, $position, $join_date, $phone, $address);
                    
                    // Attempt to execute the prepared statement
                    if($stmt->execute()) {
                        redirectWithMessage('employees.php', 'Employee added successfully. Username: ' . $username . ', Password: ' . $password, 'success');
                    } else {
                        // Delete user if employee insert fails
                        $conn->query("DELETE FROM users WHERE id = " . $user_id);
                        redirectWithMessage('employee_add.php', 'Oops! Something went wrong. Please try again later.', 'danger');
                    }
                    
                    // Close statement
                    $stmt->close();
                }
            } else {
                redirectWithMessage('employee_add.php', 'Oops! Something went wrong. Please try again later.', 'danger');
            }
            
            // Close statement
            $stmt->close();
        }
    }
}

$departments = getDepartments();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Add New Employee</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="employees.php">Employees</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add Employee</li>
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
                <h2>Employee Information</h2>
            </div>
            
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Employee ID *</label>
                                <input type="text" name="employee_id" class="form-control <?php echo (!empty($employee_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $employee_id; ?>" placeholder="e.g., EMP001">
                                <span class="invalid-feedback"><?php echo $employee_id_err; ?></span>
                            </div>
                        
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $first_name; ?>">
                                <span class="invalid-feedback"><?php echo $first_name_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $last_name; ?>">
                                <span class="invalid-feedback"><?php echo $last_name_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                                <span class="invalid-feedback"><?php echo $email_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Password (Default: password123)</label>
                                <input type="password" name="password" class="form-control" placeholder="Leave blank for default password">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department *</label>
                                <select name="department" class="form-control <?php echo (!empty($department_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department == $dept) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="invalid-feedback"><?php echo $department_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Position *</label>
                                <input type="text" name="position" class="form-control <?php echo (!empty($position_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $position; ?>">
                                <span class="invalid-feedback"><?php echo $position_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Join Date *</label>
                                <input type="date" name="join_date" class="form-control <?php echo (!empty($join_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $join_date; ?>">
                                <span class="invalid-feedback"><?php echo $join_date_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo $phone; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo $address; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" class="btn btn-primary" value="Add Employee">
                        <a href="employees.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
