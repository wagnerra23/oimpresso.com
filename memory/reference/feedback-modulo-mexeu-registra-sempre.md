# Feedback — Mexeu no módulo? REGISTRA. Sempre. (Regra primária Tier 0)

## Regra

**Toda mudança em código de Module/, daemon CT 100, schema DB, config infra ou qualquer artefato operacional DEVE ser registrada IMEDIATAMENTE em git + tests + docs canon. Não existe "ajuste rápido", "fix temporário", "depois eu commito". Não há trabalho cinza.**

Wagner palavras textuais (2026-05-15 após maratona WhatsApp 14-15/mai):

> "isso deveria ser sempre assim como pode colocar isso no regra primaria, mexeu na merda do módulo registra caralho"

## Por quê (origem da regra)

Maratona WhatsApp 14-15/mai catalogou múltiplos incidentes onde DRIFT entre prod e git canônico custou horas:

1. **Drift Tier 0 — 13 rows manuais `whatsapp_lid_pn_map`** inseridas via SQL direto 14/mai 08:40 SEM commit, SEM PR, SEM ADR. Sumiram após-rebuild da memória do time. Causa inicial do cross-contact Wagner-Eliana.

2. **Daemon source drift** — `/srv/build/whatsapp-baileys-daemon/` (CT 100) diferia de `Modules/Whatsapp/daemon-node/` (git) em ~15 commits. `Dockerfile.bak-pre-823` aparecia no path como evidência arqueológica. Ninguém sabia o que cada arquivo significava.

