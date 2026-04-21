# AGENTS.md

Este repositório segue o padrão emergente de agentes de IA. Para instruções completas, leia `CLAUDE.md` na raiz — ele é canônico.

Resumo operacional:

- Idioma: PT-BR (cliente brasileiro)
- Stack: Laravel 10 + nWidart/laravel-modules + MySQL 8
- Módulo: `Modules/PontoWr2/` (ponto eletrônico)
- Conformidade: Portaria MTP 671/2021, CLT, LGPD
- Sistema de memória: `memory/` (leia `memory/INDEX.md` primeiro)

Ao iniciar uma tarefa:
1. Leia `CLAUDE.md`
2. Leia `memory/08-handoff.md` (estado atual)
3. Leia o session log mais recente em `memory/sessions/`

Ao encerrar:
1. Atualize `memory/08-handoff.md`
2. Crie `memory/sessions/YYYY-MM-DD-session-NN.md`
3. Se decidiu algo arquitetural, crie ADR em `memory/decisions/`
