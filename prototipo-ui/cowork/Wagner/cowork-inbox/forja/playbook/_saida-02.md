---
sessao: "_saida-02"
thread: "02 · casos.md de Roadmap/Index e das 4 telas ads/Admin"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 3e89fda755
---
# _saida-02

## Entregue (1 PR por tela, todos abertos em 2026-10-07)

| tela | PR | UCs | onde o Pest rodou |
|---|---|---|---|
| `Forja/Roadmap/Index` | #8927 | 3 (`UC-RQV-01..03`) | só no CI do PR (lane advisory Forja · MySQL) |
| `ads/Admin/Projects` | #8932 | 4 (`UC-ADPJ-01..04`) | só no CI do PR |
| `ads/Admin/ProjectShow` | #8937 | 2 (`UC-ADPS-01..02`) | só no CI do PR |
| `ads/Admin/TeamScopes` | #8925 | 3 (`UC-TSCOPE-01..03`) | CT 100: 3 passed, 15 assertions |
| `ads/Admin/Tools` | #8933 | 2 (`UC-TOOLS-01..02`) | CT 100: 2 passed, 23 assertions |

Todos os UCs estão `🧪 sem veredito` até o manifesto do CI (G-7). `ads/Admin/Graph` ficou fora: é do `Modules/KB`.

## Roadmap/Index convive com o Gantt

A ADR 0367 (D7) mantém `/project-mgmt/roadmap` até o Gantt provar que a substitui, e o `routes.php` registra que ela não morre. O `INVENTARIO-ANCORAS-2026-09-09.md:414` a chama de "morte contratada". Li isso como morte condicionada, ainda não cumprida, e por isso o caso foi escrito. Se [W] ler como aposentadoria, o #8927 fecha.

## Perguntas ao [W] (registradas como `[BACKLOG]`, sem UC)

1. Achado de leitura, sem teste: `ProjectDecomposerService::decompose` busca o project só pelo id, sem `business_id`, numa rota que só exige login. Se confirmado, é vazamento entre empresas (Tier 0). Pergunta no #8937.
2. TeamScopes: a lista de devs faz junção com `user_businesses`, tabela que não existe no schema nem no staging do CT 100.
3. TeamScopes/Tools: `grant`, `revoke` e `tools/{name}/execute` só exigem login. A auditoria das tools não filtra por `business_id`, e `triggered_by` grava `'wagner'` fixo.
4. Roadmap: epic cancelado deveria aparecer? As colunas ordenam por texto (`Q1-2027` antes de `Q2-2026`): ordem cronológica é contrato?
5. Projects: a tela só exige login. É intencional? As decisões ligadas ao project voltam sempre vazias desde a ADR 0363: podar o charter ou religar a fonte?

## Risco declarado

`UC-ADPJ-02` pode colidir no código automático de project, gerado por contagem. É hipótese de leitura, não foi medida.
