let etapaAtual = 0;
let terceiroSubetapaInicial = 0;
let terceiroTentouAvancarPlaca = false;
let placaMotocaConfirmada = "";
let atualizarAssinaturaCanvas = () => {};
const selectsCustomizados = [];
const VALIDACAO_OBRIGATORIA_ATIVA = window.FORM_VALIDATION_REQUIRED === true;

const abas = document.querySelectorAll(".tab");
const steps = document.querySelectorAll(".step");
const minRelato = Number(window.MIN_RELATO || 80);
const paginaTerceiro = document.body.classList.contains("third-party-page");
const paginaLocatario = document.body.classList.contains("locatario-page");

const PERFIS_BLOQUEADOS_TERCEIRO = ["nenhuma_das_opcoes_anteriores"];
const PERFIS_COM_FORMULARIO_TERCEIRO = [
    "proprietario",
    "procurador",
    "curador_tutela",
    "seguradora_associacao_coperativa"
];

const MENSAGEM_BLOQUEIO_PERFIL = "Para prosseguir com o cadastro do sinistro, é necessário que os demais requisitos obrigatórios sejam atendidos.";
const REGEX_PLACA_MERCOSUL = /^[A-Z]{3}[0-9][A-Z][0-9]{2}$/;
const REGEX_PLACA_ANTIGA = /^[A-Z]{3}[0-9]{4}$/;

function normalizarPlaca(valor) {
    return String(valor || "").toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 7);
}

function normalizarTelefone(valor) {
    return String(valor || "").replace(/\D/g, "");
}

function placaTerceiroEhValida(placa) {
    return REGEX_PLACA_MERCOSUL.test(placa) || REGEX_PLACA_ANTIGA.test(placa);
}

function limparErroCampo(campo, erroEl = null) {
    if (campo) {
        campo.classList.remove("erro");
        campo.setCustomValidity("");
    }
    if (erroEl) {
        erroEl.textContent = "";
        erroEl.classList.add("hidden");
    }
}

function campoEstaVisivel(elemento) {
    if (!elemento) return false;
    const aba = elemento.closest(".tab");
    if (aba && !aba.classList.contains("active")) return false;
    if (elemento.closest(".hidden")) return false;
    const campo = elemento.closest(".campo");
    if (!campo) return true;
    return !campo.classList.contains("hidden");
}

function campoEstaOcultoCondicionalmente(elemento) {
    if (!elemento) return false;
    if (elemento.closest(".hidden")) return true;
    const campo = elemento.closest(".campo");
    return Boolean(campo && campo.classList.contains("hidden"));
}

function obterIndiceAbaDoCampo(elemento) {
    if (!elemento) return -1;
    const aba = elemento.closest(".tab");
    return aba ? Array.from(abas).indexOf(aba) : -1;
}

function focarCampoFormulario(elemento) {
    if (!elemento) return;
    const triggerCustomizado = elemento._customSelectTrigger;
    if (triggerCustomizado) {
        triggerCustomizado.focus();
        return;
    }
    elemento.focus();
}

function inicializarBloqueioAutofill() {
    const campos = document.querySelectorAll("[data-no-autofill='true']");
    if (!campos.length) return;

    campos.forEach((campo) => {
        if (campo.readOnly || campo.disabled) return;

        campo.readOnly = true;

        const liberar = () => {
            campo.readOnly = false;
        };

        campo.addEventListener("focus", liberar, { once: true });
        campo.addEventListener("pointerdown", liberar, { once: true });
        campo.addEventListener("keydown", liberar, { once: true });
    });
}

function inicializarAcionadoresDateTime() {
    const botoes = document.querySelectorAll(".datetime-picker-btn");
    if (!botoes.length) return;

    botoes.forEach((botao) => {
        botao.addEventListener("click", () => {
            const targetId = botao.getAttribute("data-target");
            if (!targetId) return;

            const campo = document.getElementById(targetId);
            if (!campo) return;

            if (typeof campo.showPicker === "function") {
                campo.showPicker();
                return;
            }

            campo.focus();
            campo.click();
        });
    });
}

