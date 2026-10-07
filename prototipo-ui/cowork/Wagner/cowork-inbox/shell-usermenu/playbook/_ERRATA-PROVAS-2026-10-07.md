---
errata: "00-INDICE.md (§json, campo provas)"
modulo: shell-usermenu
autor: "[CL]"
criado: 2026-10-07
base_lida: wagnerra23/oimpresso.com@main c8777ae00a
---
# Errata das provas do shell-usermenu — 2026-10-07

> As 3 threads estão **entregues**. O placar as mostra como `em curso (indecidível)` só porque as
> provas do índice são `execucao` e `runtime`, que o `scripts/qa/placar.mjs` não avalia. Este
> arquivo não edita o índice: o `00-INDICE.md` mora no espelho e editá-lo derruba o check do
> espelho em todos os PRs. A troca abaixo é para o [CC] aplicar no Cowork e devolver no próximo
> handoff.

## 1 · O que foi entregue

| thread | PR | recibo | o que está no `main` |
|---|---|---|---|
| 01 · Aparência | [#7864](https://github.com/wagnerra23/oimpresso.com/pull/7864) | `_saida-01.md` | 2 opções (Escuro "Padrão do balcão" · Claro "Escritório, luz alta"); o ✓ segue a escolha |
| 02 · Sair | [#7867](https://github.com/wagnerra23/oimpresso.com/pull/7867) | `_saida-02.md` | pergunta inline `Encerrar a sessão?`; Encerrar = `<a href="/logout">` |
| 03 · + Adicionar empresa | [#7254](https://github.com/wagnerra23/oimpresso.com/pull/7254) | `_saida-03.md` | `aria-disabled="true"` com o motivo no `title` |

## 2 · Provas novas (só tipos que o placar mede)

Cada padrão **não existia** no `Sidebar.tsx` antes do PR que o entregou, então a prova não
fica verde por acaso:

| thread | padrão | antes do PR | depois |
|---|---|---:|---:|
| 01 | `Padrão do balcão` | 0 | 1 |
| 02 | `aria-label="Encerrar a sessão?"` | 0 | 1 |
| 03 | `Criar empresa é ação de superadmin` | 0 | 1 |

O teste de cada thread entra como prova `arquivo` (os três specs existem no `main`).

```json
{
  "01": [
    { "tipo": "contem", "path": "resources/js/Components/cockpit/Sidebar.tsx", "padrao": "Padrão do balcão" },
    { "tipo": "arquivo", "path": "tests/sidebarAparencia.spec.tsx" }
  ],
  "02": [
    { "tipo": "contem", "path": "resources/js/Components/cockpit/Sidebar.tsx", "padrao": "aria-label=\"Encerrar a sessão?\"" },
    { "tipo": "arquivo", "path": "tests/sidebarSair.spec.tsx" }
  ],
  "03": [
    { "tipo": "contem", "path": "resources/js/Components/cockpit/Sidebar.tsx", "padrao": "Criar empresa é ação de superadmin" },
    { "tipo": "arquivo", "path": "tests/sidebarMenuSemantics.spec.tsx" }
  ]
}
```

As verificações humanas (`npm run lint && npx tsc --noEmit`, clique no vivo) continuam valendo como
texto do recibo, não como prova da máquina. Os `_saida` já registram o delta de lint/tsc (0 erros
novos) e por que o clique em produção não foi feito.

## 3 · Como foi conferido

Placar rodado no índice atual (antes) e numa cópia do índice com as provas acima, com os três
`_saida` copiados ao lado dela, numa pasta ignorada pelo git (depois):

```
ANTES  node scripts/qa/placar.mjs --indice .../shell-usermenu/playbook/00-INDICE.md
shell-usermenu: entregue 0 de 3 · próximo 0 · em curso 3 · pendente 0 · bloqueada 0
  01 [em curso ] (indecidível) Aparencia ...
  02 [em curso ] (indecidível) Sair ...
  03 [em curso ] (indecidível) + Adicionar empresa ...

DEPOIS node scripts/qa/placar.mjs --indice <cópia com as provas novas>
shell-usermenu: entregue 3 de 3 · próximo 0 · em curso 0 · pendente 0 · bloqueada 0
  01 [feito    ] Aparencia ...
  02 [feito    ] Sair ...
  03 [feito    ] + Adicionar empresa ...
```

Controle negativo: trocando o padrão da 01 por um texto inexistente, a 01 passa a acusar
`não contém`. A prova morde.

## 4 · Ressalva que caducou

Os três `_saida` dizem que os specs do Sidebar não rodam em nenhuma lane de CI. Isso era verdade em
setembro e não é mais: `.github/workflows/cockpit-sidebar-jsdom-gate.yml` roda `sidebarSair`,
`sidebarMenuSemantics` e `sidebarAparencia` em PR que toque o Sidebar (`paths:`). Mesmo assim a
prova `arquivo` só diz que o teste existe; quem diz que ele roda é essa lane.
