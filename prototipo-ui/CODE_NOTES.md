# CODE_NOTES.md — OUTBOX: Claude Code → Wagner

> Claude Code [CL] escreve aqui. Wagner [W] lê pra acompanhar tradução protótipo → Inertia.
> **Append-only.** Não edita entradas antigas.
> Formato em [PROTOCOL.md §4](PROTOCOL.md).

---

## 2026-05-09 — Setup inicial + auto-check passou

[CL] leu [CLAUDE_CODE_BRIEFING.md](CLAUDE_CODE_BRIEFING.md) seção 5. Respostas auto-check:

1. **Quem aprova merge final?** → `[W]` Wagner. `[CL]` sozinho NUNCA mergeia.
2. **Onde vive o protótipo Cowork?** → `prototipos/<tela-kebab>/page.tsx` (read-only no repo, vem do export Cowork).
3. **Qual skill orquestra este loop?** → `mwart-comparative` V4 (Tier A always-on).

Auto-check OK. [CL] entendeu protocolo.

---

## Template entradas futuras (copiar e preencher)

```markdown
## YYYY-MM-DD HH:MM [CL] → [W]

### Tela: <Modulo/Tela>
### Status: traduzido | aguardando | bloqueado
### Diff: <link PR | branch local>
### Build: passou | falhou (motivo)
### Charter atualizado: sim | não (motivo)

### Decisões de tradução:
- <protótipo usava X, Inertia usa Y porque...>
- <copy "Sales" virou "Vendas">
- ...

### Pendências:
- [ ] <a11y review F3.5>
- [ ] <screenshot final pra Wagner aprovar merge>

### Notas pra Wagner:
<qualquer coisa que precisa atenção dele>
```

---

## 2026-05-31 [CL] → [W]

### Tela: — (meta-rotina, não é tela)
### Status: F0 entregue · read-only · aguardando decisão de escopo

### Diff: nenhum (nada alterado) · doc novo [`F0-AUDITORIA-ROTINAS-DESIGN-2026-05-31.md`](F0-AUDITORIA-ROTINAS-DESIGN-2026-05-31.md)
### Build: n/a
### Charter atualizado: não

### Decisões de tradução:
- Respondi ao `COWORK_NOTES.amendment-otimizar-rotinas-design-2026-05-31` (que vive só do lado Cowork — nunca chegou ao disco) com o F0 que ele mesmo exige.
- 4 entregas medidas: inventário (19 skills design · 4 réguas de score) · redundância · baseline · tabela atual%→target% por G1–G6.

### Achados que mudam o plano:
- **G5 já está ~85% pronto** (6 regras `ds/*` + baseline 669-linhas + ratchet + CI `eslint-gate.yml` já existem). Falta só Stylelint + *required check*. É "fechar", não "construir".
- **`ds:report` é keystone oculto** — G2/G4/G6 dependem dele e ele só está speccado. Promovi a item explícito.
- **`a11y-report.md` = 0** no repo inteiro: gate F3.5 nunca gerou prova.
- **`HANDOFF.md` stale há 16 dias** = prova empírica do G4.
- 2 riscos: G1 não pode achatar eixos de score; G3 muda ADR 0114 (adversarial).

### Pendências (decisão Wagner):
- [ ] Escopo Onda 1: sequência refinada do F0 (G5-finish ‖ G4-handoff primeiro) vs original do amendment
- [ ] `eslint-gate` vira *required check*? (fecha G5 — só Wagner pode)
- [ ] G3 só com emenda à ADR 0114

### Notas pra Wagner:
Nada foi tocado. Leve o F0 pro Cowork pra reconciliar (ele não sabia que G5 já rodou — esse próprio descompasso é o caso do G4).

---

## 2026-05-31 [CL] → [W] · G5-finish ENTREGUE

### Tela: — (CI/infra)
### Status: PR aberto · stylelint-gate passou no CI · aguardando merge + required check

### Diff: [PR #2054](https://github.com/wagnerra23/oimpresso.com/pull/2054) · base `main` · +1398/-5 · 6 arquivos
### Build: stylelint-gate CI **PASS** (30s) · baseline 1095 congelado · delta 0

