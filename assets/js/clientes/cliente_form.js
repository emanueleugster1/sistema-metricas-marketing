document.addEventListener('DOMContentLoaded', () => {
  const checkboxes = document.querySelectorAll('.plataformas-list input[type="checkbox"]');
  const metaCheckbox = document.querySelector('#plataforma-meta-checkbox');
  const metaWrap = document.querySelector('#meta-detect-wrap');
  const metaSelects = document.querySelector('#meta-selects');
  const toggle = (pid, checked) => {
    const card = document.querySelector(`.cred-card[data-plataforma-id="${pid}"]`);
    if (!card) return;
    card.style.display = checked ? '' : 'none';
    card.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = !checked; });
  };
  checkboxes.forEach(cb => {
    const pid = cb.value;
    toggle(pid, cb.checked);
    cb.addEventListener('change', (e) => toggle(pid, e.target.checked));
  });

  const syncMetaVisibility = () => {
    if (!metaWrap || !metaCheckbox) return;
    const show = !!metaCheckbox.checked;
    metaWrap.style.display = show ? '' : 'none';
    if (!show && metaSelects) metaSelects.style.display = 'none';
  };
  if (metaCheckbox) {
    syncMetaVisibility();
    metaCheckbox.addEventListener('change', syncMetaVisibility);
  }
});
