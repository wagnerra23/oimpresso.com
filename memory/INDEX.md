# Memória do Projeto — Índice Mestre

> **Propósito:** Dar continuidade ao projeto Ponto WR2 para qualquer pessoa (ou agente de IA) que venha a trabalhar nele — hoje, daqui a uma semana ou daqui a um ano.

Este diretório é o **cérebro persistente do projeto**. Sempre que uma sessão de trabalho começar, o próximo ocupante consulta aqui; sempre que terminar, ele registra aqui o que descobriu ou decidiu.

---

## Como navegar

Os documentos estão numerados para sugerir ordem de leitura em onboarding:

| # | Documento | Quando ler |
|---|---|---|
| — | [`../CLAUDE.md`](../CLAUDE.md) | **Primeiro sempre.** Primer para agentes de IA. |
| 00 | [`00-user-profile.md`](00-user-profile.md) | Saber quem é o cliente e o que ele valoriza |
| 01 | [`01-project-overview.md`](01-project-overview.md) | Entender escopo e objetivos do módulo |
| 02 | [`02-technical-stack.md`](02-technical-stack.md) | Saber quais tecnologias usar |
| 03 | [`03-architecture.md`](03-architecture.md) | Entender a arquitetura e limites |
| 04 | [`04-conventions.md`](04-conventions.md) | Escrever código no estilo do projeto |
| 05 | [`05-preferences.md`](05-preferences.md) | Respeitar preferências do cliente |
| 06 | [`06-domain-glossary.md`](06-domain-glossary.md) | Traduzir jargão trabalhista BR |
| 07 | [`07-roadmap.md`](07-roadmap.md) | Ver o que foi feito e o que vem |
| 08 | [`08-handoff.md`](08-handoff.md) | **Sempre ao retomar trabalho.** Estado atual. |
| 09 | [`09-modulos-ultimatepos.md`](09-modulos-ultimatepos.md) | Inventário de módulos UltimatePOS da instância WR2 — referências canônicas |
| — | [`CHANGELOG.md`](CHANGELOG.md) | **Eventos estruturais** em ordem cronológica (Keep a Changelog) — Added/Changed/Removed/Fixed/Decision |
| — | [`modulos/`](modulos/) | **Specs automáticas** dos 29 módulos + `RECOMENDACOES.md` + `INDEX.md` — gerado por `php artisan module:specs` |
| — | [`decisions/`](decisions/) | ADRs — decisões arquiteturais registradas |
| — | [`sessions/`](sessions/) | Session logs — histórico cronológico de mudanças |

---

## Como manter esta memória (regras de ouro)

1. **Um fato em um único lugar.** Se cria uma regra em `04-conventions.md`, não repita em outro arquivo — referencie.
2. **Datar alterações.** Cada arquivo termina com `**Última atualização:** YYYY-MM-DD`.
3. **Não sobrescrever histórico.** Session logs são append-only (como marcações de ponto). Use um novo arquivo para cada sessão.
4. **ADR para toda decisão que amarra o futuro.** Escolha de biblioteca, pattern, formato de dado, política de retenção.
5. **Handoff é vivo.** Depois de cada sessão, atualize `08-handoff.md` com pendências, bloqueios e "próximo passo".
6. **Preferências capturadas > preferências declaradas.** Se o cliente demonstra uma preferência (ex.: "só 1 item no menu"), registre em `05-preferences.md`.

---

## Convenção de nomes

- ADRs: `decisions/NNNN-kebab-slug.md` — ex.: `0007-banco-horas-ledger.md`
- Session logs: `sessions/YYYY-MM-DD-session-NN.md` — uma por dia, numeradas quando há múltiplas
- Handoff (arquivo único): `08-handoff.md` — sobrescrevível

---

## Inspirações do padrão

- **ADR format:** [Michael Nygard, "Documenting Architecture Decisions"](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions) + variação adotada pela Thoughtworks
- **AGENTS.md emergente:** contrato voluntário de projetos que querem ser compreendidos por LLMs
- **CLAUDE.md:** convenção da Anthropic para primer de agentes
- **Memory-bank** inspirado nas práticas de Cursor/Cline e no Zettelkasten aplicado a software

---

**Última atualização:** 2026-04-19 (sessão 03 — 09-modulos-ultimatepos.md criado)
