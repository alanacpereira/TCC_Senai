<?php

require_once __DIR__ . "/../config/bootstrap.php";

try {
    jogo2ConfigurarApi("GET");
    $controller = new Jogo2Controller(jogo2Conexao());
    jogo2Json([
        "sucesso" => true,
        "dados" => $controller->listarMissoes(jogo2UsuarioId())
    ]);
} catch (Throwable $erro) {
    jogo2TratarErro($erro);
}
