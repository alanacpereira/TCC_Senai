(() => {
    "use strict";

    document.querySelectorAll("[data-toggle-password]").forEach((button) => {
        const input = document.getElementById(button.getAttribute("aria-controls"));
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        button.addEventListener("click", () => {
            const mostrar = input.type === "password";
            input.type = mostrar ? "text" : "password";
            button.setAttribute("aria-pressed", String(mostrar));
            button.textContent = mostrar ? "Ocultar" : "Mostrar";
        });
    });

    // Copia o codigo da turma e informa se o navegador exige copia manual.
    document.querySelectorAll("[data-copiar-codigo]").forEach((button) => {
        button.addEventListener("click", async () => {
            const codigo = button.dataset.copiarCodigo;
            const status = button.closest(".codigo-turma-dashboard")
                ?.querySelector("[data-status-codigo]");
            if (!codigo || !status) {
                return;
            }

            try {
                await navigator.clipboard.writeText(codigo);
                status.textContent = "Código copiado. Compartilhe com seus estudantes.";
            } catch {
                status.textContent = `Copie manualmente este código: ${codigo}`;
            }
        });
    });

    const aquario = document.querySelector("[data-aquario]");
    const botoesAquario = aquario?.querySelectorAll("[data-acao-aquario]");
    const mensagemAquario = aquario?.querySelector("[data-mensagem-aquario]");
    const mensagensAquario = {
        bolhas: [
            "Bolhas ao alto! O peixinho encontrou uma nova rota.",
            "Um brilho no fundo do mar: hora de mais uma descoberta!",
            "O peixinho manda dizer: cada resposta certa é um tesouro.",
            "Corrente favorável! Vamos navegar para mais um desafio?"
        ],
        alimentar: [
            "Nhac! O peixinho ganhou energia para novas aventuras.",
            "Lanchinho servido! Ele agradece com uma volta pelo aquário.",
            "Barriga cheia, nadadeiras prontas para explorar!"
        ]
    };
    const indicesMensagensAquario = { bolhas: 0, alimentar: 0 };

    if (aquario && botoesAquario && mensagemAquario) {
        const botoes = [...botoesAquario].filter(
            (botao) => botao instanceof HTMLButtonElement
        );

        botoes.forEach((botao) => {
            botao.addEventListener("click", () => {
                const acao = botao.dataset.acaoAquario === "alimentar"
                    ? "alimentar"
                    : "bolhas";
                const mensagens = mensagensAquario[acao];
                const indice = indicesMensagensAquario[acao];
                const classeAnimacao = acao === "alimentar"
                    ? "aquario-alimentando"
                    : "aquario-animando";
                const contador = aquario.querySelector(`[data-contador-${acao}]`);

                // O cuidado vale apenas nesta visita e nao altera pontos ou perfil.
                aquario.classList.add(classeAnimacao);
                aquario.classList.add("aquario-resposta-visivel");
                mensagemAquario.textContent = mensagens[indice];
                indicesMensagensAquario[acao] = (indice + 1) % mensagens.length;
                if (contador) {
                    contador.textContent = String(Number(contador.textContent) + 1);
                }
                botoes.forEach((botaoAquario) => {
                    botaoAquario.disabled = true;
                });

                window.setTimeout(() => {
                    aquario.classList.remove(classeAnimacao);
                    botoes.forEach((botaoAquario) => {
                        botaoAquario.disabled = false;
                    });
                }, 1000);
            });
        });
    }
})();
