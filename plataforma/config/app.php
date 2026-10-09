<?php

declare(strict_types=1);

require_once __DIR__ . "/../../jogo1/config/Auth.php";

function plataforma_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.cookie_samesite", "Lax");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax"
    ]);
    Auth::iniciarSessao();

    if (!isset($_SESSION["plataforma_csrf"])) {
        $_SESSION["plataforma_csrf"] = bin2hex(random_bytes(32));
    }
}

function plataforma_conexao(): PDO
{
    $host = getenv("DB_HOST") ?: "localhost";
    $port = getenv("DB_PORT") ?: "3308";
    $database = getenv("DB_NAME") ?: "palavra_a_bordo";
    $username = getenv("DB_USER") ?: "root";
    $password = getenv("DB_PASSWORD");

    try {
        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password === false ? "" : $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $erro) {
        error_log("[Plataforma] Falha ao conectar ao banco: " . $erro->getMessage());
        throw new RuntimeException(
            "Não foi possível conectar ao banco Palavra a Bordo. Confira DB_HOST, DB_PORT, DB_NAME, DB_USER e DB_PASSWORD.",
            0,
            $erro
        );
    }
}

function plataforma_escapar(string|int|null $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function plataforma_campo(array $dados, string $nome): string
{
    $valor = $dados[$nome] ?? "";
    if (!is_string($valor)) {
        throw new RuntimeException("Dados inválidos.");
    }

    return $valor;
}

function plataforma_token_csrf(): string
{
    plataforma_sessao();

    if (PHP_SAPI !== "cli") {
        ini_set("display_errors", "0");
        set_exception_handler(
            static function (Throwable $erro): void {
                error_log(
                    sprintf(
                        "[Plataforma] %s em %s:%d",
                        $erro->getMessage(),
                        $erro->getFile(),
                        $erro->getLine()
                    )
                );
                $status = http_response_code();
                if ($status < 400) {
                    $status = 500;
                }
                http_response_code($status);
                if (!headers_sent()) {
                    header("Content-Type: text/html; charset=UTF-8");
                }
                echo "<!doctype html><html lang=\"pt-BR\"><meta charset=\"utf-8\"><title>Não foi possível concluir</title><body><main><h1>Não foi possível concluir</h1><p>Ocorreu um erro ao processar esta solicitação. Tente novamente ou volte à página inicial.</p><p><a href=\"index.php\">Voltar à Palavra a Bordo</a></p></main></body></html>";
            }
        );
    }
    return (string) $_SESSION["plataforma_csrf"];
}

function plataforma_validar_csrf(): void
{
    plataforma_sessao();
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals((string) $_SESSION["plataforma_csrf"], $token)
    ) {
        http_response_code(403);
        throw new RuntimeException("Sua sessão expirou. Atualize a página e tente novamente.");
    }
}

function plataforma_migracao_aplicada(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'usuarios'
               AND COLUMN_NAME = 'perfil') AS perfil_disponivel,
            (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (
                   'preferencias_acessibilidade',
                   'carteiras',
                   'transacoes_carteira',
                   'recompensas',
                   'resgates_recompensas',
                   'turmas',
                   'turma_professores',
                   'turma_alunos',
                   'convites_professores',
                   'tokens_recuperacao_senha',
                   'limites_login'
               )) AS tabelas_disponiveis"
    );
    $stmt->execute();
    $estado = $stmt->fetch();

    return (int) $estado["perfil_disponivel"] === 1
        && (int) $estado["tabelas_disponiveis"] === 11;
}

