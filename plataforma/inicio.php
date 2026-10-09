<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/services.php";
require_once __DIR__ . "/includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("index.php?configuracao=pendente");
}
$usuario = plataforma_exigir_login($pdo);
if ($usuario["perfil"] === "professor") {
    plataforma_redirecionar("professor/index.php");
}

plataforma_sincronizar_carteira($pdo, (int) $usuario["id"]);
plataforma_atualizar_conquistas($pdo, (int) $usuario["id"]);
$jogos = plataforma_resumo_jogos($pdo, (int) $usuario["id"]);
$stmt = $pdo->prepare(
    "SELECT saldo_pontos FROM carteiras WHERE usuario_id = :usuario_id"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$saldo = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM usuario_conquistas WHERE usuario_id = :usuario_id"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$conquistas = (int) $stmt->fetchColumn();

plataforma_cabecalho("Início", $usuario);
?>
<h1>Olá, <?= plataforma_escapar($usuario["nome"]) ?>!</h1>
<p>Continue sua jornada de aprendizagem na Palavra a Bordo.</p>
<section aria-labelledby="resumo-pessoal">
    <h2 id="resumo-pessoal">Seu progresso</h2>
    <dl>
        <dt>Nível</dt><dd><?= (int) $usuario["nivel"] ?></dd>
        <dt>Experiência</dt><dd><?= number_format((int) $usuario["experiencia"], 0, ",", ".") ?> XP</dd>
        <dt>Pontos no tesouro</dt><dd><?= number_format($saldo, 0, ",", ".") ?></dd>
        <dt>Conquistas obtidas</dt><dd><?= $conquistas ?></dd>
    </dl>
</section>
<section aria-labelledby="jogos">
    <h2 id="jogos">Jogar</h2>
    <ul>
        <li><a href="../jogo1/index.php?desafio=1">Jogo 1 — Caça ao Erro</a></li>
        <li><a href="../jogo2/index.php">Jogo 2 — Expedição dos Sete Mares</a></li>
    </ul>
</section>
<section aria-labelledby="atividade-recente">
    <h2 id="atividade-recente">Progresso por jogo</h2>
    <?php if (!$jogos): ?>
        <p>Nenhum jogo ativo está cadastrado.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($jogos as $jogo): ?>
                <li>
                    <strong><?= plataforma_escapar($jogo["jogo"]) ?></strong>:
                    <?= (int) $jogo["tentativas"] ?> tentativas,
                    <?= (int) $jogo["concluidas"] ?> concluídas,
                    <?= (int) $jogo["acertos"] ?> acertos,
                    <?= (int) $jogo["pontos"] ?> pontos registrados.
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php plataforma_rodape(); ?>
