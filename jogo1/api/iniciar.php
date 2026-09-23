<?php

/*
 * Carrega o sistema de autenticação.
 */
require_once __DIR__ . "/../config/Auth.php";

Auth::iniciarSessao();

header(
    "Content-Type: application/json; charset=UTF-8"
);


require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../controllers/JogoController.php";


try {

    /*
     * Aceitamos somente requisições POST.
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
     * Recebe o ID do desafio enviado
     * pelo JavaScript.
     */
    $desafioId = filter_input(
        INPUT_POST,
        "desafio_id",
        FILTER_VALIDATE_INT
    );


    if (!$desafioId) {

        http_response_code(400);
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Desafio inválido."
        ]);

        exit;
    }


    /*
     * --------------------------------------------------------
     * USUÁRIO TEMPORÁRIO
     * --------------------------------------------------------
     *
     * O login ainda não foi implementado.
     *
     * Por enquanto usamos o usuário 1,
     * que já existe no banco.
     *
     * Quando o sistema de login estiver pronto,
     * esta linha será substituída por:
     *
     * $usuarioId = Auth::usuarioId();
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
     * Cria o controller do jogo.
     */
    $controller =
        new JogoController($db);


    /*
     * Cria uma nova tentativa.
     */
    $tentativaId =
        $controller->iniciarTentativa(
            $usuarioId,
            $desafioId
        );


    /*
     * Retorna os dados para o JavaScript.
     */
    echo json_encode([
        "sucesso" => true,
        "mensagem" =>
            "Desafio iniciado com sucesso.",
        "tentativa_id" =>
            (int) $tentativaId,
        "desafio_id" =>
            (int) $desafioId,
        "vidas" => 3,
        "pontuacao" => 0

    ]);


} catch (Exception $e) {

    http_response_code(400);
    echo json_encode([
        "sucesso" => false,
        "mensagem" =>
            $e->getMessage()

    ]);
}