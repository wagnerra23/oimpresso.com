# Proposta — UI `/ads/admin/skills` e `/ads/admin/policies`

> **Status:** rascunho · pendente aceite Wagner
> **Cycle alvo:** CYCLE-02 Sprint B (3 dias úteis, 20–22/05/2026)
> **Padrão imitado:** [`Modules/ADS/Http/Controllers/Admin/DecisoesController`](../../../Modules/ADS/Http/Controllers/Admin/DecisoesController.php) + [`resources/js/Pages/ads/Admin/Decisoes.tsx`](../../../resources/js/Pages/ads/Admin/Decisoes.tsx)
> **Backend:** [ADR 0073](../../decisions/0073-team-mcp-skills-policies-entidades-governadas.md) entrega `mcp_skills` + `mcp_policies` + 4 tools MCP

## O que a UI faz (V1 — leitura)

| Tela | Rota | O que faz |
|---|---|---|
| **Lista skills** | `GET /ads/admin/skills` | Tabela com 16+ skills sincronizadas: nome, source (claude-code/plugin/custom), módulo, status (✅ synced / ⚠️ drift filesystem ≠ DB), última atualização, autor último commit, hits MCP (uso via `skills-search` em 7 dias) |
| **Detalhe skill** | `GET /ads/admin/skills/{slug}` | Frontmatter parseado + body markdown renderizado + history (versões anteriores via `mcp_skills_history`) + link `git_path` no GitHub |
| **Lista policies** | `GET /ads/admin/policies` | Tabela com policies por categoria (`block_always` / `require_brain_b` / `require_human_review` / `allow_brain_a`), pattern, ativa/inativa, source, decided_by |
| **Detalhe policy** | `GET /ads/admin/policies/{slug}` | Categoria, pattern, rationale (markdown), `related_adr` linkado, history |

**V1 = read-only.** Edição continua sendo via PR git — fonte canônica é o filesystem.

## Capacidades V2+ (fora deste cycle)

| Capacidade | Quando entra |
|---|---|
| Toggle `active` em policy | Quando aparecer demanda de "desabilitar regra sem deploy" — ADR superseder de ARQ-0006 obrigatória |
| Editar skill via UI | Quando time pedir; hoje é PR git |
| Criar skill via UI | Mesmo critério |
| Soft-delete LGPD via UI | Quando primeiro caso real aparecer |

## Layout — lista skills (mockup textual)

```
┌──────────────────────────────────────────────────────────────────┐
│  Skills do projeto                              [⚡ /sync-skills]│
├──────────────────────────────────────────────────────────────────┤
│  Filtros: [todos|claude-code|plugin] [módulo▾] [busca: ___]      │
├──────────────────────────────────────────────────────────────────┤
│  ✅ ads-decision-flow         claude-code  ads      atualiz. 2d  │
│     Use ao trabalhar em Modules/ADS/...           hits 7d: 12    │
│                                                                   │
│  ✅ memoria-recall-flow       claude-code  copi     atualiz. 2d  │
│     Use ao tocar Modules/Jana/Services/...    hits 7d: 8     │
│                                                                   │
│  ⚠️ copiloto-arch              claude-code  copi     drift 1d    │
│     Use ao trabalhar em Modules/Jana/...      hits 7d: 23    │
│     ↳ filesystem tem versão ≠ DB (rodar mcp:sync-skills)         │
│                                                                   │
│  ✅ criar-modulo               claude-code  infra    atualiz. 5d │
│     ...                                                           │
└──────────────────────────────────────────────────────────────────┘
```

## Layout — detalhe skill

```
┌──────────────────────────────────────────────────────────────────┐
│  ads-decision-flow                              [📂 abrir no GH] │
│  source: claude-code  ·  módulo: ads  ·  hits 7d: 12             │
├──────────────────────────────────────────────────────────────────┤
│  Description (frontmatter)                                       │
│  ────────────────────────                                        │
│  Use ao trabalhar em Modules/ADS/ ou tocar fluxo de decisão...   │
│                                                                   │
│  Body (markdown render)                                          │
│  ──────────                                                      │
│  # Adaptive Decision System — fluxo canônico                     │
│  ## Quando ativa                                                 │
│  ...                                                              │
│                                                                   │
│  History                                                          │
│  ───────                                                         │
│  ✓ 70ee8dde · feat(skills): /sync-skills... · 2026-05-05         │
│  ✓ 5ebd107e · feat(skills): ads-decision-flow... · 2026-05-05    │
└──────────────────────────────────────────────────────────────────┘
```

