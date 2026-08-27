<?php
declare(strict_types=1);

defined('APP_RUNNING') or exit;

/**
 * Diário de Bordo CECAPE — configurações.
 * Produção: https://cecapescs.com.br/diariobordo
 */

date_default_timezone_set('America/Sao_Paulo');

/*
 * Ajustes específicos do servidor (não versionado no git).
 * Copie config.local.php.example para config.local.php para, por exemplo,
 * guardar o banco de dados fora da raiz web ou trocar o caminho das logos.
 * Carregado antes das constantes abaixo para poder sobrescrevê-las.
 */
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

const APP_NAME  = 'Diário de Bordo · CECAPE';
const APP_OWNER = 'Prof. Flávio Spina';

// Logos institucionais exibidas na barra superior e no login
defined('LOGO_BASE') or define('LOGO_BASE', 'https://cecapescs.com.br/logos');
const LOGOS = [
    ['file' => 'logo-seeduc.png',     'alt' => 'SEEDUC'],
    ['file' => 'logo-autoriascs.png', 'alt' => 'AutoriaSCS'],
    ['file' => 'logo-cecape-new.png', 'alt' => 'CECAPE'],
];

// Direção que assina os relatórios
const DIRECTOR_NAME = 'Maiberte Brogliato';
const DIRECTOR_ROLE = 'Direção · CECAPE';

// Perfis de acesso
const ROLE_LABELS = ['admin' => 'Administrador', 'gestor' => 'Gestão', 'professor' => 'Professor'];

// Tipos de intervalo registráveis no dia de trabalho
const BREAK_TYPES = ['almoco' => 'Almoço', 'janta' => 'Janta', 'saida_medica' => 'Saída médica'];

// Aviso de fim de jornada: alerta quando faltarem até N minutos
const JORNADA_WARN_MINUTES = 30;

/*
 * Contas criadas automaticamente no primeiro acesso (senha inicial: cecape2026).
 * Cada pessoa deve trocar a própria senha no painel após o primeiro login.
 * O admin (flavio) herda a senha que já estiver cadastrada no banco.
 */
const SEED_USERS = [
    ['username' => 'flavio',     'name' => 'Prof. Flávio Spina', 'role' => 'admin'],
    ['username' => 'maiberte',   'name' => 'Maiberte Brogliato', 'role' => 'gestor'],
    ['username' => 'therezinha', 'name' => 'Therezinha',         'role' => 'gestor'],
];

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
