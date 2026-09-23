<?php

/*
 * Carrega o sistema de autenticação.
 */
require_once __DIR__ . "/../config/Auth.php";
Auth::iniciarSessao();


header(
    "Content-Type: application/json; charset=UTF-8"
);


require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../controllers/JogoController.php";


try {

    /*
     * Esta API utiliza GET.
     */
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
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
        INPUT_GET,
        "tentativa_id",
        FILTER_VALIDATE_INT
    );


    /*
     * Recebe o número da pergunta.
     */
    $numeroPergunta = filter_input(
        INPUT_GET,
        "numero",
        FILTER_VALIDATE_INT
    );


    /*
     * Verifica se os dados existem.
     */
    if (
        !$tentativaId ||
        !$numeroPergunta
    ) {

        http_response_code(400);

        echo json_encode([
            "sucesso" => false,
            "mensagem" =>
                "Tentativa ou pergunta inválida."
        ]);

        exit;
    }


    /*
     * --------------------------------------------------------
     * USUÁRIO TEMPORÁRIO
     * --------------------------------------------------------
     */
    $usuarioId = Auth::usuarioId();

    if (!$usuarioId) {
        $usuarioId = 1;
    }


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
     * Busca a pergunta da tentativa.
     */
    $dados =
        $controller->buscarPerguntaDaTentativa(
            $tentativaId,
            $usuarioId,
            $numeroPergunta
        );


    /*
     * Envia os dados para o JavaScript.
     */
    echo json_encode([
        "sucesso" => true,
        "dados" => $dados

    ]);


} catch (Exception $e) {

    http_response_code(400);
    echo json_encode([

        "sucesso" => false,
        "mensagem" =>
            $e->getMessage()

    ]);
}