---
doc: CONCEITO — Charters como Conhecimento Governado no KB
status: conceito (draft de visão — pré-ADR)
owner: wagner
created: 2026-06-01
authors: [wagner, claude-opus]
module: KB
related_adrs: [0061, 0093, 0101, 0149, 0150, 0242]
related_docs:
  - memory/requisitos/KB/SCHEMA-DB-V1.md
  - memory/requisitos/SRS/DEPRECATION-PLAN.md
  - memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md
supersede_target: nenhum (conceito novo)
---

# CONCEITO — Charters como Conhecimento Governado no KB

> **Origem:** Wagner 2026-06-01 — "eu quero ele como KB… o KB tem trilha, categoria, módulos, tem tudo que preciso… e não quero editar livre a page charter. No KB dá pra colocar comentários (conteúdo supervisionado) e tem a parte de autorizar — é um sistema de governança completo. Construa o conceito, crie o estado da arte."

## Tese (1 parágrafo)

O **charter deixa de ser arquivo solto** (`*.charter.md` perdido ao lado do `.tsx`) e vira um **nó governado do grafo de conhecimento (KB)**, em **dois níveis** — **Page Charter** (contrato de tela) e **Module Charter** (contrato de módulo: meta + limite + backlog + changelog). O **núcleo é imutável** (vem do git via bridge canônico, `is_editable=false` — ninguém edita livre), mas **evolui por contribuição supervisionada** (sugestões/comentários) sujeita a um **workflow de autorização** (propor → revisar → aprovar → publicar). É o KB fazendo o que já faz de melhor — biblioteca viva, governada, com trilha/categoria/grafo — aplicado aos contratos do próprio produto. **~70% disso já existe no KB hoje;** este conceito define os ~30% que faltam.

---

## 1. O que o KB JÁ entrega (a fundação — não reinventar)

O `Modules/KB` (grafo de conhecimento, [SCHEMA-DB-V1.md](SCHEMA-DB-V1.md), ADR 0149/0150) já modela quase tudo:

| Capacidade que o Wagner quer | Já existe? | Onde |
|---|---|---|
| **Charter como tipo de nó** | ✅ | `kb_nodes.type` inclui `charter` (enum VARCHAR) |
| **Não-editável (imutável)** | ✅ | `is_editable=false` ⇒ `body_blocks IS NULL`, conteúdo vem do JOIN com `mcp_memory_documents` (git). Enforce via `KbNodeObserver` + provado em `GovernanceInvariantsTest` (PUT ilegal → 403/422) |
| **Liga charter ↔ tela** | ✅ | Aresta `charter-of` no `kb_edges`, **auto-derivada** do path `*.charter.md` ao lado do `.tsx` (`KbEdgeAutoDeriver::deriveCharterOf`) |
| **Versionamento append-only** | ✅ | Bridges (charter) versionam via `mcp_memory_documents_history` (o próprio git). Artigos via `kb_node_versions` |
| **Categoria** | ✅ | `kb_categories` — já existe a categoria **`governance`** ("ADRs, sessions, **charters**, runbooks, briefings, specs") |
| **Trilha (learning path)** | ✅ | `kb_paths` + `kb_path_steps` com `audience` (ex.: "Wagner onboarding governança") |
| **Comentários** | ✅ (cru) | `kb_comments` — texto ancorado em `block_idx`. Tem audit LGPD (LogsActivity) |
| **Grafo / relações** | ✅ | `kb_edges` tipadas: `supersedes`, `charter-of`, `cross-link`, `related-by-tag`, `references-data`, `ai-related` |
| **Freshness / re-verificação** | ✅ | `last_verified_at` + endpoint `POST /kb/nodes/{slug}/reverify` |
| **Multi-tenant Tier 0** | ✅ | `business_id` + FK em todas `kb_*` (ADR 0093 IRREVOGÁVEL) |
| **IA/RAG sobre o corpus** | ✅ | `KbAiController` (`/kb/ai/ask`) delega `Modules/Jana` |
| **Servir charter pro Claude/agentes** | ✅ | tool MCP `charter-fetch` (vive em `Modules/Jana/Mcp/Tools/CharterFetchTool.php`) |

