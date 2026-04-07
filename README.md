# Sinistro Motoca

Sistema web para abertura e gestão de sinistros, com fluxo público para envio dos formulários e área administrativa para análise, auditoria, exportação e geração de PDF.

## Stack

- PHP
- MySQL / MariaDB
- XAMPP
- Dompdf
- JavaScript, HTML e CSS

## Estrutura Principal

- [index.php](/c:/xampp/htdocs/sinistro_motoca/index.php): entrada pública do sistema
- [locatario.php](/c:/xampp/htdocs/sinistro_motoca/locatario.php): formulário do locatário
- [terceiro.php](/c:/xampp/htdocs/sinistro_motoca/terceiro.php): formulário de terceiro
- [salvar_sinistro.php](/c:/xampp/htdocs/sinistro_motoca/salvar_sinistro.php): processamento principal do envio
- [admin/](/c:/xampp/htdocs/sinistro_motoca/admin): área administrativa
- [config/](/c:/xampp/htdocs/sinistro_motoca/config): banco, sessão, permissões, auditoria, e-mail e helpers
- [database/migrations](/c:/xampp/htdocs/sinistro_motoca/database/migrations): histórico de mudanças do banco
- [docs/](/c:/xampp/htdocs/sinistro_motoca/docs): documentação operacional e técnica
- [uploads/](/c:/xampp/htdocs/sinistro_motoca/uploads): anexos enviados
- [logs/](/c:/xampp/htdocs/sinistro_motoca/logs): logs estruturados do sistema

## Fluxo Resumido

1. O usuário acessa o formulário público.
2. Informa dados do sinistro, localização, relato, anexos e assinatura.
3. O sistema valida os dados, gera número de registro e salva o caso.
4. O admin analisa, altera status, exporta relatórios e consulta auditoria.

## Recursos Já Implementados

- número de registro por sinistro
- upload validado com checagem de MIME
- sessão administrativa com timeout e renovação
- auditoria de abertura, alteração de status e exclusão
- controle de acesso por perfil no admin
- exportação Excel
- geração de PDF
- logs de login, e-mail e eventos operacionais

## Ambiente Local

Pré-requisitos:

- XAMPP com Apache e MySQL ativos
- PHP do XAMPP disponível em `C:\xampp\php\php.exe`
- banco `sinistro_motoca`

Passos básicos:

1. Coloque o projeto em `C:\xampp\htdocs\sinistro_motoca`.
2. Crie ou importe o banco `sinistro_motoca`.
3. Execute as migrations em [database/migrations](/c:/xampp/htdocs/sinistro_motoca/database/migrations).
4. Ajuste [config/db.php](/c:/xampp/htdocs/sinistro_motoca/config/db.php) e os arquivos de e-mail se necessário.
5. Acesse `http://localhost/sinistro_motoca`.

## Operação

- painel admin: [admin/painel.php](/c:/xampp/htdocs/sinistro_motoca/admin/painel.php)
- auditoria: [admin/auditoria.php](/c:/xampp/htdocs/sinistro_motoca/admin/auditoria.php)
- usuários admin: [admin/usuarios.php](/c:/xampp/htdocs/sinistro_motoca/admin/usuarios.php)
- login admin: [admin/login.php](/c:/xampp/htdocs/sinistro_motoca/admin/login.php)

## IA Externa Para Analise

Para habilitar a análise externa no detalhe do sinistro, defina variáveis de ambiente antes de iniciar o Apache:

- `MOTOCA_AI_ENABLED=1`
- `MOTOCA_AI_API_KEY=sua_chave`
- `MOTOCA_AI_MODEL=nome_do_modelo`
- `MOTOCA_AI_BASE_URL=https://api.openai.com/v1`
- `MOTOCA_AI_TIMEOUT=60`
- `MOTOCA_AI_MAX_IMAGES=3`
- `MOTOCA_AI_PROVIDER_LABEL=IA externa`

Com isso, a tela [admin/visualizar.php](/c:/xampp/htdocs/sinistro_motoca/admin/visualizar.php) passa a exibir um botão para gerar o parecer estruturado com IA externa e guardar cache local em `logs/ai_analises/`.
