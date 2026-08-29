/**
 * CareToChina Hero Hospital Slider Carousel Initializer
 * Optimized for WP Rocket (Delay JS, Defer JS, Combine JS) & Elementor
 *
 * @package CareToChina_Medical
 */

(function () {
    'use strict';

    var retryTimer = null;
    var retryCount = 0;
    var maxRetries = 100; // 100 * 100ms = 10 seconds max retry

    function initHeroSliders(context) {
        var root = context || document;
        var sliderWrappers = root.querySelectorAll('.ctc-hero-slider-wrap');

        if (!sliderWrappers.length) {
            return;
        }

        if (typeof Swiper === 'undefined') {
            // Swiper script might be delayed by WP Rocket; schedule retry
            scheduleSwiperRetry(context);
            return;
        }

        sliderWrappers.forEach(function (wrapper) {
            // Avoid duplicate initializations
            if (wrapper.dataset.sliderInitialized === 'true' && wrapper.swiperInstance) {
                return;
            }

            var swiperContainer = wrapper.querySelector('.swiper.ctc-hero-slider') || wrapper.querySelector('.ctc-hero-slider');
            if (!swiperContainer) {
                return;
            }

            // Destroy existing instance if re-initializing
            if (swiperContainer.swiper) {
                try {
                    swiperContainer.swiper.destroy(true, true);
                } catch (e) {}
            }

            var configAttr = wrapper.getAttribute('data-slider-config');
            var config = {};
            try {
                if (configAttr) {
                    config = JSON.parse(configAttr);
                }
            } catch (e) {
                console.error('[Hero Slider] Config parse error:', e);
            }

            var slidesCount = swiperContainer.querySelectorAll('.swiper-slide').length;
            var shouldLoop = Boolean(config.loop) && slidesCount > 1;

            var swiperOptions = {
                slidesPerView: 1,
                spaceBetween: 0,
                speed: config.speed || 600,
                loop: shouldLoop,
                grabCursor: true,
                watchSlidesProgress: true,
                observer: true,
                observeParents: true,
                keyboard: {
                    enabled: true,
                    onlyInViewport: true,
                },
                effect: config.effect === 'fade' ? 'fade' : 'slide',
            };

            if (config.effect === 'fade') {
                swiperOptions.fadeEffect = {
                    crossFade: true,
                };
            }

            // Autoplay Configuration
            if (config.autoplay && slidesCount > 1) {
                swiperOptions.autoplay = {
                    delay: config.autoplay.delay || 4500,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: Boolean(config.autoplay.pauseOnMouseEnter),
                };
            } else {
                swiperOptions.autoplay = false;
            }

            // Left & Right Navigation Arrows
            if (config.show_arrows) {
                var prevBtn = wrapper.querySelector('.ctc-slider-prev');
                var nextBtn = wrapper.querySelector('.ctc-slider-next');
                if (prevBtn && nextBtn) {
                    swiperOptions.navigation = {
                        prevEl: prevBtn,
                        nextEl: nextBtn,
                    };
                }
            }

            // Bottom Dot Navigator (Pagination)
            if (config.show_dots) {
                var paginationEl = wrapper.querySelector('.ctc-slider-pagination');
                if (paginationEl) {
                    swiperOptions.pagination = {
                        el: paginationEl,
                        clickable: true,
                        renderBullet: function (index, className) {
                            return '<span class="' + className + '" role="button" aria-label="Go to slide ' + (index + 1) + '" tabindex="0"></span>';
                        },
                    };
                }
            }

            try {
                wrapper.swiperInstance = new Swiper(swiperContainer, swiperOptions);
                wrapper.dataset.sliderInitialized = 'true';
            } catch (err) {
                console.error('[Hero Slider] Failed to initialize Swiper:', err);
            }
        });
    }

    function scheduleSwiperRetry(context) {
        if (retryTimer) return;
        retryTimer = setInterval(function () {
            retryCount++;
            if (typeof Swiper !== 'undefined') {
                clearInterval(retryTimer);
                retryTimer = null;
                initHeroSliders(context);
            } else if (retryCount >= maxRetries) {
                clearInterval(retryTimer);
                retryTimer = null;
            }
        }, 100);
    }

    // Expose global initializer for dynamic scripts / AJAX
    window.ctcInitHeroSliders = function (ctx) {
        initHeroSliders(ctx || document);
    };

    // Standard DOM ready initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initHeroSliders(document);
        });
    } else {
        initHeroSliders(document);
    }

    // Window load fallback
    window.addEventListener('load', function () {
        initHeroSliders(document);
    });

    // WP Rocket User Interaction Un-delay Triggers
    var interactionEvents = ['scroll', 'mousemove', 'touchstart', 'click', 'keydown'];
    var onUserInteraction = function () {
        initHeroSliders(document);
        interactionEvents.forEach(function (evt) {
            window.removeEventListener(evt, onUserInteraction, { capture: true, passive: true });
        });
    };
    interactionEvents.forEach(function (evt) {
        window.addEventListener(evt, onUserInteraction, { capture: true, passive: true });
    });

    // Elementor & Dynamic Content Compatibility
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('elementor/popup/show ajaxComplete', function () {
            initHeroSliders(document);
        });

        jQuery(window).on('elementor/frontend/init', function () {
            if (window.elementorFrontend && window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
                    if ($scope && $scope[0]) {
                        initHeroSliders($scope[0]);
                    }
                });
            }
        });
    }
})();