function mostrarAba(index) {
    abas.forEach((aba, i) => {
        aba.classList.toggle("active", i === index);
    });

    steps.forEach((step, i) => {
        step.classList.toggle("active", i === index);
    });

    atualizarVisibilidadeSteps();
    atualizarTravamentoPlaca();
    atualizarOcorrenciaLocatario();
    requestAnimationFrame(() => atualizarAssinaturaCanvas());
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function atualizarVisibilidadeSteps() {
    if (paginaTerceiro) {
        const stepsTerceiro = document.getElementById("stepsTerceiro");
        if (stepsTerceiro) {
            stepsTerceiro.classList.remove("hidden");
        }
    }

    if (paginaLocatario) {
        const stepsLocatario = document.getElementById("stepsLocatario");
        if (stepsLocatario) {
            stepsLocatario.classList.remove("hidden");
        }
    }

    if (paginaTerceiro) {
        const fluxoSeguradora = fluxoSeguradoraAtivo();
        steps.forEach((step, indice) => {
            if (!fluxoSeguradora) {
                step.classList.remove("hidden");
                const numero = step.querySelector(".step-number");
                const label = step.querySelector(".step-label");
                if (numero) numero.textContent = String(indice + 1);
                if (label) {
                    const labelsPadrao = [
                        "Validação inicial",
                        "Identificação",
                        "Contexto da ocorrência",
                        "Documentos",
                        "Fotos do veículo",
                        "Assinatura e envio",
                    ];
                    label.textContent = labelsPadrao[indice] || label.textContent;
                }
                return;
            }

            if (indice === 0) {
                step.classList.remove("hidden");
                step.classList.toggle("active", etapaAtual <= 1);
                const numero = step.querySelector(".step-number");
                const label = step.querySelector(".step-label");
                if (numero) numero.textContent = "1";
                if (label) label.textContent = "Dados da seguradora";
                return;
            }

            if (indice === 5) {
                step.classList.remove("hidden");
                step.classList.toggle("active", etapaAtual === abas.length - 1);
                const numero = step.querySelector(".step-number");
                const label = step.querySelector(".step-label");
                if (numero) numero.textContent = "2";
                if (label) label.textContent = "Assinatura e envio";
                return;
            }

            step.classList.add("hidden");
            step.classList.remove("active");
        });
    }
}

function atualizarTravamentoPlaca() {
    const inputPlacaMotoca = document.getElementById("placa_motoca");
    if (!inputPlacaMotoca) return;

    const deveTravar = etapaAtual > 0 && placaMotocaConfirmada.length === 7;
    inputPlacaMotoca.readOnly = deveTravar;

    if (deveTravar) {
        inputPlacaMotoca.value = placaMotocaConfirmada;
    }
}

function confirmarPlacaAtual() {
    const inputPlacaValidacao = document.getElementById("placa_motoca_validacao");
    const inputPlacaMotoca = document.getElementById("placa_motoca");
    const inputPlacaSeguradora = document.getElementById("placa_motoca_seguradora");
    const origem = inputPlacaValidacao || inputPlacaMotoca;
    if (!origem) return;

    placaMotocaConfirmada = normalizarPlaca(origem.value);

    if (inputPlacaValidacao) {
        inputPlacaValidacao.value = placaMotocaConfirmada;
    }
    if (inputPlacaMotoca) {
        inputPlacaMotoca.value = placaMotocaConfirmada;
    }
    if (inputPlacaSeguradora) {
        inputPlacaSeguradora.value = placaMotocaConfirmada;
    }

    atualizarTravamentoPlaca();
}

function obterEhTombamento() {
    const tipoOcorrencia = document.getElementById("tipo_ocorrencia");
    if (!tipoOcorrencia) return false;
    const valor = String(tipoOcorrencia.value || "").trim().toLowerCase();
    return valor === "tombamento";
}

function atualizarOcorrenciaLocatario() {
    if (!paginaLocatario) return;
    const campoPlacaTerceiroLocatario = document.getElementById("campoPlacaTerceiroLocatario");
    const inputPlacaTerceiroLocatario = document.getElementById("placa_terceiro");
    const ajudaMidiasAcidenteLocatario = document.getElementById("ajudaMidiasAcidenteLocatario");
    const ehTombamento = obterEhTombamento();

    if (campoPlacaTerceiroLocatario) {
        campoPlacaTerceiroLocatario.classList.toggle("hidden", ehTombamento);
    }

    if (ehTombamento && inputPlacaTerceiroLocatario) {
        inputPlacaTerceiroLocatario.value = "";
        inputPlacaTerceiroLocatario.setCustomValidity("");
        inputPlacaTerceiroLocatario.classList.remove("erro");
    }

    if (ajudaMidiasAcidenteLocatario) {
        ajudaMidiasAcidenteLocatario.textContent = ehTombamento
            ? "Envie, se houver, imagens da via, da motocicleta e demais registros do acidente."
            : "Envie, se houver, imagens da via, da motocicleta, do veículo do terceiro e demais registros do acidente.";
    }
}

function fluxoSeguradoraAtivo() {
    if (!paginaTerceiro) return false;
    const perfil = document.getElementById("perfil");
    return Boolean(perfil && perfil.value === "seguradora_associacao_coperativa");
}

function obterCampoPlacaTerceiroAtivo() {
    if (!paginaTerceiro) {
        return document.getElementById("placa_terceiro");
    }

    return fluxoSeguradoraAtivo()
        ? document.getElementById("placa_terceiro_seguradora")
        : document.getElementById("placa_terceiro");
}

function validarPlacaMotocaCampo(campo, erroEl) {
    if (!VALIDACAO_OBRIGATORIA_ATIVA) {
        limparErroCampo(campo, erroEl);
        return true;
    }

    if (!campo) return true;

    const placa = normalizarPlaca(campo.value);
    campo.value = placa;

    if (!placa) {
        if (erroEl) {
            erroEl.textContent = "Informe a placa da MOTOCA.";
            erroEl.classList.remove("hidden");
        }
        campo.classList.add("erro");
        return false;
    }

    if (!placaTerceiroEhValida(placa)) {
        if (erroEl) {
            erroEl.textContent = "Informe uma placa válida no padrão Mercosul ou antigo.";
            erroEl.classList.remove("hidden");
        }
        campo.classList.add("erro");
        return false;
    }

    if (erroEl) {
        erroEl.classList.add("hidden");
    }
    campo.classList.remove("erro");
    return true;
}

function validarPlacaMercosulGenerica(campo, validarMesmoEmAbaOculta = false) {
    if (!VALIDACAO_OBRIGATORIA_ATIVA) {
        limparErroCampo(campo);
        return true;
    }

    if (!campo || campoEstaOcultoCondicionalmente(campo)) return true;
    if (!validarMesmoEmAbaOculta && !campoEstaVisivel(campo)) return true;

    const placa = normalizarPlaca(campo.value);
    campo.value = placa;

    if (!campo.required && placa === "") {
        campo.setCustomValidity("");
        campo.classList.remove("erro");
        return true;
    }

    if (!placaTerceiroEhValida(placa)) {
        campo.setCustomValidity("Informe uma placa válida no padrão Mercosul ou antigo.");
        campo.classList.add("erro");
        return false;
    }

    campo.setCustomValidity("");
    campo.classList.remove("erro");
    return true;
}

function definirMensagemBloqueioPerfil(texto) {
    const msg = document.getElementById("mensagemBloqueioPerfil");
    if (msg) msg.textContent = texto;
}

function resetarSubetapasTerceiro() {
    terceiroSubetapaInicial = 0;
    terceiroTentouAvancarPlaca = false;

    const perfil = document.getElementById("perfil");
    const campoPlaca = document.getElementById("campoPlacaMotocaValidacao");
    const campoPerfil = document.getElementById("campoPerfilTerceiro");
    const campoProc = document.getElementById("campoProcuracaoEtapa1");
    const campoDocFoto = document.getElementById("campoDocumentoOficialEtapa1");
    const campoDocProcurador = document.getElementById("campoDocumentoOficialProcuradorEtapa1");
    const campoTipoCuratela = document.getElementById("campoTipoTermoCuratela");
    const campoLaudo = document.getElementById("campoLaudoCuratela");
    const campoTermo = document.getElementById("campoTermoCuratela");
    const campoCnh = document.getElementById("campoCnhEtapa1");
    const campoCrlv = document.getElementById("campoCrlvEtapa1");
    const btnGroup = document.getElementById("btnGroupPerfil");
    const bloqueio = document.getElementById("bloqueioPerfil");

    if (campoPlaca) campoPlaca.classList.remove("hidden");
    if (campoPerfil) campoPerfil.classList.add("hidden");
    if (campoProc) campoProc.classList.add("hidden");
    if (campoDocFoto) campoDocFoto.classList.add("hidden");
    if (campoDocProcurador) campoDocProcurador.classList.add("hidden");
    if (campoTipoCuratela) campoTipoCuratela.classList.add("hidden");
    if (campoLaudo) campoLaudo.classList.add("hidden");
    if (campoTermo) campoTermo.classList.add("hidden");
    if (campoCnh) campoCnh.classList.add("hidden");
    if (campoCrlv) campoCrlv.classList.add("hidden");
    if (btnGroup) btnGroup.classList.remove("hidden");
    if (bloqueio) bloqueio.classList.add("hidden");

    if (perfil) perfil.value = "";

    const inputProc = document.getElementById("documento_procuracao");
    const inputDocFoto = document.getElementById("documento_oficial_foto");
    const inputDocProcurador = document.getElementById("documento_oficial_procurador");
    const selectCuratela = document.getElementById("tipo_termo_curatela");
    const inputLaudo = document.getElementById("documento_laudo_curatela");
    const inputTermo = document.getElementById("documento_termo_curatela");
    const inputCnh = document.getElementById("cnh");
    const inputCrlv = document.getElementById("crlv");

    if (inputProc) { inputProc.required = false; inputProc.value = ""; }
    if (inputDocFoto) { inputDocFoto.required = false; inputDocFoto.value = ""; }
    if (inputDocProcurador) { inputDocProcurador.required = false; inputDocProcurador.value = ""; }
    if (selectCuratela) { selectCuratela.required = false; selectCuratela.value = ""; }
    if (inputLaudo) { inputLaudo.required = false; inputLaudo.value = ""; }
    if (inputTermo) { inputTermo.required = false; inputTermo.value = ""; }
    if (inputCnh) { inputCnh.required = false; inputCnh.value = ""; }
    if (inputCrlv) { inputCrlv.required = false; inputCrlv.value = ""; }
}

function atualizarUIEtapaInicialTerceiro() {
    if (!paginaTerceiro || etapaAtual !== 0) return;

    const perfil = document.getElementById("perfil");
    const campoPlaca = document.getElementById("campoPlacaMotocaValidacao");
    const campoPerfil = document.getElementById("campoPerfilTerceiro");
    const campoProc = document.getElementById("campoProcuracaoEtapa1");
    const campoDocFoto = document.getElementById("campoDocumentoOficialEtapa1");
    const campoDocProcurador = document.getElementById("campoDocumentoOficialProcuradorEtapa1");
    const campoTipoCuratela = document.getElementById("campoTipoTermoCuratela");
    const campoLaudo = document.getElementById("campoLaudoCuratela");
    const campoTermo = document.getElementById("campoTermoCuratela");
    const campoCnh = document.getElementById("campoCnhEtapa1");
    const campoCrlv = document.getElementById("campoCrlvEtapa1");

    const inputProc = document.getElementById("documento_procuracao");
    const inputDocFoto = document.getElementById("documento_oficial_foto");
    const inputDocProcurador = document.getElementById("documento_oficial_procurador");
    const selectCuratela = document.getElementById("tipo_termo_curatela");
    const inputLaudo = document.getElementById("documento_laudo_curatela");
    const inputTermo = document.getElementById("documento_termo_curatela");
    const inputCnh = document.getElementById("cnh");
    const inputCrlv = document.getElementById("crlv");

    const perfilAtual = perfil ? perfil.value : "";
    const ehProcurador = perfilAtual === "procurador";
    const ehCurador = perfilAtual === "curador_tutela";
    const ehSeguradora = perfilAtual === "seguradora_associacao_coperativa";

    if (campoPlaca) campoPlaca.classList.toggle("hidden", terceiroSubetapaInicial >= 1);
    if (campoPerfil) campoPerfil.classList.toggle("hidden", terceiroSubetapaInicial !== 1);

    if (campoProc) campoProc.classList.toggle("hidden", !(terceiroSubetapaInicial === 2 && ehProcurador));
    if (campoDocFoto) campoDocFoto.classList.toggle("hidden", !(terceiroSubetapaInicial === 2 && ehProcurador));
    if (campoDocProcurador) campoDocProcurador.classList.toggle("hidden", !(terceiroSubetapaInicial === 2 && ehProcurador));

    if (campoTipoCuratela) campoTipoCuratela.classList.toggle("hidden", !(terceiroSubetapaInicial === 2 && ehCurador));
    if (campoLaudo) campoLaudo.classList.toggle("hidden", !(terceiroSubetapaInicial === 3 && ehCurador));
    if (campoTermo) campoTermo.classList.toggle("hidden", !(terceiroSubetapaInicial === 3 && ehCurador));
    if (campoCnh) campoCnh.classList.toggle("hidden", !(terceiroSubetapaInicial === 2 && !ehSeguradora));
    if (campoCrlv) campoCrlv.classList.toggle("hidden", !(terceiroSubetapaInicial === 2 && !ehSeguradora));

    if (inputProc) inputProc.required = terceiroSubetapaInicial === 2 && ehProcurador;
    if (inputDocFoto) inputDocFoto.required = terceiroSubetapaInicial === 2 && ehProcurador;
    if (inputDocProcurador) inputDocProcurador.required = terceiroSubetapaInicial === 2 && ehProcurador;
    if (selectCuratela) selectCuratela.required = terceiroSubetapaInicial === 2 && ehCurador;
    if (inputLaudo) inputLaudo.required = terceiroSubetapaInicial === 3 && ehCurador;
    if (inputTermo) inputTermo.required = terceiroSubetapaInicial === 3 && ehCurador;
    if (inputCnh) inputCnh.required = terceiroSubetapaInicial === 2 && !ehSeguradora;
    if (inputCrlv) inputCrlv.required = terceiroSubetapaInicial === 2 && !ehSeguradora;
}

function validarDataNaoFutura() {
    const inputData = document.getElementById("data_hora");
    const erro = document.getElementById("erroData");
    if (!inputData) return true;

    if (!VALIDACAO_OBRIGATORIA_ATIVA) {
        limparErroCampo(inputData, erro);
        return true;
    }

    const agora = new Date();
    const dataSelecionada = new Date(inputData.value);

    if (inputData.value && dataSelecionada > agora) {
        inputData.setCustomValidity("A data e horário não podem ser futuros.");
        if (erro) {
            erro.textContent = "A data e horário não podem ser futuros.";
            erro.classList.remove("hidden");
        }
        inputData.classList.add("erro");
        inputData.focus();
        return false;
    }

    inputData.setCustomValidity("");
    inputData.classList.remove("erro");
    if (erro) erro.classList.add("hidden");
    return true;
}

function validarRelatoMinimo(validarMesmoEmAbaOculta = false) {
    const textarea = document.getElementById("relato");
    if (!textarea || campoEstaOcultoCondicionalmente(textarea)) return true;
    if (!validarMesmoEmAbaOculta && !campoEstaVisivel(textarea)) return true;

    if (!VALIDACAO_OBRIGATORIA_ATIVA) {
        textarea.classList.remove("erro");
        return true;
    }

    if (textarea.value.trim().length < minRelato) {
        textarea.classList.remove("neutro", "sucesso");
        textarea.classList.add("erro");
        textarea.focus();
        return false;
    }

    return true;
}

function prepararEtapaInicialTerceiroParaCampo(campo) {
    if (!paginaTerceiro || !campo || obterIndiceAbaDoCampo(campo) !== 0) return;

    const id = campo.id || campo.name || "";
    const perfil = document.getElementById("perfil");

    if (id === "placa_motoca_validacao") {
        terceiroSubetapaInicial = 0;
    } else if (id === "perfil") {
        terceiroSubetapaInicial = 1;
    } else if (id === "documento_procuracao" || id === "documento_oficial_foto" || id === "documento_oficial_procurador") {
        if (perfil) perfil.value = "procurador";
        terceiroSubetapaInicial = 2;
    } else if (id === "cnh" || id === "crlv") {
        terceiroSubetapaInicial = 2;
    } else if (id === "tipo_termo_curatela") {
        if (perfil) perfil.value = "curador_tutela";
        terceiroSubetapaInicial = 2;
    } else if (id === "documento_laudo_curatela" || id === "documento_termo_curatela") {
        if (perfil) perfil.value = "curador_tutela";
        terceiroSubetapaInicial = 3;
    }

    atualizarUIEtapaInicialTerceiro();
}

function exibirCampoComErro(campo) {
    if (!campo) return;

    const indiceAba = obterIndiceAbaDoCampo(campo);
    if (indiceAba >= 0) {
        etapaAtual = indiceAba;
        if (paginaTerceiro && indiceAba === 0) {
            prepararEtapaInicialTerceiroParaCampo(campo);
        }
        mostrarAba(etapaAtual);
    }

    if (campo.tagName === "SELECT" && campo.dataset.customSelectReady === "1") {
        const wrapper = campo._customSelectWrapper;
        if (wrapper) {
            wrapper.dataset.showInvalid = "1";
            wrapper.classList.add("is-invalid");
        }
    }

    setTimeout(() => {
        if (typeof campo.reportValidity === "function") {
            campo.reportValidity();
        }
        focarCampoFormulario(campo);
    }, 50);
}

function campoDeveSerValidadoNoEnvio(campo) {
    if (!campo || campo.disabled) return false;
    if (campoEstaOcultoCondicionalmente(campo)) return false;
    if (campo.type === "hidden" && campo.id !== "assinatura_condutor") return false;
    if (paginaTerceiro && fluxoSeguradoraAtivo()) {
        const indiceAba = obterIndiceAbaDoCampo(campo);
        if (indiceAba >= 0 && indiceAba !== 0 && indiceAba !== abas.length - 1) {
            return false;
        }
    }
    return true;
}

function validarFormularioAntesDeEnviar(formulario) {
    if (!formulario) return false;

    confirmarPlacaAtual();
    const fluxoSeguradora = paginaTerceiro && fluxoSeguradoraAtivo();

    const campoPlacaMotocaPrincipal = paginaTerceiro
        ? document.getElementById("placa_motoca_validacao")
        : document.getElementById("placa_motoca");
    const erroPlacaMotocaPrincipal = paginaTerceiro
        ? document.getElementById("erroPlacaMotocaValidacao")
        : document.getElementById("erroPlacaMotoca");

    if (campoPlacaMotocaPrincipal && !validarPlacaMotocaCampo(campoPlacaMotocaPrincipal, erroPlacaMotocaPrincipal)) {
        exibirCampoComErro(campoPlacaMotocaPrincipal);
        return false;
    }

    const placaTerceiro = obterCampoPlacaTerceiroAtivo();
    if (placaTerceiro && !validarPlacaMercosulGenerica(placaTerceiro, true)) {
        exibirCampoComErro(placaTerceiro);
        return false;
    }

    if (!fluxoSeguradora) {
        const campoData = document.getElementById("data_hora");
        if (campoData && !validarDataNaoFutura()) {
            exibirCampoComErro(campoData);
            return false;
        }
    }

    if (!fluxoSeguradora) {
        const campoRelato = document.getElementById("relato");
        if (campoRelato && !validarRelatoMinimo(true)) {
            exibirCampoComErro(campoRelato);
            return false;
        }
    }

    const assinatura = document.getElementById("assinatura_condutor");
    if (assinatura) {
        assinatura.setCustomValidity(assinatura.value ? "" : "Assine antes de enviar.");
        if (!assinatura.checkValidity()) {
            exibirCampoComErro(assinatura);
            return false;
        }
    }

    const campos = formulario.querySelectorAll("input, select, textarea");
    for (const campo of campos) {
        if (!campoDeveSerValidadoNoEnvio(campo)) continue;

        if (campo.tagName === "SELECT" && campo.dataset.customSelectReady === "1") {
            const wrapper = campo._customSelectWrapper;
            if (wrapper) {
                wrapper.dataset.showInvalid = campo.checkValidity() ? "0" : "1";
                wrapper.classList.toggle("is-invalid", !campo.checkValidity());
            }
        }

        if (!campo.checkValidity()) {
            exibirCampoComErro(campo);
            return false;
        }
    }

    return true;
}

function validarAbaAtual() {
    if (!VALIDACAO_OBRIGATORIA_ATIVA) return true;

    const aba = abas[etapaAtual];
    if (!aba) return true;

    const campos = aba.querySelectorAll("input, select, textarea");
    for (const campo of campos) {
        if (!campoEstaVisivel(campo)) continue;
        if (campo.required && !campo.checkValidity()) {
            if (campo.tagName === "SELECT" && campo.dataset.customSelectReady === "1") {
                const wrapper = campo._customSelectWrapper;
                if (wrapper) {
                    wrapper.dataset.showInvalid = "1";
                    wrapper.classList.add("is-invalid");
                }
                focarCampoFormulario(campo);
            } else {
                campo.reportValidity();
            }
            return false;
        }
    }

    if (paginaLocatario && etapaAtual === 0) {
        const placa = document.getElementById("placa_motoca");
        const erro = document.getElementById("erroPlacaMotoca");
        if (!validarPlacaMotocaCampo(placa, erro)) {
            const campoPlaca = document.getElementById("campoPlacaLocatario");
            const btnGroup = document.getElementById("btnGroupLocatarioEtapa1");
            if (erro) erro.classList.remove("hidden");
            if (campoPlaca) campoPlaca.classList.remove("hidden");
            if (btnGroup) btnGroup.classList.remove("hidden");
            return false;
        }
    }

    const placaTerceiro = obterCampoPlacaTerceiroAtivo();
    if (!validarPlacaMercosulGenerica(placaTerceiro)) {
        placaTerceiro.reportValidity();
        return false;
    }

    if (!validarDataNaoFutura()) return false;
    if (!validarRelatoMinimo()) return false;
    return true;
}

function fecharTodosSelectsCustomizados(excecao = null) {
    selectsCustomizados.forEach(({ wrapper, trigger }) => {
        if (!wrapper || wrapper === excecao) return;
        wrapper.classList.remove("is-open");
        if (trigger) trigger.setAttribute("aria-expanded", "false");
    });
}

function inicializarSelectsCustomizados() {
    document.querySelectorAll("select").forEach((select) => {
        if (select.dataset.customSelectReady === "1" || select.multiple) return;

        const wrapper = document.createElement("div");
        wrapper.className = "custom-select";
        wrapper.dataset.showInvalid = "0";
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const trigger = document.createElement("button");
        trigger.type = "button";
        trigger.className = "custom-select-trigger";
        trigger.setAttribute("aria-haspopup", "listbox");
        trigger.setAttribute("aria-expanded", "false");

        const label = document.createElement("span");
        const caret = document.createElement("span");
        caret.className = "custom-select-caret";
        trigger.appendChild(label);
        trigger.appendChild(caret);

        const menu = document.createElement("ul");
        menu.className = "custom-select-menu";
        menu.setAttribute("role", "listbox");

        const opcoes = Array.from(select.options);
        const itens = [];

        function sincronizar() {
            const selecionado = select.options[select.selectedIndex] || opcoes[0] || null;
            label.textContent = selecionado ? selecionado.textContent : "Selecione";
            trigger.classList.toggle("is-placeholder", !select.value);
            const deveMostrarErro = wrapper.dataset.showInvalid === "1";
            wrapper.classList.toggle("is-invalid", deveMostrarErro && !select.checkValidity());

            itens.forEach(({ item, option }) => {
                item.classList.toggle("is-selected", option.value === select.value);
            });
        }

        opcoes.forEach((option) => {
            const item = document.createElement("li");
            item.className = "custom-select-option";
            item.setAttribute("role", "option");
            item.textContent = option.textContent;
            item.dataset.value = option.value;
            if (option.disabled) {
                item.setAttribute("aria-disabled", "true");
                item.style.opacity = "0.45";
                item.style.cursor = "default";
            } else {
                item.addEventListener("mousedown", (event) => {
                    event.preventDefault();
                    select.value = option.value;
                    wrapper.dataset.showInvalid = "0";
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    select.dispatchEvent(new Event("input", { bubbles: true }));
                    sincronizar();
                    fecharTodosSelectsCustomizados();
                    focarCampoFormulario(select);
                });
            }
            itens.push({ item, option });
            menu.appendChild(item);
        });

        trigger.addEventListener("click", () => {
            const abrir = !wrapper.classList.contains("is-open");
            fecharTodosSelectsCustomizados(abrir ? wrapper : null);
            wrapper.classList.toggle("is-open", abrir);
            trigger.setAttribute("aria-expanded", abrir ? "true" : "false");
        });

        trigger.addEventListener("keydown", (event) => {
            const indiceAtual = opcoes.findIndex((option) => option.value === select.value);

            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                const direcao = event.key === "ArrowDown" ? 1 : -1;
                let proximo = indiceAtual;
                do {
                    proximo += direcao;
                } while (opcoes[proximo] && opcoes[proximo].disabled);

                if (opcoes[proximo]) {
                    select.value = opcoes[proximo].value;
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    sincronizar();
                }
                return;
            }

            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                trigger.click();
                return;
            }

            if (event.key === "Escape") {
                fecharTodosSelectsCustomizados();
            }
        });

        select.addEventListener("change", sincronizar);
        select.classList.add("select-native-hidden");
        select.dataset.customSelectReady = "1";
        select._customSelectTrigger = trigger;
        select._customSelectWrapper = wrapper;
        select.focus = () => trigger.focus();

        wrapper.appendChild(trigger);
        wrapper.appendChild(menu);
        sincronizar();
        selectsCustomizados.push({ wrapper, trigger });
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".custom-select")) {
            fecharTodosSelectsCustomizados();
        }
    });
}

