/* ==========================================================================
   CARETOCHINA MEDICAL BOOKING INTERACTIVE JAVASCRIPT ENGINE
   ========================================================================== */

function getBookingObj() {
  return (typeof caretochina_obj !== 'undefined') ? caretochina_obj : ((typeof careyou_obj !== 'undefined') ? careyou_obj : { hospitals: [], all_specialties: [], all_cities: [], ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });
}

window.appWizard = {
  currentStep: 1,
  selectedHospitalId: 0,
  selectedHospitalName: '',
  selectedTiming: '',
  selectedSpecialties: [],
  selectedTreatmentId: 0,
  selectedPricingPlanId: 0,
  selectedPricingPlanName: '',
  selectedPricingPlanPrice: 0.00,
  selectedPricingPlanCurrency: 'USD',

  openScenario1() {
    this.selectedHospitalId = 0;
    this.selectedHospitalName = '';
    this.selectedTiming = '';
    this.selectedSpecialties = [];
    this.selectedTreatmentId = 0;
    this.selectedPricingPlanId = 0;
    this.selectedPricingPlanName = '';
    this.selectedPricingPlanPrice = 0.00;
    
    // Clear selections
    jQuery('#wiz_hospital_id').val(0);
    jQuery('#wiz_hospital_name').val('');
    jQuery('#wiz_treatment_timing').val('');
    jQuery('#wiz_selected_treatment_id').val(0);
    jQuery('#wiz_pricing_plan_id').val(0);
    jQuery('#wiz_pricing_plan_name').val('');
    jQuery('#wiz_pricing_plan_price').val(0);
    jQuery('.timing-tag-btn').removeClass('active');
    
    // Reset steps
    this.currentStep = 1;
    this.renderHospitals();
    this.showStep(1);
    
    // Show back button on step 2
    jQuery('#wiz-back-btn-step-2').show();
    
    // Reset form and steps indicator visibility
    jQuery('#ctc-booking-wizard-form').show();
    jQuery('.wizard-steps-indicator').show();
    jQuery('#ctc-wizard-status').hide().empty();
    
    jQuery('html, body').addClass('ctc-modal-open');
    jQuery('#ctc-booking-modal').addClass('show');
  },

  openScenario2(hospitalData) {
    this.selectedHospitalId = hospitalData.id;
    this.selectedHospitalName = hospitalData.name;
    this.selectedTiming = '';
    this.selectedSpecialties = [];
    this.selectedTreatmentId = 0;
    this.selectedPricingPlanId = 0;
    this.selectedPricingPlanName = '';
    this.selectedPricingPlanPrice = 0.00;

    // Set selections
    jQuery('#wiz_hospital_id').val(hospitalData.id);
    jQuery('#wiz_hospital_name').val(hospitalData.name);
    jQuery('#wiz_treatment_timing').val('');
    jQuery('#wiz_selected_treatment_id').val(0);
    jQuery('#wiz_pricing_plan_id').val(0);
    jQuery('#wiz_pricing_plan_name').val('');
    jQuery('#wiz_pricing_plan_price').val(0);
    jQuery('.timing-tag-btn').removeClass('active');
    
    // Reset steps and skip step 1
    this.currentStep = 2;
    this.renderSpecialtyCheckboxes(hospitalData.specialties);
    this.showStep(2);
    
    // Hide back button on step 2 since hospital is fixed
    jQuery('#wiz-back-btn-step-2').hide();

    // Reset form and steps indicator visibility
    jQuery('#ctc-booking-wizard-form').show();
    jQuery('.wizard-steps-indicator').show();
    jQuery('#ctc-wizard-status').hide().empty();

    jQuery('html, body').addClass('ctc-modal-open');
    jQuery('#ctc-booking-modal').addClass('show');
  },

  closeModal() {
    jQuery('html, body').removeClass('ctc-modal-open');
    jQuery('#ctc-booking-modal').removeClass('show');
  },

  showStep(stepNum) {
    this.currentStep = stepNum;
    jQuery('.wiz-page').hide();
    jQuery('#wiz-step-' + stepNum).fadeIn(250);

    jQuery('.wiz-step').removeClass('active');
    jQuery('.wiz-step').each(function() {
      const step = parseInt(jQuery(this).data('step'));
      if (step <= stepNum) {
        jQuery(this).addClass('active');
      }
    });
  },

  nextStep(stepNum) {
    var self = this;
    var bookingObj = getBookingObj();

    // STEP 1 -> 2 VALIDATION
    if (stepNum === 2 && this.currentStep === 1) {
      if (this.selectedHospitalId === 0) {
        alert(bookingObj.hospitals.length ? 'Please select a hospital, or click "Skip Hospital" to proceed.' : 'No hospitals available. Please skip.');
        return;
      }
      const hosp = bookingObj.hospitals.find(h => h.id === this.selectedHospitalId);
      this.renderSpecialtyCheckboxes(hosp ? hosp.specialties : []);
    }

    // STEP 2 -> 3 VALIDATION (Timing & Specialty -> Pricing Plans)
    if (stepNum === 3 && this.currentStep === 2) {
      this.selectedTiming = jQuery('#wiz_treatment_timing').val();
      if (!this.selectedTiming) {
        alert('Please select treatment timing.');
        return;
      }

      this.selectedSpecialties = [];
      this.selectedTreatmentId = 0;
      jQuery('.specialty-checkbox:checked').each((index, el) => {
        self.selectedSpecialties.push(jQuery(el).val());
        var termId = parseInt(jQuery(el).data('term-id') || 0);
        if (termId > 0 && !self.selectedTreatmentId) {
          self.selectedTreatmentId = termId;
        }
      });

      if (this.selectedSpecialties.length === 0) {
        alert('Please select at least one specialty / treatment.');
        return;
      }

      jQuery('#wiz_selected_treatment_id').val(this.selectedTreatmentId);
      this.loadPricingPlansForSelectedSpecialty();
    }

    // STEP 3 -> 4 VALIDATION (Pricing Plan Selection)
    if (stepNum === 4 && this.currentStep === 3) {
      var planId = parseInt(jQuery('#wiz_pricing_plan_id').val() || 0);
      var plansCount = jQuery('.pricing-plan-card').length;
      if (plansCount > 0 && planId === 0) {
        alert('Please select a pricing plan tier to proceed.');
        return;
      }
    }

    // STEP 4 -> 5 VALIDATION (Patient Details -> Review & Submit)
    if (stepNum === 5 && this.currentStep === 4) {
      if (!jQuery('#wiz_quote_details').val()) {
        alert('Please describe your condition or request.');
        return;
      }
      if (!jQuery('#wiz_full_name').val()) {
        alert('Please enter your full name.');
        return;
      }
      if (!jQuery('#wiz_country').val()) {
        alert('Please enter your country.');
        return;
      }
      if (!jQuery('#wiz_gender').val()) {
        alert('Please select your gender.');
        return;
      }

      // Populate summary
      var planName = jQuery('#wiz_pricing_plan_name').val() || 'Standard Consultation Package';
      var planPrice = parseFloat(jQuery('#wiz_pricing_plan_price').val() || 500.00).toFixed(2);
      var currency = self.selectedPricingPlanCurrency || 'USD';

      jQuery('#wiz-sum-hospital').text(this.selectedHospitalName || 'Skipped (General Request)');
      jQuery('#wiz-sum-specialties').text(this.selectedSpecialties.join(', '));
      jQuery('#wiz-sum-plan').text(planName);
      jQuery('#wiz-sum-cost').text('$' + planPrice + ' ' + currency);
      jQuery('#wiz-sum-timing').text(this.selectedTiming);
      jQuery('#wiz-sum-patient').text(jQuery('#wiz_full_name').val());
    }

    this.showStep(stepNum);
  },

  skipHospital() {
    this.selectedHospitalId = 0;
    this.selectedHospitalName = 'Skipped (General Enquiry)';
    jQuery('#wiz_hospital_id').val(0);
    jQuery('#wiz_hospital_name').val('');
    jQuery('.hospital-select-card').removeClass('selected');
    
    // Load all specialties
    this.renderSpecialtyCheckboxes(getBookingObj().all_specialties);
    this.showStep(2);
  },

  selectHospitalCard(element, id, name) {
    jQuery('.hospital-select-card').removeClass('selected');
    jQuery(element).addClass('selected');
    this.selectedHospitalId = id;
    this.selectedHospitalName = name;
    jQuery('#wiz_hospital_id').val(id);
    jQuery('#wiz_hospital_name').val(name);
  },

  selectTiming(btn) {
    jQuery('.timing-tag-btn').removeClass('active');
    jQuery(btn).addClass('active');
    jQuery('#wiz_treatment_timing').val(jQuery(btn).data('value'));
  },

  renderHospitals() {
    const listGrid = jQuery('#wiz-hospital-list-grid');
    if (!listGrid.length) return;

    listGrid.empty();
    const searchVal = jQuery('#wiz-hospital-search').val().toLowerCase();
    const cityVal = jQuery('#wiz-hospital-city-filter').val();
    const bookingObj = getBookingObj();

    bookingObj.hospitals.forEach(h => {
      if (searchVal && h.title.toLowerCase().indexOf(searchVal) === -1) return;
      if (cityVal && !h.cities.some(c => c.id == cityVal)) return;

      const card = jQuery(`
        <div class="hospital-select-card" onclick="appWizard.selectHospitalCard(this, ${h.id}, '${h.title.replace(/'/g, "\\'")}')" style="border:1px solid #E2E8F0; padding:12px; border-radius:12px; cursor:pointer; background:#FFF; transition:all 0.2s; display:flex; gap:12px; align-items:center;">
          <img src="${h.image}" style="width:70px; height:50px; border-radius:6px; object-fit:cover;">
          <div style="flex:1; min-width:0;">
            <h4 style="margin:0 0 2px 0; font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#0F172A;">${h.title}</h4>
            <div style="font-size:11px; color:#64748B; display:flex; align-items:center; gap:4px;">
              <i class="fa-solid fa-map-marker-alt"></i> ${h.cities.map(c => c.name).join(', ')}
            </div>
            <div style="font-size:10px; color:#14B8A6; font-weight:600; margin-top:2px;">
              ${h.certification} • ${h.rating}
            </div>
          </div>
        </div>
      `);

      if (h.id === this.selectedHospitalId) {
        card.addClass('selected');
      }

      listGrid.append(card);
    });

    if (listGrid.children().length === 0) {
      listGrid.append('<div style="grid-column: span 2; text-align:center; padding:20px; color:#64748B; font-size:13px;">No hospitals matching criteria.</div>');
    }
  },

  renderSpecialtyCheckboxes(specialties) {
    const checkList = jQuery('#wiz-specialty-checkbox-list');
    if (!checkList.length) return;

    checkList.empty();
    
    if (!specialties || specialties.length === 0) {
      specialties = getBookingObj().all_specialties;
    }

    specialties.forEach(s => {
      const checkbox = jQuery(`
        <label class="specialty-check-item" style="display:flex; align-items:center; gap:8px; background:#F8FAFC; border:1px solid #E2E8F0; padding:10px 14px; border-radius:10px; cursor:pointer; font-size:13px; transition:all 0.2s;">
          <input type="checkbox" name="specialty[]" value="${s.name}" data-term-id="${s.id}" class="specialty-checkbox" style="accent-color:#0F766E;">
          <span style="color:#0F172A; font-weight:500;">${s.name}</span>
        </label>
      `);
      checkList.append(checkbox);
    });
  },

  /**
   * Load active Pricing Plans for selected Specialty / Treatment via AJAX
   */
  loadPricingPlansForSelectedSpecialty() {
    var self = this;
    var grid = jQuery('#wiz-pricing-plans-grid');
    var emptyBox = jQuery('#wiz-pricing-plans-empty');
    var apiObj = getBookingObj();

    grid.empty().html('<div style="text-align:center; padding:20px; color:#0F766E;"><i class="fa-solid fa-spinner fa-spin"></i> Loading pricing packages...</div>');
    emptyBox.hide();

    jQuery.get(apiObj.ajax_url, {
      action: 'ctc_get_treatment_plans',
      treatment_id: self.selectedTreatmentId
    }, function(res) {
      grid.empty();
      if (res.success && res.data && res.data.plans && res.data.plans.length > 0) {
        self.selectedPricingPlanCurrency = res.data.currency || 'USD';

        res.data.plans.forEach(function(plan, idx) {
          var isChecked = (self.selectedPricingPlanId == plan.id) || (self.selectedPricingPlanId === 0 && idx === 0);
          if (isChecked) {
            self.selectedPricingPlanId = plan.id;
            self.selectedPricingPlanName = plan.name;
            self.selectedPricingPlanPrice = plan.price;
            jQuery('#wiz_pricing_plan_id').val(plan.id);
            jQuery('#wiz_pricing_plan_name').val(plan.name);
            jQuery('#wiz_pricing_plan_price').val(plan.price);
          }

          var cardHtml = `
            <div class="pricing-plan-card ${isChecked ? 'selected' : ''}" onclick="appWizard.selectPricingPlanCard(this, ${plan.id}, ${plan.price}, '${plan.name.replace(/'/g, "\\'")}', '${plan.currency}')" style="border:1.5px solid ${isChecked ? '#0F766E' : '#E2E8F0'}; background:${isChecked ? '#F0FDFA' : '#FFF'}; padding:16px; border-radius:12px; cursor:pointer; transition:all 0.2s; display:flex; justify-content:space-between; align-items:center;">
              <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:8px;">
                  <input type="radio" name="plan_radio" value="${plan.id}" ${isChecked ? 'checked' : ''} style="accent-color:#0F766E;">
                  <h4 style="margin:0; font-size:15px; font-weight:800; color:#0F172A;">${plan.name}</h4>
                </div>
                ${plan.description ? `<p style="margin:4px 0 0 24px; font-size:12px; color:#64748B;">${plan.description}</p>` : ''}
              </div>
              <div style="text-align:right;">
                <div style="font-size:18px; font-weight:900; color:#0F766E;">$${parseFloat(plan.price).toFixed(2)}</div>
                <div style="font-size:11px; font-weight:700; color:#94A3B8;">${plan.currency}</div>
              </div>
            </div>
          `;
          grid.append(cardHtml);
        });
      } else {
        emptyBox.show();
        self.selectedPricingPlanId = 0;
        self.selectedPricingPlanName = 'Standard Deposit Consultation';
        self.selectedPricingPlanPrice = 500.00;
        jQuery('#wiz_pricing_plan_id').val(0);
        jQuery('#wiz_pricing_plan_name').val('Standard Deposit Consultation');
        jQuery('#wiz_pricing_plan_price').val(500.00);
      }
    }).fail(function() {
      grid.empty();
      emptyBox.show();
    });
  },

  selectPricingPlanCard(element, planId, price, name, currency) {
    jQuery('.pricing-plan-card').removeClass('selected').css({ 'border-color': '#E2E8F0', 'background': '#FFF' });
    jQuery(element).addClass('selected').css({ 'border-color': '#0F766E', 'background': '#F0FDFA' });
    jQuery(element).find('input[type="radio"]').prop('checked', true);

    this.selectedPricingPlanId = planId;
    this.selectedPricingPlanName = name;
    this.selectedPricingPlanPrice = price;
    this.selectedPricingPlanCurrency = currency || 'USD';

    jQuery('#wiz_pricing_plan_id').val(planId);
    jQuery('#wiz_pricing_plan_name').val(name);
    jQuery('#wiz_pricing_plan_price').val(price);
  },

  switchAuthModalTab(tab) {
    if (tab === 'login') {
      jQuery('#wiz-auth-tab-login').css({ 'background': '#0F766E', 'color': '#FFF' });
      jQuery('#wiz-auth-tab-reg').css({ 'background': '#F1F5F9', 'color': '#475569' });
      jQuery('#wiz-ajax-login-form').show();
      jQuery('#wiz-ajax-reg-form').hide();
    } else {
      jQuery('#wiz-auth-tab-reg').css({ 'background': '#0F766E', 'color': '#FFF' });
      jQuery('#wiz-auth-tab-login').css({ 'background': '#F1F5F9', 'color': '#475569' });
      jQuery('#wiz-ajax-reg-form').show();
      jQuery('#wiz-ajax-login-form').hide();
    }
  }
};

