---
module: DocVault
alias: docvault
status: ativo
migration_target: N/A (novo)
migration_priority: alta
risk: baixo
areas: [documentacao, evidencia, requisitos, knowledge, auditoria, memoria]
last_generated: 2026-04-24
version: 0.4
---

# DocVault

Cofre de documentação viva do oimpresso.com. Ingestão de evidências (screenshots, chat logs, erros, arquivos) → classificação (manual ou IA) → virada em requisitos estruturados, rastreáveis e auditados contra a realidade do código.

## Propósito

Transformar o caos informal de conversas + erros + anotações em **documentação auditável do sistema**. Cada requisito tem evidência de origem; cada evidência pode virar story ou regra; cada regra precisa ter teste; cada tela precisa declarar as stories que atende (`// @docvault`). O sistema audita esse triângulo (fluxo/tela/teste) automaticamente (ADR 0005).

## Índice

- **[SPEC.md](SPEC.md)** — user stories + regras Gherkin (DoD rastreável)
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, modelos, serviços, fluxos
- **[RUNBOOK.md](RUNBOOK.md)** — procedimentos operacionais e troubleshooting
- **[GLOSSARY.md](GLOSSARY.md)** — vocabulário do domínio (ADR, audit score, etc.)
- **[CHANGELOG.md](CHANGELOG.md)** — evolução versão a versão (Keep a Changelog)
- **[adr/](adr/)** — 7 ADRs numerados (+ subpastas `arq/`, `tech/`, `ui/`)
- **[audits/](audits/)** — relatórios `YYYY-MM-DD.md` de `docvault:audit-module --save`
- **[diagrams/](diagrams/)** — fluxos e diagramas markdown (opcional)

## Rotas principais

Todas sob prefixo `/docs`, middleware stack admin (`web`, `authh`, `auth`, `SetSessionData`, `language`, `timezone`, `AdminSidebarMenu`).

| Rota | Método | Controller | Descrição |
|---|---|---|---|
| `/docs` | GET | `DashboardController@index` | KPIs globais + lista de módulos + cobertura |
| `/docs/ingest` | GET · POST | `IngestController` | Formulário de evidência (upload / URL / texto) |
| `/docs/inbox` | GET | `InboxController@index` | Triagem de evidências pendentes |
| `/docs/inbox/{id}/triage` | POST | `InboxController@triage` | Classificar evidência (kind, módulo, story) |
| `/docs/inbox/{id}/apply` | POST | `InboxController@apply` | Aplicar evidência (status=applied) |
| `/docs/inbox/{id}` | DELETE | `InboxController@destroy` | Remover evidência |
| `/docs/modulos/{module}` | GET | `ModuloController@show` | Viewer do módulo com tabs Overview / Spec / Arq / Changelog / ADRs / MD raw |
| `/docs/memoria` | GET | `MemoriaController@index` | Memória unificada (primer + project + Claude) |
| `/docs/memoria/file` | GET | `MemoriaController@file` | Ler arquivo específico da memória |
| `/docs/chat` | GET | `ChatController@index` | Chat assistente (offline/AI) |
| `/docs/chat/ask` | POST | `ChatController@ask` | Enviar pergunta ao assistente |
| `/docs/chat/new` | POST | `ChatController@newSession` | Nova sessão de chat |
| `/docs/install` | GET | `InstallController@index` | Instalação padrão UltimatePOS |

## Status atual (2026-04-24)

- **Fase 1 · Scaffold** ✅ — 8 controllers + 7 entities + 6 telas Inertia/React (Dashboard, Ingest, Inbox, Modulo, Memoria, Chat)
- **Fase 2 · Estrutura por módulo** ✅ — formato pasta (`README/ARCHITECTURE/SPEC/CHANGELOG/GLOSSARY/RUNBOOK/adr/`) com fallback automático pra formato plano (`{Modulo}.md`); 2 pilotos migrados (PontoWr2 + Essentials) + DocVault
- **Fase 2.5 · Auditoria e validação** ✅ — `ModuleAuditor` com 15 checks (audit score 0-100, relatórios salvos em `audits/`) + `DocValidator` com 5 checks de integridade (`docs_validation_runs`)
- **Fase 2.6 · Memória unificada** ✅ — `MemoryReader` expõe 3 fontes (primer, project, Claude) no `/docs/memoria`
- **Fase 2.7 · Chat offline** ✅ — keyword-based, modo AI stub aguardando `OPENAI_API_KEY`
- **Fase 3 · IA classificando evidências** 🔄 — `ClassifierAgent` stub pronto (ADR 0006); integração real pendente
- **Fase 4 · Graph viewer + busca semântica** ⏳ — `docs_links` tem `relation`/`weight` prontos; Scout driver `database` com fulltext MySQL (base sem Meilisearch)

## Próximos passos

Ver [ADR 0006](adr/0006-analise-de-melhorias-e-roadmap-docvault.md) pra roadmap priorizado. Destaques:

- Integração OpenAI real no `ChatAssistant::askWithAi()`
- Auto-apply de evidências triadas regravando o SPEC.md
- Frontend: tabs "Decisões"/"Auditoria" no `ModuloController::show`
