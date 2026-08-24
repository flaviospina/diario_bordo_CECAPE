<?php
defined('APP_RUNNING') or exit;
use App\Core\Csrf;
$error = $error ?? '';
?>
  <div class="login-center">
    <div class="login-card">
      <div class="login-logo-row">
        <?php foreach (LOGOS as $i => $logo): ?>
          <?php if ($i > 0): ?><span class="login-logo-divider"></span><?php endif; ?>
          <img src="<?= e(LOGO_BASE . '/' . $logo['file']) ?>" alt="<?= e($logo['alt']) ?>" class="login-logo">
        <?php endforeach; ?>
      </div>
      <h2 class="login-title">Diário de Bordo</h2>
      <p class="login-subtitle">Registro de atividades em home office.<br>Acesso restrito — informe seu usuário e senha.</p>
      <form id="login-form" method="post" action="<?= e(url('login')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="form-group">
          <label class="form-label" for="login-user">Usuário</label>
          <input type="text" id="login-user" name="username" class="form-control" autocomplete="username" autocapitalize="none" required autofocus>
        </div>
        <div class="form-group mt-2">
          <label class="form-label" for="login-pass">Senha</label>
          <input type="password" id="login-pass" name="password" class="form-control" autocomplete="current-password" required>
        </div>
        <p class="login-error" id="login-error"><?= e($error) ?></p>
        <button class="btn-login" type="submit">Entrar</button>
      </form>
      <p class="login-back">Não tem acesso? Fale com o administrador do diário.</p>
    </div>
  </div>
