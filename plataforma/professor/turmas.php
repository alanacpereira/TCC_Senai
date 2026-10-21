<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("../index.php?configuracao=pendente");
}
$professor = plataforma_exigir_login($pdo, "professor");
$erro = "";
$sucesso = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    try {
        plataforma_validar_csrf();
        $nome = trim(plataforma_campo($_POST, "nome"));
        $descricao = trim(plataforma_campo($_POST, "descricao"));
        if ($nome === "" || strlen($nome) > 120 || strlen($descricao) > 500) {
            throw new RuntimeException("Informe um nome de turma de até 120 caracteres e uma descrição de até 500 caracteres.");
        }
        $codigo = strtoupper(bin2hex(random_bytes(6)));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO turmas (codigo, nome, descricao)
             VALUES (:codigo, :nome, :descricao)"
        );
        $stmt->execute([
            "codigo" => $codigo,
            "nome" => $nome,
            "descricao" => $descricao === "" ? null : $descricao
        ]);
        $turmaId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            "INSERT INTO turma_professores (turma_id, professor_id)
             VALUES (:turma_id, :professor_id)"
        );
        $stmt->execute([
            "turma_id" => $turmaId,
            "professor_id" => (int) $professor["id"]
        ]);
        $pdo->commit();
        $sucesso = "Turma criada. Compartilhe o código {$codigo} com seus estudantes.";
    } catch (Throwable $falha) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($falha instanceof PDOException) {
            error_log("[Plataforma] Falha ao criar turma: " . $falha->getMessage());
            $erro = "Não foi possível criar a turma.";
        } else {
            $erro = $falha->getMessage();
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT t.id, t.nome, t.codigo, t.descricao, t.ativa,
            COUNT(DISTINCT ta.aluno_id) AS total_alunos
     FROM turmas t
     INNER JOIN turma_professores tp ON tp.turma_id = t.id
     LEFT JOIN turma_alunos ta ON ta.turma_id = t.id
     WHERE tp.professor_id = :professor_id
     GROUP BY t.id, t.nome, t.codigo, t.descricao, t.ativa
     ORDER BY t.nome"
);
$stmt->execute(["professor_id" => (int) $professor["id"]]);
$turmas = $stmt->fetchAll();

plataforma_cabecalho("Minhas turmas", $professor);
?>
<section class="pagina-centralizada pagina-turmas" aria-labelledby="titulo-turmas">
<h1 id="titulo-turmas">Minhas turmas</h1>
<?php if ($erro !== ""): plataforma_mensagem_erro($erro); endif; ?>
<?php if ($sucesso !== ""): ?><p role="status"><?= plataforma_escapar($sucesso) ?></p><?php endif; ?>
<form method="post" action="turmas.php">
    <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
    <label for="nome-turma">Nome da turma</label>
    <input id="nome-turma" name="nome" maxlength="120" required>
    <label for="descricao-turma">Descrição (opcional)</label>
    <textarea id="descricao-turma" name="descricao" maxlength="500"></textarea>
    <button type="submit">Criar turma</button>
</form>
<ul class="lista-turmas">
    <?php foreach ($turmas as $turma): ?>
        <li class="cartao-turma">
            <a href="turma.php?id=<?= (int) $turma["id"] ?>">
                <strong><?= plataforma_escapar($turma["nome"]) ?></strong>
            </a>
            <span>Código <?= plataforma_escapar($turma["codigo"]) ?></span>
            <span><?= (int) $turma["total_alunos"] ?> estudante(s)</span>
        </li>
    <?php endforeach; ?>
</ul>
</section>
<?php plataforma_rodape(); ?>
