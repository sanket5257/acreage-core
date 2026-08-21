/**
 * Site Nav — burger toggle.
 *
 * The menu is a plain list in the markup and is shown by CSS at desktop widths,
 * so it works with JavaScript disabled. This only adds the open/close behaviour
 * below the breakpoint.
 *
 * Focus is trapped while the panel is open: without that, tabbing past the last
 * link moves focus to content hidden behind the overlay, and a keyboard user has
 * no way to tell where they are.
 */
(function () {
	'use strict';

	function init(root) {
		var navs = (root || document).querySelectorAll('.acreage-nav');

		Array.prototype.forEach.call(navs, function (nav) {
			if (nav.dataset.bound === '1') { return; }
			nav.dataset.bound = '1';
			bind(nav);
		});
	}

	function bind(nav) {
		var burger = nav.querySelector('.acreage-nav__burger');
		var panel = nav.querySelector('.acreage-nav__panel');

		if (!burger || !panel) { return; }

		function focusable() {
			return panel.querySelectorAll('a[href], button:not([disabled])');
		}

		function setOpen(open) {
			nav.classList.toggle('is-open', open);
			burger.setAttribute('aria-expanded', open ? 'true' : 'false');
			document.body.classList.toggle('acreage-nav-open', open);

			if (open) {
				var first = focusable()[0];
				if (first) { first.focus(); }
			}
		}

		burger.addEventListener('click', function () {
			setOpen(!nav.classList.contains('is-open'));
		});

		document.addEventListener('keydown', function (e) {
			if (!nav.classList.contains('is-open')) { return; }

			if (e.key === 'Escape') {
				setOpen(false);
				burger.focus();
				return;
			}

			if (e.key !== 'Tab') { return; }

			var items = focusable();
			if (!items.length) { return; }

			var first = items[0];
			var last = items[items.length - 1];

			// Wrap focus around the panel rather than letting it escape behind it.
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				burger.focus();
			}
		});

		// Following a link should not leave the overlay open behind the new page
		// in browsers that restore the previous DOM on back-navigation.
		panel.addEventListener('click', function (e) {
			if (e.target.closest('a')) { setOpen(false); }
		});

		// Returning above the breakpoint must clear the mobile state, or the
		// desktop menu inherits an "open" class it does not understand.
		var mq = window.matchMedia('(min-width:' + (nav.dataset.bp || 900) + 'px)');
		var onChange = function (e) { if (e.matches) { setOpen(false); } };

		if (mq.addEventListener) {
			mq.addEventListener('change', onChange);
		} else if (mq.addListener) {
			mq.addListener(onChange);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); });
	} else {
		init();
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/acreage-nav.default', function ($scope) {
			init($scope && $scope[0] ? $scope[0] : undefined);
		});
	}
})();

/**
 * Transparent-over-hero header.
 *
 * On the front page the bar is lifted onto the photograph by CSS. Once the page
 * scrolls past the hero it has to become solid, or the menu sits unreadable on
 * pale content.
 *
 * IntersectionObserver rather than a scroll listener: a scroll handler fires on
 * every frame and has to read layout to decide, which is exactly the pattern
 * that makes a page feel heavy. The observer fires twice — entering and leaving
 * — and costs nothing in between.
 */
(function () {
	'use strict';

	function initOverlay() {
		var bar = document.querySelector('.acreage-headbar');
		var hero = document.querySelector('.acreage-hero');

		if (!bar || !hero || !('IntersectionObserver' in window)) { return; }

		// Trip the switch when the hero's bottom passes the bar's own height.
		var height = bar.getBoundingClientRect().height || 90;

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				bar.classList.toggle('is-stuck', !entry.isIntersecting);
			});
		}, { rootMargin: '-' + Math.round(height) + 'px 0px 0px 0px', threshold: 0 });

		observer.observe(hero);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initOverlay);
	} else {
		initOverlay();
	}
})();
