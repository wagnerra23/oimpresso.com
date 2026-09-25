---
modulo: financeiro
titulo: "Financeiro — Unificado: onda = seção"
dono_pedido: "[CC]"
executor: "[CL]"
gerado: 2026-09-13
atualizado: 2026-09-25
base_lido: 2c115a5ca250
alvo_medido: PARCIAL — só a seção 07 (drawer), 2026-09-25
granularidade: secao (decisão [W] 2026-09-13)
---
# Financeiro — Unificado · fila de seções

> **Revisado 2026-09-25 com threads 00 e 07 EM EXECUÇÃO** — IDs e fichas mantidos; o delta está em `_PATCH-INDICE-2026-09-25.md`. Leia o patch antes de fechar o `_saida`.

> **Leia este arquivo + a thread da sua vez.** Read-order no `main`: `memory/reference/prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` → `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` → `PRE-FLIGHT-TELA.md` → `Pages/Financeiro/Unificado/Index.charter.md` + `.casos.md`.

## Estado do ALVO
- **Seção 07 (drawer) — MEDIDA** em 2026-09-25 no protótipo servido (`oimpresso.com.html`, rota `financeiro`, tema `cockpit dark`, `__oiLazyDone=true`, `querySelectorAll('*').length` 2104/2104). Sanidade antes do veredito: largura do drawer = **560** (valor conhecido, declarado em `w-[560px]`) ✓. Números na thread `07-Unificado.drawer.md` §3.
- **Seções 01–06 — NÃO MEDIDAS.** Não mexer em espaçamento/medida/cor delas com base neste arquivo.
- **`governance/design/targets/` não tem alvo do Financeiro** (lido @`2c115a5ca250`: só `cockpit--sidebar` e `jana--index`). Sem `financeiro--unificado.alvo.json` o `pedido.mjs` sai **exit 2 NÃO MEDI** ⇒ a thread **00** (ALVO) roda antes da 07.

## Frescor — por que o Unificado é 🔵
Produção à frente do protótipo: puxar o vivo, não refazer. **Exceção declarada:** o acabamento do drawer (seção 07) foi refeito deste lado em 2026-09-25 a pedido de [W] ("acabamentos feios") — é o único ponto onde o protótipo passa à frente.

## Âncoras (lidas no `main` @`2c115a5ca250`, 2026-09-25)
- **Charter/casos:** `resources/js/Pages/Financeiro/Unificado/Index.charter.md` (31.116 B) + `Index.casos.md` (21.463 B).
- **Implementação:** `resources/js/Pages/Financeiro/Unificado/Index.tsx` (170.053 B) — drawer em `:2057` (`SheetContent … fin-drawer-wide … data-contract="drawer-detalhe"`), hero `:2155`, rodapé `:2632`, lentes `DrawerLens` `:687` / `DrawerLensChip` `:678`.
- **CSS vivo do drawer:** cascata de **6 folhas** importadas em `resources/css/inertia.css` na ordem `cowork-canon-financeiro-bundle` → `fin-curadoria` → `fin-ia` → `fin-output` → `fin-cowork` → `fin-mobile` (a última ganha). Aba IA do vivo = `fin-ia.css` (`.fin-anomaly*`, `.fin-party-*`), **não** `vd-ai-*`. Detalhe na thread 07 §4.
- **Layout (protótipo):** `financeiro-page.jsx` (Drawer) + `financeiro-drawer.css` (novo, escopo `.fin-dw2`) + `financeiro-ai.jsx` (aba IA).
- **Contrato:** não existe para esta tela (`caixa-unificada.contract.json` é outra tela).

## Seções
| # | seção | marcas no protótipo | alvo |
|---|---|---|---|
| 01 | Faixa de KPI | `.os-stats.fin-stats` | não medido |
| 02 | Barra de período | `.fin-periodbar` | não medido |
| 03 | Toolbar de filtros | `.fin-toolbar` | não medido |
| 04 | Ageing | `.fin-ageing` | não medido |
| 05 | Header | `.os-page-h.fin-hero` | não medido |
| 06 | Tabela | `Table` em `financeiro-page.jsx` | não medido |
| 07 | Drawer (Detalhes + IA) | `aside.fin-drawer-wide.fin-dw2` | **medido 2026-09-25** |

