document.addEventListener('DOMContentLoaded', () => {
    // El id viene en el path de la URL limpia (/clientes/metricas/{id});
    // se conserva el query string como respaldo por compatibilidad.
    const params = new URLSearchParams(window.location.search);
    const pathMatch = window.location.pathname.match(/\/clientes\/metricas\/(\d+)/);
    const clienteId = (pathMatch && pathMatch[1]) || params.get('cliente_id');
    // CAMBIO 1: rango del selector (whitelist {30,60,90}, default 30).
    const diasParam = parseInt(params.get('dias'), 10);
    const dias = [28, 60, 90].includes(diasParam) ? diasParam : 28;

    // El selector recarga con ?dias=N (re-render server-side + datos en vivo).
    const selector = document.getElementById('rango-dias-select');
    if (selector) {
        selector.value = String(dias);
        selector.addEventListener('change', () => {
            if (window.PageLoading) window.PageLoading.show();
            const next = new URLSearchParams(window.location.search);
            next.set('dias', selector.value);
            window.location.search = next.toString();
        });
    }

    if (clienteId) {
        fetch(`/controllers/metricaController.php?action=widgets_data&cliente_id=${clienteId}&dias=${dias}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderizarWidgets(data.metricasHistoricas, data.widgets, data.liveAds || {}, clienteId);
                } else {
                    console.error('Error al cargar datos:', data.error);
                }
            })
            .catch(err => console.error('Error de red:', err));
    }
});

function renderizarWidgets(metricas, widgets, liveAds = {}, clienteId = null) {
    widgets.forEach(widget => {
        // CAMBIO 1: tarjeta tipo 'metric' de ads con lectura EN VIVO de la ventana
        // elegida (60/90). Si hay valor live para esta métrica, se muestra directo;
        // la serie histórica (charts) queda intacta.
        if (widget.tipo_visualizacion === 'metric' && liveAds[widget.metrica_principal]) {
            mostrarMetricaLive(widget.widget_id, liveAds[widget.metrica_principal]);
            return;
        }

        // Feed de Instagram: fetch en vivo, nunca hay datos históricos para este tipo.
        // DEBE ir antes de filtrarDatosPorWidget para no caer en el early-return de "Sin datos".
        if (widget.tipo_visualizacion === 'feed') {
            cargarFeedIG(widget.widget_id, clienteId);
            return;
        }

        // Filtrar y preparar datos para este widget
        const metricaData = filtrarDatosPorWidget(metricas, widget);
        
        // Si no hay datos, mostramos estado vacío y quitamos loader
        if (!metricaData.valores.length) {
            removeLoader(widget.widget_id);
            if (widget.tipo_visualizacion === 'metric' || widget.tipo_visualizacion === 'table') {
                 const container = document.getElementById(`${widget.tipo_visualizacion}-${widget.widget_id}`);
                 if (container) container.innerHTML = '<div class="text-muted small p-3">Sin datos</div>';
            }
            if (widget.tipo_visualizacion === 'chart') {
                const canvas = document.getElementById(`chart-${widget.widget_id}`);
                if (canvas) canvas.parentElement.outerHTML = '<div class="metric-container"><div class="big-number"><div class="text-muted small p-3">Sin datos</div></div></div>';
            }
            return;
        }

        switch(widget.tipo_visualizacion) {
            case 'chart':
                crearGraficoLinea(widget.widget_id, metricaData);
                break;
            case 'gauge':
                crearGraficoGauge(widget.widget_id, metricaData);
                break;
            case 'metric':
                mostrarMetricaConTendencia(widget.widget_id, metricaData);
                break;
            case 'table':
                crearTabla(widget.widget_id, metricaData);
                break;
        }
    });
}

// CAMBIO 1: render de una tarjeta 'metric' a partir del valor en vivo de la ventana.
// Sin flecha de tendencia: es un agregado de la ventana, no una serie con punto previo.
function mostrarMetricaLive(widgetId, live) {
    const container = document.getElementById(`metric-${widgetId}`);
    if (!container) return;
    const num = parseFloat(live.valor);
    const formattedVal = isNaN(num)
        ? '—'
        : new Intl.NumberFormat('es-ES', { maximumFractionDigits: 2 }).format(num);
    const unidad = live.unidad ? ' ' + live.unidad : '';
    container.innerHTML = `${formattedVal}${unidad}`;
    removeLoader(widgetId);
}

function removeLoader(widgetId) {
    const loader = document.getElementById(`loader-${widgetId}`);
    if (loader) {
        loader.style.display = 'none';
    }
}

function filtrarDatosPorWidget(metricas, widget) {
    const nombreMetrica = widget.metrica_principal;
    
    // Filtrar por nombre de métrica
    // Nota: metricasHistoricas trae todas las métricas del cliente
    const filtrados = metricas.filter(m => m.nombre_metrica === nombreMetrica);
    
    // Ordenar por fecha ascendente para gráficos
    filtrados.sort((a, b) => new Date(a.fecha_metrica) - new Date(b.fecha_metrica));
    
    const fechas = filtrados.map(m => m.fecha_metrica);
    const valores = filtrados.map(m => parseFloat(m.valor));
    
    return {
        fechas,
        valores,
        label: widget.nombre,
        unidad: filtrados[0]?.unidad || '',
        metrica: nombreMetrica
    };
}

function crearGraficoLinea(widgetId, datos) {
    const canvas = document.getElementById(`chart-${widgetId}`);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: datos.fechas,
            datasets: [{
                label: datos.label,
                data: datos.valores,
                borderColor: '#8B0000', // Primary color
                backgroundColor: 'rgba(139, 0, 0, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 2,
                pointHoverRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + (datos.unidad ? ' ' + datos.unidad : '');
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: false // Ocultar eje X para limpieza visual como en el diseño original
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        maxTicksLimit: 5
                    }
                }
            }
        }
    });
    
    removeLoader(widgetId);
}

function crearGraficoGauge(widgetId, datos) {
    const canvas = document.getElementById(`gauge-${widgetId}`);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Tomar el último valor
    const val = datos.valores[datos.valores.length - 1] || 0;
    // Calcular máximo simple (el doble del valor o 100 si es porcentaje)
    let max = val * 2 || 100;
    if (datos.unidad === '%') max = 100;
    
    const resto = Math.max(0, max - val);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Valor', 'Restante'],
            datasets: [{
                data: [val, resto],
                backgroundColor: ['#8B0000', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            }
        }
    });
    
    // Agregar texto en el centro (opcional, requeriría plugin o superposición HTML)
    removeLoader(widgetId);
}

// Métricas acumulativas: se suman todas las semanas del período para el número grande.
// Métricas de tasa/snapshot: se muestra el último valor (comportamiento anterior).
const METRICAS_ACUMULATIVAS = new Set([
    'impressions', 'reach', 'clicks', 'spend', 'inline_link_clicks',
    'page_views_total', 'page_post_engagements'
]);

function mostrarMetricaConTendencia(widgetId, datos) {
    const container = document.getElementById(`metric-${widgetId}`);
    if (!container) return;

    const esAcumulativa = METRICAS_ACUMULATIVAS.has(datos.metrica);
    const n = datos.valores.length;

    // Número grande: suma del período para acumulativas, último punto para el resto.
    const displayed = esAcumulativa
        ? datos.valores.reduce((acc, v) => acc + v, 0)
        : (datos.valores[n - 1] || 0);

    // Flecha: última semana vs penúltima (semana-a-semana) para todas las métricas.
    const last = datos.valores[n - 1];
    const prev = datos.valores[n - 2];

    let trend = 'equal';
    let icon = 'dash-lg';
    if (prev !== undefined) {
        if (last > prev)       { trend = 'up';   icon = 'arrow-up-short'; }
        else if (last < prev)  { trend = 'down';  icon = 'arrow-down-short'; }
    }

    const formattedVal = new Intl.NumberFormat('es-ES', {
        maximumFractionDigits: 2
    }).format(displayed);

    container.innerHTML = `
        ${formattedVal}${datos.unidad ? ' ' + datos.unidad : ''}
        <span class="trend-icon ${trend}"><i class="bi bi-${icon}"></i></span>
    `;
}

function crearTabla(widgetId, datos) {
    const container = document.getElementById(`table-${widgetId}`);
    if (!container) return;
    
    let html = '<table class="table table-sm" style="width:100%; font-size: 0.85rem;">';
    html += '<thead><tr><th class="text-center">Fecha</th><th class="text-center">Valor</th></tr></thead>';
    html += '<tbody>';
    
    // Mostrar últimos 5 datos en orden descendente
    const len = datos.fechas.length;
    for (let i = len - 1; i >= Math.max(0, len - 5); i--) {
        const fecha = datos.fechas[i];
        const val = datos.valores[i];
        const formattedVal = new Intl.NumberFormat('es-ES').format(val);
        
        html += `<tr>
            <td class="text-center">${fecha}</td>
            <td class="text-center">${formattedVal} ${datos.unidad}</td>
        </tr>`;
    }
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function cargarFeedIG(widgetId, clienteId) {
    const container = document.getElementById(`feed-${widgetId}`);
    if (!container) return;

    // Clases Bootstrap Icons por tipo de media (mismo sistema que el resto del proyecto)
    const tipoIconClass = {
        VIDEO:          'bi-camera-video',
        CAROUSEL_ALBUM: 'bi-images',
        IMAGE:          'bi-image'
    };

    fetch(`/controllers/metricaController.php?action=feed_ig&cliente_id=${clienteId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.posts || !data.posts.length) {
                container.innerHTML = '<div class="feed-empty">Sin posts disponibles</div>';
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'feed-grid';

            data.posts.forEach(p => {
                const card = document.createElement('div');
                card.className = 'feed-card';

                // --- Área de imagen ---
                const imgWrap = document.createElement('div');
                imgWrap.className = 'feed-card-img';

                // VIDEO → thumbnail_url; IMAGE/CAROUSEL_ALBUM → media_url
                const imgSrc = p.media_type === 'VIDEO' ? (p.thumbnail_url || '') : (p.media_url || '');
                if (imgSrc) {
                    const img = document.createElement('img');
                    img.src = imgSrc;
                    img.alt = '';
                    img.loading = 'lazy';
                    imgWrap.appendChild(img);
                } else {
                    imgWrap.classList.add('feed-card-img-placeholder');
                    imgWrap.innerHTML = '<i class="bi bi-image" aria-hidden="true"></i>';
                }

                // Badge de tipo (icono Bootstrap Icons, HTML de confianza — solo la clase varía)
                const badge = document.createElement('span');
                badge.className = 'feed-card-badge';
                badge.innerHTML = `<i class="bi ${tipoIconClass[p.media_type] || 'bi-file-image'}" aria-hidden="true"></i>`;
                imgWrap.appendChild(badge);

                // Link "Ver en Instagram" superpuesto sobre la imagen
                if (p.permalink) {
                    const link = document.createElement('a');
                    link.href = p.permalink;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.className = 'feed-card-overlay';
                    link.title = 'Ver en Instagram';
                    link.innerHTML = '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>';
                    imgWrap.appendChild(link);
                }

                // --- Cuerpo de la card ---
                const body = document.createElement('div');
                body.className = 'feed-card-body';

                // Caption: textContent evita XSS — viene de Meta, no es HTML de confianza
                const captionP = document.createElement('p');
                captionP.className = 'feed-caption';
                const rawCaption = p.caption || '';
                captionP.textContent = rawCaption.length > 80
                    ? rawCaption.slice(0, 80) + '…'
                    : (rawCaption || '(sin texto)');

                // Stats (fecha · likes · comentarios)
                const metaDiv = document.createElement('div');
                metaDiv.className = 'feed-meta';

                const fecha = new Date(p.timestamp).toLocaleDateString('es-ES', {
                    day: '2-digit', month: 'short', year: 'numeric'
                });
                const fechaSpan = document.createElement('span');
                fechaSpan.className = 'feed-meta-date';
                fechaSpan.textContent = fecha;

                // innerHTML seguro: solo inyectamos números y clases de iconos conocidos
                const statsSpan = document.createElement('span');
                statsSpan.className = 'feed-meta-stats';
                statsSpan.innerHTML =
                    `<i class="bi bi-heart" aria-hidden="true"></i> ${parseInt(p.like_count, 10) || 0}` +
                    ` <i class="bi bi-chat" aria-hidden="true"></i> ${parseInt(p.comments_count, 10) || 0}`;

                metaDiv.appendChild(fechaSpan);
                metaDiv.appendChild(statsSpan);
                body.appendChild(captionP);
                body.appendChild(metaDiv);
                card.appendChild(imgWrap);
                card.appendChild(body);
                grid.appendChild(card);
            });

            container.innerHTML = '';
            container.appendChild(grid);
        })
        .catch(() => {
            container.innerHTML = '<div class="feed-empty">Error al cargar el feed</div>';
        });
}
