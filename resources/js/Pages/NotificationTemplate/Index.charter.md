---
tela: NotificationTemplate/Index
rota: /notification-templates
tier: B
persona: Wagner (escritório 1440px) · leitura por Eliana (financeiro)
arquetipo: configuração (rail + painel)
fonte_f1: "n/a — build do F1 (notificacoes-page.jsx/.css) não veio no pacote; pedir export ao Cowork antes da onda Inertia"
contrato: "n/a — notificacoes.contract.json entra junto com a Page Inertia (contrato:check exige .tsx no alvo; medido 2026-08-19)"
---

# Modelos de notificação — charter

## Missão
Deixar o dono do negócio escrever, **uma vez**, o que o sistema vai dizer ao cliente e ao fornecedor em cada evento — por e-mail, SMS e WhatsApp — e decidir o que sai sozinho.

## Goals
- Um lugar só para os modelos dos 3 grupos (Notificações · Cliente · Fornecedor), incluindo os que módulos injetam.
- Deixar óbvio, antes de salvar, **como o texto vai chegar**: prévia por canal com as tags resolvidas.
- Deixar óbvio **o que sai sozinho** e o que depende de alguém clicar.
- Impedir erro silencioso: tag que o modelo não conhece, canal vazio, SMS que virou 2 segmentos.

## Non-goals
Editor visual arrastável de e-mail · envio em massa · histórico de envios (é do Atendimento) · modelo por cliente · anexos por modelo · lista de destinatários em CC.

## Dados (do `NotificationTemplateController@index`)
Três coleções: `generalNotifications()`, `customerNotifications()`, `supplierNotifications()`, cada item com `name` + `extra_tags` (matriz de grupos) e, via `__getTemplateDetails()`, as 9 colunas: `subject · email_body · sms_body · whatsapp_text · auto_send · auto_send_sms · auto_send_wa_notif · cc · bcc`.
Módulos injetam modelos por `moduleUtil->getModuleData('notification_list', ['notification_for' => 'customer'|'supplier'])` — **a tela não pode assumir lista fixa**.

## Regras de domínio
- **R1** Um registro por (`business_id`, `template_for`) — `updateOrCreate`. Salvar é em **lote**: `template_data[<key>][…]` de todos os modelos num POST.
- **R2** `auto_send*` chega como checkbox: ausente ⇒ `0`. Nunca inferir "manteve".
- **R3** Permissão única `send_notification`, no índice e no store.
- **R4** Envio automático é oferecido só em `new_sale` e `payment_reminder`.
- **R5** `send_ledger` é só e-mail (o Blade esconde SMS/WhatsApp).
- **R6** `{business_logo}` só é impresso no e-mail.
- **R7** Tag fora das `extra_tags` do modelo sai literal — a UI **avisa**, não bloqueia.
- **R8** `cc`/`bcc` são um endereço cada (input `email` do legado), não lista.
- **R9** Corpo vazio no canal ⇒ nada é enviado por aquele canal.
- **R10** O lembrete automático roda pelo comando `AutoSendPaymentReminder` (agendado), não no request.
- **R11** SMS com acento cai de 160 para 70 caracteres por segmento (GSM-7 → UCS-2) — muda o custo.

## Estados
`carregando` (skeleton do rail) · `sem permissão` (403, EmptyState `no-perm`) · `modelo vazio` (selo `vazio`, não envia) · `alterado não salvo` (ponto âmbar + contador no header) · `tag desconhecida` (aviso âmbar no rodapé do campo) · `canal indisponível` (aba desabilitada, R5).

## Contrato visual
Âncoras `data-contract`: `lista-modelos · cabecalho-modelo · editor-canal · tags-disponiveis · envio-automatico · previa` (+ `aviso-logo` condicional), nessa ordem top→down.

> ⚠️ O `notificacoes.contract.json` do pacote F1 **não foi versionado nesta onda**: medido em 2026-08-19, `contrato:check` reprova com `nenhum .tsx/.ts no alvo` (a tela ainda é Blade) e `--map --check` reprova porque o `fonte` aponta para um build do Cowork que não veio. Como o CI itera **todos** os `*.contract.json` com `exit $rc`, versioná-lo agora deixaria o job `Preflight + contratos ativos` vermelho em todo PR que toca `.tsx`. Ele entra junto com a Page Inertia.

## DS
Do bundle: `Switch · Alert · Toast · Tooltip · Input`. Do shell, de propósito: rail, segmented de canal, page header, botões. Pendência de DS: `Input`/`Textarea` sem `ref`/`onFocus` — por isso assunto/SMS/WhatsApp seguem nativos (precisam de posição do cursor para inserir tag).

## Anti-patterns (desta tela)
Sem inglês na UI (o seed legado é o próprio erro) · sem emoji · sem modal full-screen para editar modelo · sem `rounded-xl+` · nenhuma cor fora de token · nunca bloquear o salvamento por causa de tag desconhecida · nunca mostrar enum cru (`template_for`) ao operador.

## Tests
`tests/Feature/NotificationTemplateTest.php` — permissão (2) · índice com as 9 chaves · create/update sem duplicar · lote · checkbox ausente = 0 · isolamento multi-tenant · whitelist de `template_for` · validação de `cc` · seed PT-BR + migration preservando modelo editado · canal vazio não envia · auto_send desligado não dispara · tag desconhecida no round-trip · sanitização de `email_body`. Unit: contador de segmentos SMS (160 sem acento, 70 com).

## Pendências [W]
D1 seed PT-BR como padrão (autorizado 2026-08-19; migration escrita) · D2 editor mínimo em vez de TinyMCE · D3 rota de teste de envio · D4 manter envio automático só nos 2 modelos · D5 backend também ignorar SMS/WhatsApp em `send_ledger`.
