<?php

class Jogo2Controller
{
    private const NOME_JOGO = "Expedição dos Sete Mares";
    private const TOTAL_PERGUNTAS = 5;
    private const TOTAL_ALTERNATIVAS = 4;
    private const VIDAS_INICIAIS = 3;
    private const PONTOS_POR_ACERTO = 100;
    private const BONUS_CONCLUSAO = 200;

    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function listarMissoes(int $usuarioId): array
    {
        $sql = "SELECT
                    d.id,
                    c.nome,
                    COALESCE(d.descricao, c.descricao, '') AS descricao,
                    d.nivel,
                    d.ordem,
                    COUNT(DISTINCT p.id) AS total_perguntas
                FROM jogos j
                INNER JOIN categorias c ON c.jogo_id = j.id
                INNER JOIN desafios d ON d.categoria_id = c.id
                LEFT JOIN perguntas p ON p.desafio_id = d.id
                WHERE j.nome = :nome_jogo
                    AND j.ativo = 1
                    AND d.ativo = 1
                GROUP BY d.id, c.nome, d.descricao, c.descricao, d.nivel, d.ordem
                ORDER BY d.ordem ASC, d.id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(["nome_jogo" => self::NOME_JOGO]);
        $missoes = $stmt->fetchAll();

        $sql = "SELECT
                    t.id,
                    t.desafio_id,
                    t.concluido,
                    t.vidas_restantes,
                    t.pontuacao,
                    COUNT(r.id) AS respondidas,
                    SUM(CASE WHEN r.correta = 1 THEN 1 ELSE 0 END) AS acertos
                FROM tentativas t
                LEFT JOIN respostas r ON r.tentativa_id = t.id
                WHERE t.usuario_id = :usuario_id
                GROUP BY t.id
                ORDER BY t.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(["usuario_id" => $usuarioId]);

        $progresso = [];
        foreach ($stmt->fetchAll() as $tentativa) {
            $desafioId = (int) $tentativa["desafio_id"];
            $progresso[$desafioId] ??= [
                "concluida" => false,
                "pontuacao_maxima" => 0,
                "tentativa_aberta" => null
            ];

            if (
                (int) $tentativa["concluido"] === 1 &&
                (int) $tentativa["vidas_restantes"] > 0 &&
                (int) $tentativa["respondidas"] === self::TOTAL_PERGUNTAS
            ) {
                $progresso[$desafioId]["concluida"] = true;
                $progresso[$desafioId]["pontuacao_maxima"] = max(
                    $progresso[$desafioId]["pontuacao_maxima"],
                    (int) $tentativa["pontuacao"]
                );
            }

            if (
                (int) $tentativa["concluido"] === 0 &&
                $progresso[$desafioId]["tentativa_aberta"] === null
            ) {
                $progresso[$desafioId]["tentativa_aberta"] = [
                    "id" => (int) $tentativa["id"],
                    "respondidas" => (int) $tentativa["respondidas"],
                    "vidas" => (int) $tentativa["vidas_restantes"],
                    "pontuacao" => (int) $tentativa["pontuacao"]
                ];
            }
        }

        foreach ($missoes as &$missao) {
            $id = (int) $missao["id"];
            $missao["id"] = $id;
            $missao["nivel"] = (int) $missao["nivel"];
            $missao["total_perguntas"] = (int) $missao["total_perguntas"];
            $missao["disponivel"] =
                $missao["total_perguntas"] === self::TOTAL_PERGUNTAS;
            $missao["concluida"] = $progresso[$id]["concluida"] ?? false;
            $missao["pontuacao_maxima"] =
                $progresso[$id]["pontuacao_maxima"] ?? 0;
            $missao["tentativa_aberta"] =
                $progresso[$id]["tentativa_aberta"] ?? null;
        }
        unset($missao);

        $stmt = $this->conn->prepare(
            "SELECT nivel, experiencia FROM usuarios WHERE id = :usuario_id LIMIT 1"
        );
        $stmt->execute(["usuario_id" => $usuarioId]);
        $jogador = $stmt->fetch();

        if (!$jogador) {
            throw new Jogo2HttpException(
                "Não foi possível identificar o jogador desta sessão.",
                401
            );
        }

        $missoesConcluidas = count(array_filter(
            $missoes,
            static fn(array $missao): bool => $missao["concluida"]
        ));

        return [
            "missoes" => $missoes,
            "jogador" => [
                "nivel" => (int) $jogador["nivel"],
                "experiencia" => (int) $jogador["experiencia"],
                "missoes_concluidas" => $missoesConcluidas,
                "total_missoes" => count($missoes)
            ]
        ];
    }

