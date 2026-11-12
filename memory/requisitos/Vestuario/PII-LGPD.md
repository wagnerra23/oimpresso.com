---
id: requisitos-vestuario-pii-lgpd
---

# Vestuario — PII / LGPD

> Documento canônico de conformidade LGPD do **Modules/Vestuario** (vertical CNAE 4781-4/00 — varejo de artigos do vestuário). Atualizado 2026-11-12. Vinculado a [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7 e ADR canônica de PII redaction do core.

## Escopo de dados pessoais tratados

Vestuario é módulo **vertical-thin** ([SPEC](SPEC.md)). Tabelas próprias em 2026-11-12:

| Tabela | Dado pessoal |
|---|---|
| `vestuario_settings` | nenhum (configuração JSON por empresa) |
| `vestuario_remarcacoes` | `aplicada_por` e `revertida_por` (ids de usuário: identificam o funcionário) |
| `vestuario_remarcacao_itens` | nenhum (variação, preço antes e depois) |
| `vestuario_devolucoes` | `processed_by_user_id` e `motivo` (texto livre digitado pelo operador); sem chamador em produção desde 2026-10-26 |
| `vestuario_creditos_cliente` | `contact_id` (cliente final); sem chamador em produção desde 2026-10-26 |

Nenhuma coluna guarda nome, documento ou contato do cliente; o `motivo` é texto livre e pode conter o que o operador digitar. Toda PII de cliente final é tratada via tabelas core UltimatePOS:

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
- Pest tests (`Tests/Feature/*.php`) — **proibido** usar `biz=4` (ROTA LIVRE, produção) em fixtures. O tenant de teste é o fictício 98, com o 99 de adversário ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md))

## Conformidade Tier 0

- ⛔ PII reais (CPF/CNPJ cliente) NUNCA em PR/commit/log — usar `[REDACTED]` ou `PiiRedactor` do core (regra herdada [proibicoes.md](../../proibicoes.md))
- ✅ Multi-tenant Tier 0 — `business_id` global scope em `VestuarioSetting` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ✅ Audit trail via Spatie ActivityLog — ver [`Modules/Vestuario/Entities/VestuarioSetting.php`](../../../Modules/Vestuario/Entities/VestuarioSetting.php)

## Quando reavaliar

Quando Vestuario ganhar tabela própria com PII de cliente (ex: ficha cliente vestuário, medidas corporais, preferências), este doc precisa ser atualizado com:

- Lista de colunas PII próprias
- Pii redaction custom se aplicável (preferir delegar ao core)
- Controle por permissão de acesso. Purga ou anonimização por prazo não se aplica: num ERP o dado tem dever de guarda (decisão [W], lápide §5 2026-07-27 em [`proibicoes.md`](../../proibicoes.md))
