---
slug: 0366-fronteira-jana-forja-governance-kb
number: 366
title: "Fronteira dos 4 módulos emaranhados — Jana (IA) · Forja (trabalho + MCP) · Governance (conformidade) · KB (acervo), por pergunta respondida"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-03"
module: governance
tags: [fronteira, modularidade, jana, forja, governance, kb, mcp, sdd, escopo]
supersedes: []
superseded_by: []
related:
  - 0053-mcp-server-governanca-como-produto
  - 0070-jira-style-task-management-current-md-removed
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
  - 0351-sdd-from-source
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Ratificada por [W] em 2026-08-03**, em sessão de teste do sistema SDD: ao ver a matriz de
> dependência abaixo, [W] cortou o movimento em andamento — *"existe muito conflito entre os módulos
> Jana / Governance / Forja / KB. Acho melhor definir a finalidade de cada módulo antes de qualquer
> coisa. Estão com finalidades divergentes."* — e ratificou o eixo desta ADR. Número **0366** alocado
> por `next-id.mjs` ([ADR 0304](0304-alocacao-numero-ciente-trabalho-em-voo.md)). Ratificação formal =
> merge deste PR (R10). Corpo append-only ([ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md)).

# ADR 0366 — Fronteira dos 4 módulos: cada um responde UMA pergunta

## Contexto (medido em `origin/main` `252708dd61f`, 2026-08-03 — não suposto)

Quatro módulos cresceram sobre o mesmo substrato (`mcp_*`) sem fronteira declarada. A tentativa de
mover 4 telas de `Modules/Jana` para `Modules/Governance` esbarrou nisso: não havia critério pra
decidir o destino, porque **as finalidades declaradas dos quatro se contradizem**.

### C-1 · Estrutura

Recibo: `git ls-files 'Modules/<M>/<pasta>/*' | wc -l` por célula.

| Módulo | Controllers | Services | Entities | Migrations | Telas |
|---|---|---|---|---|---|
| Jana | 16 | 91 | **43** | 79 | 11 |
| Forja | 23 | 26 | 3 | 5 | 9 |
| KB | 14 | 10 | 13 | 14 | 3 |
| Governance | 8 | 35 | **1** | 5 | 7 |

**30 das 43 entidades do Jana são `Mcp*`** — `git ls-files 'Modules/Jana/Entities/Mcp*.php' | wc -l`
→ 30. E **59 das 79 migrations** do Jana são `mcp_*`. Ou seja: o módulo cuja finalidade declarada é
"IA do negócio" é, em massa de código, o dono do MCP server.

### C-2 · Matriz de dependência

Arquivos `.php` do módulo-linha que citam `Modules\<coluna>\`.
Recibo: `git grep -lF 'Modules\<T>\' -- 'Modules/<F>/' | wc -l`.
Controle positivo do método: `git grep -lF 'Entities\Mcp' -- 'Modules/Forja/' | wc -l` → **57**,
consistente com a célula Forja→Jana.

| de \ para | Jana | Governance | Forja | KB |
|---|---|---|---|---|
| **Jana** | — | 2 | 4 | 1 |
| **Governance** | **8** | — | 5 | 0 |
| **Forja** | **57** | 3 | — | 1 |
| **KB** | **16** | 1 | 1 | — |

**O Jana é a fundação dos outros três**: 81 arquivos dependem dele, ele depende de 7. A assimetria
não vem da IA — vem das `Mcp*`.

> ⚠️ **Nota de método (o erro fica registrado, não apagado).** As duas primeiras medições desta matriz
> voltaram **0 em todas as células** porque o padrão `"Modules\\$t\\"` em aspas duplas do bash come a
> barra. Só apareceu porque a célula Forja→Jana era conhecida (57) e servia de controle positivo.
> **Matriz de dependência sem controle positivo não é medição** — é a classe LC-08
> (`afirmar-sem-medir-fonte-certa`) do [`LICOES_CODE.md`](../LICOES_CODE.md). Em `git grep` com
> namespace PHP, usar aspas simples + `-F`.

### C-3 · As finalidades declaradas se contradizem

