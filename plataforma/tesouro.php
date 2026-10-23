<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/services.php";
require_once __DIR__ . "/includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("index.php?configuracao=pendente");
}
$usuario = plataforma_exigir_login($pdo, "aluno");
$erro = "";
$sucesso = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    try {
        plataforma_validar_csrf();
        $recompensaId = filter_var(
            $_POST["recompensa_id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        if ($recompensaId === false) {
            throw new RuntimeException("Recompensa inválida.");
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT id, codigo, nome, custo_pontos, condicoes
             FROM recompensas
             WHERE id = :id AND ativa = 1
             FOR UPDATE"
        );
        $stmt->execute(["id" => $recompensaId]);
        $recompensa = $stmt->fetch();
        if (!$recompensa) {
            throw new RuntimeException("Esta recompensa não está disponível.");
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM resgates_recompensas
             WHERE usuario_id = :usuario_id AND recompensa_id = :recompensa_id
             LIMIT 1"
        );
        $stmt->execute([
            "usuario_id" => (int) $usuario["id"],
            "recompensa_id" => (int) $recompensa["id"]
        ]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException("Você já resgatou esta recompensa.");
        }

        $condicoes = $recompensa["condicoes"] === null
            ? []
            : json_decode($recompensa["condicoes"], true, 16, JSON_THROW_ON_ERROR);
        if (
            !is_array($condicoes) ||
            ($condicoes !== [] && array_is_list($condicoes))
        ) {
            throw new RuntimeException("As condições desta recompensa estão configuradas incorretamente.");
        }
        $condicoesPermitidas = [
            "nivel_minimo",
            "missoes_concluidas_minimo",
            "conquista_id"
        ];
        if (array_diff(array_keys($condicoes), $condicoesPermitidas)) {
            throw new RuntimeException("Esta recompensa contém uma condição não suportada.");
        }
        foreach ($condicoes as $nomeCondicao => $valorCondicao) {
            if (
                filter_var(
                    $valorCondicao,
                    FILTER_VALIDATE_INT,
                    ["options" => ["min_range" => 0]]
                ) === false
            ) {
                throw new RuntimeException("Esta recompensa contém uma condição inválida.");
            }
        }
        $nivelMinimo = (int) ($condicoes["nivel_minimo"] ?? 0);
        if ((int) $usuario["nivel"] < $nivelMinimo) {
            throw new RuntimeException("Esta recompensa exige nível " . $nivelMinimo . ".");
        }
        $missoesMinimas = (int) ($condicoes["missoes_concluidas_minimo"] ?? 0);
        if ($missoesMinimas > 0) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT t.desafio_id)
                 FROM tentativas t
                 INNER JOIN desafios d ON d.id = t.desafio_id
                 INNER JOIN categorias c ON c.id = d.categoria_id
                 WHERE t.usuario_id = :usuario_id
                   AND t.concluido = 1 AND t.vidas_restantes > 0
                   AND c.jogo_id = 2"
            );
            $stmt->execute(["usuario_id" => (int) $usuario["id"]]);
            if ((int) $stmt->fetchColumn() < $missoesMinimas) {
                throw new RuntimeException(
                    "Conclua pelo menos {$missoesMinimas} missões para resgatar esta recompensa."
                );
            }
        }
        $conquistaMinima = (int) ($condicoes["conquista_id"] ?? 0);
        if ($conquistaMinima > 0) {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM usuario_conquistas
                 WHERE usuario_id = :usuario_id AND conquista_id = :conquista_id"
            );
            $stmt->execute([
                "usuario_id" => (int) $usuario["id"],
                "conquista_id" => $conquistaMinima
            ]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException("Desbloqueie a conquista exigida para resgatar esta recompensa.");
            }
        }

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO carteiras (usuario_id, saldo_pontos)
             VALUES (:usuario_id, 0)"
        );
        $stmt->execute(["usuario_id" => (int) $usuario["id"]]);
        $stmt = $pdo->prepare(
            "SELECT saldo_pontos FROM carteiras WHERE usuario_id = :usuario_id FOR UPDATE"
        );
        $stmt->execute(["usuario_id" => (int) $usuario["id"]]);
        $saldo = (int) $stmt->fetchColumn();
        $custo = (int) $recompensa["custo_pontos"];
        if ($saldo < $custo) {
            throw new RuntimeException(
                "Faltam " . ($custo - $saldo) . " pontos para resgatar esta recompensa."
            );
        }

        $transacaoId = null;
        if ($custo > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO transacoes_carteira
                    (usuario_id, tipo, valor_pontos, origem_tipo,
                     origem_referencia_id, chave_idempotencia, descricao)
                 VALUES
                    (:usuario_id, 'debito', :valor, 'recompensa',
                     :referencia, :chave, :descricao)"
            );
            $stmt->execute([
                "usuario_id" => (int) $usuario["id"],
                "valor" => $custo,
                "referencia" => (int) $recompensa["id"],
                "chave" => "resgate:" . (int) $usuario["id"] . ":" . (int) $recompensa["id"],
                "descricao" => "Resgate: " . $recompensa["nome"]
            ]);
            $transacaoId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare(
                "UPDATE carteiras
                 SET saldo_pontos = saldo_pontos - :custo_descontado
                 WHERE usuario_id = :usuario_id AND saldo_pontos >= :saldo_minimo"
            );
            $stmt->execute([
                "custo_descontado" => $custo,
                "usuario_id" => (int) $usuario["id"],
                "saldo_minimo" => $custo
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException("O saldo mudou. Atualize a página e tente novamente.");
            }
        }

        $stmt = $pdo->prepare(
            "INSERT INTO resgates_recompensas
                (usuario_id, recompensa_id, custo_pago, transacao_carteira_id)
             VALUES (:usuario_id, :recompensa_id, :custo, :transacao_id)"
        );
        $stmt->execute([
            "usuario_id" => (int) $usuario["id"],
            "recompensa_id" => (int) $recompensa["id"],
            "custo" => $custo,
            "transacao_id" => $transacaoId
        ]);
        $pdo->commit();
        $sucesso = "Recompensa resgatada.";
    } catch (Throwable $falha) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($falha instanceof PDOException && $falha->getCode() === "23000") {
            $erro = "Esta recompensa já foi resgatada.";
        } else {
            if ($falha instanceof PDOException || $falha instanceof JsonException) {
                error_log("[Plataforma] Erro ao resgatar recompensa: " . $falha->getMessage());
                $erro = "Não foi possível concluir o resgate agora. Tente novamente.";
            } else {
                $erro = $falha->getMessage();
            }
        }
    }
}

