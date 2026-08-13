<?php
/**
 * Diário de Bordo CECAPE — Configurações
 *
 * IMPORTANTE: troque a senha padrão após o primeiro acesso.
 * Para gerar um novo hash de senha, acesse gerar_senha.php no navegador,
 * copie o hash gerado e cole abaixo em ADMIN_PASSWORD_HASH.
 */

date_default_timezone_set('America/Sao_Paulo');

// Senha padrão: cecape2026
define('ADMIN_PASSWORD_HASH', '$2y$12$3xk22DTrCks3kvyUJgQg..m1x5S.z/IFZe7pl79aN9Fc1C18pdzk6');

define('APP_NAME', 'Diário de Bordo · CECAPE');
define('APP_OWNER', 'Prof. Flávio Spina');
define('DB_PATH', __DIR__ . '/data/diario.sqlite');

// Fases padrão sugeridas ao propor uma atividade (nome => peso % da duração)
const DEFAULT_PHASES = [
    ['name' => 'Planejamento',           'weight' => 15],
    ['name' => 'Execução',               'weight' => 55],
    ['name' => 'Verificação e ajustes',  'weight' => 20],
    ['name' => 'Conclusão e registro',   'weight' => 10],
];
