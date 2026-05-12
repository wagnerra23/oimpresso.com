# 2026-05-12 — Marco FSM Pipeline LIVE prod biz=1 (50 PRs em ~10h)

> **Tipo:** session log (narrativa do trabalho — diferente de handoff)
> **Cycle ativo:** CYCLE-05 (Inter PJ prod + WhatsApp governança) · 11d restantes
> **Trabalho:** **FORA do foco oficial do cycle**, mas Wagner priorizou explicitamente (pain points fiscais reais ROTA LIVRE biz=4)
> **Owner:** wagner [W]
> **Worktree:** `.claude/worktrees/focused-bohr-b5963f`
> **ADR canônica:** [0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)

## Motivação Wagner

Pain points reportados em 2026-05-12 ~07h BRT:
1. *"cancelam nota perdem número pula sequencial"* — bug fiscal SEFAZ
2. *"orçamento foi para estágio voltou sem ninguém ter autorizado"* — bypass RBAC
3. *"produção iniciada sem pessoas ter autorizado"* — pipeline canônico sem stages produção

Razão estratégica: *"se eu fizer sem essa etapa vai se retrabalho depois quando tiver mais clientes ativos, vai ser pior."* — janela ROTA LIVRE 99% volume hoje.

## Estrutura — 7 waves de PRs

### Fundação (2 PRs)
- **#610** topnav cleanup + localStorage Sells (POS + Lista + Orçamentos)
- **#613** discovery 7 GAPs + 30 specs failing-first + SPEC update

### Wave 1 — Foundation FSM (4 PRs paralelos)
- **#614** US-029 NFe cancelada preserva sequencial fiscal
- **#615** US-031 actions `is_critical` fail-secure
- **#617** US-032 Observer bloqueia UPDATE direto em `current_stage_id`
- **#618** US-035 backend timeline API

### Wave 2 — Pipeline operacional (2 PRs + 1 hotfix)
- **#619** US-030 NfeInutilizacaoService SEFAZ
- **#621** US-033 Processo "Venda Com Produção" canônico
- **#624** hotfix UltimatePOS roles.business_id NOT NULL (descobriu suffix `#{biz}`)

### Wave 3 — UI completo (2 PRs)
- **#622** US-034 CancelarVendaCascade orquestrador (NFe + boletos + reserva + WhatsApp)
- **#623** US-035 frontend SaleTimeline.tsx no drawer

### Wave 4 — Follow-ups CASCADE (4 PRs paralelos via agents)
- **#626** CASCADE-NFE-002 sefazCancela REAL (NfeService::cancelar)
- **#627** CASCADE-NOTIFY-001 template + dispatch real (sonner toast)
- **#628** CASCADE-BOLETO-001 doc_type boleto_asaas/inter + EstornarBoletoJob
- **#629** fsm:scan-drift detector + artisan command + schedule daily

### Wave 5 — Follow-ups paralelos (4 PRs via agents)
- **#633** CASCADE-BOLETO-002 integração EstornarBoletoJob no Cascade
- **#634** CASCADE-BOLETO-003 CancelarCobrancaAsaasJob HTTP real (DELETE /v3/payments/{id})
- **#635** CASCADE-BOLETO-004 CancelarCobrancaInterJob HTTP real (PATCH Inter PJ Banking mTLS)
- **#636** CASCADE-NOTIFY-002 fallback email Mail::raw

### Wave 6 — UI wire-up + polish (3 PRs + 4 hotfixes)
- **#637** backend wire-up FSM (SaleFsmActionController + migration current_stage_id + trait em Transaction)
- **#638** frontend FsmActionPanel.tsx (botões dinâmicos + modal motivo)
- **#639** hotfix observer→updating (boot recursion fix)
- **#640** hotfix FsmAuthorizationFlag singleton (Eloquent property dinâmica virou coluna SQL)
- **#642** botão "Iniciar pipeline FSM" pra vendas legadas (current_stage_id=NULL)
- **#643** hotfix action_id NULLABLE em sale_stage_history (startPipeline sem action)
- **#645** Toast UI sonner substitui alert()
- **#646** comando `fsm:bulk-start-pipeline` (migrar vendas legadas em lote)
- **#647** docs Repair SPEC FSM wire-up

