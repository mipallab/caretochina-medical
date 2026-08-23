/* ==========================================================================
   CARETOCHINA MEDICAL BOOKING INTERACTIVE JAVASCRIPT ENGINE
   ========================================================================== */

function getBookingObj() {
  return (typeof caretochina_obj !== 'undefined') ? caretochina_obj : ((typeof careyou_obj !== 'undefined') ? careyou_obj : { hospitals: [], all_specialties: [], all_cities: [], ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });
}

// Global Cross-Browser Web Audio Notifier with User Interaction Unlock
var CTC_Audio = (function() {
  var ctx = null;
  var unlocked = false;

  function initContext() {
    try {
      if (!ctx) {
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (AudioCtx) {
          ctx = new AudioCtx();
        }
      }
      if (ctx && ctx.state === 'suspended') {
        ctx.resume().then(function() {
          unlocked = true;
        }).catch(function() {});
      } else if (ctx && ctx.state === 'running') {
        unlocked = true;
      }
    } catch(e) {}
  }

  // Pre-warm / unlock on first user gesture anywhere on page
  if (typeof document !== 'undefined') {
    var unlockEvents = ['click', 'touchstart', 'keydown', 'mousedown'];
    var unlockHandler = function() {
      initContext();
      if (unlocked) {
        unlockEvents.forEach(function(evt) {
          document.removeEventListener(evt, unlockHandler, true);
        });
      }
    };
    unlockEvents.forEach(function(evt) {
      document.addEventListener(evt, unlockHandler, { capture: true, passive: true });
    });
  }

  return {
    unlock: function() {
      initContext();
    },
    play: function(type) {
      initContext();
      if (!ctx) return;

      try {
        if (ctx.state === 'suspended') {
          ctx.resume();
        }
        var now = ctx.currentTime;
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (type === 'booking' || type === 'confirm') {
          // 3-tone harmonic chime for Bookings / Confirmations
          osc.type = 'sine';
          osc.frequency.setValueAtTime(523.25, now);
          osc.frequency.setValueAtTime(659.25, now + 0.11);
          osc.frequency.setValueAtTime(783.99, now + 0.22);
          gain.gain.setValueAtTime(0.18, now);
          gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);
          osc.start(now);
          osc.stop(now + 0.6);
        } else if (type === 'approve' || type === 'action') {
          // Success tone
          osc.type = 'sine';
          osc.frequency.setValueAtTime(659.25, now);
          osc.frequency.setValueAtTime(987.77, now + 0.1);
          gain.gain.setValueAtTime(0.14, now);
          gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
          osc.start(now);
          osc.stop(now + 0.4);
        } else {
          // Gentle crisp message ding: D5 (587Hz) -> A5 (880Hz)
          osc.type = 'sine';
          osc.frequency.setValueAtTime(587.33, now);
          osc.frequency.setValueAtTime(880.00, now + 0.1);
          gain.gain.setValueAtTime(0.13, now);
          gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);
          osc.start(now);
          osc.stop(now + 0.45);
        }
      } catch(e) {
        console.warn('Audio play error:', e);
      }
    }
  };
})();

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

    var hasPricingStep = jQuery('#wiz-step-3').length > 0;
    var indicatorStep = stepNum;
    if (!hasPricingStep && stepNum === 4) {
      indicatorStep = 3; // On 3-step guest indicator, page 4 maps to indicator 3
    }

    jQuery('.wiz-step').removeClass('active');
    jQuery('.wiz-step').each(function() {
      const step = parseInt(jQuery(this).data('step'));
      if (step <= indicatorStep) {
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

    // STEP 2 VALIDATION (Timing & Specialty -> Step 3 or 4)
    if ((stepNum === 3 || stepNum === 4) && this.currentStep === 2) {
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
      if (stepNum === 3 && jQuery('#wiz-step-3').length > 0) {
        this.loadPricingPlansForSelectedSpecialty();
      }
    }

    // STEP 3 -> 4 VALIDATION (Pricing Plan Selection - Logged In only)
    if (stepNum === 4 && this.currentStep === 3) {
      var planId = parseInt(jQuery('#wiz_pricing_plan_id').val() || 0);
      var plansCount = jQuery('.pricing-plan-card').length;
      if (plansCount > 0 && planId === 0) {
        alert('Please select a pricing plan tier to proceed.');
        return;
      }
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

      const imgSrc = h.image_thumb || h.image;
      const srcsetAttribute = h.image_srcset ? `srcset="${h.image_srcset}" sizes="70px"` : '';

      const card = jQuery(`
        <div class="hospital-select-card" onclick="appWizard.selectHospitalCard(this, ${h.id}, '${h.title.replace(/'/g, "\\'")}')" style="border:1px solid #E2E8F0; padding:12px; border-radius:12px; cursor:pointer; background:#FFF; transition:all 0.2s; display:flex; gap:12px; align-items:center;">
          <img src="${imgSrc}" ${srcsetAttribute} loading="lazy" alt="${h.title}" style="width:70px; height:50px; border-radius:6px; object-fit:cover;">
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
            <div class="pricing-plan-card ${isChecked ? 'selected' : ''}" onclick="appWizard.selectPricingPlanCard(this, ${plan.id}, ${plan.price}, '${plan.name.replace(/'/g, "\\'")}', '${plan.currency}')">
              <div class="plan-card-left">
                <div class="plan-card-header">
                  <input type="radio" name="plan_radio" value="${plan.id}" ${isChecked ? 'checked' : ''}>
                  <h4 class="plan-card-title">${plan.name}</h4>
                </div>
                ${plan.description ? `<p class="plan-card-desc">${plan.description}</p>` : ''}
              </div>
              <div class="plan-card-right">
                <div class="plan-price">$${parseFloat(plan.price).toFixed(2)}</div>
                <div class="plan-currency">${plan.currency}</div>
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
    jQuery('.pricing-plan-card').removeClass('selected');
    jQuery(element).addClass('selected');
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
      jQuery('#wiz-auth-tab-login').addClass('active');
      jQuery('#wiz-auth-tab-reg').removeClass('active');
      jQuery('#wiz-ajax-login-form').show();
      jQuery('#wiz-ajax-reg-form').hide();
    } else {
      jQuery('#wiz-auth-tab-reg').addClass('active');
      jQuery('#wiz-auth-tab-login').removeClass('active');
      jQuery('#wiz-ajax-reg-form').show();
      jQuery('#wiz-ajax-login-form').hide();
    }
  }
};

// ==========================================================================
// CENTRALIZED BROWSER WEB NOTIFICATION ENGINE (DESKTOP & MOBILE)
// ==========================================================================
window.CTC_BrowserNotif = {
  init: function() {
    if (!("Notification" in window)) return;
    
    // Auto-prompt for notification permission after 20 seconds of user activity on the portal
    if (Notification.permission === "default") {
      setTimeout(function() {
        if (Notification.permission === "default") {
          CTC_BrowserNotif.requestPermission();
        }
      }, 20000);
    }
  },
  requestPermission: function(callback) {
    if (!("Notification" in window)) return;
    try {
      Notification.requestPermission().then(function(permission) {
        if (typeof callback === 'function') callback(permission);
      });
    } catch (e) {
      console.warn('Browser notification permission error:', e);
    }
  },
  send: function(title, body, url) {
    if (!("Notification" in window) || Notification.permission !== "granted") return;
    try {
      var notif = new Notification(title, {
        body: body,
        icon: (window.caretochina_obj && window.caretochina_obj.plugin_url) ? window.caretochina_obj.plugin_url + '/assets/images/favicon.png' : '',
        tag: 'ctc-notice-' + Date.now()
      });
      if (url) {
        notif.onclick = function() {
          window.focus();
          if (window.location.href !== url) {
            window.location.href = url;
          }
          notif.close();
        };
      }
    } catch (e) {
      console.warn('Browser notification error:', e);
    }
  }
};
CTC_BrowserNotif.init();

window.appDash = {
  switchTab(button, tabId) {
    jQuery('.ctc-sidebar-tab, .dash-tab').removeClass('active');
    jQuery(button).addClass('active');

    jQuery('.ctc-dash-panel, .dash-panel').removeClass('active').hide();
    jQuery('#dash-panel-' + tabId).addClass('active').show();

    // Auto-clear read badges when seen
    if (tabId === 'messages') {
      jQuery('#patient-unread-msg-badge').hide().text('0');
      jQuery('#patient-hdr-msg-badge').hide().text('0');
    } else if (tabId === 'invoices') {
      jQuery('#patient-unread-invoice-badge').hide().text('0');
    }
  },
  switchTabDirect(tabId) {
    jQuery('.ctc-sidebar-tab, .dash-tab').removeClass('active');
    var targetBtn = jQuery('.ctc-sidebar-tab[onclick*="\'' + tabId + '\'"], .dash-tab[onclick*="\'' + tabId + '\'"]');
    if (targetBtn.length) {
      targetBtn.addClass('active');
    }
    jQuery('.ctc-dash-panel, .dash-panel').removeClass('active').hide();
    jQuery('#dash-panel-' + tabId).addClass('active').show();

    // Auto-clear read badges when seen
    if (tabId === 'messages') {
      jQuery('#patient-unread-msg-badge').hide().text('0');
      jQuery('#patient-hdr-msg-badge').hide().text('0');
    } else if (tabId === 'invoices') {
      jQuery('#patient-unread-invoice-badge').hide().text('0');
    }
  },
  printReceipt(code, title, amount, currency, date, patientName) {
    var pName = patientName || jQuery('.ctc-dash-welcome-text').text().replace('Welcome back, ', '') || 'Patient';
    var printWindow = window.open('', '_blank', 'width=750,height=850');
    var html = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Payment Receipt - #${code}</title>
        <style>
          body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; color: #1E293B; background: #FFF; line-height: 1.5; }
          .receipt-container { max-width: 650px; margin: 0 auto; border: 1px solid #E2E8F0; padding: 36px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
          .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0F766E; padding-bottom: 20px; margin-bottom: 24px; }
          .brand-title { font-size: 24px; font-weight: 900; color: #0F766E; }
          .brand-sub { font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 1px; }
          .receipt-badge { background: #CCFBF1; color: #0F766E; padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 13px; }
          .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 28px; font-size: 14px; }
          .meta-box { background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; }
          .meta-label { font-size: 11px; color: #64748B; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 4px; }
          .meta-val { font-weight: 700; color: #0F172A; }
          .items-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
          .items-table th { background: #F1F5F9; padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #CBD5E1; }
          .items-table td { padding: 14px 12px; border-bottom: 1px solid #E2E8F0; font-size: 14px; }
          .total-box { text-align: right; margin-bottom: 30px; }
          .total-label { font-size: 14px; color: #64748B; }
          .total-amount { font-size: 26px; font-weight: 900; color: #0F766E; }
          .stamp { display: inline-block; border: 2px dashed #059669; color: #059669; font-weight: 900; text-transform: uppercase; padding: 6px 16px; border-radius: 8px; font-size: 14px; letter-spacing: 1px; }
          .footer { text-align: center; font-size: 11px; color: #94A3B8; border-top: 1px solid #E2E8F0; padding-top: 18px; margin-top: 20px; }
          @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .receipt-container { border: none; box-shadow: none; padding: 0; }
          }
        </style>
      </head>
      <body>
        <div class="receipt-container">
          <div class="no-print" style="text-align:right; margin-bottom:16px;">
            <button onclick="window.print()" style="background:#0F766E; color:#FFF; border:none; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px;">🖨️ Print Receipt</button>
            <button onclick="window.close()" style="background:#F1F5F9; color:#475569; border:none; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer; font-size:14px; margin-left:8px;">Close</button>
          </div>
          <div class="header">
            <div>
              <div class="brand-title">CareToChina</div>
              <div class="brand-sub">Medical Travel & Access Services</div>
            </div>
            <div>
              <span class="receipt-badge">OFFICIAL RECEIPT</span>
            </div>
          </div>
          <div class="meta-grid">
            <div class="meta-box">
              <span class="meta-label">Patient Name</span>
              <div class="meta-val">${pName}</div>
            </div>
            <div class="meta-box">
              <span class="meta-label">Reference Number</span>
              <div class="meta-val">#${code}</div>
            </div>
            <div class="meta-box">
              <span class="meta-label">Payment Date</span>
              <div class="meta-val">${date}</div>
            </div>
            <div class="meta-box">
              <span class="meta-label">Payment Gateway</span>
              <div class="meta-val">Verified Online Checkout (SSL 256-bit)</div>
            </div>
          </div>
          <table class="items-table">
            <thead>
              <tr>
                <th>Description</th>
                <th style="text-align:right;">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>${title}</strong><br><span style="font-size:12px; color:#64748B;">Authoritative medical treatment booking / consultation package</span></td>
                <td style="text-align:right; font-weight:700;">$${amount} ${currency}</td>
              </tr>
            </tbody>
          </table>
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <span class="stamp">✓ PAID IN FULL</span>
            </div>
            <div class="total-box">
              <div class="total-label">Total Amount Paid</div>
              <div class="total-amount">$${amount} ${currency}</div>
            </div>
          </div>
          <div class="footer">
            CareToChina Medical Travel Services • Confidential & Verified Medical Transaction • Contact: caretochina.com
          </div>
        </div>
      </body>
      </html>
    `;
    printWindow.document.write(html);
    printWindow.document.close();
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
  });  // GLOBAL CHAT ATTACHMENT HELPER
  window.appChat = {
    handleFileSelected: function(input) {
      if (input.files && input.files[0]) {
        var file = input.files[0];
        if (file.size > 2097152) {
          alert('Attachment file size exceeds the 2MB limit. Please select an image or PDF under 2MB.');
          input.value = '';
          jQuery('#patient_attachment_preview').hide();
          return;
        }
        jQuery('#patient_attachment_name').text(file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)');
        jQuery('#patient_attachment_preview').css('display', 'flex');
      }
    },
    clearAttachment: function() {
      var input = document.getElementById('patient_chat_file_input');
      if (input) input.value = '';
      jQuery('#patient_attachment_preview').hide();
    }
  };

  // RESTORE DRAFT STATE IF LOGGED IN (e.g. Returned from Google OAuth)
  var savedDraft = sessionStorage.getItem('ctc_wizard_draft');
  if (savedDraft && isUserLoggedIn()) {
    try {
      var draftData = JSON.parse(savedDraft);
      sessionStorage.removeItem('ctc_wizard_draft');
      submitBookingForm(draftData);
    } catch (err) {
      sessionStorage.removeItem('ctc_wizard_draft');
    }
  }

  // WIZARD FORM SUBMISSION HANDLER (Direct submission for both Guests and Logged-in Patients)
  $('#ctc-booking-wizard-form').on('submit', function(e) {
    e.preventDefault();
    syncIntlPhoneValues(this);
    var formSerialized = $(this).serialize();
    submitBookingForm(formSerialized);
  });

  function submitBookingForm(serializedData) {
    var btn = $('#ctc-wizard-submit-btn');
    var status = $('#ctc-wizard-status');

    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Submitting Booking...');
    var postData = (typeof serializedData === 'string' ? serializedData : $.param(serializedData)) + '&action=caretochina_submit_booking&nonce=' + apiObj.nonce;

    $.post(apiObj.ajax_url, postData, function(res) {
      status.show();
      if (res.success && res.data) {
        sessionStorage.removeItem('ctc_wizard_draft');
        CTC_Audio.play('booking');
        status.html(`<div style="background:#D1FAE5; color:#065F46; padding:16px; border-radius:12px; font-weight:700; font-size:14px;"><i class="fa-solid fa-circle-check"></i> ${res.data.message}</div>`);
        btn.html('<i class="fa-solid fa-check"></i> Confirmed');

        setTimeout(function() {
          appWizard.closeModal();
          $('#ctc-booking-wizard-form')[0].reset();
          btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Confirm & Submit');
          status.hide().empty();

          if (res.data.is_guest) {
            // Guest User: Payment skipped! Redirect immediately to secure Live Chat Consultation thread
            if (res.data.chat_url) {
              window.location.href = res.data.chat_url;
            } else {
              window.location.reload();
            }
          } else {
            // Logged-in user: Open payment modal if available, otherwise transition to dashboard
            if (window.CareToChinaPayment && typeof window.CareToChinaPayment.openPaymentModal === 'function') {
              CareToChinaPayment.openPaymentModal(res.data.booking_id, res.data.amount, res.data.currency, res.data.specialty);
            } else if (res.data.chat_url) {
              window.location.href = res.data.chat_url;
            } else {
              window.location.reload();
            }
          }
        }, 400);
      } else {
        status.html(`<div style="color:#EF4444; font-weight:700; font-size:14px;"><i class="fa-solid fa-triangle-exclamation"></i> ${(res.data && res.data.message) || 'Failed to submit booking.'}</div>`);
        btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Confirm & Submit');
      }
    }).fail(function() {
      btn.prop('disabled', false).html('<i class="fa-solid fa-check-circle"></i> Confirm & Submit');
      alert('Network error submitting booking.');
    });
  }

  // REAL-TIME CHAT POLLING FOR PATIENT / GUEST DASHBOARD
  var patientBookingId = $('.caretochina-dashboard-wrapper, .careyou-dashboard-wrapper').data('booking-id') || 0;
  var guestToken = $('.caretochina-guest-dashboard').data('guest-token') || '';
  var userIsScrolledUp = false;
  var lastCoordinatorMsgCount = -1;
  var lastPaymentCardCount = -1;

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
    if (!chatBox.length || !patientBookingId) return;

    $.post(apiObj.ajax_url, {
      action: 'caretochina_get_patient_chat',
      booking_id: patientBookingId,
      guest_token: guestToken,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success && res.data) {
        var newHtml = res.data.html;

        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = newHtml;

        // 1. Detect new coordinator messages
        var coordMsgs = tempDiv.querySelectorAll('.chat-msg.coordinator').length;
        if (lastCoordinatorMsgCount !== -1 && coordMsgs > lastCoordinatorMsgCount) {
          CTC_Audio.play('message');
          var lastMsgNode = $(tempDiv).find('.chat-msg.coordinator').last();
          var latestMsgText = lastMsgNode.find('.patient-msg-text, .msg-text, p').text() || lastMsgNode.text() || 'New message received';
          latestMsgText = $.trim(latestMsgText);
          if (latestMsgText.length > 60) latestMsgText = latestMsgText.substring(0, 57) + '...';

          var isMessagesActive = $('#dash-panel-messages').is(':visible');
          if (!isMessagesActive) {
            showPatientNotificationToast('Care Coordinator', latestMsgText);
            if (window.CTC_BrowserNotif) {
              CTC_BrowserNotif.send('New Message from Care Coordinator', latestMsgText, window.location.href);
            }
          }
        }
        lastCoordinatorMsgCount = coordMsgs;

        // 2. Detect new payment request cards in chat
        var paymentCards = tempDiv.querySelectorAll('.ctc-chat-payment-card').length;
        if (lastPaymentCardCount !== -1 && paymentCards > lastPaymentCardCount) {
          CTC_Audio.play('payment');
          var isInvoiceActive = $('#dash-panel-invoices').is(':visible');
          if (!isInvoiceActive) {
            showPatientNotificationToast('Payment Request', 'A new medical payment request has been prepared for your case.');
            if (window.CTC_BrowserNotif) {
              CTC_BrowserNotif.send('Medical Payment Request Issued', 'Your care coordinator has sent a treatment payment request. Click to view & pay.', window.location.href);
            }
            $('#patient-unread-invoice-badge').text('1').css({display:'inline-block', background:'#EF4444', color:'#FFF'});
          }
        }
        lastPaymentCardCount = paymentCards;

        // 3. Update unread badge in sidebar & header
        var unreadBadge = $('#patient-unread-msg-badge');
        var hdrBadge = $('#patient-hdr-msg-badge');
        var isMessagesTab = $('#dash-panel-messages').hasClass('active') && $('#dash-panel-messages').is(':visible');
        if (isMessagesTab) {
          if (unreadBadge.length) unreadBadge.hide().text('0');
          if (hdrBadge.length) hdrBadge.hide().text('0');
        } else if (lastCoordinatorMsgCount > 0) {
          if (unreadBadge.length) unreadBadge.text(lastCoordinatorMsgCount).css({display:'inline-block', background:'#EF4444', color:'#FFF'});
          if (hdrBadge.length) hdrBadge.text(lastCoordinatorMsgCount).css({display:'flex', background:'#EF4444', color:'#FFF'});
        }

        chatBox.html(newHtml);
        if (!userIsScrolledUp) {
          chatBox.scrollTop(chatBox[0].scrollHeight);
        }
        
        var typingInd = $('#patient-chat-typing-indicator');
        if (res.data.is_typing) {
          typingInd.text((res.data.typing_name || 'Coordinator') + ' is typing...').show();
        } else {
          typingInd.hide().empty();
        }
      }
    });
  }

  function showPatientNotificationToast(sender, message) {
    var existingToast = $('#ctc-patient-msg-toast');
    if (existingToast.length) existingToast.remove();

    var toastHtml = $(`
      <div id="ctc-patient-msg-toast" style="position:fixed; top:24px; right:24px; z-index:999999; background:#0F766E; color:#FFFFFF; padding:14px 20px; border-radius:14px; box-shadow:0 10px 30px rgba(15,118,110,0.35); display:flex; align-items:center; gap:14px; font-family:'Inter',sans-serif; max-width:360px; cursor:pointer; animation:ctcSlideInRight 0.3s ease;">
        <div style="font-size:22px; color:#5EEAD4;"><i class="fa-solid fa-comment-dots"></i></div>
        <div style="flex:1; overflow:hidden;">
          <div style="font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">${sender}</div>
          <div style="font-size:13px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">${message}</div>
        </div>
        <button type="button" style="background:none; border:none; color:#FFF; font-size:16px; cursor:pointer; opacity:0.7;" onclick="event.stopPropagation(); jQuery('#ctc-patient-msg-toast').fadeOut(200);">&times;</button>
      </div>
    `);

    toastHtml.on('click', function() {
      if (window.appDash && typeof window.appDash.switchTabDirect === 'function') {
        window.appDash.switchTabDirect('messages');
      }
      $(this).fadeOut(200);
    });

    $('body').append(toastHtml);
    setTimeout(function() {
      toastHtml.fadeOut(400, function() { $(this).remove(); });
    }, 6000);
  }

  if ($('#patient-chat-box').length) {
    fetchPatientChat();
    setInterval(fetchPatientChat, 1000);
  }

  // PATIENT / GUEST MESSAGING SUBMISSION (WITH ATTACHMENTS)
  $('#patient-message-form').on('submit', function(e) {
    e.preventDefault();
    var input = $('#patient_msg_input');
    var fileInput = document.getElementById('patient_chat_file_input');
    var msg = input.val();
    var hasFile = (fileInput && fileInput.files && fileInput.files.length > 0);

    if (!msg && !hasFile) return;

    var chatBox = $('#patient-chat-box');
    var safeMsg = $('<div>').text(msg).html();
    var tempMsgId = 'temp-msg-' + Date.now();
    var optimisticHtml = `
      <div class="chat-msg patient mb-14 optimistic-msg" id="${tempMsgId}" style="opacity:0.6;">
          <div class="msg-bubble">
              <span class="patient-msg-text">${safeMsg ? safeMsg : '<em>[Uploading attachment...]</em>'}</span>
              <span class="patient-name-tag">(You)</span>
              <div class="chat-msg-meta">
                  <span class="chat-tick chat-tick-delivered"><i class="fa-solid fa-spinner fa-spin"></i> Sending...</span>
              </div>
          </div>
      </div>
    `;
    
    chatBox.append(optimisticHtml);
    chatBox.scrollTop(chatBox[0].scrollHeight);
    
    var fd = new FormData(this);
    fd.append('action', 'caretochina_send_patient_message');
    fd.append('nonce', apiObj.nonce);

    input.val('');
    appChat.clearAttachment();
    userIsScrolledUp = false;

    $.ajax({
      url: apiObj.ajax_url,
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      success: function(res) {
        if (res.success && res.data && res.data.html) {
          chatBox.html(res.data.html);
          chatBox.scrollTop(chatBox[0].scrollHeight);
        } else {
          $('#' + tempMsgId).css('opacity', '1.0');
          $('#' + tempMsgId + ' .msg-bubble').css('background', '#EF4444');
          $('#' + tempMsgId + ' i').removeClass('fa-spinner fa-spin').addClass('fa-circle-exclamation');
          $('#' + tempMsgId + ' span').text((res.data && res.data.message) || 'Failed to send');
        }
      },
      error: function() {
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

  // ==========================================================================
  // MOBILE VIRTUAL KEYBOARD DYNAMIC VIEWPORT HANDLER (iOS / Android)
  // ==========================================================================
  if (window.visualViewport) {
    var chatViewport = document.getElementById('patient-chat-box');
    var msgInput = document.getElementById('patient_msg_input');

    var handleViewportChange = function() {
      if (!chatViewport) {
        chatViewport = document.getElementById('patient-chat-box');
      }
      if (chatViewport && document.activeElement === msgInput) {
        // Smoothly scroll chat to latest message when keyboard pops up
        setTimeout(function() {
          chatViewport.scrollTop = chatViewport.scrollHeight;
        }, 150);
      }
    };

    window.visualViewport.addEventListener('resize', handleViewportChange);
    window.visualViewport.addEventListener('scroll', handleViewportChange);

    if (msgInput) {
      msgInput.addEventListener('focus', function() {
        setTimeout(function() {
          if (chatViewport) chatViewport.scrollTop = chatViewport.scrollHeight;
        }, 300);
      });
    }
  }

  // ==========================================================================
  // INTERNATIONAL TELEPHONE INPUT ENGINE
  // ==========================================================================
  function updateItiPadding(el) {
    if (!el) return;
    setTimeout(function() {
      var $el = $(el);
      var $container = $el.closest('.iti');
      var $selected = $container.find('.iti__selected-country, .iti__country-container, .iti__selected-country-primary');
      if ($selected.length) {
        var w = $selected.first().outerWidth();
        if (w > 0 && w < 160) {
          el.style.setProperty('padding-left', (Math.ceil(w) + 8) + 'px', 'important');
          return;
        }
      }
      el.style.setProperty('padding-left', '52px', 'important');
    }, 25);
  }

  function initIntlTelInputs() {
    var apiObj = getBookingObj();
    if (!apiObj || !apiObj.intl_phone_enabled || typeof window.intlTelInput === 'undefined') {
      return;
    }

    $('input[type="tel"], input[name="phone"], input[name="user_phone"], input[name="whatsapp"], input[name="user_whatsapp"]').each(function() {
      var el = this;
      if ($(el).closest('.ctc-phone-group-wrapper').length) {
        return; // Managed cleanly by native country code selector group
      }
      if ($(el).data('iti-initialized')) {
        updateItiPadding(el);
        return;
      }
      $(el).data('iti-initialized', true);

      try {
        var iti = window.intlTelInput(el, {
          separateDialCode: true,
          showSelectedDialCode: true,
          initialCountry: 'auto',
          geoIpLookup: function(callback) {
            fetch('https://ipapi.co/json')
              .then(function(res) { return res.json(); })
              .then(function(data) { callback(data.country_code); })
              .catch(function() { callback('us'); });
          },
          preferredCountries: ['us', 'gb', 'ca', 'au', 'cn', 'bd', 'in', 'ae', 'sa'],
          utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.6/build/js/utils.js'
        });
        $(el).data('iti-instance', iti);

        updateItiPadding(el);
        el.addEventListener('countrychange', function() {
          updateItiPadding(el);
        });
        el.addEventListener('open:countrydropdown', function() {
          updateItiPadding(el);
        });
        el.addEventListener('close:countrydropdown', function() {
          updateItiPadding(el);
        });
      } catch (err) {}
    });
  }

  function syncIntlPhoneValues(form) {
    if (!form) return;
    $(form).find('input[type="tel"], input[name="phone"], input[name="user_phone"], input[name="whatsapp"], input[name="user_whatsapp"]').each(function() {
      var iti = $(this).data('iti-instance');
      if (iti && typeof iti.getNumber === 'function') {
        var rawVal = $(this).val().trim();
        if (rawVal) {
          var fullNum = iti.getNumber();
          if (!fullNum) {
            var countryData = iti.getSelectedCountryData();
            if (countryData && countryData.dialCode) {
              fullNum = '+' + countryData.dialCode + ' ' + rawVal;
            }
          }
          if (fullNum) {
            $(this).val(fullNum);
          }
        }
      }
    });
  }

  // ==========================================================================
  // NATIVE COUNTRY SELECT & PHONE INPUT GROUP AUTO-STRIP LOGIC
  // ==========================================================================
  $(document).on('input paste blur', '.ctc-phone-group-wrapper .ctc-phone-input', function() {
    var $input = $(this);
    var $wrapper = $input.closest('.ctc-phone-group-wrapper');
    var $select = $wrapper.find('.ctc-country-select');
    var currentDial = $select.val() || '+1';
    var dialDigits = currentDial.replace(/\D/g, '');
    var val = $input.val();

    if (!val) return;

    // 1. If user typed/pasted a +countrycode prefix matching current selection
    if (val.indexOf(currentDial) === 0) {
      val = val.substring(currentDial.length).trim();
      $input.val(val);
      return;
    }

    // 2. If user pasted a + from another country (e.g. +1 or +44 or +880), auto-select that country
    if (val.charAt(0) === '+') {
      var matched = false;
      $select.find('option').each(function() {
        var optDial = $(this).val();
        if (optDial && val.indexOf(optDial) === 0) {
          $select.val(optDial);
          val = val.substring(optDial.length).trim();
          $input.val(val);
          matched = true;
          return false;
        }
      });
      if (matched) return;
    }

    // 3. If user typed dial digits without plus e.g. 8801749949010 while +880 is active
    if (dialDigits && val.indexOf(dialDigits) === 0 && val.length > dialDigits.length + 5) {
      val = val.substring(dialDigits.length).trim();
      $input.val(val);
    }
  });

  $(document).on('change', '.ctc-phone-group-wrapper .ctc-country-select', function() {
    var $select = $(this);
    var $wrapper = $select.closest('.ctc-phone-group-wrapper');
    var $input = $wrapper.find('.ctc-phone-input');
    var currentDial = $select.val() || '';
    var dialDigits = currentDial.replace(/\D/g, '');
    var val = $input.val();

    if (val && dialDigits && val.indexOf(dialDigits) === 0 && val.length > dialDigits.length + 5) {
      val = val.substring(dialDigits.length).trim();
      $input.val(val);
    }
  });

  function syncPhoneGroupWidths() {
    $('.ctc-phone-group-wrapper').each(function() {
      var $wrapper = $(this);
      var $select = $wrapper.find('.ctc-country-select');
      var format = $wrapper.attr('data-selector-format') || $select.attr('data-format') || 'both';

      if (format === 'flag') {
        $select.css({ width: '76px', minWidth: '76px', maxWidth: '76px' });
      } else if (format === 'code') {
        $select.css({ width: '96px', minWidth: '96px', maxWidth: '96px' });
      } else {
        $select.css({ width: '136px', minWidth: '136px', maxWidth: '145px' });
      }
    });
  }

  // Initialize on document ready & modal open
  initIntlTelInputs();
  syncPhoneGroupWidths();
  $(document).on('click', '.ctc-trigger-booking, a[href="#booking"], .ctc-auth-tab-btn, .ctc-sidebar-tab', function() {
    setTimeout(function() {
      initIntlTelInputs();
      syncPhoneGroupWidths();
    }, 150);
  });

  // ==========================================================================
  // PASSWORD COMPLEXITY & VALIDATION ENGINE
  // ==========================================================================
  function checkPasswordStrength(pass) {
    if (!pass) {
      return { valid: false, message: 'Password is required.' };
    }
    if (/\s/.test(pass)) {
      return { valid: false, message: 'Password cannot contain spaces or whitespace.' };
    }
    if (pass.length < 6) {
      return { valid: false, message: 'Password must be at least 6 characters long.' };
    }
    if (pass.length > 20) {
      return { valid: false, message: 'Password cannot exceed 20 characters.' };
    }
    if (!/[a-zA-Z]/.test(pass)) {
      return { valid: false, message: 'Password must contain at least one letter (a-z, A-Z).' };
    }
    if (!/[0-9]/.test(pass)) {
      return { valid: false, message: 'Password must contain at least one number (0-9).' };
    }
    return { valid: true, message: 'Strong password (meets all criteria).' };
  }

  // Real-time password validation listener
  $(document).on('keyup input', '#reg_user_pass', function() {
    var pass = $(this).val();
    var rulesBox = $('#reg_pass_rules');
    if (!rulesBox.length) return;

    if (!pass) {
      rulesBox.html('<i class="fa-solid fa-shield-halved"></i> 6–20 characters (a-z, A-Z, 0-9), no spaces allowed').css('color', '#64748B');
      return;
    }

    var res = checkPasswordStrength(pass);
    if (res.valid) {
      rulesBox.html('<span style="color:#10B981; font-weight:700;"><i class="fa-solid fa-circle-check"></i> ' + res.message + '</span>');
    } else {
      rulesBox.html('<span style="color:#EF4444; font-weight:600;"><i class="fa-solid fa-circle-xmark"></i> ' + res.message + '</span>');
    }

    var confirmVal = $('#reg_user_pass_confirm').val();
    if (confirmVal) {
      $('#reg_user_pass_confirm').trigger('input');
    }
  });

  // Real-time confirm password listener
  $(document).on('keyup input', '#reg_user_pass_confirm', function() {
    var confirmVal = $(this).val();
    var passVal = $('#reg_user_pass').val();
    var matchBox = $('#reg_pass_match_msg');
    if (!matchBox.length) return;

    if (!confirmVal) {
      matchBox.hide().empty();
      return;
    }

    matchBox.show();
    if (confirmVal === passVal) {
      matchBox.html('<span style="color:#10B981; font-weight:700;"><i class="fa-solid fa-circle-check"></i> Passwords match</span>');
    } else {
      matchBox.html('<span style="color:#EF4444; font-weight:600;"><i class="fa-solid fa-circle-xmark"></i> Passwords do not match</span>');
    }
  });

  // PATIENT PROFILE UPDATE
  $('#patient-profile-form').on('submit', function(e) {
    e.preventDefault();
    syncIntlPhoneValues(this);

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
        }, 6000);
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

  // AUTH REGISTER SUBMISSION (WITH JS PASSWORD & PHONE VALIDATION)
  $('#careyou-auth-register-form').on('submit', function(e) {
    e.preventDefault();
    var pass = $('#reg_user_pass').val();
    var passConfirm = $('#reg_user_pass_confirm').val();
    var btn = $('#reg_submit_btn');
    var box = $('#reg-response-box');

    // Validate password (Char + Number, 6 to 20 chars)
    var passCheck = checkPasswordStrength(pass);
    if (!passCheck.valid) {
      box.show().html('<span style="color:#ef4444; font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> ' + passCheck.message + '</span>');
      $('#reg_user_pass').focus();
      return;
    }

    if (pass !== passConfirm) {
      box.show().html('<span style="color:#ef4444; font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> Passwords do not match. Please verify your password.</span>');
      $('#reg_user_pass_confirm').focus();
      return;
    }

    syncIntlPhoneValues(this);

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

  // ==========================================================================
  // LIVE GUEST CHAT COUNTDOWN TIMER TICKER
  // ==========================================================================
  function initGuestCountdownTimer() {
    var $timer = $('#ctc-guest-countdown-display');
    if (!$timer.length) return;

    var expiresTimestamp = parseInt($timer.attr('data-expires'), 10);
    if (!expiresTimestamp || isNaN(expiresTimestamp)) return;

    function updateTimer() {
      var now = Math.floor(Date.now() / 1000);
      var diff = expiresTimestamp - now;

      if (diff <= 0) {
        $timer.html('<span style="color:#EF4444; font-weight:800;"><i class="fa-solid fa-triangle-exclamation"></i> Session Expired</span>');
        return;
      }

      var days = Math.floor(diff / 86400);
      var hours = Math.floor((diff % 86400) / 3600);
      var minutes = Math.floor((diff % 3600) / 60);
      var seconds = Math.floor(diff % 60);

      var parts = [];
      if (days > 0) parts.push(days + 'd');
      parts.push((hours < 10 ? '0' : '') + hours + 'h');
      parts.push((minutes < 10 ? '0' : '') + minutes + 'm');
      parts.push((seconds < 10 ? '0' : '') + seconds + 's');

      $timer.text(parts.join(' : '));
    }

    updateTimer();
    setInterval(updateTimer, 1000);
  }

  initGuestCountdownTimer();
});