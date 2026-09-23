<?php

require_once __DIR__ . "/../models/Jogo.php";
require_once __DIR__ . "/../models/Usuario.php";


class JogoController
{
    private $conn;
    private $jogo;
    private $usuario;


    /*
     * Recebe a conexão com o banco
     * e cria os objetos que vamos utilizar.
     */
    public function __construct($db)
    {
        $this->conn = $db;

        $this->jogo = new Jogo($db);
        $this->usuario = new Usuario($db);
    }


    /*
     * Inicia uma nova tentativa.
     *
     * Uma tentativa representa uma partida
     * de um jogador em um determinado desafio.
     */
    public function iniciarTentativa($usuarioId, $desafioId)
    {
        // Verifica se o usuário realmente existe.
        $dadosUsuario = $this->usuario->buscarPorId($usuarioId);

        if (!$dadosUsuario) {
            throw new Exception("Usuário não encontrado.");
        }


        // Verifica se o desafio existe e está ativo.
        $desafio = $this->jogo->buscarDesafioPorId($desafioId);

        if (!$desafio) {
            throw new Exception("Desafio não encontrado.");
        }


        // Busca as perguntas desse desafio.
        $perguntas = $this->jogo->buscarPerguntas($desafioId);

        /*
         * O nosso desafio precisa ter 5 perguntas.
         */
        if (count($perguntas) < 5) {
            throw new Exception(
                "Este desafio ainda não possui 5 perguntas cadastradas."
            );
        }


        /*
         * Cria uma nova tentativa no banco.
         *
         * Começamos com:
         * 0 pontos
         * 3 vidas
         * não concluído
         */
        $sql = "INSERT INTO tentativas
                    (
                        usuario_id,
                        desafio_id,
                        pontuacao,
                        vidas_restantes,
                        concluido
                    )
                VALUES
                    (
                        :usuario_id,
                        :desafio_id,
                        0,
                        3,
                        FALSE
                    )";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":usuario_id",
            $usuarioId,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":desafio_id",
            $desafioId,
            PDO::PARAM_INT
        );

        $stmt->execute();


