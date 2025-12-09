<?php
/**
 * Template para el formulario de Dashboard (Crear o Personalizar)
 * 
 * Variables esperadas:
 * $mode : 'create' | 'edit'
 * $formAction : string
 * $widgetsPorPlataforma : array ( [ 'Nombre Plataforma' => [widgets...] ] )
 * $widgetsVisiblesIds : array (IDs de widgets seleccionados)
 * $dashboardInfo : array|null (Info del dashboard si edit)
 * $clienteId : int (Solo para create)
 * $clienteNombre : string (Para prellenar nombre en create)
 */

$isCreate = ($mode === 'create');
$checkboxName = $isCreate ? 'widgets_ids[]' : 'widgets[]';
$submitLabel = $isCreate ? 'Crear Dashboard' : 'Guardar Cambios';
$modalTitle = $isCreate ? 'Crear Dashboard' : 'Personalizar Dashboard';
?>

<div id="dashboard-modal" class="modal-overlay">
    <div class="modal-content">
        <!-- Header del modal -->
        <div class="modal-header">
            <h3><?= htmlspecialchars($modalTitle) ?></h3>
            <button type="button" id="modal-close-btn" class="modal-close" aria-label="Cerrar">
                ×
            </button>
        </div>
        
        <!-- Formulario -->
        <form id="dashboard-form" method="POST" action="<?= htmlspecialchars($formAction) ?>">
            
            <?php if ($isCreate): ?>
                <input type="hidden" name="cliente_id" value="<?= (int)$clienteId ?>">
                <input type="hidden" name="redirect" value="1">
                
                <div style="margin-bottom: var(--spacing-xl);">
                    <label for="dash-nombre" style="display:block; margin-bottom: var(--spacing-sm); font-weight: var(--font-weight-semibold); color: var(--color-gray-800);">Nombre del Dashboard</label>
                    <input type="text" id="dash-nombre" name="nombre" class="select-lite" 
                           style="width: 100%; border: 1px solid var(--color-gray-300); padding: 0.8rem; font-size: var(--font-size-base);" 
                           value="<?= htmlspecialchars($clienteNombre ?? '') ?>" required>
                </div>
            <?php else: ?>
                <input type="hidden" name="dashboard_id" value="<?= htmlspecialchars($dashboardInfo['id'] ?? '') ?>">
            <?php endif; ?>
            
            <!-- Secciones de Widgets -->
            <?php foreach ($widgetsPorPlataforma as $platNombre => $widgetsList): ?>
                <?php 
                    // Determinar icono basado en el nombre
                    $icon = 'bi-globe';
                    if (stripos($platNombre, 'Meta') !== false) $icon = 'bi-meta';
                    elseif (stripos($platNombre, 'Facebook') !== false) $icon = 'bi-facebook';
                    elseif (stripos($platNombre, 'Instagram') !== false) $icon = 'bi-instagram';
                ?>
                <div class="widgets-section">
                    <h4>
                        <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($platNombre) ?>
                    </h4>
                    <?php foreach ($widgetsList as $widget): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="<?= $checkboxName ?>" 
                                   value="<?= htmlspecialchars($widget['id']) ?>"
                                   id="widget-<?= htmlspecialchars($widget['id']) ?>"
                                   <?= in_array($widget['id'], $widgetsVisiblesIds ?? []) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="widget-<?= htmlspecialchars($widget['id']) ?>">
                                <strong><?= htmlspecialchars($widget['nombre']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($widget['descripcion']) ?></small>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Botones de acción -->
            <div class="modal-actions">
                <button type="button" id="cancelar-widgets-btn" class="btn btn-secondary">
                    Cancelar
                </button>
                <button type="submit" id="guardar-widgets-btn" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= htmlspecialchars($submitLabel) ?>
                </button>
            </div>
        </form>
    </div>
</div>
