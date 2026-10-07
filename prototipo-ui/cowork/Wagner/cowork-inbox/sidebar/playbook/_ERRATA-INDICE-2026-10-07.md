---
sessao: "errata-indice"
titulo: "Sidebar — o placar mostra 0 de 16 por causa do índice, não por trabalho pendente"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main ea4b4f0985
---
# Errata do `00-INDICE.md` da Sidebar (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho: a correção vale quando o Cowork a fizer e o próximo
retorno a trouxer. Este arquivo diz o que mudar, por quê, e o placar antes e depois.

Cobre as threads de classe (a) — entregues, com `_saida` e PR mergeado, presas só pelo índice:
**01 · 02 · 04 · 06 · 07 · 08 · 09 · 10 · 11 · 12 · 13 · 14 · 15**. Fora dela:
**03** (falta o diff do `CompanyPicker` nos dois sentidos, lado Cowork), **05** (dona [W]) e
**16** (`ancora.mjs` não resolve charter fora de `Pages/`; mudança de máquina pendente).

## Placar antes

`node scripts/qa/placar.mjs --indice` em `c8777ae00a`:

```
Sidebar: entregue 0 de 16 · próximo 0 · em curso 16 · pendente 0 · bloqueada 0
```

As 16 ficam `em curso` porque toda thread fecha com prova de recibo (`comparacao`, `execucao` ou
`revisao`), e o avaliador de recibo saiu do repo com a ADR 0397 — o placar marca a prova como
**não medida**. As que dependem de outra nunca veem a dependência `feita`.

## Causas, por thread

1. **01 — a pré-condição `nao_contem 'role="link"'` nunca passa.** A única ocorrência em
   `prototipo-ui/cowork/Wagner/sidebar.jsx` é o comentário da própria correção, linha 164
   (`` era `<div role="link" tabIndex={0}>` ``). O JSON abaixo troca o padrão por
   `role="link" tabIndex={0} onKeyDown`, que o comentário não contém. Alternativa igualmente
   válida: reescrever o comentário sem citar o literal (prefixo da 01) e manter a prova.
   Mesma classe da §5 2026-09-18 (sonda que casa o texto do próprio ato).
