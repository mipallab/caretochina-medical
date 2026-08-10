/* ==========================================================================
   CARETOCHINA MEDICAL STAFF INTERACTIVE JAVASCRIPT ENGINE (LIVE UPDATES)
   ========================================================================== */

function getStaffObj() {
  return (typeof caretochina_staff_obj !== 'undefined') ? caretochina_staff_obj : ((typeof careyou_staff_obj !== 'undefined') ? careyou_staff_obj : { ajax_url: '/wp-admin/admin-ajax.php', nonce: '' });
}

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
    btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Verifying...');
    var apiObj = getStaffObj();

    jQuery.post(apiObj.ajax_url, {
      action: 'caretochina_staff_verify_booking',
      booking_id: id,
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
        // Update status badge
        var badge = jQuery('#badge-status-' + id);
        badge.text('CONFIRMED').css({background: '#D1FAE5', color: '#065F46'});
        
        // Find sidebar item for patient
        setTimeout(function() {
          var item = jQuery('.staff-chat-patient-item[data-booking-id="' + id + '"]');
          if (item.length) {
            appStaff.selectPatientChat(item[0], id, name, code);
          }
          // Redirect to chat tab without reloading
          appStaff.switchTab(jQuery('.staff-tab[onclick*="\'chat\'"]')[0], 'chat');
        }, 800);
      } else {
        alert(res.data.message || 'Verification failed.');
        btn.prop('disabled', false).html('<i class="fa-solid fa-user-check"></i> Verify & Chat');
      }
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
      }
    });
  },

  handleNotificationClick() {
    if (this.latestUnreadBookingId > 0) {
      var self = this;
      var item = jQuery('.staff-chat-patient-item[data-booking-id="' + this.latestUnreadBookingId + '"]');
      if (item.length) {
        this.selectPatientChat(item[0], this.latestUnreadBookingId, this.latestUnreadPatientName, this.latestUnreadBookingCode);
      }
      this.switchTab(jQuery('.staff-tab[onclick*="\'chat\'"]')[0], 'chat');
      
      // Instantly clear badges locally for snappy response
      this.latestUnreadBookingId = 0;
      jQuery('#staff-header-bell-badge').hide();
      jQuery('#staff-chat-badge').hide();
      
      var totalBadge = jQuery('#staff-header-bell-badge');
      var totalCount = parseInt(totalBadge.text()) - 1;
      if (totalCount > 0) {
        totalBadge.text(totalCount).show();
      } else {
        totalBadge.hide();
      }
    } else {
      this.switchTab(jQuery('.staff-tab[onclick*="\'bookings\'"]')[0], 'bookings');
    }
  },

  selectPatientChat(element, id, name, code) {
    jQuery('.staff-chat-patient-item').removeClass('active').css({background:'', borderLeft:''});
    jQuery(element).addClass('active').css({background:'#CCFBF1', borderLeft:'4px solid #0F766E'});
    
    this.staffBookingId = id;
    this.activePatientName = name;
    this.activePatientCode = code;

    // Hide/show chat form based on guest status
    var patientId = jQuery(element).data('patient-id');
    if (patientId == 0) {
      jQuery('#staff-chat-form').hide();
      jQuery('#staff-chat-guest-notice').show();
    } else {
      jQuery('#staff-chat-form').show();
      jQuery('#staff-chat-guest-notice').hide();
    }

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

  playNotificationSound() {
    try {
      var context = new (window.AudioContext || window.webkitAudioContext)();
      var osc = context.createOscillator();
      var gain = context.createGain();
      osc.connect(gain);
      gain.connect(context.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, context.currentTime); // D5
      osc.frequency.setValueAtTime(880, context.currentTime + 0.15); // A5
      gain.gain.setValueAtTime(0.1, context.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.6);
      osc.start(context.currentTime);
      osc.stop(context.currentTime + 0.6);
    } catch(e) {}
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
        jQuery.post(apiObj.ajax_url, {
          action: 'caretochina_staff_get_bookings',
          nonce: apiObj.nonce
        }, function(res) {
          if (res.success && res.data.html) {
            jQuery('#staff-bookings-tbody').html(res.data.html);
          }
        });
        if (appStaff.staffBookingId === bookingId) {
          setTimeout(function() { window.location.reload(); }, 1000);
        }
      } else {
        alert(res.data.message || 'Failed to delete patient data.');
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
      nonce: apiObj.nonce
    }, function(res) {
      if (res.success) {
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

        // Update WordPress Admin menu badge bubble dynamically (fixed specific menu class targeting body bug)
        var adminMenuName = jQuery('#toplevel_page_caretochina-staff-desk .wp-menu-name, #toplevel_page_careyou-staff-desk .wp-menu-name');
        if (adminMenuName.length) {
          adminMenuName.find('.awaiting-mod').remove();
          if (totalUnread > 0) {
            adminMenuName.append(' <span class="awaiting-mod update-plugins count-' + totalUnread + '"><span class="plugin-count">' + totalUnread + '</span></span>');
          }
        }

        // Update bell dropdown list
        var dropdownList = jQuery('#staff-bell-dropdown-list');
        if (dropdownList.length) {
          dropdownList.empty();
          if (res.data.unread_items && res.data.unread_items.length > 0) {
            res.data.unread_items.forEach(function(item) {
              var icon = item.type === 'booking' ? 'fa-calendar-plus' : 'fa-comments';
              var color = item.type === 'booking' ? '#0F766E' : '#14B8A6';
              var html = `
                <div class="staff-dropdown-item" onclick="appStaff.handleDropdownItemClick('${item.type}', ${item.id}, '${item.name}', '${item.code}')" style="padding:10px 16px; border-bottom:1px solid #F1F5F9; cursor:pointer; display:flex; gap:10px; transition:background 0.2s;">
                  <div style="color:${color}; font-size:14px; margin-top:2px;"><i class="fa-solid ${icon}"></i></div>
                  <div style="flex:1;">
                    <div style="font-weight:600; color:#1E293B; line-height:1.2;">${item.title}</div>
                    <div style="font-size:10px; color:#94A3B8; margin-top:3px;">${item.time}</div>
                  </div>
                </div>
              `;
              dropdownList.append(html);
            });
            jQuery('.staff-dropdown-item').hover(
              function() { jQuery(this).css('background', '#F8FAFC'); },
              function() { jQuery(this).css('background', 'transparent'); }
            );
          } else {
            dropdownList.html('<div style="padding:20px; text-align:center; color:#94A3B8;">No new notifications</div>');
          }
        }

        // A. Check for new bookings
        if (appStaff.bookingCount > 0 && res.data.bookings_count > appStaff.bookingCount) {
          appStaff.bookingCount = res.data.bookings_count;
          appStaff.playNotificationSound();
          appStaff.showToastNotification(res.data.latest_booking_code, res.data.latest_booking_name, 'booking');

          // Refresh bookings list table
          $.post(apiObj.ajax_url, {
            action: 'caretochina_staff_get_bookings',
            nonce: apiObj.nonce
          }, function(res) {
            if (res.success && res.data.html) {
              $('#staff-bookings-tbody').html(res.data.html);
            }
          });
        } else if (appStaff.bookingCount === 0) {
          appStaff.bookingCount = res.data.bookings_count;
        }

        // B. Check for new unread messages from patients
        if (res.data.latest_message_id > 0) {
          if (appStaff.latestMessageId > 0 && res.data.latest_message_id > appStaff.latestMessageId) {
            appStaff.latestMessageId = res.data.latest_message_id;
            appStaff.playNotificationSound();
            appStaff.showToastNotification(res.data.latest_message_code, res.data.latest_message_sender, 'message', res.data.latest_message_text);

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
      }
      appStaff.switchTab(jQuery('.staff-tab[onclick*="\'chat\'"]')[0], 'chat');
    } else {
      appStaff.switchTab(jQuery('.staff-tab[onclick*="\'bookings\'"]')[0], 'bookings');
      var row = jQuery('tr[data-row-booking-id="' + id + '"]');
      if (row.length) {
        jQuery('html, body').animate({
          scrollTop: row.offset().top - 150
        }, 500);
        row.css('background', '#FFE4E6');
        setTimeout(function() {
          row.css('background', 'transparent');
        }, 2000);
      }
    }
    jQuery('#staff-bell-dropdown').hide();
  };
  
  if (portalWrapper.length) {
    appStaff.pollUpdates(); // Initial fetch
    setInterval(appStaff.pollUpdates, 5000);
  }

  $(document).on('click', function(e) {
    if (!$(e.target).closest('#staff-header-bell').length) {
      $('#staff-bell-dropdown').hide();
    }
  });

  // STAFF CHAT SUBMISSION (ZERO RELOAD)
  $('#staff-chat-form').on('submit', function(e) {
    e.preventDefault();
    var input = $('#staff_chat_input');
    var msg = input.val();

    if (!msg) return;

    // Optimistic UI: Append message locally immediately!
    var chatBox = $('#staff-chat-box');
    var safeMsg = $('<div>').text(msg).html();
    var tempMsgId = 'temp-msg-' + Date.now();
    var optimisticHtml = `
      <div class="chat-msg coordinator mb-14 optimistic-msg" id="${tempMsgId}" style="display:flex; justify-content:flex-end; margin-bottom:14px; text-align:right; font-family:'Inter', sans-serif; width:100%; opacity:0.6;">
          <div class="msg-bubble" style="background:#0F766E; color:#FFF; padding:10px 16px; border-radius:18px 18px 2px 18px; font-size:13px; max-width:80%; line-height:1.4; display:inline-block; text-align:left; border:none;">
              ${safeMsg} <span style="font-size:11px; font-weight:700; color:#CCFBF1; margin-left:6px;">:Staff</span>
              <div style="font-size:9px; text-align:right; margin-top:4px; opacity:0.8;">
                  <span style="color:#94A3B8; margin-left:6px;"><i class="fa-solid fa-spinner fa-spin"></i> Sending...</span>
              </div>
          </div>
      </div>
    `;
    chatBox.append(optimisticHtml);
    chatBox.scrollTop(chatBox[0].scrollHeight);

    var formData = $(this).serialize() + '&action=caretochina_staff_send_chat&nonce=' + apiObj.nonce;

    input.val('');

    $.post(apiObj.ajax_url, formData, function(res) {
      if (res.success && res.data && res.data.html) {
        chatBox.html(res.data.html);
        chatBox.scrollTop(chatBox[0].scrollHeight);
      } else {
        // If it fails, highlight error
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
});