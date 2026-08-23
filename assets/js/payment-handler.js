/**
 * CareToChina Payment Integration & Chat Payment Request Handler
 * Handles Stripe Elements, PayPal Smart Buttons, Payment Requests, and Receipts
 */

(function($) {
    'use strict';

    window.CareToChinaPayment = {
        stripe: null,
        elements: null,

        initStripe: function(publishableKey, clientSecret, containerId, bookingId) {
            var self = this;
            if (typeof Stripe === 'undefined') {
                console.error('Stripe.js not loaded.');
                return;
            }

            self.stripe = Stripe(publishableKey);
            self.elements = self.stripe.elements({ clientSecret: clientSecret });

            var paymentElement = self.elements.create('payment');
            $('#' + containerId).empty();
            paymentElement.mount('#' + containerId);

            $('#ctc-stripe-pay-btn').off('click').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Processing Payment...');

                self.stripe.confirmPayment({
                    elements: self.elements,
                    confirmParams: {
                        return_url: window.location.href + '?ctc_payment_confirm=1&booking_id=' + bookingId,
                    },
                    redirect: 'if_required'
                }).then(function(result) {
                    if (result.error) {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-credit-card"></i> Pay Now');
                        $('#ctc-payment-notice').removeClass('notice-success').addClass('notice-error').html('<p>' + (result.error.message || 'Payment processing failed. Please try again.') + '</p>').show();
                    } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                        $btn.html('<i class="fa-solid fa-check"></i> Payment Succeeded!');
                        $('#ctc-payment-notice').removeClass('notice-error').addClass('notice-success').html('<p>Payment received successfully! Confirming booking status...</p>').show();
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                });
            });
        },

        initPayPal: function(clientId, orderId, containerId, bookingId) {
            var self = this;
            if (typeof paypal === 'undefined') {
                console.error('PayPal SDK not loaded.');
                return;
            }

            $('#' + containerId).empty();
            paypal.Buttons({
                createOrder: function() {
                    return orderId;
                },
                onApprove: function(data, actions) {
                    $('#ctc-payment-notice').removeClass('notice-error').addClass('notice-success').html('<p>PayPal transaction authorized! Confirming case status...</p>').show();
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                },
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    $('#ctc-payment-notice').removeClass('notice-success').addClass('notice-error').html('<p>PayPal checkout encountered an error. Please try again.</p>').show();
                }
            }).render('#' + containerId);
        },

        requestPaymentIntent: function(bookingId, gateway, successCallback, errorCallback) {
            var restNonce = (window.caretochina_obj && window.caretochina_obj.rest_nonce) || (window.wpApiSettings && window.wpApiSettings.nonce) || '';
            var restUrl = (window.caretochina_obj ? window.caretochina_obj.rest_url : '/wp-json/') + 'caretochina/v1/create-payment-intent';

            $.ajax({
                url: restUrl,
                method: 'POST',
                beforeSend: function(xhr) {
                    if (restNonce) {
                        xhr.setRequestHeader('X-WP-Nonce', restNonce);
                    }
                },
                data: JSON.stringify({
                    booking_id: bookingId,
                    gateway: gateway
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                },
                error: function(xhr) {
                    var msg = 'Payment initialization failed. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (typeof errorCallback === 'function') {
                        errorCallback(msg);
                    }
                }
            });
        },

        closePaymentModal: function() {
            var modalId = 'ctc-patient-payment-modal';
            $('#' + modalId).remove();
            if (!window.appDash && !$('.caretochina-dashboard-wrapper').length) {
                var dashUrl = (window.caretochina_obj && window.caretochina_obj.dashboard_url) || '/patient-dashboard/?tab=invoices';
                window.location.href = dashUrl;
            }
        },

        skipPaymentToDashboard: function() {
            var modalId = 'ctc-patient-payment-modal';
            $('#' + modalId).remove();
            
            if (window.appDash && typeof window.appDash.switchTabDirect === 'function') {
                window.appDash.switchTabDirect('invoices');
            } else {
                var dashUrl = (window.caretochina_obj && window.caretochina_obj.dashboard_url) || '/patient-dashboard/?tab=invoices';
                window.location.href = dashUrl;
            }
        },

        openPaymentModal: function(bookingId, amount, currency, title) {
            var self = this;
            var modalId = 'ctc-patient-payment-modal';
            var $modal = $('#' + modalId);

            if ($modal.length) {
                $modal.remove();
            }

            var currCode = (currency || 'USD').toUpperCase();
            var currSym = '$';
            if (currCode === 'CNY' || currCode === 'RMB') currSym = '¥';
            else if (currCode === 'EUR') currSym = '€';
            else if (currCode === 'GBP') currSym = '£';
            else if (currCode === 'BDT') currSym = '৳';
            else if (currCode === 'JPY') currSym = '¥';

            var modalHtml = [
                '<div id="' + modalId + '" class="ctc-payment-modal-overlay" style="display:flex;">',
                '  <div class="ctc-payment-modal-dialog">',
                '    <div class="wiz-auth-modal-header">',
                '      <h3 class="wiz-auth-modal-title"><i class="fa-solid fa-shield-halved" style="color:#0F766E;"></i> Secure Payment</h3>',
                '      <button type="button" class="wiz-auth-modal-close" onclick="CareToChinaPayment.closePaymentModal()"><i class="fa-solid fa-xmark"></i></button>',
                '    </div>',
                '    <div class="ctc-payment-summary-banner">',
                '      <div>',
                '        <span style="font-size:11px; text-transform:uppercase; font-weight:700; letter-spacing:0.5px; opacity:0.85;">Service Total</span>',
                '        <div style="font-size:14px; font-weight:800; color:#0F172A; margin-top:2px;">' + (title || 'Medical Treatment') + '</div>',
                '      </div>',
                '      <div style="font-size:22px; font-weight:900; color:#0F766E; font-family:\'Manrope\', sans-serif;">' + currSym + parseFloat(amount).toFixed(2) + ' <span style="font-size:13px; font-weight:700;">' + currCode + '</span></div>',
                '    </div>',
                '    <div id="ctc-payment-notice" class="wiz-auth-notice" style="display:none;"></div>',
                '    <div class="ctc-pay-method-tabs">',
                '      <button type="button" id="tab-opt-stripe" class="wiz-auth-tab-btn active"><i class="fa-solid fa-credit-card"></i> Credit / Debit Card</button>',
                '      <button type="button" id="tab-opt-paypal" class="wiz-auth-tab-btn"><i class="fa-brands fa-paypal"></i> PayPal</button>',
                '    </div>',
                '    <div id="ctc-stripe-container">',
                '      <div id="ctc-stripe-element-mount" style="min-height:140px; margin-bottom:16px;"></div>',
                '      <button type="button" id="ctc-stripe-pay-btn" class="ctc-pay-submit-btn"><i class="fa-solid fa-lock"></i> Pay ' + currSym + parseFloat(amount).toFixed(2) + ' ' + currCode + '</button>',
                '    </div>',
                '    <div id="ctc-paypal-container" style="display:none; min-height:140px;"></div>',
                '    <div class="wiz-auth-modal-footer" style="margin-top:18px; padding-top:14px; border-top:1px solid #E2E8F0; text-align:center;">',
                '      <button type="button" class="ctc-btn-skip-pay" onclick="CareToChinaPayment.skipPaymentToDashboard()">',
                '        <i class="fa-solid fa-clock-rotate-left"></i> Skip for now & Pay later from Patient Dashboard',
                '      </button>',
                '    </div>',
                '  </div>',
                '</div>'
            ].join('');

            $('body').append(modalHtml);
            $modal = $('#' + modalId);

            // Tab Switchers
            $('#tab-opt-stripe').on('click', function() {
                $('#tab-opt-stripe').addClass('active');
                $('#tab-opt-paypal').removeClass('active');
                $('#ctc-stripe-container').show();
                $('#ctc-paypal-container').hide();
            });

            $('#tab-opt-paypal').on('click', function() {
                $('#tab-opt-paypal').addClass('active');
                $('#tab-opt-stripe').removeClass('active');
                $('#ctc-stripe-container').hide();
                $('#ctc-paypal-container').show();

                self.requestPaymentIntent(bookingId, 'paypal', function(res) {
                    if (res && res.order_id && res.client_id) {
                        self.initPayPal(res.client_id, res.order_id, 'ctc-paypal-container', bookingId);
                    }
                });
            });

            // Initialize Stripe Intent
            self.requestPaymentIntent(bookingId, 'stripe', function(res) {
                if (res && res.client_secret && res.publishable_key) {
                    self.initStripe(res.publishable_key, res.client_secret, 'ctc-stripe-element-mount', bookingId);
                }
            }, function(errMsg) {
                $('#ctc-payment-notice').removeClass('notice-success').addClass('notice-error').html('<p style="margin:0;"><i class="fa-solid fa-circle-exclamation"></i> ' + errMsg + '</p>').show();
            });
        }
    };

    /**
     * Patient accepts a payment request in chat
     */
    window.ctcAcceptPaymentRequest = function(requestId) {
        var ajaxUrl = (window.caretochina_obj && window.caretochina_obj.ajax_url) || 
                      (window.careyou_obj && window.careyou_obj.ajax_url) || 
                      (window.caretochina_staff_obj && window.caretochina_staff_obj.ajax_url) || 
                      (window.ajaxurl) || 
                      '/wp-admin/admin-ajax.php';

        var nonce = (window.caretochina_obj && (window.caretochina_obj.patient_nonce || window.caretochina_obj.nonce || window.caretochina_obj.booking_nonce)) ||
                    (window.careyou_obj && (window.careyou_obj.patient_nonce || window.careyou_obj.nonce || window.careyou_obj.booking_nonce)) ||
                    (window.caretochina_staff_obj && window.caretochina_staff_obj.nonce) ||
                    (window.careyou_staff_obj && window.careyou_staff_obj.nonce) || '';

        var $card = $('[data-request-id="' + requestId + '"]');
        var $btn = $card.find('.ctc-btn-accept-pay');
        if ($btn.length) {
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Initializing...');
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'ctc_accept_payment_request',
                request_id: requestId,
                nonce: nonce
            },
            success: function(res) {
                if (res.success && res.data) {
                    if (window.appDash && typeof window.appDash.switchTabDirect === 'function') {
                        window.appDash.switchTabDirect('invoices');
                    }
                    CareToChinaPayment.openPaymentModal(res.data.booking_id, res.data.amount, res.data.currency, res.data.title);
                } else {
                    alert((res.data && res.data.message) || 'Failed to accept payment request. Please refresh and try again.');
                }
                if ($btn.length) {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-lock"></i> Accept & Pay Online');
                }
            },
            error: function() {
                alert('Network error communicating with server. Please try again.');
                if ($btn.length) {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-lock"></i> Accept & Pay Online');
                }
            }
        });
    };

    /**
     * Staff cancels a pending payment request
     */
    window.ctcStaffCancelPaymentRequest = function(requestId) {
        if (!confirm('Are you sure you want to withdraw/cancel this payment request?')) {
            return;
        }

        var ajaxUrl = (window.caretochina_staff_obj && window.caretochina_staff_obj.ajax_url) || 
                      (window.careyou_staff_obj && window.careyou_staff_obj.ajax_url) || 
                      (window.caretochina_obj && window.caretochina_obj.ajax_url) || 
                      (window.ajaxurl) || 
                      '/wp-admin/admin-ajax.php';

        var nonce = (window.caretochina_staff_obj && window.caretochina_staff_obj.nonce) || 
                    (window.careyou_staff_obj && window.careyou_staff_obj.nonce) || 
                    (window.caretochina_obj && (window.caretochina_obj.staff_nonce || window.caretochina_obj.nonce)) || '';

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'ctc_cancel_payment_request',
                request_id: requestId,
                nonce: nonce
            },
            success: function(res) {
                if (res.success) {
                    if (window.appStaff && typeof window.appStaff.loadChatHistory === 'function') {
                        var activeId = $('#staff_chat_booking_id').val();
                        window.appStaff.loadChatHistory(activeId);
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert((res.data && res.data.message) || 'Failed to cancel payment request.');
                }
            },
            error: function() {
                alert('Network error communicating with server.');
            }
        });
    };

    /**
     * Open Staff Payment Request Modal (Registered Patients Only)
     */
    window.ctcOpenStaffPaymentReqModal = function() {
        var $activeItem = $('.staff-chat-patient-item.active');
        var patientId = parseInt($activeItem.attr('data-patient-id') || $activeItem.data('patient-id') || 0, 10);
        var isGuest = ($activeItem.attr('data-is-guest') == '1' || $activeItem.data('is-guest') == 1);
        
        if (patientId <= 0 && isGuest) {
            alert('Payment requests can only be sent to registered patient accounts. Guest users cannot receive payment requests. Please request the guest patient to save/register their account first.');
            return;
        }

        var activeId = $('#staff_chat_booking_id').val();
        $('#req_modal_booking_id').val(activeId);
        $('#staff-payment-request-modal').css('display', 'flex');
    };

    /**
     * Switch Pricing Source Type in Staff Modal
     */
    window.ctcSwitchPricingType = function(type) {
        $('.pricing-sec-box').hide();
        $('#pricing-sec-' + type).show();
        $('.ctc-pricing-opt-label').removeClass('active').css('border-color', '#CBD5E1');
        $('input[name="pricing_type"][value="' + type + '"]').closest('.ctc-pricing-opt-label').addClass('active').css('border-color', '#0F766E');
    };

    /**
     * Treatment Select change listener to dynamically load pricing plans & auto-populate price
     */
    $(document).on('change', '#req_treatment_select', function() {
        var treatmentId = $(this).val();
        var $planSelect = $('#req_plan_select');
        var $amountInput = $('#req_plan_amount');
        var $nameInput = $('#req_plan_name_input');
        var ajaxUrl = (window.caretochina_staff_obj && window.caretochina_staff_obj.ajax_url) || (window.careyou_staff_obj && window.careyou_staff_obj.ajax_url) || (window.caretochina_obj && window.caretochina_obj.ajax_url) || (window.ajaxurl) || '/wp-admin/admin-ajax.php';

        if (!treatmentId || treatmentId == '0') {
            $planSelect.empty().append('<option value="0">-- Select Specialty First --</option>');
            $amountInput.val('');
            return;
        }

        $planSelect.empty().append('<option value="0">Loading packages...</option>');

        $.get(ajaxUrl, {
            action: 'ctc_get_treatment_plans',
            treatment_id: treatmentId
        }, function(res) {
            $planSelect.empty();
            if (res.success && res.data && res.data.plans && res.data.plans.length > 0) {
                $planSelect.append('<option value="0">-- Select a Pricing Package --</option>');
                res.data.plans.forEach(function(p, idx) {
                    $planSelect.append('<option value="' + p.id + '" data-price="' + p.price + '" data-name="' + p.name + '">' + p.name + ' ($' + parseFloat(p.price).toFixed(2) + ' ' + (p.currency || 'USD') + ')</option>');
                });
                // Auto select first plan
                $planSelect.val(res.data.plans[0].id).trigger('change');
            } else {
                $planSelect.append('<option value="0">Standard Consultation Deposit ($500.00)</option>');
                $amountInput.val('500.00');
            }
        });
    });

    $(document).on('change', '#req_plan_select', function() {
        var $selected = $(this).find(':selected');
        var price = $selected.data('price');
        var name = $selected.data('name');
        if (price) {
            $('#req_plan_amount').val(parseFloat(price).toFixed(2));
        }
        if (name && !$('#req_plan_name_input').val()) {
            $('#req_plan_name_input').val(name);
        }
    });

    /**
     * Staff Submit Payment Request Form
     */
    $(document).on('submit', '#staff-send-payment-request-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $('#btn-submit-payment-req');
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Sending...');

        var ajaxUrl = (window.caretochina_staff_obj && window.caretochina_staff_obj.ajax_url) || (window.careyou_staff_obj && window.careyou_staff_obj.ajax_url) || (window.caretochina_obj && window.caretochina_obj.ajax_url) || (window.ajaxurl) || '/wp-admin/admin-ajax.php';
        var nonce = (window.caretochina_staff_obj && window.caretochina_staff_obj.nonce) || (window.careyou_staff_obj && window.careyou_staff_obj.nonce) || (window.caretochina_obj && window.caretochina_obj.staff_nonce) || (window.caretochina_obj && window.caretochina_obj.nonce) || '';

        var pricingType = $('input[name="pricing_type"]:checked').val();
        var postData = {
            action: 'ctc_create_payment_request',
            nonce: nonce,
            booking_id: $('#req_modal_booking_id').val(),
            pricing_type: pricingType
        };

        if (pricingType === 'treatment_plan') {
            postData.treatment_id = $('#req_treatment_select').val();
            postData.pricing_plan_id = $('#req_plan_select').val();
            postData.plan_name = $('#req_plan_name_input').val() || $('#req_plan_select option:selected').data('name') || '';
            postData.custom_amount = $('#req_plan_amount').val();
        } else if (pricingType === 'custom_amount') {
            postData.custom_title = $('input[name="custom_amount_title"]').val();
            postData.custom_amount = $('input[name="custom_fee_amount"]').val();
        } else if (pricingType === 'custom_treatment') {
            postData.custom_title = $('input[name="custom_treatment_title"]').val();
            postData.custom_content = $('textarea[name="custom_treatment_content"]').val();
            postData.custom_amount = $('input[name="custom_treatment_amount"]').val();
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: postData,
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Send Request to Patient');
                if (res.success) {
                    $('#staff-payment-request-modal').hide();
                    $form[0].reset();
                    if (window.appStaff && typeof window.appStaff.fetchStaffChat === 'function') {
                        window.appStaff.fetchStaffChat();
                    }
                    if (window.appStaff && typeof window.appStaff.pollUpdates === 'function') {
                        window.appStaff.pollUpdates();
                    }
                } else {
                    alert((res.data && res.data.message) || 'Failed to send payment request.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Send Request to Patient');
                alert('Server error sending payment request.');
            }
        });
    });

    /**
     * Patient Printable Receipt Modal Opener
     */
    window.ctcOpenReceiptModal = function(data) {
        if (!data) return;
        $('#rcpt-code').text('#' + (data.code || ''));
        $('#rcpt-date').text(data.date || '—');
        $('#rcpt-name').text(data.name || '—');
        $('#rcpt-gateway').text(data.gateway || '—');
        $('#rcpt-service').text(data.specialty || '—');
        $('#rcpt-hospital').text(data.hospital ? 'Facility: ' + data.hospital : '');
        $('#rcpt-amount').text('$' + (data.amount || '0.00') + ' ' + (data.currency || 'USD'));
        $('#patient-receipt-modal').css('display', 'flex');
    };

})(jQuery);
