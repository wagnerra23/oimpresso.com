---
name: Cliente Martinho Caçambas — perfil, migração concluída, contexto competitivo
description: Perfil do prospect Martinho Caçambas (business_id=164, CNPJ [REDACTED]). Migração legacy WR2 Firebird → oimpresso CONCLUÍDA 2026-05-16 (91 caçambas + 9.988 contatos + 44k vendas + 83k títulos). HiSoft é competidor. Aging real: 90% fóssil pré-2020.
type: project
---

# Martinho Caçambas — Perfil Cliente

## Identificação

| Campo | Valor |
|---|---|
| **Razão Social** | MARTINHO CAÇAMBAS LTDA |
| **CNPJ** | `[REDACTED]` — consultar `SELECT cnpj FROM business WHERE id=164` em prod Hostinger (LGPD Art. 7º) |
| **business_id oimpresso** | **164** (prod Hostinger) |
| **CNAE** | locação/manutenção caçambas |
| **Contato principal** | dona (nome não capturado em sessão) |
| **Demo realizada** | 2026-05-13 |
| **Localização** | (faltam dados — captar próxima visita) |

## Status competitivo

**HiSoft (Tubarão/SC)** já em implantação. Empresa "muito boa de software" (Wagner).

**Diferencial oimpresso:**
- HiSoft entrega zero: cliente digita do zero
- oimpresso já chega com 91 caçambas + 9.988 contatos + 44k vendas + 83k títulos importados
- Jana IA com diagnóstico financeiro honesto pronto no dia 1

**Vargas (biz=170)** mostra sinais de cancelamento — mesma abordagem proativa pode funcionar.

## Reclamações capturadas (demo 2026-05-13)

| US | Título | Prio |
|---|---|---|
| US-OFICINA-023 | Equipamentos — Box, Elevadores, Locais | p1 |
| US-OFICINA-024 | Kanban — renomear colunas | p2 |
| US-OFICINA-025 | OS — consumo peças/material extra | p1 |
| (pendente) | Boleto Bradesco API | p0 emergente ("sangrando") |
| (pendente) | Tabela de preços muito usada | p2 |
| (pendente) | Cheque como pagamento | p2 |
| (pendente) | Descontos — quem deu e por quê | p2 |
| (pendente) | NF Entrada — UX melhorada (lançamento errado) | p2 |
| (pendente) | Separar vendedor ≠ faturamento (RBAC + transportador/cond/peso) | p1 |

## Firebird legado (WR2) — fonte

| Campo | Valor |
|---|---|
| **Banco local Wagner** | `D:\DadosClientes\MartinhoCacamba\BANCO.FDB` (registry `Martinho`) |
| **Banco servidor-crm** | `D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB` (registry `MartinhoServidor`) |
| **IP direto testado** | `192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB` ✅ |
| **Credenciais** | SYSDBA / masterkey (padrão WR2 `{$IFDEF WR2}`) |
| **Charset** | WIN1252 |
| **VERSAO_BANCO** | 1404 (sem drift vs canônica Zoom v1474 — 0% gap) |

## Join strategy descoberto

```
VENDA.PLACA   → caçamba número (1-91), NÃO placa BR  ⚠️ só 94 vendas têm
EV.PLACA      → placa BR real do caminhão
EV.CODIGO     → número caçamba
VENDA real → caçamba: usar via lookup transactions.ref_no = VENDA.CODIGO + vehicles.legacy_id
```

⚠️ **Lição** — VENDA.PLACA preenchido em apenas 94/46.065. O importer canônico (`import-vendas.py`) usa outras estratégias FK pra pegar todas as 46k.

## Resultado da migração — 2026-05-16

