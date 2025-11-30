<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$error = isset($_GET['error']) ? (string)$_GET['error'] : null;
$errorMsg = null;
if ($error === 'missing') { $errorMsg = 'Complete access_token y ad_account_id/page_id según corresponda.'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Explorador Meta API</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/templates/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/clientes/form.css">
</head>
<body>
  <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
  <main class="content-with-sidebar clientes-create">
    <div class="form-title">Explorador Meta API</div>
    <?php if ($errorMsg): ?>
      <div class="alert-error"><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="/api/test_meta_api.php">
      <div class="form-grid">
        <div class="form-column">
          <div class="form-field">
            <label>Access token (FLEX hardcodeado)</label>
            <input type="text" name="access_token" value="Valores hardcodeados en test_meta_api.php" readonly>
          </div>
          <div class="form-field">
            <label>Ad account id (FLEX hardcodeado)</label>
            <input type="text" name="ad_account_id" value="act_467206800423808" readonly>
          </div>
          <div class="form-field">
            <label>Page id (FLEX hardcodeado)</label>
            <input type="text" name="page_id" value="513381938853778" readonly>
          </div>
          <div class="plataformas-list" style="gap:12px;">
            <button type="submit" name="action" value="validate_token" class="btn-submit">Validar Token</button>
            <button type="submit" name="action" value="account_info" class="btn-submit">Cuenta publicitaria</button>
            <button type="submit" name="action" value="campaigns_active" class="btn-submit">Campañas activas</button>
            <button type="submit" name="action" value="campaigns_all" class="btn-submit">Campañas todas</button>
            <button type="submit" name="action" value="insights_30d" class="btn-submit">Insights 30d</button>
            <button type="submit" name="action" value="page_posts" class="btn-submit">Posts orgánicos</button>
            <button type="submit" name="action" value="instagram_posts" class="btn-submit">Posts Instagram</button>
          </div>
        </div>
        <div class="form-column"></div>
      </div>
    </form>
  </main>
</body>
</html>