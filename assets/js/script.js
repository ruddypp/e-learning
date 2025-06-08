/**
 * MENTARI E-Learning Custom JavaScript
 */

$(document).ready(function() {
    // Toggle sidebar on mobile
    $('#sidebarToggle').click(function() {
        if ($('.sidebar').width() > 0) {
            $('.sidebar').css('width', '0');
            $('.content').css('margin-left', '0');
        } else {
            if ($(window).width() <= 576) {
                $('.sidebar').css('width', '80px');
                $('.content').css('margin-left', '80px');
            } else {
                $('.sidebar').css('width', '250px');
                $('.content').css('margin-left', '250px');
            }
        }
    });
    
    // Auto-hide flash messages after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Confirm delete actions
    $('.confirm-delete').click(function(e) {
        if (!confirm('Apakah Anda yakin ingin menghapus item ini?')) {
            e.preventDefault();
        }
    });
});

/**
 * Function to show a loading spinner
 */
function showLoading() {
    if (!$('#loading-spinner').length) {
        $('body').append('<div id="loading-spinner" class="position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-white bg-opacity-75" style="z-index: 9999;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    }
    $('#loading-spinner').show();
}

/**
 * Function to hide the loading spinner
 */
function hideLoading() {
    $('#loading-spinner').hide();
}

/**
 * Function to show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    var bgClass = 'bg-info';
    var icon = 'fa-info-circle';
    
    switch (type) {
        case 'success':
            bgClass = 'bg-success';
            icon = 'fa-check-circle';
            break;
        case 'error':
            bgClass = 'bg-danger';
            icon = 'fa-exclamation-circle';
            break;
        case 'warning':
            bgClass = 'bg-warning';
            icon = 'fa-exclamation-triangle';
            break;
    }
    
    var toast = `
        <div class="toast align-items-center text-white ${bgClass} border-0 position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icon} me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    $('body').append(toast);
    var toastElement = $('.toast').last();
    var toastInstance = new bootstrap.Toast(toastElement, {
        delay: 5000
    });
    toastInstance.show();
    
    setTimeout(function() {
        toastElement.remove();
    }, 5500);
}

/**
 * Function to format a date string
 * @param {string} dateString - The date string to format
 * @param {boolean} withTime - Whether to include time
 * @return {string} Formatted date string
 */
function formatDate(dateString, withTime = false) {
    if (!dateString) return '';
    
    var date = new Date(dateString);
    var options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    
    if (withTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }
    
    return date.toLocaleDateString('id-ID', options);
}

/**
 * Function to handle AJAX form submissions
 * @param {string} formId - The ID of the form
 * @param {function} successCallback - Callback function on success
 */
function handleAjaxForm(formId, successCallback) {
    $('#' + formId).submit(function(e) {
        e.preventDefault();
        
        var form = $(this);
        var url = form.attr('action');
        var method = form.attr('method') || 'POST';
        var formData = new FormData(this);
        
        $.ajax({
            url: url,
            type: method,
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                showLoading();
            },
            success: function(response) {
                hideLoading();
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function(xhr) {
                hideLoading();
                showToast('Terjadi kesalahan. Silakan coba lagi.', 'error');
                console.error(xhr.responseText);
            }
        });
    });
} 