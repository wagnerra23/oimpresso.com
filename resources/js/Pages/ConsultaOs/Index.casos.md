---
id: resources-js-pages-consulta-os-index-casos
casos: Consulta pública de OS · /consulta-os
irmaos: Index.charter.md (lei) · Index.tsx (código) · tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php (defesa)
tecnica: Caso de uso = o que o cliente final faz + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do ERP que fala com o cliente final sem login — o que ela expõe e o que ela recusa não pode mudar sem alguém ver.
owner: wagner
related_us: [US-CONSULTA-001, US-CONSULTA-004]
last_run: "2026-10-27"
last_run_ci: "2026-10-27: UC-COS-07 e UC-COS-11 mudam (cutover do portal antigo do Repair); veredito pendente da lane PHP / Pest (Unit) (ci-sqlite-pest.list)"
---

# Casos de Uso & Aceite — Consulta pública de OS (`/consulta-os`)

> **Âncora:** os UC derivam do [charter](Index.charter.md) — Goals, Non-Goals e Anti-hooks — e do
> aceite da US-CONSULTA-001 no [SPEC](../../../../memory/requisitos/ConsultaOs/SPEC.md),
> **nunca do `Index.tsx`** ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Estado do módulo (2026-10-02):** a busca lê as folhas de OS reais do `Modules/Repair`
> (`RepairConsultaOsRepository`) — decisão [W] "Ligar o ConsultaOs ao Repair". O mock de 4 OS
> fixas saiu. O antigo `/repair-status` redireciona pra cá.
>
> **Multi-tenant:** a rota é pública e o `ScopeByBusiness` não age sem login; o único filtro é o
> critério de busca. O portal **não sabe a empresa do cliente** — um nº válido é procurado em todas
> as empresas, como no `/repair-status`. O UC-COS-09 documenta isso; quando [W] decidir como
> identificar a empresa, o assert dele inverte.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Status |
|----|-------------|------|--------|--------|
| UC-COS-01 | Portal abre sem login | must | Non-Goals "NÃO exige login" · Mission | 🧪 |
| UC-COS-02 | Busca pelo nº da OS mostra o status daquela OS | must | Goals "busca" + "exibe" | 🧪 |
| UC-COS-03 | OS não encontrada: resposta clara, sem pista | must | Goals "não encontrada claro" · UX "sem jargão" | 🧪 |
| UC-COS-04 | Payload é a whitelist — nada interno, financeiro ou do cliente | must `[T0]` | Non-Goals "NÃO expõe dados internos/financeiros" + "NÃO expõe dados do cliente" | 🧪 |
| UC-COS-05 | Sem critério válido não há busca nem lista | must `[T0]` | Non-Goals "NÃO lista todas as OS" · aceite US-CONSULTA-001 | 🧪 |
| UC-COS-06 | Só leitura: nada grava, nada edita | must | Non-Goals "NÃO permite editar/cancelar" · Anti-hooks "NÃO grava nada em GET" | 🧪 |
| UC-COS-07 | Não deixa enumerar OS | must `[T0]` | Anti-hooks "NÃO indexa/expõe OS por enumeração" | ⬜ |
| UC-COS-08 | Busca por nº da venda e por celular; série só estreita | must | Goals "busca por nº da OS, nº da venda ou celular" | 🧪 |
| UC-COS-09 | Busca cruza empresas — comportamento atual documentado | must `[T0]` | Pendências do charter "identificar a empresa" | 🧪 |
| UC-COS-10 | Celular desligado na config é recusado | must | Goals "celular só se a config ligar" | 🧪 |
| UC-COS-11 | `/repair-status` leva ao `/consulta-os` | must | charter §2026-10-02 (D-PORTAL) | ⬜ |
| UC-COS-12 | Mudar o status da OS avisa o cliente e o portal mostra o status novo | must `[T0]` | US-CONSULTA-004 (UC-FLX-24) | 🧪 |
| UC-COS-13 | Status com o nome que a empresa deu avisa quando está marcado | must | US-CONSULTA-004 (marca por status, [W] 2026-10-21) | 🧪 |
| UC-COS-14 | Transição pela FSM hoje não avisa nem muda o portal (limite) | must | US-CONSULTA-004 limite (2) | 🧪 |
| UC-COS-31 | Cliente que recusou WhatsApp não recebe o aviso da OS | must `[T0]` | US-CONSULTA-004 + LGPD opt-out | 🧪 |
| UC-COS-32 | OS com cliente de outra empresa não avisa ninguém | must `[T0]` | US-CONSULTA-004 + ADR 0093 | 🧪 |
| UC-COS-33 | Status sem marca não avisa, mesmo chamado `ready` | must | US-CONSULTA-004 (marca por status, [W] 2026-10-21) | 🧪 |

Teste de todos: [`ConsultaOsIndexContratoTest`](../../../../tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php),
lane `PHP / Pest (Unit)` do `ci.yml` (via `.github/ci-sqlite-pest.list`), schema mínimo em SQLite
in-memory com tenants fictícios 98 e 99. Nenhum status aqui é afirmação de verde: este PR não
rodou teste local ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-COS-01 · Portal abre sem login · `must`

