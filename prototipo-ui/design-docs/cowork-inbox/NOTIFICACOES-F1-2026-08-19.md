# NOTIFICAÇÕES (Modelos de notificação) — trio F1 + pedido pro Code · 2026-08-19

> **[CC]** F1 visual. Origem: `/notification-templates` do UltimatePOS (legado Blade). Tela nova no protótipo, rota `notificacoes` do app único (`notificacoes-page.jsx` + `notificacoes-page.css`, export em `prototipo-ui/cowork/notificacoes/`).
> **Lido no espelho da pasta anexada (NÃO no `main` neste turno):** `app/Http/Controllers/NotificationTemplateController.php`, `app/NotificationTemplate.php`, `resources/views/notification_template/index.blade.php` + `partials/{tabs,tags}.blade.php`, `app/Http/Middleware/AdminSidebarMenu.php:843-852`, `app/Utils/NotificationUtil.php`, `app/Console/Commands/AutoSendPaymentReminder.php`. **Lido no `main` NESTE turno:** `package.json`, `scripts/governance/cowork-ssot-guard.mjs`, `prototipo-ui/contrato/contract.schema.json`, `prototipo-ui/contrato/EXEMPLO.contract.json`, `memory/governance/prototipo-readiness.json`, `prototipo-ui/PRE-FLIGHT-TELA.md`, `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md`.
> ⚠️ Não commitado — as tools de GitHub aqui são read-only. Ponte = cola zero-toque ou Issue `cowork-intake`.

---

## 0. Decisões que precisam de [W] antes de codar

| # | Decisão | Recomendação |
|---|---|---|
| D1 | **Copy dos modelos.** O seed (`NotificationTemplate::defaultNotificationTemplates`) está **em inglês** ("Dear {contact_name}", "Thank you for shopping with us") e vai pro cliente final. Traduzi os 11 no F1. | Aprovar a tradução como novo seed + migration que atualiza só os negócios que nunca editaram o modelo (comparar com o texto inglês exato). |
| D2 | **Editor do corpo.** Legado usa TinyMCE (CDN). F1 usa editor mínimo (negrito/itálico/lista/link) + modo HTML. | Manter mínimo — TinyMCE é 400 KB pra 4 comandos e injeta markup que quebra em cliente de e-mail. |
| D3 | **Teste de envio** ("Enviar teste pra mim") não existe no backend. | Rota nova `POST /notification-templates/test` com throttle e destino = e-mail/celular do usuário logado. |
| D4 | **`auto_send_*` só aparece em `new_sale` e `payment_reminder`** (regra do Blade), mas as 3 colunas existem para todos. | Manter a regra do Blade; se abrir para mais modelos, é decisão de produto, não de UI. |
| D5 | **Vazio = não envia** — hoje isso é implícito (`NotificationUtil` só monta o que tem corpo). | Explicitar na UI (feito) e no charter; sem mudança de backend. |

---

## 1. Charter (proposta — destino `resources/js/Pages/NotificationTemplate/Index.charter.md`)

**Missão.** Deixar o dono do negócio escrever, uma vez, o que o sistema vai dizer ao cliente e ao fornecedor em cada evento — por e-mail, SMS e WhatsApp — e decidir o que sai sozinho.

**Persona.** Wagner (escritório, 1440px) configura; Eliana lê pra conferir cobrança. Não é tela de balcão.

**Dados (do controller).** 3 grupos vindos de `NotificationTemplate::{generalNotifications,customerNotifications,supplierNotifications}()`, cada um enriquecido por `__getTemplateDetails()` com `subject · email_body · sms_body · whatsapp_text · auto_send · auto_send_sms · auto_send_wa_notif · cc · bcc`, mais `extra_tags` (matriz de grupos de tags) e `name`. Módulos podem injetar modelos via `moduleUtil->getModuleData('notification_list', …)` — a tela **não** pode assumir a lista fixa de 11.

