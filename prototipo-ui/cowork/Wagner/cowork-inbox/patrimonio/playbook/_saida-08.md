---
sessao: "08"
titulo: Saída da thread 08 — Bens (recibo retroativo)
dono: "[CL]"
medido_em: 2026-09-24
entregue_por: "#7035 — commit 45d24c862, mergeado em 2026-09-08"
arquivos_de_producao_tocados: 0
invalida: nada
---

# 08 · Saída — Bens já está no `main`

Este recibo é **retroativo**. A tela foi entregue em 08/09, quando a frente de UI ainda era a thread `06`, e o recibo daquela sessão ficou com o nome antigo: `_saida-06-bens.md`. O placar procura `_saida-08.md`, então mostrava a 08 como "sem recibo" mesmo com todas as provas verdes. Esta sessão **não executou** a thread: só registra a entrega que já existe.

## Evidência (medida em 2026-09-24 contra `origin/main`)
- `resources/js/Pages/Patrimonio/Bens.tsx` foi criado em `45d24c862` (#7035): `git log --diff-filter=A`, em clone completo (`is-shallow-repository = false`).
- `AssetController.php` contém `Inertia::render('Patrimonio/Bens'`.
- As provas da thread 08 no `00-INDICE.md` passam (`node scripts/qa/placar.mjs --indice …`).
- **Escopo:** esta thread cobre a **listagem**. `create`/`edit`/`show` seguem em Blade como Non-Goal do charter; essa parte agora é a thread **17**, que espera a decisão D-FORMS. Isso responde à nota da errata de 08/09 ("o estado correto é EM CURSO"): o resto do escopo virou thread própria.
