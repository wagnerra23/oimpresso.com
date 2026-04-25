# Changelog — Copiloto

Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

## [Unreleased] — `spec-ready`

### Decision — 2026-04-24
- **Nome comercial + técnico = "Copiloto"** (escolhido em conversa Wagner ↔ Claude, 2026-04-24).
  - Alternativas consideradas e rejeitadas: `MetasNegocio` (descritivo, sem punch), `BI` (seguro mas genérico, compete com gigantes), `Farol` (forte PT-BR, 2ª opção), `Compass`/`Pulse`/`Norte` (nomes-marca abstratos).
  - Justificativa: conceito literal = copiloto (conversa, sugere, monitora junto); surfa trend de mercado (GitHub/MS Copilot já educaram percepção); escalável como guarda-chuva (v1 metas → v2 comercial → v3 operacional → v4 financeiro).

### Decision — 2026-04-24
- **Tenancy híbrida** — `business_id` nullable; `null` = meta da plataforma oimpresso (superadmin-only). ADR [`arq/0001`](adr/arq/0001-tenancy-hibrida.md).

### Decision — 2026-04-24
- **Chat conversacional é o entry-point principal**, não dashboard. Dashboard é consequência. ADR [`ui/0001`](adr/ui/0001-chat-inline-no-dashboard.md).

### Decision — 2026-04-24
- **Dependência IA é soft** via adapter — LaravelAI preferido, fallback openai-php direto. ADR [`tech/0002`](adr/tech/0002-adapter-ia-laravelai-ou-openai.md).

### Added — 2026-04-24 (documentação, sem código)
- `README.md` — frontmatter + pitch comercial + índice.
- `ARCHITECTURE.md` — 7 áreas funcionais, 7 entidades, 4 camadas, integrações.
- `SPEC.md` — 4 personas, 18+ user stories (US-COPI-NNN), regras Gherkin.
- `GLOSSARY.md` — vocabulário canônico + termos a evitar.
- `RUNBOOK.md` — operação, seed, debug, problemas comuns.
- 5 ADRs: `arq/{0001,0002}`, `tech/{0001,0002}`, `ui/0001`.

### Related
- Nasce a partir do ADR [`decisions/0022-meta-5mi-ano-financeira.md`](../../decisions/0022-meta-5mi-ano-financeira.md).
- Seed inicial virá de [`memory/11-metas-negocio.md`](../../11-metas-negocio.md).

---

**Última atualização:** 2026-04-24
