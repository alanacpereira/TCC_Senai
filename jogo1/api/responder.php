<?php

/*
 * Carrega o sistema de autenticação.
 */
require_once __DIR__ . "/../../plataforma/config/app.php";
require_once __DIR__ . "/../../plataforma/includes/services.php";
plataforma_sessao();

header(
    "Content-Type: application/json; charset=UTF-8"
);


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../controllers/JogoController.php";


try {

    /*
     * Aceitamos somente POST.
     */
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Método não permitido."
        ]);

        exit;
    }


    /*
     * Recebe o ID da tentativa.
     */
    $tentativaId = filter_input(
        INPUT_POST,
        "tentativa_id",
        FILTER_VALIDATE_INT
    );


    /*
     * Recebe o ID da pergunta.
     */
    $perguntaId = filter_input(
        INPUT_POST,
        "pergunta_id",
        FILTER_VALIDATE_INT
    );


    /*
     * Recebe o ID da alternativa escolhida.
     */
    $alternativaId = filter_input(
        INPUT_POST,
        "alternativa_id",
        FILTER_VALIDATE_INT
    );


    /*
     * Verifica se todos os dados foram enviados.
     */
    if (
        !$tentativaId ||
        !$perguntaId ||
        !$alternativaId
    ) {

        http_response_code(400);
        echo json_encode([
            "sucesso" => false,
            "mensagem" =>
                "Dados da resposta incompletos."
        ]);

        exit;
    }


    /*
     * Conecta ao banco.
     */
    $database = new Database();
    $db = $database->conectar();
    $usuarioId = plataforma_exigir_aluno_api($db);


    /*
     * Cria o controller.
     */
    $controller =
        new JogoController($db);


    /*
     * Registra a resposta.
     *
     * O controller verifica:
     * - se a tentativa pertence ao usuário;
     * - se a pergunta pertence ao desafio;
     * - se a alternativa pertence à pergunta;
     * - se a resposta já foi enviada;
     * - se a alternativa está correta;
     * - pontuação;
     * - vidas;
     * - término da partida.
     */
    $resultado =
        $controller->responder(
            $tentativaId,
            $usuarioId,
            $perguntaId,
            $alternativaId
        );


    /*
     * Envia o resultado para o JavaScript.
     */
    echo json_encode([
        "sucesso" => true,
        "dados" => $resultado

    ]);


} catch (Exception $e) {

    http_response_code(400);
    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            $e->getMessage()

    ]);
}