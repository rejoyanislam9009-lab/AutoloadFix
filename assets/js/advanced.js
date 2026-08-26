(function () {
	'use strict';

	function query(selector, context) {
		return (context || document).querySelector(selector);
	}

	function queryAll(selector, context) {
		return Array.prototype.slice.call((context || document).querySelectorAll(selector));
	}

	function filterAdvancedOptions() {
		var table = query('#autoloadfix-advanced-table');
		if (! table) {
			return;
		}

		var searchField = query('#autoloadfix-advanced-search');
		var riskField = query('#autoloadfix-advanced-risk');
		var watchedField = query('#autoloadfix-advanced-watched');
		var search = (searchField.value || '').toLowerCase().trim();
		var risk = riskField.value;
		var watchedOnly = watchedField.checked;
		var shown = 0;

		queryAll('.autoloadfix-advanced-option', table).forEach(function (row) {
			var searchable = (row.getAttribute('data-search') || '').toLowerCase();
			var matchesSearch = ! search || searchable.indexOf(search) !== -1;
			var matchesRisk = 'all' === risk || row.getAttribute('data-risk') === risk;
			var matchesWatched = ! watchedOnly || row.getAttribute('data-watched') === '1';
			var visible = matchesSearch && matchesRisk && matchesWatched;

			row.hidden = ! visible;
			if (visible) {
				shown++;
			}
		});

		var count = query('#autoloadfix-advanced-count');
		if (count) {
			var shownLabel = (window.AutoloadFixAdvanced && AutoloadFixAdvanced.shown) || 'shown';
			count.textContent = shown + ' ' + shownLabel;
		}

		var empty = query('#autoloadfix-advanced-empty');
		if (empty) {
			empty.hidden = shown !== 0;
		}
	}

	['autoloadfix-advanced-search', 'autoloadfix-advanced-risk', 'autoloadfix-advanced-watched'].forEach(function (id) {
		var field = document.getElementById(id);
		if (field) {
			field.addEventListener('autoloadfix-advanced-search' === id ? 'input' : 'change', filterAdvancedOptions);
		}
	});

	filterAdvancedOptions();

	var copyButton = query('#autoloadfix-advanced-copy');
	if (copyButton) {
		copyButton.addEventListener('click', function () {
			var text = copyButton.getAttribute('data-copy') || '';
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					copyButton.textContent = (window.AutoloadFixAdvanced && AutoloadFixAdvanced.copied) || 'Copied';
				});
			}
		});
	}

	if (window.AutoloadFixAdvanced && AutoloadFixAdvanced.readOnly) {
		queryAll('.autoloadfix-disable-button, .autoloadfix-restore-button').forEach(function (button) {
			button.disabled = true;
			button.classList.add('autoloadfix-readonly-disabled');
			button.title = 'AutoloadFix read-only mode is enabled';
		});
	}
}());
