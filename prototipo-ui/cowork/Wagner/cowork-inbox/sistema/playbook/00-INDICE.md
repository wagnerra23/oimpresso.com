---
sessao: "00"
titulo: Sistema (Configurações · Usuários · Relatórios) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-05
base: wagnerra23/oimpresso.com@main aacb74f4df18 (lida 2026-10-05 20:58 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/sistema/playbook/
---

# Sistema (Configurações · Usuários · Relatórios) — playbook

> Sem roteiro anterior. **Tudo Blade em produção**, exceto `User/Perfil` (no lote de telas soltas). O protótipo tem as 21 telas (`cfg-*`, `usuarios`, `funcoes`, `comissionados`, `rel-*`).

## 1 · LEVANTAR — medido em `aacb74f4df18`

| tela | controller (`return view(…index)`) |
|---|---|
| Usuários | `ManageUserController:83` |
| Funções e permissões | `RoleController:81` |
| Comissionados | `SalesCommissionAgentController:80` |
| Locais comerciais | `BusinessLocationController:96` |
| Impressoras | `PrinterController:51` |
| Código de barras | `BarcodeController:54` |
| Esquemas de fatura | `InvoiceSchemeController:76` |
| Impostos | `TaxRateController:66` |
| Tipos de serviço | `TypesOfServiceController:68` |
| Modelos de notificação | `NotificationTemplateController:53` |
| Contas | `AccountController:192` |
| Relatórios | `ReportController` (não contei as views) |

**Zero `Inertia::render` de Usuário/Função/Configuração/Relatório** em `app/Http/Controllers` (busca completa no diretório), fora `UserController:186` → `User/Perfil`.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 | Configurações: uma Page por cadastro (como hoje no Blade) ou uma tela "Configurações" com abas (como o protótipo)?  → **sim** ([W] 06/10) | 04 |
| D2 | Relatórios: uma tela com grupos (Financeiro/Comercial/Estoque…, como o protótipo) ou uma Page por relatório?  → **sim** ([W] 06/10) | 07 |

## 3 · Threads

```json
{
  "modulo": "Sistema (Configurações · Usuários · Relatórios)",
  "sha": "aacb74f4df18",
  "gerado": "2026-10-05",
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Configurações: uma Page por cadastro (como hoje no Blade) ou uma tela \"Configurações\" com abas (como o protótipo)?",
      "respondida": true,
      "destrava": [
        "04"
      ],
      "resposta": "sim — Configurações numa tela com abas, como o protótipo",
      "quem": "[W]",
      "quando": "2026-10-06",
      "fonte": "_DECISOES-W-2026-10-06.md"
    },
    {
      "id": "D2",
      "pergunta": "Relatórios: uma tela com grupos (Financeiro/Comercial/Estoque…, como o protótipo) ou uma Page por relatório?",
      "respondida": true,
      "destrava": [
        "07"
      ],
      "resposta": "sim — Relatórios numa tela com grupos, como o protótipo",
      "quem": "[W]",
      "quando": "2026-10-06",
      "fonte": "_DECISOES-W-2026-10-06.md"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "Mapa Blade ↔ protótipo das 21 telas (cfg-*, usuarios/funcoes/comissionados, rel-*)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-mapa.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/"
      ],
      "nao_toca": [
        "app/",
        "resources/"
      ],
      "provas": [],
      "nota_provas": "_saida-00 com uma linha por tela: view Blade · rota do protótipo · o que falta no protótipo"
    },
    {
      "id": "01",
      "titulo": "Usuários (ManageUser) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "01-usuarios-manageuser-inertia.md",
      "depende_threads": [
        "00"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "app/Http/Controllers/ManageUserController.php",
        "resources/js/Pages/Usuarios/"
      ],
      "nao_toca": [
        "resources/views/layouts/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/ManageUserController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Funções e permissões (Role) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "02-funcoes-e-permissoes-role-inertia.md",
      "depende_threads": [
        "01"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "app/Http/Controllers/RoleController.php",
        "resources/js/Pages/Funcoes/"
      ],
      "nao_toca": [
        "resources/views/layouts/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/RoleController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Comissionados (SalesCommissionAgent) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03-comissionados-salescommissionagent-inert.md",
      "depende_threads": [
        "00"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "app/Http/Controllers/SalesCommissionAgentController.php",
        "resources/js/Pages/Comissionados/"
      ],
      "nao_toca": [
        "resources/views/layouts/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/SalesCommissionAgentController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Locais · Impressoras · Código de barras → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "04-locais-impressoras-codigo-de-barras-iner.md",
      "depende_threads": [
        "00"
      ],
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "app/Http/Controllers/BusinessLocationController.php",
        "app/Http/Controllers/PrinterController.php",
        "app/Http/Controllers/BarcodeController.php",
        "resources/js/Pages/Configuracoes/"
      ],
      "nao_toca": [
        "resources/views/layouts/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/BusinessLocationController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/PrinterController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/BarcodeController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Esquemas de fatura · Impostos · Tipos de serviço → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-esquemas-de-fatura-impostos-tipos-de-ser.md",
      "depende_threads": [
        "04"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "app/Http/Controllers/InvoiceSchemeController.php",
        "app/Http/Controllers/TaxRateController.php",
        "app/Http/Controllers/TypesOfServiceController.php",
        "resources/js/Pages/Configuracoes/"
      ],
      "nao_toca": [
        "resources/views/layouts/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/InvoiceSchemeController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/TaxRateController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/TypesOfServiceController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Modelos de notificação · Contas → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "06-modelos-de-notificacao-contas-inertia.md",
      "depende_threads": [
        "05"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "app/Http/Controllers/NotificationTemplateController.php",
        "app/Http/Controllers/AccountController.php",
        "resources/js/Pages/Configuracoes/"
      ],
      "nao_toca": [
        "resources/views/layouts/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/NotificationTemplateController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/AccountController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Relatórios (ReportController) → Inertia",
      "dono": "CL",
      "vaga": 4,
      "prs": 2,
      "arquivo": "07-relatorios.md",
      "depende_threads": [
        "00"
      ],
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "app/Http/Controllers/ReportController.php",
        "resources/js/Pages/Relatorios/"
      ],
      "nao_toca": [
        "resources/js/Pages/Financeiro/Relatorios/",
        "resources/js/Pages/Ponto/Relatorios/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "app/Http/Controllers/ReportController.php",
          "padrao": "Inertia::render('Relatorios/"
        }
      ]
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- "Configurações da empresa" (business settings, abas grandes) e "Assinatura de pacote" não viraram thread: ficam no mapa da 00 e voltam como índice novo.
- Permissão nova não entra aqui — só Page.
