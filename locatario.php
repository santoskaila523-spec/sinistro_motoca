<?php
session_start();
require_once __DIR__ . '/config/placas_motoca.php';
require_once __DIR__ . '/config/formulario_validacao.php';
$erroFormulario = $_SESSION['sinistro_form_erro'] ?? null;
unset($_SESSION['sinistro_form_erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Sinistro | Locatário - MOTOCA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/form.css">
</head>
<body class="form-page locatario-page">
<div class="container-form">

    <div class="form-header">
        <img src="assets/img/logo-site-3d.png" class="logo logo-site" alt="Motoca">
        <h1>Registro de Sinistro - Locatário</h1>
        <p class="page-subtitle">Preencha os dados com calma para registrar o ocorrido e seguir com a análise da equipe.</p>
    </div>

    <?php if (is_string($erroFormulario) && $erroFormulario !== ''): ?>
        <div class="flash-publica flash-publica-erro"><?= htmlspecialchars($erroFormulario, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="steps hidden" id="stepsLocatario">
        <div class="step active"><span class="step-number">1</span><span class="step-label">Identificação</span></div>
        <div class="step"><span class="step-number">2</span><span class="step-label">Dados do sinistro</span></div>
        <div class="step"><span class="step-number">3</span><span class="step-label">Contexto da ocorrência</span></div>
        <div class="step"><span class="step-number">4</span><span class="step-label">Fotos do veículo</span></div>
        <div class="step"><span class="step-number">5</span><span class="step-label">Assinatura e envio</span></div>
    </div>

    <form method="POST" action="salvar_sinistro.php" enctype="multipart/form-data" autocomplete="off" data-bloquear-autofill="true">
        <div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
            <input type="text" name="fake_usuario" autocomplete="username" tabindex="-1">
            <input type="email" name="fake_email" autocomplete="email" tabindex="-1">
            <input type="password" name="fake_senha" autocomplete="new-password" tabindex="-1">
        </div>
        <input type="hidden" name="tipo_formulario" value="locatario">

        <div class="tab active">
            <div class="campo" id="campoPlacaLocatario">
                <label>Placa da MOTOCA</label>
                <input type="text" name="placa_motoca" id="placa_motoca" placeholder="Ex: ABC1D23" maxlength="7" autocomplete="off" required>
                <small class="erro-msg hidden" id="erroPlacaMotoca"></small>
            </div>

            <div class="btn-group" id="btnGroupLocatarioEtapa1">
                <button type="button" class="btn" id="btnProximoLocatarioEtapa1" onclick="nextStep()">Próximo</button>
            </div>
        </div>

        <div class="tab">
            <div class="campo">
                <label>Nome completo</label>
                <input type="text" name="nome" placeholder="Ex: João da Silva" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
            </div>

            <div class="campo" id="campoPlacaTerceiroLocatario">
                <label>Placa do terceiro</label>
                <input type="text" name="placa_terceiro" id="placa_terceiro" placeholder="Ex: ABC1D23" maxlength="7" autocomplete="off">
            </div>

            <div class="linha-campos">
                <div class="campo">
                    <label>Telefone</label>
                    <input type="text" name="telefone" class="campo-telefone" inputmode="numeric" placeholder="Ex: (00) 00000-0000" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
                </div>
                <div class="campo">
                    <label>Data e horário do sinistro</label>
                    <div class="campo-datetime">
                        <input type="datetime-local" name="data_hora" id="data_hora" required>
                        <button type="button" class="datetime-picker-btn" data-target="data_hora" aria-label="Selecionar data e horário"></button>
                    </div>
                    <small class="erro-msg hidden" id="erroData"></small>
                </div>
            </div>

            <div class="campo">
                <label>E-mail</label>
                <input type="email" name="email" placeholder="Ex: exemplo@dominio.com" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
            </div>

            <div class="linha-campos">
                <div class="campo">
                    <label>CEP</label>
                    <input type="text" id="cep_local" name="cep_local" placeholder="Ex: 00000-000" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true">
                </div>
                <div class="campo">
                    <label>Logradouro</label>
                    <input type="text" id="logradouro_local" name="logradouro_local" placeholder="Ex: Rua Exemplo" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
                </div>
            </div>

            <div class="linha-campos">
                <div class="campo">
                    <label>Bairro</label>
                    <input type="text" id="bairro_local" name="bairro_local" placeholder="Ex: Bairro Exemplo" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true">
                </div>
                <div class="campo">
                    <label>Cidade</label>
                    <input type="text" id="cidade" name="cidade" placeholder="Ex: Sua cidade" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
                </div>
            </div>

            <div class="linha-campos">
                <div class="campo">
                    <label>Estado</label>
                    <input type="text" id="estado" name="estado" placeholder="Ex: UF" maxlength="2" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
                </div>
                <div class="campo">
                    <label>Número aproximado</label>
                    <input type="text" name="numero_local" placeholder="Ex: 150 ou próximo ao número 150">
                </div>
            </div>

            <div class="linha-campos">
                <div class="campo">
                    <label>Sentido Via</label>
                    <input type="text" name="sentido_via_local" placeholder="Ex: Centro / bairro">
                </div>
                <div class="campo">
                    <label>Ponto de Referência</label>
                    <input type="text" name="ponto_referencia_local" placeholder="Ex: Em frente ao mercado">
                </div>
            </div>

            <div class="campo">
                <label>Relato do ocorrido (mínimo 80 caracteres)</label>
                <textarea id="relato" name="relato" class="neutro" placeholder="Ex: Estava conduzindo pela rua XYZ quando um veículo entrou sem sinalizar e colidiu comigo..." required></textarea>
                <small class="contador" id="contadorRelato">0 / 80</small>
            </div>

            <div class="btn-group">
                <button type="button" class="btn" onclick="nextStep()">Próximo</button>
            </div>
        </div>

        <div class="tab">
            <div class="campo">
                <label>Tipo de ocorrência</label>
                <select name="tipo_ocorrencia" id="tipo_ocorrencia" required>
                    <option value="">Selecione</option>
                    <option>Colisão</option>
                    <option>Choque</option>
                    <option>Atropelamento</option>
                    <option>Capotamento</option>
                    <option>Tombamento</option>
                </select>
            </div>

            <div class="campo">
                <label>Situação da via</label>
                <select name="situacao_via" required>
                    <option value="">Selecione</option>
                    <option>Seca</option>
                    <option>Molhada</option>
                    <option>Oleosa</option>
                    <option>Arenosa</option>
                    <option>Cascalho</option>
                </select>
            </div>

            <div class="campo">
                <label>Houve vítimas?</label>
                <select id="vitimas" name="houve_vitimas" required>
                    <option value="">Selecione</option>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="campo hidden" id="qtdVitimas">
                <label>Quantas vítimas?</label>
                <input type="number" name="qtd_vitimas" min="1">
            </div>

            <div class="campo hidden" id="informacoesVitima">
                <label>Informações da vítima</label>
                <textarea name="informacoes_vitima" placeholder="Informe nome, estado de saúde, unidade de atendimento, contato e demais detalhes relevantes."></textarea>
            </div>

            <div class="campo">
                <label>Houve testemunhas?</label>
                <select id="testemunhas" name="houve_testemunhas" required>
                    <option value="">Selecione</option>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="campo hidden" id="dadosTestemunhas">
                <label>Dados das testemunhas</label>
                <textarea name="dados_testemunhas" placeholder="Ex: Nome, telefone e CPF"></textarea>
            </div>

            <div class="campo">
                <label>Existem câmeras no local?</label>
                <select id="camera" name="camera">
                    <option value="">Selecione</option>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="campo hidden" id="fotosCamera">
                <label>Anexar imagens/vídeos das câmeras</label>
                <input type="file" name="fotos_camera[]" accept="image/*,video/*" data-max-mb="50" multiple>
                <small class="info-msg">Envie arquivos nítidos e, se possível, que mostrem placa, posição dos veículos ou horário do registro. Imagens de até 10 MB e vídeos de até 50 MB.</small>
            </div>

            <div class="campo">
                <label>Existem fotos ou vídeos do acidente, da via ou dos veículos envolvidos?</label>
                <select id="possuiMidiasAcidente" name="possui_midias_acidente" required>
                    <option value="">Selecione</option>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="campo hidden" id="midiasAcidente">
                <label>Anexar fotos/vídeos do acidente</label>
                <input type="file" name="fotos_local[]" accept="image/*,video/*" data-max-mb="50" multiple>
                <small class="info-msg" id="ajudaMidiasAcidenteLocatario">Envie, se houver, imagens da via, da motocicleta, do veículo do terceiro e demais registros do acidente.</small>
            </div>

            <div class="campo">
                <label>Realizou BO?</label>
                <select id="realizouBo" name="realizou_bo" required>
                    <option value="">Selecione</option>
                    <option value="sim">Sim</option>
                    <option value="nao">Não</option>
                </select>
            </div>

            <div class="campo hidden" id="campoBoLocatario">
                <label>Anexar Boletim de Ocorrência (BO)</label>
                <input type="file" name="bo" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div class="campo hidden" id="avisoBoLocatario">
                <small class="info-msg aviso-destaque">É possível continuar o formulário, mas fique ciente de que é obrigatório o envio do BO. Deve ser entregue no prazo de 24h.</small>
            </div>

            <div class="btn-group">
                <button type="button" class="btn" onclick="prevStep()">Voltar</button>
                <button type="button" class="btn" onclick="nextStep()">Próximo</button>
            </div>
        </div>

        <div class="tab">
            <h3 class="titulo-360">Fotos 360° do veículo</h3>

            <div class="anexos-intro">
                <strong>Como enviar boas fotos</strong>
                <ul>
                    <li>Fotografe com boa iluminação e sem cortar as extremidades do veículo.</li>
                    <li>Envie os quatro lados, mesmo quando o dano parecer concentrado em apenas uma área.</li>
                    <li>Se houver detalhe importante, você pode complementar depois com imagens do local ou câmeras.</li>
                </ul>
            </div>

            <div class="fotos-360-grid">
                <div class="foto-360-card">
                    <span class="foto-label">Frente</span>
                    <img src="assets/img/moto-wheel-front.png" class="carro-360" alt="Frente">
                    <label class="upload-box">
                        <input type="file" name="foto_frente" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>

                <div class="foto-360-card">
                    <span class="foto-label">Traseira</span>
                    <img src="assets/img/moto-wheel-rear.png" class="carro-360" alt="Traseira">
                    <label class="upload-box">
                        <input type="file" name="foto_traseira" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>

                <div class="foto-360-card">
                    <span class="foto-label">Lado esquerdo</span>
                    <img src="assets/img/moto-base.png" class="carro-360" alt="Lado esquerdo">
                    <label class="upload-box">
                        <input type="file" name="foto_lado_esq" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>

                <div class="foto-360-card">
                    <span class="foto-label">Lado direito</span>
                    <img src="assets/img/moto-pass.png" class="carro-360" alt="Lado direito">
                    <label class="upload-box">
                        <input type="file" name="foto_lado_dir" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn" onclick="prevStep()">Voltar</button>
                <button type="button" class="btn" onclick="nextStep()">Próximo</button>
            </div>
        </div>

        <div class="tab">
            <p class="declaracao">
                Declaro, sob minha inteira responsabilidade, que todas as informações prestadas e os documentos anexados são verdadeiros e correspondem à realidade dos fatos. Estou ciente de que qualquer omissão ou informação inválida poderá implicar responsabilização conforme contrato e legislação aplicável.
            </p>

            <div class="campo">
                <label>Assinatura do condutor</label>
                <input type="hidden" name="assinatura_condutor" id="assinatura_condutor" required>
                <div class="assinatura-box">
                    <canvas id="assinatura_canvas" class="assinatura-canvas" width="640" height="220"></canvas>
                </div>
                <div class="assinatura-acoes">
                    <button type="button" class="btn btn-limpar-assinatura" id="limpar_assinatura">Limpar assinatura</button>
                </div>
                <small class="info-msg">Assine com o dedo ou mouse dentro da área acima. Antes de enviar, confira se os dados e anexos estão corretos.</small>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="aceite" required>
                <span>Concordo com a declaração</span>
            </label>

            <input type="hidden" name="canal_codigo_validacao" value="">
            <input type="hidden" name="destino_codigo_validacao" value="">
            <input type="hidden" name="codigo_validacao_digitado" value="">

            <div class="campo">
                <small class="info-msg">Após o envio, o registro será encaminhado para análise da equipe responsável.</small>
            </div>

            <div class="btn-group">
                <button type="button" class="btn" onclick="prevStep()">Voltar</button>
                <button type="submit" class="btn">Enviar Registro</button>
            </div>
        </div>
    </form>
</div>

<script>
window.PLACAS_MOTOCA_VALIDAS = <?php echo json_encode($PLACAS_MOTOCA_VALIDAS, JSON_UNESCAPED_UNICODE); ?>;
window.PLACAS_MOTOCA_POR_BASE = <?php echo json_encode($PLACAS_MOTOCA_POR_BASE, JSON_UNESCAPED_UNICODE); ?>;
window.FORM_VALIDATION_REQUIRED = <?php echo !empty($FORMULARIO_VALIDACAO_OBRIGATORIA) ? 'true' : 'false'; ?>;
window.MIN_RELATO = 80;
</script>
<script src="assets/js/form.js"></script>
</body>
</html>


