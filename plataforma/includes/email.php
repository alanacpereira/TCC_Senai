<?php

declare(strict_types=1);

function plataforma_smtp_resposta($socket): array
{
    $linhas = [];
    do {
        $linha = fgets($socket, 2048);
        if ($linha === false) {
            throw new RuntimeException("A conexão SMTP foi encerrada antes da resposta.");
        }
        $linhas[] = rtrim($linha, "\r\n");
        $metadados = stream_get_meta_data($socket);
        if (!empty($metadados["timed_out"])) {
            throw new RuntimeException("Tempo esgotado aguardando resposta do servidor SMTP.");
        }
    } while (strlen(end($linhas)) >= 4 && end($linhas)[3] === "-");

    $codigo = (int) substr($linhas[0], 0, 3);

    return [$codigo, implode("\n", $linhas)];
}

function plataforma_smtp_exigir_resposta($socket, array $codigosEsperados): string
{
    [$codigo, $resposta] = plataforma_smtp_resposta($socket);
    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException("O servidor SMTP recusou a operação (código {$codigo}).");
    }

    return $resposta;
}

function plataforma_smtp_escrever($socket, string $dados): void
{
    $totalEscrito = 0;
    $tamanho = strlen($dados);

    while ($totalEscrito < $tamanho) {
        $escrito = fwrite($socket, substr($dados, $totalEscrito));
        if ($escrito === false || $escrito === 0) {
            throw new RuntimeException("Não foi possível enviar dados ao servidor SMTP.");
        }
        $totalEscrito += $escrito;
    }
}

function plataforma_smtp_comando($socket, string $comando, array $codigosEsperados): string
{
    plataforma_smtp_escrever($socket, $comando . "\r\n");
    return plataforma_smtp_exigir_resposta($socket, $codigosEsperados);
}

function plataforma_enviar_email(string $destinatario, string $assunto, string $mensagem): void
{
    $host = getenv("PLATAFORMA_SMTP_HOST") ?: "localhost";
    $porta = getenv("PLATAFORMA_SMTP_PORT") ?: "1025";
    $seguranca = strtolower(getenv("PLATAFORMA_SMTP_SECURITY") ?: "none");
    $usuario = getenv("PLATAFORMA_SMTP_USER") ?: "";
    $senha = getenv("PLATAFORMA_SMTP_PASSWORD") ?: "";
    $remetente = getenv("PLATAFORMA_EMAIL_FROM") ?: "no-reply@palavra-a-bordo.local";
    $nomeRemetente = getenv("PLATAFORMA_EMAIL_FROM_NAME") ?: "Palavra a Bordo";

    if (
        strlen($host) > 253 ||
        !preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?|localhost)$/', $host) ||
        filter_var($porta, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]]) === false ||
        !in_array($seguranca, ["none", "starttls", "ssl"], true) ||
        !filter_var($destinatario, FILTER_VALIDATE_EMAIL) ||
        !filter_var($remetente, FILTER_VALIDATE_EMAIL) ||
        str_contains($nomeRemetente, "\r") ||
        str_contains($nomeRemetente, "\n") ||
        str_contains($assunto, "\r") ||
        str_contains($assunto, "\n")
    ) {
        throw new RuntimeException("A configuração de e-mail da plataforma é inválida.");
    }
    if (($usuario === "") !== ($senha === "")) {
        throw new RuntimeException("Configure usuário e senha SMTP juntos ou deixe ambos vazios.");
    }
    if ($usuario !== "" && $seguranca === "none") {
        throw new RuntimeException("Autenticação SMTP exige STARTTLS ou SSL.");
    }

    $contexto = stream_context_create([
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
            "peer_name" => $host,
            "allow_self_signed" => false
        ]
    ]);
    $endereco = ($seguranca === "ssl" ? "ssl://" : "tcp://") . $host . ":" . (int) $porta;
    set_error_handler(
        static function (int $nivel, string $mensagem): never {
            throw new RuntimeException($mensagem);
        }
    );
    $socket = null;

    try {
        $socket = stream_socket_client(
            $endereco,
            $codigoErro,
            $mensagemErro,
            8,
            STREAM_CLIENT_CONNECT,
            $contexto
        );
        if ($socket === false) {
            throw new RuntimeException(
                "Não foi possível conectar ao servidor SMTP ({$codigoErro})."
            );
        }
        stream_set_timeout($socket, 8);
        plataforma_smtp_exigir_resposta($socket, [220]);
        plataforma_smtp_comando($socket, "EHLO localhost", [250]);

        if ($seguranca === "starttls") {
            plataforma_smtp_comando($socket, "STARTTLS", [220]);
            $metodosTls = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined("STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT")) {
                $metodosTls |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($socket, true, $metodosTls)) {
                throw new RuntimeException("Não foi possível estabelecer uma conexão SMTP segura.");
            }
            plataforma_smtp_comando($socket, "EHLO localhost", [250]);
        }

        if ($usuario !== "") {
            plataforma_smtp_comando($socket, "AUTH LOGIN", [334]);
            plataforma_smtp_comando($socket, base64_encode($usuario), [334]);
            plataforma_smtp_comando($socket, base64_encode($senha), [235]);
        }

        plataforma_smtp_comando($socket, "MAIL FROM:<{$remetente}>", [250]);
        plataforma_smtp_comando($socket, "RCPT TO:<{$destinatario}>", [250, 251]);
        plataforma_smtp_comando($socket, "DATA", [354]);

        $dominioMensagem = substr(strrchr($remetente, "@"), 1);
        $cabecalhoRemetente = "=?UTF-8?B?" . base64_encode($nomeRemetente) . "?=";
        $cabecalhoAssunto = "=?UTF-8?B?" . base64_encode($assunto) . "?=";
        $corpo = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $mensagem)), 76, "\r\n");
        $conteudo = implode("\r\n", [
            "Date: " . gmdate(DATE_RFC2822),
            "From: {$cabecalhoRemetente} <{$remetente}>",
            "To: <{$destinatario}>",
            "Subject: {$cabecalhoAssunto}",
            "Message-ID: <" . bin2hex(random_bytes(16)) . "@{$dominioMensagem}>",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            $corpo
        ]);
        $conteudo = preg_replace('/(^|\r\n)\./', '$1..', $conteudo) ?? $conteudo;
        plataforma_smtp_escrever($socket, $conteudo . "\r\n.\r\n");
        plataforma_smtp_exigir_resposta($socket, [250]);
        plataforma_smtp_comando($socket, "QUIT", [221]);
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
        restore_error_handler();
    }
}
