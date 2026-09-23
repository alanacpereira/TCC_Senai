<?php

/*
 * Carrega o sistema de autenticação.
 */
require_once __DIR__ . "/config/Auth.php";


/*
 * Inicia a sessão caso ainda não esteja iniciada.
 */
Auth::iniciarSessao();


/*
 * Encerra a sessão do usuário.
 */
Auth::logout();


/*
 * Depois de sair da conta,
 * volta para a página inicial.
 */
header("Location: index.php");

exit;