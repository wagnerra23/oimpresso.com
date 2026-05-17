# Modules/Arquivos

> DMS backbone (Document Management System) oimpresso — armazena, classifica, deduplica e protege qualquer arquivo (XML NFe, foto OS, contrato, anexo ticket) com encryption-at-rest opcional, audit trail LGPD e retenção declarativa.
> **Tier 0:** Multi-tenant `business_id` global scope ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
> **ADR mãe:** [0123](../../memory/decisions/0123-modules-arquivos-backbone.md).

## Como cliente (Wagner/Larissa/Martinho) usa

| Quero... | Como acontece | Onde aparece UI |
|---|---|---|
| Anexar XML NFe a transação | Backend `nfe:emitir` → `Transaction->attachArquivo($xml, ['context'=>'nfe-xml'])` automático | Aba "Documentos" da venda |
| Anexar foto entrada OS oficina | UI upload → `ServiceOrder->attachArquivo($foto, ['context'=>'repair-foto'])` | Sheet OS, seção "Fotos" |
| Anexar contrato a cliente | Drag-and-drop em `/contatos/{id}` → `Contact->attachArquivo($pdf, ['context'=>'contratos'])` | Aba "Contratos" do contato |
| Baixar arquivo sensível (RG cliente) | Clique no link → `arquivos.download` rota signed (60min validade) | Modal preview + botão baixar |
| Auditar quem baixou arquivo X | `arquivos_audit_log` tem entrada per `signed_url_issued` + `download` | Tela admin Memória (futura) |
| Excluir arquivo (LGPD direito eliminação) | Soft-delete em 1 clique. Hard-delete batch via `arquivos:retention-cleanup --purge` | Botão "Excluir" lista |
| Esquecer arquivo de cliente removido | `Contact` deletado → arquivos cascade soft-delete por polimorphic chain | Automático |
| Compliance prazo legal (NFe 5 anos) | `config/retention.php` declara `nfe-xml=1825d`; cron mensal expira | Health check `arquivos:health` |
| Economizar storage com dedupe MD5 | Mesmo arquivo (MD5 igual) no mesmo business reusa storage; counter sobe | Automático no `attach()` |

## Garantias

- **Multi-tenant Tier 0** — arquivo de `business_id=1` jamais aparece em query/listagem/download de `business_id=4` (global scope `business_id` em `Arquivo`)
- **Encryption-at-rest** — bucket `sensitive` (RG, contrato, dados médicos) usa `VaultEncryptionService` (Crypt AES-256-CBC, APP_KEY-backed)
- **Audit trail dupla** — `arquivos_audit_log` (upload/download/signed_url/soft_delete/restore/hard_delete) + Spatie `activity_log` (mudanças de bucket/visibility/retention sem PII)
- **PII redaction** — filename pode trazer "rg-123.456.789-00.pdf"; `PiiRedactor` redaciona ANTES de persistir em audit ou log
- **Retenção declarativa** — `Config/retention.php` é fonte da verdade auditorial; `arquivos:retention-cleanup` consome
- **Dedupe ZERO leak cross-tenant** — lookup MD5 sempre filtra `business_id` (Agent E security review §dedupe leak)

## Observabilidade D9.a ([ADR 0155](../../memory/decisions/0155-module-grade-v3-tier-a-d9-otel.md))

Spans canon (zero-cost se `otel.enabled=false`):

- `arquivos.attach` · `arquivos.classify` · `arquivos.signed_url` · `arquivos.soft_delete` · `arquivos.restore`
- `arquivos.vault.put_encrypted` · `arquivos.vault.get_decrypted`
- `arquivos.retention.scan` · `arquivos.retention.expire_one` · `arquivos.retention.purge_one` · `arquivos.retention.run`

Atributos sempre `business_id` Tier 0 + `module=Arquivos`. NUNCA filename, MD5, ou storage_path em attributes (PII potencial).

## Journey real biz=1 (Wagner dev)

