---
name: Pattern canônico de migração OfficeImpresso (Delphi/Firebird) → oimpresso (Laravel/MySQL)
description: Receita validada Wagner 2026-05 — Python firebird-driver + pymysql · idempotência por legacy_id · audit JSON · 4 fases (Empresas → Vehicles → Vendas → Financeiro). Calibrada em 3 clientes (WR2 biz=1, Vargas dry-run, Martinho biz=164 live)
type: reference
---

# Migração OfficeImpresso (Delphi/Firebird) → oimpresso (Laravel/MySQL)

> Pattern canônico do projeto pra migrar clientes legacy WR Comercial (50 bancos `.FDB`) pro stack moderno do oimpresso. Validado em 3 clientes (WR2 biz=1 ✅, Vargas dry-run, Martinho biz=164 ✅ 2026-05-13 13:31 BRT).

## 1. Stack canônica

| Camada | Tecnologia | Razão |
|---|---|---|
| Leitura Firebird | Python 3.13 + `firebird-driver` 1.x | Único driver maintained 2026; `fdb` deprecated |
| Resolver alias HKCU | `lib/firebird_reader.py` `firebird_connect(alias)` | Reusa registry Windows do Delphi (`HKCU\Software\Rocha\Office Comercial\Banco\Caminhos`) |
| Charset | `WIN1252` | Delphi BR legacy hardcoded |
| User/senha | `SYSDBA` / `masterkey` | `{$IFDEF WR2}` Principal.pas linhas 3446-3449 — global em 50 bancos. Placeholder no registry é só 1 char (não a senha real) |
| Escrita MySQL | Python `pymysql` (autocommit=false, rollback on err) | Bate com Laragon dev + Hostinger prod |
| Idempotência | UPSERT manual via `SELECT WHERE business_id+legacy_id` → UPDATE OU INSERT | Schema usa `index` (não `unique`) em `(business_id, legacy_id)` — ON DUPLICATE KEY não dispararia |
| Audit | JSON em `scripts/legacy-migration/output/audit-<tabela>-biz{N}-{ts}.json` com `raw_delphi` preservado per record | Re-construção retrospectiva + LGPD trail |

## 2. As 4 fases obrigatórias (em ordem)

### Fase 1 — Empresas (FK pra todo resto)

