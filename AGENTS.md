# AGENTS.md

Este repositório segue o padrão emergente de agentes de IA ([agents.md](https://agents.md/) spec). Para instruções completas, leia `CLAUDE.md` na raiz — ele é canônico.

@CLAUDE.md

> **G7 (2026-05-15):** import `@CLAUDE.md` adicionado pra agents compatíveis com Anthropic memory spec (Claude Code, Cursor agents.md, Codex). Compat retroativa preservada — agentes que só leem markdown puro continuam vendo o resumo abaixo.

> ⚠️ **O resumo abaixo é HISTÓRICO (2026-04) e continha stack REJEITADA declarada como "verdade canônica"** (Vizra ADK — rejeitada pela [ADR 0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md); Mem0Rest nunca virou default). Corrigido na auditoria 2026-07-09. **A verdade viva está no `CLAUDE.md` + `memory/what-oimpresso.md`** — agente que só lê markdown puro: confie no bloco abaixo JÁ CORRIGIDO, mas prefira o CLAUDE.md.

Resumo operacional (corrigido 2026-07-09):

- Idioma: PT-BR (cliente brasileiro)
- Stack: Laravel 13.6 + PHP 8.4 + nWidart/laravel-modules ^10 + MySQL 8 + Inertia v3 + React 19 + Tailwind 4
- Módulos: núcleo comum + `Modules/<Vertical>` (Vestuario em prod, ComunicacaoVisual em construção) + Jana/Financeiro/NfeBrasil/RecurringBilling/Repair — ver `memory/what-oimpresso.md`
- Conformidade: Portaria MTP 671/2021, CLT, LGPD
- Sistema de memória: `memory/` (`CLAUDE.md` é canônico)
- **Stack de IA (ADR 0035 + 0048):** `laravel/ai` (camada A) + **agents próprios** em `Modules/Jana/Ai/Agents/` via `LaravelAiSdkDriver` (camada B — **Vizra ADK REJEITADA**, ADR 0048) + `MemoriaContrato` com **`MeilisearchDriver` default** e `NullDriver` dev (camada C)

Ao iniciar uma tarefa:
1. Leia `CLAUDE.md`
2. Leia `memory/08-handoff.md` (estado atual)
3. Leia o session log mais recente em `memory/sessions/`

Ao encerrar:
1. Atualize `memory/08-handoff.md`
2. Crie `memory/sessions/YYYY-MM-DD-session-NN.md`
3. Se decidiu algo arquitetural, crie ADR em `memory/decisions/`
