
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <p>&copy; <?php echo date("Y"); ?> Employee Attendance & Leave Management System</p>
        </div>
    </div>
</footer>

<script>
    // Mobile menu toggle
    document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
        document.querySelector('.mobile-menu').classList.toggle('active');
        this.classList.toggle('active');
    });

    // Close mobile menu when a link is clicked
    document.querySelectorAll('.mobile-menu a').forEach(function(link) {
        link.addEventListener('click', function() {
            document.querySelector('.mobile-menu').classList.remove('active');
            document.querySelector('.mobile-menu-btn').classList.remove('active');
        });
    });

    // Dropdown menu
    document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            this.nextElementSibling.classList.toggle('show');
        });
    });

    // Close dropdown when clicking outside
    window.addEventListener('click', function(e) {
        document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
            if (!menu.previousElementSibling.contains(e.target)) {
                menu.classList.remove('show');
            }
        });
    });
</script>
