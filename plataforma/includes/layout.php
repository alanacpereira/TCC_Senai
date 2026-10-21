<?php

declare(strict_types=1);

function plataforma_avatares(): array
{
    return [
        "marinheiro" => ["nome" => "Marinheiro", "icone" => "⚓"],
        "exploradora" => ["nome" => "Exploradora", "icone" => "🧭"],
        "pirata" => ["nome" => "Pirata amigável", "icone" => "🏴‍☠️"],
        "sereia" => ["nome" => "Sereia", "icone" => "🧜‍♀️"],
        "polvo" => ["nome" => "Polvo", "icone" => "🐙"],
        "golfinho" => ["nome" => "Golfinho", "icone" => "🐬"],
        "tartaruga" => ["nome" => "Tartaruga marinha", "icone" => "🐢"],
        "navio" => ["nome" => "Navio explorador", "icone" => "🚢"]
    ];
}

function plataforma_avatar(string $identificador): array
{
    $avatares = plataforma_avatares();
    return $avatares[$identificador] ?? $avatares["marinheiro"];
}

function plataforma_cabecalho(string $titulo, ?array $usuario = null): void
{
    $preferencias = plataforma_preferencias_acessibilidade_normalizadas(
        $usuario["preferencias"] ?? []
    );
    $diretorioAtual = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $prefixoPlataforma = str_contains($diretorioAtual, "/professor")
        ? "../"
        : "";
    $classes = plataforma_classes_acessibilidade($preferencias);
    $csrfAcessibilidade = plataforma_token_csrf();
    ?>
<!doctype html>
<html lang="pt-BR" class="<?= plataforma_escapar(implode(" ", $classes)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= plataforma_escapar($titulo) ?> — Palavra a Bordo</title>
    <?php plataforma_acessibilidade_bootstrap(
        $usuario !== null && $usuario["perfil"] !== "convidado",
        $preferencias
    ); ?>
    <link rel="stylesheet" href="<?= plataforma_escapar($prefixoPlataforma) ?>css/acessibilidade.css?v=12">
    <link rel="stylesheet" href="<?= plataforma_escapar($prefixoPlataforma) ?>css/plataforma.css?v=29">
</head>
<body class="plataforma-page">
    <header>
        <a class="marca-plataforma" href="<?= plataforma_escapar($prefixoPlataforma) ?>index.php" aria-label="Palavra a Bordo — página inicial">
            <img src="<?= plataforma_escapar($prefixoPlataforma) ?>img/Logo_N%C3%A1utico_Palavra_a_Bordo-removebg-preview.png" alt="Palavra a Bordo">
        </a>
        <?php if ($usuario): ?>
            <nav class="navegacao-plataforma" aria-label="Navegação principal">
                <?php if ($usuario["perfil"] === "professor"): ?>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>index.php">Início</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>professor/index.php">Painel do professor</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>professor/turmas.php">Minhas turmas</a>
                <?php else: ?>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>inicio.php">Início</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>inicio.php#jogos">Jogos</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>conquistas.php">Conquistas</a>
                    <a href="<?= plataforma_escapar($prefixoPlataforma) ?>tesouro.php">Tesouro</a>
                <?php endif; ?>
                <a href="<?= plataforma_escapar($prefixoPlataforma) ?>perfil.php">Perfil</a>
                <form method="post" action="<?= plataforma_escapar($prefixoPlataforma) ?>logout.php">
                    <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
                    <button type="submit">Sair da conta</button>
                </form>
            </nav>
        <?php else: ?>
            <nav class="navegacao-plataforma" aria-label="Navegação principal">
                <a href="<?= plataforma_escapar($prefixoPlataforma) ?>index.php">Início</a>
                <a href="<?= plataforma_escapar($prefixoPlataforma) ?>login.php">Entrar</a>
                <a class="link-destaque" href="<?= plataforma_escapar($prefixoPlataforma) ?>cadastro.php">Cadastrar</a>
            </nav>
        <?php endif; ?>
    </header>
    <?php plataforma_widget_acessibilidade(
        $prefixoPlataforma . "api/preferencias-acessibilidade.php",
        $csrfAcessibilidade
    ); ?>
    <main>
        <?php plataforma_convite_acessibilidade(); ?>
<?php
}

function plataforma_rodape(): void
{
    ?>
    </main>
    <footer><p>Palavra a Bordo — plataforma educacional.</p></footer>
    <script src="<?= plataforma_escapar(
        str_contains(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/professor")
            ? "../js/plataforma.js?v=1"
            : "js/plataforma.js?v=1"
    ) ?>" defer></script>
    <script src="<?= plataforma_escapar(
        str_contains(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "")), "/professor")
            ? "../js/acessibilidade.js?v=3"
            : "js/acessibilidade.js?v=3"
    ) ?>" defer></script>
</body>
</html>
<?php
}

function plataforma_preferencias_acessibilidade_normalizadas(
    array $preferencias
): array {
    $padroes = [
        "fonte_extra" => false,
        "alto_contraste" => false,
        "espacamento_texto" => false,
        "reduzir_animacoes" => false
    ];

    if (empty($preferencias["configurada"])) {
        return $padroes + ["configurada" => false];
    }
    if (empty($preferencias["acessibilidade_visual"])) {
        return $padroes + [
            "configurada" => true,
            "acessibilidade_visual" => false
        ];
    }

    return [
        "configurada" => true,
        "fonte_extra" => ($preferencias["tamanho_fonte"] ?? "padrao") !== "padrao",
        "alto_contraste" => (bool) ($preferencias["alto_contraste"] ?? false),
        "espacamento_texto" => (bool) ($preferencias["espacamento_linhas"] ?? false)
            || (bool) ($preferencias["melhor_legibilidade"] ?? false),
        "reduzir_animacoes" => (bool) ($preferencias["reduzir_animacoes"] ?? false),
        "acessibilidade_visual" => (bool) ($preferencias["acessibilidade_visual"] ?? false)
    ];
}

