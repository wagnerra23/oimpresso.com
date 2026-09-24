---
sessao: "09"
titulo: Saída da thread 09 — Alocacoes (recibo retroativo)
dono: "[CL]"
medido_em: 2026-09-24
entregue_por: "#7046 — commit 1cbe5b067, mergeado em 2026-09-08"
arquivos_de_producao_tocados: 0
invalida: nada
---

# 09 · Saída — Alocacoes já está no `main`

Este recibo é **retroativo**. A tela foi entregue em 08/09, quando a frente de UI ainda era a thread `06`, e o recibo daquela sessão ficou com o nome antigo: `_saida-06-alocacoes.md`. O placar procura `_saida-09.md`, então mostrava a 09 como "sem recibo" mesmo com todas as provas verdes. Esta sessão **não executou** a thread: só registra a entrega que já existe.

## Evidência (medida em 2026-09-24 contra `origin/main`)
- `resources/js/Pages/Patrimonio/Alocacoes.tsx` foi criado em `1cbe5b067` (#7046): `git log --diff-filter=A`, em clone completo (`is-shallow-repository = false`).
- `AssetAllocationController.php` contém `Inertia::render('Patrimonio/Alocacoes'`.
- As provas da thread 09 no `00-INDICE.md` passam (`node scripts/qa/placar.mjs --indice …`).
- **Fora do escopo:** a fusão das revogações na aba é a thread **16**; os formulários, a thread **18** (D-FORMS).
