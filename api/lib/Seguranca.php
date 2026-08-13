<?php
/**
 * Cabeçalhos de segurança, sessão endurecida, CSRF e defesa contra força bruta.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class Seguranca
{
    private static array $cfg = [];

    public static function iniciar(array $cfg): void
    {
        self::$cfg = $cfg;
        self::cabecalhos();
        self::verificarHttps();
        self::iniciarSessao();
        self::expirarSessaoInativa();
    }

    /* ------------------------------------------------------- cabeçalhos -- */

    private static function cabecalhos(): void
    {
        if (headers_sent()) {
            return;
        }
        // A API só devolve JSON; nada aqui precisa executar script ou ser
        // embutido em outro site.
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header_remove('X-Powered-By');

        if (self::ehHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function ehHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Hospedagens com proxy/CDN na frente informam o esquema neste cabeçalho.
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private static function verificarHttps(): void
    {
        if (!(self::$cfg['seguranca']['exigir_https'] ?? true)) {
            return;
        }
        if (self::ehHttps() || self::ehLocal()) {
            return;
        }
        Resposta::erro('Esta API exige HTTPS.', 403);
    }

    private static function ehLocal(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = explode(':', $host)[0];
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /* ----------------------------------------------------------- sessão -- */

    private static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(self::$cfg['sessao']['nome'] ?? 'DIARIOSESSID');
        session_set_cookie_params([
            'lifetime' => 0,          // cookie de sessão: morre ao fechar o navegador
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::ehHttps(),
            'httponly' => true,       // inacessível ao JavaScript (anti-XSS)
            'samesite' => 'Strict',   // não acompanha requisições de outros sites (anti-CSRF)
        ]);
        // Ignora identificadores de sessão vindos da URL e recusa ids que o
        // servidor não gerou (impede session fixation por id arbitrário).
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');

        session_start();

        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        if (empty($_SESSION['impressao_digital'])) {
            $_SESSION['impressao_digital'] = self::impressaoDigital();
        }
    }

    /**
     * Vincula a sessão ao navegador de origem. Não impede um roubo de cookie
     * sofisticado, mas encarece a reutilização em outro cliente.
     */
    private static function impressaoDigital(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private static function expirarSessaoInativa(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            return;
        }

        $agora   = time();
        $ocioso  = (int) (self::$cfg['sessao']['tempo_ocioso'] ?? 1800);
        $maximo  = (int) (self::$cfg['sessao']['tempo_maximo'] ?? 28800);
        $ultimo  = (int) ($_SESSION['ultimo_acesso'] ?? $agora);
        $criacao = (int) ($_SESSION['criada_em'] ?? $agora);

        $expirou = ($agora - $ultimo > $ocioso) || ($agora - $criacao > $maximo);
        $outroNavegador = !hash_equals(
            (string) ($_SESSION['impressao_digital'] ?? ''),
            self::impressaoDigital()
        );

        if ($expirou || $outroNavegador) {
            self::encerrarSessao();
            return;
        }

        $_SESSION['ultimo_acesso'] = $agora;

        // Renova o identificador periodicamente (limita session fixation).
        if ($agora - (int) ($_SESSION['renovada_em'] ?? $criacao) > 900) {
            session_regenerate_id(true);
            $_SESSION['renovada_em'] = $agora;
        }
    }

    public static function encerrarSessao(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Strict',
            ]);
        }
        session_destroy();
        session_start();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['impressao_digital'] = self::impressaoDigital();
    }

    /** Reinicia a sessão preservando nada — usado logo após autenticar. */
    public static function renovarSessao(int $usuarioId): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario_id']    = $usuarioId;
        $_SESSION['criada_em']     = time();
        $_SESSION['ultimo_acesso'] = time();
        $_SESSION['renovada_em']   = time();
        $_SESSION['csrf']          = bin2hex(random_bytes(32));
    }

    /* ------------------------------------------------------------- CSRF -- */

    public static function csrf(): string
    {
        return (string) ($_SESSION['csrf'] ?? '');
    }

    /**
     * Exigido em toda requisição que altera estado. Combinado com o cookie
     * SameSite=Strict, cobre também navegadores que ignoram o atributo.
     */
    public static function validarCsrf(): void
    {
        $enviado = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $sessao  = self::csrf();

        if ($enviado === '' || $sessao === '' || !hash_equals($sessao, $enviado)) {
            Resposta::erro('Token de segurança inválido. Recarregue a página e tente de novo.', 419);
        }
    }

    /* ------------------------------------------------- força bruta / IP -- */

    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function registrarTentativa(string $identificador, bool $sucesso): void
    {
        Bd::consultar(
            'INSERT INTO tentativas_login (identificador, ip, sucesso) VALUES (?, ?, ?)',
            [mb_substr($identificador, 0, 160), self::ip(), $sucesso ? 1 : 0]
        );

        // Limpeza oportunista dos registros antigos (mantém a tabela enxuta).
        if (random_int(1, 50) === 1) {
            Bd::consultar('DELETE FROM tentativas_login WHERE criado_em < (NOW() - INTERVAL 30 DAY)');
        }
    }

    /**
     * Segundos restantes de bloqueio, ou 0 se liberado.
     *
     * Há dois limites independentes: um por conta (rígido) e outro por IP
     * (bem mais folgado). O limite por IP existe para conter varredura vinda
     * de uma única origem, mas sem punir uma escola ou escritório inteiro que
     * sai por um mesmo IP — ali, o erro de digitação de uma pessoa não pode
     * trancar todas as outras.
     */
    public static function segundosBloqueado(string $identificador): int
    {
        return max(
            self::bloqueioPor(
                'identificador',
                mb_substr(mb_strtolower(trim($identificador)), 0, 160),
                (int) (self::$cfg['seguranca']['max_tentativas'] ?? 5)
            ),
            self::bloqueioPor(
                'ip',
                self::ip(),
                (int) (self::$cfg['seguranca']['max_tentativas_ip'] ?? 25)
            )
        );
    }

    /**
     * A contagem de tempo é feita inteiramente no banco (TIMESTAMPDIFF sobre
     * NOW()), sem misturar o relógio do PHP com o do MySQL — os dois podem
     * estar em fusos diferentes, e a conta sairia errada.
     *
     * $coluna nunca vem do usuário: é um literal escolhido aqui dentro.
     */
    private static function bloqueioPor(string $coluna, string $valor, int $maximo): int
    {
        if (!in_array($coluna, ['identificador', 'ip'], true)) {
            throw new InvalidArgumentException('Coluna inválida.');
        }

        $janela  = (int) (self::$cfg['seguranca']['janela_tentativas'] ?? 900);
        $castigo = (int) (self::$cfg['seguranca']['bloqueio_segundos'] ?? 900);

        $linha = Bd::primeiro(
            "SELECT COUNT(*) AS total,
                    COALESCE(TIMESTAMPDIFF(SECOND, MAX(criado_em), NOW()), 0) AS idade
               FROM tentativas_login
              WHERE sucesso = 0
                AND criado_em > (NOW() - INTERVAL ? SECOND)
                AND {$coluna} = ?",
            [$janela, $valor]
        );

        $total = (int) ($linha['total'] ?? 0);
        if ($total < $maximo) {
            return 0;
        }

        // Bloqueio progressivo: dobra a cada bloco de falhas além do limite,
        // com teto para não virar bloqueio permanente por acidente.
        $fator = min(8, 2 ** intdiv($total - $maximo, max(1, $maximo)));

        return max(0, ($castigo * $fator) - (int) $linha['idade']);
    }

    /**
     * Limpa as falhas da conta após um login bem-sucedido.
     *
     * Não apaga as falhas do IP de propósito: quem tem uma conta válida não
     * deve conseguir zerar o contador de origem e continuar tentando adivinhar
     * outras contas a partir dali.
     */
    public static function limparTentativas(string $identificador): void
    {
        Bd::consultar(
            'DELETE FROM tentativas_login WHERE sucesso = 0 AND identificador = ?',
            [mb_substr(mb_strtolower(trim($identificador)), 0, 160)]
        );
    }
}