window.appDash = {
  switchTab(button, tabId) {
    jQuery('.ctc-sidebar-tab, .dash-tab').removeClass('active');
    jQuery(button).addClass('active');

    jQuery('.ctc-dash-panel, .dash-panel').removeClass('active').hide();
    jQuery('#dash-panel-' + tabId).addClass('active').show();
  },
  switchTabDirect(tabId) {
    jQuery('.ctc-sidebar-tab, .dash-tab').removeClass('active');
    jQuery('.ctc-sidebar-tab:nth-child(5), .dash-tab:nth-child(5)').addClass('active');

    jQuery('.ctc-dash-panel, .dash-panel').removeClass('active').hide();
    jQuery('#dash-panel-' + tabId).addClass('active').show();
  },
  toggleSidebar() {
    const sidebar = jQuery('.ctc-dash-sidebar');
    const container = jQuery('.ctc-dash-grid');
    const btnIcon = jQuery('.ctc-sidebar-toggle-btn i');
    
    sidebar.toggleClass('collapsed');
    container.toggleClass('sidebar-collapsed');
    
    if (sidebar.hasClass('collapsed')) {
      btnIcon.removeClass('fa-angles-left').addClass('fa-angles-right');
    } else {
      btnIcon.removeClass('fa-angles-right').addClass('fa-angles-left');
    }
  }
};

