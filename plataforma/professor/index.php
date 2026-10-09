<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("../index.php?configuracao=pendente");
}
$professor = plataforma_exigir_login($pdo, "professor");
$stmt = $pdo->prepare(
    "SELECT
        t.id, t.nome, t.codigo, t.ativa,
        (SELECT COUNT(*)
         FROM turma_alunos ta
         WHERE ta.turma_id = t.id) AS total_alunos,
        (SELECT COUNT(*)
         FROM turma_alunos ta
         INNER JOIN tentativas tentativa
            ON tentativa.usuario_id = ta.aluno_id
         WHERE ta.turma_id = t.id) AS tentativas
     FROM turma_professores tp
     INNER JOIN turmas t ON t.id = tp.turma_id
     WHERE tp.professor_id = :professor_id
     ORDER BY t.nome"
);
$stmt->execute(["professor_id" => (int) $professor["id"]]);
$turmas = $stmt->fetchAll();

plataforma_cabecalho("Painel do professor", $professor);
?>
<h1>Painel do professor</h1>
<p>Os relatórios são limitados às turmas associadas à sua conta.</p>
<p><a href="turmas.php">Administrar minhas turmas</a></p>
<section aria-labelledby="turmas-autorizadas">
    <h2 id="turmas-autorizadas">Turmas autorizadas</h2>
    <?php if (!$turmas): ?>
        <p>Você ainda não tem turmas. Crie uma turma para receber estudantes.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($turmas as $turma): ?>
                <li>
                    <a href="turma.php?id=<?= (int) $turma["id"] ?>"><?= plataforma_escapar($turma["nome"]) ?></a>
                    — código <?= plataforma_escapar($turma["codigo"]) ?>,
                    <?= (int) $turma["total_alunos"] ?> estudante(s),
                    <?= (int) $turma["tentativas"] ?> tentativa(s)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php plataforma_rodape(); ?>