**Conclusão:** o charter já é cidadão de primeira classe do KB, read-only e ligado à tela. A base está pronta.

---

## 2. O que FALTA (os 3 gaps que este conceito preenche)

| # | Gap | Estado hoje | O que falta |
|---|---|---|---|
| **G1** | **Module Charter** (nível módulo) | Só existe Page Charter (por tela). Não há contrato de **módulo** com meta/limite/backlog/changelog | Novo nível de charter — consolidado, não 1 arquivo por módulo solto |
| **G2** | **Autorização (workflow)** | `status` = `draft\|ok\|outdated\|deprecated` — **sem etapa de aprovação** | Ciclo `proposto → em revisão → aprovado → publicado` + papéis (quem autoriza) |
| **G3** | **Contribuição supervisionada** | `kb_comments` é comentário cru (sem status, sem "isto é uma proposta de mudança") | "**Modo sugestão**": contribuição estruturada que aguarda aprovação e pode ser **promovida ao núcleo** |

> Os três gaps são **exatamente** o que o Wagner verbalizou: "colocar conteúdo supervisionado" (G3) + "a parte de autorizar" (G2) + "estender o charter pro módulo: meta/limite/backlog/changelog" (G1).

---

## 3. O modelo conceitual — Charter governado em 2 camadas

### 3.1 Dois níveis de charter

```
NÍVEL MÓDULO   →  Module Charter  (1 por módulo)
                  · Meta (pra onde vai)
                  · Limite (até onde / o que NÃO é)
                  · Backlog (o que falta — US + DoD%)
                  · Changelog (o que já entregou)
                       │  edge: governs-module / parent-charter
                       ▼
NÍVEL TELA     →  Page Charter   (1 por tela)   ← já existe
                  · Mission / Goals / Non-Goals / UX / Anti-hooks
                       │  edge: charter-of (auto)
                       ▼
                  Tela (.tsx)
```

O **Module Charter é o nível que faltava** na matriz do [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) (que tinha Capterra=mercado e Page Charter=tela, mas nada que respondesse "o que este *módulo* é, até onde vai, o que falta e o que já fez" num lugar só).

### 3.2 Núcleo imutável vs camada de evolução (o coração do "não editar livre")

Cada charter-nó tem **duas zonas** com regras de mudança opostas:

| Zona | Conteúdo | Fonte da verdade | Como muda | Autorização |
|---|---|---|---|---|
| 🔒 **Núcleo** | Mission, Goals, Non-Goals, Limite, Meta | **git** (`*.charter.md` → `mcp_memory_documents`) | **só por PR no git** → bridge re-sincroniza o nó | **merge do PR** (Wagner / owner) |
| 💬 **Camada KB** | Sugestões, comentários, dúvidas, anexos aprovados, métricas, verificação de frescor | **`kb_*` tables** | workflow de sugestão no próprio KB | **aprovação no KB** (owner do charter) |

Isso resolve a tensão: **o núcleo nunca é editado livre** (continua governado pelo git, ADR 0061), mas a **inteligência ao redor dele cresce** dentro do KB de forma supervisionada. A camada KB nunca contradiz o núcleo — ela **propõe** mudanças, que só entram no núcleo via PR.

---

## 4. O ciclo de governança (G2 + G3) — propor → revisar → aprovar → publicar

```
   [qualquer um com kb.comment]                [owner do charter]            [git/bridge]
            │                                          │                          │
   1. PROPOR ───────────────► 2. EM REVISÃO ──────────► 3. APROVAR ──────────────► 4. PUBLICAR
   cria "sugestão" no nó       owner avalia              aceita | rejeita           sugestão aceita
   (kb_suggestion)             (discussão = comments)    (audit log)                ├─ muda NÚCLEO? → abre PR no .charter.md
   status=proposed             status=under_review       status=accepted/rejected   │   (merge = publicação; bridge atualiza nó)
                                                                                     └─ é anexo KB? → publica direto como bloco aprovado
```

