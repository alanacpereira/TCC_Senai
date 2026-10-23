<?php

class Database
{
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;

    // Guarda a conexão com o banco
    public $conn;

    public function __construct()
    {
        // Usa variaveis do servidor e mantem os padroes locais do Laragon.
        $this->host = getenv("DB_HOST") ?: "localhost";
        $this->port = getenv("DB_PORT") ?: "3308";
        $this->db_name = getenv("DB_NAME") ?: "palavra_a_bordo";
        $this->username = getenv("DB_USER") ?: "root";
        $password = getenv("DB_PASSWORD");
        $this->password = $password === false ? "" : $password;
    }

    // Cria a conexão
    public function conectar()
    {
        $this->conn = null;

        try {

            $this->conn = new PDO(
                "mysql:host=" . $this->host .
                ";port=" . $this->port .
                ";dbname=" . $this->db_name .
                ";charset=utf8mb4",

                $this->username,
                $this->password
            );

            // Faz o PHP mostrar erros do banco
            // caso alguma consulta tenha problema
            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            // Faz os resultados do banco serem retornados
            // como arrays associativos por padrão
            $this->conn->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

        } catch (PDOException $e) {

            error_log("[Jogo 1] Falha ao conectar ao banco: " . $e->getMessage());
            throw new RuntimeException(
                "Não foi possível conectar ao banco de dados.",
                0,
                $e
            );
        }

        return $this->conn;
    }
}