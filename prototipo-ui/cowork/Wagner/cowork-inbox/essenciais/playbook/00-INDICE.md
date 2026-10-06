---
sessao: "00"
titulo: Essenciais (Essentials fora do RH) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main aacb74f4df18 (lida 2026-10-05 20:58 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/essenciais/playbook/
---

# Essenciais (Essentials fora do RH) — playbook

> Sem roteiro anterior. `Metas`, `Painel`, `Tipos` e `Licencas` são do roteiro **hrm** — fora daqui. Protótipo: `essenciais` + `ess-*`.

## 1 · LEVANTAR — medido em `aacb74f4df18`

| Page | trio | contrato |
|---|---|---|
| `Essentials/Documents/Index` | ✅ | — |
| `Essentials/Messages/Index` | ✅ | — |
| `Essentials/Reminders/Index` | ✅ | — |
| `Essentials/Knowledge/Index` | ✅ | — |
| `Essentials/Knowledge/Create` · `Edit` · `Show` | charter · **sem casos** | — |
| `Essentials/Todo/Index` | ✅ | — |
| `Essentials/Todo/Create` · `Edit` · `Show` | charter · **sem casos** | — |
| `Essentials/Settings/Index` | charter · **sem casos** | — |
| `Essentials/Holidays/Index` | charter · **sem casos** | — |

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 | Knowledge e Todo: Create/Edit/Show seguem como páginas ou viram drawer da lista (PT-02)?  → **sim** ([W] 06/10) | 03 |

## 3 · Threads

```json
{
  "modulo": "Essenciais (Essentials fora do RH)",
  "sha": "aacb74f4df18",
  "gerado": "2026-10-05",
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Knowledge e Todo: Create/Edit/Show seguem como páginas ou viram drawer da lista (PT-02)?",
      "respondida": true,
      "destrava": [
        "03"
      ],
      "resposta": "sim — Create/Edit/Show de Conhecimento e Tarefas viram painel lateral (PT-02) da lista",
      "quem": "[W]",
      "quando": "2026-10-06",
      "fonte": "_DECISOES-W-2026-10-06.md"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 13 Pages vivas → essenciais (uma rota ess-* por Index)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-puxar-vivo.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/essenciais-page.jsx"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [],
      "nota_provas": "_saida-00 com mapa rota↔Page"
    },
    {
      "id": "01",
      "titulo": "Casos: Knowledge Create/Edit/Show + Todo Create/Edit/Show",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-casos-forms.md",
      "prefixo": [
        "resources/js/Pages/Essentials/Knowledge/",
        "resources/js/Pages/Essentials/Todo/",
        "Modules/Essentials/Tests/Feature/"
      ],
      "nao_toca": [
        "resources/js/Pages/Essentials/Todo/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Knowledge/Create.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Knowledge/Edit.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Knowledge/Show.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Todo/Create.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Todo/Edit.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Todo/Show.casos.md"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Casos: Settings + Holidays",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "02-casos-config.md",
      "prefixo": [
        "resources/js/Pages/Essentials/Settings/",
        "resources/js/Pages/Essentials/Holidays/",
        "Modules/Essentials/Tests/Feature/"
      ],
      "nao_toca": [
        "resources/js/Pages/Essentials/Licencas/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Settings/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Essentials/Holidays/Index.casos.md"
        }
      ]
    },
    {
      "id": "A1",
      "titulo": "ALVO 5 Index: documentos · memorandos · lembretes · conhecimento · tarefas",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1-alvos.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "governance/design/targets/essenciais--*"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/essenciais--documentos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/essenciais--memorandos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/essenciais--lembretes--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/essenciais--conhecimento--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/essenciais--tarefas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Contratos dos 5 Index (derivados do alvo)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03-contratos.md",
      "depende_threads": [
        "A1"
      ],
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "governance/design/contracts/essenciais-*.contract.json"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/essenciais-documentos.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/essenciais-memorandos.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/essenciais-lembretes.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/essenciais-conhecimento.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/essenciais-tarefas.contract.json"
        }
      ]
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- Holidays aparece no menu do RH em produção? Não conferi — a 00 decide de qual roteiro ele é.
