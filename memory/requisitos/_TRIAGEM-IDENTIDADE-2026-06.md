# Triagem de identidade — pastas órfãs de `memory/requisitos/` (2026-06)

> **Frente KL-E1** do [plano de reestruturação SDD](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) (Semanas 1-2 · "E1 tabela identidade — Wagner 15min"). Contexto: [audit 2026-06-12](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md).
> **Este doc só PREPARA. A decisão é 100% do Wagner.** Nenhuma fusão/rename/lápide é executada por este PR — execução é KL-E2 (renames/fusões) + KL-E2b (re-seed Meilisearch), SÓ depois da coluna "Decisão Wagner" preenchida.
>
> **Como preencher (~15 min):** na coluna vazia, escreva `ok` (aceita a proposta) ou a alternativa (`FUNDIR em X` / `RENOMEAR pra Y` / `MATAR` / `GENUÍNO`). Linhas sem decisão não entram no E2.

## Números re-derivados de origin/main `afecf98f6` (anti-stale — divergem do plano)

- `memory/requisitos/` tem **73 pastas**; **34 sem `BRIEFING.md`** (porta ausente).
- Dessas 34, **22 têm ≥2 docs** — são exatamente os "22 órfãos" do `knowledge-drift.mjs` (39/61 portas, 64%); as outras **12 têm 1 doc só** e ficam ABAIXO do radar do script (`docs.length < 2` é pulado) — incluídas aqui mesmo assim.
- Coluna "último commit real" exclui os 2 mass-commits de 2026-06-08 (squash #2413 + restore), que tocaram todas as pastas e mascaram freshness.
- Pares de identidade re-derivados: **15** (plano citava 7) — ver Tabela B.

## Tabela A — pastas sem BRIEFING.md (34)

| Pasta | Docs | Últ. commit real | Código existe? | Proposta fundamentada | Decisão Wagner |
|---|---:|---|---|---|---|
| Atendimento | 1 | 2026-05-17 | ❌ (telas `Pages/Atendimento` renderizadas por `Modules/Whatsapp`) | FUNDIR em Whatsapp — BRIEFING de lá já se intitula "Whatsapp / Atendimento" | |
| Autopecas | 3 | 2026-05-10 | ❌ (wish formal `aguarda-sinal-qualificado`, piloto Vargas) | GENUÍNO — manter wish; criar porta mínima `status: wish` | |
| BI | 1 | 2026-04-25 | ❌ (só comparativo Capterra; nunca construído) | MATAR com lápide; comparativo arquiva em `_Ideias/` | |
| Chat | 1 | 2026-04-25 | ❌ (chat real vive em `Modules/Jana`) | FUNDIR em Jana (comparativo de chat) | |
| Comissao | 3 | 2026-05-15 | ❌ (feature-wish dormente — ADR 0151) | GENUÍNO — wish formal; porta mínima `status: wish` | |
| ComunicacaoVisual | 10 | 2026-05-17 | ✅ `Modules/ComunicacaoVisual` | GENUÍNO — criar porta (módulo real ativo sem BRIEFING) | |
| ConsultaOs | 4 | 2026-05-17 | ✅ `Modules/ConsultaOs` | GENUÍNO — criar porta | |
| Copiloto | 3 | 2026-05-15 | ❌ (renomeado → `Modules/Jana` em 2026-05-06/09; 3 docs Jana-Pro ficaram pra trás) | FUNDIR em Jana | |
| Estoque | 2 | 2026-06-06 | ❌ como módulo (cross-cutting core: SPEC ativo + DOC-RAIZ 2026-06-04) | GENUÍNO — criar porta cross-cutting; candidata a absorver Inventory/StockAdjustment/StockTransfer (P7) | |
| EvolutionAgent | 10 | 2026-05-09 | ❌ (spec-ready 2026-04-26, nunca construído; conceito sobreposto por ADS + TaskRegistry/MCP) | MATAR com lápide "(planejado — não existe; absorvido por ADS)" | |
| FinanceiroAvancado | 3 | 2026-05-12 | ❌ (`Modules/Financeiro` existe e tem pasta própria) | FUNDIR em Financeiro (vira ROADMAP-avancado de lá) | |
| Garantia | 3 | 2026-05-12 | ❌ (discovery 2026-05-12, sem sinal desde então) | GENUÍNO — wish; porta mínima `status: wish` | |
| Grow | 1 | 2026-04-25 | ❌ (só comparativo Capterra) | MATAR com lápide; comparativo arquiva em `_Ideias/` | |
| Inventory | 29 | 2026-05-15 | ❌ (26 dos 29 docs são RUNBOOKs/visual-comparisons de telas CORE: Produto/Purchase/Stock*) | FUNDIR — repartir: docs produto→Produto, purchase→Compras, stock\*→Estoque; SPEC wish cross-vertical → Estoque (P6/P7) | |
| LaravelAI | 15 | 2026-05-06 | ❌ (pré-história do que virou `Modules/Jana`) | FUNDIR em Jana marcando HISTORICAL | |
| Marketplaces | 3 | 2026-05-12 | ❌ (wish formal `aguarda-sinal-qualificado` — ML/Shopee ≠ conector Woocommerce) | GENUÍNO — wish; porta mínima; NÃO fundir em Woocommerce (P15) | |
| MemoriaAutonoma | 2 | 2026-05-10 | ❌ (F1 implementada dentro do então Copiloto = Jana) | FUNDIR em Jana | |
| Modules | 1 | 2026-05-17 | ❌ (tela única `Pages/Modules/Index` = `ModuleManagementController` core) | FUNDIR em Admin — nome "Modules" é isca de ghost-name | |
| Officeimpresso | 9 | 2026-05-27 | ✅ `Modules/Officeimpresso` | GENUÍNO — criar porta (migração Martinho ativa) | |
| Orcamento | 1 | 2026-05-17 | ❌ (0 tsx; só charter draft órfão; quotation = domínio Sells) | FUNDIR em Sells | |
| PaymentGateway | 4 | 2026-06-03 | ✅ `Modules/PaymentGateway` | GENUÍNO — criar porta (módulo ativo: PIX Inter, Sicoob) | |
| Pcp | 4 | 2026-05-15 | ❌ (feature-wish dormente — ADR 0152) | GENUÍNO — wish formal; porta mínima `status: wish` | |
| PontoWr2 | 13 | 2026-05-06 | ❌ (renomeado → `Modules/Ponto`; pasta Ponto tem só 4 docs) | FUNDIR em Ponto (13 docs incl. adr/ + audits/) | |
| Produto | 2 | 2026-05-17 | ❌ como módulo (8 telas core `ProductController`; ≠ `Modules/ProductCatalogue`) | GENUÍNO — criar porta; recebe os RUNBOOKs de produto hoje em Inventory (P6) | |
| Purchase | 3 | 2026-05-17 | ❌ (telas core `PurchaseController`; `Modules/Compras` existe com porta) | FUNDIR em Compras (P5) | |
| Site | 1 | 2026-05-17 | ❌ (7 telas públicas Login/Register/Blog/Pricing) | FUNDIR em Cms | |
| StockAdjustment | 1 | 2026-05-17 | ❌ (2 telas core; RUNBOOKs estão em Inventory) | FUNDIR em Estoque (P7) | |
| StockTransfer | 1 | 2026-05-17 | ❌ (2 telas core; RUNBOOKs estão em Inventory) | FUNDIR em Estoque (P7) | |
| Tarefas | 1 | 2026-05-17 | ❌ (`Pages/Tarefas/Index.tsx` é stub sem controller/backend) | MATAR com lápide — tarefas do time = MCP (ADR 0070); de cliente = ProjectMgmt/Essentials | |
| TaskRegistry | 4 | 2026-05-29 | ❌ como `Modules/` (sistema vivo no MCP server — ADR 0070) | GENUÍNO — criar porta (alternativa: FUNDIR em TeamMcp) | |
| _DesignSystem | 62 | 2026-06-11 | meta (prefixo `_`) | GENUÍNO — criar porta leve (62 docs, hops=62 no knowledge-drift) | |
| _Ideias | 12 | 2026-04-24 | meta | GENUÍNO — porta leve índice da incubadora | |
| _Showcase | 1 | 2026-05-17 | ❌ (2 telas demo do design system) | FUNDIR em _DesignSystem | |
| _processo | 1 | 2026-05-09 | meta (só MWART-CHECKLIST.md) | FUNDIR em Mwart (pasta Mwart já tem porta) | |

## Tabela B — pares suspeitos de duplicata/rename (15 decisões de identidade)

| # | Par | Evidência | Proposta | Decisão Wagner |
|---|---|---|---|---|
| P1 | Copiloto ↔ Jana | rename 2026-05-06/09 incompleto; 3 docs órfãos | FUNDIR Copiloto→Jana | |
| P2 | PontoWr2 ↔ Ponto | rename incompleto; órfã tem 13 docs, porta nova tem 4 | FUNDIR PontoWr2→Ponto | |
| P3 | LaravelAI ↔ Jana | mesmo conceito, fase anterior; 15 docs | FUNDIR→Jana (HISTORICAL) | |
| P4 | MemCofre ↔ SRS | **JÁ RESOLVIDO**: lápide em MemCofre/BRIEFING + DEPRECATION-PLAN aprovado (Caminho 1) | nenhuma ação nova; manter lápide | |
| P5 | Purchase ↔ Compras | 2 pastas pro mesmo domínio (telas core EN + módulo PT scaffold) | FUNDIR Purchase→Compras | |
| P6 | Produto ↔ ProductCatalogue ↔ Inventory | docs de produto em 3 pastas; ProductCatalogue é módulo DISTINTO (catálogo público QR) | Produto = porta das telas core; ProductCatalogue intocado; Inventory reparte | |
| P7 | Estoque ↔ Inventory ↔ StockAdjustment ↔ StockTransfer | 4 pastas pro domínio estoque; Estoque tem o DOC-RAIZ canônico (2026-06-04) | consolidar em Estoque | |
| P8 | Atendimento ↔ Whatsapp ↔ Chat | telas Atendimento são do Whatsapp; chat real é Jana | FUNDIR Atendimento→Whatsapp e Chat→Jana (alt.: RENOMEAR Whatsapp→Atendimento, nome de produto) | |
| P9 | Tarefas ↔ TaskRegistry ↔ ProjectMgmt | stub UI ≠ sistema MCP ≠ módulo cliente | MATAR stub Tarefas; porta TaskRegistry; ProjectMgmt intocado | |
| P10 | FinanceiroAvancado ↔ Financeiro | wish avançado duplica pasta do módulo real | FUNDIR→Financeiro | |
| P11 | Orcamento ↔ Sells | quotation = `Transaction type:sell status:draft` (domínio Sells) | FUNDIR→Sells | |
| P12 | Site ↔ Cms | telas públicas vs módulo de conteúdo público | FUNDIR Site→Cms | |
| P13 | Modules ↔ Admin | tela manage-modules é core/Admin; nome colide com `Modules/` do código | FUNDIR→Admin | |
| P14 | EvolutionAgent ↔ ADS | ranqueador-de-ROI nunca construído; ADS é o decisor canônico | MATAR com lápide apontando ADS | |
| P15 | Marketplaces ↔ Woocommerce | escopos distintos (marketplaces ML/Shopee ≠ conector Woo) | MANTER separados (confirmar) | |

## Depois da decisão (não é deste PR)

1. **KL-E2** executa em lotes por módulo: fusões (`git mv` + redirect stub), renames, lápides padrão "(planejado — não existe)" e portas mínimas pra GENUÍNO.
2. **KL-E2b** re-seed do índice Meilisearch após cada lote (senão o recall busca nomes mortos).
3. Codemod/rename do módulo SEMPRE antes do anchor-backfill do mesmo módulo (regra de partição do plano-mãe).
4. Meta de scorecard afetada: `front_door_coverage` 62%→100% e `ghost_count` 27→0.

---
*Gerado por frente KL-E1 (branch `sdd/kl-identidade`) a partir de origin/main `afecf98f6` em 2026-06-12. Fontes: `node scripts/governance/knowledge-drift.mjs`, `git log` por pasta, grep de `Inertia::render` nos controllers, leitura dos SPEC/BRIEFING citados.*