**Regras de domínio**
- R1 Um registro por (`business_id`, `template_for`) — `updateOrCreate`. Salvar é **em lote**: o form manda `template_data[<key>][…]` de todos os modelos.
- R2 `auto_send*` chega como checkbox: ausente ⇒ `0`. Nunca inferir "manteve o valor anterior".
- R3 Permissão única: `send_notification` (403 no index e no store).
- R4 `auto_send_*` é oferecido só em `new_sale` e `payment_reminder`.
- R5 `send_ledger` é só e-mail — SMS/WhatsApp ficam ocultos (classe `hide` no Blade).
- R6 `{business_logo}` só é renderizado no e-mail (`lang_v1.logo_not_work_in_sms`).
- R7 Tag fora da `extra_tags` do modelo sai literal no envio — a UI avisa, não bloqueia.
- R8 `cc`/`bcc` são campos `email` de um único endereço no legado (input `Form::email`), não lista.
- R9 Modelo com corpo vazio no canal ⇒ nada é enviado por aquele canal.
- R10 `payment_reminder` automático roda pela `AutoSendPaymentReminder` (comando agendado), não no request.
- R11 SMS acentuado cai de 160 para 70 caracteres por segmento (GSM-7 → UCS-2) — custo por segmento.

**Non-goals.** Editor de template visual arrastável; envio em massa; histórico de envios (é do Atendimento); template por cliente; anexos.

**Anti-hooks.** Sem inglês na UI; sem emoji; sem modal full-screen pra editar modelo (é painel na própria tela); sem `rounded-xl+`; nenhuma cor fora de token.

---

## 2. Casos de uso (destino `Index.casos.md`) — Dado/Quando/Então

| UC | Caso | Critério de aceite |
|---|---|---|
| UC-NOT-01 | Abrir a tela sem `send_notification` | 403; nada da lista é renderizado |
| UC-NOT-02 | Abrir com permissão | 3 grupos, 11+ modelos, primeiro selecionado, canal E-mail ativo |
| UC-NOT-03 | Selecionar modelo no rail | painel troca; canal volta pra E-mail; modo Visual |
| UC-NOT-04 | Editar assunto e salvar | `updateOrCreate` grava `subject`; recarregar mostra o novo valor |
| UC-NOT-05 | Editar 3 modelos e salvar | contador diz "3 modelos alterados"; um único POST grava os 3 |
| UC-NOT-06 | Descartar alterações | volta ao último salvo; contador zera |
| UC-NOT-07 | Restaurar padrão de um modelo | campos voltam ao seed; ainda exige Salvar |
| UC-NOT-08 | Inserir tag com cursor no meio do assunto | tag entra na posição, não no fim |
| UC-NOT-09 | Inserir tag no corpo (modo Visual) | tag entra no HTML; prévia resolve o valor de exemplo |
| UC-NOT-10 | Digitar `{foo_bar}` | aviso "tag não reconhecida: {foo_bar}"; salvar não é bloqueado (R7) |
| UC-NOT-11 | Expandir campos personalizados | 10 chips `{contact_custom_field_1..10}` viram inseríveis |
| UC-NOT-12 | Canal SMS sem acento, 200 caracteres | "2 SMS · 160 por segmento" |
| UC-NOT-13 | Canal SMS com acento | limite cai pra 70 e a UI diz "(tem acento)" |
| UC-NOT-14 | Modelo `send_ledger` | abas SMS e WhatsApp desabilitadas (R5) |
| UC-NOT-15 | Modelo de Fornecedor | faixa de aviso do `{business_logo}` visível (R6) |
| UC-NOT-16 | Ligar envio automático de e-mail em `new_sale` | selo `auto` no rail; POST grava `auto_send=1` |
| UC-NOT-17 | Desligar os 3 automáticos de `payment_reminder` | POST grava os 3 como `0` (R2) |
| UC-NOT-18 | Modelo com os 3 canais vazios | selo `vazio` no rail; nenhum envio (R9) |
| UC-NOT-19 | Buscar "pagamento" no rail | só Pagamento recebido, Lembrete de pagamento, Pagamento efetuado |
| UC-NOT-20 | Teclar `/` fora de campo | foco vai pra busca; `Esc` limpa e sai |
| UC-NOT-21 | Alternar Visual → HTML | mesmo conteúdo em HTML cru, editável; volta sem perder markup |
| UC-NOT-22 | Enviar teste (D3) | confirmação com o destino; throttle impede repetição imediata |
| UC-NOT-23 | Módulo injeta modelo novo em `notification_list` | aparece no grupo certo com suas `extra_tags` |
| UC-NOT-24 | CC inválido | erro no campo; salvar bloqueado só para esse campo (R8) |

