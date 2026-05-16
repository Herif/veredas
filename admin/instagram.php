<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_login();

$agentDir = dirname(__DIR__) . '/social/instagram/agente';
$creativeDir = dirname(__DIR__) . '/social/instagram';

$documents = [
    'README.md' => 'Visao geral',
    'briefing.md' => 'Briefing',
    'calendario-semanal.md' => 'Calendario',
    'respostas-direct.md' => 'Direct',
    'playbook-campanhas.md' => 'Campanhas',
    'relatorio-semanal.md' => 'Relatorio',
    'checklist-operacao.md' => 'Checklist',
];

$selected = (string)($_GET['doc'] ?? 'README.md');
if (!array_key_exists($selected, $documents)) {
    $selected = 'README.md';
}

function load_meta_config(): array
{
    $defaults = [
        'graph_host' => 'https://graph.facebook.com',
        'graph_version' => 'v24.0',
        'ig_user_id' => '',
        'access_token' => '',
        'site_base_url' => 'https://veredasdoaraguaia.com.br',
    ];

    $path = __DIR__ . '/meta-config.php';
    if (!is_file($path)) {
        return $defaults;
    }

    $config = require $path;
    if (!is_array($config)) {
        return $defaults;
    }

    return array_merge($defaults, $config);
}

function save_meta_config(array $config): void
{
    $path = __DIR__ . '/meta-config.php';
    $allowedHosts = [
        'https://graph.facebook.com',
        'https://graph.instagram.com',
    ];

    $graphHost = trim((string)($config['graph_host'] ?? 'https://graph.facebook.com'));
    if (!in_array($graphHost, $allowedHosts, true)) {
        $graphHost = 'https://graph.facebook.com';
    }

    $data = [
        'graph_host' => $graphHost,
        'graph_version' => preg_replace('/[^a-zA-Z0-9.]/', '', (string)($config['graph_version'] ?? 'v24.0')) ?: 'v24.0',
        'ig_user_id' => preg_replace('/\D+/', '', (string)($config['ig_user_id'] ?? '')),
        'access_token' => trim((string)($config['access_token'] ?? '')),
        'site_base_url' => rtrim(trim((string)($config['site_base_url'] ?? 'https://veredasdoaraguaia.com.br')), '/'),
    ];

    $php = "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "return " . var_export($data, true) . ";\n";

    if (file_put_contents($path, $php, LOCK_EX) === false) {
        throw new RuntimeException('Nao foi possivel salvar admin/meta-config.php.');
    }
}

function markdown_to_html(string $markdown): string
{
    $lines = preg_split('/\R/', $markdown) ?: [];
    $html = '';
    $paragraph = [];
    $inList = false;

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if ($paragraph === []) {
            return;
        }
        $html .= '<p>' . e(implode(' ', $paragraph)) . '</p>';
        $paragraph = [];
    };

    $closeList = static function () use (&$html, &$inList): void {
        if (!$inList) {
            return;
        }
        $html .= '</ul>';
        $inList = false;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $match)) {
            $flushParagraph();
            $closeList();
            $level = min(strlen($match[1]) + 1, 5);
            $html .= '<h' . $level . '>' . e($match[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^-\s+(.+)$/', $trimmed, $match)) {
            $flushParagraph();
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . e($match[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $match)) {
            $flushParagraph();
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . e($match[1]) . '</li>';
            continue;
        }

        $paragraph[] = $trimmed;
    }

    $flushParagraph();
    $closeList();

    return $html;
}

function read_agent_doc(string $agentDir, string $file): string
{
    $path = $agentDir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        return '# Conteudo nao encontrado';
    }
    return (string)file_get_contents($path);
}

function list_creatives(string $creativeDir): array
{
    if (!is_dir($creativeDir)) {
        return [];
    }

    $files = glob($creativeDir . DIRECTORY_SEPARATOR . '*.jpg') ?: [];
    sort($files);

    return array_map(static function (string $path): array {
        return [
            'name' => basename($path),
            'url' => '../social/instagram/' . rawurlencode(basename($path)),
        ];
    }, $files);
}

function call_meta_api(string $url, array $data): array
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('A extensao curl nao esta ativa no PHP.');
    }

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Falha ao conectar na Meta API: ' . $error);
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Resposta invalida da Meta API: ' . (string)$body);
    }

    if ($status >= 400 || isset($json['error'])) {
        $message = $json['error']['message'] ?? (string)$body;
        throw new RuntimeException('Meta API: ' . $message);
    }

    return $json;
}

