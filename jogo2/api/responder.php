<?php

require_once __DIR__ . "/../config/bootstrap.php";

try {
    jogo2ConfigurarApi("POST");
    $tentativaId = jogo2IdRecebido($_POST, "tentativa_id");
    $perguntaId = jogo2IdRecebido($_POST, "pergunta_id");
    $alternativaId = jogo2IdRecebido($_POST, "alternativa_id");
    $controller = new Jogo2Controller(jogo2Conexao());
    jogo2Json([
        "sucesso" => true,
        "dados" => $controller->responder(
            $tentativaId,
            jogo2UsuarioId(),
            $perguntaId,
            $alternativaId
        )
    ]);
} catch (Throwable $erro) {
    jogo2TratarErro($erro);
}
