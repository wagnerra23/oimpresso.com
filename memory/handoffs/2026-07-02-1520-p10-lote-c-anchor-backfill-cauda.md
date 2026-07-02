---
date: "2026-07-02"
time: "15:20 BRT"
slug: p10-lote-c-anchor-backfill-cauda
tldr: "P10 SA-A5 LOTE C mergeado (#3642): 13 módulos da cauda ancorados (88 US, 0 ambíguas), refutador Fable-5 tier-superior pegou 5 erros semânticos que o Opus tier-igual aprovou, 6 entries ledger G5, e 3 gates required (ghost_count/doneness/entry-covers) resolvidos honestamente."
prs: [3642]
decided_by: [W]
related_adrs: ["0273-anchor-spec-codigo-formato-canonico-fluxo-novo", "0302-fonte-unica-doneness-anchor-aposenta-status-spec", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
next_steps: ["Continuar cauda P10: módulos us>0 0% restantes (rodar anchor-lint --json) fora dos lotes já feitos", "Triagem fila A6 §5 das 88 US ancoradas sem teste (baldes A/B/C — teste real vs _lacuna_)"]
---

# P10 LOTE C — anchor backfill da cauda (13 módulos, 88 US)

## Estado MCP no momento
- `origin/main` @ `3eb652f75d` = squash do #3642 (merge confirmado 18:11Z / 15:11 BRT).
- Brief hook: `curl` falhou (MCP flaky) — snapshot via git. Sem cycle/task tocado (tarefa de governança pura, sem entidade MCP).
- Lotes paralelos que correram no mesmo ledger: lote A (Ponto/Marketplaces/Infra #3630-3632), lote B (NFSe/Autopecas/ComVis #3627/3628/3638), charters (#3633-3636) — todos já em main.

## O que aconteceu
Pedido: LOTE C do backfill P10 SA-A5 — a cauda de módulos com `us>0` e coverage 0% **fora** dos lotes A/B. Lista derivada live de `anchor-lint --json`: **Cms, Superadmin, Woocommerce, Comissao, Garantia, ADS, Admin, Dashboard, Connector, Mwart, Mcp, TeamMcp, Spreadsheet**.

Protocolo idêntico às waves anteriores: 4 sub-lotes gerados por **Opus** (agentes isolados, áreas sem overlap) → refutados por **Fable-5 tier-superior em sessão fresca** (protocolo G5, §3 endurecido 2026-07-01). Cada âncora `**Implementado em:**` na gramática ADR 0273 — path verificado por `existsSync` @8af585a, nunca inventado; feature não construída = `_pendente_`; parcial = `_parcial_`.

**O tier-superior provou seu valor:** os 4 lotes haviam passado **0% num refutador Opus (tier-igual)**, e o **Fable pegou 5 erros semânticos reais** que o Opus aprovou como "honestos":
- **CONN-002**: âncora `DelphiSyncService.php` fora do caminho de `doProcessaDadosCliente` + cauda atribuía `saveEquipamento` à classe errada (é local `$this->` no LicencaComputadorController).
- **TEAM-002/003**: `McpTokenIssuer` (extração Wave 18 não-religada) citado como implementador da rota — real é `McpToken::gerar` inline / `encontrarPorRaw` via McpAuthMiddleware.
- **MCP-005..016**: `_pendente_` otimista → `_parcial_` (RAGAS gate vivo advisory; recall-eval agendado; sub-issues/roadmap/whats-active Tier2 construídos).

Todos corrigidos → re-refutados → **0% error rate final**. 6 entries append-only no ledger G5 (2 aprovações C1/C2 + reprovas honestas C3/C4 §6).

## 3 gates required que o backfill acionou (todos honestos, não contornados)
1. **ghost_count ratchet (GT-G3)**: `_pendente_` de Comissao/Garantia citavam `Modules/Comissao`/`Garantia` (inexistentes → regex `MOD_REF_RE`) + MWART-010 usava `Pages/Modules/Index.tsx` (falso-ghost `Modules/Index`). Fix: "módulo X (pasta ausente)" sem prefixo `Modules/`; MWART-010 reancorado em `ModuleManagementController.php`.
2. **doneness-lint (ADR 0302)**: 8 US Mwart `_parcial_ × status:todo` = conflito `aberto-com-âncora`. Por ADR 0302 (status **aposentado**, âncora = fonte única), retirado o token `status:` legado dessas 8 US.
3. **anchor entry/covers**: US recém-ancorada sem DoD/teste `@covers-us` morde. Baseline cresceu **+108** (dívida legada surfaceada, todas dos meus módulos) via `--emit-baseline`, com trailers `BASELINE-GROW`/`BASELINE-ABSORB`. Triagem de teste real deferida à fila A6 §5.

## Artefatos gerados (no #3642, squashed em main)
- 13 `memory/requisitos/*/SPEC.md` — +88 linhas `**Implementado em:**` (coverage 60.1% dos módulos → 100% cada).
- `governance/sdd-verification-ledger.json` — +6 entries G5.
- `memory/requisitos/_ANCHOR-REVIEW-QUEUE.md` — §1 linha "wave 3 LOTE C" (0 ambíguas).
- `governance/anchor-entry-baseline.json` — +108 (−39 shrink CRM reconciliado).

## Persistência
- **git**: #3642 merged em `origin/main@3eb652f75d`. ✅
- **MCP**: webhook GitHub→MCP propaga o SPEC/ledger em ~2min.
- **BRIEFING**: N/A (backfill de governança, não muda capacidade de módulo).

## Próximos passos pra retomar
`git worktree add -b <branch> <path> origin/main` fresco → `node scripts/governance/anchor-lint.mjs --json` → módulos `us>0` e `coverage 0%` restantes (ex: cauda ainda não coberta). Triagem fila A6 §5 (88 US sem teste → baldes A/B/C).

## Lições catalogadas
- **Tier-superior não é burocracia**: o mesmo lote passou 0% em Opus e reprovou em Fable — o refutador de tier igual tem correlação de erro (alucina igual). Confirma o endurecimento §3 do PROTOCOLO-REFUTADOR (2026-07-01).
- **Ledger append-only + N lotes paralelos = corrida de rebase infinita**: 6 rebases sucessivos (main andou 6×). Solução que quebrou o loop: `gh pr merge --auto --squash` (auto-merge) — GitHub fecha sozinho na 1ª janela verde, sem eu precisar pegá-la antes do próximo conflito.
- **Backfill de âncora aciona gates em cascata previsível**: anchor-lint → ghost_count → doneness → entry/covers. Cada um tem reconciliação canônica (não contornar): ghost=sem prefixo Modules/; doneness=retira status legado (ADR 0302); entry/covers=cresce baseline com trailer.
- **Módulo zumbi fica `_pendente_`**: Comissao/Garantia (feature-wish inexistente) nunca recebem âncora inventada; Inventory (FUNDIR na triagem de identidade) fica fora do P10.

## Pointers detalhados
- [P10 roadmap](../requisitos/_Governanca/roadmap/P10-sa-a5-a6-batches-ia-fila-wagner.md) · [PROTOCOLO-REFUTADOR-BACKFILL G5](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) · [ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) · [ADR 0302](../decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md) · [fila A6](../requisitos/_ANCHOR-REVIEW-QUEUE.md)
