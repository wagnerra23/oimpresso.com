---
name: Módulo Copiloto (novo)
description: Módulo novo — chat IA conversacional que sugere e monitora metas de negócio; tenancy híbrida; spec-ready, sem código ainda
type: project
originSessionId: 3ea423cc-141d-477e-b072-2e0171a6fdd7
---
**Módulo Copiloto** — criado em spec-ready em 2026-04-24. Marca comercial e técnica iguais (pasta `Modules/Copiloto/`, URL `/copiloto`, namespace `Modules\Copiloto`).

**Posicionamento:**
- Copiloto de IA do negócio — conversa, sugere metas em cenários (fácil/realista/ambicioso), usuário escolhe, sistema monitora com apuração + alertas.
- Pitch: *"Você não precisa ser analista de dados. Seu Copiloto entende os números, conversa com você e te avisa quando algo sai da rota."*
- Nome escolhido sobre `BI`, `MetasNegocio`, `Farol`, `Compass`: alinhado com conceito IA-first + surfa trend Copilot (GitHub/MS) + escalável como guarda-chuva (v1 metas → v2 comercial → v3+ operacional/financeiro).

**Arquitetura-chave:**
- **Tenancy híbrida:** `business_id` nullable em `copiloto_metas` / `copiloto_conversas` — `null` = meta da plataforma oimpresso (superadmin-only); `not null` = meta do business.
- **Chat é entry-point**, não dashboard. `/copiloto` abre chat. Dashboard em `/copiloto/dashboard`.
- **Dependência IA soft:** interface `AiAdapter` com 2 drivers — `LaravelAiDriver` (quando módulo LaravelAI existir) e `OpenAiDirectDriver` (fallback via `openai-php/laravel`).
- **Drivers de apuração plugáveis:** `sql` / `php` / `http`. SQL valida read-only + binds obrigatórios `:business_id`/`:data_ini`/`:data_fim`.
- **Stack:** Inertia + React + shadcn. Nasce moderno, sem Blade/AdminLTE.

**Áreas funcionais (7):** Chat, Metas, Períodos, Apuração, Fontes, Dashboard, Alertas.

**Entidades (7):** `copiloto_metas`, `copiloto_meta_periodos`, `copiloto_meta_apuracoes`, `copiloto_meta_fontes`, `copiloto_conversas`, `copiloto_mensagens`, `copiloto_sugestoes`.

**Artefatos no cofre** (branch alvo `6.7-bootstrap`, atualmente worktree `lucid-dirac-00f1c6` aguardando commit/PR):
- `memory/requisitos/Copiloto/README.md` — pitch + frontmatter
- `memory/requisitos/Copiloto/ARCHITECTURE.md` — 7 áreas, 7 entidades, 4 camadas
- `memory/requisitos/Copiloto/SPEC.md` — 4 personas, US-COPI-001 a US-COPI-061, Gherkin
- `memory/requisitos/Copiloto/GLOSSARY.md` — vocabulário canônico
- `memory/requisitos/Copiloto/RUNBOOK.md` — seed, job, debug, problemas comuns
- `memory/requisitos/Copiloto/CHANGELOG.md`
- `memory/requisitos/Copiloto/adr/arq/0001-tenancy-hibrida.md`
- `memory/requisitos/Copiloto/adr/arq/0002-conversa-como-entry-point.md`
- `memory/requisitos/Copiloto/adr/tech/0001-drivers-apuracao-plugaveis.md`
- `memory/requisitos/Copiloto/adr/tech/0002-adapter-ia-laravelai-ou-openai.md`
- `memory/requisitos/Copiloto/adr/ui/0001-chat-inline-no-dashboard.md`

**Status:** `spec-ready` — documentação completa, sem código. Próximo passo aguardado pelo Wagner: scaffold `Modules/Copiloto/` (padrão PontoWr2).

**Relações:**
- Materializa `ADR 0022 — Meta R$ 5mi/ano`.
- Usa `memory/11-metas-negocio.md` como seed de metas.
- Primeira materialização do conceito `ideia_chat_ia_contextual.md` (auto-memória).
- Entra na tese de revenue dos 4 módulos promovidos (`reference_revenue_thesis_modulos.md`) — pricing a definir.
