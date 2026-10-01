---
sessao: "00"
titulo: SINCRONIZAR Crm — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 89f32db43080 (lida 2026-09-30 19:00 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/crm/playbook/
---

# SINCRONIZAR Crm — playbook (fatiado)

> Sem `PEDIDO-*` anterior. Protótipo: `crm` + `crm-*` (`app.jsx:851-856`, `window.CrmBladePage` em `crm-blade.jsx`, `CrmFicha`, `CrmPortalPage`). **Protótipo à frente** (🟠): o `main` não tem nenhuma tela React do Crm.

## 1 · LEVANTAR — medido em `89f32db43080`

**Zero `Inertia::render` em `Modules/Crm/Http`; 57 `return view(` em ~15 controllers.** Telas-índice: `crm_dashboard.index` (CrmDashboardController:102) · `dashboard.index` (DashboardController:44) · `lead.index`/`show` (LeadController:298/413) · `schedule.index` (ScheduleController:314) · `campaign.*` (CampaignController:130-289) · `call_logs.index` · `booking.index` · `contact_login.*` + `commissions` · `marketplace.index` · `settings.index` · `ledger.index` · `order_request.*` · `proposal.*` + `proposal_template.*` · `reports.index` · `profile.edit`. Vários controllers têm stubs `crm::create/show/edit` (views genéricas). `LeadController:323/448` usa `contact.create`/`contact.edit` do core.
**Atenção — o Crm já serve o Cliente:** `Modules/Crm/Http/Controllers/` tem `ClienteAuditoriaController`, `ClienteAutosaveController`, `ClienteIaController`, `ClienteLookupController`, `ClienteOssDataController`, `ClienteVeiculosController` (backend das abas do drawer de `Pages/Cliente/`) e `SellController`/`PurchaseController`. Nenhuma thread daqui toca neles.
**Alvos, contratos, medidas:** nenhum.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 | Ordem das fatias (proposta: Leads → Acompanhamentos → Painel → Campanhas → Propostas) | 01 |
| D2 | Lead reusa o form de Cliente? | 02 |
| D3 | Portal do contato (cliente-facing) entra agora? | — |
| D4 | Pages do Crm em `Modules/Crm/Resources/js/Pages` ou `resources/js/Pages/Crm` | 01 · 02 |

## 3 · Threads

```json
{
  "modulo": "Crm",
  "sha": "89f32db43080",
  "gerado": "2026-09-30",
  "absorve": [],
  "variaveis": {
    "MOD": "Modules/Crm",
    "MPAGES": "Modules/Crm/Resources/js/Pages",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Ordem das fatias do Crm (proposta: Leads → Acompanhamentos → Painel → Campanhas → Propostas)",
      "respondida": false,
      "destrava": [
        "01"
      ]
    },
    {
      "id": "D2",
      "pergunta": "Lead reusa o formulário de Cliente (hoje LeadController:323/448 devolve contact.create/edit)? Se sim, o form de lead = Cliente/Create",
      "respondida": false,
      "destrava": [
        "02"
      ]
    },
    {
      "id": "D3",
      "pergunta": "Portal do contato (ContactLoginController: logins, comissões) é cliente-facing — entra agora?",
      "respondida": false,
      "destrava": []
    },
    {
      "id": "D4",
      "pergunta": "Onde moram as Pages do Crm: Modules/Crm/Resources/js/Pages (como Superadmin/Whatsapp) ou resources/js/Pages/Crm?",
      "respondida": false,
      "destrava": [
        "02"
      ]
    }
  ],
  "threads": [
    {
      "id": "A1",
      "titulo": "ALVO lote 1: crm--leads--index · --acompanhamentos--index · --painel--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-alvos.md",
      "prefixo": [
        "${ALVOS}/crm--leads--index.*",
        "${ALVOS}/crm--acompanhamentos--index.*",
        "${ALVOS}/crm--painel--index.*"
      ],
      "nao_toca": [
        "${MOD}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/crm--leads--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/crm--acompanhamentos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/crm--painel--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Trio + contratos das 3 telas do lote 1 (do protótipo, antes do código)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "02-trio-lote1.md",
      "depende_threads": [
        "A1"
      ],
      "depende_decisoes": [
        "D1",
        "D4"
      ],
      "prefixo": [
        "${CONTRATOS}/crm-leads.contract.json",
        "${CONTRATOS}/crm-acompanhamentos.contract.json",
        "${CONTRATOS}/crm-painel.contract.json"
      ],
      "nao_toca": [
        "${MOD}/Http/",
        "${MOD}/Http/Controllers/Cliente*"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/crm-leads.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/crm-acompanhamentos.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/crm-painel.contract.json"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Leads → Inertia (LeadController index + show em drawer)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03-leads.md",
      "depende_threads": [
        "01"
      ],
      "depende_decisoes": [
        "D2",
        "D4"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/LeadController.php"
      ],
      "nao_toca": [
        "resources/js/Pages/Cliente/",
        "${MOD}/Http/Controllers/Cliente*"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/LeadController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Acompanhamentos (ScheduleController) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "04-acompanhamentos.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/ScheduleController.php"
      ],
      "nao_toca": [
        "${MOD}/Http/Controllers/LeadController.php",
        "${MOD}/Http/Controllers/Cliente*"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/ScheduleController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Painel do Crm (CrmDashboardController + DashboardController) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-painel.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/CrmDashboardController.php",
        "${MOD}/Http/Controllers/DashboardController.php"
      ],
      "nao_toca": [
        "${MOD}/Http/Controllers/LeadController.php",
        "${MOD}/Http/Controllers/Cliente*"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/CrmDashboardController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Lote 2 — levantar Campanhas, Propostas (+modelos), Ligações, Agendamentos, Pedidos, Relatórios, Extrato, Marketplace, Configurações",
      "dono": "CC",
      "vaga": 4,
      "arquivo": "06-lote2.md",
      "depende_threads": [
        "02"
      ],
      "prefixo": [
        "prototipo-ui/cowork/Wagner/cowork-inbox/crm/playbook/"
      ],
      "nao_toca": [
        "${MOD}/"
      ],
      "provas": [],
      "nota_provas": "o Cowork reescreve este índice com as threads do lote 2 depois que o lote 1 provar o molde"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- Lote 2 (10 telas) de propósito não virou thread: o lote 1 prova o molde (alvo → contrato → Page), o Cowork reescreve o índice depois.
- Não li `crm-blade.jsx` contra os blades neste turno — a fidelidade do protótipo ao legado não está provada.