function nextStep() {
    if (!VALIDACAO_OBRIGATORIA_ATIVA && paginaTerceiro && etapaAtual === 0) {
        confirmarPlacaAtual();
        etapaAtual++;
        mostrarAba(etapaAtual);
        return;
    }

    if (paginaTerceiro && etapaAtual === 0) {
        const perfil = document.getElementById("perfil");
        const inputPlacaValidacao = document.getElementById("placa_motoca_validacao");
        const erroPlacaValidacao = document.getElementById("erroPlacaMotocaValidacao");
        const bloqueio = document.getElementById("bloqueioPerfil");
        const btnGroup = document.getElementById("btnGroupPerfil");

        if (terceiroSubetapaInicial === 0) {
            terceiroTentouAvancarPlaca = true;
            if (!validarPlacaMotocaCampo(inputPlacaValidacao, erroPlacaValidacao)) {
                if (erroPlacaValidacao) erroPlacaValidacao.classList.remove("hidden");
                focarCampoFormulario(inputPlacaValidacao);
                return;
            }

            terceiroTentouAvancarPlaca = false;
            if (bloqueio) bloqueio.classList.add("hidden");
            confirmarPlacaAtual();
            terceiroSubetapaInicial = 1;
            atualizarUIEtapaInicialTerceiro();
            focarCampoFormulario(perfil);
            return;
        }

        if (terceiroSubetapaInicial === 1) {
            if (!perfil || !perfil.value) {
                focarCampoFormulario(perfil);
                return;
            }

            if (PERFIS_BLOQUEADOS_TERCEIRO.includes(perfil.value)) {
                definirMensagemBloqueioPerfil(MENSAGEM_BLOQUEIO_PERFIL);
                if (bloqueio) bloqueio.classList.remove("hidden");
                if (btnGroup) btnGroup.classList.add("hidden");
                focarCampoFormulario(perfil);
                return;
            }

            if (!PERFIS_COM_FORMULARIO_TERCEIRO.includes(perfil.value)) {
                focarCampoFormulario(perfil);
                return;
            }

            if (bloqueio) bloqueio.classList.add("hidden");
            if (btnGroup) btnGroup.classList.remove("hidden");

            if (perfil.value === "seguradora_associacao_coperativa") {
                confirmarPlacaAtual();
                etapaAtual++;
                mostrarAba(etapaAtual);
                return;
            }

            if (perfil.value === "procurador" || perfil.value === "proprietario") {
                terceiroSubetapaInicial = 2;
                atualizarUIEtapaInicialTerceiro();
                const inputInicial = perfil.value === "procurador"
                    ? document.getElementById("documento_procuracao")
                    : document.getElementById("cnh");
                if (inputInicial) inputInicial.focus();
                return;
            }

            if (perfil.value === "curador_tutela") {
                terceiroSubetapaInicial = 2;
                atualizarUIEtapaInicialTerceiro();
                const tipo = document.getElementById("tipo_termo_curatela");
                focarCampoFormulario(tipo);
                return;
            }

            confirmarPlacaAtual();
            etapaAtual++;
            mostrarAba(etapaAtual);
            return;
        }

        if (terceiroSubetapaInicial === 2) {
            if (!perfil) return;

            if (perfil.value === "procurador") {
                const inputProc = document.getElementById("documento_procuracao");
                const inputFoto = document.getElementById("documento_oficial_foto");
                const inputProcurador = document.getElementById("documento_oficial_procurador");
                const inputCnh = document.getElementById("cnh");
                const inputCrlv = document.getElementById("crlv");
                if (inputProc && !inputProc.checkValidity()) {
                    inputProc.reportValidity();
                    return;
                }
                if (inputFoto && !inputFoto.checkValidity()) {
                    inputFoto.reportValidity();
                    return;
                }
                if (inputProcurador && !inputProcurador.checkValidity()) {
                    inputProcurador.reportValidity();
                    return;
                }
                if (inputCnh && !inputCnh.checkValidity()) {
                    inputCnh.reportValidity();
                    return;
                }
                if (inputCrlv && !inputCrlv.checkValidity()) {
                    inputCrlv.reportValidity();
                    return;
                }

                confirmarPlacaAtual();
                etapaAtual++;
                mostrarAba(etapaAtual);
                return;
            }

            if (perfil.value === "proprietario") {
                const inputCnh = document.getElementById("cnh");
                const inputCrlv = document.getElementById("crlv");
                if (inputCnh && !inputCnh.checkValidity()) {
                    inputCnh.reportValidity();
                    return;
                }
                if (inputCrlv && !inputCrlv.checkValidity()) {
                    inputCrlv.reportValidity();
                    return;
                }

                confirmarPlacaAtual();
                etapaAtual++;
                mostrarAba(etapaAtual);
                return;
            }

            if (perfil.value === "curador_tutela") {
                const tipo = document.getElementById("tipo_termo_curatela");
                const inputCnh = document.getElementById("cnh");
                const inputCrlv = document.getElementById("crlv");
                if (tipo && !tipo.checkValidity()) {
                    tipo.reportValidity();
                    return;
                }
                if (inputCnh && !inputCnh.checkValidity()) {
                    inputCnh.reportValidity();
                    return;
                }
                if (inputCrlv && !inputCrlv.checkValidity()) {
                    inputCrlv.reportValidity();
                    return;
                }

                terceiroSubetapaInicial = 3;
                atualizarUIEtapaInicialTerceiro();
                const inputLaudo = document.getElementById("documento_laudo_curatela");
                if (inputLaudo) inputLaudo.focus();
                return;
            }
        }

        if (terceiroSubetapaInicial === 3) {
            const inputLaudo = document.getElementById("documento_laudo_curatela");
            const inputTermo = document.getElementById("documento_termo_curatela");
            if (inputLaudo && !inputLaudo.checkValidity()) {
                inputLaudo.reportValidity();
                return;
            }
            if (inputTermo && !inputTermo.checkValidity()) {
                inputTermo.reportValidity();
                return;
            }

            confirmarPlacaAtual();
            etapaAtual++;
            mostrarAba(etapaAtual);
            return;
        }
    }

    if (!validarAbaAtual()) return;

    if (etapaAtual < abas.length - 1) {
        if (etapaAtual === 0) confirmarPlacaAtual();
        if (paginaTerceiro && fluxoSeguradoraAtivo() && etapaAtual === 1) {
            etapaAtual = abas.length - 1;
            mostrarAba(etapaAtual);
            return;
        }
        etapaAtual++;
        mostrarAba(etapaAtual);
    }
}

