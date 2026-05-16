<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: leads.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (login_admin($email, $password)) {
        header('Location: leads.php');
        exit;
    }

    $error = 'Login ou senha invalidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin | Veredas do Araguaia</title>
    <link rel="stylesheet" href="assets/admin.css" />
  </head>
  <body class="login-page">
    <main class="login-card">
      <img src="../assets/logo-veredas-site.png" alt="Veredas do Araguaia" />
      <h1>Área administrativa</h1>
      <p>Acompanhe os leads gerados pelo site.</p>
      <?php if ($error !== ''): ?>
        <div class="alert"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <label>
          E-mail
          <input name="email" type="email" autocomplete="username" required value="<?= e(ADMIN_EMAIL) ?>" />
        </label>
        <label>
          Senha
          <input name="password" type="password" autocomplete="current-password" required />
        </label>
        <button type="submit">Entrar</button>
      </form>
    </main>
  </body>
</html>
