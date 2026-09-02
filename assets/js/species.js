/**
 * The Wikipedia card behind a species chip.
 *
 * WHAT THIS IS ALLOWED TO BREAK: NOTHING
 *
 * Every chip is already a link to every other farm carrying that game, and it
 * stays one. This file adds a card that appears while the pointer rests on a
 * chip and disappears when it leaves. Turn JavaScript off, or let the lookup
 * fail, and the page is exactly what it was before — which is why the card is
 * never rendered into the flow of the page and never moves anything.
 *
 * ONE CARD, NOT ONE PER CHIP
 *
 * A farm with eighteen species would otherwise carry eighteen hidden panels.
 * There is a single element, appended to <body> and positioned in fixed
 * co-ordinates against whichever chip asked for it. Fixed, because a chip list
 * sits inside Elementor sections whose overflow we do not control, and an
 * absolutely positioned card gets clipped by the first ancestor that hides it.
 *
 * HOVER INTENT
 *
 * A pointer crossing a row of chips on its way somewhere else passes over six
 * of them in a fifth of a second. Firing on every one of those is six requests
 * and six flashes of a card nobody asked for, so a chip has to be rested on
 * before it counts.
 */
(function () {
	'use strict';

	/* Rest this long on a chip before it counts as interest. */
	var OPEN_DELAY = 160;

	/* Grace on the way out, so the pointer can travel from chip to card. */
	var CLOSE_DELAY = 180;

	/* Answers already received, keyed by term id. Includes the empty ones. */
	var answers = {};

	/* Requests still in the air, keyed by term id, so a chip hovered twice
	   before the first answer lands shares one request instead of racing. */
	var pending = {};

	var card = null;
	var current = null;
	var openTimer = null;
	var closeTimer = null;

	/* Hover behaviour is for pointers that hover. A tap on a phone should open
	   the farms, which is what the chip has always done, not fight a card for
	   the same gesture. Keyboard focus is handled either way — a keyboard is
	   not a touchscreen. */
	var canHover = !window.matchMedia || window.matchMedia('(hover: hover)').matches;

	function build() {
		if (card) { return card; }

		card = document.createElement('div');
		card.className = 'acreage-w-wiki';
		card.id = 'acreage-wiki-card';
		card.setAttribute('role', 'tooltip');
		card.hidden = true;

		card.innerHTML =
			'<div class="acreage-w-wiki__media"><img alt="" decoding="async" /></div>' +
			'<div class="acreage-w-wiki__body">' +
			'<p class="acreage-w-wiki__name"></p>' +
			'<p class="acreage-w-wiki__text"></p>' +
			'<p class="acreage-w-wiki__credit">' +
			'<a class="acreage-w-wiki__link" target="_blank" rel="noopener noreferrer"></a>' +
			'</p>' +
			'</div>';

		document.body.appendChild(card);

		/* The pointer is allowed to move into the card — the Wikipedia link is
		   in there and has to be reachable — so the card cancels its own
		   closing while it is under the pointer. */
		if (canHover) {
			card.addEventListener('mouseenter', function () { clearTimeout(closeTimer); });
			card.addEventListener('mouseleave', function () { close(CLOSE_DELAY); });
		}

		return card;
	}

	/* ------------------------------------------------------------- fetching */

	function load(chip) {
		var id = chip.getAttribute('data-species');

		if (Object.prototype.hasOwnProperty.call(answers, id)) {
			return Promise.resolve(answers[id]);
		}

		if (pending[id]) {
			return pending[id];
		}

		var endpoint = chip.getAttribute('data-endpoint');

		if (!endpoint || !window.fetch) {
			answers[id] = null;
			return Promise.resolve(null);
		}

		var url = endpoint +
			(endpoint.indexOf('?') === -1 ? '?' : '&') +
			'action=acreage_species&term=' + encodeURIComponent(id);

		pending[id] = fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (json) {
				var data = json && json.success && json.data ? json.data.card : null;

				/* Remembered whether or not there was anything to show. A
				   species with no article should be asked about once a visit,
				   not once a hover. */
				answers[id] = data || null;

				return answers[id];
			})
			.catch(function () {
				answers[id] = null;
				return null;
			})
			.then(function (data) {
				delete pending[id];
				return data;
			});

		return pending[id];
	}

	/* ------------------------------------------------------------ rendering */

	function fill(data) {
		var media = card.querySelector('.acreage-w-wiki__media');
		var img = media.querySelector('img');

		if (data.image) {
			img.src = data.image;
			img.alt = data.name || data.title || '';
			media.hidden = false;
		} else {
			img.removeAttribute('src');
			media.hidden = true;
		}

		/* textContent throughout. The server has sanitised all of this already;
		   building the card without innerHTML means a change at either end
		   cannot turn a Wikipedia paragraph into markup. */
		card.querySelector('.acreage-w-wiki__name').textContent = data.name || data.title || '';
		card.querySelector('.acreage-w-wiki__text').textContent = data.extract || '';

		/* The credit is a licence condition, not a flourish: Wikipedia's text is
		   CC BY-SA and quoting it obliges us to say where it came from. */
		var link = card.querySelector('.acreage-w-wiki__link');
		var credit = card.querySelector('.acreage-w-wiki__credit');

		if (data.url) {
			link.href = data.url;
			link.textContent = 'Wikipedia';
			credit.hidden = false;
		} else {
			credit.hidden = true;
		}
	}

	/* ------------------------------------------------------------ placement */

	function place(chip) {
		var rect = chip.getBoundingClientRect();
		var box = card.getBoundingClientRect();
		var gap = 10;
		var margin = 12;

		/* Above the chip by preference — the card then never covers the rest of
		   the row the visitor is reading along. Below only when there is not
		   room above, which on a short viewport is most of the time. */
		var top = rect.top - box.height - gap;

		if (top < margin) {
			top = rect.bottom + gap;
		}

		/* Centred on the chip, then pulled back inside the viewport rather than
		   hanging off the edge — the last chip in a row is usually near it. */
		var left = rect.left + (rect.width / 2) - (box.width / 2);
		left = Math.max(margin, Math.min(left, window.innerWidth - box.width - margin));

		card.style.top = Math.round(top) + 'px';
		card.style.left = Math.round(left) + 'px';
	}

	/* -------------------------------------------------------- open and close */

	function open(chip) {
		build();
		current = chip;

		load(chip).then(function (data) {
			/* The pointer may have moved on while Wikipedia was answering. If
			   this is no longer the chip being rested on, the answer is kept in
			   the cache and nothing is shown. */
			if (current !== chip || !data) {
				if (current === chip) { hide(); }
				return;
			}

			fill(data);

			card.hidden = false;
			card.classList.remove('is-in');

			/* Measured only once it is laid out, and shown only once it is in
			   the right place — a card that fades in at 0,0 and then jumps is
			   worse than one that appears a frame later. */
			place(chip);

			requestAnimationFrame(function () {
				if (current === chip) { card.classList.add('is-in'); }
			});

			chip.setAttribute('aria-describedby', card.id);
		});
	}

	function hide() {
		if (!card) { return; }

		card.hidden = true;
		card.classList.remove('is-in');

		if (current) { current.removeAttribute('aria-describedby'); }

		current = null;
	}

	function close(delay) {
		clearTimeout(openTimer);
		clearTimeout(closeTimer);
		closeTimer = setTimeout(hide, delay || 0);
	}

	function schedule(chip) {
		clearTimeout(closeTimer);
		clearTimeout(openTimer);
		openTimer = setTimeout(function () { open(chip); }, OPEN_DELAY);
	}

	/* --------------------------------------------------------------- wiring */

	/* Delegated, so chips that arrive later — the editor rebuilding a widget,
	   or any future markup carrying the same attribute — need no second pass. */
	function chipFrom(event) {
		var el = event.target;

		while (el && el !== document) {
			if (el.classList && el.classList.contains('acreage-w-species__chip') && el.hasAttribute('data-species')) {
				return el;
			}
			el = el.parentNode;
		}

		return null;
	}

	function init() {
		if (canHover) {
			document.addEventListener('mouseover', function (e) {
				var chip = chipFrom(e);

				if (chip) {
					if (chip !== current) { schedule(chip); }
					else { clearTimeout(closeTimer); }
				}
			});

			document.addEventListener('mouseout', function (e) {
				if (chipFrom(e)) { close(CLOSE_DELAY); }
			});
		}

		document.addEventListener('focusin', function (e) {
			var chip = chipFrom(e);

			if (chip) { schedule(chip); }
			else if (card && !card.contains(e.target)) { close(0); }
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' || e.key === 'Esc') { close(0); }
		});

		/* A card in fixed co-ordinates is wrong the moment the page moves under
		   it, so it is put back where it belongs on every scroll — throttled to
		   a frame, and closed outright once its chip has left the viewport.

		   Closing on scroll instead would be cheaper, and was what this did
		   first. It cannot work here: the theme sets scroll-behavior:smooth, so
		   a single anchor jump emits scroll events for the best part of a
		   second afterwards, and a card opened during that tail was closed
		   again before anyone saw it. */
		var frame = null;

		function follow() {
			if (frame || !current) { return; }

			frame = requestAnimationFrame(function () {
				frame = null;

				if (!current || card.hidden) { return; }

				var rect = current.getBoundingClientRect();

				if (rect.bottom < 0 || rect.top > window.innerHeight) { hide(); }
				else { place(current); }
			});
		}

		window.addEventListener('scroll', follow, { passive: true });
		window.addEventListener('resize', follow);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
