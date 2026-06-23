---
page: /vendas · window.VendasPage
component: vendas-page.jsx (+ vendas-extras.jsx, vendas-ai.jsx, vendas-curation.jsx, vendas-shortcuts.jsx, vendas-tweaks.jsx, vendas-output.jsx, data-vendas.jsx)
repo_alvo: Inertia Sells/Index (lista) — NÃO confundir com Sells/Create (git Sells/Create.charter.md, telा de cadastro)
status: APROVADO por [W] (chat 2026-06-02 — "pode fazer sim, nada estranho"). Backfill que TRAVA o estado vivo (piloto aprovado). Mirror no git pendente (L-13).
owner: wagner
last_validated: 2026-06-03
validated_against: vendas-page.jsx @ cowork-2026-06-03 + git `resources/js/Pages/Sells/Index.charter.md` v6 ✓ lido @main 2026-06-03
canon_no_git: `resources/js/Pages/Sells/Index.charter.md` v6 (status live · review_trigger 2026-06-15) — ESTE charter local é a memória de protótipo; o git v6 é a LEI (L-13/L-grounding)
regua: ds-v6/gabarito-vendas.html  # CONTRATO ÚNICO da tela (aprovado · grounded /sells). A impl viva (vendas-page.jsx/vendas.css) CONFORMA a isto; não é régua. Ler ANTES de buildar (L-29).
ds: v6  # accent ROXO --accent (ADR 0190) · tokens DS direto (--pos/--warn/--neg/--origin-*/--stage-*) · sem --vd-* · zero oklch cru · 2 temas. v5 = histórico.
persona: Larissa (balcão 1280px) · Eliana [E] (financeiro/fiscal) · Wagner (governança)
identidade: ROXO `--accent` (ADR 0190 universal · conformado ao gabarito v6 em 2026-06-03). Verde-155 era D-02 **NÃO-aprovado** → RESOLVIDO = roxo. Positivo semântico segue `--pos` (verde). NÃO redeclara token global.
contrato_layout:  # UCs verificáveis = o que o gate/probe confere (ver Vendas.casos §Conformância DS). Isto é o contrato que torna real.
  UC-V09: accent do primary == roxo `--accent` (não verde) · ✓ verificado
  UC-V10: cor crua em regras de tela == 0 (vendas.css+styles.css+pg-styles+financeiro · exceção provada = .vd-trans papel A4 + scrim preto) · ✓ verificado 411→0
  UC-V11: detalhe = drawer lateral ~480–560px, NÃO modal central (era 720/878 → corrigido 560) · ⚠ NÃO re-verificado após o fix
  UC-V12: 2 temas (claro/escuro) legíveis, card usa `--surface` · ✓ verificado
  KPI-LAYOUT: 4 KPIs em 1 LINHA em ≥1180px (Larissa 1280); 2 col ≤1180 · ✓ verificado
nota_atual: 9.5 (piloto aprovado [W])
irmao: Vendas.casos.md (8 UCs grounded no código)
tier: A
charter_version: 2
---

# Page Charter — /vendas (Index / lista)

> ⚰️ **ESTE ARQUIVO NÃO É FONTE DE VERDADE (rebaixado 2026-06-03 · [W] "junção das memórias = regressão").**
> **Fonte única = git `resources/js/Pages/Sells/Index.charter.md`.** Este é **sketch upstream descartável** (F1): serve pra eu propor delta, **morre no F3** (vira lápide). NÃO mantenho isto sincronizado como canon paralelo — mantê-lo vivo é o que gerava divergência (verde×roxo, contadores). Mudou algo? **vai pro charter do git**, não aqui.

> **Status:** memória-por-tela (L-14), backfill DEPOIS do build pra travar o conceito da tela **piloto aprovada** (9.5). Captura o que está vivo e aprovado pra eu não regredir nem repetir pergunta. [W] confirma aprovações/reprovações explícitas.
> **Padrão visual:** Cockpit V2 (ADR 0110) · golden = `Sells/Create` (10 regras GOLDEN-REFERENCE).
> Personas: Larissa (balcão, teclado-first) · Eliana (fiscal) · Wagner (governança).

---

## Mission

A lista de vendas da gráfica: ver **o dia em números**, achar qualquer venda por visão salva/filtro, abrir o detalhe com o **fiscal grudado** (NF-e de produto + NFS-e de serviço), e cobrar o que está a receber. A pergunta que responde primeiro é **"o que faturei e o que falta receber hoje?"** — com a venda que nasceu na Oficina aparecendo aqui sem retrabalho.

---

## Goals — Features (PRECISA TER)

