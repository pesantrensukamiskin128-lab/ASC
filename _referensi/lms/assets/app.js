(() => {
  const sidebar = document.getElementById('sidebar');
  document.querySelector('[data-toggle-menu]')?.addEventListener('click', () => sidebar?.classList.toggle('open'));
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal]');
    if (opener) document.getElementById(opener.dataset.modal)?.showModal();
    const alertClose = event.target.closest('[data-alert] button');
    if (alertClose) alertClose.parentElement.remove();
  });
  document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm || 'Lanjutkan tindakan ini?')) event.preventDefault();
  }));
  document.querySelectorAll('[data-filter]').forEach((input) => input.addEventListener('input', () => {
    const target = document.querySelector(input.dataset.filter);
    const query = input.value.toLocaleLowerCase('id');
    target?.querySelectorAll('tbody tr').forEach((row) => row.hidden = !row.textContent.toLocaleLowerCase('id').includes(query));
  }));
  document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    tabs.addEventListener('click', (event) => {
      const button = event.target.closest('[data-tab]');
      if (!button) return;
      tabs.querySelectorAll('[data-tab]').forEach((el) => el.classList.toggle('active', el === button));
      document.querySelectorAll(`[data-tab-panel="${tabs.dataset.tabs}"]`).forEach((panel) => panel.hidden = panel.dataset.panel !== button.dataset.tab);
    });
  });
  setTimeout(() => document.querySelectorAll('[data-alert]').forEach((el) => el.remove()), 7000);
})();