function prevStep() {
    if (paginaTerceiro && fluxoSeguradoraAtivo() && etapaAtual === abas.length - 1) {
        etapaAtual = 1;
        mostrarAba(etapaAtual);
        return;
    }

    if (paginaTerceiro && etapaAtual === 0) {
        if (terceiroSubetapaInicial === 3) {
            terceiroSubetapaInicial = 2;
            atualizarUIEtapaInicialTerceiro();
            return;
        }
        if (terceiroSubetapaInicial === 2) {
            terceiroSubetapaInicial = 1;
            atualizarUIEtapaInicialTerceiro();
            return;
        }
        if (terceiroSubetapaInicial === 1) {
            terceiroSubetapaInicial = 0;
            atualizarUIEtapaInicialTerceiro();
            return;
        }
    }

    if (etapaAtual > 0) {
        etapaAtual--;
        mostrarAba(etapaAtual);
    }
}

function voltarParaPlaca() {
    if (paginaTerceiro) {
        const inputPlaca = document.getElementById("placa_motoca_validacao");
        resetarSubetapasTerceiro();
        if (inputPlaca) {
            inputPlaca.value = "";
            inputPlaca.focus();
        }
        return;
    }

    if (paginaLocatario) {
        const campoPlaca = document.getElementById("campoPlacaLocatario");
        const btnGroup = document.getElementById("btnGroupLocatarioEtapa1");
        const inputPlaca = document.getElementById("placa_motoca");
        const erro = document.getElementById("erroPlacaMotoca");

        if (campoPlaca) campoPlaca.classList.remove("hidden");
        if (btnGroup) btnGroup.classList.remove("hidden");
        limparErroCampo(inputPlaca, erro);
        if (inputPlaca) inputPlaca.focus();
    }
}

