<?php

class Usuario
{
    // Conexão com o banco
    private $conn;

    // Nome da tabela
    private $table = "usuarios";

    // Recebe a conexão criada pelo Database.php
    public function __construct($db)
    {
        $this->conn = $db;
    }

    // --------------------------------------------------
    // BUSCAR USUÁRIO PELO ID
    // --------------------------------------------------

    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    id,
                    nome,
                    email,
                    nivel,
                    experiencia,
                    data_cadastro
                FROM " . $this->table . "
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --------------------------------------------------
    // BUSCAR USUÁRIO PELO E-MAIL
    // --------------------------------------------------

    public function buscarPorEmail($email)
    {
        $sql = "SELECT *
                FROM " . $this->table . "
                WHERE email = :email";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":email", $email);

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --------------------------------------------------
    // CRIAR USUÁRIO
    // --------------------------------------------------

    public function criar($nome, $email, $senha)
    {
        // Transforma a senha em um hash seguro
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "INSERT INTO " . $this->table . "
                (nome, email, senha)
                VALUES
                (:nome, :email, :senha)";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(":nome", $nome);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":senha", $senhaHash);

        return $stmt->execute();
    }

    // --------------------------------------------------
    // ATUALIZAR EXPERIÊNCIA
    // --------------------------------------------------

    public function adicionarExperiencia($id, $experiencia)
    {
        $sql = "UPDATE " . $this->table . "
                SET experiencia = experiencia + :experiencia
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":experiencia",
            $experiencia,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":id",
            $id,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }

    // --------------------------------------------------
    // ATUALIZAR NÍVEL
    // --------------------------------------------------

    public function atualizarNivel($id, $nivel)
    {
        $sql = "UPDATE " . $this->table . "
                SET nivel = :nivel
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":nivel",
            $nivel,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":id",
            $id,
            PDO::PARAM_INT
        );

        return $stmt->execute();
    }
}