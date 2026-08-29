/**
 * CareToChina Medical Concierge - Dynamic Modular Pricing Script
 * Universal theme synchronization, background color luminance detection, and tab switcher.
 */
(function() {
  'use strict';

  var STORAGE_KEYS = [
    'careyou_theme',
    'caretochina_pricing_theme',
    'caretochina_theme',
    'theme',
    'dark_mode',
    'ctc_theme',
    'wp_dark_mode',
    'theme_mode',
    'color_scheme',
    'mode',
    'selected-theme',
    'night_mode',
    'dracula_mode',
    'site_theme',
    'theme-preference',
    'color-theme'
  ];

  /**
   * Helper: Calculate perceived luminance of an element's background color
   * Returns a value between 0 (black) and 255 (white), or null if transparent
   */
  function getElementBgLuminance(el) {
    if (!el) return null;
    try {
      var bg = window.getComputedStyle(el).backgroundColor;
      if (!bg || bg === 'transparent' || bg === 'rgba(0, 0, 0, 0)') {
        return null;
      }
      var rgb = bg.match(/\d+/g);
      if (rgb && rgb.length >= 3) {
        var r = parseInt(rgb[0], 10);
        var g = parseInt(rgb[1], 10);
        var b = parseInt(rgb[2], 10);
        // ITU-R BT.709 relative luminance formula
        return (0.2126 * r + 0.7152 * g + 0.0722 * b);
      }
    } catch (e) {}
    return null;
  }

  /**
   * Universal theme detection
   * Multi-strategy: CareYou Theme Engine, Class names, Data Attributes, LocalStorage, Background Luminance
   */
  function isSiteInDarkMode() {
    var b = document.body;
    var h = document.documentElement;

    // 1. Direct CareYou Theme Engine check (html.dark-theme / body.dark-theme)
    if (h && h.classList.contains('dark-theme')) {
      return true;
    }
    if (b && b.classList.contains('dark-theme')) {
      return true;
    }

    // 2. Direct class check on <body>
    if (b) {
      var bClass = b.className || '';
      if (/(^|\s)(dark|dark-mode|dark-theme|dark_mode|night-mode|is-dark|theme-dark|wp-dark-mode-active|dracula-dark-mode|elementor-theme-dark)(\s|$)/i.test(bClass)) {
        return true;
      }
      var bTheme = b.getAttribute('data-theme') || b.getAttribute('data-color-scheme') || b.getAttribute('data-bs-theme') || b.getAttribute('data-color-mode') || b.getAttribute('data-mode') || b.getAttribute('data-theme-mode');
      if (bTheme && bTheme.toLowerCase().indexOf('dark') !== -1) {
        return true;
      }
    }

    // 3. Direct class check on <html> (documentElement)
    if (h) {
      var hClass = h.className || '';
      if (/(^|\s)(dark|dark-mode|dark-theme|dark_mode|night-mode|is-dark|theme-dark|wp-dark-mode-active)(\s|$)/i.test(hClass)) {
        return true;
      }
      var hTheme = h.getAttribute('data-theme') || h.getAttribute('data-color-scheme') || h.getAttribute('data-bs-theme') || h.getAttribute('data-color-mode') || h.getAttribute('data-mode') || h.getAttribute('data-theme-mode');
      if (hTheme && hTheme.toLowerCase().indexOf('dark') !== -1) {
        return true;
      }
    }

    // 3. Check common main wrappers (#page, .site, #wrapper)
    var mainWrapper = document.querySelector('#page, .site, #wrapper, .main-wrapper, #main-content');
    if (mainWrapper) {
      var mClass = mainWrapper.className || '';
      if (/(^|\s)(dark|dark-mode|dark-theme|night-mode|is-dark)(\s|$)/i.test(mClass)) {
        return true;
      }
      var mTheme = mainWrapper.getAttribute('data-theme') || mainWrapper.getAttribute('data-color-scheme');
      if (mTheme && mTheme.toLowerCase().indexOf('dark') !== -1) {
        return true;
      }
    }

    // 4. Check LocalStorage across common theme keys
    for (var i = 0; i < STORAGE_KEYS.length; i++) {
      try {
        var val = localStorage.getItem(STORAGE_KEYS[i]);
        if (val) {
          val = String(val).toLowerCase().trim();
          if (val === 'dark' || val === 'true' || val === '1' || val === 'night') {
            return true;
          }
          if (val === 'light' || val === 'false' || val === '0' || val === 'day') {
            return false;
          }
        }
      } catch (e) {}
    }

    // 5. Computed Background Color Luminance of body or html
    var bodyLum = getElementBgLuminance(b);
    if (bodyLum !== null) {
      if (bodyLum < 125) return true;
      if (bodyLum > 140) return false;
    }
    var htmlLum = getElementBgLuminance(h);
    if (htmlLum !== null) {
      if (htmlLum < 125) return true;
      if (htmlLum > 140) return false;
    }

    // 6. System preference fallback (only if site does not explicitly declare light)
    var isExplicitLight = false;
    if (b && /(^|\s)(light|light-mode|light-theme)(\s|$)/i.test(b.className || '')) isExplicitLight = true;
    if (h && /(^|\s)(light|light-mode|light-theme)(\s|$)/i.test(h.className || '')) isExplicitLight = true;

    if (!isExplicitLight && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return true;
    }

    return false;
  }

  function applyTheme(wrapper, isDark) {
    if (!wrapper) return;

    var toggleBtn = wrapper.querySelector('.ctc-theme-toggle-btn');
    var iconSpan = toggleBtn ? toggleBtn.querySelector('.toggle-icon') : null;
    var textSpan = toggleBtn ? toggleBtn.querySelector('.toggle-text') : null;

    wrapper.setAttribute('data-theme', isDark ? 'dark' : 'light');

    if (isDark) {
      wrapper.classList.add('ctc-theme-dark');
      wrapper.classList.remove('ctc-theme-light');
      if (iconSpan) iconSpan.innerHTML = '<i class="fas fa-sun"></i>';
      if (textSpan) textSpan.textContent = 'Light Mode';
    } else {
      wrapper.classList.remove('ctc-theme-dark');
      wrapper.classList.add('ctc-theme-light');
      if (iconSpan) iconSpan.innerHTML = '<i class="fas fa-moon"></i>';
      if (textSpan) textSpan.textContent = 'Dark Mode';
    }
  }

  function syncAllScopes() {
    var isDark = isSiteInDarkMode();
    var scopes = document.querySelectorAll('.ctc-pricing-scope, .ctc-pricing-page-wrapper, .ctc-pricing-cards-wrapper, .ctc-pricing-compare-wrapper, .ctc-pricing-details-wrapper');
    for (var i = 0; i < scopes.length; i++) {
      applyTheme(scopes[i], isDark);
    }
  }

  function initThemeSwitcher(wrapper) {
    if (!wrapper) return;

    var initialDark = isSiteInDarkMode();
    applyTheme(wrapper, initialDark);

    var toggleBtn = wrapper.querySelector('.ctc-theme-toggle-btn');
    if (toggleBtn && !toggleBtn.dataset.themeBound) {
      toggleBtn.dataset.themeBound = 'true';
      toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var currentIsDark = wrapper.getAttribute('data-theme') === 'dark' || wrapper.classList.contains('ctc-theme-dark');
        var nextIsDark = !currentIsDark;

        applyTheme(wrapper, nextIsDark);

        if (document.body) {
          if (nextIsDark) {
            document.body.classList.add('dark-mode');
          } else {
            document.body.classList.remove('dark-mode');
          }
        }

        for (var i = 0; i < STORAGE_KEYS.length; i++) {
          try {
            localStorage.setItem(STORAGE_KEYS[i], nextIsDark ? 'dark' : 'light');
          } catch (err) {}
        }
      });
    }
  }

  /**
   * Switch Bento Details Tab (Universal, works in any wrapper or globally)
   */
  function switchTab(targetKey, specificContext) {
    if (!targetKey) return;

    var context = specificContext || document;
    var buttons = context.querySelectorAll('.p2-tab-pill-btn');
    var panes = context.querySelectorAll('.p2-tab-pane');

    // If context search found nothing, search whole document
    if (buttons.length === 0 || panes.length === 0) {
      buttons = document.querySelectorAll('.p2-tab-pill-btn');
      panes = document.querySelectorAll('.p2-tab-pane');
    }

    // Update active tab buttons
    for (var i = 0; i < buttons.length; i++) {
      var btn = buttons[i];
      var key = btn.getAttribute('data-tab-target');
      if (key === targetKey || String(key) === String(targetKey)) {
        btn.classList.add('p2-active-tab');
        btn.setAttribute('aria-selected', 'true');
      } else {
        btn.classList.remove('p2-active-tab');
        btn.setAttribute('aria-selected', 'false');
      }
    }

    // Update active panes
    for (var j = 0; j < panes.length; j++) {
      var pane = panes[j];
      var paneKey = pane.getAttribute('data-tab-id');
      if (paneKey === targetKey || String(paneKey) === String(targetKey)) {
        pane.style.display = 'block';
        pane.classList.add('active-pane');
      } else {
        pane.style.display = 'none';
        pane.classList.remove('active-pane');
      }
    }
  }

  // Global helper exposure
  window.p2SwitchTab = function(targetKey) {
    switchTab(targetKey);
  };
  window.ctcSwitchPricingTab = function(targetKey, wrapperId) {
    var wrapper = wrapperId ? document.getElementById(wrapperId) : null;
    switchTab(targetKey, wrapper);
  };

  /**
   * Delegated click listener for tab buttons, card links, and theme toggle buttons across document
   */
  function bindDelegatedListeners() {
    document.addEventListener('click', function(e) {
      // 1. Check if a tab button was clicked
      var tabBtn = e.target.closest('.p2-tab-pill-btn');
      if (tabBtn) {
        var targetKey = tabBtn.getAttribute('data-tab-target');
        if (targetKey) {
          e.preventDefault();
          var container = tabBtn.closest('.ctc-pricing-scope, .ctc-pricing-details-wrapper, .ctc-pricing-page-wrapper');
          switchTab(targetKey, container);
        }
        return;
      }

      // 2. Check if a card's "View Plan Details" button was clicked
      var planBtn = e.target.closest('.btn-plan[data-target-tab]');
      if (planBtn) {
        var planTarget = planBtn.getAttribute('data-target-tab');
        if (planTarget) {
          switchTab(planTarget);
          var detailsEl = document.getElementById('details') || document.querySelector('.details-section, .ctc-pricing-details-wrapper');
          if (detailsEl) {
            e.preventDefault();
            detailsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
        return;
      }

      // 3. Check if CareToChina theme toggle button was clicked
      var cyToggle = e.target.closest('#careyou-theme-toggle, .theme-toggle-btn, .cy-toggler-darklight');
      if (cyToggle) {
        setTimeout(syncAllScopes, 10);
        setTimeout(syncAllScopes, 50);
        setTimeout(syncAllScopes, 150);
        setTimeout(syncAllScopes, 350);
        return;
      }

      // 4. Fallback multi-interval sync for other elements
      setTimeout(syncAllScopes, 50);
      setTimeout(syncAllScopes, 200);
    });
  }

  /**
   * Deep Link Hash Resolver
   */
  function checkUrlHash() {
    var hash = window.location.hash;
    if (hash) {
      var clean = hash.replace('#', '');
      var matchingBtn = document.querySelector('.p2-tab-pill-btn[data-tab-target="' + clean + '"]');
      if (matchingBtn) {
        var container = matchingBtn.closest('.ctc-pricing-scope, .ctc-pricing-details-wrapper, .ctc-pricing-page-wrapper');
        switchTab(clean, container);
      }
    }
  }

  /**
   * Global theme observer (Observes HTML & Body for classes, attributes and style changes)
   */
  function setupGlobalThemeObserver() {
    if (!window.MutationObserver) return;

    var observer = new MutationObserver(function() {
      syncAllScopes();
    });

    var obsConfig = { 
      attributes: true, 
      childList: false,
      subtree: false,
      attributeFilter: ['class', 'data-theme', 'data-color-scheme', 'data-bs-theme', 'data-color-mode', 'data-mode', 'data-theme-mode', 'style'] 
    };

    if (document.body) {
      observer.observe(document.body, obsConfig);
    }
    if (document.documentElement) {
      observer.observe(document.documentElement, obsConfig);
    }
  }

  /**
   * DOM Ready Initialization
   */
  function initAll() {
    var scopes = document.querySelectorAll('.ctc-pricing-scope, .ctc-pricing-page-wrapper, .ctc-pricing-cards-wrapper, .ctc-pricing-compare-wrapper, .ctc-pricing-details-wrapper');
    for (var i = 0; i < scopes.length; i++) {
      initThemeSwitcher(scopes[i]);
    }

    bindDelegatedListeners();
    checkUrlHash();
    setupGlobalThemeObserver();

    // Storage event for cross-tab or external theme toggle sync
    window.addEventListener('storage', function(e) {
      if (STORAGE_KEYS.indexOf(e.key) !== -1) {
        syncAllScopes();
      }
    });

    // Re-check after full window load for themes that apply dark mode late
    window.addEventListener('load', function() {
      syncAllScopes();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  window.addEventListener('hashchange', function() {
    checkUrlHash();
  });

})();
