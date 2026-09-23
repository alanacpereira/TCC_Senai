<?php

class Jogo
{
    private $conn;
    private $table = "jogos";

    /*
     * Recebe a conexão com o banco de dados.
     */
    public function __construct($db)
    {
        $this->conn = $db;
    }


    /*
     * Busca um jogo pelo ID.
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT
                    id,
                    nome,
                    descricao,
                    ativo
                FROM " . $this->table . "
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":id",
            $id,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /*
     * Busca todas as categorias pertencentes a um jogo.
     *
     * Exemplo:
     * Jogo: Caça ao Erro
     *
     * Categorias:
     * - Uso do Ç
     * - S e SS
     * - X e CH
     */
    public function buscarCategorias($jogoId)
    {
        $sql = "SELECT
                    id,
                    jogo_id,
                    nome,
                    descricao
                FROM categorias
                WHERE jogo_id = :jogo_id
                ORDER BY id ASC";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":jogo_id",
            $jogoId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * Busca os desafios de uma categoria.
     *
     * O campo ordem determina a sequência
     * em que os desafios aparecem.
     */
    public function buscarDesafios($categoriaId)
    {
        $sql = "SELECT
                    id,
                    categoria_id,
                    nome,
                    descricao,
                    nivel,
                    ordem,
                    ativo
                FROM desafios
                WHERE categoria_id = :categoria_id
                AND ativo = TRUE
                ORDER BY ordem ASC";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":categoria_id",
            $categoriaId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * Busca um desafio específico.
     *
     * Isso será útil quando o jogador
     * escolher uma fase.
     */
    public function buscarDesafioPorId($id)
    {
        $sql = "SELECT
                    id,
                    categoria_id,
                    nome,
                    descricao,
                    nivel,
                    ordem,
                    ativo
                FROM desafios
                WHERE id = :id
                AND ativo = TRUE";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":id",
            $id,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /*
     * Busca as perguntas de um desafio.
     *
     * No nosso jogo, cada desafio terá
     * 5 perguntas.
     */
    public function buscarPerguntas($desafioId)
    {
        $sql = "SELECT
                    id,
                    desafio_id,
                    enunciado,
                    explicacao,
                    ordem
                FROM perguntas
                WHERE desafio_id = :desafio_id
                ORDER BY ordem ASC
                LIMIT 5";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":desafio_id",
            $desafioId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * Busca uma pergunta específica.
     */
    public function buscarPerguntaPorId($id)
    {
        $sql = "SELECT
                    id,
                    desafio_id,
                    enunciado,
                    explicacao,
                    ordem
                FROM perguntas
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":id",
            $id,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /*
     * Busca todas as alternativas de uma pergunta.
     *
     * IMPORTANTE:
     * Não precisamos esconder a alternativa correta
     * aqui. O PHP que entrega as perguntas ao jogador
     * poderá controlar quais informações serão enviadas.
     */
    public function buscarAlternativas($perguntaId)
    {
        $sql = "SELECT
                    id,
                    pergunta_id,
                    texto,
                    correta
                FROM alternativas
                WHERE pergunta_id = :pergunta_id
                ORDER BY id ASC";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":pergunta_id",
            $perguntaId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * Busca somente a alternativa correta.
     *
     * Será usada pelo sistema quando o jogador
     * responder uma pergunta.
     */
    public function buscarAlternativaCorreta($perguntaId)
    {
        $sql = "SELECT
                    id,
                    texto
                FROM alternativas
                WHERE pergunta_id = :pergunta_id
                AND correta = TRUE
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":pergunta_id",
            $perguntaId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}