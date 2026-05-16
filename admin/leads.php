<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_login();

$statuses = ['Novo', 'Em atendimento', 'Visitou', 'Negociação', 'Vendido', 'Perdido'];
$error = '';

try {
    ensure_leads_schema();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'Novo');
        $notes = trim((string)($_POST['admin_notes'] ?? ''));

        if (!in_array($status, $statuses, true)) {
            $status = 'Novo';
        }

        $stmt = db()->prepare('UPDATE leads SET status = :status, admin_notes = :admin_notes, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':admin_notes' => $notes,
            ':id' => $id,
        ]);

        header('Location: leads.php?saved=1');
        exit;
    }

    $query = trim((string)($_GET['q'] ?? ''));
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));

    $where = [];
    $params = [];

    if ($query !== '') {
        $where[] = '(nome ILIKE :q OR telefone ILIKE :q OR cidade ILIKE :q OR interesse ILIKE :q)';
        $params[':q'] = '%' . $query . '%';
    }

    if ($from !== '') {
        $where[] = 'created_at >= :from_date';
        $params[':from_date'] = $from . ' 00:00:00';
    }

    if ($to !== '') {
        $where[] = 'created_at <= :to_date';
        $params[':to_date'] = $to . ' 23:59:59';
    }

    $sql = 'SELECT * FROM leads';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 200';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
} catch (Throwable $exception) {
    $leads = [];
    $error = 'Nao foi possivel carregar os leads: ' . $exception->getMessage();
    error_log('Admin leads error: ' . $exception->getMessage());
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Leads | Veredas do Araguaia</title>
    <link rel="stylesheet" href="assets/admin.css" />
  </head>
  <body>
    <header class="admin-header">
      <a class="admin-brand" href="leads.php">
        <img src="../assets/logo-veredas-site.png" alt="Veredas do Araguaia" />
        <span>Leads</span>
      </a>
      <nav>
        <a href="instagram.php">Instagram</a>
        <span><?= e($_SESSION['admin_email'] ?? '') ?></span>
        <a href="logout.php">Sair</a>
      </nav>
    </header>

    <main class="admin-shell">
      <section class="panel intro-panel">
        <div>
          <p class="kicker">Painel comercial</p>
          <h1>Leads gerados pelo site</h1>
          <p>Use status e observações para acompanhar o atendimento de cada interessado.</p>
        </div>
        <strong><?= count($leads) ?> registros</strong>
      </section>

      <?php if (isset($_GET['saved'])): ?>
        <div class="success">Lead atualizado com sucesso.</div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="filters panel" method="get">
        <label>
          Buscar
          <input name="q" type="search" value="<?= e($query ?? '') ?>" placeholder="Nome, telefone, cidade..." />
        </label>
        <label>
          De
          <input name="from" type="date" value="<?= e($from ?? '') ?>" />
        </label>
        <label>
          Até
          <input name="to" type="date" value="<?= e($to ?? '') ?>" />
        </label>
        <button type="submit">Filtrar</button>
        <a class="clear-link" href="leads.php">Limpar</a>
      </form>

      <section class="lead-list">
        <?php if ($leads === [] && $error === ''): ?>
          <div class="panel empty">Nenhum lead encontrado.</div>
        <?php endif; ?>

        <?php foreach ($leads as $lead): ?>
          <?php
            $phone = preg_replace('/\D+/', '', (string)$lead['telefone']);
            $wa = $phone !== '' ? 'https://wa.me/55' . preg_replace('/^55/', '', $phone) : '';
          ?>
          <article class="lead-card panel">
            <div class="lead-main">
              <div>
                <span class="status-pill"><?= e($lead['status'] ?? 'Novo') ?></span>
                <h2><?= e($lead['nome']) ?></h2>
                <p><?= e($lead['telefone']) ?><?= $lead['cidade'] ? ' · ' . e($lead['cidade']) : '' ?></p>
              </div>
              <div class="lead-actions">
                <?php if ($wa !== ''): ?>
                  <a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <?php endif; ?>
              </div>
            </div>

            <dl class="lead-details">
              <div>
                <dt>Interesse</dt>
                <dd><?= e($lead['interesse']) ?></dd>
              </div>
              <div>
                <dt>Data</dt>
                <dd><?= e((string)$lead['created_at']) ?></dd>
              </div>
              <div>
                <dt>Mensagem</dt>
                <dd><?= e($lead['mensagem']) ?></dd>
              </div>
            </dl>

            <form class="lead-update" method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>" />
              <label>
                Status
                <select name="status">
                  <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($lead['status'] ?? 'Novo') === $status ? 'selected' : '' ?>>
                      <?= e($status) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Observações internas
                <textarea name="admin_notes" rows="3"><?= e($lead['admin_notes'] ?? '') ?></textarea>
              </label>
              <button type="submit">Salvar acompanhamento</button>
            </form>
          </article>
        <?php endforeach; ?>
      </section>
    </main>
  </body>
</html>
