<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

$usuario = null;
$erroBanco = false;

try {
    $pdo = plataforma_conexao();
    if (plataforma_migracao_aplicada($pdo)) {
        $usuario = plataforma_usuario($pdo);
        if ($usuario && $usuario["perfil"] === "convidado") {
            $usuario = null;
        }
    }
} catch (Throwable $erro) {
    error_log("[Plataforma] Não foi possível identificar o usuário na página pública: " . $erro->getMessage());
    $erroBanco = true;
}

plataforma_cabecalho("Início", $usuario);
?>
<div class="pagina-inicial">
    <section class="hero-inicial" aria-labelledby="titulo-inicial">
        <div class="hero-conteudo">
            <p class="sobretitulo">Uma aventura de Língua Portuguesa</p>
            <?php if ($usuario && $usuario["perfil"] === "aluno"): ?>
                <h1 id="titulo-inicial">Bem-vindo a bordo!</h1>
                <p>Entre na sua área de jogador para acompanhar sua jornada e acessar os desafios.</p>
                <a class="botao-plataforma botao-dourado" href="inicio.php">Acessar minha área</a>
            <?php elseif ($usuario && $usuario["perfil"] === "professor"): ?>
                <h1 id="titulo-inicial">Sua tripulação aprende melhor com você.</h1>
                <p>Acompanhe as turmas vinculadas à sua conta e consulte os resultados dos estudantes.</p>
                <a class="botao-plataforma botao-dourado" href="professor/index.php">Acessar painel do professor</a>
            <?php else: ?>
                <h1 id="titulo-inicial">Aprender também pode ser uma grande aventura.</h1>
                <p>Navegue por desafios de Língua Portuguesa e construa sua própria jornada de aprendizagem.</p>
                <div class="acoes-hero">
                    <a class="botao-plataforma botao-dourado" href="cadastro.php">Começar como jogador</a>
                    <a class="botao-plataforma botao-contorno" href="login.php">Já tenho uma conta</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($erroBanco): ?>
        <p class="aviso-plataforma" role="status">Não foi possível consultar sua sessão agora. Você ainda pode entrar na plataforma.</p>
    <?php endif; ?>

    <section class="cards-perfil" aria-label="Acesso para jogadores e professores">
        <article class="card-papel card-jogador">
            <span class="selo-card" aria-hidden="true">✦</span>
            <h2>Área do jogador</h2>
            <p>Acompanhe seu progresso, suas conquistas e os desafios disponíveis para sua conta.</p>
            <a class="botao-plataforma" href="<?= $usuario && $usuario["perfil"] === "aluno" ? "inicio.php" : "login.php" ?>">Entrar como jogador</a>
        </article>
        <article class="card-papel card-professor">
            <span class="selo-card" aria-hidden="true">⚓</span>
            <h2>Área do professor</h2>
            <p>Acesse o painel para acompanhar suas turmas e consultar o progresso dos estudantes.</p>
            <div class="acoes-professor<?= $usuario && $usuario["perfil"] === "professor" ? " acoes-professor-unica" : "" ?>" role="group" aria-label="Ações da área do professor">
                <a class="botao-plataforma" href="<?= $usuario && $usuario["perfil"] === "professor" ? "professor/index.php" : "login.php?perfil=professor" ?>">
                    <?= $usuario && $usuario["perfil"] === "professor" ? "Acessar painel do professor" : "Entrar como professor" ?>
                </a>
                <?php if (!$usuario || $usuario["perfil"] === "aluno"): ?>
                    <a class="link-cadastro-professor" href="cadastro.php?perfil=professor" aria-label="Criar conta para professores">
                        Criar conta
                    </a>
                <?php endif; ?>
            </div>
        </article>
    </section>
</div>
<?php plataforma_rodape(); ?>
