---
sessao: "00"
titulo: Prontidão — fechar a blindagem das telas 1-ciclo (índice)
autor: "[CC]"
criado: 2026-09-23
base: wagnerra23/oimpresso.com@main árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
---
# Prontidão — índice

**Por que existe:** `memory/governance/prototipo-readiness.json` (total 94) lista **35 telas 1-ciclo**: têm .tsx + charter + protótipo real, mas faltam as travas para o visual ser aplicado sem risco. Faltam 28 scorecards e 7 casos.md com UC. Com os 4 de Manufacturing fora (decisão de [W]), ficam **31**. Um deles não é falta, é bug do medidor: `kb-index-v2.yaml` existe, e o script procura `kb-index.v2`. Trabalho real: **23 scorecards + 7 casos.md**.

**O que isto NÃO é:** não aplica visual, não edita `.tsx` nem charter. É só a blindagem que o próprio `prototipo-readiness.mjs` pede (cabeçalho, linhas 16-18).

**Ordem:** 01 primeiro (sem ele a 14 mente sobre kb). As threads 02 a 13 são independentes entre si, 1 PR cada. A 14 fecha e depende de todas.

```json
{
  "modulo": "Prontidao",
  "arvore": "dd380c33a374",
  "gerado": "2026-09-23",
  "fora_de_escopo": [
    "Manufacturing/Insumos",
    "Manufacturing/Recipes",
    "Manufacturing/Report",
    "Manufacturing/Settings"
  ],
  "decisoes": [
    "[W] 2026-09-23: agrupar por o que falta; Manufacturing fora"
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "prototipo-readiness: slug do scorecard não troca `.` por `-` (kb/Index.v2)",
      "dono": "CL",
      "arquivo": "01-readiness-slug-ponto.md",
      "prefixo": [
        "scripts/qa/prototipo-readiness.mjs",
        "scripts/qa/prototipo-readiness.test.mjs"
      ],
      "nao_toca": [
        "memory/governance/scorecards/",
        "memory/governance/prototipo-readiness.json"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "scripts/qa/prototipo-readiness.mjs",
          "padrao": "[\\\\/.]"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "casos.md com UC · Essentials Todo + Reminders",
      "dono": "CL",
      "arquivo": "02-casos-todo.md",
      "prefixo": [
        "resources/js/Pages/Essentials/Todo/Index.casos.md",
        "resources/js/Pages/Essentials/Reminders/Index.casos.md",
        "tests/"
      ],
      "nao_toca": [
        "resources/js/Pages/Essentials/Todo/Index.tsx",
        "resources/js/Pages/Essentials/Reminders/Index.tsx",
        "resources/js/Pages/Essentials/Todo/Index.charter.md",
        "resources/js/Pages/Essentials/Reminders/Index.charter.md"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Essentials/Todo/Index.casos.md",
          "padrao": "UC-"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Essentials/Reminders/Index.casos.md",
          "padrao": "UC-"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "casos.md com UC · Essentials Documents + Knowledge + Messages",
      "dono": "CL",
      "arquivo": "03-casos-documents.md",
      "prefixo": [
        "resources/js/Pages/Essentials/Documents/Index.casos.md",
        "resources/js/Pages/Essentials/Knowledge/Index.casos.md",
        "resources/js/Pages/Essentials/Messages/Index.casos.md",
        "tests/"
      ],
      "nao_toca": [
        "resources/js/Pages/Essentials/Documents/Index.tsx",
        "resources/js/Pages/Essentials/Knowledge/Index.tsx",
        "resources/js/Pages/Essentials/Messages/Index.tsx",
        "resources/js/Pages/Essentials/Documents/Index.charter.md",
        "resources/js/Pages/Essentials/Knowledge/Index.charter.md",
        "resources/js/Pages/Essentials/Messages/Index.charter.md"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Essentials/Documents/Index.casos.md",
          "padrao": "UC-"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Essentials/Knowledge/Index.casos.md",
          "padrao": "UC-"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Essentials/Messages/Index.casos.md",
          "padrao": "UC-"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "casos.md com UC · Sells/Caixa",
      "dono": "CL",
      "arquivo": "04-casos-caixa.md",
      "prefixo": [
        "resources/js/Pages/Sells/Caixa/Index.casos.md",
        "tests/"
      ],
      "nao_toca": [
        "resources/js/Pages/Sells/Caixa/Index.tsx",
        "resources/js/Pages/Sells/Caixa/Index.charter.md"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Sells/Caixa/Index.casos.md",
          "padrao": "UC-"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "casos.md com UC · PaymentGateways (promover backlog)",
      "dono": "CL",
      "arquivo": "05-casos-paymentgateways.md",
      "prefixo": [
        "Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.casos.md",
        "tests/"
      ],
      "nao_toca": [
        "Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx",
        "Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.charter.md"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.casos.md",
          "padrao": "UC-"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Scorecard · núcleo avulso",
      "dono": "CL",
      "arquivo": "06-scorecard-arquivos.md",
      "prefixo": [
        "memory/governance/scorecards/screens/arquivos-index.yaml",
        "memory/governance/scorecards/screens/backup-index.yaml",
        "memory/governance/scorecards/screens/user-perfil.yaml"
      ],
      "nao_toca": [
        "resources/js/Pages/Arquivos/Index.tsx",
        "resources/js/Pages/Backup/Index.tsx",
        "resources/js/Pages/User/Perfil.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/arquivos-index.yaml",
          "padrao": "screen: Arquivos/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/backup-index.yaml",
          "padrao": "screen: Backup/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/user-perfil.yaml",
          "padrao": "screen: User/Perfil"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Scorecard · Essentials (Metas, Tipos)",
      "dono": "CL",
      "arquivo": "07-scorecard-essentials.md",
      "prefixo": [
        "memory/governance/scorecards/screens/essentials-metas.yaml",
        "memory/governance/scorecards/screens/essentials-tipos.yaml"
      ],
      "nao_toca": [
        "resources/js/Pages/Essentials/Metas.tsx",
        "resources/js/Pages/Essentials/Tipos.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/essentials-metas.yaml",
          "padrao": "screen: Essentials/Metas"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/essentials-tipos.yaml",
          "padrao": "screen: Essentials/Tipos"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Scorecard · Jana (Acoes, Alertas, Plataforma)",
      "dono": "CL",
      "arquivo": "08-scorecard-jana.md",
      "prefixo": [
        "memory/governance/scorecards/screens/jana-acoes.yaml",
        "memory/governance/scorecards/screens/jana-alertas.yaml",
        "memory/governance/scorecards/screens/jana-plataforma.yaml"
      ],
      "nao_toca": [
        "resources/js/Pages/Jana/Acoes.tsx",
        "resources/js/Pages/Jana/Alertas.tsx",
        "resources/js/Pages/Jana/Plataforma.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/jana-acoes.yaml",
          "padrao": "screen: Jana/Acoes"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/jana-alertas.yaml",
          "padrao": "screen: Jana/Alertas"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/jana-plataforma.yaml",
          "padrao": "screen: Jana/Plataforma"
        }
      ]
    },
    {
      "id": "09",
      "titulo": "Scorecard · Patrimonio (5 telas)",
      "dono": "CL",
      "arquivo": "09-scorecard-patrimonio.md",
      "prefixo": [
        "memory/governance/scorecards/screens/patrimonio-index.yaml",
        "memory/governance/scorecards/screens/patrimonio-bens.yaml",
        "memory/governance/scorecards/screens/patrimonio-alocacoes.yaml",
        "memory/governance/scorecards/screens/patrimonio-manutencoes.yaml",
        "memory/governance/scorecards/screens/patrimonio-configuracoes.yaml"
      ],
      "nao_toca": [
        "resources/js/Pages/Patrimonio/Index.tsx",
        "resources/js/Pages/Patrimonio/Bens.tsx",
        "resources/js/Pages/Patrimonio/Alocacoes.tsx",
        "resources/js/Pages/Patrimonio/Manutencoes.tsx",
        "resources/js/Pages/Patrimonio/Configuracoes.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/patrimonio-index.yaml",
          "padrao": "screen: Patrimonio/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/patrimonio-bens.yaml",
          "padrao": "screen: Patrimonio/Bens"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/patrimonio-alocacoes.yaml",
          "padrao": "screen: Patrimonio/Alocacoes"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/patrimonio-manutencoes.yaml",
          "padrao": "screen: Patrimonio/Manutencoes"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/patrimonio-configuracoes.yaml",
          "padrao": "screen: Patrimonio/Configuracoes"
        }
      ]
    },
    {
      "id": "10",
      "titulo": "Scorecard · Sells/CreateV3",
      "dono": "CL",
      "arquivo": "10-scorecard-sells.md",
      "prefixo": [
        "memory/governance/scorecards/screens/sells-createv3.yaml"
      ],
      "nao_toca": [
        "resources/js/Pages/Sells/CreateV3.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/sells-createv3.yaml",
          "padrao": "screen: Sells/CreateV3"
        }
      ]
    },
    {
      "id": "11",
      "titulo": "Scorecard · Forja (Aprovacoes, Trabalho, Cockpit)",
      "dono": "CL",
      "arquivo": "11-scorecard-forja.md",
      "prefixo": [
        "memory/governance/scorecards/screens/forja-aprovacoes-index.yaml",
        "memory/governance/scorecards/screens/forja-trabalho-index.yaml",
        "memory/governance/scorecards/screens/team-mcp-forja-cockpit.yaml"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx",
        "Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx",
        "Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/forja-aprovacoes-index.yaml",
          "padrao": "screen: Forja/Aprovacoes/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/forja-trabalho-index.yaml",
          "padrao": "screen: Forja/Trabalho/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/team-mcp-forja-cockpit.yaml",
          "padrao": "screen: team-mcp/Forja/Cockpit"
        }
      ]
    },
    {
      "id": "12",
      "titulo": "Scorecard · Officeimpresso (Logs)",
      "dono": "CL",
      "arquivo": "12-scorecard-officeimpresso.md",
      "prefixo": [
        "memory/governance/scorecards/screens/officeimpresso-logs-index.yaml",
        "memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml"
      ],
      "nao_toca": [
        "Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx",
        "Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/officeimpresso-logs-index.yaml",
          "padrao": "screen: Officeimpresso/Logs/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml",
          "padrao": "screen: Officeimpresso/Logs/Timeline"
        }
      ]
    },
    {
      "id": "13",
      "titulo": "Scorecard · superadmin (4 telas)",
      "dono": "CL",
      "arquivo": "13-scorecard-superadmin.md",
      "prefixo": [
        "memory/governance/scorecards/screens/superadmin-assinaturas-index.yaml",
        "memory/governance/scorecards/screens/superadmin-dashboard-index.yaml",
        "memory/governance/scorecards/screens/superadmin-negocios-index.yaml",
        "memory/governance/scorecards/screens/superadmin-pacotes-index.yaml"
      ],
      "nao_toca": [
        "Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx",
        "Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx",
        "Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx",
        "Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/superadmin-assinaturas-index.yaml",
          "padrao": "screen: superadmin/Assinaturas/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/superadmin-dashboard-index.yaml",
          "padrao": "screen: superadmin/Dashboard/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/superadmin-negocios-index.yaml",
          "padrao": "screen: superadmin/Negocios/Index"
        },
        {
          "tipo": "contem",
          "path": "memory/governance/scorecards/screens/superadmin-pacotes-index.yaml",
          "padrao": "screen: superadmin/Pacotes/Index"
        }
      ]
    },
    {
      "id": "14",
      "titulo": "Regenerar prototipo-readiness.json e dizer o número novo",
      "dono": "CL",
      "arquivo": "14-regenerar-readiness.md",
      "depende": [
        "01",
        "02",
        "03",
        "04",
        "05",
        "06",
        "07",
        "08",
        "09",
        "10",
        "11",
        "12",
        "13"
      ],
      "prefixo": [
        "memory/governance/prototipo-readiness.json"
      ],
      "nao_toca": [
        "scripts/qa/",
        "memory/governance/scorecards/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "memory/governance/prototipo-readiness.json",
          "padrao": "\"tela\": \"Arquivos/Index\""
        },
        {
          "tipo": "nao_contem",
          "path": "memory/governance/prototipo-readiness.json",
          "padrao": "\"tela\": \"kb/Index.v2\""
        }
      ]
    }
  ]
}
```

## Agrupado por o que falta
- **Medidor:** 01
- **casos.md com UC (7 telas):** 02 · 03 · 04 · 05
- **scorecard (23 telas):** 06 · 07 · 08 · 09 · 10 · 11 · 12 · 13
- **Fechamento:** 14
