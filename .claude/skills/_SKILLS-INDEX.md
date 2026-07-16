# Skills Index — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por `scripts/governance/skills-index-generate.mjs` a partir do frontmatter de `.claude/skills/*/SKILL.md` (fonte única — US-GOV-052 P31).
> Regenerar: `node scripts/governance/skills-index-generate.mjs --write`. Convenção de tiers: ADR 0095 · recalibração 5 núcleo + auto-trigger: ADR 0225.

## Resumo
- **73** skills · Tier A **6** (5 núcleo + 1 dormente) · Tier B **57** · Tier C **10**
- Auto-trigger explícito: 9 (session_start 2 · path 5 · intent 1 · on_demand 1)
- Destacadas no bloco do CLAUDE.md: 14 (entre marcadores AUTO:SKILLS)

## Todas as skills (73)
| Skill | Tier | auto_trigger | enabled | Descrição (início) |
|---|---|---|---|---|
| ads-decision-flow | B | — | sim | Use ao trabalhar em Modules/ADS/ ou tocar fluxo de decisão automatizada (Risk → Confidence → Policy → Router → Brain … |
| ads-route | A | — | **false (dormente)** | Use ANTES de qualquer mudança custosa (chamada Brain B Sonnet/Opus, deploy prod, mudança Tier 0). Roteia decisão por … |
| alinhar-tela | B | — | sim | Use quando Wagner pedir "alinhar a tela X", "ligar a máquina da tela Y", "o que já tem pronto e o que falta na tela Z… |
| aplicar-prototipo | B | — | sim | ATIVAR quando Wagner pedir "importar o zip do protótipo", "importa esse zip/bundle/handoff", "conferir se o protocolo… |
| audit-constituicao | C | — | sim | ATIVAR quando user pedir "audit pós-constituição", "/audit-constituicao", "consolidação geral", "revisão geral desde … |
| audit-to-backlog | B | — | sim | ATIVAR quando user pedir "transformar audit em tasks", "levar audit X pro backlog", "criar tasks do audit", "/audit-t… |
| automem-pending | B | — | sim | BLOQUEADOR — quando user mencionar tópico/módulo OU Edit/Read em path com auto-mem stale pendente migração (ADR 0061)… |
| avaliar-modulo | B | — | sim | ATIVAR quando user pedir "nota do módulo X", "avaliar Modules/X", "/avaliar-modulo X", "qual a nota de Y", "module gr… |
| brief-first | B | session_start | sim | BLOQUEADOR — antes de qualquer outra tool MCP, Read, Glob, Grep ou ação no projeto Oimpresso, invoque a tool brief-fe… |
| brief-update | B | — | sim | Use SEMPRE depois de commit/merge de PR que altere capacidades, diferenciais, score Capterra, UX visível, ou gaps de … |
| charter-first | B | path | sim | BLOQUEADOR — ANTES de editar qualquer .tsx que tenha .charter.md ao lado (ex Index.tsx + Index.charter.md), chame too… |
| charter-write | B | — | sim | ATIVAR quando user pedir "criar charter da tela X", "escrever charter pra /caminho", "gerar charter de Index.tsx Y", … |
| cliente-discovery | B | — | sim | ATIVAR quando Wagner pedir /cliente-discovery, "entrevistar cliente X", "fazer discovery do cliente Y", "criar person… |
| cockpit-runbook | C | — | sim | Generates a detailed RUNBOOK.md or audits a screen against the Chat Cockpit pattern (ADR 0039) for the oimpresso ERP.… |
| commit-discipline | A | — | sim | Use ANTES de git commit ou git push em qualquer PR do oimpresso. Garante 1 PR = 1 intent, ≤300 linhas, conventional c… |
| comparar-design-prod | B | — | sim | BLOQUEADOR de eyeball — ATIVAR SEMPRE que a tarefa envolver COMPARAR design/protótipo com tela em produção ou declara… |
| comparativo-do-modulo | B | — | sim | ATIVAR quando user pedir "comparar módulo X com mercado", "auditar escopo do módulo Y", "o que falta no módulo Z vs e… |
| constituicao-ui-aware | B | path | sim | Use SEMPRE antes de Edit/Write em qualquer `resources/js/Pages/<X>/*.tsx`, `resources/js/Components/shared/**/*.tsx`,… |
| cowork-prototype-replication | B | — | sim | ATIVAR quando user pedir "fazer layout estado-da-arte", "replicar protótipo Cowork", "espelhar visual-source.html", "… |
| criar-modulo | B | — | sim | Use ao criar novo módulo Laravel modular (nWidart) no oimpresso — qualquer pasta nova em `Modules/<Nome>/`, ou pedido… |
| criar-staging | B | — | sim | ATIVAR quando user pedir "criar staging", "ambiente de homologação/homolog", "replicar produção pra teste", "subir/re… |
| curador | B | — | sim | ATIVAR quando user pedir "ingerir conhecimento", "triar D:\\Conhecimento", "organizar arquivos do computador", "ler t… |
| design-deep-analysis | B | — | sim | ATIVAR quando Wagner pedir /design-deep <persona-slug>, "analisar visualmente tela X pra persona Y", "design profundo… |
| design-memoria-reprocess | B | — | sim | ATIVAR quando (a) o Claude Design enviar handoff com bloco `## new_design_memories`; (b) um doc de design for criado/… |
| encerrar-sessao | B | — | sim | BLOQUEADOR — ATIVAR SEMPRE que user disser "encerrar sessão", "fim de sessão", "vamos parar", "continua depois", "sal… |
| feedback-capture | B | — | sim | ATIVAR quando Wagner colar feedback de cliente real OU disser "Daniela reclamou X", "Larissa pediu Y", "Kamila falou … |
| feedback-dashboard | B | — | sim | ATIVAR quando Wagner pedir "/feedback-dashboard", "mostra feedback", "como está o feedback", "que feedback tem aberto… |
| governance-pr-summary | B | — | sim | Use ANTES de `gh pr create` em qualquer branch que toque Modules/<X>/. Lê módulos afetados via `git diff --name-only … |
| hostinger-dns-autonomy | A | — | sim | BLOQUEADOR Tier A — ATIVAR antes de pedir Wagner pra criar/editar DNS record, qualquer ação Hostinger painel/UI, OU s… |
| incident-done-checklist | A | — | sim | BLOQUEADOR — ATIVAR antes de declarar "incident fechado" / "está pronto" / "feature funcionando" / encerrar sessão de… |
| inertia-defer-default | B | — | sim | Use SEMPRE antes de Edit em qualquer Controller que chama `Inertia::render(...)` no oimpresso (qualquer `Modules/<X>/… |
| jana-arch | B | — | sim | Use ao trabalhar em Modules/Jana/ ou ao tocar memória/IA do projeto. Carrega arquitetura canônica do Copiloto (ADRs 0… |
| jana-brief-concierge | B | — | sim | ATIVAR quando user (Wagner) colar/citar um JSON com chaves `version`, `business_id`, `sources` (vendas/inadimplencia/… |
| jana-recall-flow | B | — | sim | Use ao tocar Modules/Jana/Services/Memoria/, ContextSnapshotService, recall hybrid (Meilisearch + HyDE + reranker), M… |
| mcp-first | B | intent | sim | ATIVAR antes de Read/Glob/Grep em memory/, ler ADR/session/spec do projeto, buscar conhecimento canônico do oimpresso… |
| memory-first-secret-search | A | — | sim | BLOQUEADOR Tier A — ATIVAR ANTES de qualquer busca por token / API key / password / SSH key / credential / secret. Sk… |
| memory-schema-preflight | B | — | sim | ATIVAR ANTES de Write/Edit em `memory/requisitos/**/SPEC.md`, `memory/requisitos/**/RUNBOOK*.md`, `memory/requisitos/… |
| memory-sync | B | — | sim | ATIVAR após criar/editar arquivo em memory/, atualizar SPEC.md/TEAM.md, salvar ADR/session log, ou usar trigger "salv… |
| meta-skill-roi-erp-autonomo | C | — | sim | ATIVAR ao criar skill nova, usar `skill:scaffold`, discutir se uma ideia merece virar skill, ou perguntar "isso vira … |
| migracao-blade-react | B | — | sim | ATIVAR quando user pedir "migrar tela X", "migrar Blade pra React", "migração massiva", "/migracao-blade-react <modul… |
| migrar-modulo | B | — | sim | Use ao mover, renomear, ou extrair controller/módulo Laravel modular existente em `Modules/<X>/` — qualquer `git mv M… |
| migration-status | B | — | sim | ATIVAR quando user pedir "status migração", "% migrado {módulo}", "tabelas Firebird", "status da migração por tabelas… |
| module-completeness-audit | B | — | sim | ATIVAR antes de marcar US como `done` (`tasks-update task_id:US-XXX-NNN status:done` ou `tasks-update from:review to:… |
| module-grades-gate | C | — | sim | ATIVAR quando user pedir "checar grades antes de PR", "rodar gate de notas local", "atualizar baseline module-grade",… |
| multi-tenant-patterns | A | — | sim | Use ao criar ou alterar Eloquent Model, Controller, Service, Job, Command ou Migration que toca dados de negócio (qua… |
| mwart-comparative | B | path | sim | Use SEMPRE antes de codar Page Inertia em migração MWART (Blade→React) no oimpresso. Skill Tier B auto-trigger V4 que… |
| mwart-process | B | path | sim | Use SEMPRE que o trabalho envolva migrar tela Blade legacy → Inertia/React no oimpresso (MWART). Carrega o processo c… |
| mwart-quality | B | — | sim | Use ANTES de criar/editar tela MWART (Module Web App React Transition Blade→Inertia/React) no oimpresso. Ativa quando… |
| officeimpresso-financial-snapshot | B | — | sim | ATIVAR quando user pedir "analisar receita do cliente X", "snapshot financeiro de {cliente OfficeImpresso}", "compara… |
| officeimpresso-source-analysis | B | — | sim | ATIVAR quando precisar entender comportamento real de uma tela/feature do OfficeImpresso legacy (Delphi WR Comercial)… |
| oimpresso-cc-watcher-setup | C | — | sim | Configura o watcher local do Claude Code que sincroniza ~/.claude/projects/*.jsonl com o MCP server do oimpresso (cc-… |
| oimpresso-stack | C | — | sim | Use ao iniciar trabalho no oimpresso ou ao entrar num módulo novo. Carrega o primer da stack canônica (Laravel 13.6, … |
| oimpresso-team-onboarding | C | — | sim | Configura ou valida acesso ao MCP server da empresa oimpresso (Wagner/Felipe/Maiara/Luiz/Eliana). Ativa quando dev no… |
| pageheader-canon | B | — | sim | ATIVAR quando agente vai aplicar o PageHeader canon (ADR 0180/0182/0189/0190) em módulo novo — user pede "aplicar pag… |
| personas-resolve | B | — | sim | BLOQUEADOR Tier A — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em arquivos de `resources/js/Pages/**/*.tsx` ou cri… |
| pr-ui-judge-manual | C | — | sim | Use quando Wagner pedir "avaliar PR <número> contra Constituição UI v2", "rodar judge no PR X", "review semântico do … |
| pre-adr-introspect | B | — | sim | ATIVAR ANTES de qualquer Write em `memory/decisions/NNNN-*.md` (ADR nova) OU antes de propor schema novo (`database/m… |
| precisao-literal | B | — | sim | ATIVAR quando user pedir "compare com o protótipo", "avalie precisão", "que % literal", "ficou idêntico?", "compare l… |
| preflight-modulo | B | path | sim | BLOQUEADOR — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em Modules/<X>/. PRÉ-FLIGHT obrigatório: ler memory/requis… |
| proxmox-docker-host | C | — | sim | Use ao mexer com infra Proxmox/CT 100/containers Docker do oimpresso. Carrega receitas: subir novo subdomínio Traefik… |
| publication-policy | B | — | sim | Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção, mudança em .env de produção, ou postagem ex… |
| reguas-do-sistema | B | — | sim | ATIVAR quando Wagner pedir "grade de réguas", "onde sou fraco vs mercado", "quais ideias estão acima do mercado", "re… |
| runtime-rules-hostinger-ct100 | B | — | sim | Use ANTES de SSH no Hostinger, composer install/update em servidor, criar git worktree em servidor, ou qualquer coman… |
| screen-grade | B | — | sim | ATIVAR quando user pedir "nota da tela X", "gradear tela Y", "/screen-grade Sells/Create", "qual a maturidade da tela… |
| sdd-avaliar | C | — | sim | Use ANTES de promover qualquer gate SDD a required (calendário ADR 0275), AO FECHAR cada onda do programa SDD (Semana… |
| session-start-check | B | session_start | sim | ATIVAR depois do brief-first em toda sessão. Chama tool MCP whats-active pra detectar se outra sessão Claude do time … |
| sidebar-menu-arch | B | — | sim | Reconhecer, auditar e modificar a arquitetura do sidebar do AppShellV2 — DataController por módulo + agrupamento visu… |
| smoke-prod-evidence | B | — | sim | ATIVAR antes de declarar "funcionando", "smoke OK", "deploy ok", "está rodando" no oimpresso. Trigger por (a) user pe… |
| tela-smoke-pos-merge | B | — | sim | ATIVAR após PR mergeado que toca resources/js/Pages/**/*.tsx OU quando Wagner pedir "smoke a tela X", "validar tela X… |
| ticket-triage | B | — | sim | ATIVAR quando user pedir "analise esse ticket", "triage", "vale a pena atender X?", "qual a prioridade", "esse client… |
| ui-component-creator | B | — | sim | Use ao criar/modificar componentes React (Pages Inertia, sub-componentes em _components/, ou shareds em Components/sh… |
| wagner-protocol-enforce | B | on_demand | sim | BLOQUEADOR Tier A always-on — carrega memory/reference/PROTOCOLO-WAGNER-SEMPRE.md no SessionStart de TODA sessão Clau… |
| wagner-request-refiner | B | — | sim | ATIVAR quando Wagner manda múltiplos pedidos curtos não-estruturados num mesmo turno (ex: lista com 3+ items, "todo: … |
