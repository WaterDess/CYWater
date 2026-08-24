(() => {
	const pad = (value) => String(Math.max(0, value)).padStart(2, '0');
	document.querySelectorAll('[data-cywater-logo-countdown]').forEach((countdown) => {
		const deadline = Date.parse(countdown.dataset.deadline || '');
		if (!Number.isFinite(deadline)) return;
		const title = countdown.querySelector('[data-cywater-logo-countdown-title]');
		const status = countdown.querySelector('[data-cywater-logo-countdown-status]');
		const fields = Object.fromEntries(
			['days', 'hours', 'minutes', 'seconds'].map((unit) => [unit, countdown.querySelector(`[data-cywater-logo-countdown-unit="${unit}"]`)])
		);
		let completed = false;
		let timer = null;
		const update = () => {
			const remaining = Math.max(0, deadline - Date.now());
			const totalSeconds = Math.floor(remaining / 1000);
			const values = {
				days: Math.floor(totalSeconds / 86400),
				hours: Math.floor((totalSeconds % 86400) / 3600),
				minutes: Math.floor((totalSeconds % 3600) / 60),
				seconds: totalSeconds % 60,
			};
			Object.entries(values).forEach(([unit, value]) => {
				if (fields[unit]) fields[unit].textContent = pad(value);
			});
			if (remaining === 0 && !completed) {
				completed = true;
				countdown.classList.add('is-complete');
				if (title) title.textContent = 'Submissions are closed';
				if (status) status.textContent = 'Submissions are closed.';
				if (timer) window.clearInterval(timer);
			}
		};
		update();
		if (!completed) timer = window.setInterval(update, 1000);
	});

  document.querySelectorAll('[data-cywater-logo-file-input]').forEach((fileInput) => {
    const fileName = document.querySelector(`[data-cywater-logo-file-name="${fileInput.id}"]`);
    fileInput.addEventListener('change', () => {
      if (fileName) fileName.textContent = fileInput.files?.[0]?.name || 'No file chosen';
    });
  });
})();
