---
id: resources-js-pages-notificationtemplate-index-casos
casos: Modelos de notificação · /notification-templates
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o que o sistema diz ao cliente não muda no refactor da tela.
owner: wagner
last_run: "2026-08-19"
---

# Casos de Uso & Aceite — Modelos de notificação

> Origem: pacote F1 do Cowork (2026-08-19), autorizado por [W]. `R` = regra do charter irmão.
>
> ⚖️ **Onde estes UC rodam, e com que força:** `tests/Feature/NotificationTemplateTest.php`
> (Pest, lane MySQL). A tela hoje é **Blade legada** (`resources/views/notification_template/`) —
> a Page Inertia ainda não existe. Por isso o comportamento de interação de tela está no
> **Backlog no fim deste arquivo**, como prosa sem id: virar `UC-` sem teste que o cite seria
> afirmar cobertura que não existe (e o casos-gate G-2 reprova, medido 2026-08-19).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-NOT-01 · Sem permissão não abre nem grava
- **Persona:** operador sem `send_notification` — não pode ler nem alterar o que o sistema fala com o cliente.
- **Aceite:** Dado um usuário sem `send_notification` · Quando abre `/notification-templates` **ou** faz POST no store · Então recebe **403** e nenhum modelo é renderizado ou gravado.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-01 · nega o indice sem a permissao send_notification` + `UC-NOT-01 · nega o store sem a permissao send_notification`.
- **Regra:** R3.
- **Status: 🧪** — feature test HTTP nos dois verbos; ✅ quando `casos:results` regravar o manifesto.

---

## UC-NOT-02 · Abrir a tela entrega os 3 grupos com as colunas do modelo
- **Persona:** Wagner abre a tela e precisa ver todos os modelos, não um subconjunto.
- **Aceite:** Dado um usuário com `send_notification` · Quando abre a tela · Então recebe os 3 grupos (`general` · `customer` · `supplier`) e cada modelo traz as colunas `subject · email_body · sms_body · whatsapp_text · auto_send · auto_send_sms · auto_send_wa_notif · cc · bcc`.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-02 · entrega os 3 grupos com as colunas de cada modelo`.
- **Regra:** R1.
- **Status: 🧪** — assert sobre as 3 coleções da view + colunas do `new_sale`.

---

## UC-NOT-04 · Editar e salvar grava a linha do modelo
- **Persona:** Wagner ajusta o assunto da venda e espera que persista.
- **Aceite:** Dado que altero o assunto de `new_sale` · Quando salvo · Então a linha é gravada e ao reler o novo assunto aparece.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-04 · cria o registro quando o modelo ainda nao existe` + `UC-NOT-04 · atualiza sem duplicar a linha`.
- **Regra:** R1 (um registro por `business_id` + `template_for`, via `updateOrCreate`).
- **Status: 🧪** — cobre criação e atualização idempotente.

---

## UC-NOT-05 · Salvar em lote grava todos num POST só
- **Persona:** Wagner mexeu em 3 modelos e clica Salvar uma vez.
- **Aceite:** Dado que alterei 3 modelos · Quando envio um único POST com `template_data[<key>]` dos 3 · Então os 3 são gravados.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-05 · grava varios modelos num unico POST`.
- **Regra:** R1.
- **Status: 🧪**

---

## UC-NOT-16 · Ligar o envio automático grava auto_send=1
- **Persona:** Wagner liga o e-mail automático da venda.
- **Aceite:** Dado `new_sale` · Quando ligo o e-mail automático e salvo · Então `auto_send=1` é gravado.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-16 · liga o envio automatico e grava auto_send=1`.
- **Regra:** R4.
- **Status: 🧪**

---

## UC-NOT-17 · Desligar os três canais grava zero nos três
- **Persona:** Wagner desliga tudo e o sistema não pode "manter o anterior".
- **Aceite:** Dado `payment_reminder` com os 3 automáticos ligados · Quando desligo todos e salvo (checkbox ausente no POST) · Então as 3 colunas gravam `0`.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-17 · zera os tres automaticos quando a checkbox nao vem no POST`.
- **Regra:** R2 — checkbox ausente ⇒ `0`, nunca "manteve".
- **Status: 🧪** — é a regra que mais fácil regride num refactor de form.

