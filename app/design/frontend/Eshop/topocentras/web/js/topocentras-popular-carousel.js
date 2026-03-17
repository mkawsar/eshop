/**
 * Popular products carousel: prev/next scroll by one card.
 * No Owl dependency; uses native scroll.
 */
define(function () {
  'use strict';

  var STEP_RATIO = 1; /* scroll by ~1 visible card */

  function initCarousel(section) {
    var viewport = section.querySelector('.popular-carousel-viewport');
    var track = section.querySelector('.popular-carousel-track');
    var prevBtn = section.querySelector('.popular-carousel-prev');
    var nextBtn = section.querySelector('.popular-carousel-next');

    if (!viewport || !track || !prevBtn || !nextBtn) return;

    function getScrollAmount() {
      var cardWrap = track.querySelector('.popular-carousel-card-wrap');
      if (!cardWrap) return viewport.offsetWidth * 0.8;
      var style = window.getComputedStyle(track);
      var gap = parseFloat(style.gap) || 20;
      return cardWrap.offsetWidth + gap;
    }

    prevBtn.addEventListener('click', function () {
      var amount = getScrollAmount();
      viewport.scrollBy({ left: -amount, behavior: 'smooth' });
    });
    nextBtn.addEventListener('click', function () {
      var amount = getScrollAmount();
      viewport.scrollBy({ left: amount, behavior: 'smooth' });
    });
  }

  function initAll() {
    var sections = document.querySelectorAll('.popular-carousel-section');
    if (!sections.length) return;
    Array.prototype.forEach.call(sections, initCarousel);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  return {};
});
