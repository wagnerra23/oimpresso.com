---
adr: TBD (proposal draft — DORMENTE)
status: proposed-parked
date: 2026-05-20
deciders: [Wagner Rocha]
parked_reason: "Wagner decidiu analisar em sessão separada (2026-05-20) — aqui foco em ADR ativação Martinho. Voltar quando ciclo migrador for elevado de prioridade."
supersedes: []
superseded-by: []
related: [0119, 0137, 0143, 0105]
tags: [migration, scorecard, governance, legacy-clients, factory, quality-gate]
---

# Grade de Consistência de Migração (Migration Coverage Scorecard)

## Status

**proposed-parked** — proposta válida, congelada aguardando elevação de prioridade. Wagner vai analisar em sessão separada.

## Contexto

Migração Martinho Caçambas (biz=164, 2026-05-13) revelou **3 bugs estruturais** que importer rodou "sem erro" mas deixou dados inconsistentes:

1. **Financeiro não filtra correto** — query/Controller bug (UI/scope)
2. **Plano de Contas não migrado** — `import-financeiro.py` não popula `accounts` table (hierárquico)
3. **Clientes não conectados ao Financeiro** — `transactions.contact_id` NULL ou inválido (FK órfã)

Padrão: importer reporta "X rows imported" mas não valida integridade referencial, completude de tabelas dependentes, nem população de tabelas hierárquicas chave (`accounts`). Próximos 6 clientes legacy (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo/Produart) terão mesmo problema sem instrumentação.

Wagner pediu: "tem que fazer uma grade de consistencia de migração com uma avaliação da migração. e quero usar pra avaliar o que vai ser migrado e quantos porcento foi efetivo".

## Decisão proposta

Criar **Migration Coverage Scorecard** — instrumentação canônica que mede % efetividade da migração por dimensão, antes (esperado) e depois (efetivo), reutilizável pra todos clientes legacy.

### Entregáveis (3 peças)

| # | Peça | Stack | Quem usa |
|---|---|---|---|
| **A** | CLI `php artisan oimpresso:migration-scorecard --business=NNN` | Artisan Command + DSN Firebird + MySQL queries. Output markdown em `storage/app/migration-reports/<cliente>-<data>.md` + JSON | Equipe Wagner roda pré + pós migração de cada cliente |
| **B** | Page Inertia `/admin/migration/scorecard` | `Modules/Admin/` ou `Modules/Governance/`. Botão "Rodar scorecard agora" + histórico timeline + comparação inter-cliente | Wagner+gestores visualizam estado, decidem cutover |
| **C** | Apêndice §2.5 + §3.5 em [`RUNBOOK-migracao-cliente-legacy.md`](../../../requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md) | Doc | Equipe Wagner segue como checklist obrigatório pré e pós migração |

### Critério "efetivo" canônico

Cada dimensão mede 3 medidas + status:

| Medida | Significado |
|---|---|
| **Esperado** | count rows Firebird legacy (probe pre-migration) |
| **Migrado** | count rows MySQL biz=NNN (pós) |
| **Conectado** | count rows com FKs válidas (não NULL nem orphan) |
| **% efetivo** | `(Migrado ∧ Conectado) / Esperado` |

| Status | Regra | Cor |
|---|---|---|
| ✅ Verde | ≥ 95% | aprovado |
| 🟡 Amarelo | 80-94% | revisar antes cutover |
| 🔴 Vermelho | < 80% | bloqueia cutover |
| ⚫ Não migrado | 0% | feature gap, criar US |

### Dimensões canônicas (12 iniciais — extensível por vertical)

| Bucket | Dimensão | Tabela legacy | Tabela MySQL |
|---|---|---|---|
| Cadastros | EMPRESA | EMPRESA | businesses |
| Cadastros | PESSOA | PESSOA | contacts |
| Cadastros | PRODUTO | PRODUTO | products |
| Cadastros | FORMA_PAGAMENTO | FORMA_PAGAMENTO | payment_methods |
| Operacional | VENDA | VENDA | transactions(type=sell) |
| Operacional | COMPRA | COMPRA | transactions(type=purchase) |
| Vertical Oficina | EQUIPAMENTO_VEICULO | EQUIPAMENTO_VEICULO | vehicles |
| Vertical Oficina | ORDEM_SERVICO | ORDEM_SERVICO | service_orders |
| Financeiro | **PLANO_CONTAS** ⚠️ | PLANO_CONTAS | accounts |
| Financeiro | CONTAS_REC/PAG | FINANCEIRO | transaction_payments |
| Financeiro | MOV_CAIXA | MOVIMENTACAO_CAIXA | cash_register |
| LGPD | consent populated | (N/A) | contacts.consent_lgpd |

(buckets adicionais por vertical: gráfica → ARTE/ORCAMENTO/PRODUCAO; vestuário → PEDIDO/CORTE/COSTURA; etc)