function plataforma_acessibilidade_bootstrap(
    ?bool $autenticado,
    ?array $preferencias = null
): void {
    $configuracao = [
        "authenticated" => $autenticado,
        "preferences" => $preferencias
    ];
    ?>
    <script>
        window.PalavraAcessibilidadeConfig = <?= json_encode(
            $configuracao,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        (() => {
            const defaults = {
                fonte_extra: false,
                alto_contraste: false,
                espacamento_texto: false,
                reduzir_animacoes: false
            };
            const config = window.PalavraAcessibilidadeConfig;
            const salvas = (() => {
                try {
                    return JSON.parse(localStorage.getItem("palavra-a-bordo-accessibility-v1"));
                } catch (error) {
                    console.warn("Não foi possível ler as preferências locais de acessibilidade.", error);
                    return null;
                }
            })();
            let preferences = { ...defaults };
            let configured = false;
            if (
                config.authenticated === true
                && config.preferences
                && config.preferences.configurada === true
            ) {
                preferences = config.preferences;
                configured = true;
            } else if (salvas && salvas.configurada === true) {
                preferences = { ...defaults, ...salvas };
                configured = true;
            }
            if (config.authenticated === true && config.preferences?.configurada === true) {
                try {
                    localStorage.setItem(
                        "palavra-a-bordo-accessibility-v1",
                        JSON.stringify({ ...preferences, configurada: true })
                    );
                } catch (error) {
                    console.warn("Não foi possível salvar preferências locais de acessibilidade.", error);
                }
            }
            const classes = {
                fonte_extra: "a11y-font-extra",
                alto_contraste: "a11y-high-contrast",
                espacamento_texto: "a11y-text-spacing",
                reduzir_animacoes: "a11y-reduce-motion"
            };
            document.documentElement.dataset.accessibilityConfigured = String(configured);
            Object.entries(classes).forEach(([key, className]) => {
                document.documentElement.classList.toggle(
                    className,
                    Boolean(preferences[key])
                );
            });
        })();
    </script>
    <?php
}

function plataforma_classes_acessibilidade(array $preferencias): array
{
    $mapa = [
        "fonte_extra" => "a11y-font-extra",
        "alto_contraste" => "a11y-high-contrast",
        "espacamento_texto" => "a11y-text-spacing",
        "reduzir_animacoes" => "a11y-reduce-motion"
    ];
    $classes = [];

    foreach ($mapa as $chave => $classe) {
        if (!empty($preferencias[$chave])) {
            $classes[] = $classe;
        }
    }

    return $classes;
}

function plataforma_widget_acessibilidade(string $endpoint, string $csrf): void
{
    ?>
    <div
        id="central-acessibilidade"
        class="central-acessibilidade"
        data-endpoint="<?= plataforma_escapar($endpoint) ?>"
        data-csrf-token="<?= plataforma_escapar($csrf) ?>"
    >
        <button
            type="button"
            id="botao-acessibilidade"
            class="botao-acessibilidade"
            aria-controls="painel-acessibilidade"
            aria-expanded="false"
            aria-label="Abrir central de acessibilidade"
            title="Abrir central de acessibilidade"
        >
            <svg
                class="icone-acessibilidade"
                viewBox="0 0 24 24"
                width="28"
                height="28"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M4 7h9M17 7h3M4 17h3M11 17h9" />
                <circle cx="15" cy="7" r="2" />
                <circle cx="9" cy="17" r="2" />
            </svg>
        </button>
        <section
            id="painel-acessibilidade"
            class="painel-acessibilidade"
            aria-labelledby="titulo-acessibilidade"
            hidden
        >
            <header class="cabecalho-acessibilidade">
                <h2 id="titulo-acessibilidade">Central de Acessibilidade</h2>
                <button type="button" class="fechar-acessibilidade" aria-label="Fechar central de acessibilidade">×</button>
            </header>
            <fieldset>
                <legend>Apresentação da página</legend>
                <label><input type="checkbox" data-preferencia="fonte_extra"> Fonte extra</label>
                <label><input type="checkbox" data-preferencia="alto_contraste"> Alto contraste</label>
                <label><input type="checkbox" data-preferencia="espacamento_texto"> Espaçamento de texto</label>
                <label><input type="checkbox" data-preferencia="reduzir_animacoes"> Reduzir animações</label>
            </fieldset>
            <button type="button" class="restaurar-acessibilidade">Restaurar configurações de acessibilidade</button>
            <p class="status-acessibilidade" role="status" aria-live="polite"></p>
        </section>
    </div>
    <?php
}

function plataforma_convite_acessibilidade(): void
{
    ?>
    <section
        id="convite-acessibilidade"
        class="convite-acessibilidade"
        aria-labelledby="titulo-convite-acessibilidade"
        hidden
    >
        <div>
            <h2 id="titulo-convite-acessibilidade">Deseja ativar os recursos de acessibilidade?</h2>
            <p>Você pode ativá-los agora ou escolher depois na central.</p>
        </div>
        <div class="acoes-convite-acessibilidade">
            <button type="button" data-ativar-acessibilidade>Sim, ativar acessibilidade</button>
            <button type="button" data-recusar-acessibilidade>Agora não</button>
        </div>
    </section>
    <?php
}

function plataforma_mensagem_erro(string $mensagem): void
{
    echo '<p role="alert">' . plataforma_escapar($mensagem) . '</p>';
}
