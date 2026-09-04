<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) { header('Location: index.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $password = $_POST['password'] ?? '';

    if (ADMIN_PASSWORD_HASH === 'REPLACE.ME') {
        $error = 'Admin-Passwort wurde auf dem Server noch nicht eingerichtet (siehe admin/config.php).';
    } elseif ($password !== '' && password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        usleep(700000);
        $error = 'Falsches Passwort.';
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin-Login — Regatta — Segelkameradschaft Buchholz e.V.</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --navy:#0a1e3f; --brass:#c6a15b; --paper:#f4f6fa; --ink:#14243d; --line:rgba(20,36,61,.15); }
  *{box-sizing:border-box}
  body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--paper); font-family:'Figtree',sans-serif; color:var(--ink); }
  .card{ background:#fff; border:1px solid var(--line); border-radius:10px; padding:2rem; width:100%; max-width:360px;
    box-shadow:0 20px 50px -25px rgba(10,30,63,.35); }
  h1{ font-family:'Fraunces',serif; font-size:1.4rem; margin:0 0 1.2rem; color:var(--navy); }
  label{ display:block; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#57687f; margin-bottom:.4rem; }
  input{ width:100%; padding:.7rem .8rem; border:1px solid var(--line); border-radius:7px; font-size:1rem; margin-bottom:1.1rem; }
  input:focus{ outline:2px solid var(--brass); }
  button{ width:100%; padding:.75rem; border:none; border-radius:7px; background:var(--brass); color:var(--navy); font-weight:700; font-size:.95rem; cursor:pointer; }
  .error{ background:#fdecea; color:#8a2a20; padding:.7rem .9rem; border-radius:7px; font-size:.85rem; margin-bottom:1rem; }
  a{ color:var(--navy); }
</style>
</head>
<body>
  <div class="card">
    <h1>⛵ Regatta-Admin</h1>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <label for="password">Passwort</label>
      <input id="password" name="password" type="password" required autofocus autocomplete="current-password">
      <button type="submit">Anmelden</button>
    </form>
    <p style="margin-top:1.2rem;font-size:.85rem"><a href="../index.html">&larr; Zurück zur Regatta-Seite</a></p>
  </div>
</body>
</html>
