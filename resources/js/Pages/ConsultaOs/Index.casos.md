---
id: resources-js-pages-consulta-os-index-casos
casos: Consulta pública de OS · /consulta-os/{empresa}
irmaos: Index.charter.md (lei) · Index.tsx (código) · tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php (defesa)
tecnica: Caso de uso = o que o cliente final faz + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do ERP que fala com o cliente final sem login — o que ela expõe e o que ela recusa não pode mudar sem alguém ver.
owner: wagner
related_us: [US-CONSULTA-001, US-CONSULTA-004]
last_run: "2026-11-17"
last_run_ci: "2026-11-17: ci.yml run 44088901109 (PHP / Pest (Unit)) — ConsultaOsIndexContratoTest 11 passed · 0 skipped · 75 assertions; verticais-pest run 44088901247 — ClienteAcompanhaStatusContratoTest 5 passed · 0 skipped · 34 assertions; modules-pest run 44082879187 (Pest Repair) — UC-COS-14"
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
> **Multi-tenant (decisão [W] 2026-11-17 — "empresa na URL, celular só com série"):** a rota é
> pública e a empresa vem do código na URL (`/consulta-os/{codigo}`, `EmpresaPublica`). A busca
> filtra `repair_job_sheets`, `transactions` e `contacts` por ela. Até 2026-11-17 um nº válido era
> procurado em todas as empresas e o UC-COS-09 travava isso; o assert inverteu. `/consulta-os` sem
> código só mostra o aviso (UC-COS-61). Os UC que mudaram com isso (01, 08, 09, 38) voltam a 🧪 até
> a lane rodar.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Status |
|----|-------------|------|--------|--------|
| UC-COS-01 | Portal abre sem login | must | Non-Goals "NÃO exige login" · Mission | 🧪 |
| UC-COS-02 | Busca pelo nº da OS mostra o status daquela OS | must | Goals "busca" + "exibe" | ✅ |
| UC-COS-03 | OS não encontrada: resposta clara, sem pista | must | Goals "não encontrada claro" · UX "sem jargão" | ✅ |
| UC-COS-04 | Payload é a whitelist — nada interno, financeiro ou do cliente | must `[T0]` | Non-Goals "NÃO expõe dados internos/financeiros" + "NÃO expõe dados do cliente" | ✅ |
| UC-COS-05 | Sem critério válido não há busca nem lista | must `[T0]` | Non-Goals "NÃO lista todas as OS" · aceite US-CONSULTA-001 | ✅ |
| UC-COS-06 | Só leitura: nada grava, nada edita | must | Non-Goals "NÃO permite editar/cancelar" · Anti-hooks "NÃO grava dado de negócio nem altera a OS em GET" | ✅ |
| UC-COS-07 | Não deixa enumerar OS | must `[T0]` | Anti-hooks "NÃO facilita enumerar OS" | ✅ |
| UC-COS-08 | Busca por nº da venda e por celular com série; série estreita | must | Goals "busca por nº da OS, nº da venda ou celular" | 🧪 |
| UC-COS-09 | Mesmo nº de OS em duas empresas: cada link só vê a sua | must `[T0]` | decisão [W] 2026-11-17 "empresa na URL" · Non-Goals "NÃO procura OS de outra empresa" | 🧪 |
| UC-COS-10 | Celular desligado na config é recusado | must | Goals "celular só se a config ligar" | ✅ |
| UC-COS-11 | `/repair-status` leva ao `/consulta-os` | must | charter §2026-10-02 (D-PORTAL) | ✅ |
| UC-COS-12 | Mudar o status da OS avisa o cliente e o portal mostra o status novo | must `[T0]` | US-CONSULTA-004 (UC-FLX-24) | ✅ |
| UC-COS-13 | Status com o nome que a empresa deu avisa quando está marcado | must | US-CONSULTA-004 (marca por status, [W] 2026-10-21) | ✅ |
| UC-COS-14 | Transição pela FSM hoje não avisa nem muda o portal (limite) | must | US-CONSULTA-004 limite (2) | 🧪 |
| UC-COS-31 | Cliente que recusou WhatsApp não recebe o aviso da OS | must `[T0]` | US-CONSULTA-004 + LGPD opt-out | ✅ |
| UC-COS-32 | OS com cliente de outra empresa não avisa ninguém | must `[T0]` | US-CONSULTA-004 + ADR 0093 | ✅ |
| UC-COS-33 | Status sem marca não avisa, mesmo chamado `ready` | must | US-CONSULTA-004 (marca por status, [W] 2026-10-21) | ✅ |
| UC-COS-34 | Resposta traz no máximo 20 OS | must `[T0]` | Non-Goals "NÃO lista todas as OS (… no máximo 20)" | 🧪 |
| UC-COS-35 | 31ª busca do mesmo IP no minuto recebe 429 | must `[T0]` | Anti-hooks "NÃO facilita enumerar OS" · SPEC US-CONSULTA-001 "rate-limit por IP (`throttle:30,1`)" | 🧪 |
| UC-COS-36 | Cada busca deixa log com IP truncado e número redigido | must | Anti-hooks "NÃO grava dado de negócio nem altera a OS em GET" (só o log redigido) · `Config/retention.php` `consulta_os_logs` | 🧪 |
| UC-COS-37 | Falha interna: 500 só com `found=false`, sem detalhe técnico | must | UX "sem jargão técnico" · UC-COS-03 | 🧪 |
| UC-COS-38 | Falha do servidor na busca: a tela avisa e não diz "não encontrada" | must | UX "sem jargão técnico" · UC-COS-37 | 🧪 |
| UC-COS-39 | Muitas buscas (429): a tela pede para esperar, e o 500 tem mensagem própria | must | UX "sem jargão técnico" · UC-COS-35 · UC-COS-38 | 🧪 |
| UC-COS-59 | Nº da venda: cada link só acha a venda da própria empresa | must `[T0]` | decisão [W] 2026-11-17 "empresa na URL" · ADR 0093 | 🧪 |
| UC-COS-60 | Celular só com nº de série, e só na empresa do link | must `[T0]` | decisão [W] 2026-11-17 "celular só com série" · ADR 0093 | 🧪 |
| UC-COS-61 | Sem código de empresa não há busca; código desconhecido dá 404 | must `[T0]` | decisão [W] 2026-11-17 "empresa na URL" | 🧪 |
| UC-COS-62 | Quem mexeu na OS aparece como primeiro nome + inicial do sobrenome | must | decisão [W] 2026-11-17 "primeiro nome + inicial" · Non-Goals "NÃO expõe o nome completo de funcionário" | 🧪 |
| UC-COS-63 | O link da empresa nasce na 1ª vez que é pedido e depois é sempre o mesmo | must | decisão [W] 2026-11-18 "o link nasce sozinho" | 🧪 |
| UC-COS-64 | O link de uma empresa nunca é o de outra, nem abre a outra | must `[T0]` | decisão [W] 2026-11-17 "empresa na URL" · ADR 0093 | 🧪 |

