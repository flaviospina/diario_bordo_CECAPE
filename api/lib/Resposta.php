<?php
/**
 * Padroniza as respostas JSON e o tratamento de erros da API.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

/** Erro esperado da aplicação: vira resposta JSON com código HTTP próprio. */
class ErroApi extends RuntimeException
{
    public function __construct(
        string $mensagem,
        private int $status = 400,
        private ?array $campos = null
    ) {
        parent::__construct($mensagem);
    }

    public function status(): int { return $this->status; }
    public function campos(): ?array { return $this->campos; }
}

final class Resposta
{
    /** Envia um payload de sucesso e encerra a requisição. */
    public static function ok(array $dados = [], int $status = 200): never
    {
        self::enviar(['ok' => true] + $dados, $status);
    }

    /** Envia uma falha e encerra a requisição. */
    public static function erro(string $mensagem, int $status = 400, ?array $campos = null): never
    {
        $corpo = ['ok' => false, 'erro' => $mensagem];
        if ($campos !== null) {
            $corpo['campos'] = $campos;
        }
        self::enviar($corpo, $status);
    }

    private static function enviar(array $corpo, int $status): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(
            $corpo,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    /**
     * Lê e decodifica o corpo JSON da requisição.
     * Recusa corpos grandes demais e JSON malformado.
     */
    public static function corpoJson(int $tamanhoMaximo): array
    {
        $tamanho = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($tamanho > $tamanhoMaximo) {
            self::erro('Requisição grande demais.', 413);
        }

        $bruto = file_get_contents('php://input', false, null, 0, $tamanhoMaximo + 1);
        if ($bruto === false || $bruto === '') {
            return [];
        }
        if (strlen($bruto) > $tamanhoMaximo) {
            self::erro('Requisição grande demais.', 413);
        }

        try {
            $dados = json_decode($bruto, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::erro('Corpo da requisição não é um JSON válido.', 400);
        }

        return is_array($dados) ? $dados : [];
    }
}
