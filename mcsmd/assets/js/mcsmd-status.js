(function () {

    function getCard(el) {
        if (!el) return null;
        return el.closest('.mcsmd-card');
    }

    document.addEventListener('click', function (event) {

        // REFRESH
        var refreshBtn = event.target.closest('[data-mcsmd-refresh]');
        if (refreshBtn) {
            event.preventDefault();

            var card = getCard(refreshBtn);
            if (card) {
                card.style.opacity = "0.6";
            }

            var url = window.location.href.split('#')[0];

            // limpiamos cualquier mcsmd_refresh previo
            url = url.replace(/([?&])mcsmd_refresh=[^&]*/g, '');
            url = url.replace(/[?&]$/, '');

            var sep = url.indexOf('?') === -1 ? '?' : '&';
            var ts  = Date.now();

            window.location.href = url + sep + 'mcsmd_refresh=' + ts;
        }
    });

})();
