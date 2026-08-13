<?php
/**
 * Modelo de configuração.
 *
 * Copie para api/config.php e ajuste os valores. O config.php real NÃO deve
 * ir para o versionamento (já está no .gitignore) nem ser servido pela web
 * (o .htaccess bloqueia; ainda assim, prefira mantê-lo fora do public_html
 * quando a hospedagem permitir e apontar o caminho em bootstrap.php).
 */

declare(strict_types=1);

return [
    'bd' => [
        'host'    => 'localhost',
        'porta'   => 3306,
        'nome'    => 'diario_bordo',
        // Use um usuário com privilégio mínimo (SELECT/INSERT/UPDATE/DELETE).
        'usuario' => 'diario_app',
        'senha'   => 'TROQUE_ESTA_SENHA',
        'charset' => 'utf8mb4',
    ],

    'sessao' => [
        'nome'          => 'DIARIOSESSID',
        // Encerra a sessão após este tempo sem nenhuma requisição (segundos).
        'tempo_ocioso'  => 1800,    // 30 minutos
        // Tempo máximo de vida da sessão, mesmo em uso contínuo (segundos).
        'tempo_maximo'  => 28800,   // 8 horas
    ],

    'seguranca' => [
        // Bloqueio progressivo após tentativas de login malsucedidas.
        // Falhas por CONTA antes do bloqueio temporário.
        'max_tentativas'     => 5,
        // Falhas por IP de origem. Bem mais folgado, porque escolas e
        // escritórios costumam sair todos por um mesmo IP.
        'max_tentativas_ip'  => 25,
        'janela_tentativas'  => 900,   // conta as falhas dos últimos 15 min
        'bloqueio_segundos'  => 900,   // e bloqueia por 15 min
        // Em produção com HTTPS, mantenha true: recusa requisições em HTTP puro.
        'exigir_https'       => true,
        // Origens permitidas quando o front estiver em outro domínio.
        // Vazio = mesma origem apenas (recomendado).
        'origens_permitidas' => [],
        // Tamanho máximo aceito no corpo da requisição, em bytes.
        'tamanho_max_corpo'  => 1048576,  // 1 MB
        // Limite maior, só para a importação de backup.
        'tamanho_max_importacao' => 8388608, // 8 MB
    ],

    // Fuso usado em todos os horários (PHP e sessão do MySQL).
    'fuso' => 'America/Sao_Paulo',

    // Caminho do arquivo de log de erros da aplicação. Deixe null para usar
    // o log padrão do PHP. Nunca exponha este arquivo pela web.
    'log_erros' => null,

    // true apenas em desenvolvimento: devolve a mensagem real do erro no JSON.
    'depuracao' => false,
];
