<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/services.php";
require_once __DIR__ . "/includes/layout.php";

try {
    $pdo = plataforma_conexao();
    $pronta = plataforma_migracao_aplicada($pdo);
    $usuario = $pronta ? plataforma_usuario($pdo) : null;
} catch (Throwable $erro) {
    error_log("[Plataforma] Não foi possível carregar a página inicial: " . $erro->getMessage());
    http_response_code(503);
    $pronta = false;
    $usuario = null;
    $erroBanco = true;
}

plataforma_cabecalho("Início", $usuario);
?>
<h1>Palavra a Bordo</h1>
<p>Uma plataforma educacional para acompanhar sua jornada pelos jogos de Língua Portuguesa.</p>

<?php if (!empty($erroBanco)): ?>
    <section aria-labelledby="banco-indisponivel">
        <h2 id="banco-indisponivel">Banco de dados indisponível</h2>
        <p>Não foi possível conectar ao banco. Confira as variáveis de ambiente da conexão e tente novamente.</p>
    </section>
<?php elseif (!$pronta): ?>
    <section aria-labelledby="migracao-pendente">
        <h2 id="migracao-pendente">Configuração da plataforma pendente</h2>
        <p>Os Jogos 1 e 2 e seus dados existentes foram preservados. As páginas da plataforma dependem das estruturas aditivas descritas na migração abaixo.</p>
        <p><a href="database/migrations/001_plataforma_palavra_a_bordo.sql">Consultar a migração SQL</a></p>
        <p>O sistema não executa essa migração automaticamente. Revise-a, faça um backup do banco e aplique-a manualmente quando estiver autorizada.</p>
        <p>Consulte <a href="README.md">as instruções da plataforma</a>.</p>
    </section>
<?php elseif ($usuario && $usuario["perfil"] !== "convidado"): ?>
    <p><a href="inicio.php">Abrir minha plataforma</a></p>
<?php else: ?>
    <p><a href="login.php">Entrar</a></p>
    <p><a href="cadastro.php">Criar conta de estudante</a></p>
<?php endif; ?>

<section aria-labelledby="jogos-disponiveis">
    <h2 id="jogos-disponiveis">Jogos existentes</h2>
    <ul>
        <li><a href="../jogo1/index.php?desafio=1">Jogo 1 — Caça ao Erro</a></li>
        <li><a href="../jogo2/index.php">Jogo 2 — Expedição dos Sete Mares</a></li>
    </ul>
</section>
<?php plataforma_rodape(); ?>
