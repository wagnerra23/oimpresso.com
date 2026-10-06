---
sessao: "00"
titulo: Tema escuro — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main e22d2f85bcf4
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/tema-escuro/playbook/
---

# Tema escuro — fazer a máquina achar o que o olho não vê

> **Por que existe:** [W] 05/10 viu "Vendas não aplica o tema black". As folhas usavam token, mas peças **invertidas** (fundo `--text`) e **cores fixas claras** só aparecem em hover, seleção ou estado — nenhuma checagem pega. Hoje o repo só confere **equivalência de token** (`dtcg-equivalence.mjs`, `ds-token-diff.mjs`); **não existe** sonda de render no escuro (busca em `scripts/` @e22d2f85bcf4, varredura limitada — ausência não provada por leitura direta; a 01 começa conferindo).

## 1 · O que o Cowork já mediu e corrigiu (protótipo, 05/10)

Varredura manual: 194 rotas do menu, tema escuro, elemento visível com fundo L > 0,78 fora de papel/placa/tag. **111 rotas cobertas** (a varredura travou depois — por isso a sonda vira máquina).

| achado | rotas | causa | corrigido no build |
|---|---|---|---|
| `<select>` nativo branco | 15 do CRM | faltava `color-scheme: dark` | ✅ `styles.css` (global) |
| dica e barra de lote | vendas | invertidas (`--text`) | ✅ `vendas.css` |
| placa "Total da venda" | venda-nova | invertida — **copiada do CreateV3 de produção** | ✅ só protótipo → thread 03 |
| bloco de IA | detalhe da venda | 3 tokens `--vd-ai-*` sem valor escuro | ✅ `vendas.css` |
| KPI de alerta | venda-devolucoes | fundo 0.985 fixo | ✅ `styles.css` |
| banner de saúde do canal | inbox | fundos 0.92–0.97 fixos | ✅ `inbox-page.css` |
| KPI hero | recurring | invertido | ✅ `cobranca-recorrente-page.css` |
| CTA "dark" | chat | invertido | ✅ `chat-jana.css` |
| `SPAN` branco · Tailwind `text-[10px]` · `inline-flex w-9` | dash-legacy · cobranca · payment-gateways | **não triado** | — fica pra 04 |

**Falso positivo da minha própria sonda (corrigir na 01):** `color-mix(… transparent)` devolve `oklch(L C H / α)`; ler só o L marcou `rep-kbd`, `fin-pill-indef`, `fin-bcrumb-back` e `vb-idade` como claros. Eles são tinta de 10% sobre o escuro — corretos.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 ✅ | Placa invertida no escuro → **tintada de accent** ([W] 05/10) | 03 |
| D2 ✅ | Sonda **required** — bloqueia o PR ([W] 05/10) | 01 |

## 3 · Threads

```json
{
  "modulo": "Tema escuro (transversal)",
  "sha": "e22d2f85bcf4",
  "gerado": "2026-10-05",
  "absorve": [],
  "variaveis": {
    "PROBE": "scripts/design/tema-escuro-probe.mjs",
    "WF": ".github/workflows/design-memory-gate.yml",
    "PAGES": "resources/js/Pages",
    "CSS": "resources/css"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Placa invertida (fundo = --text, texto = --bg) no tema escuro: vira superfície tintada de accent (o que o protótipo fez em 05/10) ou continua invertida (bloco claro)?",
      "respondida": true,
      "destrava": [
        "03"
      ],
      "resposta": "tintada de accent: color-mix(in oklch, var(--accent) 14%, var(--surface)) + borda accent 35% — o que o protótipo aplicou em 05/10",
      "fonte": "[W] 2026-10-05 no chat"
    },
    {
      "id": "D2",
      "pergunta": "A sonda entra como check advisory (comenta no PR) ou required desde o início?",
      "respondida": true,
      "destrava": [
        "01"
      ],
      "resposta": "required — a sonda bloqueia o PR desde o início",
      "fonte": "[W] 2026-10-05 no chat"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Sonda tema-escuro no espelho servido — todas as rotas, check REQUIRED",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-sonda.md",
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${PROBE}",
        "scripts/design/tema-escuro-probe.test.mjs",
        "${WF}"
      ],
      "nao_toca": [
        "prototipo-ui/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PROBE}"
        },
        {
          "tipo": "contem",
          "path": "${PROBE}",
          "padrao": "sanidade"
        },
        {
          "tipo": "contem",
          "path": "${WF}",
          "padrao": "tema-escuro-probe"
        },
        {
          "tipo": "contem",
          "path": "${WF}",
          "padrao": "tema-escuro-probe"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Mesma sonda nas Pages Inertia de produção (tema escuro)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "02-producao.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "${PROBE}",
        "tests/Browser/"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "run com 1 linha por Page Inertia (rota, n de superfícies claras fora de papel), anexado ao recibo"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Corrigir as placas invertidas em produção (lista da 02)",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "03-invertidas.md",
      "depende_threads": [
        "02"
      ],
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "${CSS}/",
        "${PAGES}/Sells/_components/v3/"
      ],
      "nao_toca": [
        "resources/css/tokens/"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "sonda da 02 verde nas Pages corrigidas; screenshot dark antes/depois"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Triar o resultado da 01 no protótipo e corrigir no build",
      "dono": "CC",
      "vaga": 2,
      "arquivo": "04-triagem-prototipo.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "prototipo-ui/cowork/Wagner/"
      ],
      "nao_toca": [
        "scripts/"
      ],
      "provas": [],
      "nota_provas": "o Cowork aplica no build; o recibo cita o run da 01 antes e depois"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- Contraste de **texto** (4,5:1) fica fora: a sonda mede superfície, não tinta sobre superfície. Se [W] quiser, vira thread 05.
- As 83 rotas que a varredura manual não cobriu só saem com a 01.
