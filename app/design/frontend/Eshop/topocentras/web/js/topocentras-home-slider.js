/**
 * Lightweight homepage hero slider (no external deps).
 *
 * Markup contract:
 * - .topocentras-home-slider
 *   - [data-slide] items
 *   - .topocentras-home-slider__prev / __next buttons
 *   - .topocentras-home-slider__dots (container)
 */
define(function () {
  'use strict';

  var AUTO_MS = 7000;

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function qsa(root, sel) {
    return Array.prototype.slice.call(root.querySelectorAll(sel));
  }

  function initSlider(root) {
    var slides = qsa(root, '[data-slide]');
    if (!slides.length) return;

    var prevBtn = qs(root, '.topocentras-home-slider__prev');
    var nextBtn = qs(root, '.topocentras-home-slider__next');
    var dotsWrap = qs(root, '.topocentras-home-slider__dots');

    var index = 0;
    var timer = null;

    function renderDots() {
      if (!dotsWrap) return;
      dotsWrap.innerHTML = '';
      slides.forEach(function (_s, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'topocentras-home-slider__dot' + (i === index ? ' is-active' : '');
        b.setAttribute('aria-label', 'Slide ' + (i + 1));
        b.addEventListener('click', function () {
          goTo(i);
        });
        dotsWrap.appendChild(b);
      });
    }

    function apply() {
      slides.forEach(function (s, i) {
        var active = i === index;
        s.classList.toggle('is-active', active);
        s.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
      renderDots();
    }

    function stop() {
      if (timer) window.clearInterval(timer);
      timer = null;
    }

    function start() {
      stop();
      if (slides.length <= 1) return;
      timer = window.setInterval(function () {
        goTo(index + 1);
      }, AUTO_MS);
    }

    function goTo(next) {
      index = (next + slides.length) % slides.length;
      apply();
      start();
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goTo(index - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goTo(index + 1);
      });
    }

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);

    // Initial paint
    apply();
    start();
  }

  function initAll() {
    var sliders = document.querySelectorAll('.topocentras-home-slider');
    if (!sliders || !sliders.length) return;
    Array.prototype.forEach.call(sliders, initSlider);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  return {};
});

