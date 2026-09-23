(function () {
	'use strict';
	function initialize() {
		document.querySelectorAll('[data-cywater-best-paper-form]').forEach(function (form) {
			if (form.dataset.cywaterBestPaperReady) return;
			form.dataset.cywaterBestPaperReady = '1';
			form.querySelectorAll('[data-cywater-best-paper-file]').forEach(function (input) {
				var label = input.parentElement.querySelector('[data-cywater-best-paper-filename]');
				var emptyLabel = label ? label.textContent : 'No file chosen';
				input.addEventListener('change', function () {
					var file = input.files && input.files[0];
					if (label) label.textContent = file ? file.name : emptyLabel;
					var error = '';
					if (file && file.size > 20 * 1024 * 1024) error = 'Choose a PDF file no larger than 20 MB.';
					else if (file && !/\.pdf$/i.test(file.name)) error = 'Choose a PDF file.';
					input.setCustomValidity(error);
					input.setAttribute('aria-invalid', error ? 'true' : 'false');
					if (error) input.reportValidity();
				});
			});
			form.addEventListener('invalid', function () { form.classList.add('was-validated'); }, true);
		});
		var notice = document.querySelector('[data-cywater-best-paper-notice]');
		if (notice && window.location.search.indexOf('bp_notice=') !== -1) notice.focus({ preventScroll: true });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
	else initialize();
}());
