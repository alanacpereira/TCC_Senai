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
        $codigo = strtoupper(trim(plataforma_campo($_POST, "codigo_turma")));
        if (!preg_match('/^[A-F0-9]{12}$/', $codigo)) {
            throw new RuntimeException("Informe um código de turma válido.");
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM turmas WHERE codigo = :codigo AND ativa = 1 LIMIT 1"
        );
        $stmt->execute(["codigo" => $codigo]);
        $turmaId = $stmt->fetchColumn();
        if (!$turmaId) {
            throw new RuntimeException("Não foi encontrada uma turma ativa com esse código.");
        }
        // O vínculo libera ao professor da turma o acesso aos resultados deste aluno.
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO turma_alunos (turma_id, aluno_id)
             VALUES (:turma_id, :aluno_id)"
        );
        $stmt->execute([
            "turma_id" => (int) $turmaId,
            "aluno_id" => (int) $usuario["id"]
        ]);
        $sucesso = $stmt->rowCount() === 1
            ? "Você entrou na turma."
            : "Você já está vinculado a essa turma.";
    } catch (Throwable $falha) {
        $erro = $falha->getMessage();
    }
}

$jogos = plataforma_resumo_jogos($pdo, (int) $usuario["id"]);
$stmt = $pdo->prepare(
    "SELECT t.id, t.nome, t.codigo, t.criada_em, tp.vinculada_em
     FROM turma_alunos ta
     INNER JOIN turmas t ON t.id = ta.turma_id
     INNER JOIN turma_professores tp ON tp.turma_id = t.id
     WHERE ta.aluno_id = :aluno_id AND t.ativa = 1
     ORDER BY t.nome, tp.professor_id"
);
$stmt->execute(["aluno_id" => (int) $usuario["id"]]);
$turmas = $stmt->fetchAll();

plataforma_cabecalho("Jornada", $usuario);
?>
<div class="pagina-centralizada pagina-jornada">
<header class="cabecalho-jornada">
    <p class="sobretitulo">SUA TRIPULAÇÃO</p>
    <h1>Minhas turmas</h1>
    <p>Use o código fornecido pelo professor para conectar seus resultados.</p>
</header>
<section class="entrada-turma" aria-labelledby="titulo-entrada-turma">
    <h2 id="titulo-entrada-turma">Entrar em uma turma</h2>
    <?php if ($erro !== ""): plataforma_mensagem_erro($erro); endif; ?>
    <?php if ($sucesso !== ""): ?><p role="status"><?= plataforma_escapar($sucesso) ?></p><?php endif; ?>
    <form method="post" action="jornada.php">
        <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
        <label for="codigo-turma">Código da turma</label>
        <input id="codigo-turma" name="codigo_turma" maxlength="12" pattern="[A-Fa-f0-9]{12}" inputmode="text" autocomplete="off" spellcheck="false" aria-describedby="ajuda-codigo-turma" required>
        <small id="ajuda-codigo-turma">São 12 letras ou números. Peça o código ao seu professor.</small>
        <button type="submit">Conectar à turma</button>
    </form>
</section>
<section aria-labelledby="turmas-conectadas">
    <h2 id="turmas-conectadas">Turmas conectadas</h2>
    <?php if ($turmas): ?>
        <ul class="lista-turmas-aluno">
            <?php foreach ($turmas as $turma): ?>
                <li><?= plataforma_escapar($turma["nome"]) ?> — turma ativa</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="estado-vazio-jornada">Você ainda não entrou em uma turma.</p>
    <?php endif; ?>
</section>
<section aria-labelledby="progresso-jogos">
    <h2 id="progresso-jogos">Progresso nos jogos</h2>
    <?php if ($jogos): ?>
        <table>
            <thead><tr><th scope="col">Jogo</th><th scope="col">Tentativas</th><th scope="col">Concluídas</th><th scope="col">Acertos</th><th scope="col">Pontos</th></tr></thead>
            <tbody>
            <?php foreach ($jogos as $jogo): ?>
                <tr>
                    <th scope="row"><?= plataforma_escapar($jogo["jogo"]) ?></th>
                    <td><?= (int) $jogo["tentativas"] ?></td>
                    <td><?= (int) $jogo["concluidas"] ?></td>
                    <td><?= (int) $jogo["acertos"] ?></td>
                    <td><?= (int) $jogo["pontos"] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Não há jogos ativos cadastrados.</p>
    <?php endif; ?>
</section>
</div>
<?php plataforma_rodape(); ?>
