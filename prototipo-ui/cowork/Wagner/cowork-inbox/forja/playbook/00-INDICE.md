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
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: forja-page.jsx (o charter) — é o que o Cockpit vivo já usa; o contrato é corrigido para apontar pra ele · errata 07b: a âncora é forja-page.jsx; o contrato NÃO muda (a copy mora em forja-gantt.jsx, dentro da mesma âncora — trocar faria a anti-tautologia acusar 6/6)",
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
    },
    {
      "id": "D5",
      "pergunta": "ProjectDecomposerService::decompose busca o project só pelo id (_saida-02 pergunta 1)",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "09"
      ],
      "resposta": "só depois de confirmar com teste: teste primeiro; se vermelho, conserto no mesmo PR",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07b.md"
    },
    {
      "id": "D6",
      "pergunta": "TeamScopes: lista de devs junta com user_businesses (tabela inexistente)",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "10"
      ],
      "resposta": "teste primeiro; se a lista vier vazia/errada, trocar a junção pela tabela real (pergunta pulada no formulário → [CC] decidiu pela recomendação)",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07c.md"
    },
    {
      "id": "D7",
      "pergunta": "Ferramentas: grant/revoke/execute só com login; auditoria sem business_id; triggered_by='wagner'",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "11"
      ],
      "resposta": "permissão própria + auditoria filtrada por empresa + autor = usuário logado",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07c.md"
    },
    {
      "id": "D8",
      "pergunta": "Roadmap: épico cancelado aparece?",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "12"
      ],
      "resposta": "esconder (pergunta pulada no formulário → [CC] decidiu pela recomendação)",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07c.md"
    },
    {
      "id": "D9",
      "pergunta": "Roadmap: ordem das colunas por trimestre",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "12"
      ],
      "resposta": "cronológica",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07c.md"
    },
    {
      "id": "D10",
      "pergunta": "Projects: a tela só exige login",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "13"
      ],
      "resposta": "exigir permissão do módulo Forja",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07c.md"
    },
    {
      "id": "D11",
      "pergunta": "Projects: decisões ligadas ao projeto sempre vazias desde a ADR 0363",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "13"
      ],
      "resposta": "tirar do charter",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07c.md"
    },
    {
      "id": "D12",
      "pergunta": "Sessões CC: cc.read.team vê o time ou só as próprias?",
      "dono": "W",
      "respondida": true,
      "destrava": [],
      "resposta": "só as próprias (charter e código atuais) — a SPEC-cc-sessions é que se corrige",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07d.md"
    },
    {
      "id": "D13",
      "pergunta": "Tarefas: transição proibida pelo FSM responde 404",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "14"
      ],
      "resposta": "responder 422",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07d.md"
    },
    {
      "id": "D14",
      "pergunta": "Equipe (Tier 0): token/DXT/cota não conferem o negócio do id da URL",
      "dono": "W",
      "respondida": true,
      "destrava": [
        "15"
      ],
      "resposta": "conferir o negócio em todas e apagar a rota legacy DELETE /team/token/{token}",
      "quando": "2026-10-07",
      "fonte": "_DECISOES-W-2026-10-07d.md"
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
          "path": "governance/design/targets/forja--cockpit.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ],
      "nota_provas": "3 de 4 medidos (_saida-A1); o Gantt saiu para A1b, que depende da 07"
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
      "provas": [
        {
          "tipo": "contem",
          "path": "governance/design/contracts/forja-gantt.contract.json",
          "padrao": "mesma âncora"
        }
      ],
      "nota_provas": "errata 07b: o contrato declara (_nota_fonte) que forja-gantt.jsx é o arquivo da copy DENTRO da âncora forja-page.jsx do charter"
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
        "Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/",
        "Modules/Forja/Resources/js/Pages/Forja/Trabalho/",
        "Modules/Forja/Resources/js/Pages/team-mcp/Forja/",
        "resources/css/"
      ],
      "nao_toca": [
        "resources/css/tokens/",
        "Modules/Forja/Http/",
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/"
      ],
      "provas": [],
      "nota_provas": "bateria A1–A12 (alvo de toque) nas 4 telas com âncora: 0 botões < 24×24; recibo com antes (81/118) e depois · Gantt fica fora (é da 05)"
    },
    {
      "id": "07",
      "titulo": "Máquina: alvo.mjs aceita clique em cadeia (--clicar repetível) ou chave de localStorage arbitrária",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "07-alvo-clique-cadeia.md",
      "prefixo": [
        "scripts/design-sync/alvo.mjs",
        "scripts/design-sync/alvo.test.mjs"
      ],
      "nao_toca": [
        "governance/design/targets/",
        "prototipo-ui/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "scripts/design-sync/alvo.mjs",
          "padrao": "passosDeClique"
        }
      ],
      "nota_provas": "errata 2026-10-07: entregue como --clicar repetível (#8948)"
    },
    {
      "id": "A1b",
      "titulo": "ALVO forja--roadmap-gantt (o 4º da A1)",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "A1b-alvo-gantt.md",
      "depende_threads": [
        "07"
      ],
      "prefixo": [
        "governance/design/targets/forja--roadmap-gantt.*"
      ],
      "nao_toca": [
        "Modules/Forja/",
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "governance/design/targets/forja--roadmap-gantt.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Defeitos achados pelos scorecards da 04 (Tasks autor fixo · Team r.ok · CcSessions limpar · Scorecard vazio)",
      "dono": "CL",
      "vaga": 2,
      "prs": 2,
      "arquivo": "08-gaps-scorecard.md",
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/team-mcp/Tasks/",
        "Modules/Forja/Resources/js/Pages/team-mcp/Team/",
        "Modules/Forja/Resources/js/Pages/team-mcp/CcSessions/",
        "Modules/Forja/Resources/js/Pages/team-mcp/Scorecard/",
        "Modules/Forja/Http/Controllers/TasksAdminController.php",
        "Modules/Forja/Tests/Feature/ForjaGapsScorecard*"
      ],
      "nao_toca": [
        "memory/governance/scorecards/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx",
          "padrao": "author: 'wagner'"
        }
      ],
      "nota_provas": "PR-a (Tier 0): autor do evento vem do usuário logado no controller, não do body — teste: usuário B move tarefa → mcp_task_events.author = B. PR-b: Team checa r.ok (403/419/500 com mensagem própria) · CcSessions Limpar zera from/to · Scorecard com 0 checks mostra estado vazio e erro do defer sai de 'Carregando…'."
    },
    {
      "id": "09",
      "titulo": "Tier 0: decompose de project sem business_id — provar com teste, depois consertar",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "09-decompose-tenant.md",
      "depende_decisoes": [
        "D5"
      ],
      "prefixo": [
        "Modules/Forja/Services/ProjectDecomposerService.php",
        "Modules/Forja/Http/Controllers/",
        "Modules/Forja/Tests/Feature/ForjaDecomposeTenantTest.php"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Tests/Feature/ForjaDecomposeTenantTest.php"
        }
      ],
      "nota_provas": "recibo com o teste vermelho no main (tenant 99 decompõe project do 98) e verde no branch; se sair verde no main, não há conserto e a thread fecha com o teste"
    },
    {
      "id": "10",
      "titulo": "TeamScopes: provar a junção com user_businesses; se quebrada, trocar pela tabela real (D6)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "10-teamscopes-juncao.md",
      "depende_decisoes": [
        "D6"
      ],
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.casos.md",
        "Modules/Forja/Tests/Feature/ForjaTeamScopesDevsTest.php"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/ads/Admin/TeamScopes.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Tests/Feature/ForjaTeamScopesDevsTest.php"
        }
      ],
      "nota_provas": "recibo: teste com 2 devs do negócio — vermelho no main se a junção quebra; verde no branch. O controller entra no prefixo do PR depois de achado (ler antes)"
    },
    {
      "id": "11",
      "titulo": "Tier 0 · Ferramentas: permissão própria em grant/revoke/execute + auditoria por empresa + autor real (D7)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "11-ferramentas-permissao.md",
      "depende_decisoes": [
        "D7"
      ],
      "prefixo": [
        "Modules/Forja/Tests/Feature/ForjaToolsPermissao*",
        "Modules/Forja/Resources/js/Pages/ads/Admin/Tools.casos.md"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/ads/Admin/Tools.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Tests/Feature/ForjaToolsPermissaoTest.php"
        }
      ],
      "nota_provas": "teste: usuário sem a permissão → 403 nas 3 rotas; auditoria do tenant 99 não aparece pro 98; triggered_by = usuário logado"
    },
    {
      "id": "12",
      "titulo": "Roadmap: esconder épico cancelado + colunas em ordem cronológica (D8 · D9)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "12-roadmap-ordem.md",
      "depende_decisoes": [
        "D8",
        "D9"
      ],
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.tsx",
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.casos.md",
        "Modules/Forja/Tests/Feature/ForjaRoadmap*"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/Forja/Resources/js/Pages/Forja/Roadmap/Index.casos.md",
          "padrao": "cronológica"
        }
      ]
    },
    {
      "id": "13",
      "titulo": "Projects: exigir permissão do módulo + tirar decisões do charter (D10 · D11)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "13-projects-acesso.md",
      "depende_decisoes": [
        "D10",
        "D11"
      ],
      "prefixo": [
        "Modules/Forja/Resources/js/Pages/ads/Admin/Projects.charter.md",
        "Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.charter.md",
        "Modules/Forja/Tests/Feature/ForjaProjectsAcesso*"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/ads/Admin/Projects.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Tests/Feature/ForjaProjectsAcessoTest.php"
        }
      ],
      "nota_provas": "teste: usuário logado sem permissão da Forja → 403 em /ads/admin/projects; charter sem a seção de decisões"
    },
    {
      "id": "14",
      "titulo": "Tarefas: transição proibida pelo FSM → 422 (D13)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "14-tasks-fsm-422.md",
      "depende_decisoes": [
        "D13"
      ],
      "prefixo": [
        "Modules/Forja/Http/Controllers/TasksAdminController.php",
        "Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.casos.md",
        "Modules/Forja/Tests/Feature/TasksFsmTransicaoTest.php"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/team-mcp/Tasks/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Tests/Feature/TasksFsmTransicaoTest.php"
        }
      ]
    },
    {
      "id": "15",
      "titulo": "Tier 0 · Equipe: conferir negócio em token/DXT/cota + apagar rota legacy (D14)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "15-team-tenant.md",
      "depende_decisoes": [
        "D14"
      ],
      "prefixo": [
        "Modules/Forja/Http/routes.php",
        "Modules/Forja/Http/Controllers/TeamController.php",
        "Modules/Forja/Tests/Feature/TeamTenantTest.php"
      ],
      "nao_toca": [
        "Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Forja/Tests/Feature/TeamTenantTest.php"
        }
      ],
      "nota_provas": "teste tenant 98×99 em gerarToken, gerarDxt, atualizarQuota e DELETE token → 404; a rota legacy some do routes.php"
    },
    {
      "id": "16",
      "titulo": "SPEC-cc-sessions: alinhar ao charter (cc.read.team = só as próprias) (D12)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "16-spec-ccsessions.md",
      "depende_decisoes": [
        "D12"
      ],
      "prefixo": [
        "memory/requisitos/Jana/SPEC-cc-sessions.md"
      ],
      "nao_toca": [
        "Modules/"
      ],
      "provas": [],
      "nota_provas": "só texto; o recibo cita o trecho antes/depois"
    }
  ],
  "revisado": "2026-10-07 _saida-A1: Gantt → A1b + thread 07 (alvo.mjs clique em cadeia) · 2026-10-07 _saida-04 (main 822ccf022258): 04 entregue; achados → thread 08 · 2026-10-07 _ERRATA forja (07 → passosDeClique) @59f777d978da · 2026-10-07 decisões [W] do formulário · 2026-10-07 decisões D6–D11 (formulário 4 perguntas) → threads 10–13 · 2026-10-07 refino: provas decidíveis · 2026-10-07 SINCRONIZAR @50e23057f1c2 · 2026-10-07 decisões [W] (formulário SINCRONIZAR 2)"
}
```

## 4 · O que este índice NÃO resolve

- `ads/Admin/Graph` — é do `Modules/KB`. Pertence a um roteiro do KB, não a este.
- Quais das 11 ondas do EXPORT-FORJA já landaram: **não medi** (R1). O único sinal tomado foi sintático — uso de classe `fj-`/`ap-`/`tf-` do `cowork-forja-bundle.css` por arquivo (zero em `ForjaTriage`, `ForjaBacklog`, `ForjaQuadro`, `ForjaDossier`, `ForjaTabBar`) — e ele não prova entrega nem ausência.
- O nome exato dos 4 alvos da A1 sai de `--tela <slug>` do `alvo.mjs`; se a A1 escolher outro slug, ela corrige as provas no `_saida-A1.md`, não aqui.
- Os 7 scorecards órfãos: antes de apagar, a 04 confere se a catraca `screen-grades-ratchet` não os lê como piso. Só um deles (`forja-triage`) entra como prova; os outros 6 vão no recibo.
- As 8 construções sem receptor do EXPORT (`forja-issue-drawer`, `forja-cmdk`, `forja-notifs`, `forja-novo-issue`, `forja-runbook`, `forja-handoff`, `forja-ia`, `forja-rag`) seguem esperando [W] declarar receptor — não viram thread aqui.
