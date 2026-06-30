/**
 * Shared CRM layout UI: CSRF ajaxSetup, topbar scroll, sidebar state, dropdown menus.
 * Expects jQuery (CDN in layout head). Reads data-crm-layout on <body>: "detail" | "dashboard".
 */

(function () {
    function initTopbarScrollHide() {
        var lastY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var ticking = false;
        var $topbar = typeof jQuery !== 'undefined' ? jQuery('.main-topbar') : null;
        if (!$topbar || !$topbar.length) {
            return;
        }

        function update() {
            var currentY = window.pageYOffset || document.documentElement.scrollTop || 0;
            var atTop = currentY <= 0;
            var scrollingDown = currentY > lastY && !atTop;

            if (scrollingDown) {
                if (!$topbar.hasClass('is-hidden')) {
                    $topbar.addClass('is-hidden');
                    document.body.classList.add('topbar-hidden');
                }
            } else if ($topbar.hasClass('is-hidden') || atTop) {
                $topbar.removeClass('is-hidden');
                document.body.classList.remove('topbar-hidden');
            }

            lastY = currentY;
            ticking = false;
        }

        function requestTick() {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(update);
            }
        }

        jQuery(function () {
            if ((window.pageYOffset || document.documentElement.scrollTop || 0) > 0) {
                $topbar.addClass('is-hidden');
                document.body.classList.add('topbar-hidden');
            }
        });

        window.addEventListener('scroll', requestTick, { passive: true });
    }

    function initSidebarAndDropdowns() {
        var layout = document.body.getAttribute('data-crm-layout') || 'detail';
        var $ = jQuery;

        if (layout === 'detail') {
            $('.collapse-btn').on('click', function (e) {
                e.preventDefault();
                $('body').addClass('sidebar-mini');
                $('.main-sidebar').removeClass('sidebar-expanded');
                $('.main-content').css('margin-left', '80px');
                localStorage.setItem('sidebarState', 'collapsed');
            });

            $('body').addClass('sidebar-mini');
            $('.main-sidebar').removeClass('sidebar-expanded');
            $('.main-content').css('margin-left', '80px');
            localStorage.setItem('sidebarState', 'collapsed');

            $('.main-sidebar').css({
                position: 'fixed',
                top: '70px',
                left: '0',
                zIndex: '999',
            });

            initTopbarScrollHide();
        } else {
            $('body').addClass('sidebar-mini');
            $('.main-sidebar').removeClass('sidebar-expanded');
            $('.main-content').css('margin-left', '0');
            localStorage.setItem('sidebarState', 'hidden');
        }

        var $topbar = $('.main-topbar');
        if ($topbar.length) {
            $topbar.removeClass('is-collapsed');
            localStorage.removeItem('topbarCollapsed');
            $(document).off('click', '.topbar-toggle');
        }

        $(document).on('click', '.js-dropdown > .icon-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $menu = $(this).siblings('.icon-dropdown-menu');
            $('.icon-dropdown-menu').not($menu).removeClass('show');
            $menu.toggleClass('show');
        });

        $(document).on('click', '.js-dropdown-right > .profile-trigger', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $menu = $(this).siblings('.profile-menu');
            $('.profile-menu').not($menu).removeClass('show');
            $menu.toggleClass('show');
        });

        $(document).on('click', function () {
            $('.icon-dropdown-menu').removeClass('show');
            $('.profile-menu').removeClass('show');
        });
    }

    jQuery(function () {
        jQuery.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content'),
            },
        });

        jQuery('.tel_input').on('blur', function () {
            this.value = this.value;
        });

        initSidebarAndDropdowns();
    });
})();
