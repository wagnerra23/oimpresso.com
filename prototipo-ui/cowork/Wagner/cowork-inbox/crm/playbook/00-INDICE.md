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
      "respondida": true,
      "destrava": [
        "01"
      ],
      "resposta": "ordem proposta",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D2",
      "pergunta": "Lead reusa o formulário de Cliente (hoje LeadController:323/448 devolve contact.create/edit)? Se sim, o form de lead = Cliente/Create",
      "respondida": true,
      "destrava": [
        "02"
      ],
      "resposta": "sim, reusa Cliente/Create",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D3",
      "pergunta": "Portal do contato (ContactLoginController: logins, comissões) é cliente-facing — entra agora?",
      "respondida": true,
      "destrava": [],
      "resposta": "sim, entra (Cowork fatia)",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D4",
      "pergunta": "Onde moram as Pages do Crm: Modules/Crm/Resources/js/Pages (como Superadmin/Whatsapp) ou resources/js/Pages/Crm?",
      "respondida": true,
      "destrava": [
        "02"
      ],
      "resposta": "Modules/Crm/Resources/js/Pages",
      "fonte": "_DECISOES-W-2026-10-01.md"
    }
  ],
  "threads": [
    {
      "id": "A1",
      "titulo": "ALVO lote 1: crm--leads--index · --acompanhamentos--index · --painel--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A1-alvos.md",
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
      "arquivo": "01-trio-lote1.md",
      "depende_threads": [
        "A1"
      ],
      "depende_decisoes": [
        "D1",
        "D4"
      ],
      "prefixo": [],
      "nao_toca": [
        "${MOD}/Http/",
        "${MOD}/Http/Controllers/Cliente*"
      ],
      "provas": [],
      "nota_provas": "errata do recibo: o gate required recusa contrato sem Page; cada contrato entra no PR da Page dele. O contrato derivado está no _saida desta thread.",
      "bloqueio": "absorvida — contrato sem Page é reprovado pelo gate required; contratos entraram em 02 · 03 · 04 (lei IT2). Derivação no _saida-01."
    },
    {
      "id": "02",
      "titulo": "Leads → Inertia (LeadController index + show em drawer)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "02-leads.md",
      "depende_threads": [
        "A1"
      ],
      "depende_decisoes": [
        "D2",
        "D4"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/LeadController.php",
        "${CONTRATOS}/crm-leads.contract.json"
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
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/crm-leads.contract.json"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Acompanhamentos (ScheduleController) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03-acompanhamentos.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/ScheduleController.php",
        "${CONTRATOS}/crm-acompanhamentos.contract.json"
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
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/crm-acompanhamentos.contract.json"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Painel do Crm (CrmDashboardController + DashboardController) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "04-painel.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/CrmDashboardController.php",
        "${MOD}/Http/Controllers/DashboardController.php",
        "${CONTRATOS}/crm-painel.contract.json"
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
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/crm-painel.contract.json"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Lote 2 + portal do contato (D3) — o Cowork levanta e fatia",
      "dono": "CC",
      "vaga": 4,
      "arquivo": "05-lote2.md",
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
    },
    {
      "id": "06",
      "titulo": "Leads: formulário (reusa Cliente/Create parametrizado — D2)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "06-leads-form.md",
      "depende_threads": [
        "02"
      ],
      "prefixo": [
        "resources/js/Pages/Cliente/Create.tsx",
        "${MPAGES}/Crm/Leads/",
        "${MOD}/Http/Controllers/LeadController.php"
      ],
      "nao_toca": [
        "resources/js/Pages/Cliente/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/LeadController.php",
          "padrao": "view('contact.create')"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Acompanhamentos: escrita em Inertia (adicionar, recorrente, editar, log, excluir)",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "07-acompanhamentos-escrita.md",
      "depende_threads": [
        "03"
      ],
      "prefixo": [
        "${MPAGES}/Crm/Acompanhamentos/",
        "${MOD}/Http/Controllers/ScheduleController.php"
      ],
      "nao_toca": [
        "${MOD}/Http/Controllers/LeadController.php"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "UCs de escrita verdes; os botões da toolbar deixam de levar a ?classico=1"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Leads: show sem filtro type=lead + raiz do SELECT de colunas removidas (CrmUtil)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "08-leads-escopo.md",
      "prefixo": [
        "${MOD}/Utils/CrmUtil.php",
        "${MOD}/Http/Controllers/LeadController.php",
        "Modules/Connector/Http/Controllers/Api/Crm/FollowUpController.php",
        "${MOD}/Tests/Feature/"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "show de um cliente (não lead) pelo id → 404; API de follow-up não dá 500 por coluna inexistente"
        }
      ]
    },
    {
      "id": "09",
      "titulo": "Leads: editar no Cliente/Edit parametrizado (hoje view(contact.edit) :505)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "09-leads-editar.md",
      "depende_threads": [
        "06"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/LeadController.php",
        "resources/js/Pages/Cliente/Edit.tsx"
      ],
      "nao_toca": [
        "resources/js/Pages/Cliente/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/LeadController.php",
          "padrao": "view('contact.edit')"
        }
      ]
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados · 2026-10-01 reconferência @99e6fa3e08f0: threads novas 06,07,08 · 2026-10-05 revisão dos recibos @aacb74f4df18"
}
```

## 4 · O que este índice NÃO resolve
- Lote 2 (10 telas) de propósito não virou thread: o lote 1 prova o molde (alvo → contrato → Page), o Cowork reescreve o índice depois.
- Não li `crm-blade.jsx` contra os blades neste turno — a fidelidade do protótipo ao legado não está provada.
