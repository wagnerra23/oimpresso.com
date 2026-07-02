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

> **Preenchimento concluído (Claude, por autorização do Wagner):**
> - **2026-06-14** — `ok` nas linhas de evidência dura (FUNDIR / MATAR / GENUÍNO — defaults autorizados).
> - **2026-06-15** — Wagner decidiu os 4 temas de julgamento que estavam pendentes:
>   1. **Atendimento** → FUNDIR em Whatsapp (NÃO renomear o módulo — caro/arriscado por ganho cosmético). `ok`
>   2. **TaskRegistry** → porta própria (não fundir em TeamMcp — mistura 2 sistemas). `ok`
>   3. **Marketplaces** → manter separado (escopo ≠ Woocommerce). `ok`
>   4. **cluster Estoque** → criar SÓ a porta `Estoque` agora; a **repartição dos 29 docs do Inventory + consolidação de StockAdjustment/StockTransfer/Produto/Purchase fica `ADIADO`** (puro hygiene, alto custo/risco, sem urgência funcional).
>
> Semântica: só `ok` (e os verbos FUNDIR/MATAR/GENUÍNO) entram no E2; `ADIADO` e vazio NÃO entram.

## Tabela A — pastas sem BRIEFING.md (34)

| Pasta | Docs | Últ. commit real | Código existe? | Proposta fundamentada | Decisão Wagner |
|---|---:|---|---|---|---|
| Atendimento | 1 | 2026-05-17 | ❌ (telas `Pages/Atendimento` renderizadas por `Modules/Whatsapp`) | FUNDIR em Whatsapp — BRIEFING de lá já se intitula "Whatsapp / Atendimento" | ok (FUNDIR→Whatsapp; não renomear módulo) |
| Autopecas | 3 | 2026-05-10 | ❌ (wish formal `aguarda-sinal-qualificado`, piloto Vargas) | GENUÍNO — manter wish; criar porta mínima `status: wish` | ok |
| BI | 1 | 2026-04-25 | ❌ (só comparativo Capterra; nunca construído) | MATAR com lápide; comparativo arquiva em `_Ideias/` | ok |
| Chat | 1 | 2026-04-25 | ❌ (chat real vive em `Modules/Jana`) | FUNDIR em Jana (comparativo de chat) | ok |
| Comissao | 3 | 2026-05-15 | ❌ (feature-wish dormente — ADR 0151) | GENUÍNO — wish formal; porta mínima `status: wish` | ok |
| ComunicacaoVisual | 10 | 2026-05-17 | ✅ `Modules/ComunicacaoVisual` | GENUÍNO — criar porta (módulo real ativo sem BRIEFING) | ok |
| ConsultaOs | 4 | 2026-05-17 | ✅ `Modules/ConsultaOs` | GENUÍNO — criar porta | ok |
| Copiloto | 3 | 2026-05-15 | ❌ (renomeado → `Modules/Jana` em 2026-05-06/09; 3 docs Jana-Pro ficaram pra trás) | FUNDIR em Jana | ok |
| Estoque | 2 | 2026-06-06 | ❌ como módulo (cross-cutting core: SPEC ativo + DOC-RAIZ 2026-06-04) | GENUÍNO — criar porta cross-cutting; candidata a absorver Inventory/StockAdjustment/StockTransfer (P7) | ok — só criar porta; absorção P7 ADIADA |
| EvolutionAgent | 10 | 2026-05-09 | ❌ (spec-ready 2026-04-26, nunca construído; conceito sobreposto por ADS + TaskRegistry/MCP) | MATAR com lápide "(planejado — não existe; absorvido por ADS)" | ok |
| FinanceiroAvancado | 3 | 2026-05-12 | ❌ (`Modules/Financeiro` existe e tem pasta própria) | FUNDIR em Financeiro (vira ROADMAP-avancado de lá) | ok |
| Garantia | 3 | 2026-05-12 | ❌ (discovery 2026-05-12, sem sinal desde então) | GENUÍNO — wish; porta mínima `status: wish` | ok |
| Grow | 1 | 2026-04-25 | ❌ (só comparativo Capterra) | MATAR com lápide; comparativo arquiva em `_Ideias/` | ok |
| Inventory | 29 | 2026-05-15 | ❌ (26 dos 29 docs são RUNBOOKs/visual-comparisons de telas CORE: Produto/Purchase/Stock*) | FUNDIR — repartir: docs produto→Produto, purchase→Compras, stock\*→Estoque; SPEC wish cross-vertical → Estoque (P6/P7) | ADIADO (repartição dos 29 docs) |
| LaravelAI | 15 | 2026-05-06 | ❌ (pré-história do que virou `Modules/Jana`) | FUNDIR em Jana marcando HISTORICAL | ok |
| Marketplaces | 3 | 2026-05-12 | ❌ (wish formal `aguarda-sinal-qualificado` — ML/Shopee ≠ conector Woocommerce) | GENUÍNO — wish; porta mínima; NÃO fundir em Woocommerce (P15) | ok (manter separado) |
| MemoriaAutonoma | 2 | 2026-05-10 | ❌ (F1 implementada dentro do então Copiloto = Jana) | FUNDIR em Jana | ok |
| Modules | 1 | 2026-05-17 | ❌ (tela única `Pages/Modules/Index` = `ModuleManagementController` core) | FUNDIR em Admin — nome "Modules" é isca de ghost-name | ok |
| Officeimpresso | 9 | 2026-05-27 | ✅ `Modules/Officeimpresso` | GENUÍNO — criar porta (migração Martinho ativa) | ok |
| Orcamento | 1 | 2026-05-17 | ❌ (0 tsx; só charter draft órfão; quotation = domínio Sells) | FUNDIR em Sells | ok |
| PaymentGateway | 4 | 2026-06-03 | ✅ `Modules/PaymentGateway` | GENUÍNO — criar porta (módulo ativo: PIX Inter, Sicoob) | ok |
| Pcp | 4 | 2026-05-15 | ❌ (feature-wish dormente — ADR 0152) | GENUÍNO — wish formal; porta mínima `status: wish` | ok |
| PontoWr2 | 13 | 2026-05-06 | ❌ (renomeado → `Modules/Ponto`; pasta Ponto tem só 4 docs) | FUNDIR em Ponto (13 docs incl. adr/ + audits/) | ok |
| Produto | 2 | 2026-05-17 | ❌ como módulo (8 telas core `ProductController`; ≠ `Modules/ProductCatalogue`) | GENUÍNO — criar porta; recebe os RUNBOOKs de produto hoje em Inventory (P6) | ADIADO (cluster Estoque) |
| Purchase | 3 | 2026-05-17 | ❌ (telas core `PurchaseController`; `Modules/Compras` existe com porta) | FUNDIR em Compras (P5) | ADIADO (cluster Estoque) |
| Site | 1 | 2026-05-17 | ❌ (7 telas públicas Login/Register/Blog/Pricing) | FUNDIR em Cms | ok |
| StockAdjustment | 1 | 2026-05-17 | ❌ (2 telas core; RUNBOOKs estão em Inventory) | FUNDIR em Estoque (P7) | ADIADO (cluster Estoque) |
| StockTransfer | 1 | 2026-05-17 | ❌ (2 telas core; RUNBOOKs estão em Inventory) | FUNDIR em Estoque (P7) | ADIADO (cluster Estoque) |
| Tarefas | 1 | 2026-05-17 | ❌ (`Pages/Tarefas/Index.tsx` é stub sem controller/backend) | MATAR com lápide — tarefas do time = MCP (ADR 0070); de cliente = ProjectMgmt/Essentials | ok |
| TaskRegistry | 4 | 2026-05-29 | ❌ como `Modules/` (sistema vivo no MCP server — ADR 0070) | GENUÍNO — criar porta (alternativa: FUNDIR em TeamMcp) | EMENDA 2026-06-15 (Wagner): FUNDIR em TeamMcp — TaskRegistry roda dentro do MCP server |
| _DesignSystem | 62 | 2026-06-11 | meta (prefixo `_`) | GENUÍNO — criar porta leve (62 docs, hops=62 no knowledge-drift) | ok |
| _Ideias | 12 | 2026-04-24 | meta | GENUÍNO — porta leve índice da incubadora | ok |
| _Showcase | 1 | 2026-05-17 | ❌ (2 telas demo do design system) | FUNDIR em _DesignSystem | ok |
| _processo | 1 | 2026-05-09 | meta (só MWART-CHECKLIST.md) | FUNDIR em Mwart (pasta Mwart já tem porta) | ok |

