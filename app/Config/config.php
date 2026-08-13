<?php
declare(strict_types=1);

defined('APP_RUNNING') or exit;

/**
 * Diário de Bordo CECAPE — configurações.
 * Produção: https://cecapescs.com.br/diariobordo
 */

date_default_timezone_set('America/Sao_Paulo');

const APP_NAME  = 'Diário de Bordo · CECAPE';
const APP_OWNER = 'Prof. Flávio Spina';

define('DB_PATH', BASE_PATH . '/data/diario.sqlite');

/**
 * Hash da senha inicial do administrador (senha padrão: cecape2026).
 * Usado apenas para semear o banco no primeiro acesso; depois disso a senha
 * é gerenciada pelo próprio painel (menu "Trocar senha") e fica no banco.
 */
const DEFAULT_ADMIN_PASSWORD_HASH = '$2y$12$3xk22DTrCks3kvyUJgQg..m1x5S.z/IFZe7pl79aN9Fc1C18pdzk6';

// Sessão do administrador expira após este tempo de inatividade (segundos)
const ADMIN_SESSION_LIFETIME = 8 * 3600;

// Proteção contra força bruta no login
const LOGIN_MAX_ATTEMPTS = 5;      // tentativas com falha…
const LOGIN_WINDOW_MINUTES = 15;   // …dentro desta janela bloqueiam o IP

// Limites de entrada
const MAX_TITLE_LEN = 200;
const MAX_CATEGORY_LEN = 100;
const MAX_DESCRIPTION_LEN = 2000;
const MAX_DURATION_MINUTES = 1440; // 24h
const MAX_PHASES = 12;

// Fases padrão sugeridas ao propor uma atividade (nome => peso % da duração)
const DEFAULT_PHASES = [
    ['name' => 'Planejamento',          'weight' => 15],
    ['name' => 'Execução',              'weight' => 55],
    ['name' => 'Verificação e ajustes', 'weight' => 20],
    ['name' => 'Conclusão e registro',  'weight' => 10],
];
