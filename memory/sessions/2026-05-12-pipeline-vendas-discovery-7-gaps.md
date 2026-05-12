# 2026-05-12 — Pipeline Vendas: discovery 7 GAPs + spec executável

> **Tipo:** session log (narrativa do trabalho — diferente de handoff)
> **Cycle ativo:** CYCLE-05 (Inter PJ prod + WhatsApp governança)
> **Trabalho:** **fora do foco oficial** do cycle, mas Wagner priorizou explicitamente
> **Owner:** wagner [W]
> **Worktree:** `.claude/worktrees/focused-bohr-b5963f`

## Motivação Wagner (sessão 2026-05-12)

Wagner reportou 3 pain points operacionais reais:

1. **"cancelam nota perdem número pula sequencial"** — bug fiscal recorrente
2. **"orçamento foi para estágio voltou sem ninguém ter autorizado"** — bypass RBAC
3. **"produção iniciada sem pessoas ter autorizado"** — falta de stages canônicos

**Razão estratégica forte:**
> *"se eu fizer sem essa etapa vai se retrabalho depois quando tiver mais clientes ativos, vai ser pior. Agora eu posso dizer que foi eles que lançaram errado e não fica feito. Prefiro resolver antes"*

Janela de oportunidade: ROTA LIVRE 99% do volume hoje, mais 6 OfficeImpresso saudáveis em pipeline de vendas (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart). Fundação canônica feita AGORA evita bola de neve com mais clientes.

## Decisão de approach

Wagner pediu: **descreva, documente, crie testes pra validar, eu valido, depois implementamos**.

Estratégia: **doc + tests failing-first** (especificação executável). Zero código de produção alterado. Wagner valida antes de qualquer mudança em `app/Domain/Fsm/`, `Modules/NfeBrasil/`, `app/Http/Controllers/`.

## Discovery — o que JÁ existe

Antes de produzir documento novo, mapeei o que Wagner já tem:

| Existente | Status |
|---|---|
| [ADR 0129](../decisions/0129-state-machine-canonica-fsm-rbac.md) — FSM tabular custom canônica | accepted 2026-05-10 |
| `app/Domain/Fsm/` — 16 classes (5 Models, Service, Policy, 3 SideEffects, 2 Exceptions, Job) | implementado |
| 5 tabelas FSM: `sale_processes`, `sale_process_stages`, `sale_stage_actions`, `sale_stage_action_roles`, `sale_stage_history` | migrations rodando |
| Tabela `stock_reservations` + 3 SideEffects (Reservar/Consumir/Liberar) | US-013 done PR #510 |
| Tabela `transaction_documents` poly (N notas por venda) | US-014 done PR #508 |
| `ExecuteStageActionService::execute()` com 6 responsabilidades | implementado |
| `StageActionPolicy::canExecute()` pra UI esconder botões | implementado |
| 3 processos seed: Sem Nota / Com Nota Manual / Com Nota Automática | US-012 done PR #507 |
| 8 testes Pest verdes em `tests/Feature/Domain/Fsm/ExecuteStageActionServiceTest.php` | rodando |
| Tabela `nfe_inutilizacoes` | criada (migration 002003) **sem service** |
| `proximoNumeroLocked` com `lockForUpdate` | implementado |

**Conclusão**: ~80% da fundação já existe. Wagner foi metódico. **Não duplicar.**

## Discovery — 7 GAPs identificados

Auditoria detalhada de [NfeService.php](../../Modules/NfeBrasil/Services/NfeService.php) + [ExecuteStageActionService.php](../../app/Domain/Fsm/Services/ExecuteStageActionService.php) revelou:

