<?php

class Auth
{
    /*
     * Inicia a sessão somente se ela ainda
     * não estiver ativa.
     */
    public static function iniciarSessao()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }


    /*
     * Verifica se existe um usuário logado.
     */
    public static function estaLogado()
    {
        self::iniciarSessao();
        return isset($_SESSION["usuario_id"]);
    }


    /*
     * Retorna o ID do usuário logado.
     */
    public static function usuarioId()
    {
        self::iniciarSessao();

        if (!isset($_SESSION["usuario_id"])) {
            return null;
        }

        return (int) $_SESSION["usuario_id"];
    }


    /*
     * Guarda os dados principais do usuário
     * na sessão após o login.
     */
    public static function login($usuario)
    {
        self::iniciarSessao();

        $_SESSION["usuario_id"] =
            (int) $usuario["id"];

        $_SESSION["usuario_nome"] =
            $usuario["nome"];

        $_SESSION["usuario_email"] =
            $usuario["email"];
    }


    /*
     * Remove todos os dados da sessão
     * do usuário.
     */
    public static function logout()
    {
        self::iniciarSessao();

        /*
         * Remove as variáveis da sessão.
         */
        $_SESSION = [];


        /*
         * Se a sessão utiliza cookies,
         * também removemos o cookie.
         */
        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }


        /*
         * Destrói a sessão.
         */
        session_destroy();
    }
}