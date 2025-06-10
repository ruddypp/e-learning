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
            // Check if we're in mobile view
            if ($(window).width() <= 576) {
                // Toggle sidebar width between 0 and 250px
                if ($('.sidebar').width() === 0) {
                    $('.sidebar').css('width', '250px');
                    $('.content').css('margin-left', '250px');
                } else {
                    $('.sidebar').css('width', '0');
                    $('.content').css('margin-left', '0');
                }
            } else {
                // Handle tablet/desktop toggle
                $('.sidebar').toggleClass('collapsed');
                
                if ($('.sidebar').hasClass('collapsed')) {
                    $('.sidebar').css('width', '80px');
                    $('.content').css('margin-left', '80px');
                } else {
                    $('.sidebar').css('width', '250px');
                    $('.content').css('margin-left', '250px');
                }
            }
        });
        
        // Handle window resize events
        $(window).resize(function() {
            var windowWidth = $(window).width();
            
            // Reset styles when transitioning between breakpoints
            if (windowWidth > 576) {
                // Moving from mobile to tablet/desktop
                if ($('.sidebar').width() === 0) {
                    $('.sidebar').css('width', '');
                    $('.content').css('margin-left', '');
                }
            } else {
                // Moving from tablet/desktop to mobile
                if (!$('.sidebar').hasClass('collapsed') && $('.sidebar').width() > 0) {
                    $('.sidebar').css('width', '0');
                    $('.content').css('margin-left', '0');
                }
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