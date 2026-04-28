# AGENTS.md

Este repositório segue o padrão emergente de agentes de IA. Para instruções completas, leia `CLAUDE.md` na raiz — ele é canônico.

Resumo operacional (atualizado 2026-04-26):

- Idioma: PT-BR (cliente brasileiro)
- Stack: Laravel 13.6 + PHP 8.4 + nWidart/laravel-modules ^10 + MySQL 8 + Inertia v3 + React + Tailwind 4
- Módulo principal: `Modules/PontoWr2/` (ponto eletrônico). Outros: Copiloto, Financeiro, Cms, MemCofre, Officeimpresso.
- Conformidade: Portaria MTP 671/2021, CLT, LGPD
- Branch ativa: `main` (promoção de `6.7-bootstrap` em 2026-04-27, ver ADR 0038)
- Sistema de memória: `memory/` (`CLAUDE.md` é canônico; `memory/INDEX.md` é índice)
- Gestão de papéis das memórias: ver ADR 0027 (meta-ADR)
- **Stack-alvo de IA (VERDADE CANÔNICA):** `laravel/ai` (camada A) + `vizra/vizra-adk` (camada B) + `MemoriaContrato` com `Mem0RestDriver` default ou `MeilisearchDriver` fallback (camada C) + `laravel/boost --dev` (tooling). Declarada por Wagner em 2026-04-26 como "melhor ROI". Ver ADR 0035 (consolida 0031/0032/0033/0034).

Ao iniciar uma tarefa:
1. Leia `CLAUDE.md`
2. Leia `memory/08-handoff.md` (estado atual)
3. Leia o session log mais recente em `memory/sessions/`

Ao encerrar:
1. Atualize `memory/08-handoff.md`
2. Crie `memory/sessions/YYYY-MM-DD-session-NN.md`
3. Se decidiu algo arquitetural, crie ADR em `memory/decisions/`