| Módulo | O que o próprio BRIEFING diz | O que a medição mostra |
|---|---|---|
| **Governance** | *"Enforcer + dashboard humano da Constituição v2… leitura consolidada das tabelas `mcp_*`"* | `/governance` **redireciona 302 pra `/ia`** desde 2026-05-22; **68 de 73 commits** recentes foram em `scripts/governance/` (Node), **1** no módulo PHP — *"o módulo Laravel está praticamente congelado"* (o próprio BRIEFING registra) |
| **Forja** | *"Cockpit de trabalho do time interno estilo Jira"* + *"absorveu a infraestrutura MCP do time (identity/token, `/api/mcp`, Daily Brief, handoff, ingest de sessões CC, hub Equipe, Admin do MCP)"* | absorveu o **uso** (57 arquivos), mas as **tabelas e entidades continuam no Jana** |
| **KB** | *"Persona real: **Wagner / governança** — o acervo é **99,8% documento de governança**"* (correção de 2026-07-17; a persona "Larissa operadora" era ficção do corpus mock) | consome 16 arquivos do Jana; e **`Jana/Memoria` é renderizada por `Modules/KB/Http/Controllers/MemoriaController.php:34`** |
| **Jana** | camada de IA do oimpresso, produto vendável (README: chat + metas + alertas) | é isso **e** o dono do MCP server; o ratio negócio/governança já está **em alarme** ([ADR 0334](0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md), US-COPI-139) |

### C-4 · As 7 sobreposições concretas

Cada linha tem arquivo — nenhuma é impressão.

| # | Tema | Está em | E também em |
|---|---|---|---|
| 1 | tabelas `mcp_*` | Jana (30 entities, 59 migrations) | Forja declara ter absorvido a infra MCP (57 arquivos) |
| 2 | audit | `Jana/Services/JanaAuditService` + `McpAuditLog` | tela `governance/Audit` |
| 3 | daily brief | `Jana/Services/BriefDiarioService` + `BriefDiarioAgent` | Forja declara ter absorvido o Daily Brief |
| 4 | governança (tela) | `Jana/Admin/Governanca/Index` | `governance/Dashboard` |
| 5 | tasks / roadmap | `Jana/Admin/Roadmap` (usa `TaskRegistry/TaskCrudService` → `McpTask`) | Forja **é** o cockpit de tasks (Kanban/Backlog/Roadmap/Triage) |
| 6 | memória / documento | `Jana/McpMemoryDocument` + `MemoriaFato` | KB `kb_nodes` via `KbBridgeFromMcpJob`; `Jana/Memoria` servida pelo KB |
| 7 | qualidade / evals | `Jana/Admin/Qualidade` (RAGAS) | Governance `module-grades` + `drift` |

## Decisão

### D-A — O critério de fronteira é a PERGUNTA que o módulo responde

Não é tecnologia, não é quem escreveu, não é onde a tabela nasceu. É **a pergunta do usuário**:

| Módulo | Finalidade | Persona | Pergunta que responde |
|---|---|---|---|
| **Jana** | IA conversacional do negócio — **produto vendável** | Larissa (cliente) | *"como está meu negócio e o que eu faço?"* |
| **Forja** | Cockpit de trabalho do time **+ dono do MCP server** | time interno ([W][M][F][L][E]) | *"o que a gente está fazendo?"* |
| **Governance** | Conformidade e enforcement — policies, audit, drift, grades, gates | [W]-auditor | *"a regra está sendo cumprida?"* |
| **KB** | Acervo de conhecimento — documento, busca, taxonomia | quem consulta | *"onde está escrito?"* |

**Corolário 1 — o MCP server pertence ao Forja.** É o consumidor dominante (57 arquivos), já declara
ter absorvido a infra MCP, e é o único dos quatro cuja finalidade declarada bate com o que consome.
As tabelas seguem `mcp_*` e as ADRs [0053](0053-mcp-server-governanca-como-produto.md) (MCP como
produto) e [0070](0070-jira-style-task-management-current-md-removed.md) (tasks) permanecem
inalteradas — muda o **dono do código**, não o contrato.

**Corolário 2 — o Jana fica só com produto.** Ao perder as `Mcp*`, o Jana volta a ser o que o README
sempre disse que era. Isso é o remédio estrutural do alarme da [ADR 0334](0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md):
enquanto a governança morar dentro do módulo de produto, o ratio negócio/governança mede duas coisas
somadas.

