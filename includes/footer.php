<?php if (isLoggedIn() && !isset($is_login_page)): ?>
    </div> <!-- .content -->

    <footer class="footer bg-white py-3 mt-auto border-top">
        <div class="container text-center">
            <span class="text-muted">&copy; <?php echo date('Y'); ?> Portaldik - E-Learning Coding. All rights reserved.</span>
        </div>
    </footer>

<?php else: ?>
    <?php if (isset($is_login_page)): ?>
        </div> <!-- .container -->
        
        <footer class="footer bg-white py-3 mt-auto border-top">
            <div class="container text-center">
                <span class="text-muted">&copy; <?php echo date('Y'); ?> Portaldik - E-Learning Coding. All rights reserved.</span>
            </div>
        </footer>
    <?php endif; ?>
<?php endif; ?>

<!-- Custom JavaScript -->
<script>
    $(document).ready(function() {
        // Toggle sidebar on mobile
        $('#sidebarToggle').click(function() {
            $('.sidebar').toggleClass('d-none d-sm-block');
            
            if ($('.sidebar').hasClass('d-none')) {
                $('.content').css('margin-left', '0');
            } else {
                $('.content').css('margin-left', '');
            }
        });
        
        // Show tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Auto-hide flash messages after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    });
</script>
</body>
</html> 