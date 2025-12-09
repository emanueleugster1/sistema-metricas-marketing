document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('dashboard-modal');
    const form = document.getElementById('dashboard-form');
    const guardarBtn = document.getElementById('guardar-widgets-btn');
    const cancelarBtn = document.getElementById('cancelar-widgets-btn');
    const closeBtn = document.getElementById('modal-close-btn');
    
    // Botones que pueden abrir el modal (Crear o Personalizar)
    const abrirBtns = document.querySelectorAll('#personalizar-dashboard-btn, #crear-dashboard-btn');
    
    if (!modal || !form || !guardarBtn) {
        console.warn('Elementos del modal de dashboard no encontrados (puede ser normal si no hay acciones disponibles)');
        return;
    }
    
    // Función para abrir modal
    function abrirModal() {
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Focus al primer elemento interactivo (input nombre o primer checkbox)
        const firstInput = modal.querySelector('input[type="text"], input[type="checkbox"]');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
    
    // Función para cerrar modal
    function cerrarModal() {
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
    }
    
    // Event listeners para botones de apertura
    abrirBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            abrirModal();
        });
    });
    
    // Cerrar con botón X
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            cerrarModal();
        });
    }
    
    // Cerrar con botón cancelar
    if (cancelarBtn) {
        cancelarBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            cerrarModal();
        });
    }
    
    // Cerrar con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            cerrarModal();
        }
    });
    
    // Cerrar al hacer clic en el overlay
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            cerrarModal();
        }
    });
    
    // Prevenir cierre al hacer clic dentro del contenido
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Manejar envío del formulario
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        guardarPersonalizacion();
    });
    
    function guardarPersonalizacion() {
        // Mostrar loading
        guardarBtn.disabled = true;
        const originalHTML = guardarBtn.innerHTML;
        guardarBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
        
        const formData = new FormData(form);
        
        // Si es acción AJAX, removemos el redirect para recibir JSON
        if (formData.has('redirect')) {
            formData.delete('redirect');
        }
        
        // Usar la acción del formulario
        const actionUrl = form.getAttribute('action');
        
        fetch(actionUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Cerrar modal
                cerrarModal();
                
                // Mostrar overlay de carga global si está disponible
                if (window.PageLoading) {
                    window.PageLoading.show();
                }
                
                // Recargar para ver cambios
                setTimeout(() => {
                    window.location.reload();
                }, 100);
            } else {
                alert('Error al guardar: ' + (data.error || 'Error desconocido'));
                guardarBtn.disabled = false;
                guardarBtn.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión. Inténtalo de nuevo.');
            guardarBtn.disabled = false;
            guardarBtn.innerHTML = originalHTML;
        });
    }
});
