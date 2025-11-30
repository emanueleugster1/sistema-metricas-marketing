document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('#cliente-search');
  if (!input) return;
  input.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const q = input.value.trim();
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    window.location.href = `/index.php?vista=clientes/lista.php${params.toString() ? '&' + params.toString() : ''}`;
  });
});
