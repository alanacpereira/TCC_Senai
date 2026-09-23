<?php

class Database
{
    // Dados de acesso ao banco
    private $host = "localhost: 3306";
    private $db_name = "palavra_a_bordo1";
    private $username = "root";
    private $password = "mysql";
    

    // Guarda a conexão com o banco
    public $conn;

    // Cria a conexão
    public function conectar()
    {
        $this->conn = null;

        try {

            $this->conn = new PDO(
                "mysql:host=" . $this->host .
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

            // Interrompe o sistema caso não consiga
            // conectar ao banco
            die("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }

        return $this->conn;
    }
}