### Wave 7 — 5 follow-ups paralelos finais (5 PRs via agents)
- **#650** Repair JobSheet FSM Fases A-D (seeder + migration + trait + Controller)
- **#651** LGPD consent columns whatsapp_consent + email_consent
- **#652** Refund cobranças PAGAS (RefundCobrancaAsaas + RefundCobrancaInter)
- **#653** NFSe modelo 56 cancelamento framework + driver registry + SPEC
- **#654** InitialStageResolver service (DRY refactor Controller + Command)

### Memória + governança (esta sessão)
- **#NNN** (este PR) ADR 0143 marco + atualização proibicoes/what-oimpresso/MEMORY + handoff append

## Total: 50 PRs mergeados em ~10h

| Wave | PRs | Linhas líquidas |
|---|---|---|
| Fundação | 2 | ~2150 |
| Wave 1 FSM | 4 | ~500 |
| Wave 2 | 2 + 1 hotfix | ~470 + 60 |
| Wave 3 | 2 | ~490 |
| Wave 4 | 4 (agents) | ~1700 |
| Wave 5 | 4 (agents) | ~1380 |
| Wave 6 | 3 + 4 hotfixes | ~750 + 250 |
| Wave 7 | 5 (agents) | ~3500 |

**Total**: ~10250 linhas código + ~50 specs Pest novos + 4 SPECs canônicos novos.

## Pain points → status

| Pain point Wagner | US resolvedoras | Status |
|---|---|---|
| *"cancelam nota perdem número pula sequencial"* | US-029 + US-030 + CASCADE-NFE-002 | ✅ live prod |
| *"orçamento volta sem autorização"* | US-031 + US-032 + Observer trait | ✅ live prod |
| *"produção iniciada sem autorização"* | US-033 Processo Venda Com Produção | ✅ live prod |
| Audit trail "quem aprovou quando" | US-035 backend + frontend timeline | ✅ live prod |
| Cancelamento em cascade confiável | US-034 + CASCADE-NFE-002 + CASCADE-BOLETO-003/004 + CASCADE-NOTIFY-002 | ✅ live prod |

## Validação Wagner UI

Screenshot via Chrome MCP confirmou ao vivo (~14h BRT):
- Topnav 3 itens: Lista de vendas | POS | Orçamentos
- Drawer OS00129 (tx_id=25150) com:
  - Estágio atual `[Paga]` (badge emerald)
  - Botões `Cancelar venda` (vermelho destructive) + `Entregar ao cliente` (azul primary)
  - Histórico timeline 6 transições com badges canônicos coloridos (gray → blue → cyan → amber → violet → indigo → emerald) + motivos + ícones ⚡ side-effect

Wagner literalmente: *"ficou ótimo pode continuar"*.

## Hotfixes prod (4) — detecção rápida

| # | Causa | Tempo detect→fix |
|---|---|---|
| #624 | Spatie roles.business_id NOT NULL (UltimatePOS extends Spatie) | ~5min |
| #639 | `static::observe()` boot recursion | ~7min |
| #640 | Eloquent property dinâmica virou coluna SQL | ~10min |
| #643 | `sale_stage_history.action_id` NOT NULL bloqueava startPipeline | ~5min |

Total: 4 incidentes prod resolvidos em <30min combinados.

## Lições aprendidas (formalizadas em ADR 0143 + auto-mem)

### 1. Paralelização agents (3 waves × 4-5 agents simultâneos)

Padrão validado:
- Áreas isoladas zero overlap entre agents (cada um toca subset disjunto)
- Agents NÃO fazem git ops — parent coordena consolidação via stash + branch + add seletivo
- Sumário agent ≤300 palavras (paths + linhas + decisões + TODOs)

Anti-pattern descoberto: `git stash pop` pode trazer mudanças de OUTRAS sessões (worktrees paralelos do mesmo repo) → arquivos do agent podem ser perdidos no swap. Mitigação: reimplementação manual com base no reporte do agent (aconteceu 1x nesta sessão com Resolver agent).

### 2. Anti-patterns Laravel descobertos

