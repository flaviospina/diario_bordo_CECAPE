<?php
/**
 * Gerador de hash de senha para o Diário de Bordo.
 * Acesse esta página no navegador, digite a nova senha e copie o hash
 * gerado para a constante ADMIN_PASSWORD_HASH em config.php.
 * Por segurança, exclua este arquivo do servidor depois de trocar a senha.
 */
$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['senha'])) {
    $hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Gerar senha — Diário de Bordo</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="login-wrap">
    <div class="card login-card" style="width:min(560px,92vw);text-align:left">
      <h2>Gerar hash de senha</h2>
      <p>Digite a nova senha, gere o hash e cole em <code>ADMIN_PASSWORD_HASH</code> no arquivo <code>config.php</code>. Depois, exclua este arquivo do servidor.</p>
      <form method="post" style="gap:10px;display:flex;flex-direction:column">
        <input type="text" name="senha" placeholder="Nova senha" required>
        <button class="btn primary" type="submit">Gerar hash</button>
      </form>
      <?php if ($hash): ?>
        <p style="margin-top:16px"><b>Hash gerado:</b></p>
        <textarea readonly rows="3" style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid var(--border);border-radius:8px" onclick="this.select()"><?= htmlspecialchars($hash) ?></textarea>
      <?php endif; ?>
      <p style="margin-top:14px"><a class="link" href="index.php">Voltar ao diário</a></p>
    </div>
  </div>
</body>
</html>
