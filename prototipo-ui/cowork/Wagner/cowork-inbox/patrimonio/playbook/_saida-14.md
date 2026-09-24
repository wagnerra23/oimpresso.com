---
sessao: "14"
titulo: Saída da thread 14 — provas de 09/10/11 no caminho flat
dono: "[CL]"
medido_em: 2026-09-23
base_medida: 66677a491878 (origin/main fresco)
arquivos_de_producao_tocados: 0
invalida: "o veredito 'arquivo ausente' das threads 09, 10 e 11 — as três telas existem no main no caminho flat"
---

# 14 · Saída — o placar para de dizer "arquivo ausente" para três telas em produção

## O que mudou
Em `00-INDICE.md` §7, só nos objetos `09`, `10` e `11`:

| thread | prova era | prova é |
|---|---|---|
| 09 | `resources/js/Pages/Patrimonio/Alocacoes/Index.tsx` | `resources/js/Pages/Patrimonio/Alocacoes.tsx` |
| 10 | `resources/js/Pages/Patrimonio/Manutencoes/Index.tsx` | `resources/js/Pages/Patrimonio/Manutencoes.tsx` |
| 11 | `resources/js/Pages/Patrimonio/Configuracoes/Index.tsx` | `resources/js/Pages/Patrimonio/Configuracoes.tsx` |

Cada `nota_provas` ganhou uma ERRATA no formato da thread 08 (o que era, o que é, como foi medido). Nenhum outro campo mudou (`git diff --numstat`: 6 linhas alteradas, 6+ / 6−).

## Placar (`node scripts/qa/placar.mjs --indice …/00-INDICE.md`, rc=0 nas duas)
- **antes** (controle positivo): `Patrimonio: entregue 5 de 19 · próximo 5 · em curso 0 · pendente 3 · bloqueada 6 · 1 sem recibo (08)`
  - 09, 10, 11: `sem _saida; resources/js/Pages/Patrimonio/<Tela>/Index.tsx (arquivo ausente)`
- **depois:** `Patrimonio: entregue 5 de 19 · próximo 5 · em curso 0 · pendente 3 · bloqueada 6 · 4 sem recibo (08, 09, 10, 11)`
  - 09, 10, 11: `(sem recibo) … sem _saida` — todas as provas verdes.

O número de entregues não mudou, e não deveria: 09/10/11 foram entregues pela antiga thread 06, com recibos `_saida-06-{alocacoes,manutencoes,configuracoes}.md`, e o placar procura `_saida-09/10/11.md`. O mesmo vale para a 08 (`_saida-06-bens.md`). Renomear ou duplicar recibo não é escopo desta thread.

## Checklist (§D da thread)
1. Os 3 `path` apontam o arquivo flat, conferido por `git ls-tree origin/main resources/js/Pages/Patrimonio/<Tela>.tsx` ✅
2. Nenhuma das três aparece como `arquivo ausente` depois ✅
3. `git grep 'Patrimonio/Bens/Index.tsx'` no `main`: **3**, não 0. Os três estão em prosa histórica (`08-bens.md:19` "era…", `14-*.md:36` o próprio checklist, `_saida-07.md:212`). Dentro do `00-INDICE.md`: **0**. Nenhuma prova regrediu ✅ (com a ressalva)
4. As erratas de 08/09 do [CL] presentes: 3 ocorrências de "D1 vive" / "ERRATA [CL] 2026-09-08" ✅
5. `resources/js/Pages/Patrimonio/_shared/` sem diff contra o `main` ✅
6. Placar no corpo do PR ✅
