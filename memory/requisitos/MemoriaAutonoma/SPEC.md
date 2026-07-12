---
module: MemoriaAutonoma
slug: memoriaautonoma-spec
title: "MemoriaAutonoma — SPEC"
type: spec
version: "1.0"
last_updated: "2026-06-13"
owners: [W]
status: historical
---

<!-- schema-allowlist: US sob "### User stories" (h3 dentro de "## Fase 1 — Auto-síntese semanal") + IDs US-MA-001..003 e US-MEMORIAAUTONOMA-001/002 sob "## Tasks operacionais"; corpo legado não tem heading h2 "## User stories" que case o gate — não renomeado pra preservar estrutura de fases. -->

# MemoriaAutonoma — SPEC

> ⚰️ **HISTORICAL — F1 (auto-síntese) implementada dentro do que virou `Modules/Jana` (KL-E2 · decisão E1 2026-06-15).** As `US-MA-*` aqui **não são contrato vivo**. Verdade viva → [`requisitos/Jana/`](../Jana/BRIEFING.md).

> **Status**: Fase 1 em implementação (2026-04-30)
> **Owner**: Wagner [W] · pode ser entregue à Eliana[E] após Fase 1
> **Goal**: memória que evolui sozinha sem ser SaaS

## Visão

Stack 4-camadas pra **memória compartilhada autônoma com auditoria**, complementando o que já existe:

```
┌─ Camada 7: Auto-onboarding (parcial ✅ via skill oimpresso-team-onboarding)
├─ Camada 6: Auto-evolução de skills (Fase 4, futuro)
├─ Camada 5: Auto-síntese semanal       (Fase 1, ESTA)
├─ Camada 4: Auto-validação contradição (Fase 3, futuro)
├─ Camada 3: Auto-extração de drafts    (Fase 2, futuro)
├─ Camada 2: Cache governado MCP        (✅ mcp_memory_documents)
└─ Camada 1: Source-of-truth git        (✅ memory/)
```

## Fase 1 — Auto-síntese semanal

### User stories

**US-MA-001**: Como Wagner, quero abrir Claude na segunda-feira e ver `memory/sessions/SEMANA-YYYY-Www-resumo.md` já pronto resumindo a semana passada.

**US-MA-002**: Como Eliana[E] que não acompanha tudo, quero ler 1 arquivo curto na segunda e ficar calibrada com o que aconteceu sem ter que ler 30 commits.

**US-MA-003**: Como Wagner, quero re-gerar a síntese de uma semana específica passando `--week=2026-W18 --force` se a primeira gerou ruim.

### Acceptance criteria

- [ ] `php artisan copiloto:sintese-semanal` roda sem args = semana ANTERIOR (não atual em curso)
- [ ] `--week=2026-W18` força semana específica
- [ ] `--dry-run` mostra inputs coletados sem chamar LLM
- [ ] `--force` sobrescreve arquivo existente; sem `--force` aborta com mensagem clara
- [ ] Output em `memory/sessions/SEMANA-2026-W18-resumo.md` com frontmatter
- [ ] Seções: Decisões · Implementações · Bloqueios · Próximos passos · Refs
- [ ] Citação de paths/hashes nas Refs (rastreável)
- [ ] Cron sex 18h em ambiente `live` (não roda em local sem `--force`)
- [ ] Falha de LLM/API loga em `copiloto-ai` channel e exit 1 (não cria arquivo vazio)
- [ ] Métrica `sintese_semanal_total` incrementa em `copiloto_memoria_metricas`

### Inputs coletados (semana = segunda 00:00 → domingo 23:59)

| Fonte | Como | Limite |
|---|---|---|
| Commits | `git log --since --until --pretty=format:%H\|%an\|%s` | 200 commits |
| Arquivos novos memory/ | `git log --diff-filter=A --name-only -- memory/` | sem limite |
| Diff CURRENT.md/TASKS.md/TEAM.md | `git log -p --follow` | top diff |
| ADRs novas/modificadas | `git log --name-only -- memory/decisions memory/requisitos` | sem limite |
| Sessões Claude Code (futuro F2) | tabela `mcp_cc_sessions` | 50 conv |

### Prompt LLM (Haiku 4.5)

System: "Você é o sintetizador semanal do oimpresso. Receba os artefatos da semana e gere uma síntese estruturada em PT-BR. Seja conciso — Wagner lê isso na segunda em <2min."

User template:
```
Semana: <YYYY-MM-DD a YYYY-MM-DD>

== COMMITS ==
<lista>

== ARQUIVOS MEMORY NOVOS ==
<lista>

== DIFF CURRENT/TASKS/TEAM ==
<diff resumido>

== ADRs NOVAS ==
<lista com slugs>

Gere síntese markdown com seções:
1. Decisões da semana (3-5 bullets, cita ADR slug)
2. Implementações mergeadas (3-5 bullets, cita commit hash curto)
3. Bloqueios identificados (se houver, cite contexto)
4. Próximos passos sugeridos (1-3 bullets)
5. Referências (paths/hashes pra navegar)

NÃO invente. Se não houver dado pra alguma seção, escreva "—".
```