function plataforma_usuario(PDO $pdo): ?array
{
    plataforma_sessao();
    $usuarioId = Auth::usuarioId();

    if ($usuarioId === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, nome, email, nivel, experiencia, data_cadastro, perfil
         FROM usuarios
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(["id" => $usuarioId]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $stmt = $pdo->prepare(
            "SELECT acessibilidade_visual, tamanho_fonte, alto_contraste,
                    espacamento_linhas, melhor_legibilidade, reduzir_animacoes,
                    destacar_interativos
             FROM preferencias_acessibilidade
             WHERE usuario_id = :usuario_id
             LIMIT 1"
        );
        $stmt->execute(["usuario_id" => $usuarioId]);
        $usuario["preferencias"] = $stmt->fetch() ?: [];
    }

    return $usuario ?: null;
}

function plataforma_exigir_login(PDO $pdo, ?string $perfil = null): array
{
    $usuario = plataforma_usuario($pdo);

    if (!$usuario || $usuario["perfil"] === "convidado") {
        header("Location: login.php");
        exit;
    }

    if ($perfil !== null && $usuario["perfil"] !== $perfil) {
        http_response_code(403);
        throw new RuntimeException("Você não tem permissão para acessar esta área.");
    }

    return $usuario;
}

function plataforma_contabilizar_falha_login(
    PDO $pdo,
    string $email,
    string $origem
): void {
    $emailHash = hash("sha256", strtolower($email), true);
    $origemHash = hash("sha256", $origem, true);
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO limites_login
                (email_hash, origem_hash, falhas, janela_iniciada_em)
             VALUES (:email_hash, :origem_hash, 0, NOW())"
        );
        $stmt->bindValue(":email_hash", $emailHash);
        $stmt->bindValue(":origem_hash", $origemHash);
        $stmt->execute();

        $stmt = $pdo->prepare(
            "SELECT falhas, janela_iniciada_em
             FROM limites_login
             WHERE email_hash = :email_hash AND origem_hash = :origem_hash
             FOR UPDATE"
        );
        $stmt->bindValue(":email_hash", $emailHash);
        $stmt->bindValue(":origem_hash", $origemHash);
        $stmt->execute();
        $limite = $stmt->fetch();

        $janelaExpirada = strtotime($limite["janela_iniciada_em"]) < time() - 900;
        $falhas = $janelaExpirada ? 1 : (int) $limite["falhas"] + 1;
        $inicioJanela = $janelaExpirada
            ? date("Y-m-d H:i:s")
            : $limite["janela_iniciada_em"];
        $bloqueadoAte = $falhas >= 8
            ? date("Y-m-d H:i:s", time() + 900)
            : null;

        $stmt = $pdo->prepare(
            "UPDATE limites_login
             SET falhas = :falhas, janela_iniciada_em = :inicio,
                 bloqueado_ate = :bloqueado
             WHERE email_hash = :email_hash AND origem_hash = :origem_hash"
        );
        $stmt->bindValue(":falhas", $falhas, PDO::PARAM_INT);
        $stmt->bindValue(":inicio", $inicioJanela);
        $stmt->bindValue(":bloqueado", $bloqueadoAte);
        $stmt->bindValue(":email_hash", $emailHash);
        $stmt->bindValue(":origem_hash", $origemHash);
        $stmt->execute();
        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function plataforma_login_bloqueado(PDO $pdo, string $email, string $origem): bool
{
    $emailHash = hash("sha256", strtolower($email), true);
    $origemHash = hash("sha256", $origem, true);
    $stmt = $pdo->prepare(
        "SELECT bloqueado_ate > NOW()
         FROM limites_login
         WHERE email_hash = :email_hash AND origem_hash = :origem_hash
         LIMIT 1"
    );
    $stmt->bindValue(":email_hash", $emailHash, PDO::PARAM_LOB);
    $stmt->bindValue(":origem_hash", $origemHash, PDO::PARAM_LOB);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function plataforma_limpar_falhas_login(PDO $pdo, string $email, string $origem): void
{
    $emailHash = hash("sha256", strtolower($email), true);
    $origemHash = hash("sha256", $origem, true);
    $stmt = $pdo->prepare(
        "DELETE FROM limites_login
         WHERE email_hash = :email_hash AND origem_hash = :origem_hash"
    );
    $stmt->bindValue(":email_hash", $emailHash, PDO::PARAM_LOB);
    $stmt->bindValue(":origem_hash", $origemHash, PDO::PARAM_LOB);
    $stmt->execute();
}

function plataforma_redirecionar(string $destino): never
{
    header("Location: " . $destino);
    exit;
}

plataforma_sessao();
