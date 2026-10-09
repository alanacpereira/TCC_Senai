<?php

require_once __DIR__ . "/../../jogo1/config/Auth.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/../controllers/Jogo2Controller.php";

Auth::iniciarSessao();

class Jogo2HttpException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

function jogo2ConfigurarApi(string $metodoEsperado): void
{
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store");
    ini_set("display_errors", "0");

    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );

    if ($_SERVER["REQUEST_METHOD"] !== $metodoEsperado) {
        throw new Jogo2HttpException("Método não permitido.", 405);
    }

    if ($metodoEsperado === "POST") {
        $esperado = $_SESSION["jogo2_csrf"] ?? "";
        $recebido = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

        if (
            !is_string($esperado) ||
            $esperado === "" ||
            !is_string($recebido) ||
            !hash_equals($esperado, $recebido)
        ) {
            throw new Jogo2HttpException(
                "Sua sessão expirou. Atualize a página e tente novamente.",
                403
            );
        }
    }
}

function jogo2Conexao(): PDO
{
    return (new Jogo2Database())->conectar();
}

function jogo2UsuarioId(): int
{
    $usuarioId = Auth::usuarioId();

    if ($usuarioId !== null) {
        return $usuarioId;
    }

    return 1;
}

function jogo2Json(array $conteudo, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $conteudo,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function jogo2TratarErro(Throwable $erro): never
{
    if ($erro instanceof Jogo2HttpException) {
        jogo2Json(
            ["sucesso" => false, "mensagem" => $erro->getMessage()],
            $erro->httpStatus()
        );
    }

    error_log(
        sprintf(
            "[Jogo 2] %s em %s:%d",
            $erro->getMessage(),
            $erro->getFile(),
            $erro->getLine()
        )
    );

    jogo2Json(
        [
            "sucesso" => false,
            "mensagem" => "Não foi possível concluir a operação. Tente novamente."
        ],
        500
    );
}

function jogo2IdRecebido(array $origem, string $campo): int
{
    $id = filter_var(
        $origem[$campo] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($id === false) {
        throw new Jogo2HttpException("Dados inválidos.");
    }

    return $id;
}
