(() => {
  const input = document.querySelector('[data-cywater-logo-preview-input]');
  const preview = document.querySelector('[data-cywater-logo-preview]');
  const image = preview?.querySelector('img');
  if (!input || !preview || !image) return;

  let objectUrl = '';
  input.addEventListener('change', () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    const file = input.files?.[0];
    if (!file || !file.type.startsWith('image/')) {
      preview.hidden = true;
      image.removeAttribute('src');
      return;
    }
    objectUrl = URL.createObjectURL(file);
    image.src = objectUrl;
    image.alt = `Preview of ${file.name}`;
    preview.hidden = false;
  });
})();