| Passo | Ação | Resultado |
|---|---|---|
| 1. Emitir NFe via `nfe:emitir 12345` | Job `EmitirNfeJob` chama `attachArquivo($xml, ['context'=>'nfe-xml'])` no Transaction | Row em `arquivos`, bucket=`active`, retention=1825d |
| 2. Subir foto entrada caçamba OS-789 | UI form upload → `ServiceOrder::find(789)->attachArquivo($jpg, ['context'=>'repair-foto'])` | bucket=`active`, retention=730d, owner morph=`ServiceOrder` |
| 3. Subir RG do motorista locatário | Form upload `["context"=>"contratos","sensitive"=>true]` → CuradorEngine classifica `bucket=sensitive` | Storage=`vault` disk, encrypted=true, retention=1825d |
| 4. Cliente pede link download de NFe | Backend gera `URL::temporarySignedRoute('arquivos.download', $arquivo, 60min)` | Audit log `signed_url_issued`, link válido 1h |
| 5. Cliente abre link, baixa XML | `DownloadController` valida signature + audita `download`, stream do disk | XML descriptografado se vault, raw se public |
| 6. Após 5 anos, cron expira XML NFe | `arquivos:retention-cleanup` lê `Config/retention.php`, scan + expire + purge batch | Soft-delete + remove storage após `grace_period_days=30` |
| 7. Auditar "quem baixou esse RG?" | Query em `arquivos_audit_log` filtrando `action=signed_url_issued OR download` | Lista user_id + created_at, PII redactada em payload |

## Estrutura

```
Modules/Arquivos/
├── Concerns/HasArquivos.php          # Trait pra Models receberem arquivos (Transaction, ServiceOrder, Contact, Ticket)
├── Console/Commands/                 # 6 commands: audit-log, dedupe-stats, health-check, recalcular-metadata, reencrypt-vault, retention-cleanup, export-zip
├── Database/Migrations/              # arquivos + arquivos_audit_log + arquivos_dedupe (+ backfills)
├── Entities/Arquivo.php              # Multi-tenant + SoftDeletes + LogsActivity + polymorphic owner
├── Http/
│   ├── Controllers/                  # Data + Download + Install
│   └── Requests/                     # Upload, Download, List, Delete, Restore validation
├── Services/
│   ├── ArquivosService.php           # API canônica: attach/classify/signedUrl/softDelete/restore/dedupe
│   ├── ArquivosRetentionService.php  # Scan + expire + purge LGPD Art. 16
│   ├── VaultEncryptionService.php    # Crypt AES-256-CBC wrapper pra disk=vault
│   └── Curador/CuradorEngine.php     # Classificação heurística (bucket/sub_destination/sensitive_flags)
├── Tests/Feature/                    # 15+ Pest (parity, multi-tenant, encryption, retention, dedupe, journey)
├── Config/config.php + retention.php # Operacional + auditorial LGPD
└── README.md (este arquivo)
```

## LGPD ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4)

- `pii_fields_tracked`: original_name (filename pode conter CPF/CNPJ), storage_path, md5 (apenas em `arquivos_audit_log` dedicado — NUNCA em `activity_log` Spatie)
- `pii_redactor_enabled`: true (defesa em profundidade — `PiiRedactor::redactArray` em audit payload + erro de log)
- `activity_log_enabled`: true (mudanças de bucket/visibility/retention via Spatie LogsActivity — sem PII)
- `retention`: declarativa em `Config/retention.php`, 8 entidades mapeadas (nfe-xml 1825d, repair-foto 730d, contratos 1825d, default 90d)

## Como integrar arquivo a um novo Model

```php
// 1. Trait no Model:
use Modules\Arquivos\Concerns\HasArquivos;

class MeuModel extends Model {
    use HasArquivos;
}

// 2. Anexar arquivo:
$meu->attachArquivo($request->file('upload'), ['context' => 'meu-contexto']);

// 3. Listar arquivos do model:
$meu->arquivos()->bucket('active')->get();

// 4. Listar somente classificados (ex: bucket=sensitive):
$meu->arquivosClassificados('sensitive');
```

## Referências

- ADR mãe: [0123](../../memory/decisions/0123-modules-arquivos-backbone.md) (Sprint 1+2 ratificadas)
- ADR multi-tenant: [0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR observabilidade: [0155](../../memory/decisions/0155-module-grade-v3-tier-a-d9-otel.md)
- ADR retention chunked encryption: [0126](../../memory/decisions/0126-arquivos-chunked-encryption-sprint2.md)
- LGPD Art. 15-16 (eliminação tempestiva) + Art. 18 §VI (direito eliminação)
- SPEC: `memory/requisitos/Arquivos/SPEC.md`
- CHANGELOG (append-only): [`CHANGELOG.md`](CHANGELOG.md)