## Tabela B — pares suspeitos de duplicata/rename (15 decisões de identidade)

| # | Par | Evidência | Proposta | Decisão Wagner |
|---|---|---|---|---|
| P1 | Copiloto ↔ Jana | rename 2026-05-06/09 incompleto; 3 docs órfãos | FUNDIR Copiloto→Jana | ok |
| P2 | PontoWr2 ↔ Ponto | rename incompleto; órfã tem 13 docs, porta nova tem 4 | FUNDIR PontoWr2→Ponto | ok |
| P3 | LaravelAI ↔ Jana | mesmo conceito, fase anterior; 15 docs | FUNDIR→Jana (HISTORICAL) | ok |
| P4 | MemCofre ↔ SRS | **JÁ RESOLVIDO**: lápide em MemCofre/BRIEFING + DEPRECATION-PLAN aprovado (Caminho 1) | nenhuma ação nova; manter lápide | ok |
| P5 | Purchase ↔ Compras | 2 pastas pro mesmo domínio (telas core EN + módulo PT scaffold) | FUNDIR Purchase→Compras | ADIADO (cluster Estoque) |
| P6 | Produto ↔ ProductCatalogue ↔ Inventory | docs de produto em 3 pastas; ProductCatalogue é módulo DISTINTO (catálogo público QR) | Produto = porta das telas core; ProductCatalogue intocado; Inventory reparte | ADIADO (cluster Estoque) |
| P7 | Estoque ↔ Inventory ↔ StockAdjustment ↔ StockTransfer | 4 pastas pro domínio estoque; Estoque tem o DOC-RAIZ canônico (2026-06-04) | consolidar em Estoque | ADIADO — só porta Estoque agora; consolidação adiada |
| P8 | Atendimento ↔ Whatsapp ↔ Chat | telas Atendimento são do Whatsapp; chat real é Jana | FUNDIR Atendimento→Whatsapp e Chat→Jana (alt.: RENOMEAR Whatsapp→Atendimento, nome de produto) | ok (FUNDIR Atendimento→Whatsapp + Chat→Jana; NÃO renomear módulo) |
| P9 | Tarefas ↔ TaskRegistry ↔ ProjectMgmt | stub UI ≠ sistema MCP ≠ módulo cliente | MATAR stub Tarefas; porta TaskRegistry; ProjectMgmt intocado | EMENDA 2026-06-15 (Wagner): MATAR Tarefas; TaskRegistry→FUNDIR em TeamMcp; ProjectMgmt intocado |
| P10 | FinanceiroAvancado ↔ Financeiro | wish avançado duplica pasta do módulo real | FUNDIR→Financeiro | ok |
| P11 | Orcamento ↔ Sells | quotation = `Transaction type:sell status:draft` (domínio Sells) | FUNDIR→Sells | ok |
| P12 | Site ↔ Cms | telas públicas vs módulo de conteúdo público | FUNDIR Site→Cms | ok |
| P13 | Modules ↔ Admin | tela manage-modules é core/Admin; nome colide com `Modules/` do código | FUNDIR→Admin | ok |
| P14 | EvolutionAgent ↔ ADS | ranqueador-de-ROI nunca construído; ADS é o decisor canônico | MATAR com lápide apontando ADS | ok |
| P15 | Marketplaces ↔ Woocommerce | escopos distintos (marketplaces ML/Shopee ≠ conector Woo) | MANTER separados (confirmar) | ok (manter separado) |

