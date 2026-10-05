---
sessao: "00"
titulo: SINCRONIZAR Atendimento (Modules/Whatsapp) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main ca44a3d54cd2 (lida 2026-09-30 18:51 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/atendimento/playbook/
---

# SINCRONIZAR Atendimento — playbook

> Sem `PEDIDO-*` anterior. Módulo no código = `Modules/Whatsapp`; nome na UI = **Atendimento**. Protótipo: rota `inbox` (`app.jsx:858`, `window.InboxPage`, fontes `inbox-page.jsx` + `inbox-ai/cur/extras/out.jsx`). O template do DS `atendimento` é a referência visual.

## 1 · LEVANTAR — medido em `ca44a3d54cd2`

**D4 (`Inertia::render`, 12 chamadas em `Modules/Whatsapp/Http`, busca completa no subdir):**

| render | controller | arquivo da Page | trio |
|---|---|---|---|
| `Atendimento/CaixaUnificada/Index` | `CaixaUnificadaController:149` | `Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` (+17 `_components`) | ✅ |
| `Atendimento/Inbox/Index` | `InboxController:268` | **não encontrado** em lugar nenhum da árvore | — |
| `Atendimento/Channels/Index` · `Show` | `ChannelsController:43` · `:142` | `…/Atendimento/Channels/` | sem casos |
| `Atendimento/Csat/Index` | `CsatController:47` | `…/Atendimento/Csat/` | sem casos |
| `Atendimento/Macros/Index` · `Variants` | `MacrosController:45` · `MacroVariantsController:53` | `…/Atendimento/Macros/` | sem casos |
| `Atendimento/Metricas/Index` | `MetricsController:56` | `…/Atendimento/Metricas/` | sem casos |
| `Atendimento/JanaTemplates` | `SettingsController:49` | `…/Atendimento/JanaTemplates.tsx` | sem casos |
| `Whatsapp/Settings` | `SettingsController:104` | `resources/js/Pages/Whatsapp/Settings.tsx` | sem casos |
| `Whatsapp/Templates/Index` | `TemplatesController:42` | `resources/js/Pages/Whatsapp/Templates/` | sem casos |
| `Whatsapp/FeedbackPublico` | `Publico/FeedbackFormController:63` | `resources/js/Pages/Whatsapp/FeedbackPublico.tsx` | ✅ |

**Achado 1 — render sem Page.** `InboxController:268` renderiza `Atendimento/Inbox/Index` e o arquivo não existe (busca `Atendimento/.*\.tsx` na árvore inteira: 35 arquivos, nenhum `Inbox/`). O docblock diz que `/atendimento/inbox` "substitui long-term `/whatsapp/conversations`" (ADR 0135). O controller tem 96 KB e é dono também de `send`, `updateTags`, `blockContact` — só o `index()` está órfão. Não verifiquei se o resolver de Page mapeia `Inbox` → `CaixaUnificada`.
**Achado 2 — trio.** 9 das 11 Pages existentes não têm `.casos.md`.
**Achado 3 — Pages em dois lugares.** `Atendimento/*` mora em `Modules/Whatsapp/Resources/js/Pages/`; `Whatsapp/*` em `resources/js/Pages/`.
**Medida existente:** `targets/medidas/Atendimento--CaixaUnificada--Index/` (não li). **Alvos:** nenhum. **Contratos:** nenhum `*atend*`/`*whats*`.

## 2 · Decisões

| id | pergunta | destrava |
|---|---|---|
| D1 | `/atendimento/inbox` (render órfão): redirecionar pra Caixa Unificada, ou existe Page que eu não achei? | 01 |
| D2 | As telas de administração (Canais, Macros, CSAT, Métricas, Templates, Configurações) entram no protótipo agora? Hoje só a caixa existe aqui | 00 (escopo) |

## 3 · Threads

```json
{
  "modulo": "Atendimento",
  "sha": "ca44a3d54cd2",
  "gerado": "2026-09-30",
  "absorve": [],
  "variaveis": {
    "MOD": "Modules/Whatsapp",
    "MPAGES": "Modules/Whatsapp/Resources/js/Pages",
    "PAGES": "resources/js/Pages",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Render órfão Atendimento/Inbox/Index: redirecionar ou existe Page?",
      "respondida": true,
      "destrava": [
        "01"
      ],
      "resposta": "redirecionar pra Caixa Unificada preservando query",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D2",
      "pergunta": "Telas de administração entram no protótipo agora?",
      "respondida": true,
      "destrava": [],
      "resposta": "sim, entram no protótipo",
      "fonte": "_DECISOES-W-2026-10-01.md"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR Caixa Unificada viva → inbox-page.jsx",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-puxar-vivo.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/inbox-*"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [],
      "nota_provas": "read-only no main + build aqui: prova = _saida-00.md com o diff nos dois sentidos"
    },
    {
      "id": "01",
      "titulo": "Render órfão /atendimento/inbox",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-inbox-orfao.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/Admin/InboxController.php",
        "${MOD}/Routes/"
      ],
      "nao_toca": [
        "${MPAGES}/Atendimento/CaixaUnificada/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/Admin/InboxController.php",
          "padrao": "Inertia::render('Atendimento/Inbox/Index'"
        }
      ],
      "nota": "se D1 = existe Page, a prova troca para {tipo: arquivo} no caminho achado"
    },
    {
      "id": "02",
      "titulo": "Casos: Channels Index + Show",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "02-casos.md",
      "prefixo": [
        "${MPAGES}/Atendimento/Channels/",
        "tests/"
      ],
      "nao_toca": [
        "${MPAGES}/Atendimento/Channels/Index.tsx",
        "${MPAGES}/Atendimento/Channels/Show.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/Channels/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/Channels/Show.casos.md"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Casos: Macros Index + Variants + JanaTemplates",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "03-casos.md",
      "prefixo": [
        "${MPAGES}/Atendimento/Macros/",
        "${MPAGES}/Atendimento/JanaTemplates.casos.md",
        "tests/"
      ],
      "nao_toca": [
        "${MPAGES}/Atendimento/Macros/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/Macros/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/Macros/Variants.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/JanaTemplates.casos.md"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Casos: Csat + Metricas + Whatsapp/Settings + Whatsapp/Templates",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "04-casos.md",
      "prefixo": [
        "${MPAGES}/Atendimento/Csat/",
        "${MPAGES}/Atendimento/Metricas/",
        "${PAGES}/Whatsapp/",
        "tests/"
      ],
      "nao_toca": [
        "${PAGES}/Whatsapp/_components/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/Csat/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Atendimento/Metricas/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Whatsapp/Settings.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Whatsapp/Templates/Index.casos.md"
        }
      ]
    },
    {
      "id": "A1",
      "titulo": "ALVO atendimento--caixa-unificada--index",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1-alvo-contrato.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "${ALVOS}/atendimento--caixa-unificada--index.*"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/atendimento--caixa-unificada--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Contrato atendimento-caixa-unificada",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-alvo-contrato.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${CONTRATOS}/atendimento-caixa-unificada.contract.json"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/atendimento-caixa-unificada.contract.json"
        }
      ]
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados"
}
```

## 4 · O que este índice NÃO resolve
- `/whatsapp/conversations` (legado citado no docblock do `InboxController`) não foi medido.
- A medida `Atendimento--CaixaUnificada--Index` não foi lida — pode ter o mesmo problema de `design.json` compartilhado visto em Repair.
- `ConversationThread.tsx` (72 KB) em `Pages/Whatsapp/_components/` sem Page dona aparente — não verifiquei quem importa.
- Sem suíte `whatsapp-pest` obrigatória: o CI não bloqueia regressão de backend aqui (só contrato + casos).
