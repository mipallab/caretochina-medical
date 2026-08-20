/* ==========================================================================
   CARETOCHINA MEDICAL STAFF INTERACTIVE JAVASCRIPT ENGINE (LIVE UPDATES)
   ========================================================================== */

function getStaffObj() {
  return (typeof caretochina_staff_obj !== 'undefined') ? caretochina_staff_obj : ((typeof careyou_staff_obj !== 'undefined') ? careyou_staff_obj : { ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });
}

// Global Browser Notification Engine
window.CTC_BrowserNotif = window.CTC_BrowserNotif || {
  init: function() {
    if (!("Notification" in window)) return;
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
        icon: (window.caretochina_staff_obj && window.caretochina_staff_obj.plugin_url) ? window.caretochina_staff_obj.plugin_url + '/assets/images/favicon.png' : '',
        tag: 'ctc-staff-notice-' + Date.now()
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

        if (type === 'booking') {
          // Energetic 3-tone harmonic chime for New Booking: C5 (523Hz) -> E5 (659Hz) -> G5 (784Hz)
          osc.type = 'sine';
          osc.frequency.setValueAtTime(523.25, now);
          osc.frequency.setValueAtTime(659.25, now + 0.11);
          osc.frequency.setValueAtTime(783.99, now + 0.22);
          gain.gain.setValueAtTime(0.18, now);
          gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);
          osc.start(now);
          osc.stop(now + 0.6);
        } else if (type === 'approve' || type === 'action') {
          // Pleasant success tone: E5 (659Hz) -> B5 (987Hz)
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

window.appStaff = {
  activeTab: 'bookings',
  staffBookingId: 0,
  activePatientName: '',
  activePatientCode: '',
  bookingCount: 0,
  isTypingSent: false,
  latestUnreadBookingId: 0,
  latestUnreadPatientName: '',
  latestUnreadBookingCode: '',

  switchTab(button, tabId) {
    jQuery('.staff-tab').removeClass('active');
    jQuery(button).addClass('active');

    jQuery('.staff-panel').removeClass('active').hide();
    jQuery('#staff-panel-' + tabId).addClass('active').show();
    
    this.activeTab = tabId;
  },

  toggleSidebar() {
    const sidebar = jQuery('.staff-sidebar');
    const container = jQuery('.staff-container');
    const btnIcon = jQuery('.staff-sidebar-toggle-btn i');
    
    sidebar.toggleClass('collapsed');
    container.toggleClass('sidebar-collapsed');
    
    if (sidebar.hasClass('collapsed')) {
      btnIcon.removeClass('fa-angles-left').addClass('fa-angles-right');
    } else {
      btnIcon.removeClass('fa-angles-right').addClass('fa-angles-left');
    }
  },

  verifyBooking(id, name, code) {
    var btn = jQuery('tr[data-row-booking-id="' + id + '"] .btn-action-verify');
    var badge = jQuery('#badge-status-' + id);
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');
    badge.html('<i class="fa-solid fa-spinner fa-spin"></i> Confirming...');
    var apiObj = getStaffObj();

    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_verify_booking',
      booking_id: id,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        CTC_Audio.play('approve');
        badge.text('CONFIRMED').css({background: '#D1FAE5', color: '#065F46'});
        btn.prop('disabled', false).html('<i class="fa-solid fa-user-check"></i>').attr('title', 'Verified');
        
        // Refresh sidebar and notifications
        if (typeof appStaff.pollUpdates === 'function') {
          appStaff.pollUpdates();
        }

        // Find sidebar item for patient and switch to live chat
        setTimeout(function() {
          var item = jQuery('.staff-chat-patient-item[data-booking-id="' + id + '"]');
          if (item.length) {
            appStaff.selectPatientChat(item[0], id, name, code);
          } else {
            appStaff.staffBookingId = id;
            appStaff.activePatientName = name;
            appStaff.activePatientCode = code;
            jQuery('#chat-active-patient-name').text(name);
            jQuery('#chat-active-patient-code').text('#' + code);
            jQuery('#staff_chat_booking_id').val(id);
            jQuery('#staff-chat-form').show();
            appStaff.fetchStaffChat();
          }
          var chatTabBtn = jQuery('.staff-tab[onclick*="\'chat\'"]')[0];
          if (chatTabBtn) {
            appStaff.switchTab(chatTabBtn, 'chat');
          }
        }, 400);
      } else {
        badge.text('PENDING').css({background: '#FEF3C7', color: '#92400E'});
        alert(res.data.message || 'Verification failed.');
        btn.prop('disabled', false).html('<i class="fa-solid fa-user-check"></i>');
      }
    }).fail(function() {
      badge.text('PENDING').css({background: '#FEF3C7', color: '#92400E'});
      btn.prop('disabled', false).html('<i class="fa-solid fa-user-check"></i>');
      alert('Network error verifying booking.');
    });
  },

  updateBookingStatus(id, newStatus) {
    var badge = jQuery('#badge-status-' + id);
    badge.html('<i class="fa-solid fa-spinner fa-spin"></i>');
    var apiObj = getStaffObj();

    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_update_booking_status',
      booking_id: id,
      status: newStatus,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        badge.text(res.data.status);
        if (newStatus === 'confirmed') badge.css({background: '#D1FAE5', color: '#065F46'});
        else if (newStatus === 'waiting') badge.css({background: '#FEF3C7', color: '#B45309'});
        else badge.css({background: '#FEE2E2', color: '#991B1B'});
        if (typeof appStaff.pollUpdates === 'function') {
          appStaff.pollUpdates();
        }
      }
    });
  },

  handleNotificationClick(event) {
    if (event) {
      event.stopPropagation();
      event.preventDefault();
    }
    var dropdown = jQuery('#staff-bell-dropdown');
    dropdown.fadeToggle(150);
  },

  selectPatientChat(element, id, name, code) {
    jQuery('.staff-chat-patient-item').removeClass('active').css({background:'', borderLeft:''});
    jQuery(element).addClass('active').css({background:'#CCFBF1', borderLeft:'4px solid #0F766E'});
    
    this.staffBookingId = id;
    this.activePatientName = name;
    this.activePatientCode = code;

    // Show chat form always, update guest badge and payment request button
    var patientId = jQuery(element).data('patient-id');
    var isGuest = (patientId == 0 || jQuery(element).data('is-guest') == 1);
    if (isGuest) {
      jQuery('#chat-active-guest-badge').show();
      jQuery('#staff-chat-req-pay-btn').css({ opacity: '0.45', cursor: 'not-allowed' }).attr('title', 'Payment requests can only be sent to registered patients. Ask guest to register first.');
    } else {
      jQuery('#chat-active-guest-badge').hide();
      jQuery('#staff-chat-req-pay-btn').css({ opacity: '1', cursor: 'pointer' }).attr('title', 'Create & Send Payment Request');
    }
    jQuery('#staff-chat-form').show();
    jQuery('#staff-chat-guest-notice').hide();

    // Update form and UI headers
    jQuery('#staff_chat_booking_id').val(id);
    jQuery('#chat-active-patient-name').text(name);
    jQuery('#chat-active-patient-code').text('#' + code);

    jQuery('#staff_timeline_booking_id').val(id);
    jQuery('#staff_invoice_booking_id').val(id);
    jQuery('#invoice-active-patient-name').text(name);

    // Update selected header status in main title banner
    jQuery('#header-active-case-code').text('#' + code);
    jQuery('#header-active-patient-name').text(name);

    // Load chat right away
    this.fetchStaffChat();

    // Trigger dynamic badge updates instantly
    setTimeout(function() {
      if (typeof appStaff.pollUpdates === 'function') {
        appStaff.pollUpdates();
      }
    }, 500);
  },

  fetchStaffChat() {
    if (!this.staffBookingId || !jQuery('#staff-chat-box').length) return;
    var apiObj = getStaffObj();

    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_get_chat',
      booking_id: this.staffBookingId,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success && res.data) {
        jQuery('#staff-chat-box').html(res.data.html);
        
        // Handle Typing indicator
        var typingInd = jQuery('#staff-chat-typing-indicator');
        if (res.data.is_typing) {
          typingInd.text('Patient is typing...').show();
        } else {
          typingInd.hide().empty();
        }
      }
    });
  },

  playNotificationSound(type) {
    CTC_Audio.play(type || 'message');
  },

  showToastNotification(code, name, type, text) {
    var toastHtml = '';
    if (type === 'booking') {
      toastHtml = `
        <div style="background:#0F766E; color:#FFF; padding:16px 20px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); font-family:'Inter', sans-serif; font-size:14px; display:flex; align-items:center; gap:12px; animation: cymbFade 0.3s ease;">
          <i class="fa-solid fa-calendar-plus" style="font-size:18px; color:#CCFBF1;"></i>
          <div>
            <strong>New Booking Alert!</strong><br>
            Patient: ${name} (#${code})
          </div>
        </div>
      `;
    } else {
      toastHtml = `
        <div style="background:#14B8A6; color:#FFF; padding:16px 20px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); font-family:'Inter', sans-serif; font-size:14px; display:flex; align-items:center; gap:12px; animation: cymbFade 0.3s ease;">
          <i class="fa-solid fa-comment-dots" style="font-size:18px; color:#FFF;"></i>
          <div>
            <strong>New Message from ${name} (#${code})</strong><br>
            <span style="font-style:italic; font-size:12px; opacity:0.9;">"${text}"</span>
          </div>
        </div>
      `;
    }
    var toast = jQuery(toastHtml);
    jQuery('#ctc-staff-toast-container').append(toast);
    
    setTimeout(function() {
      toast.fadeOut(400, function() {
        jQuery(this).remove();
      });
    }, 5000);
  },

  deletePatientData(bookingId) {
    if (!confirm('Are you absolutely sure you want to permanently delete this patient user account, their booking case, and all chat history? This cannot be undone.')) {
      return;
    }
    var apiObj = getStaffObj();
    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_admin_delete_patient_data',
      booking_id: bookingId,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        alert(res.data.message || 'Deleted successfully.');
        appStaff.fetchBookings(1);
        if (appStaff.staffBookingId === bookingId) {
          setTimeout(function() { window.location.reload(); }, 1000);
        }
      } else {
        alert(res.data.message || 'Failed to delete patient data.');
      }
    });
  },

  handleFileSelected(input) {
    if (input.files && input.files[0]) {
      var file = input.files[0];
      if (file.size > 2097152) {
        alert('Attachment file size exceeds the 2MB limit. Please choose a smaller image or PDF.');
        input.value = '';
        jQuery('#staff_attachment_preview').hide();
        return;
      }
      jQuery('#staff_attachment_name').text(file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)');
      jQuery('#staff_attachment_preview').css('display', 'flex');
    }
  },

  clearAttachment() {
    var input = document.getElementById('staff_chat_file_input');
    if (input) input.value = '';
    jQuery('#staff_attachment_preview').hide();
  },

  fetchBookings(page, search) {
    var apiObj = getStaffObj();
    var paged = page || 1;
    var searchQuery = (typeof search !== 'undefined') ? search : (jQuery('#staff-booking-search').val() || '');
    
    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_get_bookings',
      paged: paged,
      search: searchQuery,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        jQuery('#staff-bookings-tbody').html(res.data.html);
        jQuery('#staff-bookings-pagination').html(res.data.pagination);
      }
    });
  },

  changeBookingsPage(page) {
    this.fetchBookings(page);
  },

  searchBookings(query) {
    this.fetchBookings(1, query);
  },

  viewBookingDetails(bookingId) {
    var apiObj = getStaffObj();
    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_get_booking_details',
      booking_id: bookingId,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        var data = res.data;
        jQuery('#view-modal-code').text('#' + data.code);
        
        var detailsHtml = `
          <div><strong>Name:</strong> ${data.name}</div>
          <div><strong>Email:</strong> ${data.email}</div>
          <div><strong>Phone:</strong> ${data.phone}</div>
          <div><strong>Age / Gender:</strong> ${data.age} / ${data.gender}</div>
          <div><strong>Country:</strong> ${data.country}</div>
          <div><strong>Hospital:</strong> ${data.hospital}</div>
          <div><strong>Specialty:</strong> ${data.specialty}</div>
          <div><strong>Treatment Timing:</strong> ${data.timing}</div>
          <div style="grid-column: span 2;"><strong>Social IDs:</strong> WhatsApp: ${data.whatsapp} | WeChat: ${data.wechat} | Messenger: ${data.messenger} | LinkedIn: ${data.linkedin}</div>
          <div style="grid-column: span 2; background:#F8FAFC; padding:12px; border-radius:8px; border:1px solid #E2E8F0; margin-top:8px;"><strong>Medical Quote/Details:</strong><p style="margin:4px 0 0 0; white-space:pre-wrap;">${data.quote}</p></div>
          <div><strong>Current Status:</strong> <span style="font-weight:700; color:#0F766E;">${data.status}</span></div>
          <div><strong>Invoice Status:</strong> ${data.invoice}</div>
        `;
        
        jQuery('#view-modal-content').html(detailsHtml);
        jQuery('#staff-view-booking-modal').css('display', 'flex');
      } else {
        alert(res.data.message || 'Failed to fetch details.');
      }
    });
  },

  toggleRestrictPatient(bookingId, patientId) {
    var btn = jQuery('tr[data-row-booking-id="' + bookingId + '"] .btn-action-restrict');
    var isRestricted = btn.css('background-color') === 'rgb(239, 68, 68)' || btn.css('background-color') === '#EF4444' || btn.attr('title').indexOf('Unrestrict') !== -1;
    
    var reason = '';
    if (!isRestricted) {
      reason = prompt("Enter the reason for restricting this patient's chat feature:");
      if (reason === null) return; // Cancelled
    } else {
      if (!confirm("Are you sure you want to remove the chat restriction for this patient?")) {
        return;
      }
    }
    
    var apiObj = getStaffObj();
    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_toggle_restrict',
      patient_id: patientId,
      reason: reason,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        alert(res.data.message);
        appStaff.fetchBookings(1);
      } else {
        alert(res.data.message || 'Operation failed.');
      }
    });
  }
};