### Exemplo de output (Martinho retrospectivo)

```
MIGRATION SCORECARD · Martinho Caçambas biz=164 · 2026-05-20
═══════════════════════════════════════════════════════════════════════
DIMENSÃO                ESPERADO  MIGRADO  CONECTADO  %EFETIVO  STATUS
───────────────────────────────────────────────────────────────────────
Cadastros básicos
  EMPRESA                     1        1          1     100%    ✅
  PESSOA                    XXX      XXX        XXX      XX%    🟡
  PRODUTO                   XXX        ?          ?       ?%    ⚫
Operacional
  VENDA                     44k      44k         XX      XX%    🟡
  EQUIPAMENTO_VEICULO        91       91         91     100%    ✅
Financeiro
  PLANO_CONTAS              XXX        0          0       0%    🔴 ← bug 2
  FINANCEIRO               103k     103k          0       0%    🔴 ← bug 3
  MOV_CAIXA                 XXX        ?          ?       ?%    ⚫
LGPD
  consent populated           —        —        XXX      XX%    🟡
───────────────────────────────────────────────────────────────────────
SCORE AGREGADO: ~54%  (3 verde · 2 amarelo · 3 vermelho · 1 não migrado)
STATUS: 🔴 BLOQUEADO PRA CUTOVER — fix Plano de Contas + FK contact_id
═══════════════════════════════════════════════════════════════════════
```

## Bugs concretos descobertos (evidência da necessidade)

Esses 3 NÃO são corrigidos por essa ADR — viram US separadas quando ciclo for elevado:

| Bug | Descrição | Causa provável | Tabela impactada |
|---|---|---|---|
| **B1** | Financeiro não filtra correto | `business_id` faltando em Eloquent scope OU join quebrado | `transactions` query Controller |
| **B2** | Plano de Contas não migrado | `import-financeiro.py` não popula `accounts` (omissão) | `accounts` (hierárquico parent_id) |
| **B3** | Clientes não conectados ao Financeiro | `transactions.contact_id` NULL no insert OU casamento CPF/CNPJ falhou | FK `transactions.contact_id → contacts.id` |

## Consequências

### Positivas
- ✅ Zero migração às cegas — equipe roda scorecard ANTES de cutover, vê % real
- ✅ Bugs estruturais (B1/B2/B3) viram visíveis em 1 comando, não em descoberta acidental
- ✅ Migration Factory ADR 0119 ganha quality gate canônico
- ✅ Comparação inter-cliente vira fácil (Martinho 54% → Vargas X% → padrão emerge)
- ✅ Reusável pros 6 próximos clientes legacy

### Negativas / riscos
- ⚠️ Custo desenvolvimento ~16-20h IA-pair (CLI + Page + RUNBOOK seção)
- ⚠️ Manutenção: cada nova vertical/bucket exige adicionar dimensão na grade
- ⚠️ Probe Firebird requer Wagner local (LAN) ou VPN — não 100% remoto
- ⚠️ "Conectado" pode ser caro de computar em tabelas grandes (44k vendas × FK check)

## Alternativas consideradas

1. **Continuar ad-hoc:** equipe roda queries SQL manuais a cada migração ❌ não escala, não consistente
2. **Comprar tool de migração ETL pronto** (Talend/Fivetran) ❌ pago, não custom pro oimpresso schema
3. **Só logging melhor no importer Python** ❌ não cobre pós-migration validation, só fase import
4. **Migration Coverage Scorecard** (esta) ✅ cobre pré + pós + comparação cliente-a-cliente

## Próximos passos (quando elevado)

Quando Wagner elevar prioridade (sessão separada):

1. ADR renumera (proposed-parked → proposed → accepted)
2. Criar US no MCP:
   - `US-MIG-001` Peça A — CLI scorecard
   - `US-MIG-002` Peça B — Page Inertia
   - `US-MIG-003` Peça C — apêndice RUNBOOK
   - `US-MIG-B1` fix financeiro filtro
   - `US-MIG-B2` fix Plano de Contas importer
   - `US-MIG-B3` fix FK contact_id (UPDATE batch retroativo Martinho)
3. Smoke Martinho retrospectivo + decidir cutover ou recovery

## Refs

- [ADR 0119](../../0119-paralelismo-sessoes-whats-active-tier-1.md) — Migration Factory (esta proposta = quality gate da Factory)
- [ADR 0137](../../0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada (Martinho piloto)
- [ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal (justifica investir só quando ≥2 clientes vão usar)
- [RUNBOOK migração legacy](../../../requisitos/OficinaAuto/RUNBOOK-migracao-cliente-legacy.md) — onde Peça C apenda
- Discovery Martinho 2026-05-13 — origem dos 3 bugs

---
**Registro:** salvo dormente 2026-05-20 a pedido Wagner ("salve isso como ciclo separado do migrador, vou analisar em outra sessão"). Voltar quando ciclo MCP de migração for criado/elevado.