### Custos

- ~5-10k tokens input + ~1-2k tokens output por execução
- Haiku 4.5: $0.001/k input + $0.005/k output (estimado)
- Por execução: ~R$ [redacted Tier 0] (R$ [redacted Tier 0]/ano)
- Por mês: R$ [redacted Tier 0]

### Métricas (Camada 2)

Incrementar em `copiloto_memoria_metricas`:
- `sintese_semanal_total` (counter)
- `sintese_semanal_input_tokens` (gauge última)
- `sintese_semanal_output_tokens` (gauge última)
- `sintese_semanal_duracao_ms` (gauge última)

## Fases futuras (não implementar ainda)

### Fase 2 — Auto-extração de drafts (~$5-15/mês)

Job diário processa `mcp_cc_sessions` últimas 24h, Haiku detecta "isso é decisão arquitetural?", grava draft em `memory/decisions/_drafts/NNNN-slug.md`. Wagner revisa em batch.

### Fase 3 — Auto-validação (~$2/mês)

Cron diário: embedding de cada ADR (Meilisearch hybrid já tem), detecta contradição (cosine > 0.85 + sentido oposto via LLM judge), reporta em `memory/_health/YYYY-MM-DD.md`.

### Fase 4 — Auto-evolução skills (integrado F2)

Padrão recorrente em N=3+ sessões `mcp_cc_*` → sugere skill nova com frontmatter pré-pronto em `.claude/skills/_drafts/`. Wagner aprova → vira skill auto-ativável.

## Refs

- [ADR ARQ-0001](adr/arq/0001-fase-1-sintese-semanal.md)
- ADR 0035 (laravel/ai canônico)
- ADR 0050 (copiloto_memoria_metricas)

---

## Tasks operacionais (governança auto-mem)

### US-MEMORIAAUTONOMA-001 · MEM-MIGRACAO Auto-mem → git/MCP (22 candidatos pós-consolidação 2026-05-10)

> owner: wagner · priority: p2 · estimate: 8h · status: todo · type: story
> blocked_by: —

#### Contexto

