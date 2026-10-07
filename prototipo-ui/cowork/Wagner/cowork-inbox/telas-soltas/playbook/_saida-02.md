---
sessao: "02"
titulo: "Forja: 14 Pages levantadas, roteiro próprio escrito"
autor: "[CL]"
data: 2026-10-07
base: origin/main 836619f64d24
thread: 02-forja.md
veredito: "entregue — cowork-inbox/forja/playbook/00-INDICE.md com 7 threads e 1 decisão; placar parseia (rc=0)"
---

# _saida-02 · Forja sai das telas soltas

## 1 · Feito

- `prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/`: `00-INDICE.md` (levantamento + JSON) · `_SESSAO-FRIA.md` · fichas `A1`, `01`–`05`, `R1`.
- `node scripts/qa/placar.mjs --indice …/forja/playbook/00-INDICE.md` → `entregue 0 de 7 · próximo 6 · pendente 1`, rc=0.

## 2 · Como foi medido

Por máquina, não por grep no olho: `node scripts/qa/screen-coverage-map.mjs --screen <Mod/Tela>` nas 14 telas (trio, scorecard, e2e, visual-comparison, proto-baseline, UC↔teste, `related_prototype`) · `isPageScreenPath`/`pageNamespacePath` de `scripts/qa/page-path.mjs` pro denominador · `Inertia::render` nos controllers pras rotas · `governance/design/contracts/*.contract.json` e `governance/design/targets/` pro contrato e o alvo.

## 3 · Descobertas que corrigem o índice das telas soltas

- **"14 Pages em `Modules/Forja/Resources/js/Pages/`" está errado: são 13.** A 14ª tela do namespace `ads/Admin/` é `Graph.tsx`, que mora em `Modules/KB/Resources/js/Pages/ads/Admin/` (controller do KB, rota registrada no `routes.php` da Forja). Ficou fora do roteiro da Forja.
- **A §1 do índice das telas soltas marca o trio da Forja como "não conferi".** Medido: 5 de 13 com trio completo; 8 sem `casos.md`.
- **7 scorecards órfãos** (`forja-{activity,backlog,board,burndown,inbox,mywork,triage}-index.yaml`) apontam pra Pages que não existem.
- **O Gantt tem duas âncoras:** contrato diz `forja-gantt.jsx`, charter diz `forja-page.jsx`. Virou a D1 do roteiro novo.
- O `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` (2026-09-03, 11 ondas) foi absorvido por ponteiro, não copiado. Quais ondas landaram eu **não medi** — é a thread R1.

## 4 · Prefixo tocado

`prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/` (prefixo da thread) + este recibo. Nada em `Modules/Forja/`, nada no `00-INDICE.md` das telas soltas (é do Cowork; as correções acima ficam aqui pra ele aplicar).
