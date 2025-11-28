(function () {
	if (typeof MCSMD_AJAX === 'undefined') {
		return;
	}

	/**
	 * Refresca una tarjeta (status o players) vía AJAX.
	 *
	 * @param {HTMLElement} card
	 * @param {string} type "status" o "players"
	 */
	function refreshCard(card, type) {
		if (!card) return;

		type = type || card.dataset.mcsmdCard || 'status';

		var formData = new FormData();
		formData.append('nonce', MCSMD_AJAX.nonce);

		if (type === 'players') {
			formData.append('action', 'mcsmd_refresh_players');
		} else {
			formData.append('action', 'mcsmd_refresh_status');
			type = 'status';
		}

		card.classList.add('mcsmd-card-loading');

		fetch(MCSMD_AJAX.url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
			.then(function (res) {
				return res.text();
			})
			.then(function (html) {
				card.classList.remove('mcsmd-card-loading');

				html = html.trim();
				if (!html) return;

				var tmp = document.createElement('div');
				tmp.innerHTML = html;
				var newCard = tmp.firstElementChild;

				if (newCard) {
					// Por si acaso el HTML no trae el data-mcsmd-card
					if (!newCard.dataset.mcsmdCard) {
						newCard.dataset.mcsmdCard = type;
					}
					card.replaceWith(newCard);
				}
			})
			.catch(function (err) {
				console.error('MCStatus AJAX error:', err);
				card.classList.remove('mcsmd-card-loading');
			});
	}

	// Click manual en cualquier botón con data-mcsmd-refresh
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-mcsmd-refresh]');
		if (!btn) return;

		var card = btn.closest('[data-mcsmd-card]');
		if (!card) return;

		e.preventDefault();

		var type = btn.dataset.mcsmdRefresh || card.dataset.mcsmdCard || 'status';
		refreshCard(card, type);
	});

	// Auto-refresh para TODAS las tarjetas (status + players)
	document.addEventListener('DOMContentLoaded', function () {
		function autoRefreshAll() {
			var cards = document.querySelectorAll('[data-mcsmd-card]');
			if (!cards.length) return;

			cards.forEach
				? cards.forEach(function (card) {
					refreshCard(card, card.dataset.mcsmdCard || 'status');
				})
				: Array.prototype.forEach.call(cards, function (card) {
					refreshCard(card, card.dataset.mcsmdCard || 'status');
				});
		}

		// Primera actualización al entrar en la página
		autoRefreshAll();

		var interval = parseInt(MCSMD_AJAX.autoInterval, 10) || 0;
		if (interval > 0) {
			setInterval(autoRefreshAll, interval);
		}
	});
})();
