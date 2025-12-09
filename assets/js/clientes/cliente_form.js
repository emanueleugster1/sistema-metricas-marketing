document.addEventListener('DOMContentLoaded', () => {
  console.log('Script cliente_form.js cargado 🚀');
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

// ===========================================
// MANEJO DEL SUBMIT DEL FORMULARIO  
// ===========================================
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#cliente-form');
  
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      
      // Mostrar loading
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Guardando...';
      
      try {
        const formData = new FormData(form);
        
        // Debug: Log data being sent
        const dataObj = {};
        formData.forEach((value, key) => { dataObj[key] = value; });
        console.log('Enviando datos al servidor:', dataObj);

        const response = await fetch(form.action, {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        console.log('Respuesta del servidor:', result);
        
        if (result.success) {
          // Redirigir a lista de clientes
          window.location.href = '/index.php?vista=clientes/lista.php';
        } else {
          // Mostrar error
          alert('Error: ' + (result.error || 'Error desconocido'));
        }
        
      } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión. Intente nuevamente.');
      } finally {
        // Restaurar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }
});