## Depois da decisão (não é deste PR)

1. **KL-E2** executa em lotes por módulo: fusões (`git mv` + redirect stub), renames, lápides padrão "(planejado — não existe)" e portas mínimas pra GENUÍNO.
2. **KL-E2b** re-seed do índice Meilisearch após cada lote (senão o recall busca nomes mortos).
3. Codemod/rename do módulo SEMPRE antes do anchor-backfill do mesmo módulo (regra de partição do plano-mãe).
4. Meta de scorecard afetada: `front_door_coverage` 62%→100% e `ghost_count` 27→0.

---
*Gerado por frente KL-E1 (branch `sdd/kl-identidade`) a partir de origin/main `afecf98f6` em 2026-06-12. Fontes: `node scripts/governance/knowledge-drift.mjs`, `git log` por pasta, grep de `Inertia::render` nos controllers, leitura dos SPEC/BRIEFING citados. Coluna "Decisão Wagner" preenchida 2026-06-14 (defaults autorizados + 4 pendentes de julgamento).*

---

## Estado de execução E2/E3 — conferido em `origin/main dad0b113b6` (append 2026-07-02)

> **Conferência, não re-execução.** Todas as linhas `ok` (FUNDIR/MATAR) já estavam aplicadas no `origin/main` quando esta seção foi escrita — cada uma resolvida por PR KL-E2 mergeado entre 2026-06-15 e 2026-07-01. Nada foi refeito (seria regressão/duplicação). Esta seção só **registra o PR que fechou cada linha** (prova, não promessa) e o padrão adotado. Verificado: `front_door_coverage` das 33 pastas da Tabela A = 100% (só `_Governanca`, pasta de roadmap-índice, fica sem BRIEFING, por design); `knowledge-drift --check` = 0 ghosts novos; `ghost-fix.test.mjs` verde.

