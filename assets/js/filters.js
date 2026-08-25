/**
 * The live filter — ticking a box redraws the farms instead of reloading.
 *
 * WHAT THIS FILE IS ALLOWED TO ASSUME
 *
 * Nothing. The panel it enhances is a working GET form and the grid it fills is
 * already full of farms rendered by PHP. If this file never parses, never binds,
 * or fails mid-request, the visitor still has a form that filters farms — the
 * fallback for a failed fetch is to submit it. Nothing is hidden waiting for
 * script, and the Apply button is only hidden once the script is certain it has
 * something to drive.
 *
 * WHY THE URL IS REWRITTEN ON EVERY CHANGE
 *
 * "Every combination is linkable" is in the build note, and it is what the
 * client's current site does. A live filter that leaves the address bar behind
 * quietly takes that away: the visitor can no longer send anyone the four farms
 * they just narrowed down to, and the back button walks off the page instead of
 * undoing the last tick. So each change replaces the URL with the canonical one
 * the server built, and popstate puts the panel back the way it was.
 *
 * WHY PAGE LINKS BECOME A LOAD MORE BUTTON
 *
 * The page links printed with the first paint describe a result set that stops
 * existing the moment a filter changes. Rather than re-render them, the script
 * hides them once it takes over and reveals the Load more button instead, which
 * needs no page numbers to be correct.
 */
