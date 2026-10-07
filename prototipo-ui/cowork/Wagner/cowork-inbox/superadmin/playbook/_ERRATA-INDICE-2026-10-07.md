---
sessao: "errata-indice"
titulo: "Superadmin — o placar mostra 03, 04 e 06 em curso por erro do índice, não por trabalho pendente"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main 4bc5c314b8
---
# Errata do `00-INDICE.md` do Superadmin (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho: a correção vale só quando o Cowork a fizer e o
próximo retorno a trouxer. Este arquivo diz o que mudar e por quê.

## Placar medido

`node scripts/qa/placar.mjs --indice` (em `c8777ae00a`; de lá até `4bc5c314b8` nenhum commit tocou
o playbook, `Modules/Superadmin`, `governance/design/targets` ou o `_INDEX-SECRETS`):

```
Superadmin: entregue 6 de 10 · próximo 0 · em curso 4 · pendente 0 · bloqueada 0
  03 [em curso ] Pacotes: create/edit → drawer (Pacotes/Index) — depende de A2 (não feita)
  04 [em curso ] Assinaturas: add/edit/edit_date → drawer (Assinaturas/Index) — depende de A2 (não feita)
  06 [em curso ] (indecidível) Páginas (superadmin::pages)
  A2 [em curso ] (indecidível) Remedir Negócios · Pacotes · Assinaturas · Dashboard
```

## 03 e 04 — entregues; o que as segura é a dependência de A2

| thread | PR | prova do json |
|---|---|---|
| 03 | [#8725](https://github.com/wagnerra23/oimpresso.com/pull/8725) | `PackagesController.php` não contém `view('superadmin::packages.edit')` — 0 ocorrências ✅ |
| 04 | [#8726](https://github.com/wagnerra23/oimpresso.com/pull/8726) (+ [#8733](https://github.com/wagnerra23/oimpresso.com/pull/8733), "Adicionar assinatura" no drawer do negócio) | `SuperadminSubscriptionsController.php` não contém `view('superadmin::superadmin_subscription.edit')` — 0 ocorrências ✅ |

As Blades `packages/create|edit` e `superadmin_subscription/add_subscription|edit|edit_date_modal`
não existem mais (`Resources/views/packages/` e `superadmin_subscription/` só têm `index.blade.php`).

A A2 é remedição de layout (`medidas/superadmin--*`), não pré-requisito dos formulários: as duas
threads foram entregues e mergeadas sem ela. Enquanto o `depende_threads: ["A2"]` ficar, as duas
seguem `em curso` até o lado produção da A2 ser medido.

**Pedido:** no json, tirar `"depende_threads": ["A2"]` da 03 e da 04.

## 06 — descartada por decisão [W]

`_DECISOES-W-2026-10-01b.md` revoga a 1ª rodada de D1 ("pode manter separado"; "a thread 06 sai
do placar"). O json já traz a resposta nova, mas mantém a thread 06 com a prova `execucao` de
redirecionar para o Cms, que contradiz a decisão. Detalhe em `_saida-06.md`.

**Pedido:** no json, retirar o objeto da thread `06` de `threads` (o placar não tem estado
"descartada") e, em `decisoes`, trocar o `"destrava": ["06"]` de D1 por `"destrava": []`; na §2,
D1 passar a mostrar a resposta ("manter separado do Cms").

## A2 — continua pendente, e não é erro do índice

Igual ao `_saida-A2.md`, reconferido:

- `memory/_INDEX-SECRETS.md`: 0 ocorrências de "visreg" — o lado produção depende do [W]
  catalogar o `VISREG_LOGIN_TOKEN` do staging.
- `superadmin-page.jsx`: 0 ocorrências de `ROTAS`; `governance/design/targets/roles/`: nenhum
  override do Superadmin — o lado protótipo depende do Cowork declarar a tabela tela → rota.

## Medição da correção

Apliquei as 3 mudanças acima (03 e 04 sem `depende_threads`; 06 fora de `threads`; D1 com
`destrava: []`) numa cópia do playbook fora do espelho (`storage/framework/cache/`, gitignored) e
rodei `node scripts/qa/placar.mjs --indice <cópia>/00-INDICE.md`:

| índice | resultado |
|---|---|
| corrigido | `Superadmin: entregue 8 de 9 · próximo 0 · em curso 1` — 03 e 04 `[feito]`, só A2 em curso |
| controle (índice atual, copiado do mesmo jeito) | `Superadmin: entregue 6 de 10 · próximo 0 · em curso 4` |

O controle bate com o placar do repositório, então a cópia mede o mesmo que o original.