### G1 (P0 fiscal) — `forceDelete` de NFe cancelada pula sequencial
[NfeService.php:380-398](../../Modules/NfeBrasil/Services/NfeService.php#L380) trata `cancelada` igual a `rejeitada/denegada` (status terminal negativo → hard delete pra permitir retry). MAS:
- `cancelada via SEFAZ` = número usado oficialmente (imutável fiscal)
- `rejeitada/denegada` = número nunca declarado (pode reaproveitar via inutilização)

Mistura gera buraco no sequencial → infração [CONFAZ Ajuste SINIEF 07/2005 Art. 14](https://www.confaz.fazenda.gov.br/legislacao/ajustes/2005/ajuste-007-05).

### G2 (P0 fiscal) — Sem `NfeInutilizacaoService`
Tabela `nfe_inutilizacoes` existe sem código que faça `Tools::sefazInutiliza()` e persista resultado.

### G3 (P1 governança) — Actions FSM sem role permitem bypass
[ExecuteStageActionService.php:62](../../app/Domain/Fsm/Services/ExecuteStageActionService.php#L62) — se `roleNames` vazio, libera pra qualquer user. Seed esquecer role de action crítica = bypass silencioso.

### G4 (P1 governança) — UPDATE direto em `current_stage_id`
Service é gateway recomendado mas não obrigatório. Qualquer `Transaction::update(['current_stage_id' => X])` ou `$tx->current_stage_id = X; $tx->save()` bypassa RBAC.

### G5 (P0 negócio) — Sem processo "Venda Com Produção"
3 processos seed atuais são lineares (sem stage `in_production`). Cliente OficinaAuto/Vargas/Gold não consegue modelar OS produtiva via FSM canônica.

### G6 (P1 negócio) — Sem side-effect `CancelarVendaCascade`
Cancelar venda hoje é manual: cancelar NFe + estornar boleto + liberar reserva + notificar cliente. Sem orquestrador → inconsistência.

### G7 (P2 UX) — Sem UI timeline `/sells/{id}/historico`
`sale_stage_history` registra tudo desde US-011 mas nenhuma tela mostra. Wagner não responde "quem aprovou? quando?" sem rodar SQL.

## Entregáveis (zero código de produção)

### 1. Casos de uso canônicos
[`memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md`](../requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md) — 700 linhas:
- 7 casos Given/When/Then (CU-01..CU-07)
- Mapeamento GAP → US → arquivo de teste
- Base legal (CONFAZ) onde aplicável
- Critérios mínimos pra aprovação Wagner

### 2. Testes Pest failing-first
Specs executáveis em `tests/Feature/Domain/Fsm/`:
- [`SequencialNfeAposCancelamentoTest.php`](../../tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php) — 8 specs (CU-01 G1+G2)
- [`TransicaoCriticaExigeAutorizacaoTest.php`](../../tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php) — 5 specs (CU-02 G3)
- [`CurrentStageIdBypassObserverTest.php`](../../tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php) — 5 specs (CU-03 G4)
- [`ProcessoVendaComProducaoTest.php`](../../tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php) — 7 specs (CU-04 G5)
- [`CancelarVendaCascadeSideEffectTest.php`](../../tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php) — 5 specs (CU-05 G6)

Total: **30 specs failing-first**. Quando rodadas, falham com mensagens claras tipo "Class X not found" — Wagner pode rodar `php artisan test --filter=PipelineVendas` e ver exatamente o que falta.

### 3. SPEC.md atualizado
Adicionado §6 Pipeline Vendas — 7 GAPs canônicos com US-SELL-029..035:
- US-029 — NFe cancelada não sofre forceDelete (P0 fiscal, 3h+5h)
- US-030 — NfeInutilizacaoService (P0 fiscal, 6h+4h)
- US-031 — Action `is_critical` fail-secure (P1 governança, 2h+1h)
- US-032 — Observer bypass current_stage_id (P1 governança, 4h+3h)
- US-033 — Processo "Venda Com Produção" canon (P0 negócio, 6h+4h)
- US-034 — CancelarVendaCascade side-effect (P1 negócio, 4h+3h)
- US-035 — UI timeline FSM (P2 UX, 8h)

**Total recalibrado** ([ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10x IA-pair): ~20-25h codáveis + canary 7d.

## Estado MCP no momento do fechamento

```
cycles-active: CYCLE-05 (Inter PJ prod + WhatsApp governança) · 11d restantes
my-work: 4 tasks DOING (Inter RUNBOOK, Whatsapp múltiplos números, Jana Horizon, NarrarSaudeEcosistemaJob)
sessions-recent: 2026-05-11 JANA Pro Sprint A foundation + Concierge MVP (handoff)
decisions-search since 2026-05-11: 0136 (Sells Grade), 0137 (OficinaAuto), 0140 (Jana Pro), 0141 (skill migracao)
```

## Próximos passos

1. **Wagner valida** os 4 entregáveis (700 linhas doc + 5 tests + SPEC update + este log)
2. **Após aprovação** → criar US-SELL-029..035 no MCP via `tasks-create` (não fiz agora pra evitar pollution se Wagner pedir ajuste de escopo)
3. **Sequência de implementação sugerida** (não-cíclica):
   - US-029 (NFe cancelada — P0 fiscal) → desbloqueia US-030
   - US-030 (NfeInutilizacaoService — P0 fiscal)
   - US-031 (is_critical — P1) + US-032 (Observer — P1) — paralelizáveis
   - US-033 (Venda Com Produção — P0 negócio) — bloqueada por US-031+032
   - US-034 (CancelarVendaCascade — P1) — bloqueada por US-029 + US-033
   - US-035 (Timeline UI — P2) — bloqueada por US-033

## Anti-padrões evitados

- ❌ Escrever ADR FSM do zero (já existe 0129)
- ❌ Duplicar US-SELL-011..014 (já done)
- ❌ Implementar código antes de Wagner validar (publication-policy + pedido explícito)
- ❌ Tasks markdown ad-hoc (ADR 0070 — tasks via MCP só após aprovação)
- ❌ Alterar `current_stage_id` schema sem aviso prévio (Tier 0)

## Métricas da sessão

- **Discovery time**: ~20min (4 reads paralelos + decisions-search)
- **Doc time**: ~30min (CASOS-USO + SPEC update)
- **Tests failing time**: ~25min (5 arquivos, 30 specs)
- **Total**: ~75min produzindo ~2000 linhas markdown + 700 linhas Pest

**Refs canônicas:**
- [ADR 0129](../decisions/0129-state-machine-canonica-fsm-rbac.md) (mãe FSM)
- [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) (§5 SoC, §6 Tier 0)
- [ADR 0040](../decisions/0040-policy-publicacao-claude-supervisiona.md) (publication-policy — espera approval)
- [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) (estimates recalibradas)
