<?php

/*
 * Carrega o sistema de autenticação.
 */
require_once __DIR__ . "/config/Auth.php";

Auth::iniciarSessao();


require_once __DIR__ . "/config/Database.php";
require_once __DIR__ . "/controllers/JogoController.php";


/*
 * Recebe o ID da tentativa pela URL.
 *
 * Exemplo:
 *
 * resultado.php?tentativa_id=10
 */
$tentativaId = filter_input(
    INPUT_GET,
    "tentativa_id",
    FILTER_VALIDATE_INT
);


if (!$tentativaId) {

    die("Tentativa inválida.");
}


/*
 * ------------------------------------------------------------
 * USUÁRIO TEMPORÁRIO
 * ------------------------------------------------------------
 */
$usuarioId = Auth::usuarioId();

if (!$usuarioId) {
    $usuarioId = 1;
}


try {

    /*
     * Conecta ao banco.
     */
    $database = new Database();

    $db = $database->conectar();


    /*
     * Cria o controller.
     */
    $controller =
        new JogoController($db);


    /*
     * Busca a tentativa.
     *
     * O controller também garante que
     * a tentativa pertence ao usuário.
     */
    $tentativa =
        $controller->buscarTentativa(
            $tentativaId,
            $usuarioId
        );


    /*
     * Verifica se encontramos a tentativa.
     */
    if (!$tentativa) {

        die(
            "Tentativa não encontrada."
        );
    }


    /*
     * Busca as estatísticas das respostas.
     */
    $sql = "SELECT
                COUNT(*) AS total_respostas,

                SUM(
                    CASE
                        WHEN correta = 1
                        THEN 1
                        ELSE 0
                    END
                ) AS acertos,

                SUM(
                    CASE
                        WHEN correta = 0
                        THEN 1
                        ELSE 0
                    END
                ) AS erros

            FROM respostas

            WHERE tentativa_id = :tentativa_id";


    $stmt = $db->prepare($sql);


    $stmt->bindParam(
        ":tentativa_id",
        $tentativaId,
        PDO::PARAM_INT
    );


    $stmt->execute();


    $estatisticas =
        $stmt->fetch(PDO::FETCH_ASSOC);


    /*
     * Converte os valores do banco para números.
     */
    $totalRespostas =
        (int) $estatisticas["total_respostas"];


    $acertos =
        (int) $estatisticas["acertos"];


    $erros =
        (int) $estatisticas["erros"];


    /*
     * Calcula a porcentagem de acertos.
     */
    $porcentagem = 0;


    if ($totalRespostas > 0) {

        $porcentagem =
            round(
                ($acertos / $totalRespostas) * 100
            );
    }


    /*
     * O jogador venceu se:
     *
     * 1. a tentativa foi concluída;
     * 2. ainda possui pelo menos uma vida.
     */
    $venceu =
        (bool) $tentativa["concluido"] &&
        (int) $tentativa["vidas_restantes"] > 0;

}

catch (Exception $e) {

    die(
        "Erro ao carregar o resultado: " .
        htmlspecialchars(
            $e->getMessage()
        )
    );
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Resultado - Caça ao Erro
    </title>

</head>

<body>

    <main>

        <section>

            <?php if ($venceu): ?>

                <h1>
                    🎉 Parabéns!
                </h1>

                <h2>
                    Desafio concluído!
                </h2>

                <p>
                    Você conseguiu completar
                    o desafio.
                </p>

            <?php else: ?>

                <h1>
                    Fim da partida
                </h1>

                <h2>
                    Continue praticando!
                </h2>

                <p>
                    Você ficou sem vidas antes
                    de concluir o desafio.
                </p>

            <?php endif; ?>


            <hr>


            <h2>
                Resultado
            </h2>


            <p>

                <strong>
                    Desafio:
                </strong>

                <?= htmlspecialchars(
                    $tentativa["desafio_nome"]
                ) ?>

            </p>


            <p>

                <strong>
                    Nível:
                </strong>

                <?= (int) $tentativa["desafio_nivel"] ?>

            </p>


            <p>

                <strong>
                    Pontuação:
                </strong>

                <?= (int) $tentativa["pontuacao"] ?>

                pontos

            </p>


            <p>

                <strong>
                    Vidas restantes:
                </strong>

                <?= (int) $tentativa["vidas_restantes"] ?>

            </p>


            <p>

                <strong>
                    Perguntas respondidas:
                </strong>

                <?= $totalRespostas ?>

                de 5

            </p>


            <p>

                <strong>
                    Acertos:
                </strong>

                <?= $acertos ?>

            </p>


            <p>

                <strong>
                    Erros:
                </strong>

                <?= $erros ?>

            </p>


            <p>

                <strong>
                    Aproveitamento:
                </strong>

                <?= $porcentagem ?>%

            </p>


            <hr>


            <?php if ($venceu): ?>

                <p>
                    🧭 Você avançou mais uma etapa
                    na sua jornada!
                </p>

            <?php else: ?>

                <p>
                    📚 Revise as explicações e
                    tente novamente.
                </p>

            <?php endif; ?>


            <hr>


            <a
                href="index.php?desafio=<?= (int) $tentativa["desafio_id"] ?>"
            >
                Jogar novamente
            </a>


            <br>
            <br>


            <a href="index.php">
                Voltar para o início
            </a>


            <br>
            <br>


            <a href="logout.php">
                Sair
            </a>

        </section>

    </main>

</body>

</html>