function get_meta_api(string $url, array $query): array
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('A extensao curl nao esta ativa no PHP.');
    }

    $fullUrl = $url . '?' . http_build_query($query);
    $handle = curl_init($fullUrl);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Falha ao conectar na Meta API: ' . $error);
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Resposta invalida da Meta API: ' . (string)$body);
    }

    if ($status >= 400 || isset($json['error'])) {
        $message = $json['error']['message'] ?? (string)$body;
        throw new RuntimeException('Meta API: ' . $message);
    }

    return $json;
}

function test_meta_connection(array $metaConfig): array
{
    $igUserId = trim((string)$metaConfig['ig_user_id']);
    $accessToken = trim((string)$metaConfig['access_token']);
    $graphHost = rtrim(trim((string)$metaConfig['graph_host']), '/');
    $graphVersion = trim((string)$metaConfig['graph_version']);

    if ($igUserId === '' || $accessToken === '') {
        throw new RuntimeException('Informe Instagram User ID e Access Token.');
    }

    return get_meta_api(
        $graphHost . '/' . rawurlencode($graphVersion) . '/' . rawurlencode($igUserId),
        [
            'fields' => 'id,username,account_type,media_count',
            'access_token' => $accessToken,
        ]
    );
}

function publish_instagram_image(array $post, array $metaConfig): array
{
    $igUserId = trim((string)$metaConfig['ig_user_id']);
    $accessToken = trim((string)$metaConfig['access_token']);
    $graphVersion = trim((string)$metaConfig['graph_version']);
    $graphHost = rtrim(trim((string)$metaConfig['graph_host']), '/');
    $siteBaseUrl = rtrim(trim((string)$metaConfig['site_base_url']), '/');

    if ($igUserId === '' || $accessToken === '') {
        throw new RuntimeException('Configure ig_user_id e access_token em admin/meta-config.php antes de publicar.');
    }

    $creative = basename((string)$post['creative']);
    $imageUrl = $siteBaseUrl . '/social/instagram/' . rawurlencode($creative);
    $baseUrl = $graphHost . '/' . rawurlencode($graphVersion) . '/' . rawurlencode($igUserId);

    $container = call_meta_api($baseUrl . '/media', [
        'image_url' => $imageUrl,
        'caption' => (string)$post['caption'],
        'access_token' => $accessToken,
    ]);

    $creationId = (string)($container['id'] ?? '');
    if ($creationId === '') {
        throw new RuntimeException('A Meta nao retornou o ID do container de midia.');
    }

    $published = call_meta_api($baseUrl . '/media_publish', [
        'creation_id' => $creationId,
        'access_token' => $accessToken,
    ]);

    return [
        'creation_id' => $creationId,
        'media_id' => (string)($published['id'] ?? ''),
    ];
}

function fetch_instagram_posts(): array
{
    $stmt = db()->query('SELECT * FROM instagram_posts ORDER BY created_at DESC LIMIT 50');
    return $stmt->fetchAll();
}

$content = read_agent_doc($agentDir, $selected);
$creatives = list_creatives($creativeDir);
$creativeNames = array_column($creatives, 'name');
$metaConfig = load_meta_config();
$notice = '';
$error = '';

