<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/ConnectDB.php';

$login_error = '';
$old_username = '';
 $login_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old_username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($old_username === '' || $password === '') {
    $login_error = 'กรุณากรอกข้อมูลให้ครบ';
  } else {
    $pdo = connectDB();
    $stmt = $pdo->prepare('SELECT * FROM admin WHERE admin_username = :username LIMIT 1');
    $stmt->execute([':username' => $old_username]);
    $row = $stmt->fetch();

    if ($row) {
      $stored = (string)($row['admin_password'] ?? '');
      $ok = false;

      // Prefer secure password verification; fall back to plain comparison if not hashed.
      if ($stored !== '' && password_verify($password, $stored)) {
        $ok = true;
      } elseif ($password === $stored) {
        $ok = true;
      }

      if ($ok) {
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['admin_username'] = $row['admin_username'];
        $_SESSION['admin_name'] = $row['admin_name'] ?? '';
        $login_success = true;
      }
    }

    $login_error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
  }
}

?>
<!doctype html>
<html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login | Animate UI</title>
    <link rel="stylesheet" href="Assets/Css/animate-ui.css" />
  </head>
  <body>
    <main class="animate-ui-root">
      <section class="animate-ui-card" aria-live="polite" tabindex="0">
        <h1>เข้าสู่ระบบ</h1>
        <form id="animate-ui-login" class="animate-ui-form" action="" method="post">
          <label for="username">Username</label>
          <input
            id="username"
            name="username"
            type="text"
            class="animate-ui-input"
            placeholder="user123"
            value="<?php echo htmlspecialchars($old_username, ENT_QUOTES, 'UTF-8'); ?>"
            autocomplete="username"
            required
          />
          <label for="password">Password</label>
          <input
            id="password"
            name="password"
            type="password"
            class="animate-ui-input"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          />
          <p id="animate-ui-status" class="animate-ui-note">
            <?php if ($login_error !== ''): ?>
              <?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?>
            <?php else: ?>
              กรุณากรอกข้อมูลเพื่อเริ่มต้น
            <?php endif; ?>
          </p>
          <button type="submit" class="animate-ui-submit">เข้าสู่ระบบ</button>
        </form>
      </section>
    </main>
    <?php if (!empty($login_success)): ?>
      <script>
          window.__loginSuccess = true;
          window.__loginMessage = 'ล็อกอินสำเร็จ';
          window.__loginRedirect = 'Reports/dashboard.php';
        </script>
    <?php endif; ?>
    <script src="Assets/Javascript/animate-ui.js" defer></script>
  </body>
</html>