Teste de todos: [`ConsultaOsIndexContratoTest`](../../../../tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php),
lane `PHP / Pest (Unit)` do `ci.yml` (via `.github/ci-sqlite-pest.list`), schema mínimo em SQLite
in-memory com tenants fictícios 98 e 99. Os ✅ vêm do manifesto `scripts/casos-test-results.json`
(G-7) e dos runs citados no `last_run_ci`; nenhum teste rodou local
([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-COS-01 · Portal abre sem login · `must`

- **Persona:** cliente final com o link `/consulta-os/{codigo}` que a loja mandou.
- **Aceite:** Dado um visitante sem sessão · Quando abre o link da empresa como visita Inertia (com
  `X-Inertia` e `X-Requested-With`) · Então recebe 200 e a tela `ConsultaOs/Index`, com as props
  `buscaPorCelular` (booleana) e `empresa` (o código da URL) — nenhum dado de OS no render.
- **Fora do teste:** "sem AppShellV2/Sidebar" é propriedade do render no browser.
- **Status:** 🧪

## UC-COS-02 · Busca pelo nº da OS mostra o status daquela OS · `must`

- **Aceite:** Dado uma folha de OS com status, marca, modelo, aparelho, série e previsão · Quando o
  cliente busca o nº dela · Então recebe `found: true` e uma OS com esses valores · E uma outra OS
  devolve outro status (caso discriminante: um status fixo passaria com uma OS só).
- **Status:** ✅

## UC-COS-03 · OS não encontrada: resposta clara, sem pista · `must`

- **Aceite:** Dado um número que não existe · Quando o cliente busca · Então recebe 404 com corpo
  exatamente `{"found": false}`. A frase amigável ("OS não encontrada") é da tela, não da API.
- **Status:** ✅

## UC-COS-04 · Payload é a whitelist — nada interno, financeiro ou do cliente · `must` `[T0]`

- **Aceite:** Dado uma OS com custo estimado, senha do aparelho, defeitos, cliente com nome e
  documento, e um log automático de atividade com `defects` · Quando a busca devolve o payload ·
  Então as chaves de cada OS são exatamente `numero, marca, aparelho, modelo, serie, status,
  previsao_entrega, atividades`; as de cada atividade são `data, acao, por, nota, conclusao_de,
  conclusao_para` · E nenhum dos valores internos aparece em lugar nenhum do corpo.
- **Controle positivo:** o teste prova antes que o custo secreto está no banco.
- **Lista:** é a paridade com o `/repair-status` (D-PORTAL, 2026-10-02). Acrescentar campo é
  decisão [W].
- **Status:** ✅

## UC-COS-05 · Sem critério válido não há busca nem lista · `must` `[T0]`

- **Aceite:** Dado um visitante · Quando busca sem tipo, sem número, com número vazio, com tipo fora
  da lista ou só com o nº de série · Então recebe 422 · E o repositório, chamado direto com tipo
  inválido ou número em branco, devolve `[]` sem consultar (defesa em profundidade — o portal não
  pode aceitar menos que o `/post-repair-status` endurecido no #8527).
- **Controle:** com critério válido o mesmo repositório acha a OS.
- **Status:** ✅

## UC-COS-06 · Só leitura: nada grava, nada edita · `must`

- **Aceite:** Dado as rotas públicas do portal (`consulta-os.index`, `consulta-os.buscar`) · Então
  as duas existem e aceitam só `GET`/`HEAD` · E uma busca que acha (por nº da OS, da venda e
  celular), uma que não acha e uma recusada não mudam a contagem de `repair_job_sheets`,
  `activity_log`, `transactions` e `contacts` (Anti-hook "NÃO grava dado de negócio nem altera a OS
  em GET"; o log de auditoria redigido e o contador do throttle não ficam nessas tabelas).
- **Anti-vácuo:** o teste confere antes que as tabelas têm linhas.
- **Status:** ✅

## UC-COS-07 · Não deixa enumerar OS · `must` `[T0]`

- **Aceite:** Dado `consulta-os.index`, `consulta-os.buscar` e `repair-status`
  · Então as três têm `throttle:` · E um número com aspas, `%` ou mais de 20 caracteres é recusado
  com 422 · E, como controle, o formato real do nº de OS do Repair (`JS2026/0001`, com barra) é aceito.
- **Status:** ✅

## UC-COS-08 · Busca por nº da venda e por celular com série; série estreita · `must`

- **Aceite:** Dado a busca por celular ligada · Quando o cliente busca pelo nº da venda, ou pelo
  celular do cadastro junto com o nº de série · Então recebe a OS daquela venda/daquele aparelho · E
  nos outros tipos o nº de série, quando informado, só reduz o resultado (série errada → 404).
- **Status:** 🧪

## UC-COS-09 · Mesmo nº de OS em duas empresas: cada link só vê a sua · `must` `[T0]`

- **Aceite:** Dado o mesmo nº de OS nas empresas 98 e 99, com status diferentes · Quando o cliente
  busca esse nº pelo link da 98 · Então vê só a OS da 98 (e nada da 99) · E pelo link da 99 vê só a
  da 99 · E uma OS que só existe na 99 dá 404 pelo link da 98.
- **Histórico:** até 2026-11-17 este caso travava o contrário (as duas OS voltavam juntas). Decisão
  [W] 2026-11-17: "empresa na URL, celular só com série".
- **Status:** 🧪

## UC-COS-10 · Celular desligado na config é recusado · `must`

- **Aceite:** Dado `repair.enable_repair_check_using_mobile_num = false` · Quando o cliente busca por
  celular · Então recebe 422 e o repositório devolve `[]` · E os outros tipos seguem valendo.
- **Status:** ✅

## UC-COS-11 · `/repair-status` leva ao `/consulta-os` · `must`

- **Aceite:** Dado um visitante sem sessão · Quando abre o antigo `/repair-status` · Então é
  redirecionado para `/consulta-os` · E a rota `post-repair-status` não existe mais (o portal antigo
  saiu no cutover de 2026-10-27; o `POST /post-repair-status` responde 404).
- **Status:** ✅

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
  WhatsApp, pelo número da 98, para o celular do cliente da 98 · E `/consulta-os/{codigo da 98}/buscar` pelo nº
  da OS devolve só essa OS, com o status novo, sem nº nem nome da OS da 99.
- **Status:** ✅

## UC-COS-13 · Status com o nome que a empresa deu avisa quando está marcado · `must`

- **Aceite:** Dado um status "Pronto para retirada" marcado com a mensagem de pronto
  (`whatsapp_template_aviso = 'ready'`) · Quando a OS vai para ele na edição · Então sai um job de
  WhatsApp com o template de pronto, pelo número da 98, para o celular do cliente · E o portal
  mostra "Pronto para retirada".
- **Histórico:** até 2026-10-21 este UC travava o limite oposto: só os nomes literais
  `ready`/`waiting_parts` avisavam, e "Pronto para retirada" não avisava. `is_completed_status` não
  virou o sinal porque vale "Pronto" no kanban e "Entregue" no `startPipeline`.
- **Status:** ✅

## UC-COS-14 · Transição pela FSM hoje não avisa nem muda o portal · `must`

- **Aceite (estado de hoje):** Dado uma OS no pipeline FSM com `status_id` definido · Quando uma
  ação da FSM move a etapa (`RepairFsmActionController::execute`) · Então a etapa muda · E
  nenhum `RepairStatusChanged` é disparado · E o `status_id` que o portal lê fica igual.
- **Follow-up:** evento genérico de transição da FSM (fora desta US).
- **Por que segue 🧪:** o teste passa na matriz `modules-pest.yml` (job Pest Repair), mas essa
  lane não emite JUnit (`--log-junit` + artifact), então o `junit-lanes.mjs` não a lista e o
  `casos-results-publish` nunca leva o veredito ao manifesto (medido 2026-11-17: UC-COS-14 ausente
  em `scripts/casos-test-results.json`). O ✅ depende de a lane passar a publicar JUnit.
- **Status:** 🧪

## UC-COS-31 · Cliente que recusou WhatsApp não recebe o aviso da OS · `must` `[T0]`

- **Aceite:** Dado um número de WhatsApp da empresa 98 que atende aviso de OS e dois clientes da
  98 com celular — um com `whatsapp_consent = false`, outro sem resposta (`null`) · Quando a OS de
  cada um vai para `ready` · Então o de opt-out explícito não recebe job nenhum · E o sem resposta
  recebe um, pelo número da 98 (`Contact::canReceiveWhatsappNotification`, LGPD: null permite,
  false bloqueia).
- **Status:** ✅

## UC-COS-32 · OS com cliente de outra empresa não avisa ninguém · `must` `[T0]`

- **Aceite:** Dado uma OS da empresa 98 cujo `contact_id` aponta para um cliente da 99 (com
  celular e consentimento) · Quando o status vai para `ready` · Então nenhum job sai (o contato é
  buscado com `business_id` da OS, ADR 0093) · E o cliente da própria 98, no mesmo cenário,
  recebe um job.
- **Status:** ✅

## UC-COS-33 · Status sem marca não avisa, mesmo chamado `ready` · `must`

- **Aceite:** Dado um status com o nome literal `ready` e sem marca, e outro marcado com
  aguardando peças · Quando a OS vai para o primeiro · Então nenhum job sai · E quando vai para o
  segundo sai um job com o template de aguardando peças.
- **Status:** ✅

## UC-COS-34 · Resposta traz no máximo 20 OS · `must` `[T0]`

- **Aceite:** Dado 25 OS com o mesmo nº · Quando o cliente busca esse nº · Então recebe 20 OS
  (Non-Goals do charter: "NÃO lista todas as OS … no máximo 20").
- **Anti-vácuo:** o teste confere antes que as 25 existem no banco.
- **Status:** 🧪

## UC-COS-35 · 31ª busca do mesmo IP no minuto recebe 429 · `must` `[T0]`

- **Aceite:** Dado um visitante · Quando faz 30 buscas no mesmo minuto · Então as 30 são atendidas
  (422, sem critério) · E a 31ª busca e a abertura da tela recebem 429 · E outro IP, no mesmo
  minuto, ainda é atendido (o limite é por visitante, não global).
- **Por que comportamento:** o UC-COS-07 só confere que a rota declara `throttle:`; este prova que
  o limite morde.
- **Status:** 🧪

## UC-COS-36 · Cada busca deixa log com IP truncado e número redigido · `must`

- **Aceite:** Dado um visitante do IP `203.0.113.57` · Quando busca por celular (acha) e por um nº
  inexistente · Então sai um registro `consultaos.busca_publica` por busca, com o resultado de cada
  uma (`found`, `not_found`) · E o IP registrado é `203.0.113.0` · E o celular não aparece cru ·
  E o User-Agent tem no máximo 80 caracteres · E não há `business_id`, IP completo nem dado da OS
  (nº, status, série, cliente).
- **Âncora:** a política de retenção declara `consulta_os_logs` com IP e número redigido.
- **Status:** 🧪

## UC-COS-37 · Falha interna: 500 só com `found=false`, sem detalhe técnico · `must`

- **Aceite:** Dado uma falha na leitura das OS · Quando o cliente busca · Então recebe 500 com
  corpo exatamente `{"found": false}` · E o texto do erro não aparece na resposta · E o log
  `consultaos.busca_falhou` traz só o tipo do erro, não a mensagem · E a busca é registrada com
  resultado `erro`.
- **Status:** 🧪

## UC-COS-38 · Falha do servidor na busca: a tela avisa e não diz "não encontrada" · `must`

- **Persona:** cliente final que pesquisa a OS no momento em que o servidor falha.
- **Aceite:** Dado o link da empresa (`/consulta-os/{codigo}`) aberto no navegador · Quando o cliente pesquisa um número e a
  busca responde 500 · Então a tela mostra um alerta (`role="alert"`) · E o formulário continua
  visível para tentar de novo · E a tela não mostra "OS não encontrada" nem dado de OS.
- **Lado da API:** UC-COS-37 (o 500 devolve `{"found": false}` sem texto técnico).
- **Fora do teste:** o texto do alerta. A mensagem própria do 429 é o UC-COS-39.
- **Teste:** [`ConsultaOsIndexBrowserTest`](../../../../tests/Browser/ConsultaOs/ConsultaOsIndexBrowserTest.php),
  step `E2E de render · ConsultaOs/Index (ADVISORY)` do `visual-regression.yml`.
- **Status:** 🧪

## UC-COS-39 · Muitas buscas (429): a tela pede para esperar, e o 500 tem mensagem própria · `must`

- **Persona:** cliente final que pesquisa várias vezes seguidas, ou pesquisa quando o servidor falha.
- **Aceite:** Dado o link da empresa aberto no navegador · Quando a busca responde 429 · Então a
  tela diz "Muitas buscas em pouco tempo" e pede para aguardar um minuto · E o formulário continua
  visível · E a tela não diz que o servidor falhou nem que não conseguiu conectar · Quando, na mesma
  tela, a busca responde 500 · Então a tela diz "O servidor não conseguiu fazer a busca" · E a
  mensagem do 429 sai.
- **Lado da API:** UC-COS-35 (a 31ª busca do mesmo IP no minuto recebe 429).
- **Fora do teste:** a falha de rede (o `fetch` rejeita), que mantém "Não foi possível conectar ao servidor".
- **Teste:** [`ConsultaOsIndexBrowserTest`](../../../../tests/Browser/ConsultaOs/ConsultaOsIndexBrowserTest.php),
  step `E2E de render · ConsultaOs/Index (ADVISORY)` do `visual-regression.yml`.
- **Status:** 🧪

## UC-COS-59 · Nº da venda: cada link só acha a venda da própria empresa · `must` `[T0]`

- **Aceite:** Dado o mesmo nº de venda nas empresas 98 e 99, cada um ligado a uma OS da própria
  empresa · Quando o cliente busca esse nº pelo link da 98 · Então vê só a OS da 98 · E pelo link da
  99 só a da 99 · E uma venda da 99 ligada por engano a uma OS da 98 não abre essa OS pelo link da 98.
- **Status:** 🧪

## UC-COS-60 · Celular só com nº de série, e só na empresa do link · `must` `[T0]`

- **Aceite:** Dado a busca por celular ligada · Quando o cliente busca por celular sem nº de série ·
  Então recebe 422 com erro no campo `serie` e o repositório não consulta · E com a série, pelo link
  da empresa certa, acha a OS · E o mesmo celular e série pelo link da outra empresa dá 404 · E um
  contato da 99 ligado por engano a uma OS da 98 não abre essa OS pelo link da 98.
- **Status:** 🧪

## UC-COS-61 · Sem código de empresa não há busca; código desconhecido dá 404 · `must` `[T0]`

- **Aceite:** Dado um visitante sem sessão · Quando abre `/consulta-os` sem código · Então a tela
  abre com `empresa` nula, mostra o aviso para abrir o link da empresa e não mostra o formulário ·
  E a busca antiga `/consulta-os/buscar` não existe (404) · E um código no formato certo que não é de
  empresa nenhuma dá 404 na tela e na busca, só com `{"found": false}`.
- **Teste:** contrato (API) + [`ConsultaOsIndexBrowserTest`](../../../../tests/Browser/ConsultaOs/ConsultaOsIndexBrowserTest.php) (tela).
- **Status:** 🧪

## UC-COS-62 · Quem mexeu na OS aparece como primeiro nome + inicial do sobrenome · `must`

- **Aceite:** Dado uma troca de status feita por um funcionário cadastrado como "Sra. Maria
  Aparecida Souza" · Quando o cliente consulta a OS · Então a atividade mostra "Maria S." · E o
  corpo da resposta não traz o tratamento, o segundo nome nem o sobrenome · E funcionário sem
  sobrenome aparece só com o primeiro nome · E sem nome nenhum a atividade sai sem "quem".
- **Por quê:** decisão [W] 2026-11-17. Antes saía o nome completo do cadastro
  (`surname + first_name + last_name`). A inicial separa duas pessoas com o mesmo primeiro nome;
  o nome completo continua no histórico interno da OS.
- **Status:** 🧪

## UC-COS-63 · O link da empresa nasce na 1ª vez que é pedido e depois é sempre o mesmo · `must`

- **Aceite:** Dado nenhuma empresa com código · Quando o sistema pede o link da empresa 98 duas
  vezes (`EmpresaPublica::linkDe`) · Então devolve o mesmo `/consulta-os/{codigo}` nas duas · E existe
  uma única linha da 98 em `consulta_os_empresas` · E o código está no formato da rota e resolve de
  volta para a 98.
- **Por quê:** decisão [W] 2026-11-18. Até então o link só nascia pelo comando `consultaos:link` e
  nenhuma empresa tinha um — o portal estava fechado para todos. Agora nasce quando a empresa abre a
  configuração do Repair (UC-RSET-09); o comando continua para o suporte.
- **Status:** 🧪

## UC-COS-64 · O link de uma empresa nunca é o de outra, nem abre a outra · `must` `[T0]`

- **Aceite:** Dado as empresas 98 e 99 pedindo o link · Então os dois links são diferentes e
  nenhum contém o código do outro · E cada código resolve para a própria empresa · E pedir de novo o
  link da 98 depois de criar o da 99 não muda nada.
- **Status:** 🧪

---

## Fora deste arquivo (sem contrato verificável ainda)

- [BACKLOG] Copy da tela em PT-BR e sem jargão técnico — o charter pede, mas não fixa texto literal
  e não há spec Playwright desta tela. Sem texto contratado, um teste leria o `.tsx`.
- [BACKLOG] "Sem CTA loud" (pedido da ficha do playbook) — sem fonte no charter nem em PT.
- [BACKLOG] Captcha (resto da US-CONSULTA-001).