---

## 3. Testes mínimos (Pest/feature + vitest onde fizer sentido)

1. `index` sem `send_notification` → 403. 2. `index` devolve as 3 coleções com as 9 chaves de `__getTemplateDetails`. 3. `store` cria registro quando não existe. 4. `store` atualiza sem duplicar (unicidade `business_id`+`template_for`). 5. `store` grava `auto_send*=0` quando a checkbox não vem. 6. `store` em lote grava N modelos num POST. 7. `store` sem permissão → 403. 8. Isolamento multi-tenant: negócio A não altera modelo de B. 9. `BusinessUtil::createDefaultNotificationTemplates` continua semeando os 11 após a tradução (D1). 10. `NotificationUtil` não envia quando o corpo do canal está vazio. 11. `AutoSendPaymentReminder` só pega templates com `auto_send=1`. 12. Tag desconhecida sobrevive ao round-trip (não é escapada nem removida). 13. `send_ledger` ignora `sms_body`/`whatsapp_text` recebidos. 14. Contagem de segmentos SMS: 160 sem acento, 70 com acento (unit). 15. `cc`/`bcc` inválidos → erro de validação (hoje o legado não valida no server — regressão a fechar). 16. XSS: `email_body` com `<script>` é sanitizado na renderização do e-mail.

---

## 4. Contrato de Tela

`prototipo-ui/contrato/notificacoes.contract.json` (neste pacote) — 6 âncoras, já instrumentadas no F1 com `data-contract`: `lista-modelos · cabecalho-modelo · editor-canal · tags-disponiveis · envio-automatico · previa` (+ `aviso-logo` condicional).

---

## 5. DS vivo — o que veio do bundle e o que segue do shell

**Do DS:** `Switch` (envio automático, via `acessos-ds.jsx`) · `Alert` (aviso do `{business_logo}`) · `Toast` (salvar/restaurar/teste) · `Tooltip` (selos do rail) · `Input` (CC/BCC).
**Do shell de propósito** (trocar só aqui criaria dois padrões): rail (`fnc-rail`), segmented de canal (`fnc-seg`), page header (`os-page-h`), botões (`os-btn`), campos com inserção de tag.
**Pedido de DS** (bloqueia conversão total): `Input`/`Textarea` do bundle não expõem `ref` nem `onFocus`/seleção — por isso assunto, SMS e WhatsApp seguem nativos. Se o DS aceitar `inputRef` + `onFocus`, a tela fica 100% DS.

---

## 6. Achados no legado (com o código na mão)

- **A1** Copy em inglês no seed indo pro cliente final (D1). Também há mistura: `sms_body` de `payment_paid` tem quebra de linha e indentação do PHP dentro do texto enviado.
- **A2** Sem validação server-side de `cc`/`bcc` — o `store()` grava o que chegar; o `email` só existe no input HTML.
- **A3** `store()` não valida chave: `template_data[qualquer_coisa]` cria linha com `template_for` arbitrário (mesma família do D1 de Acessos — POST cria registro sem whitelist).
- **A4** `email_body` é HTML livre salvo sem sanitização (TinyMCE no cliente não é defesa) — teste 16.
- **A5** `redirect()->back()` sem toast: o legado não confirma o salvamento.
- **A6** `AdminSidebarMenu.php:843-852` comenta que o endpoint exige `send_notification` — a permissão está certa; o menu, não: o item aparece fora do grupo de configurações.
- **A7** `new_booking` depende de módulo de agenda; se o módulo estiver desativado o modelo continua listado.

---

## 7. Arquivos deste pacote (o que aplicar e onde)

| Arquivo | Destino no repo |
|---|---|
| `notificacoes-page.jsx` · `notificacoes-page.css` | `prototipo-ui/cowork/notificacoes/` (build — SSOT do design) |
| `notificacoes.contract.json` | `prototipo-ui/contrato/` |
| este `.md` | `cowork-inbox/` (nunca dentro de `cowork/` — R1 do `cowork-ssot-guard`) |
| charter · casos | canon: `resources/js/Pages/NotificationTemplate/Index.charter.md` · `Index.casos.md` (escrever a partir das seções 1 e 2) |
| testes | `tests/Feature/NotificationTemplateTest.php` (+ unit do contador de SMS) |