## Permissions (Spatie)

| Permissão | V1 quem ganha | O que libera |
|---|---|---|
| `ads.admin.skills.read` | superadmin + time dev (W/F/M/L/E) | `/ads/admin/skills*` (todas leituras) |
| `ads.admin.skills.manage` | só superadmin Wagner | (V2) toggle/edit |
| `ads.admin.policies.read` | superadmin + time dev | `/ads/admin/policies*` |
| `ads.admin.policies.manage` | só superadmin Wagner | (V2) toggle active |

Seeder `AdsAdminSkillsPermissionsSeeder` cria permissões + atribui ao role `superadmin`.

## Detecção de drift — sinal visual

Skill no filesystem (worktree) mas DB não tem (ou vice-versa) = ⚠️ amarelo. Causa mais comum: dev fez merge que tocou `.claude/skills/` e webhook ainda não rodou, OU rodou sem sucesso. Ação mostrada: **botão "rodar `mcp:sync-skills` agora"** (POST `/ads/admin/skills/sync` → dispara command via `Artisan::call`).

## Multi-tenant

Skills/policies são **cross-tenant** (não tem `business_id`). UI mostra mesma lista pra qualquer `session('user.business_id')`. Se Wagner depois quiser skills por-tenant (ex.: Larissa biz=4 vê skill custom só dela), vira ADR superseder de 0073 + coluna `business_id NULL = global` em `mcp_skills`.

## Sidebar AppShellV2

Dentro do grupo **CONHECIMENTO** (já existe — onde mora `/copiloto/admin/memoria`), adicionar:
- Skills → `/ads/admin/skills`
- Policies → `/ads/admin/policies`

Visível só com permission `ads.admin.skills.read` ou `ads.admin.policies.read`.

## Não-decisões deliberadas (V1)

- Filtro avançado por tags do frontmatter — V2.
- Diff visual entre versões (history) — V2.
- Edição inline — V2 com ADR superseder.
- Webhook visualizado em tempo real (badge "sync em andamento") — V2.
- Bulk action (rodar sync de várias) — V2.

## Pré-requisitos pra começar Sprint B (UI)

- [ ] Sprint A entregue: `mcp_skills` + `mcp_policies` populadas
- [ ] 4 tools MCP (`skills-search/fetch`, `policies-active/fetch`) testadas
- [ ] Permission seeder de Sprint A criou as permissões `copiloto.mcp.skills.read` etc — Sprint B ESPELHA pro namespace `ads.admin.*`

## Como gerir skills hoje (até CYCLE-02 entregar UI)

1. **Filesystem direto:** `code .claude/skills/<nome>/SKILL.md`, `git commit`, `git push origin main` → webhook + harness recarrega no próximo restart.
2. **Slash command `/sync-skills`** (já em main, commit `70ee8dde`): mostra diff sem precisar abrir cada arquivo.
3. **Hook `SessionStart`**: avisa automaticamente se houver skills tocadas desde último start.
4. **Tool MCP `decisions-search query:"<termo>"`**: busca por palavras nas ADRs/skills/specs sincronizados em `mcp_memory_documents` (mas skills ainda não estão lá — entram só após Sprint A do CYCLE-02).

## Riscos

| Risco | Probabilidade | Mitigação |
|---|---|---|
| `markdown-it` ou lib de render no React Pages quebra com YAML frontmatter inline | baixa | strippa `---...---` antes de renderizar (mesma lógica do gotcha #8 de `RETRIEVAL-GOTCHAS.md`) |
| `git_path` link pra GitHub quebra em fork | média | template literal: `https://github.com/wagnerra23/oimpresso.com/blob/main/{git_path}` configurável via `.env` |
| Drift detection gera false-positive em CI/staging | baixa | comparar `git_sha` da skill vs último sync de `mcp_skills.git_sha`; se igual = sem drift |

## Próxima decisão Wagner

3 caminhos:

1. **Aceitar proposta + pode iniciar Sprint A do CYCLE-02** assim que CYCLE-01 fechar (12/05).
2. **Ajustar escopo** (incluir/excluir capacidades, mudar layout, mudar prioridade).
3. **Pausar pra discutir UI design** com Maiara/Felipe antes de Sprint B.

Recomendado: **(1)** — proposta enxuta, V1 leitura, sem promessa de capacidades V2 que talvez não sejam necessárias.