plataforma_sincronizar_carteira($pdo, (int) $usuario["id"]);
$stmt = $pdo->prepare("SELECT saldo_pontos FROM carteiras WHERE usuario_id = :id");
$stmt->execute(["id" => (int) $usuario["id"]]);
$saldo = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT r.id, r.codigo, r.nome, r.descricao, r.tipo, r.custo_pontos, r.condicoes,
            rr.resgatada_em
     FROM recompensas r
     LEFT JOIN resgates_recompensas rr
       ON rr.recompensa_id = r.id AND rr.usuario_id = :usuario_id
     WHERE r.ativa = 1
     ORDER BY r.custo_pontos, r.nome"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$recompensas = $stmt->fetchAll();
$iconesRecompensas = [
    "titulo-grumete-das-palavras" => "⚓",
    "insignia-olho-de-aguia" => "🦅",
    "bau-mapa-do-tesouro" => "🗺️",
    "titulo-capitao-das-palavras" => "🏴‍☠️",
    "companheiro-peixinho" => "🐠",
    "gema-do-mar" => "💎",
    "chapeu-de-pirata" => "🏴‍☠️"
];

plataforma_cabecalho("Tesouro", $usuario);
?>
<div class="pagina-centralizada pagina-tesouro">
<header class="cabecalho-colecao cabecalho-tesouro">
    <span class="icone-cabecalho-colecao" aria-hidden="true">💎</span>
    <div>
        <p class="sobretitulo">BAÚ DE BORDO</p>
        <h1>Tesouro</h1>
        <p>Troque seus pontos por lembranças digitais da expedição.</p>
    </div>
    <div class="saldo-tesouro">
        <span>Seu saldo</span>
        <strong><?= number_format($saldo, 0, ",", ".") ?></strong>
        <small>pontos</small>
    </div>
