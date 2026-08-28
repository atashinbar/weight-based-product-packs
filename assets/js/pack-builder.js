/**
 * Weight-based pack builder — client-side logic:
 *  - Per-bundle quantity steppers capped by remaining capacity and stock
 *  - Grouped cards (one per item, weight rows inside), per-group weight totals
 *  - Weight progress bar, live price and a visual box preview
 *  - The add button is only enabled when the total weight exactly matches the capacity
 */
(function () {
	'use strict';

	if (typeof wbppData === 'undefined') {
		return;
	}

	var root = document.querySelector('.wbpp-builder');
	if (!root) {
		return;
	}

	var items = {};
	wbppData.items.forEach(function (it) {
		items[String(it.id)] = it;
	});

	/*
	 * wp_localize_script casts top-level scalars to strings ("1000" instead
	 * of 1000), which would break strict comparisons below — normalize once.
	 */
	wbppData.capacity = parseInt(wbppData.capacity, 10) || 0;
	wbppData.packId = parseInt(wbppData.packId, 10) || 0;
	wbppData.priceDecimals = parseInt(wbppData.priceDecimals, 10) || 0;
	wbppData.boxCost = parseFloat(wbppData.boxCost) || 0;

	var counts = {};
	var pending = false;
	var lastError = '';

	var elFill = root.querySelector('.wbpp-progress-fill');
	var elCurrent = root.querySelector('.wbpp-current');
	var elPrice = root.querySelector('.wbpp-total-price');
	var elHint = root.querySelector('.wbpp-hint');
	var elAdd = root.querySelector('.wbpp-add');
	var addLabel = elAdd ? elAdd.textContent : '';
	var elPreview = root.querySelector('.wbpp-preview');
	var elPreviewFill = root.querySelector('.wbpp-preview-fill');
	var elPreviewText = root.querySelector('.wbpp-preview-text');

	/* ------------------------------ formatting ------------------------------ */

	function locale() {
		return wbppData.locale || undefined;
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
		var s = faNum(n, wbppData.priceDecimals);
		var sym = wbppData.currencySymbol || '';
		var pos = wbppData.currencyPos;
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
		var price = parseFloat(wbppData.boxCost) || 0;
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
		var capacity = wbppData.capacity;
		var remaining = capacity - t.grams;

		// Rows and stepper buttons.
		root.querySelectorAll('.wbpp-row').forEach(function (row) {
			var id = row.getAttribute('data-id');
			var it = items[id];
			var c = totalCount(id);
			row.querySelector('.wbpp-count').textContent = faNum(c);

			var minus = row.querySelector('.wbpp-minus');
			var plus = row.querySelector('.wbpp-plus');

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
			row.classList.toggle('is-active', c > 0);
		});

		// Per-group weight totals.
		root.querySelectorAll('.wbpp-group').forEach(function (group) {
			var groupGrams = 0;
			group.querySelectorAll('.wbpp-row').forEach(function (row) {
				var id = row.getAttribute('data-id');
				var it = items[id];
				if (it) {
					groupGrams += it.weight * totalCount(id);
				}
			});
			var elTotal = group.querySelector('.wbpp-group-total');
			if (elTotal) {
				elTotal.textContent = groupGrams > 0 ? faNum(groupGrams) + ' ' + wbppData.i18n.grams : '';
			}
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

		// Visual box preview.
		if (elPreviewFill) {
			elPreviewFill.style.height = pct + '%';
		}
		if (elPreviewText) {
			elPreviewText.textContent = faNum(Math.round(pct)) + '%';
		}
		if (elPreview) {
			elPreview.classList.toggle('is-full', remaining === 0 && t.grams > 0);
			elPreview.classList.toggle('is-over', remaining < 0);
		}

		// Hint text (a pending error stays visible until the user changes something).
		var hint = lastError;
		if (!hint) {
			if (t.grams === 0) {
				hint = wbppData.i18n.empty;
			} else if (remaining > 0) {
				hint = fmt(wbppData.i18n.remaining, remaining);
			} else if (remaining < 0) {
				hint = fmt(wbppData.i18n.over, -remaining);
			} else {
				hint = wbppData.i18n.complete;
			}
		}
		if (elHint) {
			elHint.textContent = hint;
			elHint.classList.toggle('has-error', !!lastError || remaining < 0);
		}

		// Live price.
		if (elPrice) {
			var label = money(t.price);
			if ((parseFloat(wbppData.boxCost) || 0) > 0) {
				label += ' (' + wbppData.i18n.boxCost + ': ' + money(parseFloat(wbppData.boxCost)) + ')';
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
		var plus = e.target.closest('.wbpp-plus');
		var minus = e.target.closest('.wbpp-minus');

		if (plus) {
			var row1 = plus.closest('.wbpp-row');
			var id1 = row1.getAttribute('data-id');
			var it1 = items[id1];
			var t1 = totals();
			if (it1 && t1.grams + it1.weight <= wbppData.capacity) {
				var nextCount = totalCount(id1) + 1;
				if (it1.stock === null || nextCount <= it1.stock) {
					counts[id1] = nextCount;
					lastError = '';
				}
			}
			render();
		}

		if (minus) {
			var row2 = minus.closest('.wbpp-row');
			var id2 = row2.getAttribute('data-id');
			if (counts[id2] > 0) {
				counts[id2]--;
				lastError = '';
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
			if (t.grams !== wbppData.capacity) {
				return;
			}

			var contents = Object.keys(counts)
				.filter(function (id) { return counts[id] > 0; })
				.map(function (id) {
					return { id: parseInt(id, 10), qty: counts[id] };
				});

			var fd = new FormData();
			fd.append('action', 'wbpp_add_pack');
			fd.append('nonce', wbppData.nonce);
			fd.append('pack_id', wbppData.packId);
			fd.append('contents', JSON.stringify(contents));

			pending = true;
			elAdd.disabled = true;
			elAdd.textContent = wbppData.i18n.adding;

			fetch(wbppData.ajaxUrl, {
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
						lastError = (res && res.data && res.data.message) ? res.data.message : wbppData.i18n.error;
						elAdd.textContent = addLabel;
						if (elHint) {
							elHint.textContent = (res && res.data && res.data.message) ? res.data.message : wbppData.i18n.error;
							elHint.classList.add('has-error');
						}
						render();
					}
				})
				.catch(function () {
					pending = false;
					lastError = wbppData.i18n.error;
					render();
				});
		});
	}

	render();
})();
