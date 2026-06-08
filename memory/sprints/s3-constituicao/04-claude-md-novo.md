---
name: CLAUDE.md novo — proposta v3 (≤100 linhas + 5 imports)
description: Reescrita completa do CLAUDE.md de 390 → 95 linhas seguindo Anthropic 2026 best-practice (WHY/WHAT/HOW + progressive disclosure via @imports recursivos).
type: proposal
related_adr: NEXT-constituicao-v2
created: 2026-05-06
authors: [sonnet]
status: pending_wagner_approval
---

# CLAUDE.md novo — proposta v3

> **Status:** 📝 PROPOSTO — Wagner revisa e aprova antes de qualquer mudança no `CLAUDE.md` real.
> Atual CLAUDE.md tem 390 linhas. Esta proposta: **95 linhas** + 5 arquivos importados via `@`.
> Best-practice 2026 ([alexop.dev](https://alexop.dev/posts/stop-bloating-your-claude-md-progressive-disclosure-ai-coding-tools/), [HumanLayer](https://www.humanlayer.dev/blog/writing-a-good-claude-md)).

---

## Estrutura proposta — pattern WHY/WHAT/HOW + Progressive Disclosure

### Conteúdo proposto pra `CLAUDE.md` (~95 linhas)

```markdown
# CLAUDE.md — primer pra Claude Code @ oimpresso

> **Sempre comece com `mcp__oimpresso__brief-fetch` (skill `brief-first` Tier A).**
> Documento canônico — mudanças via ADR (ver "Como propor mudança" abaixo).

## Por que existe
@memory/why-oimpresso.md

## Stack e estrutura
@memory/what-oimpresso.md

## Como trabalhar (protocolo de sessão)

1. `brief-fetch` → estado consolidado (~3k tokens) — Tier A always-on
2. `my-work` → minhas tasks ativas
3. (S4+) `charter-fetch <page-id>` antes de editar `.tsx` que tenha `.charter.md` ao lado
4. Trabalhar (ler código, edit, test)
5. (S5+) `decide(domain, intent, payload)` se mudança custosa
6. Commit conventional + `Refs: SPRINT-N PASSO M`

@memory/how-trabalhar.md  # detalhes operacionais

## Skills Tier A (always-on — controlado por hook SessionStart)

- **brief-first** — força brief-fetch primeiro
- **mcp-first** — usar tools MCP antes de Read/Glob/Grep filesystem
- **multi-tenant-patterns** — Tier 0 isolation (`business_id` global scope) — ADR 0093
- **commit-discipline** — 1 PR = 1 intent, ≤300 linhas, conventional commits
- (dormente — S4) **charter-first**
- (dormente — S5) **ads-route**

Ver `memory/sprints/s3-constituicao/03-skills-audit.md` pra tier de cada skill.

## Constituição v2 — leia em ordem

- L7 Brief: ADR 0091
- L1 MCP CORE: ADR 0053
- Mãe: ADR `NEXT-constituicao-v2` (criada pelo S3)
- Princípio Tier 0 multi-tenant: ADR 0093
- Lista completa: tools MCP `decisions-search` (filtra por status lifecycle ativo)

## Proibições (Tier 0 — sem ADR mãe nova é proibido)

@memory/proibicoes.md

## Time e responsabilidades

@memory/regras-time.md

## Como propor mudança

| Tipo | Caminho |
|---|---|
| ADR canon | PR + ADR Nygard + aprovação Wagner |
| ADR HISTORICAL | PR opcional, status `historical` |
| Skill Tier A | PR + ADR específica + Wagner aprova |
| Skill Tier B/C | PR + SKILL.md description "Use ao/quando..." |
| Charter (S4+) | PR + `*.charter.md` ao lado do `.tsx` |
| Mudança ADR canon existente | ❌ NÃO. Append-only. Criar nova com `supersedes: [N]` |

## Onde NÃO inventar

Tier 0 — sem ADR mãe nova é proibido (ver `memory/proibicoes.md`):
- Tokens MCP, schema `mcp_audit_log`, ADRs CANON, `business_id` global scope
- Centrifugo + FrankenPHP runtime CT 100 (ADR 0058)
- Hostinger ≠ CT 100 separação (ADR 0062)
- ZERO auto-mem privada (ADR 0061)
- `laravel/octane` no Hostinger (CLAUDE.md §4)

## Métricas de saúde (rodar `php artisan jana:health-check`)

5 checks SQL diários: multi_tenant_isolation, brief_uptime_24h, custo_brain_b_24h,
pii_leak_in_assistant_responses, profile_distiller_drift.

Se algum falhar → investigar `storage/logs/laravel.log`.

---
**Última atualização:** 2026-MM-DD — pós Sprint 3 (Constituição v2)
**Linhas:** ~95 (alvo Anthropic 2026: <100 com `@imports` recursivos)
```

**Total estimado:** ~85-95 linhas (vs 390 atuais).

---

## Arquivos novos a criar (movendo conteúdo do CLAUDE.md atual)

### `memory/why-oimpresso.md` (~30 linhas)

Conteúdo: §1 atual ("O que é este projeto em 30 segundos") + visão de produto + posicionamento.

```markdown
# Por que o oimpresso existe

ERP gráfico brasileiro pra setor de **comunicação visual** (gráficas rápidas, plotters,
fachadas, brindes). Construído sobre UltimatePOS v6 com módulos próprios em
`Modules/` (Jana IA, Financeiro, MemCofre, NfeBrasil, RecurringBilling, etc).

## Cliente piloto
**ROTA LIVRE** (`business_id=4`, Larissa) — 99% do volume.

## Posicionamento
Capterra-inventoried em todos módulos críticos. Diferencial vs concorrentes
(Iugu/Asaas/Vindi): **NFe automática a partir de boleto pago** (US-RB-044 entregue),
copiloto IA com memória persistente Jana, governança formal (Constituição v2).

## Meta
R$ [redacted Tier 0] milhões/ano (ADR 0022). Usa stack canônica IA econômica
(gpt-4o-mini Brain A) pra controlar CAC.

## Cliente externo: ROTA LIVRE
Larissa dona/operadora. Histórico de quirks documentado em auto-memória
do agente. Monitor 1280px. Customizações ativas: format_date shift +3h
(ADR 0066 — preservado intencionalmente).
```

### `memory/what-oimpresso.md` (~50 linhas)

Conteúdo: §1 + §3 atuais (stack real, módulos canônicos, governança ADR 0059,
links pra ADRs centrais).

```markdown
# Stack e estrutura

## Stack (canônica)
- Laravel 13.6 + PHP 8.4 (Herd local; Hostinger prod)
- Inertia v3 + React 19 + Tailwind 4
- Pest v4 + spatie/laravel-html ^3.13 (shim `App\View\Helpers\Form`)
- nWidart/laravel-modules ^10
- MySQL Laragon dev / Hostinger prod (DB `oimpresso`)
- CT 100 Proxmox: FrankenPHP + Centrifugo + Meilisearch + MCP server

## IA (ADR 0035 canônica)
- **Camada A** (LLM wrapper): laravel/ai ^0.6.3 oficial
- **Camada B** (agents): LaravelAiSdkDriver + 4 Agents próprios em
  `Modules/Jana/Ai/Agents/` — **Vizra ADK REJEITADA** (ADR 0048, 0032)
- **Camada C** (memória): MemoriaContrato + MeilisearchDriver default + NullDriver dev

## MCP server canônico
`mcp.oimpresso.com` (CT 100/FrankenPHP) — 352 docs sincronizados de `memory/*`
(ADR 0053). Tabela `mcp_memory_documents` com índice FULLTEXT + Meilisearch hybrid.

## Padrão arquitetural
Modular monolith, DDD leve, append-only (Lei + Constituição), `business_id` global
scope obrigatório (Tier 0 ADR 0093).

## Módulos referência canônica
`Modules/Jana/`, `Modules/Repair/`, `Modules/Project/` — antes de criar/ajustar
qualquer arquivo, abrir o equivalente e imitar (ADR 0011).

## Criar módulo novo
Ler `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` — 8 peças obrigatórias.

## ADRs centrais
- 0035 Stack-alvo IA · 0040 Policy publicação · 0048 Vizra rejeitada
- 0053 MCP server governança · 0058 Centrifugo (Reverb abandonado)
- 0062 Hostinger ≠ CT 100 · 0070 Jira-style tasks · 0079 Constituição v1
- 0091 Daily Brief · 0093 Multi-tenant Tier 0
```

### `memory/how-trabalhar.md` (~80 linhas)

Conteúdo: §2 atual (caminho preferido tools MCP + tabela de perguntas → tool),
fluxo de session start, disciplina de contexto (`/compact`, `/clear`),
skills auto-ativáveis.

(Será extraído do atual CLAUDE.md §2 — substancial, ~80 linhas inalteradas.)

### `memory/proibicoes.md` (~40 linhas)

Conteúdo: §4 + §5 atuais (não fazer, sempre fazer) — lista de Tier 0 irrevogáveis.

```markdown
# Proibições (Tier 0 — sem ADR mãe nova é proibido)

## Ambiente
- ⛔ **Nunca instalar `laravel/mcp` ou `laravel/octane` no Hostinger**
- ⛔ **Nunca rodar Pest da suite Jana/MCP no Hostinger** (usar CT 100 ou local)
- ⛔ **Nunca composer update sem --lock em servidor de produção**
- ⛔ **Nunca alterar branch ativa em produção pra testar** (usar worktree)
- ⛔ **Nunca editar arquivo direto via SSH** sem commit no git
- ⛔ **Nunca rodar daemons no Hostinger** (Reverb, Centrifugo, Horizon)

## Código
- ⛔ Não modificar tabelas core UltimatePOS sem bridge table
- ⛔ Não fazer UPDATE/DELETE em `ponto_marcacoes` (append-only por lei)
- ⛔ Não remover triggers MySQL de imutabilidade sem ADR
- ⛔ Não criar dependência nova sem ADR
- ⛔ Não responder em inglês — Wagner+Eliana são brasileiros
- ⛔ Não assumir completude — Wagner valoriza economia de crédito
- ⛔ Não suba código sem alertar pré-requisitos (3 incidentes históricos)

## Sempre
- ✅ PT-BR em tudo — texto, commit, comentário, label
- ✅ Cite a lei quando aplicável (CLT Art. 66, Portaria 671/2021, LGPD)
- ✅ Preserve imutabilidade de marcações + banco de horas
- ✅ Mantenha `business_id` scopado (skill multi-tenant-patterns Tier A)
- ✅ Escreva tests pra regras CLT + isolamento multi-tenant
- ✅ Antes de criar/mudar módulo, abra Jana/Repair/Project e imite
```

### `memory/regras-time.md` (~30 linhas)

Conteúdo: §10 atual (equipe interna, perfis, WIP, matriz quem-pode-pegar-qual-task).

```markdown
# Time interno (5 pessoas)

- **Wagner [W]** — líder, dono. Aprovação final.
- **Maiara [M]** — suporte+dev.
- **Felipe [F]** — dev+suporte.
- **Luiz [L]** — iniciante+dev IA-pair.
- **Eliana [E]** — financeiro+dev IA (esposa Wagner).

## WIP máximo
- W/M/F: 2 tasks · L/E: 1 task

## Regras duras
- L não mergeia PR sozinho (F ou W aprova)
- E não mexe em Jana sprints LGPD
- M não faz deploy produção sozinha
- W deve evitar gargalo — delegar review pra F quando puder
- PII real (CPF/CNPJ cliente) NUNCA em PR/commit/log (use [REDACTED])

## 2 Elianas
`Eliana[E]` (esposa, time interno) ≠ `Eliana(WR2)` (cliente externa, PontoWr2).
Sempre desambiguar em commits/notas.

## Convenção em commits
`[W]`, `[M]`, `[F]`, `[L]`, `[E]`, `[L+C]` (Luiz pareado Claude).
Ex: `feat(jana): PII redactor BR [F]`.

## Cycle de trabalho
2 semanas como `mcp_cycles` — `cycles-active` pra estado, `cycle-goals-track`,
`tasks-list cycle:current`. Daily async 09h cada um atualiza via `tasks-update`.
Sex final cycle: `cycles-close --rollover`.
```

---

## Validação técnica do `@imports`

Claude Code resolve imports recursivos até **5 níveis** ([Anthropic docs](https://platform.claude.com/docs/claude-code)).
Após mudança em prod, validar:

- [ ] Sessão real `claude` — Claude reconhece imports e resolve no contexto
- [ ] Mudança em arquivo importado (ex: `regras-time.md`) é refletida em próxima sessão
- [ ] Skill `brief-first` ainda dispara primeiro (independente do CLAUDE.md)

---

## Decisão pendente Wagner

> Marque APROVADO/AJUSTAR. Após aprovação Sonnet executa:
> 1. Cria 5 arquivos `memory/{why,what,how,proibicoes,regras-time}.md` movendo conteúdo
> 2. Substitui CLAUDE.md pelo template ~95 linhas
> 3. Commit + smoke real (sessão Claude com brief-first ativo)

- [ ] APROVAR proposta CLAUDE.md ~95 linhas + 5 imports?
- [ ] APROVAR conteúdo proposto pros 5 arquivos importados?
- [ ] Ou prefere ajustar algo específico?