    public function iniciarTentativa(
        int $usuarioId,
        int $desafioId,
        ?int $tentativaAnteriorId = null
    ): array {
        $this->validarUsuario($usuarioId);
        $this->buscarMissao($desafioId);
        $this->validarEstruturaMissao($desafioId);

        if ($tentativaAnteriorId !== null) {
            $tentativa = $this->buscarTentativa($tentativaAnteriorId, $usuarioId);

            if ((int) $tentativa["desafio_id"] !== $desafioId) {
                throw new Jogo2HttpException("Tentativa não pertence a esta missão.", 403);
            }

            if ((int) $tentativa["concluido"] === 1) {
                throw new Jogo2HttpException("Esta missão já foi concluída nesta tentativa.", 409);
            }

            $respondidas = $this->contarRespostas($tentativaAnteriorId);

            return [
                "tentativa_id" => $tentativaAnteriorId,
                "vidas" => (int) $tentativa["vidas_restantes"],
                "pontuacao" => (int) $tentativa["pontuacao"],
                "numero_pergunta" => $respondidas + 1
            ];
        }

        $sql = "INSERT INTO tentativas
                    (usuario_id, desafio_id, pontuacao, vidas_restantes, concluido)
                VALUES
                    (:usuario_id, :desafio_id, 0, :vidas, 0)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            "usuario_id" => $usuarioId,
            "desafio_id" => $desafioId,
            "vidas" => self::VIDAS_INICIAIS
        ]);

        return [
            "tentativa_id" => (int) $this->conn->lastInsertId(),
            "vidas" => self::VIDAS_INICIAIS,
            "pontuacao" => 0,
            "numero_pergunta" => 1
        ];
    }

    public function buscarPergunta(
        int $tentativaId,
        int $usuarioId,
        int $numero
    ): array {
        $tentativa = $this->buscarTentativa($tentativaId, $usuarioId);
        $this->validarTentativaAberta($tentativa);

        if ($numero < 1 || $numero > self::TOTAL_PERGUNTAS) {
            throw new Jogo2HttpException("Número de pergunta inválido.");
        }

        $respondidas = $this->contarRespostas($tentativaId);
        if ($numero !== $respondidas + 1) {
            throw new Jogo2HttpException(
                "Avance as perguntas na ordem da missão.",
                409
            );
        }

        $perguntas = $this->buscarPerguntas((int) $tentativa["desafio_id"]);
        $pergunta = $perguntas[$numero - 1];
        $alternativas = $this->buscarAlternativas((int) $pergunta["id"]);
        $this->validarAlternativas($alternativas);

        foreach ($alternativas as &$alternativa) {
            unset($alternativa["correta"]);
            $alternativa["id"] = (int) $alternativa["id"];
        }
        unset($alternativa);

        return [
            "missao" => [
                "id" => (int) $tentativa["desafio_id"],
                "nome" => $tentativa["missao_nome"]
            ],
            "pergunta" => [
                "id" => (int) $pergunta["id"],
                "enunciado" => $pergunta["enunciado"],
                "explicacao" => $pergunta["explicacao"]
            ],
            "alternativas" => $alternativas,
            "numero" => $numero,
            "total" => self::TOTAL_PERGUNTAS,
            "vidas" => (int) $tentativa["vidas_restantes"],
            "pontuacao" => (int) $tentativa["pontuacao"]
        ];
    }

    public function responder(
        int $tentativaId,
        int $usuarioId,
        int $perguntaId,
        int $alternativaId
    ): array {
        $this->conn->beginTransaction();

        try {
            $tentativa = $this->buscarTentativa(
                $tentativaId,
                $usuarioId,
                true
            );

            $sql = "SELECT
                        r.alternativa_id,
                        r.correta,
                        r.pontos,
                        p.explicacao
                    FROM respostas r
                    INNER JOIN perguntas p ON p.id = r.pergunta_id
                    WHERE r.tentativa_id = :tentativa_id
                        AND r.pergunta_id = :pergunta_id
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                "tentativa_id" => $tentativaId,
                "pergunta_id" => $perguntaId
            ]);
            $respostaExistente = $stmt->fetch();

            if ($respostaExistente) {
                if ((int) $respostaExistente["alternativa_id"] !== $alternativaId) {
                    throw new Jogo2HttpException(
                        "Esta pergunta já foi respondida com outra alternativa.",
                        409
                    );
                }

                $alternativaEscolhida = $this->buscarAlternativa(
                    $perguntaId,
                    $alternativaId
                );
                $alternativaCorreta = $this->buscarAlternativaCorreta($perguntaId);
                $totalRespondidas = $this->contarRespostas($tentativaId);
                $finalizou = (int) $tentativa["concluido"] === 1;
                $this->conn->commit();

                return [
                    "correta" => (int) $respostaExistente["correta"] === 1,
                    "alternativa_escolhida" => [
                        "id" => $alternativaId,
                        "texto" => $alternativaEscolhida["texto"]
                    ],
                    "alternativa_correta" => [
                        "id" => (int) $alternativaCorreta["id"],
                        "texto" => $alternativaCorreta["texto"]
                    ],
                    "explicacao" => $respostaExistente["explicacao"],
                    "pontos_ganhos" => (int) $respostaExistente["pontos"],
                    "bonus_conclusao" =>
                        $finalizou &&
                        $totalRespondidas === self::TOTAL_PERGUNTAS &&
                        (int) $tentativa["vidas_restantes"] > 0
                            ? self::BONUS_CONCLUSAO
                            : 0,
                    "pontuacao" => (int) $tentativa["pontuacao"],
                    "vidas" => (int) $tentativa["vidas_restantes"],
                    "pergunta_atual" => $totalRespondidas,
                    "total_perguntas" => self::TOTAL_PERGUNTAS,
                    "finalizou" => $finalizou,
                    "venceu" => $finalizou &&
                        $totalRespondidas === self::TOTAL_PERGUNTAS &&
                        (int) $tentativa["vidas_restantes"] > 0
                ];
            }

            $this->validarTentativaAberta($tentativa);

            $respondidas = $this->contarRespostas($tentativaId);
            $perguntas = $this->buscarPerguntas((int) $tentativa["desafio_id"]);
            if (
                !isset($perguntas[$respondidas]) ||
                (int) $perguntas[$respondidas]["id"] !== $perguntaId
            ) {
                throw new Jogo2HttpException(
                    "Esta pergunta não é a próxima pergunta da missão.",
                    409
                );
            }

            $alternativa = $this->buscarAlternativa(
                $perguntaId,
                $alternativaId
            );
            $correta = (int) $alternativa["correta"] === 1;
            $pontosGanhos = $correta ? self::PONTOS_POR_ACERTO : 0;
            $vidas = (int) $tentativa["vidas_restantes"];
            $vidasNovas = $correta ? $vidas : max(0, $vidas - 1);

            $sql = "INSERT INTO respostas
                        (tentativa_id, pergunta_id, alternativa_id, correta, pontos)
                    VALUES
                        (:tentativa_id, :pergunta_id, :alternativa_id, :correta, :pontos)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                "tentativa_id" => $tentativaId,
                "pergunta_id" => $perguntaId,
                "alternativa_id" => $alternativaId,
                "correta" => $correta ? 1 : 0,
                "pontos" => $pontosGanhos
            ]);

            $totalRespondidas = $respondidas + 1;
            $finalizou = $vidasNovas === 0 ||
                $totalRespondidas === self::TOTAL_PERGUNTAS;
            $bonus = (
                $totalRespondidas === self::TOTAL_PERGUNTAS &&
                $vidasNovas > 0
            ) ? self::BONUS_CONCLUSAO : 0;
            $pontuacaoNova =
                (int) $tentativa["pontuacao"] + $pontosGanhos + $bonus;

            $sql = "UPDATE tentativas
                    SET pontuacao = :pontuacao,
                        vidas_restantes = :vidas,
                        concluido = :concluido,
                        data_fim = CASE WHEN :finalizou = 1 THEN NOW() ELSE data_fim END
                    WHERE id = :tentativa_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                "pontuacao" => $pontuacaoNova,
                "vidas" => $vidasNovas,
                "concluido" => $finalizou ? 1 : 0,
                "finalizou" => $finalizou ? 1 : 0,
                "tentativa_id" => $tentativaId
            ]);

            if ($bonus > 0) {
                $sql = "UPDATE usuarios
                        SET experiencia = experiencia + :experiencia
                        WHERE id = :usuario_id";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    "experiencia" => $pontuacaoNova,
                    "usuario_id" => $usuarioId
                ]);
            }

            $alternativaCorreta = $this->buscarAlternativaCorreta($perguntaId);
            $this->conn->commit();

            return [
                "correta" => $correta,
                "alternativa_escolhida" => [
                    "id" => $alternativaId,
                    "texto" => $alternativa["texto"]
                ],
                "alternativa_correta" => [
                    "id" => (int) $alternativaCorreta["id"],
                    "texto" => $alternativaCorreta["texto"]
                ],
                "explicacao" => $perguntas[$respondidas]["explicacao"],
                "pontos_ganhos" => $pontosGanhos,
                "bonus_conclusao" => $bonus,
                "pontuacao" => $pontuacaoNova,
                "vidas" => $vidasNovas,
                "pergunta_atual" => $totalRespondidas,
                "total_perguntas" => self::TOTAL_PERGUNTAS,
                "finalizou" => $finalizou,
                "venceu" => $totalRespondidas === self::TOTAL_PERGUNTAS &&
                    $vidasNovas > 0
            ];
        } catch (Throwable $erro) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $erro;
        }
    }

    public function buscarResultado(int $tentativaId, int $usuarioId): array
    {
        $tentativa = $this->buscarTentativa($tentativaId, $usuarioId);

        if ((int) $tentativa["concluido"] !== 1) {
            throw new Jogo2HttpException("Esta tentativa ainda não foi concluída.", 409);
        }

        $sql = "SELECT
                    COUNT(*) AS respondidas,
                    SUM(CASE WHEN correta = 1 THEN 1 ELSE 0 END) AS acertos,
                    SUM(CASE WHEN correta = 0 THEN 1 ELSE 0 END) AS erros
                FROM respostas
                WHERE tentativa_id = :tentativa_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(["tentativa_id" => $tentativaId]);
        $contagem = $stmt->fetch();
        $respondidas = (int) $contagem["respondidas"];
        $acertos = (int) $contagem["acertos"];
        $vidas = (int) $tentativa["vidas_restantes"];

        return [
            "tentativa_id" => $tentativaId,
            "missao" => $tentativa["missao_nome"],
            "respondidas" => $respondidas,
            "total_perguntas" => self::TOTAL_PERGUNTAS,
            "acertos" => $acertos,
            "erros" => (int) $contagem["erros"],
            "pontuacao" => (int) $tentativa["pontuacao"],
            "vidas" => $vidas,
            "venceu" => $respondidas === self::TOTAL_PERGUNTAS && $vidas > 0,
            "mensagem" => $respondidas === self::TOTAL_PERGUNTAS && $vidas > 0
                ? "Missão concluída! Você navegou muito bem."
                : "A expedição terminou quando suas vidas acabaram. Tente novamente!"
        ];
    }

    private function validarUsuario(int $usuarioId): void
    {
        $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE id = :id");
        $stmt->execute(["id" => $usuarioId]);

        if (!$stmt->fetch()) {
            throw new Jogo2HttpException(
                "Não foi possível identificar o jogador desta sessão.",
                401
            );
        }
    }

    private function buscarMissao(int $desafioId): array
    {
        $sql = "SELECT
                    d.id,
                    d.nome,
                    d.ativo,
                    j.ativo AS jogo_ativo
                FROM desafios d
                INNER JOIN categorias c ON c.id = d.categoria_id
                INNER JOIN jogos j ON j.id = c.jogo_id
                WHERE d.id = :desafio_id
                    AND j.nome = :nome_jogo
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            "desafio_id" => $desafioId,
            "nome_jogo" => self::NOME_JOGO
        ]);
        $missao = $stmt->fetch();

        if (
            !$missao ||
            (int) $missao["ativo"] !== 1 ||
            (int) $missao["jogo_ativo"] !== 1
        ) {
            throw new Jogo2HttpException("Missão não encontrada ou indisponível.", 404);
        }

        return $missao;
    }

    private function validarEstruturaMissao(int $desafioId): void
    {
        $perguntas = $this->buscarPerguntas($desafioId);
        if (count($perguntas) !== self::TOTAL_PERGUNTAS) {
            throw new Jogo2HttpException(
                "Esta missão precisa ter exatamente cinco perguntas para ser iniciada.",
                422
            );
        }

        foreach ($perguntas as $pergunta) {
            $alternativas = $this->buscarAlternativas((int) $pergunta["id"]);
            $this->validarAlternativas($alternativas);
        }
    }

    private function validarAlternativas(array $alternativas): void
    {
        $corretas = count(array_filter(
            $alternativas,
            static fn(array $alternativa): bool => (int) $alternativa["correta"] === 1
        ));

        if (
            count($alternativas) !== self::TOTAL_ALTERNATIVAS ||
            $corretas !== 1
        ) {
            throw new Jogo2HttpException(
                "Uma pergunta desta missão não tem quatro alternativas e uma única resposta correta.",
                422
            );
        }
    }

    private function buscarPerguntas(int $desafioId): array
    {
        $sql = "SELECT id, desafio_id, enunciado, explicacao, ordem
                FROM perguntas
                WHERE desafio_id = :desafio_id
                ORDER BY ordem ASC, id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(["desafio_id" => $desafioId]);

        return $stmt->fetchAll();
    }

    private function buscarAlternativas(int $perguntaId): array
    {
        $sql = "SELECT id, pergunta_id, texto, correta
                FROM alternativas
                WHERE pergunta_id = :pergunta_id
                ORDER BY id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(["pergunta_id" => $perguntaId]);

        return $stmt->fetchAll();
    }

    private function buscarAlternativa(
        int $perguntaId,
        int $alternativaId
    ): array {
        $sql = "SELECT id, texto, correta
                FROM alternativas
                WHERE id = :alternativa_id
                    AND pergunta_id = :pergunta_id
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            "alternativa_id" => $alternativaId,
            "pergunta_id" => $perguntaId
        ]);
        $alternativa = $stmt->fetch();

        if (!$alternativa) {
            throw new Jogo2HttpException(
                "A alternativa selecionada não pertence a esta pergunta.",
                422
            );
        }

        return $alternativa;
    }

    private function buscarAlternativaCorreta(int $perguntaId): array
    {
        $sql = "SELECT id, texto
                FROM alternativas
                WHERE pergunta_id = :pergunta_id
                    AND correta = 1
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(["pergunta_id" => $perguntaId]);

        return $stmt->fetch();
    }

    private function buscarTentativa(
        int $tentativaId,
        int $usuarioId,
        bool $bloquear = false
    ): array {
        $sql = "SELECT
                    t.id,
                    t.usuario_id,
                    t.desafio_id,
                    t.pontuacao,
                    t.vidas_restantes,
                    t.concluido,
                    d.nome AS desafio_nome,
                    c.nome AS missao_nome
                FROM tentativas t
                INNER JOIN desafios d ON d.id = t.desafio_id
                INNER JOIN categorias c ON c.id = d.categoria_id
                INNER JOIN jogos j ON j.id = c.jogo_id
                WHERE t.id = :tentativa_id
                    AND t.usuario_id = :usuario_id
                    AND j.nome = :nome_jogo
                LIMIT 1" . ($bloquear ? " FOR UPDATE" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            "tentativa_id" => $tentativaId,
            "usuario_id" => $usuarioId,
            "nome_jogo" => self::NOME_JOGO
        ]);
        $tentativa = $stmt->fetch();

        if (!$tentativa) {
            throw new Jogo2HttpException("Tentativa não encontrada.", 404);
        }

        return $tentativa;
    }

    private function validarTentativaAberta(array $tentativa): void
    {
        if ((int) $tentativa["concluido"] === 1) {
            throw new Jogo2HttpException("Esta tentativa já foi concluída.", 409);
        }
    }

    private function contarRespostas(int $tentativaId): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM respostas WHERE tentativa_id = :tentativa_id"
        );
        $stmt->execute(["tentativa_id" => $tentativaId]);

        return (int) $stmt->fetchColumn();
    }
}
