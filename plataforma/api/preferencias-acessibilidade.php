<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/layout.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, private");

function plataforma_responder_json(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

try {
    $pdo = plataforma_conexao();
    if (!plataforma_migracao_aplicada($pdo)) {
        plataforma_responder_json([
            "sucesso" => false,
            "mensagem" => "As preferências não estão disponíveis no momento."
        ], 503);
    }

    $metodo = $_SERVER["REQUEST_METHOD"] ?? "GET";
    if ($metodo === "GET") {
        $usuario = plataforma_usuario($pdo);
        $autenticado = $usuario !== null && $usuario["perfil"] !== "convidado";
        $preferencias = $autenticado
            ? plataforma_preferencias_acessibilidade_normalizadas(
                $usuario["preferencias"] ?? []
            )
            : [
                "configurada" => false,
                "fonte_extra" => false,
                "alto_contraste" => false,
                "espacamento_texto" => false,
                "reduzir_animacoes" => false,
                "acessibilidade_visual" => false
            ];

        plataforma_responder_json([
            "sucesso" => true,
            "autenticado" => $autenticado,
            "preferencias" => $preferencias
        ]);
    }

    if ($metodo !== "POST") {
        plataforma_responder_json([
            "sucesso" => false,
            "mensagem" => "Método não permitido."
        ], 405);
    }

    plataforma_validar_csrf();
    $usuario = plataforma_usuario($pdo);
    if (!$usuario || $usuario["perfil"] === "convidado") {
        plataforma_responder_json([
            "sucesso" => false,
            "mensagem" => "Entre na sua conta para sincronizar as preferências."
        ], 401);
    }

    $preferencias = [];
    foreach (
        [
            "fonte_extra",
            "alto_contraste",
            "espacamento_texto",
            "reduzir_animacoes",
            "acessibilidade_visual"
        ]
        as $campo
    ) {
        $valor = $_POST[$campo] ?? null;
        if (!is_string($valor) || !in_array($valor, ["0", "1"], true)) {
            plataforma_responder_json([
                "sucesso" => false,
                "mensagem" => "Uma das preferências recebidas é inválida."
            ], 400);
        }
        $preferencias[$campo] = (int) $valor;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO preferencias_acessibilidade
            (usuario_id, configurada, acessibilidade_visual, tamanho_fonte,
             alto_contraste, espacamento_linhas, melhor_legibilidade,
             reduzir_animacoes)
         VALUES
            (:usuario_id, 1, :acessibilidade_visual, :tamanho_fonte, :alto_contraste,
             :espacamento_linhas, :melhor_legibilidade, :reduzir_animacoes)
         ON DUPLICATE KEY UPDATE
            configurada = 1,
            acessibilidade_visual = VALUES(acessibilidade_visual),
            tamanho_fonte = VALUES(tamanho_fonte),
            alto_contraste = VALUES(alto_contraste),
            espacamento_linhas = VALUES(espacamento_linhas),
            melhor_legibilidade = VALUES(melhor_legibilidade),
            reduzir_animacoes = VALUES(reduzir_animacoes)"
    );
    $espacamento = $preferencias["espacamento_texto"];
    $stmt->execute([
        "usuario_id" => (int) $usuario["id"],
        "acessibilidade_visual" => $preferencias["acessibilidade_visual"],
        "tamanho_fonte" => $preferencias["fonte_extra"] ? "grande" : "padrao",
        "alto_contraste" => $preferencias["alto_contraste"],
        "espacamento_linhas" => $espacamento,
        "melhor_legibilidade" => $espacamento,
        "reduzir_animacoes" => $preferencias["reduzir_animacoes"]
    ]);

    plataforma_responder_json([
        "sucesso" => true,
        "preferencias" => [
            "configurada" => true,
            "fonte_extra" => (bool) $preferencias["fonte_extra"],
            "alto_contraste" => (bool) $preferencias["alto_contraste"],
            "espacamento_texto" => (bool) $espacamento,
            "reduzir_animacoes" => (bool) $preferencias["reduzir_animacoes"],
            "acessibilidade_visual" => (bool) $preferencias["acessibilidade_visual"]
        ]
    ]);
} catch (Throwable $erro) {
    if ($erro instanceof PDOException) {
        error_log("[Plataforma] Falha ao sincronizar acessibilidade: " . $erro->getMessage());
        plataforma_responder_json([
            "sucesso" => false,
            "mensagem" => "Não foi possível sincronizar as preferências."
        ], 500);
    }

    plataforma_responder_json([
        "sucesso" => false,
        "mensagem" => $erro instanceof RuntimeException
            ? $erro->getMessage()
            : "Não foi possível salvar as preferências."
    ], http_response_code() >= 400 ? http_response_code() : 400);
}
