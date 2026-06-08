---
page: /vendas · window.VendasPage
component: vendas-page.jsx (+ vendas-extras.jsx, vendas-ai.jsx, vendas-curation.jsx, vendas-shortcuts.jsx, vendas-tweaks.jsx, vendas-output.jsx, data-vendas.jsx)
repo_alvo: Inertia Sells/Index (lista) — NÃO confundir com Sells/Create (git Sells/Create.charter.md, telा de cadastro)
status: APROVADO por [W] (chat 2026-06-02 — "pode fazer sim, nada estranho"). Backfill que TRAVA o estado vivo (piloto aprovado). Mirror no git pendente (L-13).
owner: wagner
last_validated: 2026-06-02
validated_against: vendas-page.jsx @ cowork-2026-06-02
persona: Larissa (balcão 1280px) · Eliana [E] (financeiro/fiscal) · Wagner (governança)
identidade: verde 155 — accent ESCOPADO `.vendas-scope{ --accent: oklch(0.45 0.11 155) }` por cima do DS (ADR 0200 D-02, proposta). NÃO redeclara token global; o roxo canon (ADR 0235) segue intacto fora do escopo.
nota_atual: 9.5 (piloto aprovado [W])
irmao: Vendas.casos.md (8 UCs grounded no código)
tier: A
charter_version: 1
---

# Page Charter — /vendas (Index / lista)

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

- **Accent escopado verde 155** (`.vendas-scope{ --accent: oklch(0.45 0.11 155) }`) — herda os componentes do `ds-v5`, que se pintam sozinhos via `var(--accent)`. NÃO redeclara token global; roxo canon (ADR 0235) intacto fora do escopo.
- Migração pro `ds-v5`: aditiva, reuse-first. Gate visual F1.5 (antes/depois) obrigatório — é piloto, qualquer regressão de layout barra o merge.

---

## Refs

- Vendas.casos.md — 8 UCs grounded em `vendas-page.jsx`
- ADR 0110 — Cockpit V2 · ADR 0235 — roxo canon · ADR 0200 — DS é piso / accent escopado
- git Sells/Create.charter.md — a tela IRMÃ de cadastro (não confundir)

## Trilha do tempo
- 2026-06-02 · [CC] criou o charter (backfill) travando o estado vivo da lista 9.5, grounded nos 8 UCs. Passo 2 do `_PROPOSTA-0245` (trio antes de migrar pro v5). Aguarda confirmação de [W] das aprovações/reprovações + mirror no git.