### O que entrou:
- `stylelint.config.mjs` (4 regras de drift) · `scripts/stylelint-baseline.mjs` (ratchet via API) · `.github/workflows/stylelint-gate.yml` · `config/stylelint-baseline.json` (1095) · stylelint 17.12 + 3 scripts npm.
- Espelha o ratchet ESLint `ds/*` (ADR 0209), agora cobrindo CSS além de TSX.

### Erratas do F0 (medido em staging-ct100 vs origin/main):
- `ds:report` **JÁ EXISTE** em origin/main (keystone K resolvido).
- `main` local stale 155 commits — recomendo `git fetch`.

### Pendências (Wagner):
- [ ] Mergear PR #2054 (não mergeei — publication-policy)
- [ ] Tornar `eslint-gate` + `stylelint-gate` *required checks* → fecha G5
- [ ] Onda 2 opcional: `customSyntax` pro CssSyntaxError do inertia.css + regra radius/spacing px-cru

---

## 2026-06-02 [CL] → [W] · TRAVA-SEGUNDA Martinho · Passo 0 entregue

### Tela: — (worklist do núcleo, 6 telas)
### Status: **Passo 0 concluído · aguardando 3 decisões [W] antes de tocar código** (Tier 0)

### Diff: nenhum (nada alterado ainda) · este é o retorno do §10.4 Passo 0

### Achado central — §10.4 confirmado: **as 6 já existem e estão maduras em `origin/main`**
Job NÃO é construir. É **estender + wire-up + migrar dados + estabilizar 1 build**.