jQuery(document).ready(function($) {
  var apiObj = (typeof caretochina_obj !== 'undefined') ? caretochina_obj : ((typeof careyou_obj !== 'undefined') ? careyou_obj : { ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });

  function isUserLoggedIn() {
    return $('body').hasClass('logged-in');
  }

  // Append booking modal to body
  if ($('#ctc-booking-modal').length) {
    $('body').append($('#ctc-booking-modal'));
  }
  if ($('#wiz-auth-gate-modal').length) {
    $('body').append($('#wiz-auth-gate-modal'));
  }

  // Render Cities filter in wizard
  if ($('#wiz-hospital-city-filter').length && apiObj.all_cities) {
    const filter = $('#wiz-hospital-city-filter');
    apiObj.all_cities.forEach(c => {
      filter.append(`<option value="${c.id}">${c.name}</option>`);
    });
  }

  // Search & filter listeners
  $(document).on('keyup input', '#wiz-hospital-search', function() {
    appWizard.renderHospitals();
  });
  $(document).on('keydown', '#wiz-hospital-search', function(e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
      e.preventDefault();
      $(this).blur();
    }
  });
  $(document).on('change', '#wiz-hospital-city-filter', function() {
    appWizard.renderHospitals();
  });

  // Modal open triggers
  $(document).on('click', 'a[href="#booking"], .ctc-trigger-booking, [id="booking"], .ctc-quote-btn, .ctc-sidebar-quote-btn', function(e) {
    e.preventDefault();
    if (typeof apiObj.current_hospital !== 'undefined' && apiObj.current_hospital) {
      appWizard.openScenario2(apiObj.current_hospital);
    } else {
      appWizard.openScenario1();
    }
  });

  // Close modals on backdrop or Escape
  $(document).on('click', '#ctc-booking-modal', function(e) {
    if ($(e.target).is('#ctc-booking-modal')) {
      appWizard.closeModal();
    }
  });
  $(document).on('keyup', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      appWizard.closeModal();
      $('#wiz-auth-gate-modal').hide();
    }
  });

  // RESTORE DRAFT STATE IF LOGGED IN (e.g. Returned from Google OAuth)
  var savedDraft = sessionStorage.getItem('ctc_wizard_draft');
  if (savedDraft && isUserLoggedIn()) {
    try {
      var draftData = JSON.parse(savedDraft);
      sessionStorage.removeItem('ctc_wizard_draft');
      // Submit booking automatically for authenticated user
      submitBookingForm(draftData);
    } catch (err) {
      sessionStorage.removeItem('ctc_wizard_draft');
    }
  }

  // WIZARD FORM SUBMISSION HANDLER
  $('#ctc-booking-wizard-form').on('submit', function(e) {
    e.preventDefault();
    var formSerialized = $(this).serialize();

    // Check if user is logged in
    if (!isUserLoggedIn()) {
      // Save draft state to sessionStorage
      sessionStorage.setItem('ctc_wizard_draft', JSON.stringify(formSerialized));
      // Pre-fill email/name into auth forms if provided
      var emailVal = $('#wiz_email').val();
      var nameVal = $('#wiz_full_name').val();
      if (emailVal) {
        $('#wiz_auth_log_email, #wiz_auth_reg_email').val(emailVal);
      }
      if (nameVal) {
        $('#wiz_auth_reg_name').val(nameVal);
      }
      // Open Auth Gate Modal
      $('#wiz-auth-gate-modal').css('display', 'flex');
      return;
    }

    submitBookingForm(formSerialized);
  });

  function submitBookingForm(serializedData) {
    var btn = $('#ctc-wizard-submit-btn');
    var status = $('#ctc-wizard-status');

    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Processing Booking...');
    var postData = (typeof serializedData === 'string' ? serializedData : $.param(serializedData)) + '&action=caretochina_submit_booking&nonce=' + apiObj.nonce;

    $.post(apiObj.ajax_url, postData, function(res) {
      status.show();
      if (res.success && res.data) {
        sessionStorage.removeItem('ctc_wizard_draft');
        status.html(`<div style="background:#D1FAE5; color:#065F46; padding:16px; border-radius:12px; font-weight:700; font-size:14px;"><i class="fa-solid fa-circle-check"></i> ${res.data.message}</div>`);
        btn.html('<i class="fa-solid fa-check"></i> Confirmed');

        setTimeout(function() {
          appWizard.closeModal();
          $('#ctc-booking-wizard-form')[0].reset();
          btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Confirm & Proceed to Payment');
          status.hide().empty();

          // Trigger Payment Modal directly with snapshotted plan amount!
          if (window.CareToChinaPayment && typeof window.CareToChinaPayment.openPaymentModal === 'function') {
            CareToChinaPayment.openPaymentModal(res.data.booking_id, res.data.amount, res.data.currency, res.data.specialty);
          } else {
            window.location.reload();
          }
        }, 1200);
      } else {
        status.html(`<div style="color:#EF4444; font-weight:700; font-size:14px;"><i class="fa-solid fa-triangle-exclamation"></i> ${(res.data && res.data.message) || 'Failed to submit booking.'}</div>`);
        btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Confirm & Proceed to Payment');
      }
    }).fail(function() {
      btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Confirm & Proceed to Payment');
      alert('Network error submitting booking.');
    });
  }

  // AJAX LOGIN IN WIZARD AUTH GATE MODAL
  $('#wiz-ajax-login-form').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#btn-wiz-ajax-login');
    var $notice = $('#wiz-auth-modal-notice');
    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...');
    $notice.hide().empty();

    $.post(apiObj.ajax_url, {
      action: 'caretochina_user_login',
      nonce: apiObj.nonce,
      log: $('#wiz_auth_log_email').val(),
      pwd: $('#wiz_auth_log_pass').val()
    }, function(res) {
      if (res.success) {
        $notice.removeClass('notice-error').css({ 'background': '#D1FAE5', 'color': '#065F46' }).html('<i class="fa-solid fa-check"></i> Authenticated! Resuming booking...').show();
        $('body').addClass('logged-in');
        $('#wiz-auth-gate-modal').hide();
        var saved = sessionStorage.getItem('ctc_wizard_draft');
        submitBookingForm(saved ? JSON.parse(saved) : $('#ctc-booking-wizard-form').serialize());
      } else {
        $btn.prop('disabled', false).html('<i class="fa-solid fa-right-to-bracket"></i> Sign In & Complete Booking');
        $notice.css({ 'background': '#FEE2E2', 'color': '#991B1B' }).html('<i class="fa-solid fa-triangle-exclamation"></i> ' + ((res.data && res.data.message) || 'Login failed.')).show();
      }
    }).fail(function() {
      $btn.prop('disabled', false).html('<i class="fa-solid fa-right-to-bracket"></i> Sign In & Complete Booking');
      $notice.css({ 'background': '#FEE2E2', 'color': '#991B1B' }).html('<i class="fa-solid fa-triangle-exclamation"></i> Server communication error.').show();
    });
  });

  // AJAX REGISTER IN WIZARD AUTH GATE MODAL
  $('#wiz-ajax-reg-form').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#btn-wiz-ajax-reg');
    var $notice = $('#wiz-auth-modal-notice');
    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Registering...');
    $notice.hide().empty();

    var postData = {
      action: 'caretochina_user_register',
      nonce: apiObj.nonce,
      user_name: $('#wiz_auth_reg_name').val(),
      user_email: $('#wiz_auth_reg_email').val(),
      user_pass: $('#wiz_auth_reg_pass').val(),
      user_pass_confirm: $('#wiz_auth_reg_pass').val(),
      user_phone: $('#wiz_phone').val() || '+1000000000',
      user_gender: $('#wiz_gender').val() || 'Not Specified'
    };

    $.post(apiObj.ajax_url, postData, function(res) {
      if (res.success) {
        $notice.css({ 'background': '#D1FAE5', 'color': '#065F46' }).html('<i class="fa-solid fa-check"></i> Account created! Submitting booking...').show();
        $('body').addClass('logged-in');
        $('#wiz-auth-gate-modal').hide();
        var saved = sessionStorage.getItem('ctc_wizard_draft');
        submitBookingForm(saved ? JSON.parse(saved) : $('#ctc-booking-wizard-form').serialize());
      } else {
        $btn.prop('disabled', false).html('<i class="fa-solid fa-user-plus"></i> Register & Complete Booking');
        $notice.css({ 'background': '#FEE2E2', 'color': '#991B1B' }).html('<i class="fa-solid fa-triangle-exclamation"></i> ' + ((res.data && res.data.message) || 'Registration failed.')).show();
      }
    }).fail(function() {
      $btn.prop('disabled', false).html('<i class="fa-solid fa-user-plus"></i> Register & Complete Booking');
      $notice.css({ 'background': '#FEE2E2', 'color': '#991B1B' }).html('<i class="fa-solid fa-triangle-exclamation"></i> Server communication error.').show();
    });
  });

  // REAL-TIME CHAT POLLING FOR PATIENT DASHBOARD
  var patientBookingId = $('.caretochina-dashboard-wrapper, .careyou-dashboard-wrapper').data('booking-id') || 1;
  var userIsScrolledUp = false;

  $('#patient-chat-box').on('scroll', function() {
    var elem = $(this);
    if (elem.scrollTop() + elem.innerHeight() < elem[0].scrollHeight - 50) {
      userIsScrolledUp = true;
    } else {
      userIsScrolledUp = false;
    }
  });

  function fetchPatientChat() {
    var chatBox = $('#patient-chat-box');
    if (!chatBox.length) return;

    $.post(apiObj.ajax_url, {
      action: 'caretochina_get_patient_chat',
      booking_id: patientBookingId,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success && res.data) {
        chatBox.html(res.data.html);
        if (!userIsScrolledUp) {
          chatBox.scrollTop(chatBox[0].scrollHeight);
        }
        
        var typingInd = $('#patient-chat-typing-indicator');
        if (res.data.is_typing) {
          typingInd.text(res.data.typing_name + ' is typing...').show();
        } else {
          typingInd.hide().empty();
        }
      }
    });
  }

  if ($('#patient-chat-box').length) {
    fetchPatientChat();
    setInterval(fetchPatientChat, 1000);
  }

  // PATIENT MESSAGING SUBMISSION
  $('#patient-message-form').on('submit', function(e) {
    e.preventDefault();
    var input = $('#patient_msg_input');
    var sendBtn = $('#patient_send_btn');
    var msg = input.val();

    if (!msg) return;

    var chatBox = $('#patient-chat-box');
    var safeMsg = $('<div>').text(msg).html();
    var tempMsgId = 'temp-msg-' + Date.now();
    var optimisticHtml = `
      <div class="chat-msg patient mb-14 optimistic-msg" id="${tempMsgId}" style="display:flex; justify-content:flex-end; margin-bottom:14px; text-align:right; font-family:'Inter', sans-serif; width:100%; opacity:0.6;">
          <div class="msg-bubble" style="background:#0F766E; color:#FFF; padding:10px 16px; border-radius:18px 18px 2px 18px; font-size:13px; max-width:80%; line-height:1.4; display:inline-block; text-align:left; border:none;">
              ${safeMsg} <span style="font-size:11px; font-weight:700; color:#CCFBF1; margin-left:6px;">Patient</span>
              <div style="font-size:9px; text-align:right; margin-top:4px; opacity:0.8;">
                  <span style="color:#94A3B8; margin-left:6px;"><i class="fa-solid fa-spinner fa-spin"></i> Sending...</span>
              </div>
          </div>
      </div>
    `;
    
    chatBox.append(optimisticHtml);
    chatBox.scrollTop(chatBox[0].scrollHeight);
    
    var formData = $(this).serialize() + '&action=caretochina_send_patient_message&nonce=' + apiObj.nonce;

    input.val('');
    userIsScrolledUp = false;

    $.post(apiObj.ajax_url, formData, function(res) {
      if (res.success && res.data && res.data.html) {
        chatBox.html(res.data.html);
        chatBox.scrollTop(chatBox[0].scrollHeight);
      } else {
        $('#' + tempMsgId).css('opacity', '1.0');
        $('#' + tempMsgId + ' .msg-bubble').css('background', '#EF4444');
        $('#' + tempMsgId + ' i').removeClass('fa-spinner fa-spin').addClass('fa-circle-exclamation');
        $('#' + tempMsgId + ' span').text('Failed to send');
      }
    });
  });

  var isTypingSent = false;
  $('#patient_msg_input').on('keyup input', function() {
    if (!isTypingSent) {
      isTypingSent = true;
      $.post(apiObj.ajax_url, {
        action: 'caretochina_patient_send_typing',
        booking_id: patientBookingId,
        nonce: apiObj.nonce
      });
      setTimeout(function() {
        isTypingSent = false;
      }, 3000);
    }
  });

  // PATIENT PROFILE UPDATE
  $('#patient-profile-form').on('submit', function(e) {
    e.preventDefault();
    var btn = $('#save_profile_btn');
    var box = $('#profile-response-box');

    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');
    var formData = $(this).serialize() + '&action=caretochina_update_patient_profile&nonce=' + apiObj.nonce;

    $.post(apiObj.ajax_url, formData, function(res) {
      box.show();
      if (res.success) {
        box.html('<span style="color:#10b981; font-weight:700;"><i class="fa-solid fa-circle-check"></i> ' + res.data.message + '</span>');
        btn.prop('disabled', false).html('<i class="fa-solid fa-check"></i> Saved!');
        
        if (res.data.new_avatar_url) {
          $('.ctc-dash-avatar, .ctc-profile-avatar-img').attr('src', res.data.new_avatar_url);
        }

        setTimeout(function() {
          btn.html('<i class="fa-solid fa-floppy-disk"></i> Save Profile Changes');
        }, 2500);
      } else {
        box.html('<span style="color:#ef4444; font-weight:700;"><i class="fa-solid fa-triangle-exclamation"></i> ' + res.data.message + '</span>');
        btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Profile Changes');
      }
    });
  });

  // Patient Account Delete Handler
  $(document).on('click', '#delete_own_profile_btn', function(e) {
    e.preventDefault();
    if (!confirm('Are you absolutely sure you want to permanently delete your patient account, your booking request cases, and all chat message history? This action is irreversible.')) {
      return;
    }
    
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Deleting Account...');
    
    $.post(apiObj.ajax_url, {
      action: 'caretochina_patient_delete_own_account',
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        alert(res.data.message || 'Account successfully deleted.');
        window.location.href = res.data.redirect || '/';
      } else {
        alert(res.data.message || 'Failed to delete account.');
        btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i> Delete My Account');
      }
    });
  });

  // Patient Profile Image AJAX Upload Handler
  $(document).on('click', '.ctc-profile-badge-avatar, .ctc-change-avatar-btn', function(e) {
    e.preventDefault();
    $('#ctc-avatar-file-input').trigger('click');
  });

  $(document).on('change', '#ctc-avatar-file-input', function() {
    var fileInput = this;
    if (fileInput.files.length === 0) return;

    var file = fileInput.files[0];
    var statusSpan = $('#avatar-upload-status');

    var maxSizeBytes = 2 * 1024 * 1024;
    if (file.size > maxSizeBytes) {
      statusSpan.show().html('<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Size exceeds 2MB limit.</span>');
      fileInput.value = '';
      return;
    }

    var allowedExtensions = /(\.png|\.jpg|\.jpeg|\.webp)$/i;
    if (!allowedExtensions.exec(file.name)) {
      statusSpan.show().html('<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Only PNG, JPG, and WEBP allowed.</span>');
      fileInput.value = '';
      return;
    }

    var formData = new FormData();
    formData.append('avatar', file);
    formData.append('action', 'caretochina_upload_patient_avatar');
    formData.append('nonce', apiObj.nonce);

    statusSpan.show().html('<span style="color:#0f766e;"><i class="fa-solid fa-spinner fa-spin"></i> Uploading...</span>');
    $('.ctc-avatar-upload-overlay').css('opacity', '1').html('<i class="fa-solid fa-spinner fa-spin" style="font-size:20px; color:#fff;"></i>');

    $.ajax({
      url: apiObj.ajax_url,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(res) {
        if (res.success) {
          statusSpan.html('<span style="color:#10b981;"><i class="fa-solid fa-circle-check"></i> Uploaded!</span>');
          $('.ctc-dash-avatar, .ctc-profile-avatar-img').attr('src', res.data.avatar_url);
          setTimeout(function() {
            statusSpan.fadeOut();
          }, 3000);
        } else {
          statusSpan.html('<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> ' + res.data.message + '</span>');
        }
      },
      error: function() {
        statusSpan.html('<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> Upload failed. Try again.</span>');
      },
      complete: function() {
        $('.ctc-avatar-upload-overlay').css('opacity', '').html('<i class="fa-solid fa-camera" style="font-size: 20px;"></i>');
        fileInput.value = '';
      }
    });
  });

  // AUTH LOGIN SUBMISSION
  $('#careyou-auth-login-form').on('submit', function(e) {
    e.preventDefault();
    var btn = $('#login_submit_btn');
    var box = $('#login-response-box');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...');

    var formData = $(this).serialize() + '&action=caretochina_user_login&nonce=' + apiObj.nonce;

    $.post(apiObj.ajax_url, formData, function(res) {
      box.show();
      if (res.success) {
        box.html('<span style="color:#10b981; font-weight:700;"><i class="fa-solid fa-circle-check"></i> ' + res.data.message + '</span>');
        setTimeout(function() { window.location.href = res.data.redirect; }, 1000);
      } else {
        box.html('<span style="color:#ef4444; font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> ' + res.data.message + '</span>');
        btn.prop('disabled', false).html('<i class="fa-solid fa-right-to-bracket"></i> Sign In to Account');
      }
    });
  });

  // AUTH REGISTER SUBMISSION
  $('#careyou-auth-register-form').on('submit', function(e) {
    e.preventDefault();
    var btn = $('#reg_submit_btn');
    var box = $('#reg-response-box');
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Creating Patient Account...');

    var formData = $(this).serialize() + '&action=caretochina_user_register&nonce=' + apiObj.nonce;

    $.post(apiObj.ajax_url, formData, function(res) {
      box.show();
      if (res.success) {
        box.html('<span style="color:#10b981; font-weight:700;"><i class="fa-solid fa-circle-check"></i> ' + res.data.message + '</span>');
        setTimeout(function() { window.location.href = res.data.redirect; }, 1000);
      } else {
        box.html('<span style="color:#ef4444; font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> ' + res.data.message + '</span>');
        btn.prop('disabled', false).html('<i class="fa-solid fa-user-plus"></i> Register Patient Account');
      }
    });
  });
});