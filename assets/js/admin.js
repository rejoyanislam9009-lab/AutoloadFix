(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var disableButton = event.target.closest('.autoloadfix-disable-button');
		var restoreButton = event.target.closest('.autoloadfix-restore-button');

		if (disableButton && ! window.confirm(AutoloadFixAdmin.confirmDisable)) {
			event.preventDefault();
		}

		if (restoreButton && ! window.confirm(AutoloadFixAdmin.confirmRestore)) {
			event.preventDefault();
		}
	});
}());
