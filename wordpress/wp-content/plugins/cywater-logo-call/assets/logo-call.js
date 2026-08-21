(() => {
  document.querySelectorAll('[data-cywater-logo-file-input]').forEach((fileInput) => {
    const fileName = document.querySelector(`[data-cywater-logo-file-name="${fileInput.id}"]`);
    fileInput.addEventListener('change', () => {
      if (fileName) fileName.textContent = fileInput.files?.[0]?.name || 'No file chosen';
    });
  });
})();
