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
    <title>Sinistro | Terceiro - MOTOCA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/form.css">
    <link rel="stylesheet" href="assets/css/terceiro.css">
</head>
<body class="form-page third-party-page">
<div class="container-form">
    <div class="form-header">
        <img src="assets/img/logo-site-3d.png" class="logo logo-site" alt="Motoca">
        <h1>Registro de Sinistro - Terceiro</h1>
        <p class="page-subtitle">Use este fluxo para registrar o sinistro como terceiro envolvido e enviar os documentos necessários.</p>
    </div>

    <?php if (is_string($erroFormulario) && $erroFormulario !== ''): ?>
        <div class="flash-publica flash-publica-erro"><?= htmlspecialchars($erroFormulario, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="steps hidden" id="stepsTerceiro">
        <div class="step active"><span class="step-number">1</span><span class="step-label">Validação inicial</span></div>
        <div class="step"><span class="step-number">2</span><span class="step-label">Identificação</span></div>
        <div class="step"><span class="step-number">3</span><span class="step-label">Contexto da ocorrência</span></div>
        <div class="step"><span class="step-number">4</span><span class="step-label">Documentos</span></div>
        <div class="step"><span class="step-number">5</span><span class="step-label">Fotos do veículo</span></div>
        <div class="step"><span class="step-number">6</span><span class="step-label">Assinatura e envio</span></div>
    </div>

    <form method="POST" action="salvar_sinistro.php" enctype="multipart/form-data" autocomplete="off" data-bloquear-autofill="true">
        <div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
            <input type="text" name="fake_usuario" autocomplete="username" tabindex="-1">
            <input type="email" name="fake_email" autocomplete="email" tabindex="-1">
            <input type="password" name="fake_senha" autocomplete="new-password" tabindex="-1">
        </div>
        <input type="hidden" name="tipo_formulario" value="terceiro">

        <div class="tab active">
            <section class="contexto-card" aria-labelledby="contexto-terceiro-titulo">
                <span class="contexto-badge">Orientações importantes</span>
                <h2 id="contexto-terceiro-titulo">Antes de iniciar, confirme se este formulário é o ideal para o seu caso</h2>
                <p>
                    Para registrarmos este acidente no sistema, este fluxo deve ser usado por quem representa o veículo envolvido no atendimento.
                </p>
                <p>
                    Em geral, seguimos com o cadastro quando o responsável é o proprietário, o procurador do proprietário, o curador/tutor do proprietário ou a seguradora, associação ou cooperativa vinculada ao veículo.
                </p>

                <div class="contexto-definicoes">
                    <p><strong>Proprietário:</strong> pessoa indicada no documento do veículo como responsável principal.</p>
                    <p><strong>Procurador do proprietário:</strong> pessoa autorizada a representar o proprietário neste processo, com documentação que comprove a representação.</p>
                </div>

                <div class="contexto-lista">
                    <strong>Separe, sempre que possível:</strong>
                    <ol>
                        <li>Documentos do proprietário, como CNH, CRLV e documento oficial com foto.</li>
                        <li>Documentos que comprovem a representação, quando o cadastro não for feito pelo proprietário.</li>
                        <li>Fotos ou vídeos do local e dos danos.</li>
                        <li>Registro de Ocorrência de Trânsito ou Boletim de Ocorrência, quando houver.</li>
                    </ol>
                </div>
            </section>

            <div class="campo" id="campoPlacaMotocaValidacao">
                <label>Placa da MOTOCA</label>
                <input type="text" name="placa_motoca_validacao" id="placa_motoca_validacao" placeholder="Ex: ABC1D23" maxlength="7" autocomplete="off" required>
                <small class="erro-msg hidden" id="erroPlacaMotocaValidacao"></small>
            </div>

            <div class="campo campo-perfil hidden" id="campoPerfilTerceiro">
                <label>Você é:</label>
                <select name="perfil" id="perfil" required>
                    <option value="">Selecione</option>
                    <option value="proprietario">Proprietário</option>
                    <option value="procurador">Procurador do proprietário</option>
                    <option value="curador_tutela">Curador/Tutor</option>
                    <option value="seguradora_associacao_coperativa">Seguradora/Associação/Cooperativa</option>
                    <option value="nenhuma_das_opcoes_anteriores">Nenhuma das opções anteriores</option>
                </select>
            </div> 

            <div id="campoProcuracaoEtapa1" class="campo hidden">
                <label>Procuração do proprietário ou documento que comprove a representação</label>
                <input type="file" id="documento_procuracao" name="documento_procuracao" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div id="campoDocumentoOficialEtapa1" class="campo hidden">
                <label>Documento oficial com foto do proprietário</label>
                <input type="file" id="documento_oficial_foto" name="documento_oficial_foto" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div id="campoDocumentoOficialProcuradorEtapa1" class="campo hidden">
                <label>Documento oficial com foto do procurador</label>
                <input type="file" id="documento_oficial_procurador" name="documento_oficial_procurador" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div id="campoTipoTermoCuratela" class="campo hidden">
                <label>Curatela / Interdição</label>
                <select id="tipo_termo_curatela" name="tipo_termo_curatela">
                    <option value="">Selecione</option>
                    <option value="curatela_parcial">Curatela parcial</option>
                    <option value="curatela_total">Curatela total</option>
                    <option value="tomada_decisao_apoiada">Tomada de decisão apoiada</option>
                </select>
            </div>

            <div id="campoLaudoCuratela" class="campo hidden">
                <label>Anexar laudo médico</label>
                <input type="file" id="documento_laudo_curatela" name="documento_laudo_curatela" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div id="campoTermoCuratela" class="campo hidden">
                <label>Anexar termo de curatela</label>
                <input type="file" id="documento_termo_curatela" name="documento_termo_curatela" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div class="campo hidden" id="campoCnhEtapa1">
                <label>CNH do proprietário</label>
                <input type="file" id="cnh" name="cnh" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div class="campo hidden" id="campoCrlvEtapa1">
                <label>CRLV do proprietário</label>
                <input type="file" id="crlv" name="crlv" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div class="btn-group" id="btnGroupPerfil">
                <button type="button" class="btn" onclick="nextStep()">Próximo</button>
            </div>

            <div id="bloqueioPerfil" class="perfil-bloqueio hidden">
                <strong id="mensagemBloqueioPerfil">Para prosseguir com o cadastro do sinistro, é necessário que os demais requisitos obrigatórios sejam atendidos.</strong>
                <button type="button" class="btn btn-encerrar" onclick="voltarParaPlaca()">Voltar</button>
            </div>
        </div>
    

        <div class="tab" id="tabIdentificacaoTerceiro">
            <div id="blocoIdentificacaoPadrao">
            <div class="campo">
                <label>Placa da MOTOCA</label>
                <input type="text" name="placa_motoca" id="placa_motoca" placeholder="Ex: ABC1D23" maxlength="7" autocomplete="off" readonly required>
                <small class="erro-msg hidden" id="erroPlacaMotoca"></small>
            </div>
            
            <div class="campo">
                <label>Nome completo</label>
                <input type="text" name="nome" placeholder="Ex: João da Silva" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
            </div>

            <div class="campo">
                <label>Placa do seu veículo</label>
                <input type="text" name="placa_terceiro" id="placa_terceiro" placeholder="Ex: ABC1D23" maxlength="7" autocomplete="off" required>
            </div>

            <div class="linha-campos">
                <div class="campo">
                    <label>Telefone</label>
                    <input type="text" name="telefone" class="campo-telefone" inputmode="numeric" placeholder="(00) 00000-0000" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
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
                <input type="email" name="email" placeholder="exemplo@dominio.com" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-no-autofill="true" required>
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
                <label>Relato do ocorrido (mínimo 150 caracteres)</label>
                <textarea id="relato" name="relato" class="neutro" placeholder="Descreva como ocorreu o acidente..." required></textarea>
                <small class="contador" id="contadorRelato">0 / 150</small>
            </div>
            </div>

            <div id="blocoSeguradora" class="hidden">
                <div class="anexos-intro">
                    <strong>Informações para Seguradora/Associação/Cooperativa</strong>
                    <ul>
                        <li>Preencha os dados abaixo para formalizar o contato em nome do proprietário ou terceiro envolvido.</li>
                        <li>Ao concluir esta etapa, o formulário seguirá para a finalização do envio.</li>
                    </ul>
                </div>

                <div class="campo">
                    <label>Nome da seguradora/associação/cooperativa</label>
                    <input type="text" name="seguradora_nome" id="seguradora_nome" placeholder="Ex: Nome da seguradora">
                </div>

                <div class="linha-campos">
                    <div class="campo">
                        <label>Placa da MOTOCA</label>
                        <input type="text" id="placa_motoca_seguradora" placeholder="Ex: ABC1D23" readonly>
                    </div>
                    <div class="campo">
                        <label>Placa do terceiro</label>
                        <input type="text" name="placa_terceiro_seguradora" id="placa_terceiro_seguradora" placeholder="Ex: ABC1D23" maxlength="7" autocomplete="off">
                    </div>
                </div>

                    <div class="campo">
                        <label>Quem está representando</label>
                    <input type="text" name="seguradora_representando" id="seguradora_representando" placeholder="Ex: Representante de seguros">
                    </div>

                <div class="campo">
                    <label>Documento que comprove a representação</label>
                    <input type="file" name="documento_representacao_seguradora" id="documento_representacao_seguradora" accept="image/*,.pdf" data-max-mb="10">
                </div>

                <div class="campo">
                    <label>Motivo do contato</label>
                    <textarea name="motivo_contato_seguradora" id="motivo_contato_seguradora" placeholder="Descreva o motivo do contato e o contexto do atendimento."></textarea>
                </div>

                <div class="campo">
                    <label>Outros documentos</label>
                    <input type="file" name="outros_documentos_seguradora[]" id="outros_documentos_seguradora" accept="image/*,.pdf" data-max-mb="10" data-max-files="10" multiple>
                    <small class="info-msg">É possível anexar até 10 documentos adicionais, se necessário.</small>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn" onclick="nextStep()">Próximo</button>
            </div>
        </div>

        <div class="tab">
            <div class="campo">
                <label>Tipo de ocorrência</label>
                <select name="tipo_ocorrencia" required>
                    <option value="">Selecione</option>
                    <option>Colisão</option>
                    <option>Choque</option>
                    <option>Atropelamento</option>
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
                <textarea name="informacoes_vitima" placeholder="Informe nome, estado de saúde, local de atendimento, contato e demais detalhes relevantes."></textarea>
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
                <small class="info-msg">Envie arquivos nítidos e, se possível, com identificação de placa, horário ou posição dos veículos. Imagens de até 10 MB e vídeos de até 50 MB.</small>
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
                <input type="file" name="fotos[]" accept="image/*,video/*" data-max-mb="50" multiple>
                <small class="info-msg">Envie fotos ou vídeos da via, dos danos, da motocicleta e do veículo do terceiro, se houver.</small>
            </div>

            <div class="btn-group">
                <button type="button" class="btn" onclick="prevStep()">Voltar</button>
                <button type="button" class="btn" onclick="nextStep()">Próximo</button>
            </div>
        </div>

        <div class="tab">
            <div class="anexos-intro">
                <strong>Orientações para anexos</strong>
                <ul>
                    <li>Envie documentos legíveis, sem corte e com todas as informações visíveis.</li>
                    <li>Nas fotos e vídeos do local, priorize imagens claras do posicionamento dos veículos e danos aparentes.</li>
                    <li>Use PDF ou imagem para documentos e evite arquivos desfocados.</li>
                </ul>
            </div>

            <div class="campo">
                <label>Boletim de Ocorrência (BO) - opcional</label>
                <input type="file" name="bo" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div class="campo">
                <label>Já houve conserto do veículo?</label>
                <select id="jaHouveConserto" name="ja_houve_conserto" required>
                    <option value="">Selecione</option>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div class="campo hidden" id="campoComprovanteConserto">
                <label>Anexar comprovante ou nota fiscal</label>
                <input type="file" name="comprovante_conserto" accept="image/*,.pdf" data-max-mb="10">
            </div>

            <div class="campo hidden" id="campoJaRealizouOrcamento">
                <label>Já realizou orçamento?</label>
                <select id="jaRealizouOrcamento" name="ja_realizou_orcamento">
                    <option value="">Selecione</option>
                    <option value="nao">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <div id="camposOrcamento" class="hidden">
                <div class="campo">
                    <label>Anexar orçamento 1</label>
                    <input type="file" name="orcamento_1" accept="image/*,.pdf" data-max-mb="10">
                </div>
                <div class="campo">
                    <label>Anexar orçamento 2</label>
                    <input type="file" name="orcamento_2" accept="image/*,.pdf" data-max-mb="10">
                </div>
                <div class="campo">
                    <label>Anexar orçamento 3</label>
                    <input type="file" name="orcamento_3" accept="image/*,.pdf" data-max-mb="10">
                </div>
            </div>

            <div class="campo hidden" id="avisoSemOrcamento">
                <small class="info-msg aviso-destaque">A MOTOCA possui oficinas parceiras para indicação. Após a análise da documentação enviada, serão encaminhadas por e-mail opções de oficinas parceiras, a título comparativo, juntamente com os orçamentos já apresentados.</small>
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
                    <li>Fotografe frente, traseira e laterais com boa iluminação.</li>
                    <li>Mesmo sem dano visível em um lado, envie a imagem para registrar o estado geral do veículo.</li>
                    <li>Se houver detalhe relevante, complemente com fotos extras na etapa anterior.</li>
                </ul>
            </div>

            <div class="fotos-360-grid">
                <div class="foto-360-card">
                    <span class="foto-label">Frente</span>
                    <img src="assets/img/carro-frente.png" class="carro-360" alt="Frente">
                    <label class="upload-box">
                        <input type="file" name="foto_frente" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>

                <div class="foto-360-card">
                    <span class="foto-label">Traseira</span>
                    <img src="assets/img/carro-traseira.png" class="carro-360" alt="Traseira">
                    <label class="upload-box">
                        <input type="file" name="foto_traseira" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>

                <div class="foto-360-card">
                    <span class="foto-label">Lado esquerdo</span>
                    <img src="assets/img/carro-lado-esq.png" class="carro-360" alt="Lado esquerdo">
                    <label class="upload-box">
                        <input type="file" name="foto_lado_esq" accept="image/*,.pdf" data-max-mb="10" class="input-foto">
                        <span class="upload-text">Arraste ou clique para anexar</span>
                    </label>
                </div>

                <div class="foto-360-card">
                    <span class="foto-label">Lado direito</span>
                    <img src="assets/img/carro-lado-dir.png" class="carro-360" alt="Lado direito">
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
                Declaro, sob minha responsabilidade, que as informações fornecidas e os documentos enviados são verdadeiros e refletem fielmente os fatos ocorridos. Reconheço que a apresentação de dados ou documentos falsos poderá gerar responsabilização nas esferas administrativa, civil e/ou penal.
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
                <small class="info-msg">Assine com o dedo ou mouse dentro da área acima.</small>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="aceite" required>
                <span>Concordo com a declaração acima</span>
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
window.MIN_RELATO = 150;
</script>
<script src="assets/js/form.js"></script>
</body>
</html>
