document.addEventListener('DOMContentLoaded', () => {
  const tokenInput = document.querySelector('#meta-access-token');
  const detectBtn = document.querySelector('#meta-detect-btn');
  const selectsWrap = document.querySelector('#meta-selects');
  const pageSelect = document.querySelector('#meta-page-select');
  const adSelect = document.querySelector('#meta-adaccount-select');
  const igInput = document.querySelector('#meta-instagram-id');
  const metaCheckbox = document.querySelector('#plataforma-meta-checkbox');
  if (!detectBtn) return;

  const parseMetaPid = () => {
    const accInput = document.querySelector('input[name^="cred["][name$="[access_token]"]');
    if (accInput) {
      const m = accInput.name.match(/^cred\[(\d+)\]\[access_token\]$/);
      if (m) return parseInt(m[1], 10);
    }
    if (metaCheckbox && metaCheckbox.value) {
      const v = parseInt(metaCheckbox.value, 10);
      if (!Number.isNaN(v)) return v;
    }
    return null;
  };

  const setCredField = (pid, field, value) => {
    const input = document.querySelector(`input[name="cred[${pid}][${field}]"]`);
    if (input) input.value = value || '';
  };

  detectBtn.addEventListener('click', async () => {
    const token = tokenInput.value.trim();
    if (!token) { alert('Ingrese access_token'); if (window.ButtonLoading) window.ButtonLoading.clear(detectBtn); return; }
    const url = new URL('../../controllers/clienteController.php', window.location.origin);
    url.searchParams.set('action', 'detectar_opciones');
    const fd = new FormData();
    fd.set('access_token', token);
    const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) { alert('Error al detectar: ' + (data.error || '')); if (window.ButtonLoading) window.ButtonLoading.clear(detectBtn); return; }
    const payload = data.data || {};
    const pages = payload.pages || [];
    const adaccounts = payload.adaccounts || [];
    const igMap = payload.instagram_business_by_page || {};
    pageSelect.innerHTML = '<option value="">Selecciona página...</option>' + pages.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
    adSelect.innerHTML = '<option value="">Selecciona cuenta publicitaria...</option>' + adaccounts.map(a => `<option value="${a.id}">${a.name || a.id} ${a.currency ? '(' + a.currency + ')' : ''}</option>`).join('');
    selectsWrap.style.display = '';
    if (window.ButtonLoading) window.ButtonLoading.clear(detectBtn);
    const initialPid = parseMetaPid();
    if (initialPid !== null) {
      setCredField(initialPid, 'access_token', token);
    }
    pageSelect.addEventListener('change', () => {
      const pid = parseMetaPid();
      if (pid === null) return;
      setCredField(pid, 'page_id', pageSelect.value);
      const igId = igMap[pageSelect.value] || '';
      if (igInput) { igInput.value = igId || ''; }
      setCredField(pid, 'instagram_business_account_id', igId || '');
    });
    adSelect.addEventListener('change', () => {
      const pid = parseMetaPid();
      if (pid === null) return;
      setCredField(pid, 'ad_account_id', adSelect.value);
    });
  });
});