---

## UC-NOT-10 · Tag desconhecida sobrevive ao round-trip
- **Persona:** o operador digitou `{tag_que_nao_existe}`; o sistema avisa mas não apaga o texto dele.
- **Aceite:** Dado um assunto com tag fora das `extra_tags` · Quando salvo e releio · Então o texto volta **literal**, sem remoção nem escape.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-10 · preserva tag desconhecida no round-trip`.
- **Regra:** R7 — a UI avisa, não bloqueia.
- **Status: 🧪**

---

## UC-NOT-26 · Um negócio não altera o modelo de outro
- **Persona:** Tier 0 — isolamento multi-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado o modelo `new_sale` do negócio B · Quando o negócio A salva `new_sale` · Então a linha de B fica intacta (o `updateOrCreate` é escopado por `business_id`).
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-26 · nao deixa um negocio alterar o modelo de outro`.
- **Status: 🧪** — cross-tenant explícito, tenant fictício 98 × 99 (ADR 0358).

---

## UC-NOT-24 · CC inválido é recusado no servidor
- **Persona:** um POST fora da tela (ou o `type=email` burlado) não pode gravar endereço inválido.
- **Aceite:** Dado `cc = "nao-e-email"` · Quando salvo · Então o servidor recusa com erro de validação em `template_data.new_sale.cc` e o modelo não grava o endereço.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-24 · recusa cc invalido no servidor`.
- **Regra:** R8 — um endereço por campo.
- **Patch:** P3.
- **Status: ⬜** — hoje **falha**: só o `type="email"` do HTML protege. Vira 🧪 quando o P3 landar.

---

## UC-NOT-27 · Chave de modelo desconhecida não vira linha
- **Persona:** um POST forjado com `template_data[modelo_inventado]` não pode poluir a tabela do negócio.
- **Aceite:** Dado `template_data` com uma chave fora das que a tela oferece · Quando salvo · Então nenhuma linha com aquele `template_for` é criada.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-27 · ignora chave de modelo desconhecida no POST`.
- **Patch:** P2.
- **Status: ⬜** — hoje **falha**: o `store()` grava qualquer chave. Vira 🧪 quando o P2 landar.

---

## UC-NOT-28 · Negócio novo nasce com os modelos em português
- **Persona:** cliente novo não pode receber e-mail em inglês.
- **Aceite:** Dado um negócio recém-criado · Quando o seed roda (`BusinessUtil::createDefaultNotificationTemplates`) · Então `new_sale` tem assunto e corpo em PT-BR e `whatsapp_text` preenchido.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-28 · semeia os modelos em portugues para negocio novo`.
- **Patch:** P1.
- **Status: ⬜** — hoje **falha**: o seed está em inglês e não preenche `whatsapp_text`. Vira 🧪 quando o P1 landar.

---

## UC-NOT-29 · A tradução preserva o modelo que o negócio editou
- **Persona:** quem já reescreveu o próprio texto não pode perdê-lo na migration.
- **Aceite:** Dado um modelo cujo assunto/corpo o negócio editou · Quando a migration de tradução roda · Então o texto do negócio fica **intacto** (só o que ainda é o seed inglês é reescrito).
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-29 · a traducao preserva o modelo que o negocio editou`.
- **Patch:** P1 (migration `2026_08_19_000000_traduzir_notification_templates_pt_br`).
- **Status: ⬜** — o teste sobe junto com a migration.

---

