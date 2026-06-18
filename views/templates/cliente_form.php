<?php
$titulo = isset($isEdit) && $isEdit ? 'Editar cliente' : 'Nuevo cliente';
$subtitulo = isset($isEdit) && $isEdit
    ? 'Actualizá los datos y las conexiones del cliente.'
    : 'Cargá los datos del cliente y conectá sus plataformas.';
$actionUrl = isset($isEdit) && $isEdit
    ? '/controllers/clienteController.php?action=actualizar_con_credenciales'
    : '/controllers/clienteController.php?action=crear_con_credenciales';
$nombreVal = isset($cliente['nombre']) ? (string)$cliente['nombre'] : '';
$sectorVal = isset($cliente['sector']) ? (string)$cliente['sector'] : '';
$activoVal = isset($cliente['activo']) ? (int)$cliente['activo'] : 1;
$validadaMap = isset($validadaMap) && is_array($validadaMap) ? $validadaMap : [];
?>
<header class="page-header">
  <div>
    <h1><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <a class="btn btn-secondary" href="/clientes/lista"><i class="bi bi-arrow-left"></i> Volver</a>
</header>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form id="cliente-form" method="post" action="<?= $actionUrl ?>">
  <input type="hidden" name="redirect" value="1">
  <?php if (!empty($cliente) && isset($cliente['id'])): ?>
    <input type="hidden" name="id" value="<?= (int)$cliente['id'] ?>">
  <?php endif; ?>

  <div class="form-grid">

    <!-- Columna 1: datos del cliente -->
    <section class="form-card">
      <h2 class="form-card-title">Datos del cliente</h2>

      <div class="form-field">
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" type="text" required value="<?= htmlspecialchars($nombreVal, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-field">
        <label for="sector">Sector</label>
        <input id="sector" name="sector" type="text" value="<?= htmlspecialchars($sectorVal, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="form-field">
        <label for="activo">Estado</label>
        <select id="activo" name="activo">
          <option value="1" <?= $activoVal === 1 ? 'selected' : '' ?>>Activo</option>
          <option value="0" <?= $activoVal === 0 ? 'selected' : '' ?>>Inactivo</option>
        </select>
      </div>

      <h3 class="form-subtitle">Plataformas</h3>
      <div class="plataformas-list">
        <?php foreach ($plataformas as $p): ?>
          <?php
            $pid = (int)$p['id'];
            $checked = isset($credencialesMap[$pid]);
            $validada = !empty($validadaMap[$pid]);
            $nombrePlat = (string)$p['nombre'];
            $isMeta = strcasecmp($nombrePlat, 'Meta') === 0;
          ?>
          <label class="plataforma-item">
            <input type="checkbox" name="plataformas[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?> data-plataforma-nombre="<?= htmlspecialchars($nombrePlat, ENT_QUOTES, 'UTF-8') ?>" <?= $isMeta ? 'id="plataforma-meta-checkbox"' : '' ?>>
            <span class="plataforma-nombre"><?= htmlspecialchars($nombrePlat, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($checked && $validada): ?>
              <span class="badge badge-success"><i class="bi bi-check-lg"></i> Validada</span>
            <?php elseif ($checked): ?>
              <span class="badge badge-muted">Sin validar</span>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Columna 2: conexiones de plataforma -->
    <section class="form-card">
      <h2 class="form-card-title">Conexiones de plataforma</h2>

      <div id="meta-detect-wrap" class="form-field" style="display:none;">
        <label for="meta-access-token">Access token (Meta)</label>
        <input type="text" id="meta-access-token" placeholder="Token personal…">
        <div class="meta-detect-actions">
          <button type="button" id="meta-detect-btn" class="btn btn-primary"><i class="bi bi-search"></i> Detectar opciones</button>
        </div>
      </div>

      <div id="meta-selects" class="meta-selects" style="display:none;">
        <div class="form-field">
          <label for="meta-page-select">Página de Facebook</label>
          <select id="meta-page-select"></select>
        </div>
        <div class="form-field">
          <label for="meta-adaccount-select">Cuenta publicitaria</label>
          <select id="meta-adaccount-select"></select>
        </div>
      </div>

      <div id="credenciales-container" class="credenciales-section">
        <?php foreach ($plataformas as $p): ?>
          <?php
            $pid = (int)$p['id'];
            $campos = $camposPorPlataforma[$pid] ?? [];
            $checked = isset($credencialesMap[$pid]);
            $validada = !empty($validadaMap[$pid]);
          ?>
          <div class="cred-card" data-plataforma-id="<?= $pid ?>" style="<?= $checked ? '' : 'display:none;' ?>">
            <div class="cred-card-head">
              <h4 class="cred-title">Credenciales · <?= htmlspecialchars((string)$p['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
              <?php if ($checked && $validada): ?>
                <span class="badge badge-success"><i class="bi bi-check-lg"></i> Conectada</span>
              <?php elseif ($checked): ?>
                <span class="badge badge-muted">Sin validar</span>
              <?php endif; ?>
            </div>
            <?php foreach ($campos as $c): ?>
              <?php
                $nombreCampo = (string)$c['nombre_campo'];
                $labelCampo = (string)$c['label'];
                $val = $credencialesMap[$pid][$nombreCampo] ?? '';
              ?>
              <div class="form-field">
                <label><?= htmlspecialchars($labelCampo, ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" name="cred[<?= $pid ?>][<?= htmlspecialchars($nombreCampo, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($labelCampo, ENT_QUOTES, 'UTF-8') ?>…" <?= $checked ? '' : 'disabled' ?> readonly>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  </div>

  <div class="form-actions">
    <a class="btn btn-secondary" href="/clientes/lista">Cancelar</a>
    <button type="submit" class="btn btn-primary no-global-loading">Guardar</button>
  </div>
</form>