        /*
         * Retorna o ID da tentativa criada.
         *
         * Esse ID será usado para registrar
         * todas as respostas dessa partida.
         */
        return $this->conn->lastInsertId();
    }


    /*
     * Busca as informações de uma tentativa.
     */
    public function buscarTentativa($tentativaId, $usuarioId)
    {
        $sql = "SELECT
                    t.id,
                    t.usuario_id,
                    t.desafio_id,
                    t.pontuacao,
                    t.vidas_restantes,
                    t.concluido,
                    t.data_inicio,
                    t.data_fim,

                    d.nome AS desafio_nome,
                    d.nivel AS desafio_nivel

                FROM tentativas t

                INNER JOIN desafios d
                    ON t.desafio_id = d.id

                WHERE t.id = :tentativa_id
                AND t.usuario_id = :usuario_id";


        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":tentativa_id",
            $tentativaId,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":usuario_id",
            $usuarioId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /*
     * Busca uma pergunta e suas alternativas.
     *
     * A pergunta será localizada pelo número
     * da pergunta dentro da tentativa.
     */
    public function buscarPerguntaDaTentativa(
        $tentativaId,
        $usuarioId,
        $numeroPergunta
    ) {
        // Primeiro buscamos a tentativa.
        $tentativa = $this->buscarTentativa(
            $tentativaId,
            $usuarioId
        );

        if (!$tentativa) {
            throw new Exception("Tentativa não encontrada.");
        }


        // Não podemos continuar uma partida finalizada.
        if ($tentativa["concluido"]) {
            throw new Exception("Esta tentativa já foi concluída.");
        }


        /*
         * O número da pergunta começa em 1.
         *
         * Exemplo:
         * número 1 → índice 0
         * número 2 → índice 1
         */
        $indice = $numeroPergunta - 1;


        if ($indice < 0 || $indice >= 5) {
            throw new Exception("Número de pergunta inválido.");
        }


        /*
         * Busca as 5 perguntas do desafio.
         */
        $perguntas = $this->jogo->buscarPerguntas(
            $tentativa["desafio_id"]
        );


        if (!isset($perguntas[$indice])) {
            throw new Exception("Pergunta não encontrada.");
        }


        $pergunta = $perguntas[$indice];


        /*
         * Busca as alternativas dessa pergunta.
         */
        $alternativas = $this->jogo->buscarAlternativas(
            $pergunta["id"]
        );


        /*
         * Segurança:
         *
         * Não enviamos para o jogador qual alternativa
         * é a correta antes que ele responda.
         */
        foreach ($alternativas as &$alternativa) {
            unset($alternativa["correta"]);
        }

        unset($alternativa);


        return [
            "pergunta" => $pergunta,
            "alternativas" => $alternativas,
            "numero" => $numeroPergunta,
            "total" => 5,
            "vidas" => (int) $tentativa["vidas_restantes"],
            "pontuacao" => (int) $tentativa["pontuacao"]
        ];
    }


    /*
     * Registra a resposta do jogador.
     */
    public function responder(
        $tentativaId,
        $usuarioId,
        $perguntaId,
        $alternativaId
    ) {
        /*
         * Confirma que a tentativa pertence
         * ao jogador informado.
         */
        $tentativa = $this->buscarTentativa(
            $tentativaId,
            $usuarioId
        );


        if (!$tentativa) {
            throw new Exception("Tentativa não encontrada.");
        }


        if ($tentativa["concluido"]) {
            throw new Exception("Esta tentativa já foi concluída.");
        }


        /*
         * Verifica se a pergunta realmente existe.
         */
        $pergunta = $this->jogo->buscarPerguntaPorId(
            $perguntaId
        );


        if (!$pergunta) {
            throw new Exception("Pergunta não encontrada.");
        }


        /*
         * Confirma que a pergunta pertence
         * ao desafio da tentativa.
         */
        if (
            (int) $pergunta["desafio_id"] !==
            (int) $tentativa["desafio_id"]
        ) {
            throw new Exception(
                "Esta pergunta não pertence a este desafio."
            );
        }


        /*
         * Verifica se essa pergunta já foi respondida
         * nessa tentativa.
         *
         * Isso evita que o jogador envie a mesma
         * resposta várias vezes.
         */
        $sql = "SELECT id
                FROM respostas
                WHERE tentativa_id = :tentativa_id
                AND pergunta_id = :pergunta_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":tentativa_id",
            $tentativaId,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":pergunta_id",
            $perguntaId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        if ($stmt->fetch()) {
            throw new Exception(
                "Esta pergunta já foi respondida."
            );
        }


        /*
         * Busca a alternativa escolhida.
         */
        $sql = "SELECT
                    id,
                    pergunta_id,
                    texto,
                    correta
                FROM alternativas
                WHERE id = :alternativa_id
                AND pergunta_id = :pergunta_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":alternativa_id",
            $alternativaId,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":pergunta_id",
            $perguntaId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $alternativaEscolhida = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$alternativaEscolhida) {
            throw new Exception(
                "Alternativa inválida."
            );
        }


        /*
         * Verifica se a resposta está correta.
         */
        $correta = (bool) $alternativaEscolhida["correta"];


        /*
         * Regra de pontuação:
         *
         * Acertou → +100 pontos
         * Errou   → +0 pontos
         */
        $pontos = $correta ? 100 : 0;


        /*
         * Se errar, perde uma vida.
         *
         * Se acertar, continua com a mesma quantidade
         * de vidas.
         */
        $vidasAtuais = (int) $tentativa["vidas_restantes"];

        $vidasNovas = $correta
            ? $vidasAtuais
            : max(0, $vidasAtuais - 1);


        /*
         * Registra a resposta no banco.
         */
        $sql = "INSERT INTO respostas
                    (
                        tentativa_id,
                        pergunta_id,
                        alternativa_id,
                        correta,
                        pontos
                    )
                VALUES
                    (
                        :tentativa_id,
                        :pergunta_id,
                        :alternativa_id,
                        :correta,
                        :pontos
                    )";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":tentativa_id",
            $tentativaId,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":pergunta_id",
            $perguntaId,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":alternativa_id",
            $alternativaId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ":correta",
            $correta ? 1 : 0,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":pontos",
            $pontos,
            PDO::PARAM_INT
        );

        $stmt->execute();


        /*
         * Atualiza a pontuação e as vidas da tentativa.
         */
        $pontuacaoNova =
            (int) $tentativa["pontuacao"] + $pontos;


        /*
         * Descobre quantas perguntas já foram respondidas.
         */
        $sql = "SELECT COUNT(*) AS total
                FROM respostas
                WHERE tentativa_id = :tentativa_id";

        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":tentativa_id",
            $tentativaId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalRespondidas = (int) $resultado["total"];


        /*
         * A partida termina quando:
         *
         * 1. As 5 perguntas foram respondidas
         * OU
         * 2. O jogador ficou sem vidas.
         */
        $finalizou =
            $totalRespondidas >= 5 ||
            $vidasNovas <= 0;


        /*
         * O bônus de conclusão é de +200 pontos.
         *
         * Ele só é dado quando o jogador
         * respondeu as 5 perguntas e ainda
         * não ficou sem vidas.
         */
        $bonusConclusao = 0;

        if (
            $totalRespondidas >= 5 &&
            $vidasNovas > 0
        ) {
            $bonusConclusao = 200;

            $pontuacaoNova += $bonusConclusao;
        }


        /*
         * Atualiza a tentativa.
         */
        if ($finalizou) {

            $sql = "UPDATE tentativas
                    SET
                        pontuacao = :pontuacao,
                        vidas_restantes = :vidas,
                        concluido = TRUE,
                        data_fim = NOW()
                    WHERE id = :tentativa_id";

        } else {

            $sql = "UPDATE tentativas
                    SET
                        pontuacao = :pontuacao,
                        vidas_restantes = :vidas
                    WHERE id = :tentativa_id";
        }


        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(
            ":pontuacao",
            $pontuacaoNova,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":vidas",
            $vidasNovas,
            PDO::PARAM_INT
        );

        $stmt->bindParam(
            ":tentativa_id",
            $tentativaId,
            PDO::PARAM_INT
        );

        $stmt->execute();


        /*
         * Se o jogador terminou o desafio,
         * adicionamos experiência.
         *
         * Aqui usamos a mesma pontuação como experiência.
         */
        if ($finalizou && $vidasNovas > 0) {

            $this->usuario->adicionarExperiencia(
                $usuarioId,
                $pontuacaoNova
            );
        }


        /*
         * Busca a alternativa correta para podermos
         * mostrar a explicação ao jogador quando
         * ele errar.
         */
        $alternativaCorreta =
            $this->jogo->buscarAlternativaCorreta(
                $perguntaId
            );


        /*
         * Retorna todas as informações necessárias
         * para o JavaScript mostrar o resultado.
         */
        return [
            "correta" => $correta,

            "alternativa_escolhida" => [
                "id" => (int) $alternativaEscolhida["id"],
                "texto" => $alternativaEscolhida["texto"]
            ],

            "alternativa_correta" => $alternativaCorreta,

            "explicacao" => $pergunta["explicacao"],

            "pontos_ganhos" => $pontos,

            "bonus_conclusao" => $bonusConclusao,

            "pontuacao" => $pontuacaoNova,

            "vidas" => $vidasNovas,

            "pergunta_atual" => $totalRespondidas,

            "total_perguntas" => 5,

            "finalizou" => $finalizou,

            "venceu" =>
                $finalizou &&
                $vidasNovas > 0
        ];
    }
}