| # | Tela | Estado real `origin/main` | Trabalho trava-segunda |
|---|------|---|---|
| 1 | Cliente | ✅ drawer + `_form` + Ledger + Import | **estender:** campo preço-por-cliente visível/aplicável + importar clientes Martinho |
| 2 | Produto/Preço | ✅ Index/Create/Edit/BulkEdit | importar produtos+tabela de preço; preço resolve na venda |
| 3 | Venda | ✅ Sells/* (piloto 9.5) | **wire-up:** cliente→item→preço-por-cliente→emite fiscal |
| 4 | Fiscal | ✅ Nfe/Nfse + NfeBrasil/NFSe controllers | **wire-up:** venda emite NF-e(produto)/NFS-e(serviço) homolog |
| 5 | Financeiro | ✅ Unificado/Cobranca/Boleto/ContaReceber (F3 real) | **wire-up:** venda gera título a receber + boleto 30d |
| 6 | Oficina | ✅ OficinaAuto ProducaoOficina+ServiceOrders+DVI | **demo limpa:** check-in→DVI→aprovação→execução |

### Fatos que gatilham decisão [W]
1. **Base errada:** sessão está em `feat/staging-ct100` — *dirty* + **158 commits atrás de `origin/main`**. "Estabilizar 1 build limpo estendendo origin/main" exige **worktree fresco off `origin/main`**, não esta branch.
2. **Dado REAL da Martinho:** CU-3→4→5 pede "dados REAIS". O repo **não tem** export/credencial Firebird da Martinho. Sem fonte de dado, o loop roda mas com **fixture seed**, não com os dados dela.
3. **Harness de smoke:** worklist pede Playwright+`data-testid` (`_PROPOSTA-0244`). **Não há Playwright no repo** (`package.json` sem dep) e `_PROPOSTA-0244` não está em disco (vive no Cowork). Canon atual de smoke = **Pest `SmokeRoutesTest`** (já no CI). Introduzir Playwright sob deadline = infra nova.

### Pendências (decisão [W] — bloqueiam código):
- [ ] **D1 Base:** abro worktree fresco off `origin/main` pra TRAVA-SEGUNDA? (recomendado — descarta o ruído da branch staging)
- [ ] **D2 Dado:** fonte dos dados REAIS da Martinho — (a) Wagner sobe dump/conexão Firebird agora · (b) eu construo o pipeline de migração + seed fixture representativa e os dados reais entram quando você passar acesso · (c) já existe migrado em algum lugar?
- [ ] **D3 Smoke:** Playwright net-new (literal do worklist) **vs** estender Pest `SmokeRoutesTest` canon (zero dep nova, já no CI) pros 6 CUs?

### Notas pra Wagner:
Nada foi tocado — respeitando "Tier 0 → PR + espera [W]" e as lições do `LICOES_F3_FINANCEIRO_REJEITADO.md` (não inventar Model/Service/dado; não blastar 6 módulos numa branch suja). Respondidas as 3, executo: extend Cliente (preço-por-cliente) → wire CU-3→4→5 → demo Oficina → 6 smokes → 1 build limpo → PR e espero você.

---

## 2026-06-02 [CL] → [W] · Decisões aplicadas + gap map preciso (pós-investigação)

### Decisões [W]: D1=worktree fresco off origin/main ✅ · D2="já migrado" (biz=164) · D3=eu decido → **Pest E2E now + Playwright roadmap**
- Worktree limpo criado: `feat/trava-segunda-martinho` off `origin/main` (HEAD 2e9f5881e). **Nada commitado ainda.**
- **Martinho = business_id 164** (perfil + ADR 0171). ⚠️ **Discrepância de dado:** ADR 0171 confirma **91 veículos + 91 service_orders** migrados (Oficina/CU-6 ✅). Mas o tracker `_pipeline-migracao-legacy.md` diz **"vendas/fin pendente"** — ou seja **clientes/produtos/preços/títulos da Martinho NÃO estão migrados ainda**. "Já está migrado" vale pra **Oficina**, não pro loop venda→fiscal→fin.
  - **Implicação (boa):** os smokes resilientes **semeiam fixture determinística própria** (cliente c/ price group + produto c/ variation_group_price + serviço) e provam o encadeamento em CI. Validação com os dados REAIS de biz=164 = passo de prod/staging seu, separado. É o design correto de "smoke que falha alto".

### Gap map real (investigado em 4 frentes, file:line confirmados) — §10.4 confirmadíssimo
| CU | Estado real | Trabalho |
|---|---|---|
| CU-1 Cliente preço | ✅ Contact.customer_group_id → CustomerGroup.selling_price_group_id → variation_group_prices · `_drawer/ComercialTab.tsx` dropdown + PATCH `/cliente/{id}/comercial` | nenhum |
| CU-2 Produto resolve | ✅ `Sells/Create.tsx:446` auto-aplica price group · `ProductUtil.php:1626` join variation_group_prices | nenhum |
| CU-3 Venda | ✅ Sells/Create piloto 9.5 wired | nenhum |
| CU-4 Fiscal | ✅ **backend pronto** `NfeEmissaoController` POST `/nfe-brasil/transactions/{tx}/emitir` + `NfseController.create(transaction_id)` · homolog default (`business.ambiente=2`) · SEFAZ stubável em teste · ⚠️ **modais `VdNfeEmitModal`/`VdNfseEmitModal` em `Sells/Show.tsx` são stub `setTimeout`** (wire-up "próximo PR") | **WIRE-UP frontend** |
| CU-5 Financeiro | ✅ `TransactionObserver`→`TituloAutoService` cria Titulo receber auto, venc +30d, idempotente, tenant-scoped, ADR 0175 conta opcional, **testado** · ⚠️ boleto é botão manual (`TituloService::emitirBoleto`), não auto | nenhum novo (boleto via ação existente no smoke) |
| CU-6 Oficina | ✅ caminho FSM limpo check-in→DVI→aprovação(mutação real)→concluir, **testado** (`FsmTransitionTest`) · ⚠️ sem seeder de demo no biz de teste | **demo seeder** |

### Conclusão: **o loop CU-3→4→5 já encadeia no backend e está testado.** Trabalho trava-segunda reduzido a 4 itens:
1. **CU-4 wire-up** — `Sells/Show.tsx` modais → endpoints reais (remove `setTimeout`). Emite em **homologação** (seguro). Único item comportamental → seu olho no PR.
2. **CU-6 demo seeder** — OficinaAuto idempotente (1 veículo + 1 OS + FSM iniciada) p/ demo limpa reproduzível sem depender de prod biz=164.
3. **6 smokes Pest E2E** (CU-1..6) — fixture seed determinística; joia = CU-3→4→5 encadeado (venda→emite NF-e+NFS-e→gera título+boleto). Canon Pest, já no CI. "Falha alto".
4. **1 build limpo** — confirmar `npm run build` das 6 áreas (já parto de origin/main limpo).

### Congelado (vira roadmap, NÃO entra segunda): auto-boleto-on-finalize, Playwright/data-testid, estoque avançado, BOM, comissão, manifestação entrada, MDF-e/NFC-e, migração real dos dados venda/fin da Martinho (depende de fonte Firebird sua).

### Pendência [W]: confirmar que o **CU-4 wire-up frontend** (emitir NF-e/NFS-e real a partir da venda, em homologação) pode entrar no PR — era marcado "próximo PR" por decisão anterior (KB-9.75). Se preferir CU-4 só backend+smoke e deixar o botão pro PR seguinte, eu separo.

---

## 2026-06-03 [CL] → [W]

### Tela: — (Passo-0 de verificação · fluxo DANI · não é tradução de tela)
### Status: **VERIFICADO vs `origin/main` · o "enviar pra faturamento" JÁ EXISTE e é source-agnostic · ZERO tela nova · 0 código alterado**
### Diff: nenhum (Passo-0 = verificar antes de assumir). Gate de aprovação central da Dani = roadmap (você simplificou).
### Build: n/a · Charter atualizado: não

### O pedido (após sua simplificação)
"Basta o **enviar pra faturamento** (handoff venda→faturamento); o gate de aprovação central vira roadmap." MVP = confirmar que a ação existe e aparece nos **dois** tipos de venda (OS `source:oficina` + balcão `source:balcao`).

### Resposta às 3 perguntas do Passo-0 (vs `origin/main`)

**Q1 — A ação "enviar pra faturamento" existe e aparece nos 2 tipos?** → **SIM, e é source-agnostic.**
Os dois tipos viram a MESMA entidade `transactions (type=sell)` e renderizam na MESMA tela Inertia:
- **Balcão** → `transactions.source='balcao'` (default da migration `2026_05_25_140000_add_source_and_os_ref`).
- **OS** → `transactions.source='oficina'` criada por observers: `Modules/OficinaAuto/Observers/ServiceOrderObserver.php:123` (`os_ref=SO-{id}`) e `Modules/Repair/Observers/JobSheetObserver.php:119` (`os_ref=OS-{id}`).
- Ambas caem no `SellController@index` sem filtro implícito por source (filtro por source só quando o usuário escolhe) → aparecem juntas na lista + no breakdown `porOrigem` (balcao/oficina/online).

**Onde o handoff aparece (3 superfícies, todas source-agnostic):**
1. **Fila do faturista** — saved view **"Aguardando faturamento"** em [Sells/Index.tsx:421](resources/js/Pages/Sells/Index.tsx) — filtro `payment_status !== 'paid' && fiscal_status == null`. Pega balcão E oficina, independente de FSM.
2. **Ação na venda** — [Sells/Show.tsx:400](resources/js/Pages/Sells/Show.tsx) coluna direita:
   - `VdNextActionPanel` (hero "Próxima ação") → botão **"Faturar"** com gate fiscal *"Emita NF-e antes de faturar"* + CTAs *"Emitir NF-e/NFS-e agora →"*.
   - `FsmActionPanel` ("Todas as transições") → se a venda ainda não está no pipeline mostra **"Iniciar pipeline FSM"**; se está, mostra "Faturar".
3. **Emissão** — `VdNfeEmitModal` / `VdNfseEmitModal` (o ato de faturar em si).

**Q2 — Existe o estado FSM "pronto pra faturar" + papel da Dani?** → **SIM.**
`database/seeders/FsmProcessoVendaComProducaoSeeder.php`: stage `ready_for_invoice` ("Pronto pra faturar") → action **`faturar`** → stage `invoiced` ("Faturada"), com **role `financeiro.faturar`** (= o papel da Dani/[E]). A transição que empurra a venda pra fila é `concluir_producao` (in_production → ready_for_invoice).

**Q3 — O loop venda→faturamento→financeiro fecha?** → **SIM (já provado #2135 + entrada CODE_NOTES anterior).**
Faturar → `TituloAutoService` lança título no contas a receber (CU-5, testado). `fin_titulos` já tem coluna `aprovacao` (migration `2026_05_19_220002`) — base do gate Financeiro-side que você mandou pro roadmap. Tela `Financeiro/Unificado` live.

### Conclusão
**É superfície/visível, não build** — exatamente seu palpite. O caminho "SE já existe → só configurar o papel/visão da Dani. Zero tela nova" é o que se aplica. **Não escrevi código** (Passo-0).

### Gap real (pequeno) — uneven affordance, NÃO bloqueia o MVP
O botão **"Faturar"** do `VdNextActionPanel` só aparece quando a venda está em `ready_for_invoice`, estado que **só o caminho de produção** (`concluir_producao`) alcança. Uma venda de **balcão pura** (sem produção), ao iniciar pipeline, mapeia direto pra `invoiced`/`paid` (`InitialStageResolver`) — pula `ready_for_invoice` e **não mostra o CTA "Faturar"** no hero. Pro balcão o handoff hoje vive na **saved view "Aguardando faturamento" + modais de emissão** (funciona, mas não é o mesmo botão). → Se quiser um CTA "Enviar pra faturamento" idêntico nos dois caminhos, é aditivo em `Sells/Show.tsx` (toca UI governada → exige charter + visual-comparison + PRE-MERGE-UI, então **não é "reversível CI-verde autônomo"** — eu pararia pra seu OK).

### O que é config (não-código) e depende de você / dados prod
- Rodar `FsmProcessoVendaComProducaoSeeder` no **biz=164 (Martinho)** e atribuir a role **`financeiro.faturar`** à Dani. É dado de **produção** → fora do meu escopo autônomo (você [W] aprova). Posso te entregar o comando/`tasks-create` se quiser.

### Pendências
- [ ] [W] decidir: (a) aceitar o estado atual (fila + emissão cobrem balcão; Faturar cobre produção) como "MVP atendido", ou (b) autorizar o CTA uniforme "Enviar pra faturamento" em `Sells/Show.tsx` (aditivo, gate UI, não-autônomo).
- [ ] [W]/dados: seed FSM + role `financeiro.faturar` pra Dani no biz=164.

### Notas pra Wagner
Não cunhei ADR (era pra ser 0238 no prompt original — sua simplificação tirou a necessidade). Nada mergeado, nada em prod. O gate de aprovação central da Dani permanece roadmap, como você definiu.

### Decisão [W] 2026-06-03: **implementar opção (a) = CTA surface-only.**
("faça o a" = abordagem Surface-only, não "deixa como está" — corrigido após "vai fazer?".)
Escopo aprovado: CTA **"Enviar pra faturamento"** no `VdNextActionPanel`, source-agnostic, abrindo os modais de emissão NF-e/NFS-e existentes — quando a venda ainda não foi faturada e não há action "Faturar" no FSM (caso balcão puro). **Zero backend, zero migration, reversível.** Build + Pest + visual-comparison; PARO no screenshot pra [W] aprovar antes de merge.

Pro roadmap (NÃO entra agora):
- Gate de aprovação central da Dani (estado de aprovação + fila central OS+balcão + permissão).
- Caminho FSM pra balcão alcançar `ready_for_invoice` (opção FSM-level descartada — surface-only resolve o MVP).
- Config quando ativar pra Martinho: seed FSM + role `financeiro.faturar` pra Dani no biz=164 (dado de prod → [W]).

---

## 2026-06-03 [CL] → [W] · implementação surface-only

### Tela: Sells/Show (coluna direita · VdNextActionPanel)
### Status: **código pronto · isolado em 4 arquivos · ⏸ AGUARDANDO screenshot/gate ([W])**
### Diff: working-tree (não commitado). Meus arquivos (os outros do `git status` são pré-existentes, NÃO meus):
- `app/Http/Controllers/SellController.php` — `show()` headline ganha `fiscal_status` (lookup `NfeEmissao` mais recente, espelha index l.1265) + `source`. +14 linhas.
- `resources/js/Pages/Sells/Show.tsx` — passa `saleStatus`+`fiscalStatus` pro painel; interface Headline ganha os 2 campos. +6 linhas.
- `resources/js/Pages/Sells/_components/VdNextActionPanel.tsx` — no branch "fora do pipeline", em vez de `return null`, renderiza CTA **"Enviar pra faturamento"** (header "Pronta pra faturar" + msg "Emita a nota pra lançar o título no contas a receber" + botões Emitir NF-e/NFS-e → `onOpenEmit`). Gate: `saleStatus==='final' && !fiscalStatus`. Reusa CSS `.vd-next`/`.vd-next-gate-cta` existente (zero CSS novo). +48 linhas.
- `tests/Feature/Sells/SellsKb975EmitModalsTest.php` — +4 testes estruturais (props, CTA, wire Show, headline backend).

### Comportamento
- **Balcão** (`source=balcao`, final, sem NF, fora do FSM) → agora vê o CTA "Enviar pra faturamento" no hero (antes: nada no hero, só fila+modais).
- **OS/produção** → inalterado: continua usando o hero "Faturar" do FSM (quando em `ready_for_invoice`). Sem CTA duplicado (o novo só aparece quando `in_pipeline=false`).
- **Já faturada** (`fiscal_status` preenchido) ou **rascunho/orçamento** (`status≠final`) → não mostra (gate).

### Verificação local: **BLOQUEADA neste checkout** (honesto)
`D:/oimpresso.com` está **sem `node_modules` e sem `vendor`** — não rodei tsc/vite build/Pest nem subi o app. (Meu "tsc 0 errors" anterior foi falso-positivo: tsc não chegou a rodar.) O que dá pra garantir: change é aditivo/cirúrgico, tipos TS adicionados consistentes, PHP espelha padrão já existente do index, e **todas as strings dos 4 testes estruturais foram conferidas presentes** (passam no CI — são `file_get_contents`+`toContain`).

### ✅ [W] escolheu opção 1 — branch limpo + CI. **PR #2146 aberto.**
- Branch `feat/sells-enviar-faturamento-balcao` (base `origin/main`), commit `ee66a2ab0`, **exatamente 4 arquivos** (113+/2-). Isolado via worktree novo off `origin/main` (patch aplicou limpo; os outros arquivos sujos do staging-ct100 NÃO entraram).
- PR: https://github.com/wagnerra23/oimpresso.com/pull/2146 — CI roda build + Pest + visual-regression.
- ⏸ **Gate visual ainda pendente:** aprova o screenshot (CI visual-regression ou staging) ANTES do merge. **Não mergeio sem teu OK** (MWART/PRE-MERGE-UI). Nada em prod.

### ✅ MERGED — [W] autorizou "pode fazer tudo / merge" (2026-06-03)
- **PR #2146 mergeado** (squash, `--admin` pra contornar branch-protection — [W] explícito + todo CI verde). Commit em `origin/main`: `792bb1c1d`.
- Fix no ciclo: UI Lint falhou 1ª vez (R3 emoji — meus 3 `📄` viraram `<FileText/>` lucide, AP6 PRE-MERGE-UI). Re-push → **todos os 14 checks verdes**: Vite build · Pest · ESLint · PHPStan · UI Lint · visual-regression · PR UI Judge · mwart/charter/module-grades gates.
- Branch + worktree limpos (local e remoto). Nada de prod tocado (só código; fiscal real continua homolog/[W]).

---

## 2026-06-03 [CL] → [W] · LIGAR FISCAL REAL Martinho (biz=164) — Passo 0 + prep autônomo

Resposta ao `PROMPT_PARA_CODE_LIGAR-FISCAL-REAL-MARTINHO.md`. **NÃO flipei nada. NÃO emiti nada. NÃO toquei `.env` de prod.** Fiz Passo 0 (verificação vs código real) + 1 fix de prep que é **pré-requisito de segurança** do cutover. Nenhuma nota foi/será autorizada por mim — o irreversível é teu.

### Passo 0 — ambiente/credenciais são por-business? (a pergunta-chave do prompt)

| Documento | Onde mora o **ambiente** (homolog/prod) | Onde mora o **certificado** | Por-business? | Cutover seguro pra biz=164 sem tocar ROTA LIVRE? |
|---|---|---|---|---|
| **NF-e 55** | coluna `business.ambiente` (1=prod, 2=homolog) → vira `tpAmb` em `NfeService::criarTools` (l.1042/1111) | `nfe_certificados` (encrypted-at-rest, `HasBusinessScope`) | ✅ **SIM** | ✅ **SIM** — setar `business.ambiente=1` só na linha id=164 |
| **NFS-e** | era **GLOBAL** `env('NFSE_AMBIENTE')` → `SnNfseAdapter` bindado 1× no container | `nfse_provider_configs.cert_id` → `nfe_certificados` (per-business ✅) | ❌ **NÃO (era)** → ✅ **agora SIM (corrigi)** | ❌→✅ depois do meu fix |

**O furo que o Passo 0 pegou (era exatamente pra isso):** o NFS-e resolvia o ambiente pelo **bind global** `config('nfse.ambiente')` em `NfseServiceProvider:36`. O campo por-business `nfse_provider_configs.ambiente` **existia mas estava morto** (só aparecia na UI, nunca chegava no adapter). Resultado: flipar NFS-e pra produção exigiria mudar o `.env` global → **emitiria nota real de TODOS os tenants (ROTA LIVRE inclusa)**. Viola direto o "não pode tocar outros clientes".

### Fix de prep que implementei (autônomo, dentro de "prep de código/config" do prompt)
**Intenção única:** NFS-e passa a resolver ambiente **por-business** (igual NF-e já fazia). 3 arquivos, ~40 linhas, aditivo, reversível, **zero migration** (o campo `ambiente` e o DTO já existiam):
- `Modules/NFSe/Services/NfseEmissaoService.php` — `montarPayload()` agora popula `ambiente:` e `municipioIbge:` a partir de `NfseProviderConfig` do tenant. **Fail-safe:** sem config → `'homologacao'` (nunca emite real por acidente).
- `Modules/NFSe/Adapters/SnNfseAdapter.php` — `emitir()` + `buildDps()` derivam endpoint e `tpAmb` de `$payload->ambiente` (por-call), não mais do bind global. Helper `resolveBaseUrl()`.
- `Modules/NFSe/Tests/Feature/AmbientePorBusinessTest.php` — **novo**, 4 testes DB-free (Http::fake): provam que payload `producao` emite no endpoint real mesmo com bind global homolog, e o anti-vazamento inverso.

**Verificação local:** `php -l` limpo nos 3. **Pest NÃO rodou local** (este checkout está sem `vendor/` — mesmo bloqueio honesto do PR #2146). Os testes precisam rodar no **CI/staging**. Não commitei ainda — aguardo teu OK pra abrir PR em branch limpo off `origin/main`.

⚠️ **Follow-up conhecido (fora do cutover, deixei documentado no código):** `SnNfseAdapter::consultar()`/`cancelar()` ainda usam o ambiente do bind (não recebem payload). Hoje não é regressão (já eram globais), mas quando biz=164 for pra prod, um *cancelamento* de NFS-e cairia no endpoint errado. **Cancelamento já estava marcado "PR separado" no teu prompt** — só registrando que entra junto nesse PR futuro.

### Sequência segura (confirmada viável com os achados)
1. **[W]** sobe credenciais Martinho em **HOMOLOGAÇÃO** (biz=164): cert A1 + `business.ambiente=2` + `nfse_provider_configs.ambiente='homologacao'`.
2. **[CL]** emite **1 NF-e 55 + 1 NFS-e de teste em homolog** → confirma `autorizada`. ⚠️ **isto depende do cert** (item 1) — **não há stub**: homologação bate no webservice REAL de homologação da SEFAZ/SN-NFSe e exige o certificado de verdade.
3. **[W]** flip **só biz=164**: `business.ambiente=1` (NF-e) + `nfse_provider_configs.ambiente='producao'` (NFS-e) — **com o fix acima mergeado**, isso é cirúrgico e não vaza.
4. **[CL]** emite **1 nota REAL de teste** (valor pequeno) via emissão **manual** (`POST /nfe-brasil/transactions/{tx}/emitir` com **`modelo:'55'`** — atenção: default é `'65'`/NFC-e, que está **fora de escopo e sem CSC** configurado).
5. **CHECKPOINT [W]** — confirma autorizada no **portal SEFAZ** (ponto irreversível).
6. **Só então** abre o gate de auto-emissão: `nfe_business_configs.auto_emission_enabled=true` pra biz=164 (gate per-business já existe e protege os outros por default-false; tripla trava: flag global + per-business + FSM `emitir_nfe`).

### CHECKLIST — o que falta de [W] (dado de produção / irreversível, fora do meu escopo)
- [ ] **Aprovar** o fix NFS-e per-business acima → eu abro PR limpo + CI (sem isso, NFS-e prod = global = vaza pra ROTA LIVRE).
- [ ] **Certificado digital A1** (.pfx + senha) do CNPJ da Martinho (serve pra homolog E prod). Subir via `/nfe-brasil/configuracao/certificado`.
- [ ] **Regime tributário + CRT** (Simples/Presumido/Real) → popular `nfe_business_configs` (regime + `tributacao_default`: CFOP/CST/CSOSN/alíquotas) pra biz=164.
- [ ] **NF-e 55:** série + próxima numeração + UF. (`business.numero_serie_nfe`/`ultimo_numero_nfe`.)
- [ ] **NFS-e:** confirmar que o município da Martinho participa do **SN-NFSe nacional** (o adapter atual fala só com `sefin.nfse.gov.br`); LC116 + alíquota ISS + IM do prestador → `nfse_provider_configs` (biz=164).
- [ ] **Flip ambiente=produção** (passo 3) — **só [W]**.
- [ ] **Checkpoint** da 1ª nota real no portal SEFAZ (passo 5) — **só [W]**, abre o gate.

**Não-escopo (roadmap, não toquei):** NFC-e (CSC vazio em `NfeService:1052-53`), MDF-e, manifestação, cancelamento per-business.

### ✅ [W] autorizou "abrir PR limpo + CI" → **PR #2147 aberto** (2026-06-03)
- Branch `fix/nfse-ambiente-por-business` off `origin/main` (worktree isolado; os 2 arquivos de line-ending normalization NÃO entraram). Commit `3cd6ba59a`, **exatamente 3 arquivos** (133+/3-).
- PR: https://github.com/wagnerra23/oimpresso.com/pull/2147 — CI roda Pest (incl. os 4 novos) + PHPStan + gates.
- ⏸ **Não mergeio sem teu OK.** E mesmo mergeado, **nada de fiscal real acontece** — continua tudo homologação até VOCÊ subir cert + flipar `business.ambiente`/`nfse_provider_configs.ambiente` da biz=164 + confirmar a 1ª nota real no portal SEFAZ.

### ✅ MERGED — [W] "aprovo" (2026-06-03)
- **PR #2147 mergeado** (squash `--admin`, todo CI verde). Merge commit `77ced51`.
- Fix no ciclo: PHPStan ratchet acusou `undefined property $ambiente`/`$municipio_codigo_ibge` (Larastan não via as colunas Eloquent nos acessos novos). Resolvido com `@property` docblocks em `NfseProviderConfig` (commit `0f28ccb57`) → **PHPStan + Pest + 12 checks verdes**.
- Branch remota + worktree limpos. **NFS-e agora resolve ambiente per-business** — o cutover da biz=164 pode prosseguir sem vazar pra ROTA LIVRE.
- ⚠️ **Nada de fiscal real tocado.** Continua 100% homologação até [W] executar o checklist acima (cert + flip + checkpoint SEFAZ).

