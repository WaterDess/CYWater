(function () {
	'use strict';

	function initializeProfileFiles() {
		document.querySelectorAll('[data-cywater-profile-file-input]').forEach(function (input) {
			if (input.dataset.cywaterProfileFileReady === '1') {
				return;
			}
			input.dataset.cywaterProfileFileReady = '1';
			var control = input.closest('.cywater-profile-file__control');
			var output = control ? control.querySelector('[data-cywater-profile-file-name]') : null;
			if (!output) {
				return;
			}
			input.addEventListener('change', function () {
				output.textContent = input.files && input.files[0] ? input.files[0].name : 'No file chosen';
			});
		});
	}

	function arrangeAccountFields() {
		var grid = document.querySelector('#pmpro_member_profile_edit-account-information .pmpro_form_fields');
		var username = document.querySelector('[data-cywater-profile-username-field]');
		if (!grid || !username || username.dataset.cywaterProfileUsernameReady === '1') {
			return;
		}
		username.dataset.cywaterProfileUsernameReady = '1';
		grid.prepend(username);
		var display = grid.querySelector('.pmpro_form_field-display_name');
		if (display) {
			username.after(display);
		}
		if (display && !display.querySelector('.cywater-profile-display-hint')) {
			var hint = document.createElement('p');
			hint.className = 'pmpro_form_hint cywater-profile-display-hint';
			hint.textContent = 'Shown to other members. Leave blank to use your username.';
			display.append(hint);
		}
	}

	function initializeProfileControls() {
		arrangeAccountFields();
		initializeProfileFiles();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeProfileControls, { once: true });
	} else {
		initializeProfileControls();
	}
}());
