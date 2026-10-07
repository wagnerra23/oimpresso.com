---
sessao: "00"
titulo: SINCRONIZAR Superadmin — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 89f32db43080 (lida 2026-09-30 19:00 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/superadmin/playbook/
---

# SINCRONIZAR Superadmin — playbook

> Sem `PEDIDO-*` anterior. Protótipo: `superadmin` + `sa-negocios`, `sa-assinaturas`, `sa-pacotes`, `sa-comunicador`, `sa-config` (`app.jsx:882-887`, `window.SuperadminPage`).

## 1 · LEVANTAR — medido em `89f32db43080`

**Em React (Pages em `Modules/Superadmin/Resources/js/Pages/superadmin/`):** `Dashboard/Index` (SuperadminController:48) · `Negocios/Index` (BusinessController:73) · `Pacotes/Index` (PackagesController:67) · `Assinaturas/Index` (SuperadminSubscriptionsController:63) — os 4 com trio ✅ **e contrato** (`superadmin-{dashboard,negocios,pacotes,assinaturas}.contract.json`). `Usuario360/Index` + `Show` (Usuario360Controller:69/86) — **sem casos, sem contrato**. `Site/Pricing` (PricingController:46) é do site.
**Ainda Blade:** `business.create` (:383) · `business.show` (:503) · `packages.create/edit` (:162/:238) · `superadmin_subscription.add/edit/edit_date_modal` (:257/:324/:450) · `communicator.index` (:30) · `superadmin_settings.edit` (:110) · `pages.*` (4) · `subscription.index/pay/modal` (assinatura do lado do negócio).
**Medidas:** as 4 de `superadmin--*` usam **o mesmo `design.json`** (blob `a396380243af`) — não valem como estão.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 | `superadmin::pages` × `Cms` `Admin/Content` | 06 |
| D2 | Assinatura vista pelo negócio: aqui ou Financeiro/RecurringBilling | 07 |

## 3 · Threads

```json
{
  "modulo": "Superadmin",
  "sha": "89f32db43080",
  "gerado": "2026-09-30",
  "absorve": [],
  "variaveis": {
    "MOD": "Modules/Superadmin",
    "MPAGES": "Modules/Superadmin/Resources/js/Pages/superadmin",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "superadmin::pages (CRUD de páginas) × Modules/Cms Admin/Content: fundir ou manter?",
      "respondida": true,
      "destrava": [
        "06"
      ],
      "resposta": "manter separado do Cms — _DECISOES-W-2026-10-01b.md",
      "fonte": "_DECISOES-W-2026-10-01b.md"
    },
    {
      "id": "D2",
      "pergunta": "SubscriptionController (assinatura vista pelo NEGÓCIO: index, pagar) entra aqui ou em Financeiro/RecurringBilling?",
      "respondida": true,
      "destrava": [
        "07"
      ],
      "resposta": "aqui no Superadmin",
      "fonte": "_DECISOES-W-2026-10-01.md"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR 6 Pages vivas → superadmin-page (sa-*)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-puxar.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/superadmin*"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [],
      "nota_provas": "_saida-00.md com diff por tela e 1 rota sa-* por Page"
    },
    {
      "id": "A1",
      "titulo": "Remedir as 4 medidas (design.json compartilhado) + ALVO usuario360",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1-alvos.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "${ALVOS}/medidas/superadmin--*",
        "${ALVOS}/superadmin--usuario360--index.*"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/superadmin--usuario360--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Casos: Usuario360 Index + Show",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-casos.md",
      "prefixo": [
        "${MPAGES}/Usuario360/",
        "Modules/Superadmin/Tests/Feature/"
      ],
      "nao_toca": [
        "${MPAGES}/Usuario360/Show.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Usuario360/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Usuario360/Show.casos.md"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Negócios: create/show → drawer da lista (Negocios/Index)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "02-formularios.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/BusinessController.php",
        "${MPAGES}/Negocios/"
      ],
      "nao_toca": [
        "${CONTRATOS}/superadmin-negocios.contract.json"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/BusinessController.php",
          "padrao": "view('superadmin::business.show')"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Pacotes: create/edit → drawer (Pacotes/Index)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03-formularios.md",
      "depende_threads": [
        "A2"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/PackagesController.php",
        "${MPAGES}/Pacotes/"
      ],
      "nao_toca": [
        "${CONTRATOS}/superadmin-pacotes.contract.json"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/PackagesController.php",
          "padrao": "view('superadmin::packages.edit')"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Assinaturas: add/edit/edit_date → drawer (Assinaturas/Index)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "04-formularios.md",
      "depende_threads": [
        "A2"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/SuperadminSubscriptionsController.php",
        "${MPAGES}/Assinaturas/"
      ],
      "nao_toca": [
        "${CONTRATOS}/superadmin-assinaturas.contract.json"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/SuperadminSubscriptionsController.php",
          "padrao": "view('superadmin::superadmin_subscription.edit')"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Comunicador + Configurações → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "05-comunicador-config.md",
      "prefixo": [
        "${MOD}/Http/Controllers/CommunicatorController.php",
        "${MOD}/Http/Controllers/SuperadminSettingsController.php",
        "${MPAGES}/Comunicador/",
        "${MPAGES}/Configuracoes/"
      ],
      "nao_toca": [
        "${MPAGES}/Dashboard/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/CommunicatorController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/SuperadminSettingsController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Páginas (superadmin::pages)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "06-paginas.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/PageController.php"
      ],
      "nao_toca": [
        "Modules/Cms/"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "superadmin::pages redireciona para Cms Admin/Content com paridade de campos medida no recibo"
        }
      ],
      "nota_provas": "depende de D1"
    },
    {
      "id": "07",
      "titulo": "Assinatura do negócio (SubscriptionController: index, pagar)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "07-assinatura-negocio.md",
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/SubscriptionController.php"
      ],
      "nao_toca": [
        "Modules/PaymentGateway/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/SubscriptionController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "A2",
      "titulo": "Remedir Negócios · Pacotes · Assinaturas · Dashboard (o lote só mede os 2 lados juntos)",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "A2-remedir-4.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "governance/design/targets/medidas/superadmin--*"
      ],
      "nao_toca": [
        "Modules/Superadmin/"
      ],
      "provas": [
        {
          "tipo": "medicao",
          "nota": "4 medidas, cada uma com o seu design.json (rota sa-* própria), 2× byte-idêntico"
        }
      ]
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados · 2026-10-01 reconferência @99e6fa3e08f0: threads novas — · 2026-10-05 revisão dos recibos @aacb74f4df18"
}
```

## 4 · O que este índice NÃO resolve
- `Show` de negócio vira drawer (PT-02 proíbe página cheia pra detalhe) — se o Code achar que precisa ser página, volta pra [W].
- `Site/Pricing` fica com o roteiro do site/CMS.