**Vivo hoje (aprovado / manter):**
- **KPI hero** "Faturado hoje" + sparkline 30d, "Ticket médio", "A receber" (`vd-kpi-hero`) — _UC-V01_
- **Nova venda** num toque: botão + tecla **N** + **⌘K** — _UC-V02_
- **Visões salvas (árvore):** Pendentes (por vendedor) · Faturadas (B2B/B2C) · Origem (balcão/oficina/online) · Favoritas (`topView`/`subView`) — _UC-V03_
- **A receber com ageing/SLA:** KPI marca alerta + CTA "ver estouradas" filtra os títulos vencidos (`slaCounts` · view `atrasadas`) — _UC-V04_
- **Fiscal no drawer:** `VdFiscalCard` por documento — **NF-e** (produto) + **NFS-e** (serviço), número/chave SEFAZ 44 díg. copiável — _UC-V05_
- **Ponte Oficina→Vendas:** OS pronta gera venda derivada (origin=oficina); listener `oimpresso:open-venda{id}` abre; filtro Origem=oficina lista as derivadas — _UC-V06_ (contraparte do UC-08 da Oficina)
- **⌘K** teclado-first: Nova venda · Emitir NF-e em lote · Buscar por chave SEFAZ — _UC-V07_
- **Emitir NF-e em lote:** seleção > 0 → fluxo de emissão em lote (`setBulkEmitOpen`) — _UC-V08_

---

## Non-Goals — Features (NÃO faz)

- ❌ **Não é POS de balcão.** O "Create POS" foi **aposentado** (2026-06-01); a superfície de criação rica migrou pra **Oficina/OS** (documento vivo). Vendas = lista + detalhe + fiscal + cobrança, não cupom de caixa.
- ❌ Cobrança via WhatsApp cliente-facing (proibição charter)
- ❌ Detalhe em modal full-screen (canon = drawer lateral / Sheet)
- ❌ Emissão fiscal completa na tela (aqui só dispara + mostra status/chave; o motor vive nos módulos NFe/NFSe)
- ❌ Inglês em UI cliente-facing

---

## UX Targets

- Cabe em 1280px sem scroll horizontal (Larissa) — KPIs e árvore refluem, nunca esmagam
- Teclado-first: N (nova) · ⌘K (paleta) · `/` (busca) · J/K (navegar) — Larissa quase não usa mouse
- Fiscal split visível: produto→NF-e, serviço→NFS-e, sempre os dois cards quando há os dois
- A receber estourado salta aos olhos (alerta no KPI, 1 clique pros vencidos)
- escala warm semântica (emerald pago · amber vencendo · rose atrasado), zero cor crua
- 0 erros JS console

---

## UX Anti-patterns (REPROVADO — não repetir)

- ❌ **Recriar o POS de balcão aqui** — foi decisão de [W] aposentar e migrar pra Oficina/OS. Não ressuscitar create pesado na lista.
- ❌ **Esconder o split fiscal** (mostrar só "NF" genérica) — produto e serviço têm documentos distintos; o card tem que separar.
- ❌ Cor crua fora dos tokens / `--<prefixo>-*` inventado — identidade só por `.vendas-scope{--accent}` herdando os componentes do DS
- ❌ `rounded-xl+` · `font-bold` em h1 · modal full-screen pra detalhe
- ❌ Mexer na estrutura sem ler este charter + casos antes (a tela é **piloto aprovado** — regressão aqui é cara)

---

## Identidade & DS

- **Accent = ROXO `--accent` universal** (ADR 0190/0235 · conformado ao gabarito `ds-v6` em 2026-06-03). ~~Verde-155 escopado~~ era a **D-02 NÃO-aprovada → RESOLVIDO = roxo** (corrige a contradição interna deste arquivo: o frontmatter já dizia roxo, este corpo dizia verde · 2026-06-04). Componentes do `ds-v5` se pintam sozinhos via `var(--accent)`; positivo semântico segue `--pos`. NÃO redeclara token global.
- Migração pro `ds-v5`: aditiva, reuse-first. Gate visual F1.5 (antes/depois) obrigatório — é piloto, qualquer regressão de layout barra o merge.

---

## Refs

- Vendas.casos.md — 8 UCs grounded em `vendas-page.jsx`
- ADR 0110 — Cockpit V2 · ADR 0235 — roxo canon · ADR 0200 — DS é piso / accent escopado
- git Sells/Create.charter.md — a tela IRMÃ de cadastro (não confundir)

## Diferenças vs git `Sells/Index.charter` v6 (✓ lido @main 2026-06-03)

