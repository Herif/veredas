<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

function field(array $payload, string $key, int $maxLength): string
{
    $value = trim((string)($payload[$key] ?? ''));
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

$lead = [
    'nome' => field($payload, 'nome', 160),
    'telefone' => field($payload, 'telefone', 60),
    'cidade' => field($payload, 'cidade', 120),
    'interesse' => field($payload, 'interesse', 160),
    'mensagem' => field($payload, 'mensagem', 1200),
    'origem' => field($payload, 'origem', 120),
];

if ($lead['nome'] === '' || $lead['telefone'] === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'required_fields']);
    exit;
}

$dbHost = 'localhost';
$dbPort = '5432';
$dbName = 'veredas1_veredas';
$dbUser = 'veredas1_codex';
$dbPass = 'code1001!@#$';

try {
    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('pdo_pgsql extension is not enabled.');
    }

    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS leads (
            id BIGSERIAL PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            telefone VARCHAR(60) NOT NULL,
            cidade VARCHAR(120),
            interesse VARCHAR(160),
            mensagem TEXT,
            origem VARCHAR(120),
            ip VARCHAR(64),
            user_agent TEXT,
            status VARCHAR(40) NOT NULL DEFAULT 'Novo',
            admin_notes TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS status VARCHAR(40) NOT NULL DEFAULT 'Novo'");
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS admin_notes TEXT");
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ");

    $stmt = $pdo->prepare(
        "INSERT INTO leads
            (nome, telefone, cidade, interesse, mensagem, origem, ip, user_agent)
         VALUES
            (:nome, :telefone, :cidade, :interesse, :mensagem, :origem, :ip, :user_agent)
         RETURNING id"
    );

    $stmt->execute([
        ':nome' => $lead['nome'],
        ':telefone' => $lead['telefone'],
        ':cidade' => $lead['cidade'],
        ':interesse' => $lead['interesse'],
        ':mensagem' => $lead['mensagem'],
        ':origem' => $lead['origem'] ?: 'site',
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 600, 'UTF-8'),
    ]);

    $id = $stmt->fetchColumn();
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $error) {
    error_log('Lead save error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed']);
}
