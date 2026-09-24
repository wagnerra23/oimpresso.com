---
sessao: "10"
titulo: Saída da thread 10 — Manutencoes (recibo retroativo)
dono: "[CL]"
medido_em: 2026-09-24
entregue_por: "#7045 — commit f0a2cf879, mergeado em 2026-09-08"
arquivos_de_producao_tocados: 0
invalida: nada
---

# 10 · Saída — Manutencoes já está no `main`

Este recibo é **retroativo**. A tela foi entregue em 08/09, quando a frente de UI ainda era a thread `06`, e o recibo daquela sessão ficou com o nome antigo: `_saida-06-manutencoes.md`. O placar procura `_saida-10.md`, então mostrava a 10 como "sem recibo" mesmo com todas as provas verdes. Esta sessão **não executou** a thread: só registra a entrega que já existe.

## Evidência (medida em 2026-09-24 contra `origin/main`)
- `resources/js/Pages/Patrimonio/Manutencoes.tsx` foi criado em `f0a2cf879` (#7045): `git log --diff-filter=A`, em clone completo (`is-shallow-repository = false`).
- `AssetMaitenanceController.php` contém `Inertia::render('Patrimonio/Manutencoes'`.
- As provas da thread 10 no `00-INDICE.md` passam (`node scripts/qa/placar.mjs --indice …`).
- ⚠️ **O `_saida-06-manutencoes.md` NÃO é o recibo desta tela.** Ele registra uma sessão anterior que **não** migrou a tela ("gate de dependência bateu") e entregou só o conserto de autorização D1 (#7034). A tela veio depois, no #7045, e por outra sessão. Ler aquele recibo como entrega da tela seria errado.
- **D1** (autorização): consertado no #7034, conforme o próprio `_saida-06-manutencoes.md`. A nota da thread 10 ("D1 vive em 6 sítios") é anterior a esse conserto.
- **Fora do escopo:** os formulários são a thread **19** (D-FORMS).
