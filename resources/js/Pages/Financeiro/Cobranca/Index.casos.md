---
id: resources-js-pages-financeiro-cobranca-index-casos
casos: Cobrança · /financeiro/cobranca
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/cobranca

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Personas: **Eliana [E]** + **Larissa [Cliente Piloto]**. Tela de acompanhamento (read-only); emissão vive em POST dedicado.

## UC-COB-01 — "Quem pagou hoje? O que vence amanhã?"
Status: 🧪 (`CobrancaControllerTest` — shape `cobrancas/kpis/funil/contas/gateways/filtros` · em quarentena na lane financeiro-pest em 2026-09-03 — estado vivo em `.github/financeiro-pest-quarantine.list`; eixo RENDER em `CobrancaIndexTest` — Browser)
Quando Eliana abre a tela · Então vê os 4 KPIs (Pago no mês · Vencido · Em aberto · 1 contextual) e a tabela densa por vencimento com pagador, chip composto gateway+tipo, conta destino, nosso nº, valor e status com ícone.

## UC-COB-02 — Funil de 5 etapas espelha o estado real
Status: 🧪 (`CobrancaControllerTest` — funil `aberto/lembrete/cobranca_ativa/vencido_5d/protesto` · em quarentena na lane financeiro-pest em 2026-09-03 — estado vivo em `.github/financeiro-pest-quarantine.list`; eixo RENDER + concordância funil×KPI em `CobrancaIndexTest` — Browser)
As 5 etapas somam as cobranças do período; "Protesto" é derivação de UI (sem job real — Non-Goal declarado).
Resíduo: nenhum dos dois prova a ARITMÉTICA da soma — o servidor prova o shape, o Browser prova que a etapa "Em aberto" e o KPI homônimo não se contradizem na tela.

## UC-COB-03 — O servidor filtra pela querystring; o cliente restaura a preferência salva
Status: 🧪 eixo CLIENTE (`tests/js/cobranca-filtros-deeplink.test.tsx` — 8 casos vitest, mordida provada por mutação; **não é ✅ por um motivo declarado, não por falta de prova:** o manifesto G-7 (`scripts/casos-test-results.json`) é alimentado por `casos-results-publish`, que colhe **JUnit das lanes Pest** — vitest não é colhido hoje, então nenhum spec JS consegue carimbar ✅. O `casos-gate` me barrou nisso e ele está certo: colar run id na prosa não conta) · 🧪 eixo SERVIDOR (`CobrancaControllerTest` — filtro por status/tipo/gateway/conta/origem via querystring · em quarentena na lane financeiro-pest em 2026-09-03 — estado vivo em `.github/financeiro-pest-quarantine.list`)
Dado que Eliana abre `/financeiro/cobranca?status=vencida` · Quando a tela monta · Então **o servidor** devolve só as vencidas, e **o cliente** aplica por cima a preferência salva em `localStorage` dos 5 filtros do charter (`tab` · `tipo` · `gateway` · `account` · `origem`) — que **vence a querystring**. `busca` é a exceção: não persiste e vem só da URL.

**Redigido a partir do desfecho da contestação de 2026-09-03 — a redação anterior ("Trocar tab/chip/dropdown reflete na URL e a volta pela URL reproduz a visão") era FALSA nas duas metades e foi substituída, não afrouxada:**

- **Metade 1 — "reflete na URL": não existe mecanismo.** Varredura contada nos **11 de 11** arquivos de `Pages/Financeiro/Cobranca/`: os únicos `router.*` são dois `router.visit('/settings/payment-gateways')` (navegação para outra tela) e um `router.post('/financeiro/cobranca/emitir')` (submit). Zero `router.get`/`reload`, zero `window.history`/`pushState`/`replaceState`, zero `URLSearchParams`, zero `location`. Trocar tab/chip/dropdown **nunca** escreveu na URL — não é bug sutil, é ausência.
- **Metade 2 — "a volta pela URL reproduz a visão": o `localStorage` vence.** `lsGet(key, def)` ([`_lib/cobranca-shared.ts:461`](_lib/cobranca-shared.ts)) só devolve o default quando a chave está **ausente**; havendo valor salvo, ele ignora `filtros.*` (que é a querystring lida pelo `CobrancaController`). Provado por execução, não por leitura.
- **Quem é o perdedor:** o UC, não o `.tsx`. O charter (lei) §Estado, linha 45, **exige** a persistência: *"Persistência localStorage: namespace `oimpresso.financeiro.cobranca.*` (tab, tipo, gateway, account, origem)"* — os cinco exatos que o código persiste. O código **cumpre a lei**; o UC anterior prometia um comportamento que o charter nunca pediu. Por isso nada foi mudado em `Index.tsx` (regra de precedência de `memory/proibicoes.md`: corrigir o perdedor).