(function () {
	'use strict';

	/* Long enough that ticking three boxes in a row is one request, short enough
	   that a single tick feels immediate. */
	var DEBOUNCE = 260;

	function forEach(list, fn) {
		Array.prototype.forEach.call(list, fn);
	}

	function init(root) {
		var forms = (root || document).querySelectorAll('form.acreage-w-filters[data-endpoint]');

		forEach(forms, function (form) {
			if (form.dataset.bound === '1') { return; }

			/*
			 * Only an archive grid can be driven — a homepage band shows a chosen
			 * set of farms and filtering it would be meaningless. No grid on the
			 * page means the panel stays an ordinary form, which is a perfectly
			 * good thing for it to be.
			 */
			var wrap = document.querySelector('.acreage-w-gridwrap[data-live="1"]');

			if (!wrap || !wrap.dataset.endpoint) { return; }

			form.dataset.bound = '1';
			bind(form, wrap);
		});
	}

	function bind(form, wrap) {
		var grid = wrap.querySelector('.acreage-w-grid');
		var moreWrap = wrap.querySelector('.acreage-w-more');
		var moreBtn = moreWrap ? moreWrap.querySelector('.acreage-w-more__btn') : null;
		var pages = wrap.querySelector('.acreage-w-pages');
		var chipsWrap = form.querySelector('.acreage-w-filters__activewrap');
		var resultLine = wrap.querySelector('.acreage-w-results__count');
		var sortForm = wrap.querySelector('.acreage-w-results__sort');
		var sortSelect = sortForm ? sortForm.querySelector('select[name="sort"]') : null;

		/*
		 * The panel's own count line is the fallback, not the first choice. When
		 * the results bar is on the page it says the same thing in the place the
		 * comp puts it, and two copies of one number is one too many.
		 */
		var result = resultLine ? null : form.querySelector('.acreage-w-filters__result');

		var page = 1;
		var timer = null;
		var controller = null;
		var tookOver = false;

		if (!grid) { return; }

		// Now that there is something to drive, the Apply button is redundant.
		form.classList.add('is-live');

		/* ------------------------------------------------------------- state */

		var axes = (form.dataset.axes || '').split(',').filter(Boolean);

		/** The hidden inputs carrying filters that have no checkbox here. */
		function carried() {
			return form.querySelectorAll('input[type="hidden"][name$="[]"]');
		}

		/*
		 * Both sources count. A panel is usually set to show four of the seven
		 * axes, and a ?species=sable that arrived from somewhere else is carried
		 * in a hidden input instead — reading only the checkboxes would drop it
		 * on the first tick, which is precisely what the chip bar exists to stop.
		 */
		function state() {
			var out = {};

			function add(field) {
				var axis = field.name.replace(/\[\]$/, '');

				if (!field.value) { return; }
				if (!out[axis]) { out[axis] = []; }
				if (out[axis].indexOf(field.value) === -1) { out[axis].push(field.value); }
			}

			forEach(form.querySelectorAll('input[type="checkbox"]:checked'), add);
			forEach(carried(), add);

			return out;
		}

		function hidden(name) {
			var field = form.querySelector('input[type="hidden"][name="' + name + '"]');

			return field ? field.value : '';
		}

		/*
		 * The keyword and the sort live in hidden inputs rather than in a
		 * variable, so that a fallback submit carries them too. Writing them back
		 * keeps those two truths the same one.
		 */
		function setHidden(name, value) {
			var field = form.querySelector('input[type="hidden"][name="' + name + '"]');

			if (!value) {
				if (field) { field.parentNode.removeChild(field); }
				return;
			}

			if (!field) {
				field = document.createElement('input');
				field.type = 'hidden';
				field.name = name;
				form.appendChild(field);
			}

			field.value = value;
		}

		/** Put the panel into whatever state a URL describes. */
		function apply(href) {
			var url = new URL(href, window.location.href);
			var wanted = {};

			url.searchParams.forEach(function (value, key) {
				// Only the seven axes. A stray query argument is not a filter.
				if (axes.indexOf(key) === -1) { return; }
				wanted[key] = value.split(',');
			});

			var boxed = {};

			forEach(form.querySelectorAll('input[type="checkbox"]'), function (box) {
				var axis = box.name.replace(/\[\]$/, '');

				if (!boxed[axis]) { boxed[axis] = []; }
				boxed[axis].push(box.value);

				box.checked = !!wanted[axis] && wanted[axis].indexOf(box.value) !== -1;
			});

			// Rebuilt rather than edited: a chip removed from a hidden axis has to
			// take its carrier with it.
			forEach(carried(), function (field) { field.parentNode.removeChild(field); });

			Object.keys(wanted).forEach(function (axis) {
				wanted[axis].forEach(function (slug) {
					if (!slug) { return; }
					if (boxed[axis] && boxed[axis].indexOf(slug) !== -1) { return; }

					var field = document.createElement('input');

					field.type = 'hidden';
					field.name = axis + '[]';
					field.value = slug;
					form.appendChild(field);
				});
			});

			setHidden('s', url.searchParams.get('s') || '');
			setHidden('sort', url.searchParams.get('sort') || '');

			// A URL with no sort is the default one, and the select has to say so
			// — otherwise the back button leaves it claiming an order the farms
			// are no longer in.
			if (sortSelect) {
				sortSelect.value = url.searchParams.get('sort') || 'latest';
			}
		}

		/** The canonical URL for what is ticked right now. */
		function canonical() {
			var url = new URL(form.dataset.archive, window.location.href);
			var chosen = state();

			Object.keys(chosen).forEach(function (axis) {
				url.searchParams.set(axis, chosen[axis].join(','));
			});

			if (hidden('s')) { url.searchParams.set('s', hidden('s')); }
			if (hidden('sort')) { url.searchParams.set('sort', hidden('sort')); }

			return url.toString();
		}

		/* ----------------------------------------------------------- drawing */

		function showEmpty() {
			var note = document.createElement('p');

			note.className = 'acreage-w-empty';
			note.textContent = wrap.dataset.empty || '';

			grid.textContent = '';
			grid.appendChild(note);
		}

		/* ---------------------------------------------------------- requests */

		function run(pageNo, append, push) {
			if (controller) { controller.abort(); }
			controller = ('AbortController' in window) ? new AbortController() : null;

			var body = new URLSearchParams();

			body.set('action', 'acreage_grid');
			body.set('nonce', wrap.dataset.nonce);
			body.set('args', wrap.dataset.args);
			body.set('sig', wrap.dataset.sig);
			body.set('page', String(pageNo));

			var chosen = state();

			Object.keys(chosen).forEach(function (axis) {
				body.set(axis, chosen[axis].join(','));
			});

			if (hidden('s')) { body.set('s', hidden('s')); }
			if (hidden('sort')) { body.set('sort', hidden('sort')); }

			wrap.classList.add('is-loading');
			grid.setAttribute('aria-busy', 'true');

			fetch(wrap.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
				signal: controller ? controller.signal : undefined
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { throw new Error('rejected'); }

					if (append) {
						grid.insertAdjacentHTML('beforeend', res.data.html);
					} else if (res.data.html) {
						grid.innerHTML = res.data.html;
					} else {
						showEmpty();
					}

					if (chipsWrap) { chipsWrap.innerHTML = res.data.chips || ''; }

					if (resultLine) {
						resultLine.textContent = res.data.result || res.data.count || '';
					}

					if (result) {
						result.textContent = res.data.count || '';
						result.hidden = !res.data.count;
					}

					page = pageNo;

					if (moreWrap) { moreWrap.hidden = !res.data.more; }

					if (moreBtn) {
						moreBtn.disabled = false;
					}

					/*
					 * The first successful live request is where the page links stop
					 * being true. Hidden rather than removed, so a script error later
					 * cannot leave the visitor with no way to page at all.
					 */
					if (!tookOver) {
						tookOver = true;
						if (pages) { pages.hidden = true; }
					}

					if (push) {
						window.history.pushState(
							{ acreage: true },
							'',
							res.data.url || canonical()
						);
					}
				})
				.catch(function (error) {
					if (error && 'AbortError' === error.name) { return; }

					/*
					 * A failed request must not leave a half-filtered page. Loading
					 * more is a dead end that can be reported in place; a filter change
					 * has already been promised to the visitor, so it is honoured the
					 * slow way — a real navigation to the same URL, which the archive
					 * understands on its own.
					 */
					if (append && moreBtn) {
						moreBtn.disabled = true;
						moreBtn.textContent = moreBtn.dataset.failed || 'Could not load more';
					} else {
						window.location.href = canonical();
					}
				})
				.then(function () {
					wrap.classList.remove('is-loading');
					grid.removeAttribute('aria-busy');
				});
		}

		function schedule() {
			window.clearTimeout(timer);
			timer = window.setTimeout(function () { run(1, false, true); }, DEBOUNCE);
		}

		/* ---------------------------------------------------------- handlers */

		form.addEventListener('change', function (event) {
			if (event.target && 'checkbox' === event.target.type) { schedule(); }
		});

		// Enter in the panel, or a click on Apply before the script hid it.
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			window.clearTimeout(timer);
			run(1, false, true);
		});

		/*
		 * Chips and the clear links are rewritten on every response, so they are
		 * handled here rather than bound individually — a listener attached to a
		 * chip dies with the chip.
		 */
		form.addEventListener('click', function (event) {
			var link = event.target.closest
				? event.target.closest('.acreage-w-filters__chip, .acreage-w-filters__clearall, .acreage-w-filters__clear')
				: null;

			if (!link || !link.href) { return; }

			event.preventDefault();
			window.clearTimeout(timer);
			apply(link.href);
			run(1, false, true);
		});

		if (moreBtn) {
			moreBtn.addEventListener('click', function () {
				run(page + 1, true, false);
			});
		}

		/*
		 * Sorting. The chosen order is written into the panel's hidden input
		 * rather than kept in a variable of its own, because that input is what
		 * every other path already reads — the canonical URL, the request body
		 * and a fallback submit. One place to be wrong instead of two.
		 */
		if (sortSelect) {
			sortSelect.addEventListener('change', function () {
				window.clearTimeout(timer);
				setHidden('sort', sortSelect.value === 'latest' ? '' : sortSelect.value);
				run(1, false, true);
			});
		}

		if (sortForm) {
			sortForm.addEventListener('submit', function (event) {
				event.preventDefault();
				window.clearTimeout(timer);
				setHidden('sort', sortSelect && sortSelect.value !== 'latest' ? sortSelect.value : '');
				run(1, false, true);
			});
		}

		/*
		 * Back and forward have to mean something. Without this the browser walks
		 * off the results page entirely on the first back press, which is not what
		 * a visitor who has just unticked one box is asking for.
		 */
		window.addEventListener('popstate', function () {
			apply(window.location.href);
			run(1, false, false);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); });
	} else {
		init();
	}

	// Elementor rebuilds widget markup in the editor without reloading the page.
	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		forEach(
			['acreage-farm-filters.default', 'acreage-farm-grid.default'],
			function (element) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/' + element, function () {
					init();
				});
			}
		);
	}
})();
