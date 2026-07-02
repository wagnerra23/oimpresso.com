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

## Estado de execução E2/E3 — conferência ADVERSARIAL (append 2026-07-02, base `origin/main dad0b113b6`)

> **A trilha FUNDIR NÃO estava completa.** Uma conferência inicial concluiu "tudo feito, só bookkeeping" — um painel adversarial de 3 céticos (US-órfãs · integridade-de-lápide · auditoria-do-registro) refutou isso com evidência dura. Padrão de causa-raiz: em 12 das 13 fusões o executor tombstoneou só o **BRIEFING** (redirect ⚰️), mas deixou os **SPECs portadores de US congelados in-place** — a maioria ainda `status: ativo`/`rascunho`, **sem** banner HISTORICAL e **sem** ponteiro pro receptor. Só Copiloto fechou o loop de verdade (`git mv` real, #3565). E 3 lápides **prometiam operações que nunca rodaram** ("arquiva em `_Ideias/`", "vira ROADMAP-avancado", "FUNDIDO em Mwart"). **Este PR corrige** — caminho sancionado (§"Depois da decisão" + regra E2 por-US): *marca o SPEC-fonte HISTORICAL com ponteiro* (alternativa barata à migração de 65 US) e *reconcilia as lápides que mentem com o disco*.

### FUNDIR — PR que criou a lápide-redirect (corrigido: #3559 era só CODEOWNERS)

| Fonte | Receptor | PR redirect | US-fonte órfãs? | Correção neste PR |
|---|---|---|---|---|
| Copiloto | Jana | #2750 + #3565 | não (0 US; 3 docs movidos) | — (loop já fechado) |
| Chat | Jana | #2750 | não (só comparativo) | — |
| MemoriaAutonoma | Jana | #2750 | **5 US-MA** | SPEC→`historical` + ponteiro Jana |
| LaravelAI | Jana | #2750 | **5 US-AI** | SPEC→`historical` + ponteiro Jana |
| PontoWr2 | Ponto | #2750 | **12 US-PONT** | SPEC→`historical` + ponteiro Ponto |
| Atendimento | Whatsapp | #2750 | não (refs a US-WA do Whatsapp) | — |
| FinanceiroAvancado | Financeiro | #2750 | **33 US-FINA** | SPEC→`historical` + ponteiro; lápide corrigida |
| Orcamento | Sells | #2757 | não | — |
| Site | Cms | #2750 | não | — |
| Modules | Admin | #2757 | não | — |
| _Showcase | _DesignSystem | #2750 | não | — |
| **_processo** | ~~Mwart~~ | #2750 | — | **UN-TOMBSTONE — não é morto: hub VIVO citado como Fonte em 8 SPECs** |
| TaskRegistry | TeamMcp | #2750 (emenda #2748) | **15 US-TR + 8 US-UI** | SPEC→`historical`, ponteiro nuançado (sistema→TeamMcp; UI SPEC-UI-FASE7 segue Fonte viva de ProjectMgmt US-TR-309) |

### MATAR — lápide (#2751) + correção das promessas falsas

| Fonte | Estado | Correção neste PR |
|---|---|---|
| BI | lápide dizia "comparativo arquiva em `_Ideias/`" — **nunca aconteceu** | texto → "comparativo congelado in-place (proveniência)" |
| Grow | idem BI | idem |
| EvolutionAgent | lápide OK (aponta ADS) | — |
| Tarefas | lápide OK | — |

### Lápides que mentiam (reconciliadas com o disco)
- **`_processo` "FUNDIDO em Mwart / estado vigente → Mwart" era FALSO e perigoso.** A pasta é um hub de processo VIVO: `BATCH-BACKLOG-34-2026-06-20.md` é citado como **Fonte de US aprovadas ("Aprovação [W]")** em 8 SPECs (Financeiro, Governance, Infra, Jana, PaymentGateway, RecurringBilling, Sells, Whatsapp). Foi tombstoneada quando tinha 1 doc e virou hub depois. **Correção: BRIEFING honesto (cross-cutting vivo), NÃO mover arquivos** (8 SPECs citam o caminho — mover quebraria). `MWART-CHECKLIST.md` fica com cross-ref a Mwart, sem mover. Reverter esta lápide serve a intenção original (consolidar órfãos MORTOS) — `_processo` não é morto.
- **BI/Grow "arquiva em `_Ideias/`"** — o `_Ideias/` nunca os recebeu; o `_COMPARATIVOS_INDEX.md` ainda linka o comparativo na própria pasta. Corrigido o texto (in-place, não `_Ideias/`).
- **FinanceiroAvancado "vira roadmap-avançado de lá"** — nenhum `ROADMAP-avancado` existe em `Financeiro/`. Corrigido: o wish fica HISTORICAL nesta pasta com ponteiro; sem prometer artefato inexistente.

### Também corrigido
- **`memory/INDEX.md`** — o índice-mestre listava ~10 pastas tombstoneadas como módulos vivos sem marcador. Marcadas ⚰️.
- **`ProjectMgmt/SPEC.md`** — referência a TaskRegistry qualificada (spec histórico; SPEC-UI-FASE7 segue Fonte funcional viva).

### Follow-up registrado (não neste PR)
- **Receptores mudos:** 10/10 receptores não anotam no próprio BRIEFING que absorveram a fonte (fusão invisível do lado que recebe). É navegação/estrutura, não mentira — fica como higiene menor separada.

### Fora do escopo
- **ADIADO — cluster Estoque** (Inventory/Produto/Purchase/StockAdjustment/StockTransfer): não tocado (decisão E1 + trava `sdd-fase-2.js`).
- **E2b re-seed Meili / E3 distiller:** outra frente (#3534/#3532/#3553) — ver [P11](_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md).

*Conferência adversarial E2/E3 (worktree `claude/e2e3-triagem-closure`) a partir de `origin/main dad0b113b6` em 2026-07-02. `knowledge-drift --check` = 0 ghosts novos.*
