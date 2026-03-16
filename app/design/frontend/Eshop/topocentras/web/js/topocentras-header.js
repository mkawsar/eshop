/**
 * Theme behavior glue for Topocentras-style header.
 *
 * - If the homepage sidebar catalog exists, clicking "Prekių katalogas" toggles it.
 * - Otherwise, the link behaves as a normal navigation link.
 */
define(['jquery'], function ($) {
  'use strict';

  var BODY_OPEN_CLASS = 'topocentras-catalog-open';
  var MENU_OPEN_CLASS = 'is-open';
  var BODY_MEGA_OPEN_CLASS = 'topocentras-mega-open';
  var STORAGE_KEY_DRAWER_OPEN = 'topocentrasCatalogDrawerOpen';
  var DRAWER_MAX_WIDTH = 1024;

  function getCatalogMenu() {
    return $('.topocentras-catalog-menu').first();
  }

  function getMegaMenu() {
    return $('.topocentras-mega-menu').first();
  }

  function isCatalogMenuPresent() {
    return getCatalogMenu().length > 0;
  }

  function isMegaMenuPresent() {
    return getMegaMenu().length > 0;
  }

  function isDrawerMode() {
    return window.matchMedia && window.matchMedia('(max-width: ' + DRAWER_MAX_WIDTH + 'px)').matches;
  }

  function setDrawerStored(open) {
    try {
      if (open) {
        window.sessionStorage.setItem(STORAGE_KEY_DRAWER_OPEN, '1');
      } else {
        window.sessionStorage.removeItem(STORAGE_KEY_DRAWER_OPEN);
      }
    } catch (e) {}
  }

  function getDrawerStored() {
    try {
      return window.sessionStorage.getItem(STORAGE_KEY_DRAWER_OPEN) === '1';
    } catch (e) {
      return false;
    }
  }

  function setOpen(open) {
    var $menu = getCatalogMenu();
    if (!$menu.length) return;

    $('body').toggleClass(BODY_OPEN_CLASS, open);
    $menu.toggleClass(MENU_OPEN_CLASS, open);

    // Persist only in drawer mode (mobile/tablet).
    if (isDrawerMode()) {
      setDrawerStored(open);
    } else {
      setDrawerStored(false);
    }
  }

  function setMegaOpen(open) {
    var $mega = getMegaMenu();
    if (!$mega.length) return;

    $('body').toggleClass(BODY_MEGA_OPEN_CLASS, open);
    $mega.toggleClass('is-open', open).attr('aria-hidden', open ? 'false' : 'true');
  }

  function toggleOpen() {
    var open = !$('body').hasClass(BODY_OPEN_CLASS);
    setOpen(open);
  }

  function toggleMegaOpen() {
    var open = !$('body').hasClass(BODY_MEGA_OPEN_CLASS);
    setMegaOpen(open);
  }

  function close() {
    setOpen(false);
    setMegaOpen(false);
  }

  function bind() {
    // Restore drawer state on load (mobile/tablet only).
    if (isDrawerMode() && isCatalogMenuPresent() && getDrawerStored()) {
      setOpen(true);
    }

    // Toggle by clicking the "Prekių katalogas" trigger in the blue bar.
    $(document).on('click', '[data-action="toggle-catalog-sidebar"]', function (e) {
      e.preventDefault();

      // Prefer mega menu (desktop behavior), fallback to sidebar drawer (homepage).
      if (isMegaMenuPresent()) {
        toggleMegaOpen();
        return;
      }
      if (isCatalogMenuPresent()) {
        toggleOpen();
      }
    });

    // Mega menu: switch tabs on hover/focus.
    $(document).on('mouseenter focusin', '.topocentras-mega-tab-link', function () {
      var id = $(this).data('mega-tab');
      if (!id) return;

      $('.topocentras-mega-tab').removeClass('is-active');
      $(this).closest('.topocentras-mega-tab').addClass('is-active');

      $('.topocentras-mega-panel').removeClass('is-active');
      $('.topocentras-mega-panel[data-mega-panel="' + id + '"]').addClass('is-active');
    });

    // Mega menu: open on hover of catalog trigger (desktop feel).
    $(document).on('mouseenter', '.nav-catalog-trigger', function () {
      if (isMegaMenuPresent()) setMegaOpen(true);
    });
    $(document).on('mouseleave', '.navigation', function () {
      // Close when leaving nav area.
      if (isMegaMenuPresent()) setMegaOpen(false);
    });

    // If you resize from mobile->desktop, ensure drawer state doesn't linger.
    $(window).on('resize', function () {
      if (!isDrawerMode()) {
        setDrawerStored(false);
        $('body').removeClass(BODY_OPEN_CLASS);
        getCatalogMenu().removeClass(MENU_OPEN_CLASS);
      }
    });

    // Close on ESC.
    $(document).on('keydown', function (e) {
      if (e.key === 'Escape') close();
    });

    // Close when clicking outside menus.
    $(document).on('click', function (e) {
      var $menu = getCatalogMenu();
      var $mega = getMegaMenu();

      var $target = $(e.target);
      var clickedTrigger = $target.closest('[data-action="toggle-catalog-sidebar"]').length > 0;
      var clickedInsideMenu = $target.closest($menu).length > 0;
      var clickedInsideMega = $target.closest($mega).length > 0;

      if (!clickedTrigger && !clickedInsideMenu && !clickedInsideMega) {
        close();
      }
    });
  }

  $(bind);

  // Enforce header order: Logo → Search → Heart → Cart
  function ensureHeaderOrder() {
    var scope = document.querySelector('.header.content') || document.querySelector('.page-header');
    if (!scope) return;
    var row = scope.querySelector('.header-main-row');
    var logo = row && row.querySelector('.header-logo');
    var search = scope.querySelector('.block-search');
    if (!row || !logo || !search) return;
    var actions = row.querySelector('.header-actions');
    if (!actions) return;
    if (search.parentElement !== row) {
      row.insertBefore(search, actions);
    } else if (logo.nextElementSibling !== search) {
      row.insertBefore(search, logo.nextElementSibling);
    }
  }
  $(ensureHeaderOrder);
  setTimeout(ensureHeaderOrder, 100);
  setTimeout(ensureHeaderOrder, 600);
  $(document).on('contentUpdated', ensureHeaderOrder);

  // Actual design: search placeholder "Ieškoti prekes, kategorijos..."
  var searchInput = document.getElementById('search') || document.querySelector('.block-search input[name="q"]');
  if (searchInput) {
    searchInput.setAttribute('placeholder', 'Ieškoti prekes, kategorijos...');
  }

  return {};
});