Sessão de consolidação massiva 2026-05-10 reduziu auto-mem `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\` de **63 → 32 arquivos** (49% redução). Auditoria cross-check com canon CLAUDE.md identificou **22 candidatos a migração pro git/MCP** (ADR 0061: zero auto-mem privada de conhecimento canônico do time).

#### Política ADR 0061

Migrar **1 por trigger contextual, NÃO em batch** — skill `automem-pending` Tier B ativa quando user toca path com auto-mem stale relacionada e força decisão "migrar git OU deletar".

#### Os 22 candidatos identificados

##### 🟢 RUNBOOKs operacionais (4) — baixa fricção
- `reference_tests_pest_canon.md` → `tests/README.md` ou `memory/requisitos/Infra/RUNBOOK-pest.md`
- `reference_deploy_e_recovery.md` → `memory/requisitos/Infra/RUNBOOK-deploy-hostinger.md`
- `reference_branch_protection_admin_merge.md` → `memory/requisitos/Infra/RUNBOOK-branch-protection.md`
- `reference_local_dev_setup.md` → `memory/requisitos/Infra/RUNBOOK-local-dev.md`

##### 🟢 Docs de módulo (7)
- `project_form_shim_migration.md` → `docs/MIGRATIONS/form-shim.md`
- `project_nfebrasil_estado_2026_05_07.md` → `memory/requisitos/NfeBrasil/STATE-2026-05-07.md`
- `project_officeimpresso_modulo.md` → `memory/requisitos/Officeimpresso/MODULE-NOTES.md`
- `reference_modules_cms_landing.md` → `memory/requisitos/Cms/MODULE-NOTES.md`
- `reference_financeiro_integracao.md` → `memory/requisitos/Financeiro/INTEGRATION-NOTES.md`
- `reference_ultimatepos_integracao.md` → `memory/requisitos/Core/ultimatepos-integration.md`
- `reference_mcp_endpoints.md` → `memory/requisitos/Infra/mcp-endpoints.md`

##### 🟢 Decisões/feedback que viram ADR (8)
- `feedback_check_main_antes_de_pr.md` → skill `commit-discipline` ou ADR PR-checklist
- `feedback_outbound_markdown_over_mcp_tasks.md` → ADR governança outbound
- `feedback_tenancy_changes_require_pest_local.md` → ADR refina 0093/0094
- `feedback_test_biz_99_cross_tenant_convention.md` → ADR refina 0101
- `feedback_auto_merge_quando_verde.md` → skill ou ADR policy merge
- `cockpit_layout_canonico.md` → ADR refina 0110
- `ideia_chat_ia_contextual.md` → ADR feature-wish (framework ADR 0105)
- `project_octane_mcp_prod_deps_pending_adr.md` → **abrir ADR estrutural** (pendente desde 2026-05-10)

##### 🟡 MISTO — split público/sensível (3)
- `cliente_rotalivre.md` — Migrar perfil + sensibilidades operacionais; CNPJ/telefone fica auto-mem
- `reference_clientes_ativos.md` — Tabela 7-businesses agregada migra; nomes/IDs ficam
- `reference_revenue_thesis_modulos.md` — Pricing migra; estimativas individuais ficam

#### 🔴 FICAM em auto-mem (8) — auto-mem é o lugar correto

`user_profile.md`, `trigger_guarde_no_cofre.md`, `reference_vaultwarden_credenciais.md`, `reference_cursor_collaboration.md`, `reference_hostinger.md`, `reference_infra_proxmox_ct100.md`, `reference_infra_rede_empresa.md`, `reference_legacy_delphi_firebird.md` (creds em plaintext, padrão ADR 0061).

#### Acceptance criteria

- [ ] Skill `automem-pending` ativa em pelo menos 5 paths cobrindo os 22 candidatos
- [ ] Manifesto `memory/requisitos/Infra/AUTO-MEM-PENDING.md` atualizado com status de cada
- [ ] PRs individuais (1 por arquivo migrado) com `Refs: US-MEMORIAAUTONOMA-001`
- [ ] Auto-mem deletada APÓS git push + webhook propagar pro MCP server (verificar via `mcp_memory_documents`)

#### Refs

- ADR 0061 (zero auto-mem privada)
- ADR 0053 (MCP server canônico)
- Sessão 2026-05-10 consolidação fase 2 (4 agentes paralelos, auditoria 4-eixos)

---

### US-MEMORIAAUTONOMA-002 · MEM-VERIFICAR 8 pendências stale detectadas pós-consolidação 2026-05-10

> owner: wagner · priority: p3 · estimate: 2h · status: todo · type: story
> blocked_by: —

#### Contexto

Auditoria de staleness na sessão de consolidação auto-mem 2026-05-10 identificou 8 itens com status questionável que **exigem decisão Wagner** (não dá pra fixar via análise estática).

#### Itens a verificar

##### 1. NfeBrasil smoke biz=1 aconteceu?
- Auto-mem `project_nfebrasil_estado_2026_05_07.md` diz "biz=1 PRONTA pra smoke" há 3 dias
- Goal cycle CYCLE-03: "1ª NFC-e real cstat 100"
- Brief atual: "venda OS00126/127/128/129 criadas biz=1" — pipeline OK mas sem confirmação cstat
- **Decidir:** flag `NFEBRASIL_AUTO_EMISSION_NFCE=true` ativada? Resposta SEFAZ recebida?

##### 2. ADR estrutural Octane+Mcp prod-deps
- Auto-mem `project_octane_mcp_prod_deps_pending_adr.md` aponta gap desde 2026-05-10
- composer.json tem `laravel/octane` + `laravel/mcp` em `require` (não dev)
- Hostinger contamina vendor/ — viola ADR 0062 (Hostinger ≠ CT 100)
- **Decidir:** abrir ADR? 3 opções identificadas no auto-mem

##### 3. Drift Modules/Cms (worktree ↔ produção)
- Auto-mem `reference_modules_cms_landing.md` afirma "Modules/Cms ausente no worktree, vive em produção"
- **Decidir:** drift resolvido nos últimos 14 dias?

##### 4. Roadmap PontoWr2 começou?
- ADR git `memory/requisitos/PontoWr2/adr/ui/0002` define 10 moves Tier A/B/C desde 2026-04-24
- **Decidir:** algum move começou ou ainda backlog dormente?

##### 5. Revenue thesis pós ADR 0121
- `reference_revenue_thesis_modulos.md` menciona "LaravelAI" como módulo separado (Tier 3)
- ADR 0121 (Modular especializado por vertical) pode ter mudado: "LaravelAI" virou Modules/Jana?
- **Decidir:** atualizar pricing tiers

##### 6. Central VoIP Issabel — relevância?
- `reference_infra_rede_empresa.md`: CentOS 7 EOL + Asterisk 13 EOL desde 2024 + MySQL root password desconhecida + 2 ramais online
- **Decidir:** projeto VoIP é prioridade ou desativar referência?

##### 7. Concorrentes Com.Visual — outbound rolou?
- Research 2026-04-25 (`reference_concorrentes_com_visual.md`) era pra "PR2 redesign Cms"
- Política atual `feedback_outbound_markdown_over_mcp_tasks` — só vira US se sinal qualificado
- **Decidir:** PR2 aconteceu OU virou outbound markdown ainda em standby?

##### 8. Ideia chat IA contextual — quando?
- `ideia_chat_ia_contextual.md`: "implementar depois Fase 1-3 redesign Ponto"
- Status redesign Ponto?
- **Decidir:** vira ADR feature-wish (framework ADR 0105) ou descarta?

#### Acceptance

- [ ] 8 verificações respondidas (sim/não/postpone com data)
- [ ] Auto-mem atualizada OU deletada conforme veredito
- [ ] Triggers concretos pra skill `automem-pending` ativar nos itens postpone

#### Refs

- Sessão consolidação 2026-05-10 (4 agentes paralelos, auditoria 4-eixos: QA + canon-cross-check + staleness + migração-ADR-0061)
