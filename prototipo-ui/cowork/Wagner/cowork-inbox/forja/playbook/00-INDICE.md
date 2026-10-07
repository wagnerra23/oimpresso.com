---
sessao: "00"
titulo: Forja (Tarefas · Equipe · MCP · Admin) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CL]"
criado: 2026-10-07
base: wagnerra23/oimpresso.com@main 836619f64d24 (lida 2026-10-07 10:32 UTC)
origem: telas-soltas/playbook/02-forja.md
---

# Forja — playbook

Saiu de `telas-soltas` (thread 02). Absorve o `../../COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` (11 ondas, 2026-09-03) **por ponteiro** — não copia as ondas; a thread R1 confere quais já estão no `main`.

## 1 · LEVANTAR — medido em `836619f64d24`

Fonte: `node scripts/qa/screen-coverage-map.mjs --screen <Mod/Tela>` (as 14) · `Inertia::render` no controller · `governance/design/contracts/*.contract.json` · `governance/design/targets/`. Nada no olho.

**As "14 Pages" são 13 da Forja + 1 do KB.** O namespace `ads/Admin/` tem 5 telas no mapa, mas `ads/Admin/Graph.tsx` mora em `Modules/KB/Resources/js/Pages/` (controller `Modules\KB\Http\Controllers\Admin\GraphController`; a rota `/ads/admin/graph` é registrada em `Modules/Forja/Http/routes.php:425`). Fica **fora** deste roteiro (§4).

| tela (rota) | Page | trio | UC c/ teste | scorecard | contrato | alvo | âncora (charter) |
|---|---|---|---|---|---|---|---|
| Aprovações (`/forja/aprovacoes`) | `Forja/Aprovacoes/Index` | ✅ | 8/8 | ✅ | ✅ `forja-aprovacoes` | — | `forja-aprova.jsx` |
| Trabalho (`/forja/trabalho`) | `Forja/Trabalho/Index` | ✅ | 20/20 | ✅ | ✅ `forja-trabalho` | — | `forja-page.jsx` |
| Gantt (`/forja/roadmap-gantt`) | `Forja/Roadmap/Gantt` | ✅ | 10/10 | ✅ | ✅ `forja-gantt` | — | `forja-page.jsx` ⚠️ D1 |
| Roadmap (`/project-mgmt/roadmap`) | `Forja/Roadmap/Index` | **casos ✗** | 0 | ✅ | — | — | n/a (bespoke) |
| Cockpit (`/forja`, `/forja/{backlog,quadro,changelog,mcp,integrador,saude,handoffs}`) | `team-mcp/Forja/Cockpit` | ✅ | **12/16** | ✅ | — | — | `forja-page.jsx` |
| Scorecard (`/team-mcp/scorecard`) | `team-mcp/Scorecard/Index` | ✅ | **6/8** | **✗** | — | — | n/a (PT-04) |
| Sessões CC (`/team-mcp/cc-sessions`) | `team-mcp/CcSessions/Index` | **casos ✗** | 0 | **✗** | — | — | n/a (PT-07) |
| Tarefas (`/team-mcp/tasks`) | `team-mcp/Tasks/Index` | **casos ✗** | 0 | **✗** | — | — | n/a (PT-05) |
| Equipe (`/team-mcp/team`) | `team-mcp/Team/Index` | **casos ✗** | 0 | **✗** | — | — | n/a (PT-01) |
| Projetos (`/ads/admin/projects`) | `ads/Admin/Projects` | **casos ✗** | 0 | ✅ | — | — | n/a (bespoke) |
| Projeto (`/ads/admin/projects/{id}`) | `ads/Admin/ProjectShow` | **casos ✗** | 0 | ✅ | — | — | n/a (bespoke) |
| Escopos (`/ads/admin/team-scopes`) | `ads/Admin/TeamScopes` | **casos ✗** | 0 | ✅ | — | — | n/a (PT-01) |
| Ferramentas (`/ads/admin/tools`) | `ads/Admin/Tools` | **casos ✗** | 0 | ✅ | — | — | n/a (PT-01) |