**Mecanismo (reusa o que existe + 1 delta):**

- **Propor** = evoluir `kb_comments` → uma "sugestão" ganha `kind` (`comment | suggestion | question | erratum`) e `status` (`proposed | under_review | accepted | rejected | merged`). Quem propõe precisa de `kb.comment` (já existe).
- **Revisar** = o `owner` do charter (campo já existe no frontmatter) recebe na inbox; discussão acontece como comentários encadeados.
- **Aprovar** = ação com permissão nova `kb.charter.approve` (só owner/Wagner). Registrada em `activity_log` (LGPD Art. 37 — já temos) + `mcp_audit_log`.
- **Publicar** = bifurca:
  - **Mudança de núcleo** → o sistema **gera um PR** (ou uma task MCP) alterando o `*.charter.md` no git. Merge = publicação real; o bridge job re-sincroniza o nó em ≤15 min. **Git continua fonte da verdade** (ADR 0061 preservado).
  - **Anexo KB** (ex.: "exemplo de uso", "gotcha conhecido") → publica como bloco aprovado **anexo** ao nó, sem tocar o núcleo git.

> Esse é o padrão **propose-review-approve-publish** com **git como sistema de registro** e KB como **sistema de engajamento** — o mesmo princípio de developer portals (catálogo no git, governança na plataforma).

---

## 5. As dimensões do KB aplicadas ao charter (o que o Wagner achou "lindo")

| Dimensão KB | Como o charter usa |
|---|---|
| **Categoria** | `governance` (já seedada) → subcategoria `charter` (page) / nova `module-charter` |
| **Trilha** (`kb_paths`) | "Onboarding de um módulo": Module Charter → Page Charters das telas → ADRs → runbook. Trilha por `audience` (dev novo, Wagner, agente IA) |
| **Módulo** | hoje implícito (categoria `sistema` / tags). **Delta proposto:** dimensão explícita de módulo (tag canônica `module:<Nome>` ou FK), pra o Module Charter ancorar e o Page Charter herdar |
| **Grafo** (`kb_edges`) | `charter-of` (charter→tela, auto) · novo `governs-module` (module charter→módulo) · novo `parent-charter` (page→module) · `supersedes` (charter v2→v1) |
| **Decision tree** | bônus: troubleshooter "minha tela está fazendo X que o charter proíbe — o que fazer?" |

---

## 6. Module Charter em detalhe (G1) — e a função do SRS que se salva

O **Module Charter** consolida 4 eixos, **puxando de fontes que já existem** (não cria dado duplicado):

| Eixo | De onde vem | Observação |
|---|---|---|
| **Meta** | `BRIEFING.md` + `SCOPE.md` (`purpose`) | "pra onde o módulo vai" |
| **Limite** | `SCOPE.md` (`not_contains`) + Non-Goals dos Page Charters filhos | "o que NÃO é / teto de ambição" |
| **Backlog** | `SPEC.md` (US-XXX + **DoD %** dos checkboxes) | progresso real |
| **Changelog** | `CHANGELOG.md` | "o que já entregou" |
| **Saúde** (bônus) | `module:grade` (Governance, D1–D9) | nota de maturidade |

**É aqui que entra a função do SRS que vale salvar:** o `RequirementsFileReader` (de `Modules/SRS`) **já faz exatamente este parse** — lê `memory/requisitos/<Módulo>/`, extrai frontmatter + US com DoD% + rules + README + CHANGELOG + ADRs + coverage 0-100. Em vez de morrer com o SRS, ele **migra pro KB** como o *leitor que alimenta o Module Charter*. (Ver [SRS/DEPRECATION-PLAN.md](../SRS/DEPRECATION-PLAN.md) item 13 — antes mapeado pra `app/Services`; este conceito o redireciona pro KB com propósito claro.)

