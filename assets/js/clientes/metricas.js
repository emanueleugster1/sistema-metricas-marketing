(() => {
  const root = document.getElementById('metricas-root');
  if (!root) return;
  const rawStr = root.getAttribute('data-insights') || '[]';
  let raw;
  try { raw = JSON.parse(rawStr); } catch { raw = []; }
  const labels = raw.length ? raw.map((r, i) => (r.date_start ?? i)).slice(0, 7) : ['1','2','3','4','5','6','7'];
  const data = raw.length ? raw.map(r => Number(r.impressions||0)).slice(0, 7) : [5,12,9,14,11,8,9];
  const cfg = {
    type: 'line',
    data: { labels, datasets: [{ data, borderColor: '#8B0000', tension: 0.25 }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { ticks: { display: false } }, x: { ticks: { display: false } } } }
  };
  const ig = document.getElementById('chartIg');
  const fb = document.getElementById('chartFb');
  if (ig) new Chart(ig, cfg);
  if (fb) new Chart(fb, cfg);
})();

//