> Reconciliação 2026-06-03. O git está **live/v6** (onda KB-9.75 P0/P1, PRs #1638/#1641/#1644/#1648/#1649). O protótipo local foi a **semente visual** (`visual_source: prototipo-ui/vendas-page.jsx`) e em pontos está **à frente**, em outros **atrás**. Tudo abaixo é PROPOSTA (não-lei até ADR/[W] · L-03/L-17); Tier-0 = [W].

### A · Git tem, local NÃO tem (gaps a fechar se [W] retomar Vendas)
- ⚠️ **P0 multi-tenant — `localStorage` por business:** git exige prefixo `oimpresso.sells.b<bizId>.` (anti-hook Tier 0 · ADR 0093). Local usa `oimpresso.sells.` **sem bizId** (`vendas-page.jsx` lsGet/lsSet ✓ lido cowork). Portar como está = vazamento cross-tenant de estado. **É o item mais sério.**
- **VdNextActionPanel** — painel FSM cockpit com emojis canon ✓/📄/📦/💰/⊘ (PR #1641, override aprovado). Local tem `VdStepper` (dots) + lista "Próximos passos" no transcript — ≠ o componente canon.
- **Validações fiscais BR** (`validacoesFiscaisBr.ts`) — git BLOQUEIA emitir sem CNPJ/CPF/idEstrangeiro (anti-hook). Local não trava emissão por documento.
- **Botão "Criar OS"** idempotente do drawer (1 venda → N OS · Modules/Repair · ADR 0192). Local só tem o listener **inbound** (oficina→venda); falta o **outbound**.
- **Saved view "Aguardando faturamento"** (`payment_status !== 'paid' && fiscal_status === null` · PR #1644). Local tem hoje/pendentes/faturadas/origem/favoritas/atrasadas/rejeitadas — não essa.
- **Emit modais single** `VdNfeEmitModal` + `VdNfseEmitModal` do drawer FISCAL (PR #1644). Local tem bulk (`setBulkEmitOpen`) mas não os single canônicos.

### B · Local tem, git NÃO lista — e DOIS são CONFLITO com anti-hook do git
- 🔴 **CONFLITO — IA no ⌘K palette:** local chama `window.claude.complete` dentro da paleta de busca (`askAi`). Git anti-hook é explícito: *"NÃO chama LLM em filtros/listagem — Copiloto +IA dispara APENAS via botão explícito drawer"* (✓ lido @main). Resolver: mover IA pro drawer (`SaleAiPanel`) **ou** [W] decide manter na palette (Tier 0).
- 🔴 **CONFLITO — PDV F2 / "Abrir PDV balcão":** local mantém em Visões ▾ + handler F2 (`window.__vendasPdvOpen`). Git Non-Goal: *"Create POS aposentado"* / *"NÃO /sells/create Cowork"*. O próprio charter local já diz **POS aposentado** nos Non-Goals → **inconsistência interna do artefato local** (charter diz aposentado, código ainda tem F2). Limpar.
- **Coluna Comissão + Ranking vendedores:** git tem `CommissionSplitEditor` (componente) + aba FOCO Comissão, mas **não** lista coluna Comissão na tabela. Local adicionou ambas. Candidato a PROPOR pro git (se [W] quiser).
- **Placa Mercosul inline** na tabela de Vendas (vertical mec). Git tem placa na Oficina/Repair, não no charter Sells. Domínio Martinho (caminhão pesado · ADR 0194) → propor.

### C · Divergência INTENCIONAL (não "consertar")
- **Sem PageHeader v3:** git pede PageHeader v3 (ADR 0180/0182/0189); local usa `os-head`/`vd-head-clean`. **[W] odeia page header** (preferência registrada no STATUS · L-28). NÃO propor adicionar PageHeader — a divergência é alinhada ao gosto de [W].
- **"Foco" 3 (Caixa/Faturamento/Comissão)** vs git **SubNav 4 abas + segmented Operacional/Financeira/Produção** (ADR 0178). Vocabulário diferente; mesma intenção. Reconciliar nomes só via [W] (não-trivial).

### Alinhado (✓ os dois)
KPI hero+sparkline · A receber + ageing 0-30/31-60/+60 + SLA · saved tree "Por origem ▾" (ADR 0192) · listener `oimpresso:open-venda` · WhatsApp 3-tab Confirmação/Retirada/Cobrança · NF-e+NFS-e split no drawer (chave SEFAZ 44 díg) · ⌘K/?/J/K/Enter/B (KB-9.75) · "Emitir cobrança" (`pg-vendas-integration.jsx` · ADR 0144) · escala warm sem cor crua.

---

## Trilha do tempo
- 2026-06-03 · [CC] **v1→v2: aterrou no git** (✓ lido `Sells/Index.charter` v6 + `Index.review.md` @main). Adicionada a seção "Diferenças vs git v6" (gaps A · conflitos+local-ahead B · divergência intencional C). Correção da L-grounding: o charter v1 era backfill do protótipo e **não referenciava o git v6** — agora referencia. Nenhuma linha do v1 reescrita (append). Plano + ponte: ver sessão `2026-06-03-vendas-reconciliacao-git.md`.
- 2026-06-02 · [CC] criou o charter (backfill) travando o estado vivo da lista 9.5, grounded nos 8 UCs. Passo 2 do `_PROPOSTA-0245` (trio antes de migrar pro v5). Aguarda confirmação de [W] das aprovações/reprovações + mirror no git.