try {
    ensure_instagram_schema();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create_draft') {
            $title = trim((string)($_POST['title'] ?? ''));
            $caption = trim((string)($_POST['caption'] ?? ''));
            $creative = basename((string)($_POST['creative'] ?? ''));
            $postType = (string)($_POST['post_type'] ?? 'feed_image');

            if ($title === '' || $caption === '' || !in_array($creative, $creativeNames, true)) {
                throw new RuntimeException('Preencha titulo, legenda e escolha uma arte valida.');
            }

            if ($postType !== 'feed_image') {
                $postType = 'feed_image';
            }

            $stmt = db()->prepare(
                "INSERT INTO instagram_posts (title, creative, caption, post_type)
                 VALUES (:title, :creative, :caption, :post_type)"
            );
            $stmt->execute([
                ':title' => mb_substr($title, 0, 180, 'UTF-8'),
                ':creative' => $creative,
                ':caption' => mb_substr($caption, 0, 2200, 'UTF-8'),
                ':post_type' => $postType,
            ]);
            $notice = 'Rascunho criado com sucesso.';
        }

        if ($action === 'save_meta_config') {
            save_meta_config([
                'graph_host' => $_POST['graph_host'] ?? '',
                'graph_version' => $_POST['graph_version'] ?? '',
                'ig_user_id' => $_POST['ig_user_id'] ?? '',
                'access_token' => $_POST['access_token'] ?? '',
                'site_base_url' => $_POST['site_base_url'] ?? '',
            ]);
            $metaConfig = load_meta_config();
            $notice = 'Configuracao da Meta API salva.';
        }

        if ($action === 'test_meta_config') {
            $result = test_meta_connection($metaConfig);
            $notice = 'Meta API conectada: @' . e((string)($result['username'] ?? 'conta')) . ' / ID ' . e((string)($result['id'] ?? ''));
        }

        if ($action === 'mark_published') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare(
                "UPDATE instagram_posts
                 SET status = 'Publicado manualmente', published_at = NOW(), updated_at = NOW(), error_message = NULL
                 WHERE id = :id"
            );
            $stmt->execute([':id' => $id]);
            $notice = 'Publicacao marcada como feita manualmente.';
        }

        if ($action === 'publish_now') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT * FROM instagram_posts WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $post = $stmt->fetch();

            if (!$post) {
                throw new RuntimeException('Rascunho nao encontrado.');
            }

            $result = publish_instagram_image($post, $metaConfig);
            $stmt = db()->prepare(
                "UPDATE instagram_posts
                 SET status = 'Publicado pela API', meta_media_id = :media_id, error_message = NULL,
                     published_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                ':media_id' => $result['media_id'],
                ':id' => $id,
            ]);
            $notice = 'Publicacao enviada para o Instagram pela Meta API.';
        }
    }

    $posts = fetch_instagram_posts();
} catch (Throwable $exception) {
    $posts = [];
    $error = $exception->getMessage();

    if (isset($action, $id) && $action === 'publish_now' && $id > 0) {
        try {
            $stmt = db()->prepare(
                "UPDATE instagram_posts
                 SET status = 'Erro ao publicar', error_message = :error, updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                ':error' => mb_substr($exception->getMessage(), 0, 1000, 'UTF-8'),
                ':id' => $id,
            ]);
            $posts = fetch_instagram_posts();
        } catch (Throwable $ignored) {
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Instagram | Veredas do Araguaia</title>
    <link rel="stylesheet" href="assets/admin.css" />
  </head>
  <body>
    <header class="admin-header">
      <a class="admin-brand" href="leads.php">
        <img src="../assets/logo-veredas-site.png" alt="Veredas do Araguaia" />
        <span>Instagram</span>
      </a>
      <nav>
        <a href="leads.php">Leads</a>
        <a href="instagram.php">Instagram</a>
        <span><?= e($_SESSION['admin_email'] ?? '') ?></span>
        <a href="logout.php">Sair</a>
      </nav>
    </header>

    <main class="admin-shell instagram-shell">
      <section class="panel intro-panel">
        <div>
          <p class="kicker">Agente Instagram</p>
          <h1>Operacao de conteudo e campanhas</h1>
          <p>Use esta area para planejar publicacoes, responder directs e acompanhar a rotina comercial do Instagram.</p>
        </div>
        <strong>7 arquivos</strong>
      </section>

      <section class="instagram-layout">
        <aside class="panel agent-menu">
          <?php foreach ($documents as $file => $label): ?>
            <a class="<?= $file === $selected ? 'active' : '' ?>" href="instagram.php?doc=<?= e($file) ?>">
              <?= e($label) ?>
            </a>
          <?php endforeach; ?>
        </aside>

        <article class="panel agent-document">
          <?= markdown_to_html($content) ?>
        </article>
      </section>

      <?php if ($notice !== ''): ?>
        <div class="success"><?= e($notice) ?></div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <section class="panel publisher-panel">
        <div>
          <p class="kicker">Meta API</p>
          <h2>Configuracao de publicacao</h2>
          <p>Preencha estes dados para ativar publicacao direta. O token fica salvo no servidor em um arquivo PHP protegido pelo login do painel.</p>
        </div>

        <form class="publisher-form" method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="save_meta_config" />
          <label>
            API
            <select name="graph_host">
              <?php foreach (['https://graph.facebook.com', 'https://graph.instagram.com'] as $host): ?>
                <option value="<?= e($host) ?>" <?= (string)$metaConfig['graph_host'] === $host ? 'selected' : '' ?>>
                  <?= e($host) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Versao
            <input name="graph_version" value="<?= e((string)$metaConfig['graph_version']) ?>" placeholder="v24.0" />
          </label>
          <label>
            Instagram User ID
            <input name="ig_user_id" value="<?= e((string)$metaConfig['ig_user_id']) ?>" placeholder="Ex: 1784..." />
          </label>
          <label class="caption-field">
            Access Token
            <textarea name="access_token" rows="3" placeholder="Cole aqui o token da Meta"><?= e((string)$metaConfig['access_token']) ?></textarea>
          </label>
          <label class="caption-field">
            URL base do site
            <input name="site_base_url" value="<?= e((string)$metaConfig['site_base_url']) ?>" />
          </label>
          <button type="submit">Salvar configuracao</button>
        </form>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="test_meta_config" />
          <button class="secondary-button" type="submit">Testar conexao Meta</button>
        </form>

        <div class="meta-help">
          <p><strong>Dados necessarios:</strong> Instagram User ID e Access Token com permissao de publicacao.</p>
          <p>Fluxo oficial da Meta: criar um container de midia e depois publicar com <code>media_publish</code>.</p>
        </div>
      </section>

      <section class="panel publisher-panel">
        <div>
          <p class="kicker">Publicador</p>
          <h2>Criar publicacao</h2>
          <p>Crie um rascunho com arte e legenda. O botao Publicar agora usa a Meta API quando o token estiver configurado.</p>
        </div>

        <?php if (trim((string)$metaConfig['ig_user_id']) === '' || trim((string)$metaConfig['access_token']) === ''): ?>
          <div class="alert inline-alert">
            Para publicar direto pelo painel, configure o arquivo <strong>admin/meta-config.php</strong> com o Instagram User ID e o Access Token da Meta.
          </div>
        <?php endif; ?>

        <form class="publisher-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="create_draft" />
          <label>
            Titulo interno
            <input name="title" maxlength="180" placeholder="Ex: Post mapa e disponibilidade" required />
          </label>
          <label>
            Arte
            <select name="creative" required>
              <option value="">Escolha uma arte</option>
              <?php foreach ($creatives as $creative): ?>
                <option value="<?= e($creative['name']) ?>"><?= e($creative['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Tipo
            <select name="post_type">
              <option value="feed_image">Feed imagem</option>
            </select>
          </label>
          <label class="caption-field">
            Legenda
            <textarea name="caption" rows="6" maxlength="2200" required placeholder="Escreva a legenda do post..."></textarea>
          </label>
          <button type="submit">Salvar rascunho</button>
        </form>
      </section>

      <section class="post-list">
        <?php if ($posts === []): ?>
          <div class="panel empty">Nenhum rascunho criado ainda.</div>
        <?php endif; ?>

        <?php foreach ($posts as $post): ?>
          <article class="panel post-card">
            <img src="../social/instagram/<?= e(rawurlencode((string)$post['creative'])) ?>" alt="<?= e($post['title']) ?>" />
            <div>
              <span class="status-pill"><?= e($post['status']) ?></span>
              <h2><?= e($post['title']) ?></h2>
              <p><?= nl2br(e($post['caption'])) ?></p>
              <?php if (!empty($post['error_message'])): ?>
                <div class="alert inline-alert"><?= e($post['error_message']) ?></div>
              <?php endif; ?>
              <?php if (!empty($post['meta_media_id'])): ?>
                <p><strong>ID Meta:</strong> <?= e($post['meta_media_id']) ?></p>
              <?php endif; ?>
              <div class="post-actions">
                <a class="clear-link" href="../social/instagram/<?= e(rawurlencode((string)$post['creative'])) ?>" target="_blank" rel="noopener">Abrir arte</a>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="action" value="publish_now" />
                  <input type="hidden" name="id" value="<?= (int)$post['id'] ?>" />
                  <button type="submit">Publicar agora</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="action" value="mark_published" />
                  <input type="hidden" name="id" value="<?= (int)$post['id'] ?>" />
                  <button class="secondary-button" type="submit">Marcar manual</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="panel creative-panel">
        <div class="creative-heading">
          <div>
            <p class="kicker">Criativos prontos</p>
            <h2>Artes para posts e stories</h2>
            <p>Use estes arquivos como base para publicacoes organicas e testes de campanha.</p>
          </div>
          <a class="clear-link" href="../social/instagram/legendas-campanha.md" target="_blank" rel="noopener">Legendas</a>
        </div>

        <?php if ($creatives === []): ?>
          <p>Nenhum criativo encontrado no servidor.</p>
        <?php else: ?>
          <div class="creative-grid">
            <?php foreach ($creatives as $creative): ?>
              <a class="creative-card" href="<?= e($creative['url']) ?>" target="_blank" rel="noopener">
                <img src="<?= e($creative['url']) ?>" alt="<?= e($creative['name']) ?>" loading="lazy" />
                <span><?= e($creative['name']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </body>
</html>
