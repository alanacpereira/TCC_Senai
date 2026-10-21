<?php

declare(strict_types=1);

function plataforma_preferencias(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT configurada, acessibilidade_visual, tamanho_fonte, alto_contraste,
                espacamento_linhas, melhor_legibilidade, reduzir_animacoes,
                destacar_interativos
         FROM preferencias_acessibilidade
         WHERE usuario_id = :usuario_id
         LIMIT 1"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);
    $preferencias = $stmt->fetch();

    $preferenciasPadrao = [
        "configurada" => 0,
        "acessibilidade_visual" => 0,
        "tamanho_fonte" => "padrao",
        "alto_contraste" => 0,
        "espacamento_linhas" => 0,
        "melhor_legibilidade" => 0,
        "reduzir_animacoes" => 0,
        "destacar_interativos" => 0
    ];
    if (
        !$preferencias
        || empty($preferencias["configurada"])
        || empty($preferencias["acessibilidade_visual"])
    ) {
        $preferenciasPadrao["configurada"] = (int) !empty($preferencias["configurada"]);
        return $preferenciasPadrao;
    }

    return $preferencias;
}

function plataforma_exigir_aluno(PDO $pdo, string $urlLogin): array
{
    $usuario = plataforma_usuario($pdo);

    if (!$usuario || $usuario["perfil"] === "convidado") {
        header("Location: " . $urlLogin);
        exit;
    }

    if ($usuario["perfil"] !== "aluno") {
        http_response_code(403);
        exit("Esta área é exclusiva para jogadores.");
    }

    return $usuario;
}

function plataforma_exigir_aluno_api(PDO $pdo): int
{
    plataforma_sessao();
    $usuarioId = Auth::usuarioId();

    if ($usuarioId === null) {
        plataforma_responder_erro_api(401, "Entre na sua conta para continuar.");
    }

    $stmt = $pdo->prepare(
        "SELECT perfil FROM usuarios WHERE id = :id LIMIT 1"
    );
    $stmt->execute(["id" => $usuarioId]);
    $perfil = $stmt->fetchColumn();

    if ($perfil === false || $perfil === "convidado") {
        plataforma_responder_erro_api(401, "Sua sessão expirou. Entre novamente.");
    }

    if ($perfil !== "aluno") {
        plataforma_responder_erro_api(403, "Esta área é exclusiva para jogadores.");
    }

    return $usuarioId;
}

function plataforma_responder_erro_api(int $status, string $mensagem): never
{
    http_response_code($status);
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store");
    echo json_encode(
        ["sucesso" => false, "mensagem" => $mensagem],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function plataforma_sincronizar_carteira(PDO $pdo, int $usuarioId): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO carteiras (usuario_id, saldo_pontos)
             VALUES (:usuario_id, 0)"
        );
        $stmt->execute(["usuario_id" => $usuarioId]);

        $stmt = $pdo->prepare(
            "SELECT id, pontuacao
             FROM tentativas
             WHERE usuario_id = :usuario_id
               AND concluido = 1
             ORDER BY id"
        );
        $stmt->execute(["usuario_id" => $usuarioId]);
        $tentativas = $stmt->fetchAll();

        foreach ($tentativas as $tentativa) {
            $pontos = (int) $tentativa["pontuacao"];
            if ($pontos <= 0) {
                continue;
            }

            $chave = "tentativa-concluida:" . (int) $tentativa["id"];
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO transacoes_carteira
                    (usuario_id, tipo, valor_pontos, origem_tipo,
                     origem_referencia_id, chave_idempotencia, descricao)
                 VALUES
                    (:usuario_id, 'credito', :valor, 'tentativa',
                     :referencia, :chave, 'Pontuação de tentativa concluída')"
            );
            $stmt->execute([
                "usuario_id" => $usuarioId,
                "valor" => $pontos,
                "referencia" => (int) $tentativa["id"],
                "chave" => $chave
            ]);

            if ($stmt->rowCount() === 1) {
                $stmt = $pdo->prepare(
                    "UPDATE carteiras
                     SET saldo_pontos = saldo_pontos + :valor
                     WHERE usuario_id = :usuario_id"
                );
                $stmt->execute([
                    "valor" => $pontos,
                    "usuario_id" => $usuarioId
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function plataforma_atualizar_conquistas(PDO $pdo, int $usuarioId): void
{
    $stmt = $pdo->prepare(
        "SELECT id, nome
         FROM conquistas
         WHERE jogo_id = 1"
    );
    $stmt->execute();
    $conquistas = $stmt->fetchAll();
    if (!$conquistas) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS acertos
         FROM respostas r
         INNER JOIN tentativas t ON t.id = r.tentativa_id
         INNER JOIN desafios d ON d.id = t.desafio_id
         INNER JOIN categorias c ON c.id = d.categoria_id
         WHERE t.usuario_id = :usuario_id
           AND c.jogo_id = 1
           AND r.correta = 1"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);
    $totalAcertos = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM tentativas t
         WHERE t.usuario_id = :usuario_id
           AND t.concluido = 1
           AND t.vidas_restantes > 0
           AND EXISTS (
               SELECT 1
               FROM desafios d
               INNER JOIN categorias c ON c.id = d.categoria_id
               WHERE d.id = t.desafio_id AND c.jogo_id = 1
           )
           AND NOT EXISTS (
               SELECT 1
               FROM respostas r
               WHERE r.tentativa_id = t.id
                 AND r.correta = 0
           )
           AND (
               SELECT COUNT(*)
               FROM respostas r
               WHERE r.tentativa_id = t.id
           ) = (
               SELECT COUNT(*)
               FROM perguntas p
               WHERE p.desafio_id = t.desafio_id
           )"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);
    $tentativasPerfeitas = (int) $stmt->fetchColumn();

    foreach ($conquistas as $conquista) {
        $nome = strtolower($conquista["nome"]);
        $elegivel = match (true) {
            str_contains($nome, "primeiro acerto") => $totalAcertos >= 1,
            str_contains($nome, "olho de aguia"),
            str_contains($nome, "olho de águia") => $totalAcertos >= 5,
            str_contains($nome, "mestre da ortografia") => $tentativasPerfeitas >= 1,
            default => false
        };

        if ($elegivel) {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO usuario_conquistas (usuario_id, conquista_id)
                 VALUES (:usuario_id, :conquista_id)"
            );
            $stmt->execute([
                "usuario_id" => $usuarioId,
                "conquista_id" => (int) $conquista["id"]
            ]);
        }
    }
}

function plataforma_resumo_jogos(PDO $pdo, int $usuarioId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            j.id AS jogo_id,
            j.nome AS jogo,
            COUNT(DISTINCT t.id) AS tentativas,
            COALESCE(SUM(t.pontuacao), 0) AS pontos,
            COALESCE(SUM(
                (SELECT COUNT(*) FROM respostas r
                 WHERE r.tentativa_id = t.id AND r.correta = 1)
            ), 0) AS acertos,
            COALESCE(SUM(t.concluido = 1), 0) AS concluidas
         FROM jogos j
         LEFT JOIN categorias c ON c.jogo_id = j.id
         LEFT JOIN desafios d ON d.categoria_id = c.id
         LEFT JOIN tentativas t
            ON t.desafio_id = d.id AND t.usuario_id = :usuario_id
         WHERE j.ativo = 1
         GROUP BY j.id, j.nome
         ORDER BY j.id"
    );
    $stmt->execute(["usuario_id" => $usuarioId]);

    return $stmt->fetchAll();
}
