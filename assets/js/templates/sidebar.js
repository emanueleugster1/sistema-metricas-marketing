document.addEventListener('DOMContentLoaded', () => {
  const setLoading = (btn) => { if (!btn) return; if (btn.dataset.loading === '1') return; btn.dataset.loading = '1'; btn.classList.add('is-loading'); btn.disabled = true; };
  const clearLoading = (btn) => { if (!btn) return; btn.dataset.loading = ''; btn.classList.remove('is-loading'); btn.disabled = false; };
  window.ButtonLoading = { set: setLoading, clear: clearLoading };
  let lastSubmitButton = null;
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    if (btn.closest('.login-form')) return;
    if (btn.classList.contains('no-global-loading')) return;
    if (btn.disabled) return;
    
    // Si es botón submit, NO activar loading en click (esperar al evento submit del form)
    // Esto evita que se deshabilite el botón antes de que el form se envíe
    const type = (btn.getAttribute('type') || '').toLowerCase();
    if (type === 'submit') {
      lastSubmitButton = btn;
    } else {
      setLoading(btn);
    }
  });
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form && lastSubmitButton && form.contains(lastSubmitButton)) { setLoading(lastSubmitButton); }
  });

  const showPageLoading = () => {
    let overlay = document.querySelector('.page-loading-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'page-loading-overlay';
      const loader = document.createElement('div');
      loader.className = 'loader';
      overlay.appendChild(loader);
      document.body.appendChild(overlay);
    }
    document.body.classList.add('is-page-loading');
  };
  window.PageLoading = { show: showPageLoading };

  document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href') || '';
    if (href.includes('clientes/metricas.php')) { showPageLoading(); }
  });
});
