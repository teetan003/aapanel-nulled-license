jQuery(document).ready(function($) {
    'use strict';

    // Toggle test/live mode fields
    function toggleModeFields() {
        var testMode = $('#woocommerce_momo_business_testmode').is(':checked');
        
        if (testMode) {
            $('.test-mode-field').closest('tr').show();
            $('.live-mode-field').closest('tr').hide();
        } else {
            $('.test-mode-field').closest('tr').hide();
            $('.live-mode-field').closest('tr').show();
        }
    }

    // Add classes to distinguish test and live fields
    $('#woocommerce_momo_business_test_endpoint').addClass('test-mode-field');
    $('#woocommerce_momo_business_live_endpoint').addClass('live-mode-field');

    // Initialize on page load
    toggleModeFields();

    // Toggle when test mode checkbox changes
    $('#woocommerce_momo_business_testmode').on('change', function() {
        toggleModeFields();
    });

    // Validate form before submission
    $('form').on('submit', function(e) {
        var partnerCode = $('#woocommerce_momo_business_partner_code').val();
        var accessKey = $('#woocommerce_momo_business_access_key').val();
        var secretKey = $('#woocommerce_momo_business_secret_key').val();
        var enabled = $('#woocommerce_momo_business_enabled').is(':checked');

        if (enabled && (!partnerCode || !accessKey || !secretKey)) {
            e.preventDefault();
            alert('Please fill in all required MoMo Business credentials (Partner Code, Access Key, Secret Key) before enabling the payment gateway.');
            return false;
        }
    });

    // Test connection button
    if ($('#momo-test-connection').length === 0) {
        var testButton = '<p><button type="button" id="momo-test-connection" class="button-secondary">Test MoMo Connection</button></p>';
        $(testButton).insertAfter('#woocommerce_momo_business_secret_key').closest('tr');
    }

    $('#momo-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        
        button.text('Testing...').prop('disabled', true);

        var data = {
            action: 'test_momo_connection',
            partner_code: $('#woocommerce_momo_business_partner_code').val(),
            access_key: $('#woocommerce_momo_business_access_key').val(),
            secret_key: $('#woocommerce_momo_business_secret_key').val(),
            testmode: $('#woocommerce_momo_business_testmode').is(':checked'),
            test_endpoint: $('#woocommerce_momo_business_test_endpoint').val(),
            live_endpoint: $('#woocommerce_momo_business_live_endpoint').val(),
            nonce: momo_business_admin.nonce
        };

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                alert('Connection successful! MoMo API is reachable.');
            } else {
                alert('Connection failed: ' + response.data);
            }
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    });

    // Show/hide advanced settings
    if ($('#momo-advanced-toggle').length === 0) {
        var advancedToggle = '<p><a href="#" id="momo-advanced-toggle">Show Advanced Settings</a></p>';
        $(advancedToggle).insertAfter('#woocommerce_momo_business_debug').closest('tr');
    }

    // Hide advanced settings initially
    $('#woocommerce_momo_business_test_endpoint, #woocommerce_momo_business_live_endpoint')
        .closest('tr').hide().addClass('momo-advanced-setting');

    $('#momo-advanced-toggle').on('click', function(e) {
        e.preventDefault();
        
        var link = $(this);
        var advancedSettings = $('.momo-advanced-setting');
        
        if (advancedSettings.is(':visible')) {
            advancedSettings.hide();
            link.text('Show Advanced Settings');
        } else {
            advancedSettings.show();
            link.text('Hide Advanced Settings');
        }
    });

    // Add helpful tooltips
    function addTooltip(selector, message) {
        var element = $(selector);
        if (element.length && !element.attr('title')) {
            element.attr('title', message);
        }
    }

    addTooltip('#woocommerce_momo_business_partner_code', 'Your unique partner code provided by MoMo Business');
    addTooltip('#woocommerce_momo_business_access_key', 'Access key for MoMo Business API authentication');
    addTooltip('#woocommerce_momo_business_secret_key', 'Secret key used for signature generation and verification');
    addTooltip('#woocommerce_momo_business_testmode', 'Use test environment for development and testing');

    // Format currency display
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    // Validation helpers
    function validatePartnerCode(code) {
        return /^[A-Z0-9]{8,20}$/.test(code);
    }

    function validateAccessKey(key) {
        return /^[A-Z0-9]{32,64}$/.test(key);
    }

    // Real-time validation
    $('#woocommerce_momo_business_partner_code').on('blur', function() {
        var value = $(this).val();
        var feedback = $(this).siblings('.validation-feedback');
        
        if (feedback.length === 0) {
            feedback = $('<span class="validation-feedback"></span>');
            $(this).after(feedback);
        }
        
        if (value && !validatePartnerCode(value)) {
            feedback.text('Partner code format appears invalid').css('color', 'red');
        } else {
            feedback.empty();
        }
    });

    $('#woocommerce_momo_business_access_key').on('blur', function() {
        var value = $(this).val();
        var feedback = $(this).siblings('.validation-feedback');
        
        if (feedback.length === 0) {
            feedback = $('<span class="validation-feedback"></span>');
            $(this).after(feedback);
        }
        
        if (value && !validateAccessKey(value)) {
            feedback.text('Access key format appears invalid').css('color', 'red');
        } else {
            feedback.empty();
        }
    });
});