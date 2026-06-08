# Repair — PII / LGPD

> Documento canônico de proteção a dados pessoais no módulo Repair (OS).
> Wave 3 v3 booster D7.a — herda PiiRedactor do core + LogsActivity Spatie.
> Ver também: [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0 multi-tenant), [SPEC.md](SPEC.md), [BRIEFING.md](BRIEFING.md).

## Por que importa

O módulo Repair lida com **dados pessoais do cliente** (Contact: CPF/CNPJ, nome, telefone, e-mail, endereço), **descrição de defeito** (pode revelar contexto pessoal — "celular do meu filho"), e **número de série de dispositivo** (rastreável ao proprietário).

LGPD Art. 7º — tratamento legítimo via execução de contrato (Art. 7º V) + interesse legítimo do controlador (Art. 7º IX) na prestação do serviço técnico.

## Defesa em profundidade

### 1. Isolamento multi-tenant (Tier 0 IRREVOGÁVEL)

[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md): `business_id` global scope obrigatório em `JobSheet`, `RepairStatus`, `DeviceModel`. Cliente da biz A nunca enxerga OS da biz B.

Implementação: `App\Concerns\HasBusinessScope` aplicado no `JobSheet`. Pest test cross-tenant biz=1 vs biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

### 2. PiiRedactor herdado do core

`App\Services\PiiRedactor` (core UltimatePOS) é o serviço canônico de **redação automática** de PII em logs/exports/comunicações externas.

Onde Repair usa (herdado, não duplicado):

- **Logs estruturados** — todo `Log::info/error` que toca `contact_id` ou `defects` passa por `PiiRedactor::redact($payload)` antes de gravar
- **Skill `commit-discipline` Tier A** — bloqueia commit com CPF/CNPJ real
- **Briefing diário Jana** — narrativa do brief usa `PiiRedactor` antes de chamar LLM Brain B

> ⛔ NUNCA logar `$jobSheet->customer->mobile` ou `$jobSheet->customer->tax_number` direto. Use `PiiRedactor::redactContact($contact)`.

### 3. Audit trail

Duas camadas complementares:

| Camada | Tabela | Escopo |
|---|---|---|
| FSM stage transitions | `sale_stage_history` (ADR 0143) | Mudanças de `current_stage_id` via `ExecuteStageActionService` |
| Field-level changes | `activity_log` (Spatie) | Mudanças de `status_id`, `service_staff`, `defects`, `device_*`, `completed_on` |

`JobSheet` declara `LogsActivity` trait (Wave 3 v3 booster) com `getActivitylogOptions()` listando 8 campos críticos + `logOnlyDirty()` (não loga UPDATE sem mudança real).

**Log name:** `repair_job_sheet` — facilita filtro em consulta `Activity::where('log_name', 'repair_job_sheet')`.

### 4. Retenção e direito ao esquecimento

LGPD Art. 18 III (anonimização) + Art. 16 (eliminação ao fim do tratamento).

Política atual: OS com `completed_on` > 5 anos podem ser anonimizadas via job `repair:anonymize-old-jobsheets` (a implementar — backlog). Mantém `device_id`, `defects`, `parts` (interesse legítimo estatístico), zera `contact_id` (FK NULL) ou aponta pra `Contact` anonimizado central.

## Pontos críticos de exposição (auditar antes de release)

1. **Print OS / customer copy** (`RepairController@printCustomerCopy`) — imprime nome+telefone+endereço. PDF baixado expõe PII off-system → operador deve descartar com segurança.
2. **Status público** (`/repair-status` em [Routes/web.php](../../Modules/Repair/Routes/web.php) linha 3) — endpoint sem auth recebe número OS + telefone últimos 4 dígitos. **Throttle obrigatório** (D8.a v3 booster) — ver [SPEC.md §"R-REPA-008"](SPEC.md).
3. **Upload de docs/fotos** (`JobSheetController@postUploadDocs`) — fotos podem capturar serial/IMEI/etiqueta cliente. Armazenamento em `arquivos` table (ADR 0123) tem `business_id` scoped + `bucket=active|archived`.
4. **Activity::where('subject_type', JobSheet::class)** — endpoint admin que lê audit log expõe diff de campos sensíveis (`defects`). Restrito a `superadmin` ou `repair.view_all`.

## Checklist pré-merge (PR que toca Repair)

- [ ] Sem CPF/CNPJ/telefone real em commit, log, comentário, test fixture
- [ ] Test fixture usa `Contact::factory()->fake()` (Faker) — nunca dump de cliente real
- [ ] Log de erro envolve `PiiRedactor::redact()` antes de gravar
- [ ] Migration nova tem `business_id` indexado + FK
- [ ] Test cross-tenant biz=1 vs biz=99 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))

## Skills relacionadas

`multi-tenant-patterns` (Tier A) · `commit-discipline` (Tier A) · `preflight-modulo` (Tier A)

---
**Última atualização:** 2026-05-16 — Wave 3 v3 booster D7.a/D7.b
