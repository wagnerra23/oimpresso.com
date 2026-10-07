---
sessao: "00"
titulo: Telas soltas (sem roteiro próprio) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main aacb74f4df18 (lida 2026-10-05 20:58 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/telas-soltas/playbook/
---

# Telas soltas (sem roteiro próprio) — playbook

> Telas únicas ou pequenas. Quando uma ganhar trabalho de verdade, sai daqui pro roteiro dela.

## 1 · LEVANTAR — medido em `aacb74f4df18`

| tela (rota) | Page | trio | contrato |
|---|---|---|---|
| Visão geral (`dash-legacy`) | `Home/Index` | ✅ | — |
| Base de Conhecimento (`kb`) | `kb/Index` · `Graph` (+ `Index.v2`) | ✅ | — |
| Módulos | `Modules/Index` | ✅ | ✅ `modulos` |
| Backup | `Backup/Index` | ✅ | ✅ `backup` |
| Documentação | `Documentacao/Index` | ✅ | — (`Programa` é do roteiro programa-doc) |
| Preferências (`prefs`) | `User/Perfil` | ✅ | — |
| Suporte | `Suporte/Empresas` · `Visao` | ✅ | — |
| Cobrança | `Financeiro/Cobranca/Index` | ✅ | — (Gateways no lote-trio-medida) |
| Forja · Tarefas · Equipe | `Modules/Forja/…` (14 Pages: Aprovacoes, Roadmap/Gantt, Trabalho, team-mcp Tasks/Team/CcSessions/Scorecard/Cockpit, ads Admin ×4) | não conferi | ✅ aprovacoes · gantt · trabalho |
| Planilhas · Voz do Cliente · Ordens de Serviço | **não achei Page** (busca limitada) | — | — |

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 | Planilhas, Voz do Cliente e Ordens de Serviço: entram em produção, ficam como exploração ou saem do menu? → **entram em produção como no protótipo** ([W] 07/10, revoga 06/10) | 03 · 04 · 05 · 06 |

## 3 · Threads

```json
{
  "modulo": "Telas soltas (sem roteiro próprio)",
  "sha": "aacb74f4df18",
  "gerado": "2026-10-05",
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Planilhas, Voz do Cliente e Ordens de Serviço existem só no protótipo: entram em produção, ficam como exploração ou saem do menu?",
      "respondida": true,
      "destrava": [
        "03",
        "04",
        "05",
        "06"
      ],
      "resposta": "entram em produção como no protótipo — [W] 2026-10-07 (\"essa pode colocar sim\"), revoga \"ficam como exploração\" de 06/10",
      "quem": "[W]",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07.md"
    }
  ],
  "threads": [
    {
      "id": "A1",
      "titulo": "ALVO 7 telas sem contrato: home · kb · documentacao · perfil · suporte-empresas · suporte-visao · cobranca",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A1-alvos.md",
      "prefixo": [
        "governance/design/targets/soltas--*"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--home--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--kb--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--documentacao--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--perfil--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--suporte-empresas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--suporte-visao--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/soltas--cobranca--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Contratos das 7 (derivados do alvo)",
      "dono": "CL",
      "vaga": 2,
      "prs": 2,
      "arquivo": "01-contratos.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "governance/design/contracts/"
      ],
      "nao_toca": [
        "resources/js/Pages/",
        "governance/design/contracts/modulos.contract.json",
        "governance/design/contracts/backup.contract.json"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/home.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/kb.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/documentacao.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/perfil.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/suporte-empresas.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/suporte-visao.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/cobranca.contract.json"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Forja: levantar as 14 Pages e escrever o roteiro próprio",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "02-forja.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/"
      ],
      "nao_toca": [
        "Modules/Forja/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/00-INDICE.md"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Planilhas · Voz do Cliente · OS: tirar o aviso de exploração e acertar o build",
      "dono": "CC",
      "vaga": 3,
      "arquivo": "03-so-prototipo.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "prototipo-ui/cowork/Wagner/"
      ],
      "nao_toca": [
        "app/",
        "Modules/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "prototipo-ui/cowork/Wagner/app.jsx",
          "padrao": "ExploracaoAviso"
        }
      ],
      "nota_provas": "conforme D1: roteiro novo, aviso de exploração na tela, ou item removido do menu"
    },
    {
      "id": "04",
      "titulo": "Planilhas em Inertia: /spreadsheet/sheets (Blade → trio), forma do protótipo",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "04-planilhas.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "Modules/Spreadsheet/Http/Controllers/SpreadsheetController.php",
        "resources/js/Pages/Spreadsheet/",
        "governance/design/contracts/planilhas.contract.json"
      ],
      "nao_toca": [
        "Modules/Spreadsheet/Database/",
        "Modules/Spreadsheet/Config/retention.php"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Spreadsheet/Index.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Spreadsheet/Index.charter.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Spreadsheet/Index.casos.md"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Voz do Cliente em Inertia — executa a thread 03 de modulos-faltantes (não duplica)",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "05-voz.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "Modules/VozDoCliente/"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/VozDoCliente/Caixa.tsx"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "OS da gráfica: PESQUISAR qual backend é o dono antes de qualquer Page",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "06-os-pesquisa.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [],
      "nao_toca": [
        "app/",
        "Modules/",
        "resources/"
      ],
      "provas": [],
      "nota_provas": "pesquisa: fecha pelo recibo, que TEM de citar arquivo:linha do dono escolhido e o motivo"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- `kb/Index.v2` (charter de 37 KB) sugere uma v2 em andamento — não li.
- Tema escuro: `dash-legacy` e `cobranca` têm achado não triado (roteiro tema-escuro, thread 04).