function inicializarDataMaxima() {
    const inputData = document.getElementById("data_hora");
    if (!inputData) return;

    const agora = new Date();
    const pad = (n) => String(n).padStart(2, "0");

    inputData.max =
        `${agora.getFullYear()}-${pad(agora.getMonth() + 1)}-${pad(agora.getDate())}T${pad(agora.getHours())}:${pad(agora.getMinutes())}`;

    inputData.addEventListener("input", () => {
        inputData.setCustomValidity("");
        const erro = document.getElementById("erroData");
        if (erro) erro.classList.add("hidden");
    });

    inputData.addEventListener("change", validarDataNaoFutura);
}

function removerObrigatoriedadeTemporaria() {
    if (VALIDACAO_OBRIGATORIA_ATIVA) return;

    document.querySelectorAll("form [required]").forEach((campo) => {
        campo.dataset.requiredOriginal = "1";
        campo.required = false;
        campo.removeAttribute("required");
        campo.setCustomValidity("");
    });
}

function inicializarCamposCondicionais() {
    const tipoOcorrencia = document.getElementById("tipo_ocorrencia");
    if (paginaLocatario && tipoOcorrencia) {
        tipoOcorrencia.addEventListener("change", atualizarOcorrenciaLocatario);
        atualizarOcorrenciaLocatario();
    }

    const vitimas = document.getElementById("vitimas");
    const qtdVitimas = document.getElementById("qtdVitimas");
    const informacoesVitima = document.getElementById("informacoesVitima");
    if (vitimas && qtdVitimas) {
        const textareaVitima = informacoesVitima ? informacoesVitima.querySelector("textarea") : null;
        const inputQtdVitimas = qtdVitimas.querySelector("input");
        const atualizar = () => {
            const ativo = vitimas.value === "sim";
            qtdVitimas.classList.toggle("hidden", !ativo);
            if (informacoesVitima) informacoesVitima.classList.toggle("hidden", !ativo);
            if (inputQtdVitimas) inputQtdVitimas.required = ativo;
            if (textareaVitima) textareaVitima.required = ativo;
        };
        vitimas.addEventListener("change", atualizar);
        atualizar();
    }

    const testemunhas = document.getElementById("testemunhas");
    const dadosTestemunhas = document.getElementById("dadosTestemunhas");
    if (testemunhas && dadosTestemunhas) {
        const atualizar = () => dadosTestemunhas.classList.toggle("hidden", testemunhas.value !== "sim");
        testemunhas.addEventListener("change", atualizar);
        atualizar();
    }

    const camera = document.getElementById("camera");
    const fotosCamera = document.getElementById("fotosCamera");
    if (camera && fotosCamera) {
        const atualizar = () => fotosCamera.classList.toggle("hidden", camera.value !== "sim");
        camera.addEventListener("change", atualizar);
        atualizar();
    }

    const possuiMidiasAcidente = document.getElementById("possuiMidiasAcidente");
    const midiasAcidente = document.getElementById("midiasAcidente");
    if (possuiMidiasAcidente && midiasAcidente) {
        const inputMidias = midiasAcidente.querySelector("input[type='file']");
        const atualizar = () => {
            const ativo = possuiMidiasAcidente.value === "sim";
            midiasAcidente.classList.toggle("hidden", !ativo);
            if (inputMidias) inputMidias.required = ativo;
        };
        possuiMidiasAcidente.addEventListener("change", atualizar);
        atualizar();
    }

    const realizouBo = document.getElementById("realizouBo");
    const campoBoLocatario = document.getElementById("campoBoLocatario");
    const avisoBoLocatario = document.getElementById("avisoBoLocatario");
    if (realizouBo && campoBoLocatario && avisoBoLocatario) {
        const inputBo = campoBoLocatario.querySelector("input[type='file']");
        const atualizar = () => {
            const realizou = realizouBo.value === "sim";
            const naoRealizou = realizouBo.value === "nao";
            campoBoLocatario.classList.toggle("hidden", !realizou);
            avisoBoLocatario.classList.toggle("hidden", !naoRealizou);
            if (inputBo) inputBo.required = realizou;
        };
        realizouBo.addEventListener("change", atualizar);
        atualizar();
    }

    const jaHouveConserto = document.getElementById("jaHouveConserto");
    const campoComprovanteConserto = document.getElementById("campoComprovanteConserto");
    const campoJaRealizouOrcamento = document.getElementById("campoJaRealizouOrcamento");
    const jaRealizouOrcamento = document.getElementById("jaRealizouOrcamento");
    const camposOrcamento = document.getElementById("camposOrcamento");
    const avisoSemOrcamento = document.getElementById("avisoSemOrcamento");
    if (jaHouveConserto && campoComprovanteConserto) {
        const inputComprovante = campoComprovanteConserto.querySelector("input[type='file']");
        const inputsOrcamento = camposOrcamento
            ? camposOrcamento.querySelectorAll("input[type='file']")
            : [];
        const atualizarOrcamento = () => {
            if (!jaRealizouOrcamento) return;

            const podePerguntarOrcamento = jaHouveConserto.value === "nao";
            const realizouOrcamento = jaRealizouOrcamento.value === "sim";
            const naoRealizouOrcamento = jaRealizouOrcamento.value === "nao";

            if (campoJaRealizouOrcamento) campoJaRealizouOrcamento.classList.toggle("hidden", !podePerguntarOrcamento);
            if (camposOrcamento) camposOrcamento.classList.toggle("hidden", !(podePerguntarOrcamento && realizouOrcamento));
            if (avisoSemOrcamento) avisoSemOrcamento.classList.toggle("hidden", !(podePerguntarOrcamento && naoRealizouOrcamento));

            jaRealizouOrcamento.required = podePerguntarOrcamento;
            if (!podePerguntarOrcamento) {
                jaRealizouOrcamento.value = "";
            }

            inputsOrcamento.forEach((input, indice) => {
                input.required = podePerguntarOrcamento && realizouOrcamento && indice === 0;
                if (!podePerguntarOrcamento || !realizouOrcamento) {
                    input.value = "";
                }
            });
        };
        const atualizar = () => {
            const ativo = jaHouveConserto.value === "sim";
            campoComprovanteConserto.classList.toggle("hidden", !ativo);
            if (inputComprovante) inputComprovante.required = ativo;
            if (!ativo && inputComprovante) {
                inputComprovante.value = "";
            }
            atualizarOrcamento();
        };
        jaHouveConserto.addEventListener("change", atualizar);
        if (jaRealizouOrcamento) {
            jaRealizouOrcamento.addEventListener("change", atualizarOrcamento);
        }
        atualizar();
    }

    const destinoCodigoValidacao = document.getElementById("destinoCodigoValidacao");
    const ajudaDestinoCodigo = document.getElementById("ajudaDestinoCodigo");
    const labelCodigoRecebido = document.getElementById("labelCodigoRecebido");
    const ajudaCodigoRecebido = document.getElementById("ajudaCodigoRecebido");
    if (destinoCodigoValidacao) {
        const atualizar = () => {
            const campoEmail = document.querySelector("input[name='email']");
            destinoCodigoValidacao.placeholder = "Informe o e-mail";
            if (campoEmail && campoEmail.value.trim() !== "") {
                destinoCodigoValidacao.value = campoEmail.value.trim();
            }
            if (ajudaDestinoCodigo) ajudaDestinoCodigo.textContent = paginaTerceiro
                ? "Usaremos o e-mail informado no cadastro. Para seguradora/associação/cooperativa, informe aqui o e-mail que deve receber o código."
                : "Usaremos o e-mail informado no cadastro para enviar o código de validação.";
            if (labelCodigoRecebido) labelCodigoRecebido.textContent = "Código recebido por e-mail";
            if (ajudaCodigoRecebido) ajudaCodigoRecebido.textContent = "Depois de receber o código no e-mail, informe-o aqui para concluir o envio.";
        };
        const campoEmail = document.querySelector("input[name='email']");
        if (campoEmail) {
            campoEmail.addEventListener("input", atualizar);
            campoEmail.addEventListener("change", atualizar);
        }
        atualizar();
    }
}