> **Decisão de produto importante (recomendada):** o Module Charter é **visão consolidada read-only** das peças que já existem — **não** uma tabela editável nova. Isso evita repetir o erro que matou o SRS (a tabela `srs_entries` ambiciosa que nunca nasceu). A *evolução* acontece pelo ciclo §4 (sugestão → PR nos arquivos-fonte).

---

## 7. Estado da arte mundial 2026 (benchmark)

> ✅ **Pesquisa web 2026 concluída** (6 territórios, fontes primárias). O estado-da-arte converge num desenho que o oimpresso já tangencia. Síntese abaixo + tabela de padrões transferíveis.

| Padrão de mercado | Quem exemplifica | Princípio transferível p/ Charters-as-KB |
|---|---|---|
| **Software Catalog + descriptor no git** | Backstage (Spotify), Cortex, Port, OpsLevel | Entidade descrita por arquivo versionado no git (`catalog-info.yaml`); a plataforma indexa e governa por cima. = nosso bridge `.charter.md` → `kb_node` |
| **Maturity scorecards** | Backstage Soundcheck / Tech Insights | Nota de maturidade por componente, com checks objetivos. = `module:grade` ligado ao Module Charter |
| **Docs acoplados ao componente** | TechDocs (docs-as-code) | Documentação vive junto da entidade e renderiza no portal |
| **Páginas verificadas / trust** | Confluence "verified", Guru "card verification + intervalo" | `last_verified_at` + re-verificação periódica; combate doc-cemitério |
| **Modo sugestão (não edição)** | Google Docs / Notion suggestions, GitHub "suggested changes" | Contribuir = propor, não sobrescrever. = nosso `kb_suggestion` |
| **RFC/RFD git-backed com estados** | Oxide RFD, Rust RFCs, IETF | `discussion → published → committed`; append-only, supersedes. = nosso ciclo §4 |
| **ADR append-only + supersede** | MADR / Nygard | Decisão nunca editada em-place; nova versão referencia a antiga. = já é a regra do projeto |
| **Taxonomia de documentação** | Diátaxis (tutorial/how-to/reference/explanation) | Tipos de nó com propósitos distintos. = `type` + categoria/trilha |
| **Contract linting** | OpenAPI + Spectral | Contrato validável por regra automática. = Pest GUARD dos Non-Goals (ADR 0101) |
| **Conhecimento consumível por IA** | MCP, context engineering, agent constitutions | Contrato exposto a agentes com baixo custo de token. = `charter-fetch` MCP |

### 7.1 Top 10 princípios de design (rankeados) — fontes 2026