**Recibo (execução, não leitura):** `npx vitest run tests/js/cobranca-filtros-deeplink.test.tsx` → **8 passed**. Mordida provada por mutação: dando precedência à querystring nos 5 `useState` do `Index.tsx:75-79`, os 2 casos do achado ficam **vermelhos** (a linha reaparece) e os 6 restantes seguem verdes; o `.tsx` foi restaurado byte-idêntico (`git status` limpo) e o verde reconfirmado. O controle positivo do próprio spec fecha o par: **sem** `localStorage`, o mesmo `?status=vencida` **mostra** a linha.

## UC-COB-04 — Cobrança paga cria o título no caixa
Status: 🧪 (`OnCobrancaPagaCreateFinanceiroTituloTest`)
Dado cobrança emitida · Quando o gateway confirma o pagamento · Então nasce/quita o título correspondente no Financeiro com a **forma realizada** (read-only na Unificada).

## UC-COB-05 — Boleto/PIX emitido tem artefato conferível no drawer
Status: 🧪 (`BoletoMockEmissaoTest` — emissão mock 21 bancos)
Boleto mostra linha digitável + código de barras; PIX mostra BR Code; cartão mostra last4 — cada tipo com seu drawer condicional.

## UC-COB-06 — Webhook do banco é idempotente
Status: 🧪 (`Onda26InterWebhookIntegrationTest`, `ProcessAsaasPixWebhookListenerTest`)
Re-entrega do mesmo webhook não duplica baixa nem título.

## UC-COB-07 — Tier 0, read-only e sem side-effect no render
Status: 🧪 (`CobrancaControllerTest` — global scope + GET não muta · em quarentena na lane financeiro-pest em 2026-09-03 — estado vivo em `.github/financeiro-pest-quarantine.list`)
Abrir a tela não dispara e-mail/WhatsApp, não chama API de gateway e não enxerga outro business ([ADR 0093]). Redirect 301 de `/financeiro/boletos` preservado.

## Backlog de casos (sem id)
- **[BACKLOG] Export CSV/PDF** — botão existe na UI; backend é backlog (Onda 5). Hoje é botão sem contrato.
- **[BACKLOG] Wizard "Nova cobrança" 4 passos** — grava só no submit final; nenhum passo intermediário emite.
- **[BACKLOG] Remessa/Retorno CNAB 240 (C6)** — sheet existe, driver é backlog.
- **[BACKLOG] Atalhos KB-9.75** (`/` `J/K` `Enter` `Esc` `?`). A persistência em `localStorage` SAIU deste backlog em 2026-09-03: ela é exigida pelo charter (linha 45), está implementada nos 5 filtros e agora tem prova de execução no `UC-COB-03`.
- **[BACKLOG] Deep-link de verdade** — escrever os filtros na URL (`router.get` com `preserveState`, ou `history.replaceState`) para que um link compartilhado reproduza a visão. Hoje **não existe** (varredura contada 11/11 acima). Vira US se [W] quiser a capacidade; não é defeito, porque o charter nunca a pediu — e ela precisa de uma decisão de precedência: querystring explícita deve ganhar da preferência salva na 1ª carga?

## Trilha do tempo
- 2026-09-03 · [CC] **`UC-COB-03` reescrito — a contestação do #6632 fechou com PROVA, e o perdedor foi o contrato.** A hipótese ("o localStorage vence a querystring") **procede**, medida por execução em `tests/js/cobranca-filtros-deeplink.test.tsx` (8 casos, mordida por mutação). `Index.tsx` **não foi tocado**: ele cumpre a linha 45 do charter. **Achado colateral, reportado e não consertado aqui:** o `CobrancaIndexTest` (Browser) do próprio #6632 já executou uma vez — run `33762382505`, 03/09 13:52Z — e falhou **6 de 6** com `RuntimeException: "Nao estabilizou: as 3 linhas de fixture montarem — esperado '3', ultimo '0'"`. O step aparece `success` na API do GitHub porque tem `continue-on-error: true` (`visual-regression.yml:787`). Ou seja: hoje esta tela de DINHEIRO não tem **nenhuma** cobertura de execução verde — o servidor está em quarentena e o Browser está vermelho-mascarado. Consertar o E2E é outro intent.
- 2026-09-03 · [CC] E2E de render `tests/Browser/Financeiro/CobrancaIndexTest.php` (fecha o `e2e (Browser) ✗` do `screen-coverage-map`), wirado ADVISORY no `visual-regression.yml`. `UC-COB-02`/`UC-COB-07` saíram do docblock pro TÍTULO do `CobrancaControllerTest`. **Achado que reenquadra a tela:** o "7/7 UC citados por teste" é string-match — 5 dos 7 (`UC-COB-01/02/03/05/07`) apontam pra arquivo em `.github/financeiro-pest-quarantine.list`, e o manifesto G-7 (`scripts/casos-test-results.json`) só tem `UC-COB-04` e `UC-COB-06`: **2 de 7 com prova de execução**. Sair da quarentena exige consertar os 4 vermelhos anotados na lista — decisão [W].
- 2026-08-17 · [CC] criado no espelho Cowork (preparo da leva 2). Charter v1 rico, 0 `casos.md` — os `it()` do `CobrancaControllerTest` precisam citar os ids (G-2).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