function inicializarCodigoValidacaoEmail() {
    const botaoEnviarCodigo = document.getElementById("btnEnviarCodigoEmail");
    const campoDestinoCodigo = document.getElementById("destinoCodigoValidacao");
    const campoCodigoDigitado = document.getElementById("codigoValidacaoDigitado");
    const campoCodigoRecebido = document.getElementById("campoCodigoRecebido");
    const statusEnvioCodigo = document.getElementById("statusEnvioCodigo");
    const formulario = document.querySelector("form");

    if (!botaoEnviarCodigo || !campoDestinoCodigo || !campoCodigoDigitado || !statusEnvioCodigo || !formulario) {
        return;
    }

    const definirStatus = (mensagem, tipo = "info") => {
        statusEnvioCodigo.textContent = mensagem;
        statusEnvioCodigo.classList.remove("erro-msg");
        statusEnvioCodigo.classList.add("info-msg");
        statusEnvioCodigo.style.color = tipo === "erro" ? "#b42318" : "#475467";
    };

    campoCodigoDigitado.addEventListener("input", () => {
        campoCodigoDigitado.value = campoCodigoDigitado.value.replace(/\D/g, "").slice(0, 6);
        campoCodigoDigitado.setCustomValidity("");
    });
    campoCodigoDigitado.required = false;

    botaoEnviarCodigo.addEventListener("click", async () => {
        const email = campoDestinoCodigo.value.trim();
        const tipoFormularioInput = formulario.querySelector("input[name='tipo_formulario']");
        const tipoFormulario = tipoFormularioInput ? tipoFormularioInput.value : "";

        if (!email) {
            campoDestinoCodigo.setCustomValidity("Informe o e-mail para receber o código.");
            campoDestinoCodigo.reportValidity();
            return;
        }

        const emailValido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        campoDestinoCodigo.setCustomValidity(emailValido ? "" : "Informe um e-mail válido para receber o código.");

        if (!campoDestinoCodigo.checkValidity()) {
            campoDestinoCodigo.reportValidity();
            return;
        }

        if (!["locatario", "terceiro"].includes(tipoFormulario)) {
            definirStatus("Não foi possível identificar o formulário para envio do código.", "erro");
            return;
        }

        botaoEnviarCodigo.disabled = true;
        definirStatus("Enviando código para o e-mail informado...");

        try {
            const resposta = await fetch("enviar_codigo_validacao.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                },
                body: new URLSearchParams({
                    destino: email,
                    tipo_formulario: tipoFormulario,
                }),
            });

            const payload = await resposta.json();

            if (!resposta.ok || !payload.ok) {
                throw new Error(payload.msg || "Não foi possível enviar o código.");
            }

            definirStatus(payload.msg || "Código enviado para o e-mail informado.");
            if (campoCodigoRecebido) campoCodigoRecebido.classList.remove("hidden");
            campoCodigoDigitado.required = true;
            campoCodigoDigitado.focus();
        } catch (erro) {
            definirStatus(erro.message || "Não foi possível enviar o código agora.", "erro");
        } finally {
            botaoEnviarCodigo.disabled = false;
        }
    });
    definirStatus("Clique para enviar o código de validação ao e-mail informado acima.");
}

