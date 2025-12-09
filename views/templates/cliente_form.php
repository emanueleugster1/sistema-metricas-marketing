<?php
$titulo = isset($isEdit) && $isEdit ? 'Editar cliente' : 'Nuevo cliente';
$actionUrl = isset($isEdit) && $isEdit ? '/controllers/clienteController.php?action=actualizar_con_credenciales' : '/controllers/clienteController.php?action=crear_con_credenciales';
$nombreVal = isset($cliente['nombre']) ? (string)$cliente['nombre'] : '';
$sectorVal = isset($cliente['sector']) ? (string)$cliente['sector'] : '';
$activoVal = isset($cliente['activo']) ? (int)$cliente['activo'] : 1;
?>
<main class="content-with-sidebar clientes-create">
  <div class="create-header">
    <a class="btn-back" href="/index.php?vista=clientes/lista.php">Atrás</a>
  </div>
  <div class="page-card">
  <form id="cliente-form" method="post" action="<?= $actionUrl ?>">
    <input type="hidden" name="redirect" value="1">
    <?php if (!empty($cliente) && isset($cliente['id'])): ?>
      <input type="hidden" name="id" value="<?= (int)$cliente['id'] ?>">
    <?php endif; ?>
    <div class="form-grid">
      <div class="form-column">
        <div class="form-title"><?= $titulo ?></div>
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
        <div class="plataformas-title">Plataformas</div>
        <div class="plataformas-list">
          <?php foreach ($plataformas as $p): ?>
            <?php $pid = (int)$p['id']; $checked = isset($credencialesMap[$pid]); $nombrePlat = (string)$p['nombre']; $isMeta = strcasecmp($nombrePlat, 'Meta') === 0; ?>
            <label class="plataforma-item">
              <input type="checkbox" name="plataformas[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?> data-plataforma-nombre="<?= htmlspecialchars($nombrePlat, ENT_QUOTES, 'UTF-8') ?>" <?= $isMeta ? 'id="plataforma-meta-checkbox"' : '' ?>>
              <span><?= htmlspecialchars($nombrePlat, ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-column">
        <div id="meta-detect-wrap" class="form-field" style="display:none;">
          <label>Access token (Meta)</label>
          <input type="text" id="meta-access-token" placeholder="Token personal...">
          <div style="margin-top: var(--spacing-sm);">
            <button type="button" id="meta-detect-btn" class="btn btn-primary">Detectar opciones disponibles</button>
          </div>
        </div>
        <div id="meta-selects" class="meta-selects" style="display:none;">
          <div class="form-field">
            <label>Selecciona página de Facebook</label>
            <select id="meta-page-select"></select>
          </div>
          <div class="form-field">
            <label>Selecciona cuenta publicitaria</label>
            <select id="meta-adaccount-select"></select>
          </div>
        </div>
        <div id="credenciales-container" class="credenciales-section">
          <?php foreach ($plataformas as $p): ?>
            <?php $pid = (int)$p['id']; $campos = $camposPorPlataforma[$pid] ?? []; $checked = isset($credencialesMap[$pid]); ?>
            <div class="cred-card" data-plataforma-id="<?= $pid ?>" style="<?= $checked ? '' : 'display:none;' ?>">
              <div class="cred-title">Credenciales <?= htmlspecialchars((string)$p['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php foreach ($campos as $c): ?>
                <?php 
                  $nombreCampo = (string)$c['nombre_campo'];
                  $labelCampo = (string)$c['label'];
                  $val = $credencialesMap[$pid][$nombreCampo] ?? ''; 
                ?>
                <div class="form-field">
                  <label><?= htmlspecialchars($labelCampo, ENT_QUOTES, 'UTF-8') ?></label>
                  <input type="text" name="cred[<?= $pid ?>][<?= htmlspecialchars($nombreCampo, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($labelCampo, ENT_QUOTES, 'UTF-8') ?>..." <?= $checked ? '' : 'disabled' ?> readonly>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-primary no-global-loading">Guardar</button>
    </div>
  </form>
  </div>
</main>