</header>
<?php if ($erro !== ""): ?>
    <div class="mensagem-tesouro mensagem-tesouro-erro"><?php plataforma_mensagem_erro($erro); ?></div>
<?php endif; ?>
<?php if ($sucesso !== ""): ?>
    <p class="mensagem-tesouro mensagem-tesouro-sucesso" role="status">
        <span aria-hidden="true">✨</span> <?= plataforma_escapar($sucesso) ?>
    </p>
<?php endif; ?>
<?php if (!$recompensas): ?>
    <p class="estado-vazio-tesouro">O baú está sendo preparado. Volte em breve para conferir as novidades.</p>
<?php else: ?>
    <ul class="grade-tesouro">
        <?php foreach ($recompensas as $recompensa): ?>
            <?php
            $resgatada = !empty($recompensa["resgatada_em"]);
            $iconeRecompensa = $iconesRecompensas[$recompensa["codigo"]] ?? "✨";
            $tipoRecompensa = match ($recompensa["tipo"]) {
                "bau" => "Baú especial",
                "insignia" => "Insígnia",
                "titulo" => "Título de bordo",
                "item" => "Colecionável digital",
                "desafio" => "Desafio",
                default => "Recompensa"
            };
            ?>
            <li class="cartao-recompensa<?= $resgatada ? " recompensa-resgatada" : "" ?>">
                <div class="topo-recompensa">
                    <span class="icone-recompensa" aria-hidden="true"><?= $iconeRecompensa ?></span>
                    <span class="tipo-recompensa"><?= $tipoRecompensa ?></span>
                </div>
                <h2><?= plataforma_escapar($recompensa["nome"]) ?></h2>
                <p><?= plataforma_escapar($recompensa["descricao"]) ?></p>
                <div class="custo-recompensa">
                    <span aria-hidden="true">✦</span>
                    <strong><?= number_format((int) $recompensa["custo_pontos"], 0, ",", ".") ?></strong>
                    <span>pontos</span>
                </div>
                <?php if ($recompensa["resgatada_em"]): ?>
                    <p class="estado-recompensa">Já está no seu baú</p>
                <?php else: ?>
                    <form method="post" action="tesouro.php">
                        <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
                        <input type="hidden" name="recompensa_id" value="<?= (int) $recompensa["id"] ?>">
                        <button class="botao-resgatar" type="submit" <?= $saldo < (int) $recompensa["custo_pontos"] ? "disabled" : "" ?>>Resgatar recompensa</button>
                        <?php if ($saldo < (int) $recompensa["custo_pontos"]): ?>
                            <span class="faltam-pontos">Faltam <?= number_format((int) $recompensa["custo_pontos"] - $saldo, 0, ",", ".") ?> pontos.</span>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<section aria-labelledby="historico">
    <h2 id="historico">Histórico de movimentações</h2>
    <?php
    $stmt = $pdo->prepare(
        "SELECT tipo, valor_pontos, origem_tipo, descricao, criada_em
         FROM transacoes_carteira
         WHERE usuario_id = :usuario_id
         ORDER BY id DESC
         LIMIT 100"
    );
    $stmt->execute(["usuario_id" => (int) $usuario["id"]]);
    $transacoes = $stmt->fetchAll();
    ?>
    <?php if (!$transacoes): ?>
        <p>Nenhuma movimentação registrada.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($transacoes as $transacao): ?>
                <li><?= plataforma_escapar($transacao["tipo"]) ?>:
                    <?= (int) $transacao["valor_pontos"] ?> pontos —
                    <?= plataforma_escapar($transacao["descricao"] ?: $transacao["origem_tipo"]) ?>,
                    <?= plataforma_escapar($transacao["criada_em"]) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
</div>
<?php plataforma_rodape(); ?>
