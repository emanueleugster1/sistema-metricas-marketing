document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('#cliente-search');
  if (!input) return;
  input.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const q = input.value.trim();
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    window.location.href = `/clientes/lista${params.toString() ? '?' + params.toString() : ''}`;
  });
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('a[href^="/clientes/metricas/"]').forEach(link => {
    link.addEventListener('click', () => {
      if (window.PageLoading) window.PageLoading.show();
    });
  });
});
