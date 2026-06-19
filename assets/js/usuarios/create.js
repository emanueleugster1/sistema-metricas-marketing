// Feedback visual en vivo de las reglas de contrasena en el alta de usuario
// (solo ayuda visual; la validacion real es server-side con validarPassword()).
// Las reglas replican EXACTAMENTE includes/passwordHelper.php.
document.addEventListener('DOMContentLoaded', () => {
  const pass = document.getElementById('password');
  const lista = document.querySelector('.pw-requisitos');
  if (!pass || !lista) return;

  const reglas = {
    longitud: (v) => v.length >= 8 && v.length <= 20,
    mayuscula: (v) => /[A-Z]/.test(v),
    minuscula: (v) => /[a-z]/.test(v),
    numero: (v) => /[0-9]/.test(v),
    especial: (v) => /[^A-Za-z0-9]/.test(v),
  };

  const marcar = (regla, ok) => {
    const item = lista.querySelector(`[data-regla="${regla}"]`);
    if (!item) return;
    item.classList.toggle('cumplido', ok);
    const icono = item.querySelector('i');
    if (icono) icono.className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
  };

  const evaluar = () => {
    const valor = pass.value;
    Object.keys(reglas).forEach((regla) => marcar(regla, reglas[regla](valor)));
  };

  pass.addEventListener('input', evaluar);
  evaluar();
});
