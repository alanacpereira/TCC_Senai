<?php

require_once __DIR__ . "/../config/bootstrap.php";

try {
    jogo2ConfigurarApi("POST");
    $desafioId = jogo2IdRecebido($_POST, "desafio_id");
    $tentativaAnteriorId = isset($_POST["tentativa_id"])
        ? jogo2IdRecebido($_POST, "tentativa_id")
        : null;
    $controller = new Jogo2Controller(jogo2Conexao());
    jogo2Json([
        "sucesso" => true,
        "dados" => $controller->iniciarTentativa(
            jogo2UsuarioId(),
            $desafioId,
            $tentativaAnteriorId
        )
    ]);
} catch (Throwable $erro) {
    jogo2TratarErro($erro);
}
