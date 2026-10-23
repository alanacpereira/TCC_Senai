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
plataforma_atualizar_conquistas($pdo, (int) $usuario["id"]);
$stmt = $pdo->prepare(
    "SELECT c.id, c.nome, c.descricao, c.icone, c.condicao,
            uc.data_conquista
     FROM conquistas c
     LEFT JOIN usuario_conquistas uc
       ON uc.conquista_id = c.id AND uc.usuario_id = :usuario_id
     ORDER BY c.jogo_id, c.id"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$conquistas = $stmt->fetchAll();
$totalConquistas = count($conquistas);
$conquistasObtidas = count(array_filter(
    $conquistas,
    static fn (array $conquista): bool => !empty($conquista["data_conquista"])
));
$progressoConquistas = $totalConquistas > 0
    ? (int) round($conquistasObtidas / $totalConquistas * 100)
    : 0;

plataforma_cabecalho("Conquistas", $usuario);
?>
<section class="pagina-centralizada pagina-conquistas" aria-labelledby="titulo-conquistas">
<header class="cabecalho-colecao">
    <span class="icone-cabecalho-colecao" aria-hidden="true">🏆</span>
    <div>
        <p class="sobretitulo">DIÁRIO DE BORDO</p>
        <h1 id="titulo-conquistas">Conquistas</h1>
        <p>Suas descobertas e grandes momentos nesta viagem.</p>
    </div>
</header>
<div class="resumo-colecao">
    <div class="rotulo-progresso-colecao">
        <span>Conquistas descobertas</span>
        <strong><?= $conquistasObtidas ?> de <?= $totalConquistas ?></strong>
    </div>
    <div
        class="trilho-colecao"
        role="progressbar"
        aria-label="Progresso das conquistas"
        aria-valuemin="0"
        aria-valuemax="<?= $totalConquistas ?>"
        aria-valuenow="<?= $conquistasObtidas ?>">
        <span style="width: <?= $progressoConquistas ?>%"></span>
    </div>
</div>
<?php if (!$conquistas): ?>
    <p>Ainda não há conquistas cadastradas.</p>
<?php else: ?>
    <ul class="grade-conquistas">
        <?php foreach ($conquistas as $conquista): ?>
            <?php
            $desbloqueada = !empty($conquista["data_conquista"]);
            $iconeConquista = match ($conquista["icone"] ?? "") {
                "medalha-primeiro-acerto" => "⚓",
                "medalha-olho-de-aguia" => "🦅",
                "medalha-mestre-ortografia" => "📜",
                default => "✦"
            };
            ?>
            <li class="cartao-conquista<?= $desbloqueada ? " conquista-desbloqueada" : "" ?>">
                <span class="icone-conquista" aria-hidden="true"><?= $iconeConquista ?></span>
                <div class="conteudo-conquista">
                    <span class="estado-conquista">
                        <?= $desbloqueada ? "Conquistada" : "No horizonte" ?>
                    </span>
                    <h2><?= plataforma_escapar($conquista["nome"]) ?></h2>
                    <p><?= plataforma_escapar($conquista["descricao"]) ?></p>
                    <p class="condicao-conquista">
                        <strong>Como conquistar:</strong>
                        <?= plataforma_escapar($conquista["condicao"] ?: "Continue jogando para descobrir.") ?>
                    </p>
                    <?php if ($desbloqueada): ?>
                        <p class="data-conquista">
                            Registrada em <?= plataforma_escapar($conquista["data_conquista"]) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
<?php plataforma_rodape(); ?>