1. **Co-localização + git como fonte de verdade.** Charter mora ao lado do `.tsx`/módulo, versionado. Backstage descobriu empiricamente: metadata se mantém atualizada *quando vive com o código que descreve*. ([backstage.io/docs/features/software-catalog](https://backstage.io/docs/features/software-catalog/)) — **o oimpresso já faz** (`.charter.md` co-locado + bridge).
2. **Núcleo append-only, imutável quando `accepted`.** "Accepted has to be immutable for the collection to be trustworthy" (MADR). Mudar = supersede. ([adr.github.io/madr](https://adr.github.io/madr/)) — **já é regra do projeto** (proíbe editar ADR canon).
3. **Lifecycle que separa "decidido" de "verdadeiro".** Oxide RFD: `accepted` (decisão) ≠ `committed`/`live` (descreve como o sistema **funciona hoje**, verificado). ([oxide.computer/blog/rfd-1](https://oxide.computer/blog/rfd-1-requests-for-discussion)) — adotar o estado `live`.
4. **Status de confiança + cadência de re-verificação.** Guru: `verified`/`stale` + timestamp + verificador; vencimento gera **tarefa na fila do owner**; re-verificar = 1 clique; stale+sem-uso → auto-arquiva. **Único antídoto comprovado contra cemitério.** ([getguru.com/features/verification](https://www.getguru.com/features/verification)) — KB já tem `last_verified_at`+reverify; falta **cadência**.
5. **Governança proporcional ao risco.** OpsLevel: rubricas escopadas por criticidade (não um baseline único). ([opslevel.com](https://www.opslevel.com/resources/cortex-vs-backstage-whats-the-best-internal-developer-portal)) — bate com **"Tiered cost"** da Constituição v2.
6. **Muitos contribuem draft, poucos aprovam — com comentário + audit.** Approve/reject **exige comentário**; toda ação auditada. ([Document360](https://www.eesel.ai/blog/gitbook-vs-document360), ApprovalFlow) — alinha com `mcp_audit_log` + MCP Elicitation.
7. **Schema linter no CI que falha o PR ("Spectral para charters").** Mission/Non-Goals/owner/UX-targets validados como policy-as-code. ([stoplight.io/open-source/spectral](https://stoplight.io/open-source/spectral)) — embrião já existe (`stylelint-gate`, `module:grade`).
8. **Ownership executável.** CODEOWNERS dispara review obrigatório do owner; merge que toca `.tsx` sem tocar/re-verificar o charter é bloqueado.
9. **Entidade tipada + scorecard de maturidade first-class.** Backstage Soundcheck: checks pass/fail → níveis bronze/prata/ouro. ([backstage.spotify.com/.../soundcheck](https://backstage.spotify.com/partners/spotify/plugin/soundcheck/)) — ligar ao `module:grade`.
10. **Charter mínimo — IA-aware (contra-intuitivo).** Charter inchado/gerado por IA **degrada o agente** (−success, **+20% custo de inferência**) e queima budget de contexto. ([Gloaguen et al. 2026, arXiv 2602.20478](https://arxiv.org/html/2602.20478v1)) — **só o julgamento que linter nenhum captura; não repetir o que CI/`module:grade` já garante.**

### 7.2 Padrões de workflow de aprovação (nomeados)

| Padrão | Mecânica | Origem | Usar p/ charter |
|---|---|---|---|
| **Propose → Review → Approve → Publish** | aprovadores nomeados, approve/reject c/ comentário | Document360 / RFC | crítico |
| **RFD 6-state** | ideation→discussion→published→**committed** | Oxide | separar decidido de "funciona hoje" |
| **MADR + supersede** | proposed→accepted(imutável)→superseded | MADR 4.0 | núcleo append-only |
| **Draft/In-Review/Verified/Stale** | vence review-date → **Stale** (badge clicável) | Guru/Document360 | motor anti-decay |
| **Suggestion mode (sugestão > edição)** | contribuição = sugestão; só owner aceita | Oxide / MCP Elicitation | contribuição supervisionada (time MCP) |
| **Change request leve** | PR + merge rule | GitBook | baixo risco |
| **Bronze/Silver/Gold** | todos os checks do nível passam → badge | Soundcheck/OpsLevel | maturidade objetiva |
| **NEVER / ASK / ALWAYS** | 3 tiers de guardrail p/ agente | AGENTS.md spec | como o agente trata o charter |

### 7.3 Anti-padrões (o que faz KB virar cemitério) — com fonte

1. **Trust-erosion (morte canônica):** doc desatualiza → "uma doc velha e você para de confiar em TODAS" → todos voltam a perguntar no Slack. *"Quando verificar a wiki custa mais que perguntar a um colega, param de checar."* ([dev.to/kislay](https://dev.to/kislay/why-your-engineering-wiki-is-a-graveyard-and-how-to-fix-it-2eme)) → **mitigação: cadência de re-verificação (princípio 4).**
2. **Status decorativo sem enforcement:** Confluence "In approval" = igual a "Verified" (sem workflow/views/gate). → **status tem que mudar comportamento.**
3. **Manutenção invisível/não-recompensada:** sem owner + cadência forçada, ninguém atualiza.
4. **Doc isolada do trabalho real:** charter fora do CI/PR/portal é ignorado.
5. **Scorecard único pra tudo:** times com contexto diferente ignoram (→ governança tiered).
6. **Duplicação de fonte canônica:** Google evita ("one canonical source per topic").
7. **Editar o "accepted" em vez de supersede:** destrói histórico → acervo perde confiança.
8. **Contexto inchado p/ agente (2026):** charter longo degrada success + 20% custo (Gloaguen).
9. **Sistema ambicioso que nunca nasce → zumbi** (lição direta do SRS: começar read-only, evoluir depois).

---

## 8. Arquitetura proposta (deltas mínimos sobre o KB atual)

| # | Delta | Tipo | Reusa |
|---|---|---|---|
| D1 | `type=module-charter` (novo valor do enum) + bridge a partir de `memory/requisitos/<X>/` consolidado | dados | bridge job + RequirementsFileReader (do SRS) |
| D2 | Arestas novas: `governs-module`, `parent-charter` | grafo | `kb_edges` (só novos `edge_type`) |
| D3 | `kb_comments` → ganha `kind` + `status` (vira "sugestão") OU nova `kb_suggestions` | dados | `kb_comments` (evolução) |
| D4 | Status de aprovação do nó: `draft → in_review → ratified → outdated → superseded` | governança | `kb_nodes.status` (estender enum VARCHAR — sem ALTER doloroso) |
| D5 | Permissões: `kb.charter.approve`, `kb.charter.suggest` | Spatie | `permissions.php` |
| D6 | "Publicar mudança de núcleo" = gera PR/task no `.charter.md` | integração | git + tasks MCP |
| D7 | Tela Module Charter (Inertia, MWART) reusa a ideia do `ModuloController` do SRS | UI | padrão PT do design system |
| D8 | **Cadência de re-verificação** por charter (`verify_interval` + fila de tarefa quando vence → vira `stale`) | governança | `last_verified_at` + reverify (Guru-style) |
| D9 | **"Spectral para charters"**: linter de schema no CI (Mission/Non-Goals/owner/UX-targets) que falha o PR | CI | `stylelint-gate` + Pest GUARD (ADR 0101) |
| D10 | **RACI no frontmatter** (`owner` / `consulted` / `informed`) — quem decide, opina, é avisado | governança | frontmatter `owner` (MADR 4.0) — pro time MCP |

> **Nenhum delta quebra Tier 0.** Tudo é aditivo. O núcleo imutável e o multi-tenant continuam como estão.
>
> **Governança tiered (princípio 5):** o peso do gate é proporcional ao risco do charter — tela financeira crítica = aprovação multi-estágio; tela experimental = change-request leve. Não um baseline único.

---

## 9. Como a IA / agentes consomem (continua barato)

- `charter-fetch <page>` (MCP, Jana) já carrega ~500 tokens em vez de 30k do CLAUDE.md.
- Com Module Charter no KB: `charter-fetch <module>` carrega meta/limite/backlog/changelog consolidados — um agente sabe **até onde pode ir** num módulo antes de codar (combate goal drift, ADR 0101 §Non-Goals).
- RAG do KB (`/kb/ai/ask`) passa a responder "o que o módulo X pode fazer?" com fonte governada — **citando o charter e refletindo seu status de confiança** (se `stale`, sinaliza incerteza, igual RAG grounded 2026).

> ⚠️ **Regra dura (Gloaguen 2026):** charter **mínimo**. Charter longo/gerado por IA **reduz** o sucesso do agente e **aumenta +20% o custo**. O charter só carrega o **julgamento institucional que nenhum linter/CI captura** (Mission, Non-Goals, Anti-hooks). O que `module:grade`/Pest/stylelint já garantem determinista­mente, o charter **NÃO repete** — senão vira ruído que queima budget de contexto.

---

## 10. Faseamento sugerido (conceito → execução, NÃO agora)

| Fase | Entrega | Pré-req |
|---|---|---|
| **F0** | Este conceito vira **ADR** (proposta) + Wagner aprova o ângulo | — |
| **F1** | Page Charters existentes (já brid—) ganham status workflow + modo sugestão (D3/D4/D5) | F0 |
| **F2** | Module Charter read-only: bridge `memory/requisitos/<X>/` + tela (D1/D2/D7) — salva `RequirementsFileReader` do SRS | F1 |
| **F3** | "Publicar = PR" (D6) + Pest GUARD dos Non-Goals no CI | F2 |
| **F4** | Trilhas de onboarding por módulo + decision-tree "violei o charter?" | F3 |

> Faseamento **destrava a deprecação do SRS** com propósito: a função de valor (`RequirementsFileReader` + ideia do `ModuloController`) **renasce no KB** em vez de morrer.

---

## 11. Charter como motor de maturidade Champion + autonomia

Hoje o SCREEN-GRADE é uma **foto** (19 agentes leem o `.tsx` → nota; 0/222 em Champion). Foto constata, não faz subir. O charter governado vira o **motor**:

```
Charter (alvo)  →  checks do scorecard  →  CI gate (charter-lint + Pest GUARD Non-Goals)
                                              ↓ falha o PR fora de conformidade
                   ratchet (ADR 0236, nota só sobe) + cadência de re-verificação (Guru)
                                              ↓ impede regressão
                   tela sobe de nível e NÃO volta  →  caminho real pra Champion
```

**Como isso torna o sistema mais autônomo** (resposta ao Wagner 2026-06-01):

| Mecanismo | Autonomia |
|---|---|
| Charter = alvo legível por máquina | agente sabe o que construir e o que **não** (Non-Goals) → menos HITL |
| Scorecard + ratchet + CI gate | sistema se **auto-mede e auto-trava** (loop fechado, princípio #4) |
| Sugestão supervisionada + aprovação | loop Cowork↔Code ([ADR 0241](../../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md)) registra proposta; humano só **autoriza** |
| Module Charter (meta/limite) | agente conhece o teto do módulo antes de codar → não estende escopo sozinho |
| Charter mínimo (Gloaguen) | contexto barato/preciso → agente opera melhor sem humano |
| Charters de papel ([ADR 0242](../../decisions/0242-charters-papel-governanca-loop-cowork-code.md)) | definem **quem** (agente champion) decide/desenha/aplica; o charter de tela/módulo é **o objeto** que operam |

**Síntese:** charter de papel (*quem*) + charter de tela/módulo (*o quê*) + scorecard (*medir*) + ratchet/CI (*travar*) = evolução com intervenção humana mínima = **mais autônomo**. O charter governado é a peça que fecha o loop.

## 12. Referências

- [memory/requisitos/KB/SCHEMA-DB-V1.md](SCHEMA-DB-V1.md) — modelo de dados do KB (fundação)
- [ADR 0101 — Sistema Charter-Capterra (escopo 2 níveis × 3 eixos)](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md)
- [ADR 0149/0150 — KB Unificado como Grafo de Conhecimento](../../decisions/proposals/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)
- [ADR 0061 — Conhecimento canônico via git+MCP (zero auto-mem)](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0242 — Charters de papel (loop Cowork↔Code)](../../decisions/0242-charters-papel-governanca-loop-cowork-code.md)
- [SRS/DEPRECATION-PLAN.md](../SRS/DEPRECATION-PLAN.md) — de onde sai o `RequirementsFileReader`
- Código KB real: `Modules/KB/Entities/KbNode.php`, `Observers/KbNodeObserver.php`, `Tests/Feature/GovernanceInvariantsTest.php`

---

**Próximo passo:** Wagner valida o ângulo → vira ADR proposta → F1. (§7 benchmark mundial 2026 ✅ incorporado.)