### D-B — Destino das 4 telas admin do Jana

A pergunta de cada tela decide seu destino — e **elas não vão todas pro mesmo módulo**, ao contrário
do que a formulação inicial ("colocar em outro módulo governance") supunha:

| Tela | Destino | Por quê |
|---|---|---|
| `Jana/Admin/Governanca/Index` | **Governance** | é a mesma tela que `governance/Dashboard` — sobreposição #4, funde |
| `Jana/Admin/Custos/Index` | **Governance** | `Chat.charter.md` já mandava: *"custo vai pra /governance — Wagner-only"* |
| `Jana/Admin/Qualidade/Index` | **Governance** | decisão [W] 2026-08-03: eval é **gate de conformidade**, medido contra piso/baseline igual `module-grades` e `drift` |
| `Jana/Admin/Roadmap` | **Forja** ⚠️ | usa `TaskCrudService`/`McpTask` — é tasks, e tasks é Forja. Mandar pro Governance criaria a 3ª tela de roadmap |

### D-C — Esta ADR NÃO move um arquivo

Ela declara a fronteira. **Todo movimento vem em PR separado, um por vez**, cada um com seu
pré-flight ([`proibicoes.md`](../proibicoes.md) §Regra Primária, FASE 1) e seu smoke real (R1).
Ordem proposta, do mais barato ao mais caro — cada etapa é decisão [W] independente:

| # | Movimento | Custo estimado | Bloqueia? |
|---|---|---|---|
| 1 | `Admin/Governanca` → funde com `governance/Dashboard` | baixo | — |
| 2 | `Admin/Custos` + `Admin/Qualidade` → Governance | baixo | — |
| 3 | `Admin/Roadmap` → Forja | médio (colide com telas de roadmap existentes) | conferir duplicação antes |
| 4 | 30 `Mcp*` + 59 migrations + tools MCP → Forja | **alto** — 192 arquivos, 7 áreas, Tier 0 | exige ADR própria + janela |

O item 4 **não está autorizado por esta ADR** — ela só declara que o destino correto é o Forja.
A migração em si precisa de decisão separada, com plano de migration e canary.

## Consequências

- **Positivas:** critério único e verificável pra decidir onde uma tela/serviço nasce; o Jana volta a
  ser mensurável como produto; a duplicação de conceito (#4, #5) ganha caminho de resolução; o SDD por
  módulo passa a ter escopo definido — sem isso o SDD do Jana teria documentado produto + infra como
  se fossem a mesma coisa, que foi o que travou o piloto do Produto em 11% ([ADR 0351](0351-sdd-from-source.md)).
- **Negativas / custo:** 4 PRs no mínimo; o item 4, se autorizado, toca 192 arquivos e o Forja inteiro.
  Durante a transição haverá período com telas movidas e tabelas não — estado intermediário legítimo,
  desde que declarado.
- **Não muda:** contratos MCP (0053), tasks Jira-style (0070), multi-tenant Tier 0 (0093). A exceção
  cross-tenant do Governance (Constituição Art. 6+8) permanece.

## Gate de reversão

Esta ADR é revertida (nova ADR com `supersedes: [366]`) se ficar demonstrado que:

1. o eixo "pergunta respondida" produz destino ambíguo em ≥3 telas novas seguidas — sinal de que o
   critério não discrimina; **ou**
2. mover as `Mcp*` pro Forja quebrar consumidor fora dos 4 módulos que a matriz não enxergou (a matriz
   cobre `Modules/*`; `app/`, `tests/` e `scripts/` foram contados no agregado de 192 mas não
   decompostos por módulo).

## Referências

- [ADR 0053](0053-mcp-server-governanca-como-produto.md) — MCP server como produto
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) — tasks Jira-style
- [ADR 0334](0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md) — anti-atrofia da inteligência de negócio
- [ADR 0351](0351-sdd-from-source.md) — `sdd-from-source` (o trabalho que expôs o emaranhado)
- BRIEFINGs dos 4: [Jana](../requisitos/Jana/BRIEFING.md) · [Forja](../requisitos/Forja/BRIEFING.md) · [Governance](../requisitos/Governance/BRIEFING.md) · [KB](../requisitos/KB/BRIEFING.md)