jQuery(document).ready(function($) {
  var apiObj = (typeof caretochina_staff_obj !== 'undefined') ? caretochina_staff_obj : ((typeof careyou_staff_obj !== 'undefined') ? careyou_staff_obj : { ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });
  
  // Set initial variables
  const portalWrapper = $('.caretochina-staff-portal-wrapper, .careyou-staff-portal-wrapper');
  appStaff.staffBookingId = portalWrapper.data('booking-id') || 1;
  appStaff.bookingCount = parseInt(portalWrapper.data('booking-count')) || 0;
  appStaff.latestMessageId = 0;
  
  // Initial bookings fetch for search & pagination
  appStaff.fetchBookings(1);
  
  // Initial chat load
  if ($('#staff-chat-box').length) {
    appStaff.fetchStaffChat();
    // Poll chat every 1 second
    setInterval(function() {
      appStaff.fetchStaffChat();
    }, 1000);
  }

  // 1. POLL FOR BOTH NEW BOOKINGS & UNREAD MESSAGES (EVERY 5 SECONDS)
  appStaff.pollUpdates = function() {
    $.post(apiObj.ajax_url, {
      action: 'caretochina_staff_check_unread_updates',
      active_booking_id: appStaff.staffBookingId || 0,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success && res.data) {
        // Update sidebar badges
        var bookingsBadge = $('#staff-bookings-badge');
        if (res.data.pending_bookings_count > 0) {
          bookingsBadge.text(res.data.pending_bookings_count).show();
        } else {
          bookingsBadge.hide();
        }

        var chatBadge = $('#staff-chat-badge');
        if (res.data.unread_messages_count > 0) {
          chatBadge.text(res.data.unread_messages_count).show();
        } else {
          chatBadge.hide();
        }

        // Update header bell badge
        var totalUnread = res.data.pending_bookings_count + res.data.unread_messages_count;
        var bellBadge = $('#staff-header-bell-badge');
        if (totalUnread > 0) {
          bellBadge.text(totalUnread).show();
        } else {
          bellBadge.hide();
        }

        // Update WordPress Admin menu badge bubble dynamically
        var adminMenuName = jQuery('#toplevel_page_caretochina-staff-desk .wp-menu-name, #toplevel_page_careyou-staff-desk .wp-menu-name');
        if (adminMenuName.length) {
          adminMenuName.find('.awaiting-mod').remove();
          if (totalUnread > 0) {
            adminMenuName.append(' <span class="awaiting-mod update-plugins count-' + totalUnread + '"><span class="plugin-count">' + totalUnread + '</span></span>');
          }
        }

        // Update bell dropdown list
        var dropdownList = jQuery('#staff-bell-dropdown-list');
        var unreadTag = jQuery('#staff-bell-unread-tag');
        if (unreadTag.length) {
          if (totalUnread > 0) {
            unreadTag.text(totalUnread + ' New').show();
          } else {
            unreadTag.hide();
          }
        }
        if (dropdownList.length) {
          if (res.data.dropdown_html) {
            dropdownList.html(res.data.dropdown_html);
          } else if (res.data.unread_items && res.data.unread_items.length > 0) {
            dropdownList.empty();
            res.data.unread_items.forEach(function(item) {
              var icon = item.type === 'booking' ? 'fa-calendar-plus' : 'fa-comments';
              var iconBg = item.type === 'booking' ? '#FEF3C7' : '#CCFBF1';
              var iconColor = item.type === 'booking' ? '#D97706' : '#0F766E';
              var badgeTag = item.type === 'booking'
                ? '<span style="font-size:10px; background:#FEF3C7; color:#92400E; padding:2px 7px; border-radius:6px; font-weight:700; flex-shrink:0;">Approve</span>'
                : '<span style="font-size:10px; background:#CCFBF1; color:#0F766E; padding:2px 7px; border-radius:6px; font-weight:700; flex-shrink:0;">Chat</span>';
              var html = `
                <div class="staff-dropdown-item" onclick="appStaff.handleDropdownItemClick('${item.type}', ${item.id}, '${item.name}', '${item.code}')" style="padding:12px 16px; border-bottom:1px solid #F1F5F9; cursor:pointer; display:flex; gap:12px; align-items:flex-start; transition:background 0.2s;">
                  <div style="width:34px; height:34px; border-radius:50%; background:${iconBg}; color:${iconColor}; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; margin-top:2px;">
                    <i class="fa-solid ${icon}"></i>
                  </div>
                  <div style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-bottom:2px;">
                      <span style="font-weight:700; color:#0F172A; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.title}</span>
                      ${badgeTag}
                    </div>
                    <div style="font-size:12px; color:#64748B; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${item.subtitle || ''}</div>
                    <div style="font-size:11px; color:#94A3B8; margin-top:4px;"><i class="fa-regular fa-clock" style="font-size:10px;"></i> ${item.time}</div>
                  </div>
                </div>
              `;
              dropdownList.append(html);
            });
          } else {
            dropdownList.html('<div style="padding:28px 16px; text-align:center; color:#94A3B8;"><i class="fa-regular fa-bell-slash" style="font-size:26px; margin-bottom:8px; display:block; color:#CBD5E1;"></i> No new notifications</div>');
          }
        }

        // Real-Time Chat Contact List Update (WhatsApp-style snippets and order)
        if (res.data.chat_sidebar_html && $('.staff-chat-patient-list').length) {
          var currentSearch = $('#staff-booking-search').val();
          if (!currentSearch) {
            $('.staff-chat-patient-list').html(res.data.chat_sidebar_html);
          }
        }

        // A. Check for new bookings
        if (res.data.bookings_count !== undefined && res.data.bookings_count !== appStaff.bookingCount) {
          if (appStaff.bookingCount > 0 && res.data.bookings_count > appStaff.bookingCount) {
            appStaff.playNotificationSound('booking');
            appStaff.showToastNotification(res.data.latest_booking_code, res.data.latest_booking_name, 'booking');
            if (window.CTC_BrowserNotif) {
              CTC_BrowserNotif.send('New Patient Booking Alert!', '#' + res.data.latest_booking_code + ' - ' + res.data.latest_booking_name, window.location.href);
            }
          }
          appStaff.bookingCount = res.data.bookings_count;

          // Refresh bookings list table without reload
          $.post(apiObj.ajax_url, {
            action: 'caretochina_staff_get_bookings',
            nonce: apiObj.nonce
          }, function(bRes) {
            if (bRes.success && bRes.data && bRes.data.html) {
              $('#staff-bookings-tbody').html(bRes.data.html);
            }
          });
        }

        // B. Check for new unread messages from patients
        if (res.data.latest_message_id > 0) {
          if (appStaff.latestMessageId > 0 && res.data.latest_message_id > appStaff.latestMessageId) {
            appStaff.latestMessageId = res.data.latest_message_id;
            appStaff.playNotificationSound('message');
            appStaff.showToastNotification(res.data.latest_message_code, res.data.latest_message_sender, 'message', res.data.latest_message_text);
            if (window.CTC_BrowserNotif) {
              CTC_BrowserNotif.send('New Message from ' + res.data.latest_message_sender, '#' + res.data.latest_message_code + ': ' + res.data.latest_message_text, window.location.href);
            }

            if (appStaff.staffBookingId === res.data.latest_message_booking_id) {
              appStaff.fetchStaffChat();
            }
          } else if (appStaff.latestMessageId === 0) {
            appStaff.latestMessageId = res.data.latest_message_id;
          }
        }
      }
    });
  };

  appStaff.handleDropdownItemClick = function(type, id, name, code) {
    if (type === 'message') {
      var item = jQuery('.staff-chat-patient-item[data-booking-id="' + id + '"]');
      if (item.length) {
        appStaff.selectPatientChat(item[0], id, name, code);
      } else {
        appStaff.staffBookingId = id;
        appStaff.activePatientName = name;
        appStaff.activePatientCode = code;
        jQuery('#chat-active-patient-name').text(name);
        jQuery('#chat-active-patient-code').text('#' + code);
        jQuery('#staff_chat_booking_id').val(id);
        jQuery('#staff-chat-form').show();
        appStaff.fetchStaffChat();
      }
      var chatTab = jQuery('.staff-tab[onclick*="\'chat\'"]')[0];
      if (chatTab) {
        appStaff.switchTab(chatTab, 'chat');
      }
    } else {
      var bookingsTab = jQuery('.staff-tab[onclick*="\'bookings\'"]')[0];
      if (bookingsTab) {
        appStaff.switchTab(bookingsTab, 'bookings');
      }
      var row = jQuery('tr[data-row-booking-id="' + id + '"]');
      if (row.length) {
        jQuery('html, body').animate({
          scrollTop: row.offset().top - 150
        }, 500);
        row.css('background', '#FEF3C7');
        setTimeout(function() {
          row.css('background', 'transparent');
        }, 2500);
      }
    }
    jQuery('#staff-bell-dropdown').fadeOut(120);
  };

  // Close notification dropdown when clicking outside
  jQuery(document).on('click', function(e) {
    if (!jQuery(e.target).closest('#staff-header-bell').length) {
      jQuery('#staff-bell-dropdown').fadeOut(120);
    }
  });
  
  if (portalWrapper.length) {
    appStaff.pollUpdates(); // Initial fetch
    setInterval(appStaff.pollUpdates, 5000);
  }

  $(document).on('click', function(e) {
    if (!$(e.target).closest('#staff-header-bell').length) {
      $('#staff-bell-dropdown').hide();
    }
  });

  // STAFF CHAT SUBMISSION (ZERO RELOAD WITH ATTACHMENTS)
  $('#staff-chat-form').on('submit', function(e) {
    e.preventDefault();
    var input = $('#staff_chat_input');
    var fileInput = document.getElementById('staff_chat_file_input');
    var msg = input.val();
    var hasFile = (fileInput && fileInput.files && fileInput.files.length > 0);

    if (!msg && !hasFile) return;

    // Optimistic UI: Append message locally immediately!
    var chatBox = $('#staff-chat-box');
    var safeMsg = $('<div>').text(msg).html();
    var tempMsgId = 'temp-msg-' + Date.now();
    var optimisticHtml = `
      <div class="chat-msg coordinator mb-14 optimistic-msg" id="${tempMsgId}" style="display:flex; justify-content:flex-end; margin-bottom:14px; text-align:right; font-family:'Inter', sans-serif; width:100%; opacity:0.6;">
          <div class="msg-bubble" style="background:#0F766E; color:#FFF; padding:10px 16px; border-radius:18px 18px 2px 18px; font-size:13px; max-width:80%; line-height:1.4; display:inline-block; text-align:left; border:none;">
              ${safeMsg ? safeMsg : '<em>[Uploading attachment...]</em>'} <span style="font-size:11px; font-weight:700; color:#CCFBF1; margin-left:6px;">:Staff</span>
              <div style="font-size:9px; text-align:right; margin-top:4px; opacity:0.8;">
                  <span style="color:#94A3B8; margin-left:6px;"><i class="fa-solid fa-spinner fa-spin"></i> Sending...</span>
              </div>
          </div>
      </div>
    `;
    chatBox.append(optimisticHtml);
    chatBox.scrollTop(chatBox[0].scrollHeight);

    var fd = new FormData(this);
    fd.append('action', 'caretochina_staff_send_chat');
    fd.append('nonce', apiObj.nonce);

    input.val('');
    appStaff.clearAttachment();

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

          if (res.data.chat_sidebar_html && $('.staff-chat-patient-list').length) {
            $('.staff-chat-patient-list').html(res.data.chat_sidebar_html);
          }
        } else {
          $('#' + tempMsgId).css('opacity', '1.0');
          $('#' + tempMsgId + ' .msg-bubble').css('background', '#EF4444');
          $('#' + tempMsgId + ' i').removeClass('fa-spinner fa-spin').addClass('fa-circle-exclamation');
          $('#' + tempMsgId + ' span').text(res.data && res.data.message ? res.data.message : 'Failed to send');
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

  // Keyup listener to send typing signal
  $('#staff_chat_input').on('keyup input', function() {
    if (!appStaff.isTypingSent) {
      appStaff.isTypingSent = true;
      $.post(apiObj.ajax_url, {
        action: 'caretochina_staff_send_typing',
        booking_id: appStaff.staffBookingId,
        nonce: apiObj.nonce
      });
      setTimeout(function() {
        appStaff.isTypingSent = false;
      }, 3000);
    }
  });

  // STAFF TIMELINE SUBMISSION
  $('#staff-timeline-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize() + '&action=caretochina_staff_update_timeline&nonce=' + apiObj.nonce;
    $.post(apiObj.ajax_url, formData, function(res) {
      if (res.success) alert(res.data.message);
    });
  });

  // STAFF INVOICE SUBMISSION
  $('#staff-invoice-form').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize() + '&action=caretochina_staff_update_invoice&nonce=' + apiObj.nonce;
    $.post(apiObj.ajax_url, formData, function(res) {
      if (res.success) {
        $('#staff-invoice-badge').text(res.data.status);
        alert('Invoice Payment Status updated to: ' + res.data.status);
      }
    });
  });

  window.deleteStaffAccount = function(userId) {
    if (!confirm('Are you sure you want to delete this staff account? This action cannot be undone.')) {
      return;
    }
    $.post(apiObj.ajax_url, {
      action: 'caretochina_admin_delete_staff',
      user_id: userId,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        alert(res.data.message);
        window.refreshAdminStaffList();
      } else {
        alert(res.data.message || 'Failed to delete staff account.');
      }
    });
  };

  window.refreshAdminStaffList = function() {
    $.post(apiObj.ajax_url, {
      action: 'caretochina_admin_get_staff',
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success && res.data.html) {
        $('#admin-staff-list-tbody').html(res.data.html);
      }
    });
  };

  /**
   * STAFF PAYMENT REQUEST SYSTEM (Zero Reload)
   */
  window.ctcOpenStaffPaymentReqModal = function() {
    var $activeItem = $('.staff-chat-patient-item.active');
    var patientId = parseInt($activeItem.attr('data-patient-id') || $activeItem.data('patient-id') || 0, 10);
    var isGuest = ($activeItem.attr('data-is-guest') == '1' || $activeItem.data('is-guest') == 1);
    
    if (patientId <= 0 && isGuest) {
      alert('Payment requests can only be sent to registered patient accounts. Guest users cannot receive payment requests. Please request the guest patient to save/register their account first.');
      return;
    }

    var activeId = $('#staff_chat_booking_id').val() || (appStaff ? appStaff.staffBookingId : 0);
    $('#req_modal_booking_id').val(activeId);
    $('#staff-payment-request-modal').css('display', 'flex');
  };

  window.ctcSwitchPricingType = function(type) {
    $('.pricing-sec-box').hide();
    $('#pricing-sec-' + type).show();
    $('.ctc-pricing-opt-label').removeClass('active').css('border-color', '#CBD5E1');
    $('input[name="pricing_type"][value="' + type + '"]').closest('.ctc-pricing-opt-label').addClass('active').css('border-color', '#0F766E');
  };

  $(document).on('change', '#req_treatment_select', function() {
    var treatmentId = $(this).val();
    var $planSelect = $('#req_plan_select');
    var $amountInput = $('#req_plan_amount');

    if (!treatmentId || treatmentId == '0') {
      $planSelect.empty().append('<option value="0">-- Select Specialty First --</option>');
      $amountInput.val('');
      return;
    }

    $planSelect.empty().append('<option value="0">Loading packages...</option>');

    $.get(apiObj.ajax_url, {
      action: 'ctc_get_treatment_plans',
      treatment_id: treatmentId
    }, function(res) {
      $planSelect.empty();
      if (res.success && res.data && res.data.plans && res.data.plans.length > 0) {
        $planSelect.append('<option value="0">-- Select a Pricing Package --</option>');
        res.data.plans.forEach(function(p) {
          var curr = p.currency || 'USD';
          $planSelect.append('<option value="' + p.id + '" data-price="' + p.price + '" data-name="' + p.name + '" data-currency="' + curr + '">' + p.name + ' (' + curr + ' ' + parseFloat(p.price).toFixed(2) + ')</option>');
        });
        $planSelect.val(res.data.plans[0].id).trigger('change');
      } else {
        $planSelect.append('<option value="0">Standard Consultation Deposit</option>');
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

  $(document).on('submit', '#staff-send-payment-request-form', function(e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $('#btn-submit-payment-req');
    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Sending Request...');

    var pricingType = $('input[name="pricing_type"]:checked').val();
    var postData = {
      action: 'ctc_create_payment_request',
      nonce: apiObj.nonce,
      booking_id: $('#req_modal_booking_id').val() || appStaff.staffBookingId,
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
      url: apiObj.ajax_url,
      method: 'POST',
      data: postData,
      success: function(res) {
        $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Send Request to Patient');
        if (res.success) {
          $('#staff-payment-request-modal').hide();
          $form[0].reset();
          if (typeof appStaff.fetchStaffChat === 'function') {
            appStaff.fetchStaffChat();
          }
          if (typeof appStaff.pollUpdates === 'function') {
            appStaff.pollUpdates();
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
});