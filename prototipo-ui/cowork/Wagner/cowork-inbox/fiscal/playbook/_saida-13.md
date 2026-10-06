---
sessao: "13"
titulo: Aferição read-only — de onde vêm os chamados fiscais
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main 1ab4ab51b1 (pós-#8806)
medido_em: produção Hostinger, 2026-10-06 17:14 (relógio do MySQL), só SELECT COUNT
prefixo_tocado: só este arquivo
---
# _saida-13 · De onde vêm os chamados fiscais

**Resposta curta:** hoje o chamado de suporte **não é registrado como chamado**. Ele chega como **conversa de WhatsApp** na Caixa Unificada do business 1, e nenhuma das três estruturas que poderiam classificá-lo está em uso. O volume fiscal **não é medível como chamado**. O que dá para medir é um **teto por palavra-chave**: 23 mensagens recebidas em 14 conversas nos últimos 30 dias.

## 1 · Feito — onde o chamado é registrado (caminho e linha, `main@1ab4ab51b1`)

| fonte | onde | tem motivo/categoria? | uso em produção (30 dias) |
|---|---|---|---|
| **Conversa WhatsApp** (Caixa Unificada) | tabela `conversations` · `Modules/Whatsapp/Database/Migrations/2026_05_11_000001_create_omnichannel_tables.php:102-139` · `status` só `open/awaiting_human/resolved/archived` (`:114`) | **não**: nenhuma coluna de motivo | **59** conversas ativas, **todas no business 1**; **1.015** mensagens recebidas |
| **Tags da conversa** | `whatsapp_tags` + `whatsapp_conversation_tags` · `.../2026_05_11_120000_create_conversation_tags_tables.php:33-60` · N:N, catálogo por business | **sim, livre** (slug + label) | catálogo com 6 slugs: `cobranca · financeiro · reclamacao · repair-os · suporte · vendas`. **Nenhum é fiscal.** **0** aplicações em 30d, **1** em toda a história |
| **Fila** | tabela `whatsapp_queues` por business (ADR 0267; lida em `CaixaUnificadaController.php:251-266`, com `config('whatsapp.queues')` só de fallback, `Config/config.php:161-176`) + `conversations.queue_override` (`.../2026_06_10_000002_add_queue_override_to_conversations.php:33`) · fila efetiva em `CaixaUnificadaController.php:830-845` | indireto (fila derivada de tag) | business 1 tem só `comercial, financeiro`; **não existe fila fiscal**; **0** overrides em 30d |
| **Feedback do cliente (VoC)** | `clients_feedbacks` · `.../2026_05_27_180000_create_clients_feedbacks_table.php:31-94` · `modulo_afetado`/`tela_afetada`/`acao_afetada` (`:52-54`) · gravado por `ClientFeedbackController.php:42-112` (rota `atendimento.feedback.capture`, `Routes/web.php:260-263`) e pelo formulário público assinado (`FeedbackFormController.php:100-138`, `Routes/web.php:54-63`) | **sim** (módulo/tela/ação, texto livre) | **2** registros em toda a história, ambos `web_form`, `modulo_afetado` nulo, o último de 2026-07-17. **0** em 30d |
| **Jana** | `jana_conversas` / `jana_mensagens` (`copiloto_*` sobrevive só como view: `Schema::hasTable` = false, presente no `information_schema`) | não | **0** mensagens de usuário em 30d |
| **Modo Suporte** (acesso do suporte ao tenant) | `support_access_logs` · `database/migrations/2026_06_23_130000_create_support_access_logs_table.php:26-35` (ADR 0305) · grava `route` | não, mas a rota seria um sinal | **0** em 30d; 36 no total, o último em 2026-07-15 |
| **Forja** (`mcp_tasks`) | `Modules/Forja/Database/Migrations/2026_04_30_180001_create_mcp_tasks_table.php` · `module` | é tarefa de dev, não chamado | módulos fiscais: Fiscal 23 · NfeBrasil 34 · NFSe 22 · NFE 2 (todos antigos); **0** criadas em 30d (21 tarefas no total em 30d, nenhuma fiscal) |

**Inferência, não medida:** o business 1 é a operação interna, e os 2 canais `whatsapp_whatsmeow` dele são a linha por onde o cliente fala com o suporte. O banco não diz se cada conversa é de cliente do ERP ou de outro contato.

## 2 · Contagem — fiscal, últimos 30 dias

| medida | n | o que prova |
|---|---|---|
| mensagens recebidas (business 1) | 1.015 | denominador |
| … com palavra fiscal (regex abaixo) | **23** | **teto, não contagem**: palavra ≠ chamado |
| conversas distintas com essas mensagens | **14** de 59 ativas | idem |
| por termo: `nota fiscal` 8 · `imposto/icms/tribut` 6 · `certificado` 3 · `nfe/nf-e` 2 · `xml` 2 · `nfs-e` 1 · `cfop` 1 · `sefaz` 0 · `rejei` 0 · `ncm` 0 · `danfe` 0 · `nfc-e` 0 | — | termos sobrepõem (a soma 23 é coincidência, não partição) |
| conversas fiscais classificadas por tag, fila ou feedback | **0** | nenhuma estrutura classifica hoje |

**NÃO MEDI** o volume de *chamados* fiscais: não existe registro de chamado com motivo, e ler o texto das 23 mensagens para classificar seria tratar conteúdo de cliente. Mantive só contagens.

Regex usado: `nota fiscal|nfe|nf-e|nfc-e|nfce|nfs-e|nfse|sefaz|rejei|ncm|cfop|certificado|imposto|icms|tribut|danfe|xml` sobre `lower(messages.body)`, `direction='inbound'`, `created_at >= now() - interval 30 day`. `body` nulo nas recebidas em 30d: 0.

### Recibo (como reproduzir)
Quatro scripts PHP **só de SELECT** (bootstrap do Laravel → `DB::table(...)->count()`), enviados por stdin: `ssh … 'cd domains/oimpresso.com/public_html && php' < probe.php`. Nenhum grava nada, e nenhum devolve texto de mensagem, nome ou valor. As consultas estão transcritas acima; o arquivo do script foi descartável (scratchpad da sessão).

## 3 · Taxonomia proposta (5 motivos) e onde gravar

| slug | label | exemplo de gatilho |
|---|---|---|
| `fiscal-rejeicao-cadastral` | Rejeição cadastral | SEFAZ rejeitou por dado do cliente ou produto (IE, CEP, NCM) |
| `fiscal-duvida-imposto` | Dúvida de imposto | "qual ICMS/CST/CFOP", "por que esse valor de imposto" |
| `fiscal-configuracao` | Configuração | regra tributária, série, ambiente, natureza de operação |
| `fiscal-certificado` | Certificado | vencido, senha, A1 não carrega |
| `fiscal-outro` | Outro | o resto, para não forçar encaixe |

**Onde gravar: recomendo o catálogo de tags que já existe**, com os 5 slugs acima em `whatsapp_tags` do business 1 e uma fila `fiscal` em `whatsapp_queues` do business 1 (a fila é soft-config por business, ADR 0267) com `trigger_tags` = os 5 slugs.
- **A favor:** zero coluna nova; a tag já é por conversa, com Tier 0 por `business_id` (`wa_tags_biz_slug_uniq`) e autor (`created_by_user_id`). A fila dá visibilidade imediata, e a contagem sai de `whatsapp_conversation_tags` × `whatsapp_tags`.
- **Contra (precisa entrar no UC da próxima thread):** **(a)** a tag é N:N e não força **um** motivo por chamado; o UC tem de dizer se 2 motivos valem ou se a UI restringe a 1. **(b)** A adoção é o risco real: tag tem **1 uso em toda a história**, e `clients_feedbacks` **2**. Sem um passo obrigatório (ex.: pedir o motivo ao marcar a conversa como `resolved` quando ela caiu na fila fiscal), a medição sai zero de novo.
- **Alternativas descartadas:**
  - `clients_feedbacks.modulo_afetado`: é Voz do Cliente (dor de produto), não chamado; 2 linhas.
  - Coluna nova `conversations.motivo`: exige migration e duplica o que a tag já faz.
  - Jana e Modo Suporte: 0 uso em 30d.

## 4 · Não feito e por quê
- Não classifiquei as 23 mensagens: seria ler conteúdo de cliente, e a thread é só de contagem.
- Não criei as tags nem a fila: escrita fora do prefixo. É a próxima thread, com UC (UC-TRB-32).
- Não abri `mcp_tasks` da Forja como fonte: é backlog de dev, não chamado.

## 5 · Pedido literal pro [CL]/[W]
> Thread nova "motivo do chamado fiscal" (UC-TRB-32): criar os 5 slugs `fiscal-*` em `whatsapp_tags` (seed idempotente por business, sem migration), a fila `fiscal` em `whatsapp_queues` (por business, pela tela de filas, ADR 0267 — não no `config.php`, que é só fallback) com esses `trigger_tags`, e pedir **um** motivo ao resolver conversa da fila fiscal. Teste: tag de outro business não aplica (Tier 0); resolver sem motivo na fila fiscal é recusado. Re-medir 30 dias depois com a mesma consulta da §2 mais a contagem por tag.

## 6 · Descobertas que mudam outra sessão
- **Thread 16 (bloqueio com saída) e 14 (saúde fiscal):** o "antes" que elas querem reduzir **não tem linha de base classificada**. O único número é o teto de 14 conversas / 23 mensagens em 30d (business 1). Medir depois da 16 sem a tag da §3 vai comparar teto com teto.
- **Jana:** `copiloto_conversas`/`copiloto_mensagens` existem só como **view** em produção (`Schema::hasTable` = false); as tabelas reais são `jana_*`. Quem consultar pelo nome antigo via `Schema::hasTable` lê "não existe".
- **Tags/fila:** o catálogo de tags do business 1 não tem nada fiscal, e a fila `financeiro` dispara por `trigger_tags`, mas houve **1** aplicação de tag em toda a história. A Caixa Unificada classifica no papel, não na prática.

## 7 · Prefixo tocado
`prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/_saida-13.md`. Nenhum outro arquivo; nenhum código; nenhuma escrita em banco.