| Tabela | Qtd importada | Notas |
|---|---:|---|
| `vehicles` | **91** | 100% das EQUIPAMENTO_VEICULO |
| `contacts` (WR2:*) | **9.988** | 100% das PESSOAS |
| `transactions` | **44.018** | ~96% das 46.065 VENDA (faltam ~2k sem PLACA válida) |
| `fin_titulos` | **83.040** | Todas FINANCEIRO ATIVO* importadas |
| `fin_titulo_baixas` | **71.675** | DATAPAGTO != NULL → baixa criada |
| **Valor total nominal** | **R$ 212.734.888,93** | inclui quitados + abertos histórico completo |
| **Total quitado** | R$ 143.227.197,48 (71.673 títulos) | |
| **Total aberto** | R$ 4.005.627,01 (3.867 títulos) | |

## Aging — bombshell pra Larissa

| Categoria | Qtd | Valor aberto |
|---|---:|---:|
| 🔴 **Fóssil pré-2020** | 3.594 | **R$ 3.613.465** (90%) |
| 🟡 Vencido pós-2020 | 272 | R$ 390.901 (10% — devedor real) |
| 🟢 A vencer | 1 | R$ 1.260 |
| **TOTAL** | **3.867** | **R$ 4.005.627** |

**Bala mágica da demo:**

> "Vocês têm **R$ 4 milhões em aberto na contabilidade**.
> Mas só **R$ 391 mil são clientes que devem AGORA**.
> Os outros **R$ 3,6 milhões (90%) é histórico fóssil pré-2020** — não existe mais cliente nem cobrança real.
> A oimpresso já chega importada com tudo + ferramenta de limpeza que apaga esses 90% em ~3 semanas."

## Scripts canônicos da migração

| Script | Função | Status |
|---|---|---|
| `scripts/legacy-migration/import-vehicles.py` | EQUIPAMENTO_VEICULO → vehicles | ✅ rodou prod |
| `scripts/legacy-migration/import-contacts-from-venda.py` | PESSOAS → contacts | ✅ rodou prod |
| `scripts/legacy-migration/import-vendas.py` | VENDA → transactions (UltimatePOS) | ✅ rodou prod |
| `scripts/legacy-migration/import-financeiro.py` | FINANCEIRO → fin_titulos + baixas | ✅ rodou prod |
| `scripts/legacy-migration/loop-financeiro.sh` | Wrapper bash auto-restart (resolve deadlock + python morre ~30min) | ✅ usado |
| `scripts/legacy-migration/inspect-*.py` | Probes diagnósticos | ✅ histórico |

### Lições operacionais da migração

1. **Python morre silenciosamente após ~30min em rodar PowerShell** — migrar pra Bash + criar wrapper auto-restart resolveu
2. **Lock wait timeout / deadlocks recorrentes** — Hostinger MariaDB com várias conexões competindo. Solução: `--start-date` calculada do MAX(emissao) atual a cada iter
3. **Conexões zombie ficam por horas** — `KILL` manual via `information_schema.PROCESSLIST` quando TIME>300
4. **Idempotência por chave lógica** salvou:
   - vehicles: `legacy_id`
   - contacts: `custom_field1='WR2:{CODIGO}'`
   - transactions: `ref_no=VENDA.CODIGO`
   - fin_titulos: `numero='WR-{CODIGO}'` + `metadata.legacy_codigo`
5. **Iter 2 do Python rodou ~13h ininterruptos** quando não tinha lock contention (zombies limpos antes do start)

## Histórico business_id=164

Registro originalmente estava cadastrado como "JAIR UMBELINA VARGAS ME" (erro importação WR2 legacy — campos CNPJ/nome trocados entre clientes). Wagner aprovou correção em 2026-05-13 → atualizado pra "MARTINHO CAÇAMBAS LTDA".

## Próximos passos

- [ ] **Follow-up Larissa** — apresentar relatório aging em call/visita
- [ ] **Cleanup tool** US-OFICINA-005 — UI pra batch write-off dos R$3,6M fóssil
- [ ] **Boleto Bradesco API** — emergência P0 conforme reclamação ("sangrando")
- [ ] **Migrar os ~2k vendas faltantes** (sem PLACA válida) usando outra FK estratégia
- [ ] **Replicar abordagem com Vargas (biz=170)** — sinais de cancelamento, mesma técnica
