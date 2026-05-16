<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_login();

$checks = [
    'pdo' => extension_loaded('pdo'),
    'pdo_pgsql' => extension_loaded('pdo_pgsql'),
    'pgsql' => extension_loaded('pgsql'),
    'mbstring' => extension_loaded('mbstring'),
    'json' => extension_loaded('json'),
    'curl' => extension_loaded('curl'),
    'session' => extension_loaded('session'),
];

$dbMessage = '';
$dbOk = false;

try {
    ensure_leads_schema();
    $dbOk = true;
    $dbMessage = 'Conexao com PostgreSQL e tabela leads OK.';
} catch (Throwable $exception) {
    $dbMessage = $exception->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Diagnostico | Veredas do Araguaia</title>
    <link rel="stylesheet" href="assets/admin.css" />
  </head>
  <body>
    <header class="admin-header">
      <a class="admin-brand" href="leads.php">
        <img src="../assets/logo-veredas-site.png" alt="Veredas do Araguaia" />
        <span>Diagnostico</span>
      </a>
      <nav>
        <a href="leads.php">Leads</a>
        <a href="instagram.php">Instagram</a>
        <a href="logout.php">Sair</a>
      </nav>
    </header>
    <main class="admin-shell">
      <section class="panel intro-panel">
        <div>
          <p class="kicker">PHP e banco</p>
          <h1>Diagnostico do servidor</h1>
          <p>Use esta tela para confirmar as extensoes necessarias ao painel.</p>
        </div>
      </section>
      <section class="panel" style="padding: 24px;">
        <h2>Extensoes PHP</h2>
        <dl class="lead-details">
          <?php foreach ($checks as $name => $enabled): ?>
            <div>
              <dt><?= e($name) ?></dt>
              <dd><?= $enabled ? 'Ativa' : 'Inativa' ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>
        <h2>Banco de dados</h2>
        <p class="<?= $dbOk ? 'success' : 'alert' ?>" style="width: auto; margin: 0;">
          <?= e($dbMessage) ?>
        </p>
      </section>
    </main>
  </body>
</html>
