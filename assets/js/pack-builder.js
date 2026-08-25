/**
 * Weight-based pack builder — client-side logic:
 *  - Per-bundle quantity steppers capped by remaining capacity and stock
 *  - Weight progress bar + live price
 *  - The add button is only enabled when the total weight exactly matches the capacity
 */
(function () {
	'use strict';

	if (typeof wbpData === 'undefined') {
		return;
	}

	var root = document.querySelector('.wbp-builder');
	if (!root) {
		return;
	}

	var items = {};
	wbpData.items.forEach(function (it) {
		items[String(it.id)] = it;
	});

	var counts = {};
	var pending = false;

	var elFill = root.querySelector('.wbp-progress-fill');
	var elCurrent = root.querySelector('.wbp-current');
	var elPrice = root.querySelector('.wbp-total-price');
	var elHint = root.querySelector('.wbp-hint');
	var elAdd = root.querySelector('.wbp-add');
	var addLabel = elAdd ? elAdd.textContent : '';

	/* ------------------------------ formatting ------------------------------ */

	function locale() {
		return wbpData.locale || undefined;
	}

	function faNum(n, decimals) {
		var opts = {
			maximumFractionDigits: decimals || 0,
			minimumFractionDigits: decimals || 0
		};
		try {
			return Number(n).toLocaleString(locale(), opts);
		} catch (e) {
			return String(n);
		}
	}

	function money(n) {
		var s = faNum(n, wbpData.priceDecimals);
		var sym = wbpData.currencySymbol || '';
		var pos = wbpData.currencyPos;
		if (pos === 'left' || pos === 'left_space') {
			return sym + (pos === 'left_space' ? ' ' : '') + s;
		}
		if (pos === 'right_space') {
			return s + ' ' + sym;
		}
		return s + sym;
	}

	function fmt(str, grams) {
		return str.replace('%s', faNum(grams));
	}

	/* ------------------------------ computation ------------------------------ */

	function totalCount(id) {
		return counts[id] || 0;
	}

	function totals() {
		var grams = 0;
		var price = parseFloat(wbpData.boxCost) || 0;
		Object.keys(counts).forEach(function (id) {
			var c = counts[id];
			if (!c) { return; }
			var it = items[id];
			if (!it) { return; }
			grams += it.weight * c;
			price += it.price * c;
		});
		return { grams: grams, price: price };
	}

	/* ------------------------------ render ------------------------------ */

	function render() {
		var t = totals();
		var capacity = wbpData.capacity;
		var remaining = capacity - t.grams;

		// Counters and stepper buttons.
		root.querySelectorAll('.wbp-item').forEach(function (card) {
			var id = card.getAttribute('data-id');
			var it = items[id];
			var c = totalCount(id);
			card.querySelector('.wbp-count').textContent = faNum(c);

			var minus = card.querySelector('.wbp-minus');
			var plus = card.querySelector('.wbp-plus');

			minus.disabled = c <= 0;
			var plusBlocked = false;
			if (it) {
				// Capacity cap: adding one more must not exceed the capacity.
				if (t.grams + it.weight > capacity) {
					plusBlocked = true;
				}
				// Stock cap.
				if (it.stock !== null && c >= it.stock) {
					plusBlocked = true;
				}
			}
			plus.disabled = plusBlocked;
			card.classList.toggle('is-active', c > 0);
		});

		// Progress bar.
		var pct = capacity > 0 ? (t.grams / capacity) * 100 : 0;
		pct = Math.max(0, Math.min(100, pct));
		if (elFill) {
			elFill.style.width = pct + '%';
		}
		if (elCurrent) {
			elCurrent.textContent = faNum(t.grams);
		}
		root.classList.toggle('is-empty', t.grams === 0);
		root.classList.toggle('is-partial', t.grams > 0 && remaining > 0);
		root.classList.toggle('is-full', remaining === 0 && t.grams > 0);
		root.classList.toggle('is-over', remaining < 0);

		// Hint text.
		var hint = '';
		if (t.grams === 0) {
			hint = wbpData.i18n.empty;
		} else if (remaining > 0) {
			hint = fmt(wbpData.i18n.remaining, remaining);
		} else if (remaining < 0) {
			hint = fmt(wbpData.i18n.over, -remaining);
		} else {
			hint = wbpData.i18n.complete;
		}
		if (elHint) {
			elHint.textContent = hint;
			elHint.classList.toggle('has-error', remaining < 0);
		}

		// Live price.
		if (elPrice) {
			var label = money(t.price);
			if ((parseFloat(wbpData.boxCost) || 0) > 0) {
				label += ' (' + wbpData.i18n.boxCost + ': ' + money(parseFloat(wbpData.boxCost)) + ')';
			}
			elPrice.textContent = label;
		}

		// Add button.
		if (elAdd && !pending) {
			elAdd.disabled = !(t.grams > 0 && remaining === 0);
		}
	}

	/* ------------------------------ events ------------------------------ */

	root.addEventListener('click', function (e) {
		var plus = e.target.closest('.wbp-plus');
		var minus = e.target.closest('.wbp-minus');

		if (plus) {
			var card1 = plus.closest('.wbp-item');
			var id1 = card1.getAttribute('data-id');
			var it1 = items[id1];
			var t1 = totals();
			if (it1 && t1.grams + it1.weight <= wbpData.capacity) {
				var nextCount = totalCount(id1) + 1;
				if (it1.stock === null || nextCount <= it1.stock) {
					counts[id1] = nextCount;
				}
			}
			render();
		}

		if (minus) {
			var card2 = minus.closest('.wbp-item');
			var id2 = card2.getAttribute('data-id');
			if (counts[id2] > 0) {
				counts[id2]--;
			}
			render();
		}
	});

	if (elAdd) {
		elAdd.addEventListener('click', function () {
			if (pending) {
				return;
			}
			var t = totals();
			if (t.grams !== wbpData.capacity) {
				return;
			}

			var contents = Object.keys(counts)
				.filter(function (id) { return counts[id] > 0; })
				.map(function (id) {
					return { id: parseInt(id, 10), qty: counts[id] };
				});

			var fd = new FormData();
			fd.append('action', 'wbp_add_pack');
			fd.append('nonce', wbpData.nonce);
			fd.append('pack_id', wbpData.packId);
			fd.append('contents', JSON.stringify(contents));

			pending = true;
			elAdd.disabled = true;
			elAdd.textContent = wbpData.i18n.adding;

			fetch(wbpData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success && res.data && res.data.redirect) {
						window.location = res.data.redirect;
					} else {
						pending = false;
						elAdd.textContent = addLabel;
						if (elHint) {
							elHint.textContent = (res && res.data && res.data.message) ? res.data.message : wbpData.i18n.error;
							elHint.classList.add('has-error');
						}
						render();
					}
				})
				.catch(function () {
					pending = false;
					if (elHint) {
						elHint.textContent = wbpData.i18n.error;
						elHint.classList.add('has-error');
					}
					render();
				});
		});
	}

	render();
})();
