# PII-LGPD-FISCAL — exceção fiscal CONFAZ no módulo NfeBrasil

> Documento canônico que justifica por que `PiiRedactor` **NÃO se aplica** ao módulo NfeBrasil, e como a conformidade LGPD é preservada por base legal fiscal + audit-trail técnico.

## 1. Por que NfeBrasil é exceção D7.a (PiiRedactor)

O ERP oimpresso aplica `PiiRedactor` em logs/comentários/exceções pra evitar vazamento de CPF/CNPJ de cliente (ADR 0093 multi-tenant + skill `commit-discipline`). **NfeBrasil é exceção justificada** pelos seguintes motivos:

- **CPF/CNPJ obrigatórios em XML SEFAZ** — Layout NFe 4.00 exige `<CPF>` ou `<CNPJ>` do destinatário em campos `<dest>` (modelo 55) e `<dest>` opcional NFC-e modelo 65 (acima do limite R$ 200,00). Mascarar viola schema XSD da SEFAZ — XML rejeitado com `cStat=215` ("Falha schema XML").
- **Logs de envio SEFAZ devem preservar payload original** — re-envio (`cStat=108` "serviço em escala"), debug de rejeição (`cStat=233` "CPF inexistente na base") e reprocessamento (`cStat=539` "duplicidade chave de acesso") exigem o payload original byte-a-byte. Redact = perda de rastreabilidade fiscal.
- **Base legal CONFAZ SINIEF 07/2005 Art. 14** — Documentos fiscais eletrônicos (NFe, NFC-e, CT-e, MDF-e) e seus eventos (cancelamento 110111, inutilização, carta de correção) devem ser preservados em estado original pelo prazo de 5 anos (Art. 195 CTN), disponíveis pra fiscalização SEFAZ a qualquer momento.
- **LGPD Art. 7º II + Art. 11 II.a** — Tratamento de dados pessoais é lícito quando "para o cumprimento de obrigação legal ou regulatória pelo controlador". Emissão fiscal é obrigação regulatória direta (Lei Complementar 87/1996 ICMS + Decreto 7.212/2010 IPI).

## 2. Modelos cobertos pela exceção

| Model | Tabela | Conteúdo PII fiscal | Retention |
|---|---|---|---|
| `NfeEmissao` | `nfe_emissoes` | XML completo com `<dest>` CPF/CNPJ + endereço fiscal | 5 anos (CONFAZ + CTN Art. 195) |
| `NfeEvento` | `nfe_eventos` | Eventos 110111 (cancelamento), 110110 (CCe) com chave-44 | 5 anos |
| `NfeInutilizacao` | `nfe_inutilizacoes` | Range de números inutilizados + justificativa | 5 anos |
| `NfseEmissao` | `nfse_emissoes` | XML/JSON NFSe ABRASF com CPF/CNPJ tomador | 5 anos |
| `NfseEventoCancelamento` | `nfse_eventos_cancelamento` | Eventos cancelamento NFSe | 5 anos |

XML fiscal original armazenado em `arquivos` table (ADR 0123 trait `HasArquivos`) com `sub_destination='nfe-xml'` ou `'nfe-danfe'` — **bucket `active` durante prazo legal, depois archive S3/Glacier**.

## 3. Como conformidade LGPD é preservada (D7.b — audit trail)

Mesmo sem `PiiRedactor`, o módulo cumpre LGPD via:

### 3.1 LogsActivity (Spatie) em Models críticos

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class NfeEmissao extends Model
{
    use LogsActivity; // D7 audit trail — quem disparou emissão fiscal

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'cstat', 'motivo', 'numero', 'chave_44', 'emitido_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('nfe_emissao');
    }
}
```

- **Quem** emitiu/cancelou/inutilizou (causer_id) — preserva accountability LGPD Art. 37 (responsabilidade pessoal)
- **Quando** (created_at do log) — atende Art. 37 + auditoria SEFAZ
- **O que mudou** (status, cstat) — não loga o XML body completo (já no `arquivos` table)

### 3.2 Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

Todo Model NfeBrasil usa `HasBusinessScope` (global scope `business_id`) — **cross-tenant leak é impossível por construção**. CPF/CNPJ de cliente biz=4 nunca aparece em query biz=1.

### 3.3 Bucket lifecycle (ADR 0123)

- `bucket=active` — XML acessível via UI durante 90 dias (operação normal)
- `bucket=archive` — após 90 dias, move pra storage frio (S3 Glacier) — ainda acessível por SEFAZ via reidratação
- `bucket=purge` — **NUNCA** pra documentos fiscais (proibido por CONFAZ até 5 anos)

### 3.4 Direito do titular (LGPD Art. 18)

Cliente pode solicitar:
- **Acesso (Art. 18.II)** — endpoint `/nfe-brasil/transactions/{tx}/emissoes` lista todas NFe da venda dele
- **Portabilidade (Art. 18.V)** — DANFE PDF + XML download via `emissoes/{id}/danfe-pdf`
- **Eliminação (Art. 18.VI)** — ⛔ **NEGADA** durante prazo legal fiscal de 5 anos (LGPD Art. 16.I "cumprimento de obrigação legal ou regulatória") — após expiração, lifecycle job purga `arquivos` com `bucket=archive AND retention_until < now()`

## 4. Operações Tier 0 IRREVOGÁVEIS

- ⛔ **Nunca aplicar `PiiRedactor` em payload de envio SEFAZ** (`Modules/NfeBrasil/Services/Sefaz/*`) — XML rejeitado por schema XSD
- ⛔ **Nunca `forceDelete()` em `nfe_emissoes`** com status `autorizada` ou `cancelada` — número permanece usado oficialmente (CONFAZ SINIEF 07/2005 Art. 14). Apenas `rejeitada`/`denegada`/`erro_envio` viram `inutilizada` preservando registro (já documentado em [proibicoes.md](../../proibicoes.md))
- ⛔ **Nunca expor XML completo em log estruturado padrão** (`Log::info(['xml' => $xml])`) — preserva em `arquivos` table com link signed-URL temporário. Log estruturado loga apenas `chave_44` + `cstat` + `motivo`
- ✅ **Sempre `LogsActivity` nos 5 Models críticos** — accountability LGPD Art. 37 (D7.b satisfeito)
- ✅ **Sempre `business_id` global scope** — isolamento Tier 0 ADR 0093
- ✅ **Sempre throttle em rotas emissão SEFAZ** — anti-DOS protege resource externo (latência SEFAZ 2-15s)

## 5. Refs

- **CONFAZ Ajuste SINIEF 07/2005 Art. 14** — Sequência fiscal append-only + cancelamento por evento 110111
- **CTN Art. 195** — Guarda 5 anos documentos fiscais
- **LGPD Art. 7º II + Art. 11 II.a** — Base legal "cumprimento obrigação legal/regulatória"
- **LGPD Art. 16.I** — Eliminação negada durante prazo legal fiscal
- **LGPD Art. 18** — Direito do titular (acesso, portabilidade, eliminação após expiração)
- **LGPD Art. 37** — Accountability via LogsActivity
- **Layout NFe 4.00** — Schema XSD `<dest><CPF>|<CNPJ>` obrigatório
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0123](../../decisions/0123-arquivos-table-bucket-lifecycle.md) — Bucket lifecycle XML fiscal
- [SPEC.md](SPEC.md) — User stories NfeBrasil
- [BRIEFING.md](BRIEFING.md) — Estado consolidado módulo

---
**Última atualização:** 2026-05-16 — Onda 3 D7 LGPD fiscal compliance.
