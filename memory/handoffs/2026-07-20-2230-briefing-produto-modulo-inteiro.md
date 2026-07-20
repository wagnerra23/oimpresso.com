---
data: 2026-07-20
hora: "22:30"
slug: briefing-produto-modulo-inteiro
autores: [M, CC]
tema: "BRIEFING do módulo Produto reescrito de '8 telas' pra módulo inteiro (18 áreas), podado anti-apodrecimento, links validados"
prs: [4417, 4449, 4464]
status: aberto
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0121-oimpresso-modular-especializado-por-vertical]
---

# Handoff — BRIEFING do Produto (módulo inteiro) + trio do cadastro

## ⚠️ Correção de identidade (importante pra próxima sessão)
A usuária é a **Maiara [M]**, NÃO o Felipe. O e-mail do sistema é `felipe@wr2.com.br`, mas a memória canônica (MEMORY.md) diz "Usuária é Maiara". Vários commits desta sessão foram assinados **[F+CC] por engano** (já mergeados, não dá pra reescrever); do handoff em diante é **[M+CC]**. Wagner é a autoridade de aprovação (owner).

## O que foi feito nesta sessão

**3 PRs mergeados** (todos em `main`):
- **#4417** — trio do cadastro (`Create.casos.md` + `CadastroProdutoContratoTest` UC-PCAD-01/04/06). Achado no caminho: `create()` duplicar produto alheio dava **500** → corrigido `find()`→`findOrFail()`. Reclassificação [M]: UC-PCAD-02/03 NÃO eram bugs (validação client-side no Blade + defaults no form, por design UltimatePOS) → viraram gaps de paridade; UC-PCAD-05 (cross-tenant no `store()`) = achado Tier 0 real → **task própria**.
- **#4464** — premissa falsa do multiplicador corrigida em SDD/FICHA/INVENTARIO/BRIEFING + Errata na ADR ARQ-0001. **NÃO é "multiplicador oco"**: preço por tabela funciona (`fixed`/`percentage`); gap real = regra de tabela inteira (default por grupo). A nota C02/61 ficou marcada "sob revisão [W]".
- **#4449** — regra de processo "pedido explícito de contrato de tela" em `how-trabalhar.md`.

**BRIEFING do Produto reescrito** (o trabalho central — **PRONTO, aguarda merge**):
- Estava em "8 telas"; reescrito pra **módulo inteiro** após varredura completa (view/controller/utils/rotas/connector/prototipo-ui/tests) — a Maiara insistiu (e estava certa): eu operava com ~3 arquivos, o módulo tem ~50.
- **18 áreas** (8 cadastro React + 7 gestão só-Blade + 3 âncoras). **Estoque + Restaurant são âncoras relacionadas** (decisão Wagner — não saem, dependem do produto).
- **+ API REST no módulo Connector** (`/connector/api/product`) — eu tinha errado dizendo "api.php não tem produto".
- **+ origem/linhagem** (Delphi WR → OfficeImpresso → fork UltimatePOS/Blade → React; paridade mira 2 alvos: não regredir Blade + alcançar Delphi).
- **Poda anti-apodrecimento** (pedido Maiara): grades/status/`draft-live`/% saíram do corpo → apontam pros geradores (UI-CATALOG, casos-coverage, module:grade). Ficou estrutura + achados + recibo datado.
- **Todos os links validados** (49 refs, 0 broken) + critério de link explícito no doc.
- **Gates verdes no worktree:** charter-refs 0 broken · schema OK (module/status=parcial/owner=W) · briefing-code-staleness 0 stale.
- Este handoff **inclui o BRIEFING commitado** em `memory/requisitos/Produto/BRIEFING.md` (worktree `docs/briefing-produto-modulo-inteiro`).

## Próximos passos (pra continuar)

1. **Mergear este PR** (`docs/briefing-produto-modulo-inteiro`) se o CI ficar verde — é o BRIEFING pronto.
2. **Consolidação do mapa de paridade** (discutido, NÃO feito): o `PARIDADE-charter-vs-legado.md` já é o mapa Delphi×Blade×React; absorver nele o inventário de arquivos + ordem de execução; NÃO criar doc paralelo (o `MAPA-blade-react` que eu ia criar era duplicata). Decisão de rename pendente [W/M].
3. ~~UC-PCAD-05~~ **JÁ RESOLVIDO** — a sessão irmã (#4554, 2026-07-19, CT100-verificado) já pôs a validação de FK cross-tenant no `store():633`. O BRIEFING foi corrigido pra refletir isso ANTES do commit (eu tinha escrito "aberto" — erro pego na varredura de fechamento). Não há task pendente aqui.
4. **Trio das 6 telas sem casos.md** (Index, Edit, Show, BulkEdit, StockHistory, Unificado) — US-PROD-020.
5. **Preço no Create.tsx** (P0 da paridade) — bloco de preço + `product.js` não migrados.

## Estado MCP no momento do fechamento
- Brief #386 (SessionStart): HITL Wagner=2 (FIN-004, runbook on-prem). Em voo: Produto tem 2 itens ativos (BOM drag-drop G-06; [V0] preço-0 US-PROD-027).
- Handoffs irmãos recentes: `2026-07-19-2130-mapa-vivo-resolver-uc-pcad-05` · `2026-07-20-1138-kb-test-helper-fk-cycle-fix`.
- Base do worktree: `origin/main` @ `009bfa74c`.

## Lição de método (repetida nesta sessão)
Reincidi 5× em **concluir sem varrer completo** (proibicoes §5, 2026-07-15): tratei a casca React como o cadastro; disse "api.php não tem produto" sem ver o Connector; índice com links inconsistentes; briefing "8 telas" sem varrer os 11 controllers. A Maiara pegou cada um. **Varrer view+controller+utils+api ANTES de escrever o briefing** — não depois.