function inicializarContadorRelato() {
    const textarea = document.getElementById("relato");
    const contador = document.getElementById("contadorRelato");
    if (!textarea || !contador) return;

    const atualizar = () => {
        const total = textarea.value.trim().length;
        contador.textContent = `${total} / ${minRelato}`;

        textarea.classList.remove("neutro", "erro", "sucesso");
        contador.classList.remove("erro", "ok");

        if (total === 0) {
            textarea.classList.add("neutro");
            return;
        }

        if (total < minRelato) {
            textarea.classList.add("erro");
            contador.classList.add("erro");
            return;
        }

        textarea.classList.add("sucesso");
        contador.classList.add("ok");
    };

    textarea.addEventListener("input", atualizar);
    atualizar();
}

function inicializarPreviewFotos360() {
    document.querySelectorAll(".input-foto").forEach((input) => {
        input.addEventListener("change", () => {
            const file = input.files[0];
            if (!file) return;

            const uploadBox = input.closest(".upload-box");
            const card = input.closest(".foto-360-card");
            const img = card ? card.querySelector(".carro-360") : null;
            const text = uploadBox ? uploadBox.querySelector(".upload-text") : null;
            const isPdf = file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf");

            if (uploadBox) uploadBox.classList.add("uploaded");
            if (text) text.textContent = isPdf ? `PDF anexado: ${file.name}` : "Arquivo anexado";

            if (!isPdf && img) {
                const reader = new FileReader();
                reader.onload = (e) => { img.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });
    });
}

function inicializarFormatacaoPlaca() {
    const campos = [
        document.getElementById("placa_motoca"),
        document.getElementById("placa_terceiro"),
        document.getElementById("placa_terceiro_seguradora"),
        document.getElementById("placa_motoca_validacao")
    ];

    campos.forEach((campo) => {
        if (!campo) return;

        campo.addEventListener("input", () => {
            if (campo.id === "placa_motoca" && campo.readOnly && placaMotocaConfirmada.length === 7) {
                campo.value = placaMotocaConfirmada;
                return;
            }

            if (!(campo.id === "placa_motoca_validacao" && etapaAtual === 0)) {
                campo.value = normalizarPlaca(campo.value);
            }

            if (campo.id === "placa_motoca_validacao") {
                const destino = document.getElementById("placa_motoca");
                if (destino) destino.value = normalizarPlaca(campo.value);
                if (paginaTerceiro && etapaAtual === 0 && terceiroSubetapaInicial > 0) {
                    terceiroSubetapaInicial = 0;
                    atualizarUIEtapaInicialTerceiro();
                }
                const bloqueio = document.getElementById("bloqueioPerfil");
                if (bloqueio) bloqueio.classList.add("hidden");
            }

            if (campo.id === "placa_terceiro" || campo.id === "placa_terceiro_seguradora") {
                validarPlacaMercosulGenerica(campo);
            }
        });
    });
}

function formatarTelefone(valor) {
    const digitos = String(valor || "").replace(/\D/g, "").slice(0, 11);

    if (digitos.length <= 2) return digitos;
    if (digitos.length <= 6) return `(${digitos.slice(0, 2)}) ${digitos.slice(2)}`;
    if (digitos.length <= 10) return `(${digitos.slice(0, 2)}) ${digitos.slice(2, 6)}-${digitos.slice(6)}`;
    return `(${digitos.slice(0, 2)}) ${digitos.slice(2, 7)}-${digitos.slice(7)}`;
}

function inicializarMascaraTelefone() {
    document.querySelectorAll(".campo-telefone").forEach((campo) => {
        campo.addEventListener("input", () => {
            campo.value = formatarTelefone(campo.value);
        });
    });
}

function inicializarValidacaoArquivos() {
    document.querySelectorAll("input[type='file']").forEach((input) => {
        input.addEventListener("change", () => {
            const maxMb = Number(input.dataset.maxMb || "10");
            const maxFiles = Number(input.dataset.maxFiles || "0");
            const arquivos = Array.from(input.files || []);

            if (maxFiles > 0 && arquivos.length > maxFiles) {
                input.value = "";
                input.setCustomValidity(`Selecione no máximo ${maxFiles} arquivos.`);
                input.reportValidity();
                return;
            }

            const invalido = arquivos.find((file) => {
                const limite = file.type.startsWith("video/") ? Math.max(maxMb, 50) : Math.min(maxMb, 10);
                return file.size > limite * 1024 * 1024;
            });

            if (invalido) {
                input.value = "";
                input.setCustomValidity(`O arquivo ${invalido.name} excede o limite permitido.`);
                input.reportValidity();
                return;
            }

            input.setCustomValidity("");
        });
    });
}

function inicializarLocalizacaoManual() {
    const campoEstado = document.getElementById("estado");
    const campoCep = document.getElementById("cep_local");

    if (campoEstado) {
        campoEstado.addEventListener("input", () => {
            campoEstado.value = campoEstado.value.toUpperCase().replace(/[^A-Z]/g, "").slice(0, 2);
        });
    }

    if (campoCep) {
        campoCep.addEventListener("input", () => {
            const digitos = campoCep.value.replace(/\D/g, "").slice(0, 8);
            campoCep.value = digitos.length > 5
                ? `${digitos.slice(0, 5)}-${digitos.slice(5)}`
                : digitos;
        });
    }
}

function inicializarAssinatura() {
    const canvas = document.getElementById("assinatura_canvas");
    const input = document.getElementById("assinatura_condutor");
    const botaoLimpar = document.getElementById("limpar_assinatura");

    if (!canvas || !input || !botaoLimpar) return;

    const contexto = canvas.getContext("2d");
    if (!contexto) return;

    let desenhando = false;
    let teveDesenho = false;

    function ajustarCanvas() {
        const rect = canvas.getBoundingClientRect();
        if (rect.width < 50) {
            return false;
        }
        const escala = window.devicePixelRatio || 1;
        canvas.width = Math.max(1, Math.round(rect.width * escala));
        canvas.height = Math.max(1, Math.round(220 * escala));
        contexto.setTransform(1, 0, 0, 1, 0, 0);
        contexto.scale(escala, escala);
        contexto.lineWidth = 2.2;
        contexto.lineCap = "round";
        contexto.lineJoin = "round";
        contexto.strokeStyle = "#111";
        contexto.fillStyle = "#fff";
        contexto.fillRect(0, 0, rect.width, 220);
        if (teveDesenho) {
            input.value = "";
            teveDesenho = false;
            input.setCustomValidity(VALIDACAO_OBRIGATORIA_ATIVA ? "Assine novamente após abrir a etapa." : "");
        }
        return true;
    }

    function obterPosicao(event) {
        const rect = canvas.getBoundingClientRect();
        const ponto = "touches" in event ? event.touches[0] : event;
        return {
            x: ponto.clientX - rect.left,
            y: ponto.clientY - rect.top
        };
    }

    function iniciar(event) {
        event.preventDefault();
        const posicao = obterPosicao(event);
        desenhando = true;
        contexto.beginPath();
        contexto.moveTo(posicao.x, posicao.y);
    }

    function mover(event) {
        if (!desenhando) return;
        event.preventDefault();
        const posicao = obterPosicao(event);
        contexto.lineTo(posicao.x, posicao.y);
        contexto.stroke();
        teveDesenho = true;
    }

    function finalizar() {
        if (!desenhando) return;
        desenhando = false;
        contexto.closePath();
        input.value = teveDesenho ? canvas.toDataURL("image/png") : "";
        input.setCustomValidity(teveDesenho || !VALIDACAO_OBRIGATORIA_ATIVA ? "" : "Assine antes de enviar.");
    }

    function limpar() {
        teveDesenho = false;
        input.value = "";
        input.setCustomValidity(VALIDACAO_OBRIGATORIA_ATIVA ? "Assine antes de enviar." : "");
        ajustarCanvas();
    }

    canvas.addEventListener("pointerdown", iniciar);
    canvas.addEventListener("pointermove", mover);
    canvas.addEventListener("pointerup", finalizar);
    canvas.addEventListener("pointerleave", finalizar);
    canvas.addEventListener("touchstart", iniciar, { passive: false });
    canvas.addEventListener("touchmove", mover, { passive: false });
    canvas.addEventListener("touchend", finalizar);
    botaoLimpar.addEventListener("click", limpar);
    atualizarAssinaturaCanvas = () => {
        if (!ajustarCanvas()) {
            setTimeout(() => {
                ajustarCanvas();
            }, 80);
        }
    };
    window.addEventListener("resize", atualizarAssinaturaCanvas);

    atualizarAssinaturaCanvas();
    input.setCustomValidity(VALIDACAO_OBRIGATORIA_ATIVA ? "Assine antes de enviar." : "");
}

function inicializarPerfilTerceiro() {
    if (!paginaTerceiro) return;

    const perfil = document.getElementById("perfil");
    if (!perfil) return;

    const blocoPadrao = document.getElementById("blocoIdentificacaoPadrao");
    const blocoSeguradora = document.getElementById("blocoSeguradora");

    const atualizarFluxoSeguradora = () => {
        const ativo = fluxoSeguradoraAtivo();
        const camposSeguradora = [
            document.getElementById("seguradora_nome"),
            document.getElementById("placa_terceiro_seguradora"),
            document.getElementById("seguradora_representando"),
            document.getElementById("documento_representacao_seguradora"),
            document.getElementById("motivo_contato_seguradora")
        ];
        if (blocoPadrao) blocoPadrao.classList.toggle("hidden", ativo);
        if (blocoSeguradora) blocoSeguradora.classList.toggle("hidden", !ativo);
        camposSeguradora.forEach((campo) => {
            if (campo) campo.required = ativo;
        });
        confirmarPlacaAtual();
    };

    perfil.addEventListener("change", () => {
        if (etapaAtual === 0 && terceiroSubetapaInicial > 1) {
            terceiroSubetapaInicial = 1;
        }
        atualizarUIEtapaInicialTerceiro();
        atualizarFluxoSeguradora();
    });

    atualizarFluxoSeguradora();
}

function inicializarEnvioFormulario() {
    const formulario = document.querySelector("form");
    if (!formulario) return;

    formulario.noValidate = true;

    formulario.addEventListener("submit", (event) => {
        if (formulario.dataset.enviando === "1") {
            return;
        }

        event.preventDefault();

        if (paginaTerceiro && fluxoSeguradoraAtivo()) {
            confirmarPlacaAtual();
        }

        if (!validarFormularioAntesDeEnviar(formulario)) {
            return;
        }

        formulario.dataset.enviando = "1";
        const botaoSubmit = formulario.querySelector('button[type="submit"], .btn[type="submit"]');
        if (botaoSubmit) {
            botaoSubmit.disabled = true;
            botaoSubmit.dataset.textoOriginal = botaoSubmit.textContent || "";
            botaoSubmit.textContent = "Enviando...";
            botaoSubmit.style.opacity = "0.7";
            botaoSubmit.style.cursor = "wait";
        }
        HTMLFormElement.prototype.submit.call(formulario);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    removerObrigatoriedadeTemporaria();
    inicializarBloqueioAutofill();
    inicializarAcionadoresDateTime();
    inicializarSelectsCustomizados();
    mostrarAba(etapaAtual);

    if (paginaTerceiro) {
        resetarSubetapasTerceiro();
        atualizarUIEtapaInicialTerceiro();
    }

    inicializarCamposCondicionais();
    inicializarPreviewFotos360();
    inicializarContadorRelato();
    inicializarDataMaxima();
    inicializarFormatacaoPlaca();
    inicializarMascaraTelefone();
    inicializarValidacaoArquivos();
    inicializarLocalizacaoManual();
    inicializarAssinatura();
    inicializarPerfilTerceiro();
    inicializarCodigoValidacaoEmail();
    inicializarEnvioFormulario();

    const placaValidacaoTerceiro = document.getElementById("placa_motoca_validacao");
    if (placaValidacaoTerceiro) {
        placaValidacaoTerceiro.addEventListener("keydown", (event) => {
            if (event.key === "Enter" && paginaTerceiro && etapaAtual === 0) {
                event.preventDefault();
            }
        });
    }
});

window.addEventListener("pageshow", () => {
    if (paginaTerceiro && etapaAtual === 0) {
        resetarSubetapasTerceiro();
        atualizarUIEtapaInicialTerceiro();
    }
});


