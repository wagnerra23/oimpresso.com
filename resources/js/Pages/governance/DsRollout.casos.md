---
casos: DS Rollout · Ledger de Conformidade DS · /governance/ds-rollout
irmaos: DsRollout.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-12"
---

# Casos de uso — /governance/ds-rollout

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Tradução F3 do protótipo Cowork `DS Rollout - Ondas e Testes`. Persona: Wagner [W] (decide onda a onda). O placar prova "tudo aplicado" mecanicamente — o teste-mãe desta tela é o próprio `scripts/ds-ledger.mjs`.

## UC-DSR-01 — O placar só mostra número que veio de gate rodando
Status: ⬜ (manual/visual — abrir com e sem `governance/ds-ledger.json`)
Com o artefato presente, o Ledger renderiza as linhas medidas + o carimbo `● medido @sha · timestamp`.
Sem o artefato, o `DsRolloutController` cai no `staticFallback()` e a tela mostra o badge âmbar
`▲ snapshot estático · TODO ledger` com `0%` — nunca um número real não-medido.
**Pronto quando:** apagar `governance/ds-ledger.json` faz a tela exibir "TODO ledger"; recriar via
`npm run ds:ledger -- --write` faz aparecer o `%` real carimbado com o SHA.

## UC-DSR-02 — O censo reproduz a "Medição real" do design
Status: 🧪 (cobertura futura — assert no output de `ds-ledger.mjs --json`)
`scripts/ds-ledger.mjs` mede por Page: eslint `ds/*` (cor arbitrária + status-text + primitivos nativos)
+ paleta Tailwind crua + `conformance-gate` + `components-tree-guard`. O piloto `Produto/Create` cai em
**tokens** (`stone/rose` cru) **e primitivos** (`<input type=checkbox>` nativo, pego por `ds/no-native-checkbox`).
**Pronto quando:** `node scripts/ds-ledger.mjs --json` lista `Produto` com `cells.tokens="no"` e `cells.primitivos="no"`.

## UC-DSR-03 — probe e dark nunca fingem verde
Status: ⬜ (manual/visual — inspecionar células das colunas Probe/Dark)
Probe G1–13 e Dark exigem render de browser (Camada 2). O censo estático NÃO os mede e renderiza `–`
("não medido") com tooltip explicativo — jamais `✓`.
**Pronto quando:** toda linha não-referência mostra `–` em Probe e Dark, com `title` "não medido por censo estático".

## UC-DSR-04 — O Ledger renderiza as linhas reais do census
Status: 🧪 (cobertura futura — assert que a tabela tem N=counts.screens+references linhas)
A tabela do Ledger é populada da prop `census.ledger` (não hardcoded). Linha-referência (`Atendimento`)
aparece com `★` e destaque, fora da conta do `%`. A barra de progresso reflete `census.progressPct`.
**Pronto quando:** o nº de linhas = `census.counts.screens + census.counts.references` e a barra = `progressPct%`.

## UC-DSR-05 — Banner da árvore de componentes reflete o guard
Status: ⬜ (manual/visual — conferir badge "árvore Components/")
O census carrega `treeGuard` (de `components-tree-guard`). Pass → badge verde `✓ canônica`; fail → badge
vermelho com a contagem de violações.
**Pronto quando:** o badge bate com `node scripts/components-tree-guard.mjs` (exit 0 = ✓).

## UC-DSR-06 — A tela é canon (não recria o débito do protótipo)
Status: 🧪 (cobertura: eslint `ds/*` = 0 nesta tela · CI ESLint ratchet)
Usa `@/Components/PageHeader` v3.8 (não o `shared/` congelado), `KpiCard`, primitivos de layout
`<Inline>/<Grid>` e tokens semânticos (`text-success`/`text-destructive`/`text-warning`) — zero `<style>`
OKLCH cru e zero violação `ds/*`.
**Pronto quando:** `eslint resources/js/Pages/governance/DsRollout.tsx` = 0 warnings `ds/*`.

## UC-DSR-07 — As ondas seguem Tier-0 (a tela não executa nada)
Status: ⬜ (manual — a tela é read-only; nenhuma ação muta token/tela)
A tela é puramente informativa/medição. Tokenizar cor e portar telas (as ondas) permanecem Tier-0,
travadas no "vai" de Wagner onda a onda — fora desta tela.
**Pronto quando:** não há nenhuma ação na tela que edite token, CSS ou Page.

## UC-DSR-08 — Acesso (auth + permissão)
Status: ⬜ (manual — rota sob `auth` + `governance.dashboard.view`)
`/governance/ds-rollout` exige login e a permissão `governance.dashboard.view` (mesma família das demais
telas de governança). Item de nav só aparece com a permissão.
**Pronto quando:** usuário sem `governance.dashboard.view` não vê o link nem acessa a rota.
