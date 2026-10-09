<?php

require_once __DIR__ . "/../config/bootstrap.php";

try {
    jogo2ConfigurarApi("GET");
    $tentativaId = jogo2IdRecebido($_GET, "tentativa_id");
    $controller = new Jogo2Controller(jogo2Conexao());
    jogo2Json([
        "sucesso" => true,
        "dados" => $controller->buscarResultado(
            $tentativaId,
            jogo2UsuarioId()
        )
    ]);
} catch (Throwable $erro) {
    jogo2TratarErro($erro);
}
