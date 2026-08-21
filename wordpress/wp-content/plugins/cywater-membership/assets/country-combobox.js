(() => {
  const enhance = (select) => {
    if (!select || select.dataset.cywaterCountryReady === '1') return;
    select.dataset.cywaterCountryReady = '1';

    const options = [...select.options]
      .filter((option) => option.value)
      .map((option) => ({ value: option.value, label: option.textContent.trim() }));
    if (!options.length) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'cywater-country';
    const input = document.createElement('input');
    const list = document.createElement('div');
    const selected = options.find((option) => option.value === select.value);
    const listId = `${select.id || 'cyw-country'}-options`;

    input.type = 'search';
    input.className = 'input cywater-country__input';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    input.placeholder = select.options[0]?.textContent?.trim() || 'Select a country or region';
    input.value = selected?.label || '';
    input.required = select.required;

    list.id = listId;
    list.className = 'cywater-country__list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    const close = () => {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    };

    const choose = (option) => {
      select.value = option.value;
      input.value = option.label;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    };

    const render = (query = '') => {
      const normalized = query.trim().toLocaleLowerCase();
      const matches = options.filter((option) => option.label.toLocaleLowerCase().includes(normalized));
      list.replaceChildren();
      matches.forEach((option, index) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.id = `${listId}-${index}`;
        item.className = 'cywater-country__option';
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.value === select.value));
        item.dataset.value = option.value;
        item.textContent = option.label;
        item.addEventListener('mousedown', (event) => event.preventDefault());
        item.addEventListener('click', () => choose(option));
        list.append(item);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('focus', () => render(input.value));
    input.addEventListener('input', () => {
      select.value = '';
      render(input.value);
    });
    input.addEventListener('keydown', (event) => {
      const items = [...list.querySelectorAll('[role="option"]')];
      const activeId = input.getAttribute('aria-activedescendant');
      let index = items.findIndex((item) => item.id === activeId);
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (list.hidden) render(input.value);
        const available = [...list.querySelectorAll('[role="option"]')];
        index = event.key === 'ArrowDown'
          ? Math.min(index + 1, available.length - 1)
          : Math.max(index < 0 ? available.length - 1 : index - 1, 0);
        const item = available[index];
        if (item) {
          input.setAttribute('aria-activedescendant', item.id);
          item.scrollIntoView({ block: 'nearest' });
        }
      } else if (event.key === 'Enter' && activeId) {
        const item = document.getElementById(activeId);
        const option = options.find((candidate) => candidate.value === item?.dataset.value);
        if (option) {
          event.preventDefault();
          choose(option);
        }
      } else if (event.key === 'Escape') {
        close();
      }
    });
    input.addEventListener('blur', () => {
      const current = options.find((option) => option.value === select.value);
      input.value = current?.label || '';
      close();
    });

    select.classList.add('cywater-country__native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    select.removeAttribute('required');
    select.parentNode.insertBefore(wrapper, select);
    wrapper.append(input, list, select);
  };

  document.querySelectorAll('select[name="cyw_country"]').forEach(enhance);
})();