### FUNDIR — lápide-redirect no BRIEFING apontando pro receptor (✅ executado)

| Fonte | Receptor | PR que fechou | Padrão |
|---|---|---|---|
| Copiloto | Jana | #3559 + #3565 | redirect **+ 3 docs Jana-Pro movidos** pra `Jana/` (loop fechado) |
| Chat | Jana | #2750 | redirect (proveniência congelada in-place) |
| MemoriaAutonoma | Jana | #2750 | redirect in-place |
| LaravelAI (HISTORICAL) | Jana | #2750 | redirect in-place |
| PontoWr2 (13 docs incl. adr/+audits/) | Ponto | #2750 | redirect in-place |
| Atendimento (módulo NÃO renomeado) | Whatsapp | #2750 | redirect in-place |
| FinanceiroAvancado | Financeiro | #2750 | redirect in-place — ⚠️ ver divergência (b) |
| Orcamento | Sells | #2757 | redirect stub |
| Site | Cms | #2750 | redirect in-place |
| Modules | Admin | #2757 | redirect stub |
| _Showcase | _DesignSystem | #2750 | redirect in-place |
| _processo | Mwart | #2750 | redirect in-place |
| TaskRegistry (EMENDA 2026-06-15) | TeamMcp | #2750 (emenda #2748) | redirect in-place |

### MATAR — lápide "(planejado — não existe)" (✅ executado, PR #2751)

| Fonte | Lápide aponta pra | Divergência |
|---|---|---|
| BI | sem sucessor ("não investir") | comparativo NÃO movido pra `_Ideias/` — ver (c) |
| Grow | sem sucessor | idem (c) |
| EvolutionAgent | `Modules/ADS` (decisor Dual-Brain) | idem (c) |
| Tarefas | MCP/TaskRegistry (time) · ProjectMgmt (cliente) | stub sem comparativo |

### P4 MemCofre↔SRS
Lápide já existia (DEPRECATION-PLAN Caminho 1) — nenhuma ação nova, conforme decisão. É o **precedente** do padrão in-place adotado nas fusões acima.

### Divergências texto-da-decisão × padrão executado (in-place, coerente com MemCofre)
- **(b) FinanceiroAvancado:** a decisão dizia "vira ROADMAP-avancado de lá"; o executor tombstoneou in-place e **não criou** `Financeiro/ROADMAP-avancado.md`. Sem impacto de correção — o SPEC é um wish ("planejado — não existe"), sem capacidade viva a migrar; in-place é o mesmo padrão de Chat/LaravelAI.
- **(c) MATAR (BI/Grow/EvolutionAgent):** a decisão dizia "comparativo arquiva em `_Ideias/`"; o executor congelou o `COMPARATIVO_CONCORRENCIA.md` in-place sob a lápide. `_Ideias/` não os contém. In-place preserva os links históricos append-only da própria lápide — mover agora seria cosmético e arriscaria quebra de links.
- Ambas são divergências **de forma**, não de correção. Ficam registradas aqui; mover fisicamente exige decisão explícita do Wagner (não é regressão a corrigir).

### Fora do escopo desta conferência
- **ADIADO — cluster Estoque** (Inventory, Produto, Purchase, StockAdjustment, StockTransfer): NÃO tocado aqui, conforme decisão E1 + trava do motor `sdd-fase-2.js`. Trabalho relacionado de ghost-repartition foi tratado em outra frente (P11/KL-E2 Estoque) — não é a fusão de pastas destes itens, que segue `ADIADO`.
- **GENUÍNO** (criar porta): fora da trilha FUNDIR/MATAR desta seção; portas criadas nos lotes #2750/#2751.
- **E2b re-seed Meilisearch / E3 distiller:** executados/parciais em outra frente (#3534/#3532/#3553) — ver [P11](_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md).

*Append por conferência E2/E3 (worktree `claude/e2e3-triagem-closure`) a partir de `origin/main dad0b113b6` em 2026-07-02. Sem re-execução; só registro de estado + PRs.*