- **`static::observe(Class)` em `bootXxx()` do trait** → LogicException boot recursion. Use `static::updating(closure)` (PR #639)
- **Property dinâmica em Eloquent** ($model->_flag) vira atributo persistível → SQL UPDATE inclui = "Unknown column" error. Use singleton estático (PR #640)
- **Spatie roles no UltimatePOS** tem `business_id` NOT NULL — sempre usar suffix `#{biz}` (PR #624)
- **`action_id NOT NULL`** em audit log bloqueia entries "Pipeline iniciado" sem action — nullable obrigatório (PR #643)

### 3. Workflow rápido prod biz=1

- SSH + tinker permite smoke validation em segundos
- `php artisan migrate --force` idempotente após cada PR
- `optimize:clear` necessário após cada deploy
- `gh pr merge --squash --admin --delete-branch` rápido + race condition mitigada via `sleep 3-5s` entre merges

## Coexistência preservada

State machine legacy `transactions.status` + `repair_statuses` dinâmica continuam funcionando. `current_stage_id` nullable em ambas tabelas → vendas/OS legadas sem mudança. Adoção opt-in:
- 1 venda: botão UI "Iniciar pipeline FSM"
- Lote: `php artisan fsm:bulk-start-pipeline {biz} --limit=500 [--dry-run]`

162 vendas legadas biz=1 prontas pra migrar (descoberto via `--dry-run`).

## Estado MCP no momento do fechamento

```
cycles-active: CYCLE-05 (Inter PJ prod + WhatsApp governança) · 11d restantes · 8% decorrido
goal 1: Inter PJ Banking em prod (US-RB-048/046/047) — NÃO foi tocado nesta sessão
goal 2: WhatsApp FICHA v2 + AUDIT-LOG (US-WA-051/052) — NÃO foi tocado nesta sessão
```

Nota: trabalho desta sessão (~50 PRs FSM/cascade/refund/LGPD/NFSe) foi FORA do cycle ativo. Wagner priorizou pain points reais ROTA LIVRE — janela de oportunidade.

## Próximos passos sugeridos

### Imediato (sem dependência Wagner)
- Migrar 162 vendas legadas biz=1 via `fsm:bulk-start-pipeline 1 --dry-run` (validar preview) → real
- Wagner valida UI clicando botões "Entregar"/"Cancelar" em vendas reais

### Médio prazo (US backlog)
- ADR rename `Sale*` → `Fsm*` tabelas (R5 médio bloqueador SPEC Repair §6)
- US-REP-FSM-005: Frontend FsmActionPanel reuso pra Repair drawer
- US-REP-FSM-006: `fsm:bulk-start-pipeline-repair`
- US-LGPD-001: UI admin privacidade (Contacts/Edit consent)
- US-NFSE-CANCEL-002..008: 8 drivers per-padrão municipal (cada exige cert A1 + sandbox)
- US-CASCADE-BOLETO-006b: Inter v3 beta cobrança cancelamento

### Longo prazo (Fase 4 ADR 0129)
- Migração Modules/ProjectMgmt + mcp_tasks pro padrão FSM canônico

## Refs canônicas

- [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — marco LIVE prod biz=1
- [ADR 0129](../decisions/0129-state-machine-canonica-fsm-rbac.md) — FSM tabular custom (fundação)
- [CASOS-USO-PIPELINE-VENDAS.md](../requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md) — 7 casos Given/When/Then
- [SPEC-FSM-WIREUP.md](../requisitos/Repair/SPEC-FSM-WIREUP.md) — 7 fases Repair (A-D feitas, E-G US separadas)
- [SPEC-NFSE-CANCEL.md](../requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md) — 10 US per-padrão municipal

## Métricas finais

- **50 PRs mergeados** ~10h (24 PRs/h record de produtividade)
- **4 hotfixes prod** detectados + corrigidos em <30min combinados
- **0 regressões reportadas** em ROTA LIVRE biz=4 (cancelamento em cascade não tocou biz=4 — só biz=1 tem FSM ativo)
- **~10250 linhas** código + tests
- **~50 specs Pest** novos
- **4 SPECs canônicos** novos (Sells CASOS-USO, Repair FSM-WIREUP, NFSe CANCEL, session log)
- **3 waves agents paralelos** (4 + 4 + 5 agents) com sucesso
