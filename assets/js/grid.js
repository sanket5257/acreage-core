/**
 * Category tabs and Load more for the Farm Grid.
 *
 * PROGRESSIVE ENHANCEMENT, ON PURPOSE
 *
 * The first page of cards is rendered by PHP, so a visitor with no JavaScript —
 * or on the two seconds before this file parses — sees farms, not a spinner.
 * Everything here only ever adds to that. Nothing is hidden waiting for script.
 *
 * The horizontal scroller needs no JavaScript at all; it is CSS scroll-snap, so
 * it works with a swipe, a trackpad, the scrollbar and the keyboard for free.
 * The arrows below are a convenience on top of a thing that already works.
 */
(function () {
	'use strict';

	function init(root) {
		var wraps = (root || document).querySelectorAll('.acreage-w-gridwrap[data-sig]');

		Array.prototype.forEach.call(wraps, function (wrap) {
			if (wrap.dataset.bound === '1') { return; }
			wrap.dataset.bound = '1';
			bind(wrap);
		});
	}

	function bind(wrap) {
		var grid = wrap.querySelector('.acreage-w-grid');
		var moreBtn = wrap.querySelector('.acreage-w-more__btn');
		var tabs = wrap.parentNode ? wrap.parentNode.querySelectorAll('.acreage-w-tab') : [];
		var page = 1;
		var busy = false;

		function request(category, pageNo, append) {
			if (busy) { return; }
			busy = true;
			wrap.classList.add('is-loading');

			var body = new URLSearchParams();
			body.set('action', 'acreage_grid');
			body.set('nonce', wrap.dataset.nonce);
			body.set('args', wrap.dataset.args);
			body.set('sig', wrap.dataset.sig);
			body.set('category', category);
			body.set('page', String(pageNo));

			fetch(wrap.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { throw new Error('rejected'); }

					if (append) {
						grid.insertAdjacentHTML('beforeend', res.data.html);
					} else {
						grid.innerHTML = res.data.html;
					}

					if (moreBtn) {
						moreBtn.parentNode.hidden = !res.data.more;
					}
				})
				.catch(function () {
					/*
					 * A failed fetch must not leave the visitor with an empty grid and
					 * no explanation. On a tab switch the cards are already gone, so
					 * fall back to a full page load with the filter in the URL — the
					 * archive understands the same parameter.
					 */
					if (!append) {
						window.location.href = gridFallbackUrl(category);
					} else if (moreBtn) {
						moreBtn.disabled = true;
						moreBtn.textContent = moreBtn.dataset.failed || 'Could not load more';
					}
				})
				.then(function () {
					busy = false;
					wrap.classList.remove('is-loading');
				});
		}

		function gridFallbackUrl(category) {
			var url = new URL(window.location.href);
			if (category) {
				url.searchParams.set('listing_category', category);
			} else {
				url.searchParams.delete('listing_category');
			}
			return url.toString();
		}

		Array.prototype.forEach.call(tabs, function (tab) {
			tab.addEventListener('click', function () {
				Array.prototype.forEach.call(tabs, function (t) {
					t.classList.remove('is-on');
					t.setAttribute('aria-selected', 'false');
				});
				tab.classList.add('is-on');
				tab.setAttribute('aria-selected', 'true');

				page = 1;
				wrap.dataset.category = tab.dataset.category;
				request(tab.dataset.category, 1, false);
			});
		});

		if (moreBtn) {
			moreBtn.addEventListener('click', function () {
				page += 1;
				request(wrap.dataset.category || '', page, true);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(); });
	} else {
		init();
	}

	// Elementor rebuilds widget markup in the editor without reloading the page.
	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/acreage-farm-grid.default', function ($scope) {
			init($scope && $scope[0] ? $scope[0] : undefined);
		});
	}
})();
