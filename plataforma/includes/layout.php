<?php

declare(strict_types=1);

function plataforma_cabecalho(string $titulo, ?array $usuario = null): void
{
    $preferencias = $usuario["preferencias"] ?? [];
    $classes = [];
    $diretorioAtual = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $prefixoPlataforma = str_contains($diretorioAtual, "/professor")
        ? "../"
        : "";
    $acessibilidadeAtivada = !empty($preferencias["acessibilidade_visual"]);

    if ($acessibilidadeAtivada && !empty($preferencias["alto_contraste"])) {
        $classes[] = "alto-contraste";
    }
    if ($acessibilidadeAtivada && !empty($preferencias["espacamento_linhas"])) {
        $classes[] = "espacamento-ampliado";
    }
    if ($acessibilidadeAtivada && !empty($preferencias["melhor_legibilidade"])) {
        $classes[] = "texto-legivel";
    }
    if ($acessibilidadeAtivada && !empty($preferencias["reduzir_animacoes"])) {
        $classes[] = "animacoes-reduzidas";
    }
    if ($acessibilidadeAtivada && !empty($preferencias["destacar_interativos"])) {
        $classes[] = "interativos-destacados";
    }
    $tamanhoFonte = $preferencias["tamanho_fonte"] ?? "padrao";
    if ($acessibilidadeAtivada && $tamanhoFonte !== "padrao") {
        $classes[] = "fonte-" . $tamanhoFonte;
    }
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= plataforma_escapar($titulo) ?> — Palavra a Bordo</title>
    <link rel="stylesheet" href="<?= plataforma_escapar($prefixoPlataforma) ?>css/acessibilidade.css">
</head>
<body class="<?= plataforma_escapar(implode(" ", $classes)) ?>">
    <header>
        <a href="<?= plataforma_escapar($prefixoPlataforma) ?>index.php">Palavra a Bordo</a>
        <?php if ($usuario): ?>
            <nav aria-label="Navegação principal">
                <?php if ($usuario["perfil"] === "professor"): ?>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>professor/index.php">Painel do professor</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>professor/turmas.php">Minhas turmas</a>
                <?php else: ?>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>inicio.php">Início</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>jornada.php">Jornada</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>conquistas.php">Conquistas</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>tesouro.php">Tesouro</a>
                <?php endif; ?>
                <a href="<?= plataforma_escapar($prefixoPlataforma) ?>../jogo1/index.php?desafio=1">Jogo 1</a>
                <a href="<?= plataforma_escapar($prefixoPlataforma) ?>../jogo2/index.php">Jogo 2</a>
                <a href="<?= plataforma_escapar($prefixoPlataforma) ?>perfil.php">Perfil</a>
                <form method="post" action="<?= plataforma_escapar($prefixoPlataforma) ?>logout.php">
                    <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
                    <button type="submit">Sair da conta</button>
                </form>
            </nav>
            <p>Olá, <?= plataforma_escapar($usuario["nome"]) ?>.</p>
        <?php else: ?>
            <nav aria-label="Acesso">
                <a href="login.php">Entrar</a>
                <a href="cadastro.php">Criar conta de estudante</a>
            </nav>
        <?php endif; ?>
    </header>
    <main>
<?php
}

function plataforma_rodape(): void
{
    ?>
    </main>
    <footer><p>Palavra a Bordo — plataforma educacional.</p></footer>
</body>
</html>
<?php
}

function plataforma_mensagem_erro(string $mensagem): void
{
    echo '<p role="alert">' . plataforma_escapar($mensagem) . '</p>';
}
