(function () {
	'use strict';
	function initializeCountdowns() {
		document.querySelectorAll('[data-cywater-meeting-countdown]').forEach(function (countdown) {
			var deadline = Date.parse(countdown.getAttribute('data-deadline') || '');
			var serverNow = Date.parse(countdown.getAttribute('data-server-now') || '');
			if (!Number.isFinite(deadline) || !Number.isFinite(serverNow)) return;
			var started = Date.now();
			var units = {};
			countdown.querySelectorAll('[data-cywater-meeting-countdown-unit]').forEach(function (node) { units[node.getAttribute('data-cywater-meeting-countdown-unit')] = node; });
			var status = countdown.querySelector('[data-cywater-meeting-countdown-status]');
			var title = countdown.querySelector('[data-cywater-meeting-countdown-title]');
			var reloading = false;
			function pad(value) { return String(Math.max(0, value)).padStart(2, '0'); }
			function update() {
				var remaining = Math.max(0, deadline - (serverNow + (Date.now() - started)));
				var totalSeconds = Math.floor(remaining / 1000);
				var values = { days: Math.floor(totalSeconds / 86400), hours: Math.floor((totalSeconds % 86400) / 3600), minutes: Math.floor((totalSeconds % 3600) / 60), seconds: totalSeconds % 60 };
				Object.keys(values).forEach(function (key) { if (units[key]) units[key].textContent = pad(values[key]); });
				if (status) status.textContent = values.days + ' days, ' + values.hours + ' hours, ' + values.minutes + ' minutes and ' + values.seconds + ' seconds remaining.';
				if (remaining <= 0) {
					countdown.classList.add('is-complete');
					if (title) title.textContent = countdown.getAttribute('data-complete-title') || 'Milestone reached';
					if (!reloading) { reloading = true; window.setTimeout(function () { window.location.reload(); }, 1500); }
					return;
				}
				window.setTimeout(update, 1000);
			}
			update();
		});
	}
	initializeCountdowns();
	var form = document.querySelector('[data-cywater-meeting-form]');
	if (!form) return;
	var category = form.querySelector('[data-cywater-meeting-category]');
	var plan = form.querySelector('[data-cywater-meeting-plan]');
	var presentationFields = form.querySelectorAll('[data-cywater-meeting-title-field]');
	var titleInput = presentationFields.length ? presentationFields[0].querySelector('input') : null;
	var proof = form.querySelector('[data-cywater-meeting-proof]');
	var output = form.querySelector('[data-cywater-meeting-fee]');
	var rates = JSON.parse(form.getAttribute('data-rates') || '{}');
	var memberKind = form.getAttribute('data-member-kind') || 'none';
	var period = form.getAttribute('data-early') === '1' ? 'early' : 'standard';
	var isMember = ['student', 'professional', 'lifetime'].indexOf(memberKind) !== -1;
	function enhanceCountry(select) {
		if (!select || select.dataset.cywaterCountryReady === '1') return;
		select.dataset.cywaterCountryReady = '1';
		var options = Array.prototype.slice.call(select.options).filter(function (option) { return option.value; }).map(function (option) { return { value: option.value, label: option.textContent.trim() }; });
		if (!options.length) return;
		var wrapper = document.createElement('div');
		var input = document.createElement('input');
		var list = document.createElement('div');
		var selected = options.find(function (option) { return option.value === select.value; });
		var listId = (select.id || 'cywater-meeting-country') + '-options';
		wrapper.className = 'cywater-country';
		input.type = 'search';
		input.className = 'input cywater-country__input';
		input.autocomplete = 'off';
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', listId);
		input.placeholder = select.options[0] ? select.options[0].textContent.trim() : 'Select a country or region';
		input.value = selected ? selected.label : '';
		input.required = select.required;
		list.id = listId;
		list.className = 'cywater-country__list';
		list.setAttribute('role', 'listbox');
		list.hidden = true;
		function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); }
		function validate() { input.setCustomValidity(select.value ? '' : 'Select a country or region from the list.'); }
		function choose(option) { select.value = option.value; input.value = option.label; validate(); select.dispatchEvent(new Event('change', { bubbles: true })); close(); }
		function render(query) {
			var normalized = (query || '').trim().toLocaleLowerCase();
			var matches = options.filter(function (option) { return option.label.toLocaleLowerCase().indexOf(normalized) !== -1; });
			list.replaceChildren();
			matches.forEach(function (option, index) {
				var item = document.createElement('button');
				item.type = 'button'; item.id = listId + '-' + index; item.className = 'cywater-country__option'; item.setAttribute('role', 'option'); item.setAttribute('aria-selected', String(option.value === select.value)); item.dataset.value = option.value; item.textContent = option.label;
				item.addEventListener('mousedown', function (event) { event.preventDefault(); });
				item.addEventListener('click', function () { choose(option); });
				list.append(item);
			});
			list.hidden = false; input.setAttribute('aria-expanded', 'true');
		}
		input.addEventListener('focus', function () { render(input.value); });
		input.addEventListener('input', function () { select.value = ''; validate(); render(input.value); });
		input.addEventListener('keydown', function (event) {
			var items = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
			var activeId = input.getAttribute('aria-activedescendant');
			var index = items.findIndex(function (item) { return item.id === activeId; });
			if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault(); if (list.hidden) render(input.value);
				items = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
				index = event.key === 'ArrowDown' ? Math.min(index + 1, items.length - 1) : Math.max(index < 0 ? items.length - 1 : index - 1, 0);
				if (items[index]) { input.setAttribute('aria-activedescendant', items[index].id); items[index].scrollIntoView({ block: 'nearest' }); }
			} else if (event.key === 'Enter' && activeId) {
				var active = document.getElementById(activeId);
				var option = options.find(function (candidate) { return active && candidate.value === active.dataset.value; });
				if (option) { event.preventDefault(); choose(option); }
			} else if (event.key === 'Escape') close();
		});
		input.addEventListener('blur', function () { var current = options.find(function (option) { return option.value === select.value; }); input.value = current ? current.label : ''; validate(); close(); });
		select.classList.add('cywater-country__native'); select.tabIndex = -1; select.setAttribute('aria-hidden', 'true'); select.removeAttribute('required');
		select.parentNode.insertBefore(wrapper, select); wrapper.append(input, list, select);
		validate();
	}
	function enhanceFile(input) {
		var output = form.querySelector('[data-cywater-meeting-file-name="' + input.id + '"]');
		if (!output) return;
		input.addEventListener('change', function () { output.textContent = input.files && input.files[0] ? input.files[0].name : 'No file chosen'; });
	}
	function rateKey() {
		if (category.value === 'corporate' || category.value === 'invited') return category.value;
		if (category.value === 'student') return isMember ? 'student_member' : 'student_nonmember';
		return isMember ? 'regular_member' : 'regular_nonmember';
	}
	function update() {
		var rate = rates[rateKey()] || {};
		var cny = Number(rate[period + '_cny'] || 0);
		var usd = Number(rate[period + '_usd'] || 0);
		output.textContent = cny === 0 ? 'Fee waived, subject to organizer verification' : 'CNY ' + cny.toLocaleString() + ' (approximately USD ' + usd.toLocaleString() + ')';
		if (proof) proof.required = category.value === 'student';
		var presents = plan.value !== 'attend';
		presentationFields.forEach(function (field) { field.hidden = !presents; });
		if (titleInput) titleInput.required = presents;
	}
	category.addEventListener('change', update);
	plan.addEventListener('change', update);
	enhanceCountry(form.querySelector('[data-cywater-meeting-country]'));
	form.querySelectorAll('[data-cywater-meeting-file-input]').forEach(enhanceFile);
	update();
}());
