jQuery(document).ready(function($) {
    'use strict';

    var momoCheckout = {
        
        init: function() {
            this.bindEvents();
            this.setupPaymentMethod();
        },

        bindEvents: function() {
            // Handle payment method selection
            $('body').on('change', 'input[name="payment_method"]', this.onPaymentMethodChange);
            
            // Handle place order button
            $('body').on('click', '#place_order', this.onPlaceOrder);
            
            // Handle MoMo payment form submission
            $('body').on('submit', 'form.checkout', this.onCheckoutSubmit);
            
            // Handle payment status check
            $(window).on('beforeunload', this.onPageUnload);
        },

        setupPaymentMethod: function() {
            // Add MoMo-specific styling when method is selected
            if ($('input[name="payment_method"]:checked').val() === 'momo_business') {
                this.showMoMoInstructions();
            }
        },

        onPaymentMethodChange: function() {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            
            if (selectedMethod === 'momo_business') {
                momoCheckout.showMoMoInstructions();
            } else {
                momoCheckout.hideMoMoInstructions();
            }
        },

        showMoMoInstructions: function() {
            // Remove existing instructions
            $('.momo-payment-instructions').remove();
            
            // Create instructions HTML
            var instructions = '<div class="momo-payment-instructions">' +
                '<div class="momo-payment-info">' +
                    '<h4><img src="' + momo_business_params.plugin_url + 'assets/images/momo-logo.svg" alt="MoMo" class="momo-logo-small"> ' + 
                    'Thanh toán bằng MoMo</h4>' +
                    '<p>Bạn sẽ được chuyển hướng đến MoMo để hoàn thành thanh toán một cách an toàn.</p>' +
                    '<ul class="momo-payment-steps">' +
                        '<li>Nhấn "Đặt hàng" để tiếp tục</li>' +
                        '<li>Đăng nhập vào tài khoản MoMo của bạn</li>' +
                        '<li>Xác nhận thông tin thanh toán</li>' +
                        '<li>Hoàn thành giao dịch</li>' +
                    '</ul>' +
                    '<div class="momo-security-note">' +
                        '<span class="dashicons dashicons-lock"></span>' +
                        'Giao dịch được bảo mật bởi MoMo Business' +
                    '</div>' +
                '</div>' +
                '</div>';
            
            // Insert after MoMo payment method
            $('input[value="momo_business"]').closest('.wc_payment_method').after(instructions);
        },

        hideMoMoInstructions: function() {
            $('.momo-payment-instructions').fadeOut(300, function() {
                $(this).remove();
            });
        },

        onPlaceOrder: function(e) {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            
            if (selectedMethod === 'momo_business') {
                // Add visual feedback
                momoCheckout.showProcessingMessage();
            }
        },

        onCheckoutSubmit: function(e) {
            var selectedMethod = $('input[name="payment_method"]:checked').val();
            
            if (selectedMethod === 'momo_business') {
                // Validate required fields
                if (!momoCheckout.validateCheckoutForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show processing state
                momoCheckout.showProcessingMessage();
            }
        },

        validateCheckoutForm: function() {
            var isValid = true;
            var errors = [];
            
            // Check required billing fields
            var requiredFields = [
                'billing_first_name',
                'billing_last_name',
                'billing_email',
                'billing_phone'
            ];
            
            requiredFields.forEach(function(fieldName) {
                var field = $('#' + fieldName);
                if (field.length && !field.val().trim()) {
                    errors.push(field.closest('.form-row').find('label').text().replace('*', ''));
                    isValid = false;
                }
            });
            
            // Validate email format
            var email = $('#billing_email').val();
            if (email && !momoCheckout.isValidEmail(email)) {
                errors.push('Email không hợp lệ');
                isValid = false;
            }
            
            // Validate phone format
            var phone = $('#billing_phone').val();
            if (phone && !momoCheckout.isValidPhone(phone)) {
                errors.push('Số điện thoại không hợp lệ');
                isValid = false;
            }
            
            if (!isValid) {
                momoCheckout.showValidationErrors(errors);
            }
            
            return isValid;
        },

        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        isValidPhone: function(phone) {
            // Vietnamese phone number validation
            var phoneRegex = /^(\+84|84|0)[3|5|7|8|9][0-9]{8}$/;
            return phoneRegex.test(phone.replace(/\s/g, ''));
        },

        showValidationErrors: function(errors) {
            var errorHtml = '<ul class="woocommerce-error" role="alert">';
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + ' là bắt buộc.</li>';
            });
            errorHtml += '</ul>';
            
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
            $('form.checkout').prepend(errorHtml);
            
            $('html, body').animate({
                scrollTop: $('form.checkout').offset().top - 100
            }, 1000);
        },

        showProcessingMessage: function() {
            // Remove existing messages
            $('.momo-processing-message').remove();
            
            // Create processing message
            var processingHtml = '<div class="momo-processing-message">' +
                '<div class="momo-processing-content">' +
                    '<div class="momo-spinner"></div>' +
                    '<h4>' + momo_business_params.processing_text + '</h4>' +
                    '<p>Vui lòng không đóng trình duyệt...</p>' +
                '</div>' +
                '</div>';
            
            $('body').append(processingHtml);
            
            // Disable place order button
            $('#place_order').prop('disabled', true).text('Đang xử lý...');
        },

        hideProcessingMessage: function() {
            $('.momo-processing-message').fadeOut(300, function() {
                $(this).remove();
            });
            
            $('#place_order').prop('disabled', false).text('Đặt hàng');
        },

        onPageUnload: function() {
            // Clean up any pending operations
            if ($('.momo-processing-message').length) {
                // Cancel any pending AJAX requests if needed
            }
        },

        // Utility functions
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            var noticeHtml = '<div class="woocommerce-' + type + ' momo-notice" role="alert">' +
                message +
                '</div>';
            
            $('.momo-notice').remove();
            $('form.checkout').prepend(noticeHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.momo-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Payment status polling (for future use)
        pollPaymentStatus: function(orderId, requestId) {
            var pollCount = 0;
            var maxPolls = 30; // 5 minutes with 10-second intervals
            
            var poll = setInterval(function() {
                pollCount++;
                
                $.ajax({
                    url: momo_business_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'check_momo_payment_status',
                        order_id: orderId,
                        request_id: requestId,
                        nonce: momo_business_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.status === 'completed') {
                                clearInterval(poll);
                                window.location.href = response.data.redirect_url;
                            } else if (response.data.status === 'failed') {
                                clearInterval(poll);
                                momoCheckout.showNotice('Thanh toán thất bại. Vui lòng thử lại.', 'error');
                                momoCheckout.hideProcessingMessage();
                            }
                        }
                        
                        if (pollCount >= maxPolls) {
                            clearInterval(poll);
                            momoCheckout.showNotice('Timeout: Không thể xác nhận trạng thái thanh toán.', 'error');
                            momoCheckout.hideProcessingMessage();
                        }
                    },
                    error: function() {
                        if (pollCount >= maxPolls) {
                            clearInterval(poll);
                            momoCheckout.hideProcessingMessage();
                        }
                    }
                });
            }, 10000); // Poll every 10 seconds
        }
    };

    // Initialize when document is ready
    momoCheckout.init();

    // Handle WooCommerce checkout updates
    $('body').on('updated_checkout', function() {
        momoCheckout.setupPaymentMethod();
    });

    // Handle AJAX errors
    $(document).ajaxError(function(event, xhr, settings, error) {
        if (settings.url && settings.url.indexOf('momo') !== -1) {
            momoCheckout.hideProcessingMessage();
            momoCheckout.showNotice('Có lỗi xảy ra khi kết nối với MoMo. Vui lòng thử lại.', 'error');
        }
    });

    // Expose for global access
    window.momoCheckout = momoCheckout;

});