2. **04 — `depende_threads: ["01"]` sem conteúdo.** A 04 foi entregue antes (#7209, 2026-09-11)
   e não lê nada da 01. Sai a dependência.
3. **06 — `revisao` com `tests/` no prefixo.** Por contrato, `revisao` só fecha thread que escreve
   `.md`/`.contract.json`. A `_saida-06` registrou a troca para `execucao`; um export posterior a
   desfez, e os recibos gravados então ficavam em `prototipo-ui/design-docs/…`, árvore que a ADR
   0397 removeu.
4. **09 · 10 · 13 — `testes` por pasta inteira** (`tests/js/`, `tests/Feature/`). O JSON abaixo
   nomeia os arquivos que as `_saida` citam.
5. **14** — além do contrato Pest, o spec de render `tests/Feature/Sidebar/ghost-icone.spec.tsx`
   (entra na lane `cockpit-sidebar-jsdom-gate` pelo #8893).
6. **15 — prefixo errado.** O índice diz `scripts/design/`; a thread mudou
   `scripts/design-sync/alvo.mjs` e `scripts/qa/secao-check.mjs` (`scripts/design/alvo.mjs` não
   existe). A `_saida-15` já pedia.

## A decisão que o JSON abaixo toma, e que é do Cowork confirmar

Para as 13 threads, a prova de recibo foi **trocada por provas estruturais** que o placar mede
(`arquivo` / `contem` / `nao_contem`): o código entregue, o alvo versionado, o arquivo de teste.
A execução dos testes passa a ser provada pelo CI — os specs jsdom rodam na
`cockpit-sidebar-jsdom-gate` e os Pest na lane sqlite —, não pelo placar. Isso é mais fraco que
um recibo: `arquivo` prova que o teste existe, não que passou. A outra saída é portar de volta o
avaliador de recibo (o docblock de `scripts/qa/placar-indice.mjs` lista as peças), o que vale para
todos os playbooks. Se o Cowork preferir esperar o avaliador, aplique só os itens 1–6 acima e
mantenha as provas de recibo.

## Placar depois (medido)

Mesmo comando sobre uma cópia temporária do índice com o JSON abaixo aplicado e as `_saida`
ao lado, em `ea4b4f0985`:

```
Sidebar: entregue 13 de 16 · próximo 0 · em curso 3 · pendente 0 · bloqueada 0
  03 [em curso ] (indecidível) Seção TOPO — prova "comparacao" não medida
  05 [em curso ] (indecidível) Ghosts × ADR 0180 — prova "revisao" não medida
  16 [em curso ] (indecidível) Charter + casos do Sidebar — prova "revisao" não medida
cobertura cumulativa: 13 de 16 (81.3%)
```

## JSON das 13 threads como devem ficar

Gerado por script a partir do índice atual (só `provas`, `prefixo` da 15 e `depende_threads` da
04 mudam; o resto do objeto é o de hoje):

```json
{
  "thread_01": {
    "id": "01",
    "titulo": "Seção CORPO: nav + a11y A1–A12 + aposentar Tabs/Chat/ConvRow",
    "dono": "CC",
    "vaga": 1,
    "arquivo": "01-corpo-a11y.md",
    "prefixo": [
      "${BUILD}/sidebar.jsx",
      "${BUILD}/styles.css"
    ],
    "nao_toca": [
      "${BUILD}/app.jsx",
      "${BUILD}/data.jsx",
      "${CKPT}/"
    ],
    "depende_decisoes": [
      "RESIDUO-2"
    ],
    "provas": [
      {
        "tipo": "nao_contem",
        "path": "${BUILD}/sidebar.jsx",
        "padrao": "function SidebarChat",
        "nota": "pré-condição barata; UI-0011, RESIDUO-2 = remover"
      },
      {
        "tipo": "nao_contem",
        "path": "${BUILD}/sidebar.jsx",
        "padrao": "role=\"link\" tabIndex={0} onKeyDown",
        "nota": "pré-condição: o padrão antigo (role=\"link\") casava só o comentário da própria correção em sidebar.jsx:164"
      }
    ],
    "nota_estado": "aplicada e medida em 2026-09-10 (_saida-01.md): nav+a11y, 42 clicáveis viraram button, código morto e CSS órfão removidos, layout remedido por família de controle. Falta a comparação."
  },
  "thread_02": {
    "id": "02",
    "titulo": "Seção MODOS: auto-rail UI-0030 + persistir só escolha manual",
    "dono": "CC",
    "vaga": 1,
    "arquivo": "02-modos-auto-rail.md",
    "prefixo": [
      "${BUILD}/app.jsx"
    ],
    "nao_toca": [
      "${BUILD}/sidebar.jsx",
      "${BUILD}/styles.css"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${BUILD}/app.jsx",
        "padrao": "matchMedia",
        "nota": "pré-condição: auto-rail por largura sem escolha persistida"
      },
      {
        "tipo": "nao_contem",
        "path": "${BUILD}/app.jsx",
        "padrao": "oimpresso.sidebar.tab",
        "nota": "pré-condição: estado morto do Chat cortado"
      }
    ],
    "nota_estado": "aplicada e medida em 2026-09-10 (_saida-02.md): 1280 inclusive, persistência só manual, ciclo de atalhos verde nos dois sentidos após corrigir a regressão do closure. Falta a comparação."
  },
  "thread_04": {
    "id": "04",
    "titulo": "Modo hidden + SidebarReopenHandle → promover pro vivo",
    "dono": "CL",
    "vaga": 1,
    "arquivo": "04-hidden-reopen.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "${CKPT}/shared.ts",
      "resources/js/Layouts/AppShellV2.tsx",
      "resources/css/cockpit.css"
    ],
    "nao_toca": [
      "resources/js/Pages/Financeiro/_cowork-bundle/",
      "${CKPT}/useSidebarShortcut.ts",
      "app/Sidebar/"
    ],
    "depende_decisoes": [
      "RESIDUO-3"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/shared.ts",
        "padrao": "hidden",
        "nota": "SidebarMode ganha o 3º modo"
      },
      {
        "tipo": "contem",
        "path": "resources/js/Layouts/AppShellV2.tsx",
        "padrao": "SidebarReopenHandle"
      },
      {
        "tipo": "contem",
        "path": "resources/css/cockpit.css",
        "padrao": ".sb-reopen-handle"
      },
      {
        "tipo": "arquivo",
        "path": "tests/Feature/Sidebar/SidebarMenuItemContractTest.php"
      }
    ],
    "nota_estado": "DESTRAVADA pela UI-0029 (modo e alça são forma; o protótipo tem os dois). O rail do alerta de certificado (thread 03) é invenção do protótipo, não paridade — se entrar no vivo, é decisão à parte."
  },
  "thread_06": {
    "id": "06",
    "titulo": "Contrato de tela do shell + gates",
    "dono": "CL",
    "vaga": 3,
    "arquivo": "06-contrato-e-gates.md",
    "prefixo": [
      "governance/design/contracts/cockpit-sidebar.contract.json",
      "tests/Feature/Sidebar/"
    ],
    "nao_toca": [
      "${CKPT}/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [],
    "provas": [
      {
        "tipo": "json_com_chaves",
        "path": "${CT}",
        "chaves": [
          "alvo",
          "secoes"
        ],
        "nota": "pré-condição"
      },
      {
        "tipo": "contem",
        "path": "resources/js/Layouts/AppShellV2.tsx",
        "padrao": "data-contract=\"sb-corpo\"",
        "nota": "âncora do contrato no shell"
      },
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "data-contract=\"sb-rodape\""
      }
    ],
    "nota_estado": "ORDEM INVERTIDA (2026-09-10): deixou de depender de 01/03/04 e passou a ser PRÉ-REQUISITO delas — sem o .contract.json não existe prova de comparacao, e sem comparacao nenhuma thread de build fecha. Ver 2-bis."
  },
  "thread_07": {
    "id": "07",
    "titulo": "ALVO — medir protótipo × vivo (read-only)",
    "dono": "CL",
    "vaga": 4,
    "arquivo": "07-alvo-medir.md",
    "prefixo": [
      "governance/design/targets/"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [
      "06"
    ],
    "provas": [
      {
        "tipo": "arquivo",
        "path": "governance/design/targets/cockpit--sidebar.alvo.json"
      },
      {
        "tipo": "arquivo",
        "path": "governance/design/targets/cockpit--sidebar.secoes.json"
      }
    ],
    "nota_estado": "onda 2 (2026-09-25) · seção todas"
  },
  "thread_08": {
    "id": "08",
    "titulo": "Cabeçalho do grupo: seta à direita + cor/raio",
    "dono": "CL",
    "vaga": 5,
    "arquivo": "08-cabecalho-grupo.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "resources/css/cockpit.css"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [
      "07"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "sb-group-n",
        "nota": "pré-condição; ChevronDown depois do contador"
      },
      {
        "tipo": "contem",
        "path": "resources/css/cockpit.css",
        "padrao": "sidebar/08"
      }
    ],
    "nota_estado": "onda 2 (2026-09-25) · seção sb-corpo"
  },
  "thread_09": {
    "id": "09",
    "titulo": "Item ativo: aria-current + grupo abre sozinho",
    "dono": "CL",
    "vaga": 6,
    "arquivo": "09-item-ativo.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "tests/js/"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [
      "07",
      "08"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "aria-current={ativo"
      },
      {
        "tipo": "arquivo",
        "path": "tests/js/sidebar-item-ativo.test.tsx"
      }
    ],
    "nota_estado": "onda 2 (2026-09-25) · seção sb-corpo"
  },
  "thread_10": {
    "id": "10",
    "titulo": "Sub-telas: promover a ativa + mostrar menos",
    "dono": "CL",
    "vaga": 7,
    "arquivo": "10-ghosts.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "tests/js/",
      "${CT}"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [
      "07",
      "09"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "mostrar menos"
      },
      {
        "tipo": "arquivo",
        "path": "tests/js/sidebar-ghosts-teto.test.tsx"
      }
    ],
    "nota_estado": "onda 2 (2026-09-25) · seção sb-corpo"
  },
  "thread_11": {
    "id": "11",
    "titulo": "Rail: ícone do grupo + grupo ativo + dica fixa",
    "dono": "CL",
    "vaga": 8,
    "arquivo": "11-rail.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "resources/css/cockpit.css"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [
      "07",
      "10"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "GROUP_ICON_MAP[g.key]"
      },
      {
        "tipo": "arquivo",
        "path": "tests/js/sidebar-rail.test.tsx"
      }
    ],
    "nota_estado": "onda 2 (2026-09-25) · seção sb-modos"
  },
  "thread_12": {
    "id": "12",
    "titulo": "Rodapé: valor do modo + Buscar tela ⌘K + tirar ⌘/ morto",
    "dono": "CL",
    "vaga": 9,
    "arquivo": "12-rodape.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "tests/",
      "${CT}"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/",
      "resources/js/Layouts/AppShellV2.tsx"
    ],
    "depende_threads": [
      "07",
      "11"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "Buscar tela"
      },
      {
        "tipo": "nao_contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "<span className=\"kbd\">⌘/</span>"
      },
      {
        "tipo": "contem",
        "path": "tests/sidebarAparencia.spec.tsx",
        "padrao": "Buscar tela"
      }
    ],
    "nota_estado": "onda 2 (2026-09-25) · seção sb-rodape"
  },
  "thread_13": {
    "id": "13",
    "titulo": "Presença clicável e persistida",
    "dono": "CL",
    "vaga": 10,
    "arquivo": "13-presenca.md",
    "prefixo": [
      "${CKPT}/Sidebar.tsx",
      "database/migrations/",
      "routes/web.php",
      "app/Http/",
      "tests/Feature/",
      "${CT}"
    ],
    "nao_toca": [
      "${BUILD}/",
      "app/Sidebar/"
    ],
    "depende_threads": [
      "07",
      "12"
    ],
    "depende_decisoes": [
      "RESIDUO-6"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "Invisível"
      },
      {
        "tipo": "nao_contem",
        "path": "${CKPT}/Sidebar.tsx",
        "padrao": "Não perturbe"
      },
      {
        "tipo": "arquivo",
        "path": "tests/Feature/Sidebar/PresencaPreferenciaTest.php"
      },
      {
        "tipo": "arquivo",
        "path": "tests/Feature/Sidebar/presenca.spec.tsx"
      }
    ],
    "nota_estado": "onda 2 · decisão [W] 2026-09-25 · padrão = useTheme.ts:70 + routes/web.php:1162"
  },
  "thread_14": {
    "id": "14",
    "titulo": "Ícone por sub-tela (SidebarGhost::$icon opcional)",
    "dono": "CL",
    "vaga": 11,
    "arquivo": "14-ghost-icone.md",
    "prefixo": [
      "app/Sidebar/SidebarGhost.php",
      "${CKPT}/shared.ts",
      "${CKPT}/Sidebar.tsx",
      "tests/Feature/Sidebar/"
    ],
    "nao_toca": [
      "${BUILD}/"
    ],
    "depende_threads": [
      "07",
      "10",
      "13"
    ],
    "depende_decisoes": [
      "RESIDUO-7"
    ],
    "provas": [
      {
        "tipo": "contem",
        "path": "app/Sidebar/SidebarGhost.php",
        "padrao": "?string $icon"
      },
      {
        "tipo": "arquivo",
        "path": "tests/Feature/Sidebar/ghost-icone.spec.tsx"
      }
    ],
    "nota_estado": "onda 2 · decisão [W] 2026-09-25 · 1 DataController preenchido como prova"
  },
  "thread_15": {
    "id": "15",
    "titulo": "Máquina: alvo.mjs mede expanded/hidden",
    "dono": "CL",
    "vaga": 6,
    "arquivo": "15-alvo-expanded.md",
    "prefixo": [
      "scripts/design-sync/alvo.mjs",
      "scripts/qa/secao-check.mjs",
      "governance/design/targets/"
    ],
    "nao_toca": [
      "${CKPT}/",
      "${BUILD}/"
    ],
    "depende_threads": [
      "07"
    ],
    "provas": [
      {
        "tipo": "arquivo",
        "path": "governance/design/targets/cockpit--sidebar.alvo.json",
        "guarda": true
      },
      {
        "tipo": "arquivo",
        "path": "governance/design/targets/cockpit--sidebar-expanded.alvo.json"
      },
      {
        "tipo": "arquivo",
        "path": "governance/design/targets/cockpit--sidebar-hidden.alvo.json"
      }
    ],
    "nota_estado": "nasce do _saida-07 §Não feito 1 · destrava a comparacao de 08/09/10"
  }
}
```
