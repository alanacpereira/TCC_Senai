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

plataforma_cabecalho("Conquistas", $usuario);
?>
<section class="pagina-centralizada pagina-conquistas" aria-labelledby="titulo-conquistas">
<h1 id="titulo-conquistas">Conquistas</h1>
<?php if (!$conquistas): ?>
    <p>Ainda não há conquistas cadastradas.</p>
<?php else: ?>
    <ul>
        <?php foreach ($conquistas as $conquista): ?>
            <li>
                <h2><?= plataforma_escapar($conquista["nome"]) ?></h2>
                <p><?= plataforma_escapar($conquista["descricao"]) ?></p>
                <p>Condição: <?= plataforma_escapar($conquista["condicao"] ?: "Não informada") ?></p>
                <?php if ($conquista["data_conquista"]): ?>
                    <p>Obtida em <?= plataforma_escapar($conquista["data_conquista"]) ?>.</p>
                <?php else: ?>
                    <p>Ainda não desbloqueada.</p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
<?php plataforma_rodape(); ?>
