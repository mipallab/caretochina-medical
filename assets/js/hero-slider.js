/**
 * CareToChina Hero Hospital Slider Carousel Initializer
 *
 * @package CareToChina_Medical
 */

(function () {
    'use strict';

    function initHeroSliders(context) {
        var root = context || document;
        var sliderWrappers = root.querySelectorAll('.ctc-hero-slider-wrap');

        if (!sliderWrappers.length) {
            return;
        }

        sliderWrappers.forEach(function (wrapper) {
            // Avoid duplicate initializations
            if (wrapper.dataset.sliderInitialized === 'true') {
                return;
            }

            var swiperContainer = wrapper.querySelector('.swiper.ctc-hero-slider');
            if (!swiperContainer) {
                return;
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

            if (typeof Swiper !== 'undefined') {
                try {
                    new Swiper(swiperContainer, swiperOptions);
                    wrapper.dataset.sliderInitialized = 'true';
                } catch (err) {
                    console.error('[Hero Slider] Failed to initialize Swiper:', err);
                }
            }
        });
    }

    // Standard DOM ready initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initHeroSliders(document);
        });
    } else {
        initHeroSliders(document);
    }

    // Elementor & Dynamic Content Compatibility
    window.addEventListener('load', function () {
        initHeroSliders(document);
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('elementor/popup/show ajaxComplete', function () {
            initHeroSliders(document);
        });

        // Elementor Frontend Hook
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
