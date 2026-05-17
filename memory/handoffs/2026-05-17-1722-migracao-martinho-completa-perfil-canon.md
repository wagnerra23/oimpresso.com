---
data: 2026-05-17 17:22
slug: migracao-martinho-completa-perfil-canon
sessao_iniciada: 2026-05-15 (3 dias)
participantes: Wagner + Claude
---

# Handoff — Migração Martinho Caçambas completa + perfil canon

## Estado MCP no momento do fechamento

> Snapshot via tools MCP foi pulado (sessão técnica de migração, sem mudanças em cycles/tasks ativos). Backlog OficinaAuto consultado anteriormente — 3 US criadas (US-OFICINA-023/024/025) durante a sessão.

## O que foi entregue

### Migração WR2 Firebird → oimpresso prod Hostinger (biz=164)

| Tabela | Registros | Notas |
|---|---:|---|
| `vehicles` | 91 | 100% das EQUIPAMENTO_VEICULO |
| `contacts` (WR2:*) | 9.988 | 100% das PESSOAS |
| `transactions` | 44.018 | ~96% das 46.065 VENDA |
| `fin_titulos` | 83.040 | Todas FINANCEIRO ATIVO* |
| `fin_titulo_baixas` | 71.675 | DATAPAGTO ≠ NULL |
| **Total nominal** | **R$ [redacted Tier 0]M** | quitados + abertos histórico |

### Aging analysis (bombshell pra demo Larissa)

| Categoria | Qtd | Valor aberto |
|---|---:|---:|
| 🔴 Fóssil pré-2020 | 3.594 | R$ [redacted Tier 0] (90%) |
| 🟡 Vencido pós-2020 | 272 | R$ [redacted Tier 0] (10% — devedor real) |
| 🟢 A vencer | 1 | R$ [redacted Tier 0] |

**História pra Larissa:** R$ [redacted Tier 0]M aberto na contabilidade → só R$ [redacted Tier 0]k é cobrança real, R$ [redacted Tier 0]M é histórico fóssil pré-2020 que vira write-off.

### Artefatos gerados

- **PR #1033** `docs/cliente-martinho-perfil` — perfil canon `cliente-martinho-cacambas.md` + atualizações cruzadas `_INDEX.md` e `clientes-ativos.md`
- **business_id=164** corrigido em prod ("JAIR UMBELINA VARGAS ME" → "MARTINHO CAÇAMBAS LTDA")
- **3 US-OFICINA criadas** (023 Equipamentos, 024 Kanban rename, 025 OS consumo)
- **6 reclamações documentadas** pendentes (Boleto Bradesco P0, tabela preços, cheque, descontos, NF entrada, RBAC vendedor/faturamento)

## Lições operacionais da migração

1. **Python morre silenciosamente em PowerShell** após ~30min — migrar pra Bash + wrapper `loop-financeiro.sh` com auto-restart resolveu
2. **Lock wait timeout / deadlocks recorrentes** no Hostinger MariaDB — solução: `--start-date` recalculada do MAX(emissao) atual a cada iter
3. **Conexões MySQL zombie ficam por horas** — `KILL` manual via `information_schema.PROCESSLIST WHERE TIME>300`
4. **Idempotência por chave lógica** salvou repetidas tentativas:
   - `vehicles.legacy_id`
   - `contacts.custom_field1 = 'WR2:{CODIGO}'`
   - `transactions.ref_no = VENDA.CODIGO`
   - `fin_titulos.numero = 'WR-{CODIGO}'`
5. **Iter Python rodou ~13h ininterruptos** quando zombies foram limpos antes do start (sem lock contention)
6. **VENDA.PLACA preenchida em apenas 94/46.065** — strategy de FK via `transactions.ref_no` (não PLACA) foi o que viabilizou os 96% importados

## Contexto competitivo

**HiSoft (Tubarão/SC)** já em implantação na Martinho. Diferencial oimpresso: chegar com universo legacy completo importado + diagnóstico Jana no dia 1 vs HiSoft que entrega zero (cliente digita tudo).

**Vargas (biz=170)** com sinais de cancelamento — mesma abordagem proativa é replicável.

## Próximos passos

- [ ] **Merge PR #1033** quando CI verde (auto-merge desabilitado no repo, exige aprovação manual)
- [ ] **Follow-up Larissa** apresentando o aging analysis
- [ ] **US-OFICINA-005 cleanup tool** — UI batch write-off dos R$ [redacted Tier 0]M fóssil
- [ ] **Boleto Bradesco API** — emergência P0 ("sangrando")
- [ ] **Migrar ~2k vendas faltantes** (sem PLACA válida) via outra estratégia FK
- [ ] **Replicar abordagem com Vargas (biz=170)** antes do cancelamento

## Refs

- Perfil canon: `memory/reference/cliente-martinho-cacambas.md`
- Scripts: `scripts/legacy-migration/{import-vehicles,import-contacts-from-venda,import-vendas,import-financeiro}.py` + `loop-financeiro.sh`
- US criadas: US-OFICINA-023, US-OFICINA-024, US-OFICINA-025
- Branch: `docs/cliente-martinho-perfil` (commit `71d95ffa4`, base `d2631403a` em `claude/caixa-unif-cutover-301`)
