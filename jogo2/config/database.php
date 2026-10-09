<?php

class Jogo2Database
{
    public function conectar(): PDO
    {
        $host = getenv("DB_HOST") ?: "localhost";
        $port = getenv("DB_PORT") ?: "3308";
        $database = getenv("DB_NAME") ?: "palavra_a_bordo";
        $username = getenv("DB_USER") ?: "root";
        $password = getenv("DB_PASSWORD");

        try {
            return new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password === false ? "" : $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $error) {
            throw new RuntimeException(
                "Não foi possível conectar ao banco de dados.",
                0,
                $error
            );
        }
    }
}
