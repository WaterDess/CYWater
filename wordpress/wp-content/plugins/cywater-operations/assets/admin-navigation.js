(function () {
	'use strict';

	var groupLabels = {
		content: 'Content',
		community: 'Community & programs',
		membership: 'Membership & accounts',
		system: 'Site system',
		work: 'My work'
	};

	function storageKey(group) {
		return 'cywater-admin-menu-' + group;
	}

	function storedExpanded(group) {
		try {
			var stored = window.localStorage.getItem(storageKey(group));
			if (stored !== null) {
				return stored === 'expanded';
			}
		} catch (error) {
			// A disabled local store must never make WordPress navigation unusable.
		}
		return group !== 'system';
	}

	function storeExpanded(group, expanded) {
		try {
			window.localStorage.setItem(storageKey(group), expanded ? 'expanded' : 'collapsed');
		} catch (error) {
			// The in-memory state remains functional when persistence is blocked.
		}
	}

	function setGroupExpanded(group, items, button, expanded) {
		items.forEach(function (item) {
			item.classList.toggle('cywater-menu-item--collapsed', !expanded);
		});
		button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		storeExpanded(group, expanded);
	}

	function buildMenuSections() {
		var menu = document.getElementById('adminmenu');
		if (!menu || menu.classList.contains('cywater-menu-enhanced')) {
			return;
		}

		var inserted = false;
		Object.keys(groupLabels).forEach(function (group) {
			var items = Array.prototype.slice.call(menu.children).filter(function (item) {
				return item.classList.contains('cywater-menu-group-' + group);
			});
			if (!items.length) {
				return;
			}

			var heading = document.createElement('li');
			heading.className = 'cywater-admin-menu-section cywater-admin-menu-section--' + group;
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'cywater-admin-menu-section__toggle';
			button.textContent = groupLabels[group];
			heading.appendChild(button);
			menu.insertBefore(heading, items[0]);
			inserted = true;

			var hasCurrent = items.some(function (item) {
				return item.classList.contains('current') || item.classList.contains('wp-has-current-submenu');
			});
			var expanded = hasCurrent || storedExpanded(group);
			setGroupExpanded(group, items, button, expanded);
			button.addEventListener('click', function () {
				setGroupExpanded(group, items, button, button.getAttribute('aria-expanded') !== 'true');
			});
		});

		if (inserted) {
			menu.classList.add('cywater-menu-enhanced');
		}
	}

	function closeActionMenus(except) {
		document.querySelectorAll('.cywater-user-actions-more.is-open').forEach(function (menu) {
			if (menu === except) {
				return;
			}
			menu.classList.remove('is-open');
			var toggle = menu.querySelector('.cywater-user-actions-more__toggle');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function buildUserActionMenus() {
		document.querySelectorAll('.cywater-user-actions-more__toggle').forEach(function (toggle) {
			var container = toggle.closest('.cywater-user-actions-more');
			if (!container) {
				return;
			}
			toggle.addEventListener('click', function (event) {
				event.stopPropagation();
				var willOpen = !container.classList.contains('is-open');
				closeActionMenus(container);
				container.classList.toggle('is-open', willOpen);
				toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			});
		});

		document.addEventListener('click', function () {
			closeActionMenus(null);
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeActionMenus(null);
			}
		});
	}

	function initialize() {
		buildMenuSections();
		buildUserActionMenus();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize);
	} else {
		initialize();
	}
}());