- **Fonte:** `EMPRESA WHERE ATIVO='S'` (113 cols)
- **Alvo:** `contacts` (type=both, multi-tenant business_id) + `business.tax_number_1` se for entidade própria
- **Importer:** `scripts/legacy-migration/import-empresas.py`
- **NÃO migrar:** `CERTIFICADO` (PKCS#12 base64), `CERTIFICADO_SENHA`, `WEB_SERVICE_SENHA`, `NFSE_SENHA`, `NFCE_*_CSC`, `APP_SENHA`, `NFE_NUMSERIE` → registro futuro Vaultwarden
- **Chave dedupe:** `cpf_cnpj` normalizado (digits only)
- **Status:** ✅ validado WR2 biz=1 (4 entidades · PRs #593/#596 fix Eloquent cast)

### Fase 2 — Veículos (Modules/OficinaAuto · só se vertical for oficina/locação)

- **Fonte:** `EQUIPAMENTO_VEICULO` (22 cols, mas só PLACA tem dado consistente — outras nulas em 100% Martinho)
- **Alvo:** `vehicles` (multi-tenant Tier 0 ADR 0093; soft delete)
- **Importer:** `scripts/legacy-migration/import-vehicles.py`
- **Mapping crítico §9.4 [TELA-LISTA-VENDAS.md](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md):**

  | Delphi | Laravel | Notas |
  |---|---|---|
  | `CODIGO` (int) | `legacy_id` (string 20) | Preserva chave natural pra dedupe |
  | `PLACA` | `plate` | Obrigatório → placeholder se nulo |
  | `PLACA2` | `secondary_plate` | Cavalo+reboque (Vargas) |
  | `CHASSI`/`CHASSI2` | `chassis`/`secondary_chassis` | — |
  | `ANO_FABRICACAO`/`ANO_MODELO` | `manufacture_year`/`model_year` | smallInteger |
  | `MOTOR` | `engine` | Texto |
  | `KM` | `mileage_at_entry` | unsigned int |
  | `COMBUSTIVEL` | `fuel_type` | — |
  | `TIPO`+`ESPECIE`+`CMOD` | `notes` (concat) | Campos livres Delphi → string única |

- **FK em VENDA:** `P.PLACA` é `int` FK pra `EQUIPAMENTO_VEICULO.CODIGO` (NÃO é string da placa!) → resolve via JOIN antes de migrar vendas (Fase 4)
- **Placeholder pra rows sem placa:** Sugestão `S/N-{codigo}` (Wagner aceitou `#EQ{codigo}` também — 2026-05-13 13:31). **Pegar pattern existente em prod antes de criar.** ⚠️ dado sujo Delphi (ex Martinho CODIGO=62 PLACA="PLACA:" literal) é mantido pra honestidade — Wagner corrige no app
- **Status:** ✅ validado Martinho biz=164 (91 vehicles · placeholder `#EQ{codigo}` pros 4 sem placa: 1, 48, 64, 90 · placa MIB2628 duplicada em CODIGO=59 e 63 mantida)

### Fase 3 — Vendas (Modules/Sells, com JOIN EQUIPAMENTO_VEICULO)

- **Fonte:** `VENDA` (centenas de cols; mapping em [TELA-LISTA-VENDAS.md](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md) §9.1-9.5)
- **Alvo:** `transactions` (UltimatePOS core, multi-tenant Tier 0)
- **Pré-req:** Fase 1 (contacts) + Fase 2 (vehicles) prontas — vendas referenciam ambos via FK
- **Conversões críticas:**
  - `VENDA_TIPO` → `transaction_type` (orçamento, venda, devolução, etc — mapping em `_MAPPING/TIPOS-VENDA.md`)
  - `STATUS` → `status` enum
  - `DT_EMISSAO` → `transaction_date`
  - `P.PLACA` → resolve em `transactions.vehicle_id` via JOIN com `vehicles.legacy_id`
- **Status:** 🟡 pendente (Vargas tem 1.064 veículos + multi-item; Martinho 44.709 vendas)

### Fase 4 — Financeiro (contas a pagar/receber)

- **Fonte:** `FINANCEIRO` (milhões de linhas em clientes grandes)
- **Alvo:** `transaction_payments` + linkagem com `transactions` Fase 3
- **Decisão estratégica Wagner:** cleanup-first — write-off candidate (`DT_VENCTO > 365d + sem BOLETO + sem movimentação`) flagado, NÃO importado. ROI maior que dunning pra 76.7% inadimplência típica (caso Martinho)
- **Status:** 🟡 pendente — depende US-OFICINA-005 (cleanup tools)

## 3. Idempotência canônica (TODA fase)

```python
# Pattern obrigatório em todo importer Python:
with con.cursor() as cur:
    cur.execute(
        "SELECT id FROM <tabela> WHERE business_id=%s AND legacy_id=%s LIMIT 1",
        (business_id, legacy_id),
    )
    row = cur.fetchone()
    if row:
        cur.execute(f"UPDATE <tabela> SET {set_clause}, updated_at=NOW() WHERE id=%s", ...)
    else:
        cur.execute(f"INSERT INTO <tabela> (...) VALUES (...)", ...)
```

NÃO usar `INSERT ... ON DUPLICATE KEY UPDATE` — schema usa `index` (não `unique`) em `(business_id, legacy_id)`. Migration nova adicionando `unique` quebraria 50 clientes legacy.

## 4. Pre-flight obrigatório ANTES de aplicar

```bash
# 1. Confirmar business_id alvo existe + identificar (CNPJ)
ssh hostinger 'cd ... && php artisan tinker --execute="echo \App\Business::find(N)->name;"'

# 2. Contar tabela alvo HOJE (detecta importação anterior)
ssh hostinger 'cd ... && php artisan tinker --execute="echo \DB::table(\"vehicles\")->where(\"business_id\", N)->count();"'

# Se já tem rows: PARAR + investigar quem importou antes
# - Procurar audit JSON em scripts/legacy-migration/output/
# - git log --since "data importação"
# - cc-search MCP sessions JSONL local
# - Decidir: avançar (presumir bem-feito) | re-importar com pattern canônico | sanitizar
```

## 5. Anti-patterns documentados (drift detectado em Martinho 2026-05-13)

| Anti-pattern | Sintoma | Mitigação |
|---|---|---|
| **Importer não-commitado** | 91 rows em prod sem `git log` correspondente; sem audit JSON; sem artisan command/seeder rastreável | Importer SEMPRE em `scripts/legacy-migration/import-<tabela>.py` commitado + audit JSON salvo |
| **Pattern placeholder divergente** | Agente A usa `S/N-{codigo}`, Agente B usa `#EQ{codigo}` — dados misturados | Pre-flight obrigatório: ver placeholder existente em prod ANTES de criar importer; reusar |
| **Wave 0 rename pulado** | [plano-paralelizacao.md](../requisitos/OficinaAuto/demo-martinho-2026-05-13/plano-paralelizacao.md) tinha `vehicles`→`oa_vehicles` como pré-req; agente importou em `vehicles` (sem prefixo módulo) | Charter/SPEC.md fonte de verdade; agente lê pré-reqs antes de Fase 1 |
| **Múltiplos agentes paralelos sem `whats-active`** | Wagner achou que estava começando Fase 2, mas agente cloud já tinha importado 4h antes | Hook `whats-active` MCP detecta sessões paralelas tocando paths overlapping ([ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)) |
| **PII em log** | CPF/CNPJ Martinho aparecer no audit JSON sem redação | Sempre `PiiRedactor::redact()` no campo `metadata.delphi_legacy` antes de `json.dumps()` |

## 6. Hooks LGPD obrigatórios

- **`EMPRESA_SECRET_FIELDS`** em `import-empresas.py` — set hardcoded de cols a NÃO migrar (8 campos sensitivos)
- **`Contact::canReceiveEmailNotification()`** — checar antes de qualquer email pós-import (LGPD opt-in)
- **Vaultwarden** pra credenciais bancárias (`CLIENTID`, `CLIENTSECRET`, `KEYFILE`, `CERTFILE`, `APPKEY` em `CONTAS.*`)
- **Não logar** `tax_number_1` raw — sempre `[REDACTED]` ou usar `PiiRedactor`

## 7. Refs canon

- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0121](../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical
- [ADR 0137](../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto qualificada (Martinho #1)
- [legacy-delphi-firebird.md](legacy-delphi-firebird.md) — DSNs, credenciais, registry, 50 bancos
- [project-officeimpresso-modulo.md](project-officeimpresso-modulo.md) — módulo Laravel desktop (licença)
- [matriz-conhecimento-clientes-legacy.md](matriz-conhecimento-clientes-legacy.md) — quem · banco · status · receita · vertical
- [migracao-auto-mem.md (Jana)](../requisitos/Jana/MIGRACAO-AUTO-MEM-2026-05-13.md) — origem migration auto-mem→git

## 8. Próximo cliente — checklist

Antes de disparar agent `migracao-officeimpresso` pra cliente novo:

- [ ] Hash do cliente identificado em [matriz-conhecimento-clientes-legacy.md](matriz-conhecimento-clientes-legacy.md)
- [ ] `business_id` no oimpresso existe (criar se necessário com `Business::create([...])`)
- [ ] Alias HKCU registrado (rodar `Get-ItemProperty 'HKCU:\Software\Rocha\Office Comercial\Banco\Caminhos'`)
- [ ] Vertical confirmada (Vestuario / ComVis / OficinaAuto / outros) — define quais fases rodar
- [ ] Sinal qualificado per [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente paga + reporta OU métrica drift)
- [ ] Discovery doc criado em `memory/requisitos/<Modulo>/discovery-<cliente>-YYYY-MM-DD.md`
- [ ] Pre-flight count tabela alvo em prod (detect import anterior)

---

**Última atualização:** 2026-05-13 ~16h BRT · sessão `angry-liskov-ec22c0` · descoberta drift Martinho (Wave 0 pulado + agente créditos esgotaram) catalogada.
