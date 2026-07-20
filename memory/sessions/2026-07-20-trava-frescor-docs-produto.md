# Sessão 2026-07-20 — Trava de frescor de documentação (docs ↔ tela, módulo Produto)

**Contexto:** conversa de método (pouco código). Começou com "quantos arquivos de documentação
são necessários pra construir uma tela?" e evoluiu pro design de uma **trava** (gate automático) que
force os documentos a acompanharem mudanças de tela — pra a documentação não "morrer".

**Branch:** `test/financeiro-ci-baseline-lane` (STALE — ~+3291 / −5516 vs `origin/main`).
Nada implementado; só discussão + este registro + levantamento de inventário. Trava real, se aprovada,
entra por **PR pra `main`**.

**Sessão irmã (mesmo dia, tema vizinho — enforcement por hook):**
[`2026-07-20-conversa-hooks-enforcement-protocolo.md`](2026-07-20-conversa-hooks-enforcement-protocolo.md).

---

## Pontos discutidos

1. **Quantos arquivos por tela.** Os 6 tipos que o Felipe listou (briefing, charter, casos de uso,
   teste, catálogo, mapa) mapeiam 1:1 no que o módulo Produto já tem: `BRIEFING.md`, `<Tela>.charter.md`,
   `<Tela>.casos.md`, Pest, `UI-CATALOG.md`, `SDD-tela-*.md`. **Só 2 são por-tela** (charter + teste);
   os outros 4 são **por-módulo** (a tela nova só ganha uma linha/seção). Custo marginal de uma tela
   nova ≈ **2 arquivos novos + ~4 appends de 1 linha**, não 6 arquivos.
2. **Briefing-como-índice.** Ideia certa e **já é política** (regra "BRIEFING zero-órfãos" + `related_docs:`
   no frontmatter do SDD). MAS resolve **descoberta** (achar o doc / evitar órfão), **não frescor**
   (o doc estar certo). Índice mantido à mão costuma ser o *primeiro* a apodrecer.
3. **O que mantém doc viva (estado da arte).** Só **dois** mecanismos estruturais:
   - **(a) Auto-geração** — o doc é uma projeção da fonte de verdade, não pode driftar. Ex. no projeto:
     `UI-CATALOG.md` ("auto-regeneravel"). Comprovado há décadas (Storybook, OpenAPI, TypeDoc).
   - **(b) Coupling gated** — CI/hook **impede terminar** sem atualizar o doc. Ex. no projeto:
     `mwart-gate.yml`, `charter-first`, `governance-gate`.
   - Demais abordagens (índice, ADR append-only, Diátaxis, SDD dirigido por IA) driftam ou dependem de
     disciplina. Provados em produção: auto-geração, BDD/executable-spec, ADR, gates CI. **SDD dirigido
     por IA** (GitHub Spec Kit / Amazon Kiro) é fronteira 2024–2026, **ainda não battle-tested** —
     registrado sem inflar.
4. **Diagnóstico do Produto.** Dos 6 docs: **catálogo** (auto-gen) + **charter/teste** (gated) estão
   vivos; **BRIEFING, casos e SDD/mapa** são "papel na geladeira" → vão driftar sem mecânica.
5. **Descoberta ao vivo (prova do problema).** O `SDD` lista `related_docs` (`SPEC.md`, `BRIEFING.md`,
   `RUNBOOK`s) que **existem no `origin/main` mas não no checkout local stale** — link apontando pra
   arquivo ausente é o problema "papel na geladeira" acontecendo em tempo real.

## Design da trava — 4 parâmetros definidos pelo Felipe

1. **Gatilho** = *qualquer* arquivo vinculado à tela (não só o `.tsx`).
2. **Mapa de vínculos** ("arquivo X mexido → doc Y obrigatório") = **trabalho do Claude** levantar e
   apresentar; Felipe não tem o conhecimento técnico pra montar.
3. **Palavra de escape** = conceito **aprovado**; o texto exato quem define é o **Wagner**.
4. **Onde mora** = **todos os pontos de entrada** (máquina local + servidor do time).

## Inventário levantado (`origin/main`) — matéria-prima do mapa de vínculos

- **8 telas:** Index, Create, Edit, Show, BulkEdit, SellingPrices, StockHistory, Unificado/Index.
- **Charters:** 8/8 (100%).
- **`casos.md`:** só **2/8** (Create, SellingPrices) — cobertura assimétrica.
- **RUNBOOKs (`_telas/`):** 7 (bulk-edit, create, edit, index, selling-prices, show, stock-history).
- **visual-comparison:** 7 + `produto-index-setor-matrix.md`.
- **Controllers:** `ProductController.php` (~2.729 LOC, serve VÁRIAS telas) + `ProdutoUnificadoController.php`.
- **Testes:** `Wave2*BaselineTest` + `Wave2*InertiaTest` por tela.
- **Docs módulo:** SPEC, BRIEFING, SDD, UI-CATALOG, CAPTERRA-FICHA, CAPTERRA-INVENTARIO, PARIDADE,
  ANTI-REGRESSAO×2, produtos-gap, PROTOTIPO-preco-especial, adr/arq/0001.

## Acordados

- Vale construir a trava pros **3 docs frágeis** (SDD/mapa, BRIEFING, casos).
- Só **2 mecanismos** mantêm doc viva (auto-geração + gate); índice sozinho **não basta**.
- Os **4 parâmetros** acima (gatilho / mapa é do Claude / escape aprovado / mora em todo lugar).
- **Nada implementado** — "ainda não" explícito do Felipe. Ordem atual: montar o **mapa de vínculos**
  primeiro, pra ele e o Wagner conferirem, antes de codar a trava.
- Trava entra por **PR pra `main`** (branch atual é stale).

## Desacordados / em aberto

- **Mapa de resposta "arquivo → doc obrigatório":** ainda **não construído** — a conversa virou pro
  resumo enquanto o inventário estava sendo levantado.
- **Vínculo tela ↔ controller é 1:N:** `ProductController` serve várias telas. "Qualquer arquivo
  vinculado" (param 1) esbarra nisso — mexer no controller não aponta pra *uma* tela. **Questão de
  design aberta.**
- **Assimetria de cobertura** (`casos.md` só 2/8): a trava vai *exigir* `casos.md` onde ele nem existe?
  Decisão pendente (exigir criação vs. só exigir atualização do que já existe).
- **Palavra de escape:** texto exato pendente do **Wagner**.
- **Onde a trava mora, no detalhe:** Felipe disse "todos os pontos"; falta traduzir em
  `settings.json` versionado (local) + workflow CI (time) — detalhamento técnico pendente.

---

_Registro append-only. Autor: Claude (Opus 4.8) + Felipe [F]. Sem PII. Sem mudança de código._