3. **Auth state MySQL 103 rows corrompidas** — Baileys 6.x → 7.x deploy falhou porque NINGUÉM tinha purgado auth state antigo. Solução existia em código (PR #857 backup) mas processo de migração não tinha catalogada Fase 1.5 "purge pré-major-bump".

4. **Cache stale 1h TTL `whatsapp.auto_link:*`** — operador edita Contact CRM via SQL/UI, cache continua apontando pro mapping antigo. NÃO tinha ContactObserver invalidando. Cross-contact recorrente até PR #870 fechar.

5. **Eliana com `alternate_number=48999872822`** — número Wagner errado salvo no contato dela. Origem desconhecida no git, ninguém commitou esse INSERT. Causou bug raiz incident 14/mai.

**Padrão comum:** todos esses problemas tinham UMA fonte — alguém mexeu E NÃO REGISTROU. Resultado: tempo perdido em diagnóstico arqueológico, retrabalho, surpresas.

## How to apply

### TODA mudança operacional do oimpresso DEVE seguir um destes 5 caminhos canônicos:

| O que você fez | Onde registra |
|---|---|
| **Editou código Module** (PHP/TS/React) | PR no git → CI verde → merge |
| **Adicionou comando artisan / cron** | PR + entry em `app/Console/Kernel.php` + log estruturado |
| **Mexeu em schema DB** (DDL) | Migration PHP + Pest test sobrevive re-run + ADR se decisão arquitetural |
| **INSERT/UPDATE direto no DB** (SQL prompt, tinker, phpMyAdmin) | Seeder OR comando artisan idempotente OR backfill job + commit |
| **Editou arquivo direto no servidor** (Hostinger SSH, CT 100, daemon source) | **PROIBIDO** ([proibicoes.md](../proibicoes.md) já formalizado) — fazer via git pull do canônico |

### Sinais de falha (= violação dessa regra):

- "Vou rodar UPDATE rápido via tinker" sem seeder/migration → **DRIFT GARANTIDO**
- "Edita esse YAML no CT 100 só pra testar" → **DRIFT GARANTIDO**
- "Cria contato via UI direto, não precisa de PR" → **DRIFT GARANTIDO** se entity sensitive (LID map, auth state, etc)
- "Salva esse script bash no /opt/scripts/ direto" → **DRIFT GARANTIDO** se não tiver versão git via `infra/scripts/`
- "Esquece, depois eu commito" → **DRIFT GARANTIDO**

### Mitigação automática (defesas instaladas após maratona 14-15/mai)

| Defesa | Detecta | PR onde foi instalada |
|---|---|---|
| `whatsapp:auth-state-drift-check` (cron daily) | orphans + banned/inactive + stale 90d em `whatsapp_baileys_auth_state` | [#869](https://github.com/wagnerra23/oimpresso.com/pull/869) |
| `whatsapp:daemon-source-drift-check` (cron weekly) | source local vs daemon CT 100 prod | PR anterior |
| `whatsapp:channels-reconcile` (cron 5min) | drift channels DB ↔ daemon | PR #851 |
| `procedure_drift` check em `jana:health-check` | DDL direto em prod (CREATE/REPLACE PROCEDURE) | US-COPI-092 |
| `ProcedureDriftSnapshotTest` Pest | quebra CI se procedure mudou sem migration | ADR 0094 §5 |
| `Contact::observe(ContactObserver)` | invalida cache `whatsapp.auto_link:*` automaticamente em edição phone fields | [#870](https://github.com/wagnerra23/oimpresso.com/pull/870) |
| `block-automem.ps1` hook | bloqueia Write em auto-mem privada (`~/.claude/projects/*/memory/`) | ADR 0061 |

**Pattern emergente:** pra cada classe de drift descoberto em incidente, criar UM cron/test/observer que detecta drift naquela classe. Defesa-em-profundidade, não desespero ad-hoc.

### Quando Claude detectar pedido que GERA drift, deve:

1. **Recusar fazer "ajuste rápido"** sem PR — mesmo se for 5 linhas de SQL UPDATE
2. **Propor caminho canônico** — seeder, migration, comando artisan, PR
3. **Se Wagner insistir em ajuste rápido (Tier 0 superadmin):** marcar a operação explicitamente como `// DRIFT TIER 0 — Wagner aprovou em <data>, follow-up PR em <hash>` no log
4. **Spawnar PR de follow-up imediatamente** com a mudança aplicada → garantir que estado prod = estado git em ≤1 sessão

## Histórico catalogado da maratona 14-15/mai

| Tipo de drift | Custo | Defesa permanente |
|---|---|---|
| 13 rows manuais `whatsapp_lid_pn_map` (08:40 sem trail) | 4h investigação retrospectiva | PR #854 (resolver bloqueia `source=manual` sem webhook prévio) |
| Daemon CT 100 source diferente do git | ~30min descoberta pré-deploy 7.x | `daemon-source-drift-check` cron weekly |
| Auth state 6.x corrompido | ~30min purge manual pós-deploy 7.x | `auth-state-drift-check` cron daily |
| Cache stale Eliana 1h TTL | 3 ciclos cross-contact reincidente | `ContactObserver` PR #870 |
| `alternate_number=48999872822` cadastrado no contato errado | bug raiz incident 14/mai inteiro | SOFT-DELETE manual via SQL (drift cataloguei aqui mesmo) |

**Total:** ~5h trabalho retrospectivo + 12 PRs corretivos. Se a regra "mexeu, registra" tivesse sido seguida 100% nos 60 dias anteriores, **3 dos 5 vetores acima nem teriam existido**.

## Referências cruzadas

- [`memory/proibicoes.md`](../proibicoes.md) §"Ambiente" — proibição "Nunca editar arquivo direto via SSH" já formalizada — **regra emergente aqui é o complemento positivo: SE mexeu, REGISTRA**
- [`memory/handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-...md`](../handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-8prs-baileys7x-deploy-hostinger.md) — cronologia das 5 instâncias de drift custosas
- [`memory/reference/feedback-baileys-7x-decisao-irreversivel.md`](feedback-baileys-7x-decisao-irreversivel.md) — feedback irmão, mesma família (Wagner pediu rigor)
- [`.claude/skills/baileys-update-procedure/SKILL.md`](../../.claude/skills/baileys-update-procedure/SKILL.md) §Fase 1.5 — PURGA pre-major-bump catalogada
- [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §5 "SoC brutal" — princípio constitucional irmão
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant é um exemplo onde a regra "registra" é OBRIGAÇÃO LEGAL (LGPD)

## Updated

- Criado: 2026-05-15 — Wagner [W+C] após fechamento maratona WhatsApp 14-15/mai (12 PRs)