- **Persona:** cliente final com o link `/consulta-os` (ou o antigo `/repair-status`) que a loja mandou.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/consulta-os` como visita Inertia (com
  `X-Inertia` e `X-Requested-With`) · Então recebe 200 e a tela `ConsultaOs/Index`, cuja única prop
  de página é `buscaPorCelular` (booleana) — nenhum dado de OS no render.
- **Fora do teste:** "sem AppShellV2/Sidebar" é propriedade do render no browser.
- **Status:** 🧪

## UC-COS-02 · Busca pelo nº da OS mostra o status daquela OS · `must`

- **Aceite:** Dado uma folha de OS com status, marca, modelo, aparelho, série e previsão · Quando o
  cliente busca o nº dela · Então recebe `found: true` e uma OS com esses valores · E uma outra OS
  devolve outro status (caso discriminante: um status fixo passaria com uma OS só).
- **Status:** 🧪

## UC-COS-03 · OS não encontrada: resposta clara, sem pista · `must`

- **Aceite:** Dado um número que não existe · Quando o cliente busca · Então recebe 404 com corpo
  exatamente `{"found": false}`. A frase amigável ("OS não encontrada") é da tela, não da API.
- **Status:** 🧪

## UC-COS-04 · Payload é a whitelist — nada interno, financeiro ou do cliente · `must` `[T0]`

- **Aceite:** Dado uma OS com custo estimado, senha do aparelho, defeitos, cliente com nome e
  documento, e um log automático de atividade com `defects` · Quando a busca devolve o payload ·
  Então as chaves de cada OS são exatamente `numero, marca, aparelho, modelo, serie, status,
  previsao_entrega, atividades`; as de cada atividade são `data, acao, por, nota, conclusao_de,
  conclusao_para` · E nenhum dos valores internos aparece em lugar nenhum do corpo.
- **Controle positivo:** o teste prova antes que o custo secreto está no banco.
- **Lista:** é a paridade com o `/repair-status` (D-PORTAL, 2026-10-02). Acrescentar campo é
  decisão [W].
- **Status:** 🧪

## UC-COS-05 · Sem critério válido não há busca nem lista · `must` `[T0]`

- **Aceite:** Dado um visitante · Quando busca sem tipo, sem número, com número vazio, com tipo fora
  da lista ou só com o nº de série · Então recebe 422 · E o repositório, chamado direto com tipo
  inválido ou número em branco, devolve `[]` sem consultar (defesa em profundidade — o portal não
  pode aceitar menos que o `/post-repair-status` endurecido no #8527).
- **Controle:** com critério válido o mesmo repositório acha a OS.
- **Status:** 🧪

## UC-COS-06 · Só leitura: nada grava, nada edita · `must`

- **Aceite:** Dado as rotas públicas do portal (`consulta-os.index`, `consulta-os.buscar`) · Então
  as duas existem e aceitam só `GET`/`HEAD`.
- **Status:** 🧪

## UC-COS-07 · Não deixa enumerar OS · `must` `[T0]`

- **Aceite:** Dado `consulta-os.index`, `consulta-os.buscar` e `repair-status`
  · Então as três têm `throttle:` · E um número com aspas, `%` ou mais de 20 caracteres é recusado
  com 422 · E, como controle, o formato real do nº de OS do Repair (`JS2026/0001`, com barra) é aceito.
- **Status:**⬜ (mudou em 2026-10-27; veredito da lane PHP / Pest (Unit) pendente)

## UC-COS-08 · Busca por nº da venda e por celular; série só estreita · `must`

- **Aceite:** Dado a busca por celular ligada · Quando o cliente busca pelo nº da venda ou pelo
  celular do cadastro · Então recebe a OS daquela venda/daquele celular · E o nº de série, quando
  informado, só reduz o resultado (série errada → 404).
- **Status:** 🧪

## UC-COS-09 · Busca cruza empresas — comportamento atual documentado · `must` `[T0]`

- **Aceite:** Dado o mesmo nº de OS em duas empresas (98 e 99) e uma terceira OS com outro nº ·
  Quando o cliente busca esse nº · Então a OS de outro nº **não** aparece · E as duas OS com o mesmo
  nº aparecem (é o que o `/repair-status` faz hoje).
- **Pendente [W]:** fechar exige identificar a empresa (subdomínio, slug ou parâmetro na URL). O
  ConsultaOs não tem esse mecanismo; criar rota nova ficou fora desta thread. Quando [W] decidir, o
  segundo assert inverte.
- **Status:** 🧪

## UC-COS-10 · Celular desligado na config é recusado · `must`

- **Aceite:** Dado `repair.enable_repair_check_using_mobile_num = false` · Quando o cliente busca por
  celular · Então recebe 422 e o repositório devolve `[]` · E os outros tipos seguem valendo.
- **Status:** 🧪

## UC-COS-11 · `/repair-status` leva ao `/consulta-os` · `must`

- **Aceite:** Dado um visitante sem sessão · Quando abre o antigo `/repair-status` · Então é
  redirecionado para `/consulta-os` · E a rota `post-repair-status` não existe mais (o portal antigo
  saiu no cutover de 2026-10-27; o `POST /post-repair-status` responde 404).
- **Status:** ⬜ (mudou em 2026-10-27; veredito da lane PHP / Pest (Unit) pendente)

## Cliente acompanha o status — catálogo UC-FLX-24 (US-CONSULTA-004)

> Fonte: catálogo `/fluxos` UC-FLX-24 ("a cada transição avisa o cliente e atualiza o portal") +
> o caminho real medido em 2026-10-20: `JobSheetController::update` → `RepairStatusChanged` →
> listener `NotifyRepairCustomer` (Whatsapp) → `SendWhatsappMessageJob`; o portal lê `status_id`.
> Testes: [`ClienteAcompanhaStatusContratoTest`](../../../../Modules/Repair/Tests/Feature/ClienteAcompanhaStatusContratoTest.php)
> (MySQL, tenant 98 × 99) e [`RepairFsmActionControllerTest`](../../../../Modules/Repair/Tests/Feature/RepairFsmActionControllerTest.php) (SQLite).
> Desde 2026-10-21 quem decide o aviso é a marca `repair_statuses.whatsapp_template_aviso`, configurada
> em `/repair/status` (decisão [W]). UC-COS-14 ainda trava um limite de hoje: a FSM (PR 2).

## UC-COS-12 · Mudar o status da OS avisa o cliente e o portal mostra o status novo · `must` `[T0]`

- **Aceite:** Dado uma OS da empresa 98 com cliente com celular, um número de WhatsApp da empresa
  que atende aviso de OS, e uma OS da empresa 99 com cliente e número próprios · Quando o status
  da OS da 98 muda na edição (para um status que o aviso reconhece) · Então sai **um** job de
  WhatsApp, pelo número da 98, para o celular do cliente da 98 · E `/consulta-os/buscar` pelo nº
  da OS devolve só essa OS, com o status novo, sem nº nem nome da OS da 99.
- **Status:** 🧪

## UC-COS-13 · Status com o nome que a empresa deu avisa quando está marcado · `must`

- **Aceite:** Dado um status "Pronto para retirada" marcado com a mensagem de pronto
  (`whatsapp_template_aviso = 'ready'`) · Quando a OS vai para ele na edição · Então sai um job de
  WhatsApp com o template de pronto, pelo número da 98, para o celular do cliente · E o portal
  mostra "Pronto para retirada".
- **Histórico:** até 2026-10-21 este UC travava o limite oposto: só os nomes literais
  `ready`/`waiting_parts` avisavam, e "Pronto para retirada" não avisava. `is_completed_status` não
  virou o sinal porque vale "Pronto" no kanban e "Entregue" no `startPipeline`.
- **Status:** 🧪

## UC-COS-14 · Transição pela FSM hoje não avisa nem muda o portal · `must`

- **Aceite (estado de hoje):** Dado uma OS no pipeline FSM com `status_id` definido · Quando uma
  ação da FSM move a etapa (`RepairFsmActionController::execute`) · Então a etapa muda · E
  nenhum `RepairStatusChanged` é disparado · E o `status_id` que o portal lê fica igual.
- **Follow-up:** evento genérico de transição da FSM (fora desta US).
- **Status:** 🧪

## UC-COS-31 · Cliente que recusou WhatsApp não recebe o aviso da OS · `must` `[T0]`

- **Aceite:** Dado um número de WhatsApp da empresa 98 que atende aviso de OS e dois clientes da
  98 com celular — um com `whatsapp_consent = false`, outro sem resposta (`null`) · Quando a OS de
  cada um vai para `ready` · Então o de opt-out explícito não recebe job nenhum · E o sem resposta
  recebe um, pelo número da 98 (`Contact::canReceiveWhatsappNotification`, LGPD: null permite,
  false bloqueia).
- **Status:** 🧪

## UC-COS-32 · OS com cliente de outra empresa não avisa ninguém · `must` `[T0]`

- **Aceite:** Dado uma OS da empresa 98 cujo `contact_id` aponta para um cliente da 99 (com
  celular e consentimento) · Quando o status vai para `ready` · Então nenhum job sai (o contato é
  buscado com `business_id` da OS, ADR 0093) · E o cliente da própria 98, no mesmo cenário,
  recebe um job.
- **Status:** 🧪

## UC-COS-33 · Status sem marca não avisa, mesmo chamado `ready` · `must`

- **Aceite:** Dado um status com o nome literal `ready` e sem marca, e outro marcado com
  aguardando peças · Quando a OS vai para o primeiro · Então nenhum job sai · E quando vai para o
  segundo sai um job com o template de aguardando peças.
- **Status:** 🧪

---

## Fora deste arquivo (sem contrato verificável ainda)

- [BACKLOG] Copy da tela em PT-BR e sem jargão técnico — o charter pede, mas não fixa texto literal
  e não há spec Playwright desta tela. Sem texto contratado, um teste leria o `.tsx`.
- [BACKLOG] "Sem CTA loud" (pedido da ficha do playbook) — sem fonte no charter nem em PT.
- [BACKLOG] Captcha (resto da US-CONSULTA-001).
