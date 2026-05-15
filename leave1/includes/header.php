
<header class="header">
    <div class="navbar">
        <div class="navbar-brand">
            <a href="dashboard.php">Employee Attendance System</a>
        </div>
        
        <?php if(isset($_SESSION["user_id"])): ?>
            <div class="navbar-menu">
                <div class="nav-links-container">
                    <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php if(isAdmin()): ?>
                        <a href="employees.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                        <a href="departments.php" class="nav-link"><i class="fas fa-building"></i> Departments</a>
                        <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
                    <?php endif; ?>
                    <?php if(isManager()): ?>
                        <a href="leave_management.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Leave Management</a>
                        <a href="attendance_report.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a>
                    <?php endif; ?>
                    <?php if(!isAdmin() && !isManager()): ?>
                        <a href="leave_request.php" class="nav-link"><i class="fas fa-calendar-plus"></i> Request Leave</a>
                        <a href="my_leaves.php" class="nav-link"><i class="fas fa-history"></i> My Leaves</a>
                    <?php endif; ?>
                </div>
                <div class="user-menu">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($_SESSION["username"]); ?> <i class="fas fa-angle-down"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="fas fa-id-card"></i> My Profile</a>
                        <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        <?php endif; ?>
    </div>
</header>