Comum às 13: charter ✅ (todos `draft`) · E2E Pest Browser ✗ · proto-baseline ✗ · "pode ligar" ✗ (zero sinal de prod). VRT: só Aprovações (`tests/Browser/visreg-screens.json`). Alvo: **nenhum** `governance/design/targets/forja--*` existe.

UCs sem teste (o `--screen` marca ÓRFÃO): `UC-FORJA-03 · 08 · 09 · 10` (Cockpit) · `UC-SC-02 · 06` (Scorecard).

**Scorecards órfãos — tela que não existe mais:** `memory/governance/scorecards/screens/forja-{activity,backlog,board,burndown,inbox,mywork,triage}-index.yaml` declaram `screen: Forja/<X>/Index`, e nenhuma dessas 7 Pages existe no `main`.

**Divergência medida (D1):** `forja-gantt.contract.json` tem `fonte: prototipo-ui/cowork/Wagner/forja-gantt.jsx`; o `Gantt.charter.md:5` declara `related_prototype: prototipo-ui/cowork/Wagner/forja-page.jsx`. Os dois arquivos existem no espelho.

## 2 · Decisões

| id | pergunta | destrava |
|---|---|---|
| D1 ✅ | A âncora do Gantt é `forja-gantt.jsx` (como o contrato) ou `forja-page.jsx` (como o charter)? | 05 |
| D2 ✅ | Corpo do Gantt → **mantém `@svar-ui/react-gantt` com tokens do DS** (07/10) | 05 |
| D3 ✅ | Toque → **24×24 mínimo**, 44 só persona Técnico (07/10) | 06 |
| D4 ✅ | 8 superfícies sem receptor → **sem thread agora** (07/10) | — |

## 3 · Threads

