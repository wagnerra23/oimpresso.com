# Vestuario — PII / LGPD

> Documento canônico de conformidade LGPD do **Modules/Vestuario** (vertical CNAE 4781-4/00 — varejo de artigos do vestuário). Atualizado 2026-05-16. Vinculado a [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7 e ADR canônica de PII redaction do core.

## Escopo de dados pessoais tratados

Vestuario é módulo **vertical-thin** ([SPEC](SPEC.md) Sprint 1): contém apenas tabela `vestuario_settings` (settings JSON per-business) — **nenhuma coluna PII própria**. Toda PII de cliente final (consumidor que compra na loja de Larissa) é tratada via tabelas core UltimatePOS:

| Dado | Onde vive | Quem possui PII redaction |
|---|---|---|
| Nome cliente final | `App\Contact` (core UltimatePOS) | Core — `App\Services\PiiRedactor` |
| CPF/CNPJ cliente final | `App\Contact.tax_number` | Core — `App\Services\PiiRedactor` |
| Endereço entrega | `App\Contact.address_line_1/2` | Core — `App\Services\PiiRedactor` |
| Email/telefone cliente | `App\Contact.email`, `mobile` | Core — `App\Services\PiiRedactor` |
| Dados do business (Larissa) | `App\Business` | Core — não-PII público (CNPJ exposto em NFe) |

## Decisão arquitetural

Vestuario **NÃO cria PiiRedactor próprio**. Herda o redactor do core conforme ADR de PII redaction central. Justificativa:

1. **SoC brutal** (Constituição v2 §5) — uma única implementação de redaction garante consistência cross-módulo em logs/PR/commits
2. **Vestuario é vertical-thin** — não persiste PII própria; só referencia FK `business_id` (não-PII público)
3. **Larissa (business owner) é cliente identificado interno do oimpresso** — não-PII público do ponto de vista contratual (CNPJ aparece em NFe pública)

## Onde PII pode aparecer em logs do Vestuario

- `Modules/Vestuario/Console/Commands/VestuarioSettingsCommand.php` — settings JSON nunca contém PII por construção (só flags `format_date_shift_hours`, etc)
- Pest tests (`Tests/Feature/*.php`) — **proibido** usar `biz=4` (ROTA LIVRE prod, Larissa) em fixtures ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)). Tests usam `biz=1` sempre

## Conformidade Tier 0

- ⛔ PII reais (CPF/CNPJ cliente) NUNCA em PR/commit/log — usar `[REDACTED]` ou `PiiRedactor` do core (regra herdada [proibicoes.md](../../proibicoes.md))
- ✅ Multi-tenant Tier 0 — `business_id` global scope em `VestuarioSetting` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ✅ Audit trail via Spatie ActivityLog — ver [`Modules/Vestuario/Entities/VestuarioSetting.php`](../../../Modules/Vestuario/Entities/VestuarioSetting.php)

## Quando reavaliar

Quando Vestuario evoluir Sprint 2+ e ganhar tabelas próprias com PII (ex: ficha cliente vestuário, medidas corporais, preferências), este doc precisa ser atualizado com:

- Lista de colunas PII próprias
- Pii redaction custom se aplicável (preferir delegar ao core)
- Política de retenção LGPD Art. 16
