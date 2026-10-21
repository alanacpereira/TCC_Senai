(() => {
    "use strict";

    const widget = document.getElementById("central-acessibilidade");
    if (!widget) {
        return;
    }

    const openButton = document.getElementById("botao-acessibilidade");
    const panel = document.getElementById("painel-acessibilidade");
    const closeButton = panel.querySelector(".fechar-acessibilidade");
    const resetButton = panel.querySelector(".restaurar-acessibilidade");
    const status = panel.querySelector(".status-acessibilidade");
    const convite = document.getElementById("convite-acessibilidade");
    const ativarButton = convite.querySelector("[data-ativar-acessibilidade]");
    const recusarButton = convite.querySelector("[data-recusar-acessibilidade]");
    const controls = [...panel.querySelectorAll("[data-preferencia]")];
    const config = window.PalavraAcessibilidadeConfig || {};
    const endpoint = widget.dataset.endpoint;
    const storageKey = "palavra-a-bordo-accessibility-v1";
    const defaults = {
        fonte_extra: true,
        alto_contraste: true,
        espacamento_texto: true,
        reduzir_animacoes: true,
        acessibilidade_visual: true,
        configurada: true
    };
    const classNames = {
        fonte_extra: "a11y-font-extra",
        alto_contraste: "a11y-high-contrast",
        espacamento_texto: "a11y-text-spacing",
        reduzir_animacoes: "a11y-reduce-motion"
    };
    let autenticado = config.authenticated === true;
    let alteradoAntesDeCarregar = false;
    let syncChain = Promise.resolve();
    let syncRevision = 0;
    let preferencias = carregarPreferenciasIniciais();
    let preferenciasConfiguradas = preferencias.configurada === true;

    function normalizarPreferencias(valor) {
        if (!valor || typeof valor !== "object") {
            return {
                fonte_extra: false,
                alto_contraste: false,
                espacamento_texto: false,
                reduzir_animacoes: false,
                acessibilidade_visual: false,
                configurada: false
            };
        }
        return {
            fonte_extra: Boolean(valor.fonte_extra),
            alto_contraste: Boolean(valor.alto_contraste),
            espacamento_texto: Boolean(valor.espacamento_texto),
            reduzir_animacoes: Boolean(valor.reduzir_animacoes),
            acessibilidade_visual: Boolean(valor.acessibilidade_visual),
            configurada: valor.configurada === true
        };
    }

    function lerPreferenciasLocais() {
        try {
            const salvas = JSON.parse(localStorage.getItem(storageKey));
            return salvas && typeof salvas === "object" ? salvas : null;
        } catch (erro) {
            console.warn("Não foi possível ler preferências locais de acessibilidade.", erro);
            return null;
        }
    }

    function carregarPreferenciasIniciais() {
        if (
            config.authenticated === true
            && config.preferences?.configurada === true
        ) {
            return normalizarPreferencias(config.preferences);
        }
        const locais = lerPreferenciasLocais();
        return locais?.configurada === true
            ? normalizarPreferencias(locais)
            : normalizarPreferencias(null);
    }

    function salvarPreferenciasLocais() {
        try {
            localStorage.setItem(
                storageKey,
                JSON.stringify({
                    ...preferencias,
                    configurada: preferenciasConfiguradas
                })
            );
            return true;
        } catch (erro) {
            console.warn("Não foi possível salvar preferências locais de acessibilidade.", erro);
            return false;
        }
    }

    function aplicarPreferencias() {
        document.documentElement.dataset.accessibilityConfigured =
            String(preferenciasConfiguradas);
        convite.hidden = preferenciasConfiguradas;
        Object.entries(classNames).forEach(([chave, classe]) => {
            document.documentElement.classList.toggle(
                classe,
                preferencias[chave]
            );
        });
        controls.forEach((controle) => {
            controle.checked = preferencias[controle.dataset.preferencia];
        });
    }

    function registrarAlteracao(mensagem) {
        preferenciasConfiguradas = true;
        preferencias.configurada = true;
        preferencias.acessibilidade_visual = Object.keys(classNames)
            .some((chave) => preferencias[chave]);
        const salvaLocalmente = salvarPreferenciasLocais();
        aplicarPreferencias();
        status.textContent = salvaLocalmente
            ? mensagem
            : `${mensagem} O navegador não permitiu salvar a preferência neste dispositivo.`;
        if (autenticado) {
            sincronizarPreferencias();
        }
    }

    function sincronizarPreferencias() {
        const revision = ++syncRevision;
        const snapshot = { ...preferencias };
        const corpo = new URLSearchParams({
            csrf_token: widget.dataset.csrfToken || "",
            fonte_extra: snapshot.fonte_extra ? "1" : "0",
            alto_contraste: snapshot.alto_contraste ? "1" : "0",
            espacamento_texto: snapshot.espacamento_texto ? "1" : "0",
            reduzir_animacoes: snapshot.reduzir_animacoes ? "1" : "0",
            acessibilidade_visual: snapshot.acessibilidade_visual ? "1" : "0"
        });

        const sincronizar = async () => {
            if (revision === syncRevision) {
                status.textContent = "Preferências aplicadas. Sincronizando com sua conta...";
            }
            try {
                const resposta = await fetch(endpoint, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "Accept": "application/json"
                    },
                    body: corpo
                });
                const resultado = await resposta.json();
                if (!resposta.ok || !resultado.sucesso) {
                    throw new Error(resultado.mensagem || "Não foi possível sincronizar as preferências.");
                }
                if (revision === syncRevision) {
                    status.textContent = "Preferências salvas na sua conta.";
                }
            } catch (erro) {
                console.error("Falha ao sincronizar as preferências de acessibilidade:", erro);
                if (revision === syncRevision) {
                    status.textContent =
                        "Preferências aplicadas e salvas neste navegador, mas não foi possível sincronizar com sua conta.";
                }
            }
        };
        syncChain = syncChain.then(sincronizar, sincronizar);
        return syncChain;
    }

    function abrirPainel() {
        panel.hidden = false;
        openButton.setAttribute("aria-expanded", "true");
        openButton.setAttribute("aria-label", "Fechar central de acessibilidade");
        openButton.title = "Fechar central de acessibilidade";
        panel.querySelector("input").focus();
    }

    function fecharPainel(devolverFoco = true) {
        panel.hidden = true;
        openButton.setAttribute("aria-expanded", "false");
        openButton.setAttribute("aria-label", "Abrir central de acessibilidade");
        openButton.title = "Abrir central de acessibilidade";
        if (devolverFoco) {
            openButton.focus();
        }
    }

    openButton.addEventListener("click", () => {
        if (panel.hidden) {
            abrirPainel();
        } else {
            fecharPainel(false);
        }
    });
    ativarButton.addEventListener("click", () => {
        preferencias = { ...defaults };
        registrarAlteracao("Recursos de acessibilidade ativados.");
        openButton.focus();
    });
    recusarButton.addEventListener("click", () => {
        preferencias = {
            fonte_extra: false,
            alto_contraste: false,
            espacamento_texto: false,
            reduzir_animacoes: false,
            acessibilidade_visual: false,
            configurada: true
        };
        registrarAlteracao("Acessibilidade não foi ativada. Você pode alterar essa escolha na central quando quiser.");
        openButton.focus();
    });
    closeButton.addEventListener("click", () => fecharPainel());
    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape" && !panel.hidden) {
            fecharPainel();
        }
    });

    controls.forEach((controle) => {
        controle.addEventListener("change", () => {
            alteradoAntesDeCarregar = true;
            preferencias[controle.dataset.preferencia] = controle.checked;
            registrarAlteracao("Preferência aplicada.");
        });
    });

    resetButton.addEventListener("click", () => {
        alteradoAntesDeCarregar = true;
        preferencias = { ...defaults };
        registrarAlteracao("Configurações padrão restauradas.");
    });

    window.addEventListener("storage", (evento) => {
        if (evento.key === storageKey && !autenticado) {
            preferencias = normalizarPreferencias(lerPreferenciasLocais());
            aplicarPreferencias();
        }
    });

    aplicarPreferencias();
    if (!salvarPreferenciasLocais()) {
        status.textContent = "Preferências ativas nesta página, mas o navegador não permitiu salvá-las.";
    }

    if (config.authenticated === null || config.authenticated === undefined) {
        fetch(endpoint, {
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        })
            .then(async (resposta) => {
                const resultado = await resposta.json();
                if (!resposta.ok || !resultado.sucesso) {
                    throw new Error(resultado.mensagem || "Não foi possível carregar preferências.");
                }
                autenticado = resultado.autenticado === true;
                if (!alteradoAntesDeCarregar && autenticado) {
                    preferencias = normalizarPreferencias(resultado.preferencias);
                    aplicarPreferencias();
                    salvarPreferenciasLocais();
                } else if (alteradoAntesDeCarregar && autenticado) {
                    sincronizarPreferencias();
                }
            })
            .catch((erro) => {
                console.error("Falha ao consultar preferências da conta:", erro);
                status.textContent =
                    "As configurações deste navegador estão ativas; não foi possível consultar a sincronização da conta.";
            });
    }
    if (
        autenticado
        && !config.preferences?.configurada
        && preferenciasConfiguradas
    ) {
        sincronizarPreferencias();
    }
})();