```json
{
  "modulo": "financeiro",
  "view": "Unificado",
  "base_lido": "2c115a5ca250",
  "alvo_medido": "parcial",
  "granularidade": "secao",
  "variaveis": {
    "CHARTER": "resources/js/Pages/Financeiro/Unificado/Index.charter.md",
    "CASOS": "resources/js/Pages/Financeiro/Unificado/Index.casos.md",
    "CSS": "resources/css/fin-cowork.css",
    "CSS_IA": "resources/css/fin-ia.css",
    "PROTOTIPO": "prototipo-ui/cowork/Wagner/financeiro-page.jsx"
  },
  "decisoes": [
    {
      "id": "D-FIN-ALVO",
      "pergunta": "Medir as secoes 01-06 numa passada so antes das threads delas? Recomendacao: sim.",
      "respondida": false,
      "dono": "[W]"
    },
    {
      "id": "D-FIN-CONTRATO",
      "pergunta": "Escrever o Contrato de Tela do Unificado (9 UC ja escritos) antes ou depois das ondas de layout?",
      "respondida": false,
      "dono": "[W]"
    },
    {
      "id": "D-FIN-IA-CONTEUDO",
      "pergunta": "Aba IA: prototipo (Perguntar a IA + 4 stats) ou vivo (detector de anomalia + historico 5 recentes) e o alvo de CONTEUDO? A thread 07 so troca cor.",
      "respondida": false,
      "dono": "[W]"
    },
    {
      "id": "D-FIN-DW-TEMA",
      "pergunta": "Drawer de producao e forcado claro ([role=dialog].fin-cowork em fin-cowork.css). Passa a seguir o tema (recomendado) ou fica claro? Define se o alvo dark do §3 vale.",
      "respondida": false,
      "dono": "W"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "ALVO financeiro--unificado (secao drawer)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "07-Unificado.drawer.md",
      "prefixo": [
        "governance/design/targets/financeiro--unificado."
      ],
      "nao_toca": [
        "resources/",
        "prototipo-ui/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/targets/financeiro--unificado.alvo.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/targets/financeiro--unificado.secoes.json"
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/financeiro--unificado.secoes.json",
          "chaves": [
            "dw-painel",
            "dw-hero",
            "dw-abas",
            "dw-rodape"
          ]
        }
      ],
      "nota": "entregue pelo [CL] em 2026-09-25 (_saida-00.md): 11 secoes dw-*; provas ajustadas ao que foi medido"
    },
    {
      "id": "07",
      "titulo": "Drawer do lancamento — acabamento + aba IA em tokens",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "07-Unificado.drawer.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "resources/css/fin-cowork.css",
        "resources/css/fin-ia.css",
        "resources/css/fin-output.css",
        "resources/js/Pages/Financeiro/Unificado/Index.tsx"
      ],
      "nao_toca": [
        "resources/js/Pages/Financeiro/Unificado/_components/",
        "resources/css/cowork-canon-financeiro-bundle.css",
        "resources/css/sells-cowork-ia.css",
        "app/",
        "Modules/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/css/fin-cowork.css",
          "padrao": "[role=\"dialog\"].fin-cowork .fin-dw-hero"
        },
        {
          "tipo": "contem",
          "path": "resources/css/fin-cowork.css",
          "padrao": "[role=\"dialog\"].fin-cowork .fin-drawer-footer"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/css/fin-ia.css",
          "padrao": "background: white;"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/css/fin-output.css",
          "padrao": ".fin-cowork .fin-drawer-wide { padding-left: 22px; padding-right: 22px; }"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Financeiro/Unificado/Index.tsx",
          "padrao": "data-contract=\"drawer-detalhe\"",
          "guarda": true
        },
        {
          "tipo": "contem",
          "path": "resources/css/fin-cowork.css",
          "padrao": "[role=\"dialog\"].fin-cowork .fin-drawer-tabs",
          "guarda": true,
          "nota": "fix Onda 25 do margin negativo nao pode sumir"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Drawer segue o tema: remover vars claras fixas de [role=dialog].fin-cowork",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "_PATCH-INDICE-2026-09-25.md",
      "depende_threads": [
        "07"
      ],
      "depende_decisoes": [
        "D-FIN-DW-TEMA"
      ],
      "prefixo": [
        "resources/css/fin-cowork.css"
      ],
      "nao_toca": [
        "resources/js/",
        "resources/css/cowork-canon-financeiro-bundle.css"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "resources/css/fin-cowork.css",
          "padrao": "background: #ffffff !important;"
        },
        {
          "tipo": "contem",
          "path": "resources/css/fin-cowork.css",
          "padrao": "[role=\"dialog\"].fin-cowork .fin-drawer-tabs",
          "guarda": true
        }
      ]
    }
  ],
  "sha": "2c115a5ca250",
  "gerado": "2026-09-25"
}
```

## O que este pacote NÃO resolve (bloco 7)
- Seções 01–06 continuam **sem alvo** — nenhuma thread de layout delas.
- As 4 telas-irmãs (`Conciliacao`, `Dre`, `Fluxo`, `Impostos`) compartilham `financeiro-telas-extras.jsx` — fora desta view.
- A thread 07 não resolve os pontos listados no §7 dela (formatação `R$ 1.2k`, anel do stepper, alvos <24px).
