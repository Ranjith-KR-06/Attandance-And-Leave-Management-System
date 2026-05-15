
<?php
// Include config file
require_once "config/config.php";

// Check if the user is already logged in, if yes redirect to dashboard
if(isLoggedIn()) {
    header("location: dashboard.php");
    exit;
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if username is empty
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = sanitizeInput($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty($_POST["password"])) {
        $password_err = "Please enter your password.";
    } else {
        $password = $_POST["password"];
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists, if yes then verify password
                if($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $role);
                    if($stmt->fetch()) {
                        // Using plain text password comparison
                        if($password === $hashed_password) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            
                            // Redirect user to welcome page
                            header("location: dashboard.php");
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Employee Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>Employee Attendance & Leave Management System</h1>
        </div>
        
        <div class="login-box">
            <div class="login-form">
                <h2>Login</h2>
                
                <?php 
                if(!empty($login_err)){
                    echo '<div class="alert alert-danger">' . $login_err . '</div>';
                }        
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>    
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" class="btn btn-primary" value="Login">
                    </div>
                </form>
            </div>
            
            <div class="login-info">
                <p>Default Admin Credentials:</p>
                <p>Username: admin</p>
                <p>Password: password</p>
            </div>
        </div>
    </div>
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Employee Attendance & Leave Management System</p>
        </div>
    </footer>
</body>
</html>
