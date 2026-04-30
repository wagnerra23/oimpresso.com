# MemoriaAutonoma — SPEC

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
- Por execução: ~R$ 0.10 (R$ 5/ano)
- Por mês: R$ 0.40

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
