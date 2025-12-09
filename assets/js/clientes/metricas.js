document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const clienteId = params.get('cliente_id');
    const dias = 30;

    if (clienteId) {
        fetch(`controllers/metricaController.php?action=widgets_data&cliente_id=${clienteId}&dias=${dias}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderizarWidgets(data.metricasHistoricas, data.widgets);
                } else {
                    console.error('Error al cargar datos:', data.error);
                }
            })
            .catch(err => console.error('Error de red:', err));
    }
});

function renderizarWidgets(metricas, widgets) {
    widgets.forEach(widget => {
        // Filtrar y preparar datos para este widget
        const metricaData = filtrarDatosPorWidget(metricas, widget);
        
        // Si no hay datos, mostramos estado vacío y quitamos loader
        if (!metricaData.valores.length) {
            removeLoader(widget.widget_id);
            if (widget.tipo_visualizacion === 'metric' || widget.tipo_visualizacion === 'table') {
                 const container = document.getElementById(`${widget.tipo_visualizacion}-${widget.widget_id}`);
                 if (container) container.innerHTML = '<div class="text-muted small p-3">Sin datos</div>';
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
        unidad: filtrados[0]?.unidad || ''
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

function mostrarMetricaConTendencia(widgetId, datos) {
    const container = document.getElementById(`metric-${widgetId}`);
    if (!container) return;
    
    // Último valor
    const current = datos.valores[datos.valores.length - 1] || 0;
    // Penúltimo valor
    const prev = datos.valores[datos.valores.length - 2];
    
    let trend = 'equal';
    let icon = 'dash-lg';
    
    if (prev !== undefined) {
        if (current > prev) {
            trend = 'up';
            icon = 'arrow-up-short';
        } else if (current < prev) {
            trend = 'down';
            icon = 'arrow-down-short';
        }
    }
    
    const colorClass = trend === 'up' ? 'text-success' : (trend === 'down' ? 'text-danger' : 'text-secondary');
    // Mapear clases de color del CSS (var(--color-success) etc)
    // En metricas.css: .trend-icon.up { color: var(--color-success); }
    const trendClass = trend; 
    
    const formattedVal = new Intl.NumberFormat('es-ES', { 
        maximumFractionDigits: 2 
    }).format(current);
    
    container.innerHTML = `
        ${formattedVal}${datos.unidad ? ' ' + datos.unidad : ''}
        <span class="trend-icon ${trendClass}"><i class="bi bi-${icon}"></i></span>
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
