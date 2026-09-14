---
sessao: "14"
titulo: provas de 09/10/11 apontam subpasta; o main é flat
dono: "[CL]"
base: f8e6e02876fc
prefixo: prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md
nao_toca: resources/js/** · Modules/AssetManagement/**
depende: —
---
# 14 · o placar diz "arquivo ausente" para três telas que estão em produção

## A · IDENTIDADE
- **âncora:** `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md` (**22.024 B**) :: `§7.threads` — objetos `09`, `10`, `11`, campo `provas[].path`.
- **oráculo (existência, não leitura):** `resources/js/Pages/Patrimonio/Alocacoes.tsx` 14.994 B · `Manutencoes.tsx` 19.025 B · `Configuracoes.tsx` 17.605 B.
- **precedente que fecha a discussão:** a errata de 08/09 na thread `08` já fez exatamente isto para Bens (`Bens/Index.tsx` → `Bens.tsx`), com o diagnóstico escrito: *"a prova NUNCA passaria, e o placar marcava 'pendente · arquivo ausente' para uma tela que está em produção"*.

## B · NÃO INVENTAR
- **Não mover arquivo de tela.** O layout **flat** é o que está mergeado e é o que o `PatrimonioSubNav` consome; quem está errado é a prova, não a produção.
- **Não reescrever o índice.** Editar **só** os 3 `path` (e o que a errata exigir na `nota_provas` de cada), preservando as duas erratas de 08/09 e as 10 saídas.
- **Não escrever estado.** `feito`/`pendente` é derivado pelo `placar-indice.mjs`; nenhum número vai no `.md`.

## C · O DEFEITO MEDIDO
| thread | prova declarada | o que existe no `main` |
|---|---|---|
| `09` | `resources/js/Pages/Patrimonio/Alocacoes/Index.tsx` | `Alocacoes.tsx` (flat) |
| `10` | `resources/js/Pages/Patrimonio/Manutencoes/Index.tsx` | `Manutencoes.tsx` (flat) |
| `11` | `resources/js/Pages/Patrimonio/Configuracoes/Index.tsx` | `Configuracoes.tsx` (flat) |

As três telas têm `Inertia::render` vivo (`AssetAllocationController:150`, `AssetMaitenanceController:262`, `AssetSettingsController:92`) e trio completo (`.tsx` + `.charter.md` + `.casos.md`). A prova de arquivo é o único motivo de elas contarem como pendência.

**Por que é a 1ª onda:** com o placar mentindo, qualquer decisão sobre "o que falta no Patrimônio" parte de um denominador errado — inclusive a sua. Consertar a régua antes de medir é mais barato que medir três vezes.

## D · COMO VALIDAR
1. Os 3 `path` apontam o arquivo **flat**; nenhum outro campo dos objetos `09`/`10`/`11` muda.
2. `placar-indice.mjs --indice … --root . --proximo` roda e **nenhuma** das três aparece como `arquivo ausente`. Rodar **antes** e **depois**, e colar os dois resumos no `_saida-14.md` — o "antes" é o controle positivo.
3. `git grep 'Patrimonio/Bens/Index.tsx'` = **0** (o precedente já foi corrigido; se voltar, alguém regrediu).
4. As duas erratas do [CL] de 08/09 (D1 vive · caminho do `placar-indice.mjs`) **presentes e intactas** — guarda.
5. `_shared/PatrimonioSubNav.tsx` intacto (guarda: nada de tela muda nesta thread).
6. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `00-INDICE.md` do playbook — **só ele**.
- **REUSAR:** o formato de errata que a thread `08` já usa (`nota_provas` dizendo o que era, o que é, e como foi medido).
- **CRIAR:** nada além do `_saida-14.md`.
- **NÃO TOCAR:** nenhuma Page, nenhum controller, nenhum teste, nenhuma outra thread.
- **PASSO A PASSO:** 1) rodar o placar e guardar o "antes" · 2) trocar os 3 paths · 3) rodar o placar e guardar o "depois" · 4) `_saida-14.md` com os dois resumos e o `invalida:` (esta thread **invalida** o veredito de pendência de 09/10/11).
- **DADO:** nenhum.
- **PARAR SE:** o placar acusar divergência entre o índice do `main` e a cópia que você abriu — a pasta local do Cowork está com **7** arquivos contra **24** no `main`: **remedir antes de escrever**.

## PRÉ / PÓS
- **antes:** 3 provas apontando subpasta inexistente; placar dizendo pendente.
- **depois:** 3 provas no caminho flat; placar refletindo o `main`.
- **quebra:** se os paths já estão flat, **não execute** — reporte e pare.

## PROVA
`00-INDICE.md` contém `Pages/Patrimonio/Alocacoes.tsx`, `Manutencoes.tsx` e `Configuracoes.tsx` · `_saida-14.md` com placar antes/depois · `PatrimonioSubNav.tsx` intacto (guarda).