## UC-NOT-30 · Script no corpo não chega ao e-mail
- **Persona:** o corpo é HTML livre; um `<script>` gravado (por operador ou POST forjado) não pode sair no e-mail do cliente.
- **Aceite:** Dado `email_body` com `<p>ok</p><script>alert(1)</script>` · Quando o e-mail é montado · Então o conteúdo mantém `ok` e **não** contém `<script`.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-30 · sanitiza script no corpo do e-mail ao montar a mensagem`.
- **Patch:** P4 — sanitização na **saída**, em `CustomerNotification::toMail()` + `SupplierNotification::toMail()` (os 2 pontos onde os 4 produtores de e-mail convergem), não em cada produtor.
- **Status: ⬜** — hoje **falha**: nada sanitiza. Vira 🧪 quando o P4 landar.

---

## UC-NOT-25 · Lembrete automático só dispara com auto_send ligado
- **Persona:** cliente não pode receber cobrança de um modelo que o dono desligou.
- **Aceite:** Dado `payment_reminder` com `auto_send=0` · Quando o comando agendado roda · Então nenhum e-mail é disparado.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-25 · o lembrete automatico nao dispara com auto_send desligado`.
- **Regra:** R10 — o disparo é do `AutoSendPaymentReminder`, não do request.
- **Status: ⬜** — precisa de fatura + contato de exemplo no tenant de teste; o teste está `todo()` com a razão declarada no arquivo.

---

## UC-NOT-18 · Canal com corpo vazio não envia
- **Persona:** modelo sem corpo de e-mail não pode disparar e-mail em branco.
- **Aceite:** Dado um modelo com `email_body` vazio · Quando o envio automático roda · Então nada sai por e-mail.
- **Teste:** `NotificationTemplateTest.php` — `UC-NOT-18 · nao envia pelo canal cujo corpo esta vazio`.
- **Regra:** R9.
- **Status: ⬜** — mesma dependência de fixture do UC-NOT-25; `todo()` com razão declarada.

---

## Backlog — comportamento pretendido, ainda sem teste que o prove

> Estes itens descrevem a **Page Inertia que ainda não existe** (a tela hoje é Blade). Ficam
> como prosa SEM id de UC de propósito: um `UC-` declarado exige teste que o cite (casos-gate
> G-2), e satisfazer esse gate com uma menção em comentário seria cobertura cosmética. Cada
> bullet vira `UC-NOT-NN` no PR que trouxer o teste (e2e Playwright ou Pest Browser) que o exercite.

- [BACKLOG] Trocar de modelo no rail reseta o canal para E-mail e o editor para Visual.
- [BACKLOG] "Descartar alterações" devolve todos os campos ao último estado salvo e zera o contador do header.
- [BACKLOG] "Restaurar padrão desta" volta ao texto de fábrica sem gravar nada até o operador salvar.
- [BACKLOG] Clicar um chip de tag insere na posição do cursor e deixa o cursor depois da tag.
- [BACKLOG] Inserir tag no corpo atualiza a prévia com o valor de exemplo correspondente.
- [BACKLOG] Grupo Contato colapsado expande os 10 chips `{contact_custom_field_1..10}` sob demanda.
- [BACKLOG] Rodapé do SMS mostra "2 SMS · 160 por segmento" para 200 caracteres sem acento (R11).
- [BACKLOG] Rodapé do SMS cai para 70 por segmento quando há acento, e diz que é por causa do acento (R11, GSM-7 → UCS-2).
- [BACKLOG] `send_ledger` desabilita as abas SMS e WhatsApp na UI (R5) — e o backend também passa a ignorá-las (D5, decisão [W] pendente).
- [BACKLOG] Grupo Fornecedor exibe a faixa avisando que `{business_logo}` só é impresso no e-mail (R6).
- [BACKLOG] Busca no rail filtra os modelos e esconde grupo sem resultado.
- [BACKLOG] Atalho `/` foca a busca; `Esc` limpa o texto e devolve o foco.
- [BACKLOG] Alternar Visual ↔ HTML preserva o conteúdo do corpo nos dois sentidos.
- [BACKLOG] Modelo injetado por módulo via `notification_list` aparece no grupo com as `extra_tags` declaradas (R1 — a tela não assume lista fixa).
- [BACKLOG] "Enviar teste pra mim" manda para o e-mail do usuário logado (nunca do request) e a segunda tentativa imediata é barrada por `throttle:6,1` (P6).