```json
{
  "modulo": "Forja",
  "sha": "836619f64d24",
  "gerado": "2026-10-07",
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "A âncora do Gantt é forja-gantt.jsx (contrato) ou forja-page.jsx (charter)?",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "05"
      ],
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: forja-page.jsx (o charter) — é o que o Cockpit vivo já usa; o contrato é corrigido para apontar pra ele",
      "quando": "2026-10-07"
    },
    {
      "id": "D2",
      "pergunta": "Corpo do Gantt: .fj-g-* próprio ou @svar-ui/react-gantt?",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "05"
      ],
      "resposta": "mantém @svar-ui/react-gantt vestido com tokens do DS; .fj-g-* é alvo de forma, não código a portar",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07.md (delegada ao [CC])"
    },
    {
      "id": "D3",
      "pergunta": "Alvo de toque (81 de 118 botões < 24×24)",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "06"
      ],
      "resposta": "24×24 mínimo (WCAG 2.2 2.5.8) crescendo a área clicável, não o glifo; 44 só persona Técnico",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07.md (delegada ao [CC])"
    },
    {
      "id": "D4",
      "pergunta": "Receptor das 8 superfícies sem dono",
      "dono": "W",
      "respondida": true,
      "destrava": [],
      "resposta": "sem thread agora; ordem futura issue-drawer > cmdk > novo-issue; ia/rag por último",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07.md (delegada ao [CC])"
    }
  ],
  "threads": [
    {
      "id": "A1",
      "titulo": "ALVO das 4 telas com âncora: aprovacoes · trabalho · roadmap-gantt · cockpit",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A1-alvos.md",
      "prefixo": [
        "governance/design/targets/forja--*"
      ],
      "nao_toca": [
        "Modules/Forja/",
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/forja--aprovacoes--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/forja--trabalho--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/forja--roadmap-gantt.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/forja--cockpit.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "casos.md das 3 team-mcp sem trio: CcSessions · Tasks · Team (1 PR por tela)",
      "dono": "CL",
      "vaga": 1,
      "prs": 3,
      "arquivo": "01-casos-team-mcp.md",
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.casos.md",
        "Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.casos.md",
        "Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.casos.md",
        "Modules/Forja/Tests/Feature/"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/team-mcp/Forja/",
        "Modules/Forja/Http/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.casos.md"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "casos.md de Roadmap/Index + 4 ads/Admin (1 PR por tela)",
      "dono": "CL",
      "vaga": 2,
      "prs": 5,
      "arquivo": "02-casos-roadmap-admin.md",
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.casos.md",
        "Modules/Forja/Resources/js/Pages/ads/Admin/",
        "Modules/Forja/Tests/Feature/"
      ],
      "nao_toca": [
        "Modules/KB/",
        "Modules/Forja/Http/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/ads/Admin/Projects.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Resources/js/Pages/ads/Admin/Tools.casos.md"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "UCs órfãos ganham teste: UC-FORJA-03/08/09/10 · UC-SC-02/06",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "03-ucs-orfaos.md",
      "prefixo": [
        "Modules/Forja/Tests/Feature/"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/",
        "Modules/Forja/Http/"
      ],
      "provas": [],
      "nota_provas": "screen-coverage-map --screen team-mcp/Forja/Cockpit e team-mcp/Scorecard/Index sem nenhuma linha ÓRFÃO"
    },
    {
      "id": "04",
      "titulo": "Scorecards: nota das 4 team-mcp sem nota + 7 órfãos de tela que não existe",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "04-scorecards.md",
      "prefixo": [
        "memory/governance/scorecards/screens/team-mcp-*",
        "memory/governance/scorecards/screens/forja-*"
      ],
      "nao_toca": [
        "Modules/Forja/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "memory/governance/scorecards/screens/team-mcp-ccsessions-index.yaml"
        },
        {
          "tipo": "arquivo",
          "path": "memory/governance/scorecards/screens/team-mcp-scorecard-index.yaml"
        },
        {
          "tipo": "arquivo",
          "path": "memory/governance/scorecards/screens/team-mcp-tasks-index.yaml"
        },
        {
          "tipo": "arquivo",
          "path": "memory/governance/scorecards/screens/team-mcp-team-index.yaml"
        },
        {
          "tipo": "ausente",
          "path": "memory/governance/scorecards/screens/forja-triage-index.yaml"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Gantt: alinhar charter e contrato à âncora decidida na D1",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "05-gantt-ancora.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.charter.md",
        "governance/design/contracts/forja-gantt.contract.json"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.tsx"
      ],
      "provas": [],
      "nota_provas": "related_prototype do charter == fonte do contrato"
    },
    {
      "id": "R1",
      "titulo": "Conferir as 11 ondas do EXPORT-FORJA (2026-09-03) contra o main",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "R1-export-forja.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/_saida-R1.md"
      ],
      "nao_toca": [
        "Modules/",
        "resources/"
      ],
      "provas": [],
      "nota_provas": "_saida-R1.md com 1 linha por onda: entregue (PR) · parcial · não iniciada"
    },
    {
      "id": "06",
      "titulo": "Alvo de toque 24×24 nas telas da Forja (D3)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "06-alvo-toque.md",
      "depende_decisoes": [
        "D3"
      ],
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/",
        "resources/css/"
      ],
      "nao_toca": [
        "resources/css/tokens/",
        "Modules/Forja/Http/"
      ],
      "provas": [],
      "nota_provas": "bateria A1–A12 (alvo de toque) nas 4 telas com âncora: 0 botões < 24×24; recibo com antes (81/118) e depois"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve

- `ads/Admin/Graph` — é do `Modules/KB`. Pertence a um roteiro do KB, não a este.
- Quais das 11 ondas do EXPORT-FORJA já landaram: **não medi** (R1). O único sinal tomado foi sintático — uso de classe `fj-`/`ap-`/`tf-` do `cowork-forja-bundle.css` por arquivo (zero em `ForjaTriage`, `ForjaBacklog`, `ForjaQuadro`, `ForjaDossier`, `ForjaTabBar`) — e ele não prova entrega nem ausência.
- O nome exato dos 4 alvos da A1 sai de `--tela <slug>` do `alvo.mjs`; se a A1 escolher outro slug, ela corrige as provas no `_saida-A1.md`, não aqui.
- Os 7 scorecards órfãos: antes de apagar, a 04 confere se a catraca `screen-grades-ratchet` não os lê como piso. Só um deles (`forja-triage`) entra como prova; os outros 6 vão no recibo.
- As 8 construções sem receptor do EXPORT (`forja-issue-drawer`, `forja-cmdk`, `forja-notifs`, `forja-novo-issue`, `forja-runbook`, `forja-handoff`, `forja-ia`, `forja-rag`) seguem esperando [W] declarar receptor — não viram thread aqui.
