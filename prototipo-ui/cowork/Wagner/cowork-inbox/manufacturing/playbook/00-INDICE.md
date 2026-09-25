---
sessao: "00"
titulo: Fabricação — Ordens de produção
autor: "[CC]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main 2c115a5ca250 (lido 2026-09-25 18:46 UTC)
---
# Fabricação — índice

**4 threads.** D-MFG-FONTE respondida: **Wagner** é a fonte única. D-MFG-DATA respondida: data **aplica na hora**. Abrir com `/onda manufacturing --thread NN`. Não é EXPORT de layout: o protótipo puxou o vivo e o build desce no pacote do Cowork. Detalhe: `../PEDIDO-MANUFACTURING-ORDENS-2026-09-25.md`.

```json
{
  "modulo": "Manufacturing",
  "sha": "2c115a5ca250",
  "gerado": "2026-09-25",
  "decisoes": [
    {
      "id": "D-MFG-FONTE",
      "pergunta": "Fonte de design de Manufacturing/Index: cowork/Wagner/manufacturing-producao.jsx (host) ou cowork/Felipe/manufacturing-producao.jsx (charter hoje)?",
      "respondida": true,
      "dono": "W",
      "resposta": "Wagner. [W] 2026-09-25: 'e tudo do wagner, vai ser usado so esse agora'."
    },
    {
      "id": "D-MFG-DATA",
      "pergunta": "Filtro de data: aplicar no blur + lupa (vivo) ou na hora (prototipo)?",
      "respondida": true,
      "dono": "W",
      "resposta": "Aplicar na hora (como o prototipo). [W] 2026-09-25 delegou ao [CC] ('escolha melhor opcao e iguale os dois'): 1 gesto a menos, mesmo comportamento de Local e 'So finalizadas', que ja aplicam no change."
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Contrato de Tela manufacturing-index + data-contract no Index.tsx",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-contrato-index.md",
      "prefixo": [
        "governance/design/contracts/manufacturing-index.contract.json",
        "resources/js/Pages/Manufacturing/Index.tsx"
      ],
      "nao_toca": [
        "resources/js/Pages/Manufacturing/Recipes.tsx",
        "governance/design/contracts/manufacturing-recipes.contract.json",
        "prototipo-ui/cowork/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/manufacturing-index.contract.json"
        },
        {
          "tipo": "contem",
          "path": "governance/design/contracts/manufacturing-index.contract.json",
          "padrao": "Sem produções cadastradas"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "data-contract=\"kpis\""
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "data-contract=\"lista\""
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "StatusBadge kind=\"producao\"",
          "guarda": true,
          "nota": "situacao nao pode voltar a StatusPill local"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "5 charters do modulo: related_prototype -> cowork/Wagner",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "02-charter-fonte.md",
      "prefixo": [
        "resources/js/Pages/Manufacturing/"
      ],
      "nao_toca": [
        "resources/js/Pages/Manufacturing/*.tsx",
        "prototipo-ui/cowork/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Wagner/manufacturing-producao.jsx"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Index.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Felipe/"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Recipes.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Wagner/manufacturing-page.jsx"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Recipes.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Felipe/"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Insumos.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Wagner/manufacturing-insumos.jsx"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Insumos.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Felipe/"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Report.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Wagner/manufacturing-producao.jsx"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Report.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Felipe/"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Settings.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Wagner/manufacturing-producao.jsx"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Settings.charter.md",
          "padrao": "related_prototype: prototipo-ui/cowork/Felipe/"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Aposentar cowork/Felipe/manufacturing-* (7 arquivos) apos listar o que so existe la",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "03-aposentar-felipe.md",
      "depende_threads": [
        "02"
      ],
      "prefixo": [
        "prototipo-ui/cowork/Felipe/manufacturing-"
      ],
      "nao_toca": [
        "prototipo-ui/cowork/Wagner/",
        "prototipo-ui/cowork/Felipe/handoff_fabricacao/",
        "prototipo-ui/cowork/Felipe/*.md",
        "resources/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "prototipo-ui/cowork/Wagner/cowork-inbox/manufacturing/playbook/_saida-03.md",
          "padrao": "so no Felipe"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-data.jsx"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-insumos.jsx"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-page.css"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-page.jsx"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-print.jsx"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-producao.jsx"
        },
        {
          "tipo": "ausente",
          "path": "prototipo-ui/cowork/Felipe/manufacturing-recipe.jsx"
        },
        {
          "tipo": "arquivo",
          "path": "prototipo-ui/cowork/Wagner/manufacturing-producao.jsx",
          "guarda": true,
          "nota": "a fonte unica nao pode ir junto"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Ordens de producao: filtro De/Ate aplica no change (sem blur, sem botao lupa)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "04-data-aplica-na-hora.md",
      "prefixo": [
        "resources/js/Pages/Manufacturing/Index.tsx",
        "resources/js/Pages/Manufacturing/Index.casos.md"
      ],
      "nao_toca": [
        "app/**",
        "Modules/**",
        "resources/js/Pages/Manufacturing/Recipes.tsx",
        "prototipo-ui/cowork/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "onBlur={applyDateRange}"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "Aplicar intervalo de datas"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "only: ['productions', 'summary', 'filters']",
          "guarda": true,
          "nota": "continua partial reload"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.tsx",
          "padrao": "htmlFor=\"mfg-op-data-inicial\"",
          "guarda": true,
          "nota": "rotulo visivel De/Ate nao pode sumir"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Manufacturing/Index.casos.md",
          "padrao": "aplica ao escolher"
        }
      ]
    }
  ]
}
```
