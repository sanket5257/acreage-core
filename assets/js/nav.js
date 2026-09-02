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

		/* Only what can actually be reached. The search box lives inside the
		   panel and is display:none until the magnifier is pressed, so an
		   unfiltered list would trap tab focus on a field nobody can see. */
		function focusable() {
			var all = panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])');

			return Array.prototype.filter.call(all, function (el) {
				return el.offsetParent !== null;
			});
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

		bindSearch(nav);

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
		// in browsers that restore the previous DOM on back-navigation. The
		// magnifier is excluded: it is a link only so that it still goes
		// somewhere with scripting off, and closing the menu around it would
		// take the box it just opened away with it.
		panel.addEventListener('click', function (e) {
			var link = e.target.closest('a');

			if (link && !link.hasAttribute('data-search-toggle')) { setOpen(false); }
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

	/**
	 * The header search: a magnifier that opens a keyword box.
	 *
	 * The magnifier is a link to the farms page in the markup, so that with
	 * scripting off it still takes a visitor somewhere they can search rather
	 * than being a control that does nothing. Here it is upgraded into a
	 * disclosure — which is also where aria-expanded is added, because until
	 * this runs there is nothing expandable for it to describe.
	 */
	function bindSearch(nav) {
		var toggle = nav.querySelector('[data-search-toggle]');
		var form = nav.querySelector('.acreage-nav__searchform');

		if (!toggle || !form) { return; }

		var field = form.querySelector('.acreage-nav__searchfield');

		toggle.setAttribute('aria-expanded', 'false');

		function setSearching(open) {
			nav.classList.toggle('is-searching', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

			/* The field is what the visitor came for, so it takes focus rather
			   than leaving them to find it. Closing hands focus back to the
			   magnifier — a keyboard user who dismisses a panel and lands at
			   the top of the document has effectively lost their place. */
			if (open && field) { field.focus(); }
		}

		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			setSearching(!nav.classList.contains('is-searching'));
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || !nav.classList.contains('is-searching')) { return; }

			setSearching(false);
			toggle.focus();
		});

		/* Clicking away closes it. An empty box left hanging over the page is
		   the sort of thing a visitor has to work out how to dismiss, and the
		   answer should never be "press the magnifier again". */
		document.addEventListener('click', function (e) {
			if (!nav.classList.contains('is-searching')) { return; }
			if (e.target.closest('.acreage-nav__search')) { return; }

			setSearching(false);
		});

		/* An empty search would land on the archive with ?s= and no keyword,
		   which reads as "no farms match" rather than as nothing having been
		   typed. Refuse it and keep the cursor where it is. */
		form.addEventListener('submit', function (e) {
			if (field && '' === field.value.trim()) {
				e.preventDefault();
				field.focus();
			}
		});

		/* Above the breakpoint the panel is a row in the bar; below it, it is
		   the burger overlay. The box has to close on the way across, or it is
		   left open in a layout that was never showing it. */
		var mq = window.matchMedia('(min-width:' + (nav.dataset.bp || 900) + 'px)');
		var onChange = function () { setSearching(false); };

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
