---
id: reference-cert-a1-icp-brasil-multi-uso
name: Certificado A1 ICP-Brasil multi-uso (single source NfeCertificado)
description: Padrão arquitetural canon — TODOS os drivers REST PJ BR (NFe SEFAZ + Sicoob API + Bradesco/Inter/BB/Itaú/Santander futuros) exigem cert ICP-Brasil A1 do CNPJ da empresa e reusam o mesmo `NfeCertificado` canon, NÃO criam storage próprio.
type: reference
---

# Cert A1 ICP-Brasil — single source canon

> Padrão estabelecido em 2026-05-27 após bug US-FIN-044/046. Wagner apontou duplicação: meu SicoobApiDriver criou `storage/app/private/sicoob/{biz}.pfx` plain, ao lado do `NfeCertificado` canon encrypted-at-rest. Refactor US-FIN-046 corrigiu — driver reusa `NfeCertificado`. Esse arquivo registra o padrão pra **próximas integrações bancárias** (Felipe/Maiara/Eliana implementarão Bradesco/Inter/BB/Itaú API drivers).

## Fato canon

**Todas APIs REST de cobrança PJ no Brasil que exigem mTLS pedem o MESMO tipo de certificado:**

| API | Cert exigido | Fonte |
|---|---|---|
| NFe SEFAZ | ICP-Brasil A1 do CNPJ da empresa | sped-nfe lib · convenções 2026 |
| Sicoob API Cobrança v3 | ICP-Brasil A1 do CNPJ da empresa | TecnoSpeed/Soften/ACBr 2026-05-27 |
| Bradesco API Cobrança | ICP-Brasil A1 do CNPJ da empresa | Bradesco docs (a confirmar) |
| Banco do Brasil API | ICP-Brasil A1 do CNPJ da empresa | BB docs (a confirmar) |
| Inter API (já implementado) | mTLS com `.crt` + `.key` Inter PJ A1 ICP-Brasil | Inter Empresas (cert é Inter mas formato compatível ICP-Brasil) |
| Itaú API Cobrança | ICP-Brasil A1 do CNPJ da empresa | Itaú docs (a confirmar) |
| Santander API Cobrança | ICP-Brasil A1 do CNPJ da empresa | Santander docs (a confirmar) |

**É O MESMO CERT.** Cliente uploadou UMA vez em `/fiscal/configuracao/certificado` (pra NFe) → serve pra TODAS.

## Single source canon

Storage canônico do cert vive em **`Modules\NfeBrasil\Models\NfeCertificado`**:

- Tabela `nfe_certificados` (multi-tenant `HasBusinessScope` ADR 0093)
- `.pfx` em disco em `storage/app/nfe-brasil/{biz}/cert/{uuid}.pfx.enc` — **encrypted-at-rest** via `Crypt::encrypt(file_contents)`
- Senha em `encrypted_password` (`Crypt::encryptString`)
- Validação parse PKCS12 + check CNPJ Subject CN bate com business CNPJ (PCI/LGPD)
- Apenas 1 cert ativo por business (rotação cega anterior)
- Service canônico: **`Modules\NfeBrasil\Services\CertificadoService::carregarParaSefaz(int $businessId): array`**

Retorno:
```php
[
    'pfx_binary' => string,         // binary decifrado em memória
    'senha'      => string,          // senha decifrada
    'valido_ate' => DateTimeInterface,
    'source'     => 'nfe_brasil' | 'legacy' | 'institucional',
]
```

## Como implementar novo driver REST PJ BR

Template (já validado em SicoobApiDriver US-FIN-046):

```php
namespace Modules\PaymentGateway\Services\Drivers;

use Modules\NfeBrasil\Services\CertificadoService;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use RuntimeException;

class BradescoApiDriver implements PaymentDriverContract
{
    public function __construct(
        private readonly CertificadoService $certificadoService,
    ) {}

    /**
     * mTLS options pra Guzzle — REUSA NfeCertificado canon.
     * Bradesco API exige ICP-Brasil A1, mesmo cert NFe SEFAZ usa.
     */
    private function mtlsOptions(PaymentGatewayCredential $cred): array
    {
        try {
            $cert = $this->certificadoService->carregarParaSefaz($cred->business_id);
        } catch (RuntimeException $e) {
            throw new CredentialMisconfiguredException(
                'Bradesco API exige certificado A1 ICP-Brasil cadastrado em ' .
                '/fiscal/configuracao/certificado — mesmo cert usado pra NFe SEFAZ. ' .
                "Erro original: {$e->getMessage()}"
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'bradesco-pfx-');
        if ($tempPath === false) {
            throw new CredentialMisconfiguredException(
                'Falha ao criar arquivo temporário pro .pfx Bradesco'
            );
        }

        file_put_contents($tempPath, $cert['pfx_binary']);
        @chmod($tempPath, 0600);

        register_shutdown_function(static function () use ($tempPath): void {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        });

        return ['cert' => [$tempPath, $cert['senha']]];
    }

    // ... resto do driver: OAuth2 + emitirBoleto + webhook + etc
}
```

**NÃO** crie:
- ❌ Storage próprio em `storage/app/private/{banco}/*` (vai duplicar cert)
- ❌ Colunas `requires_mtls` / `mtls_pfx_path` em `payment_gateway_credentials` (US-FIN-046 removeu)
- ❌ Upload `.pfx` no wizard `SheetNovoGateway` (US-FIN-046 removeu)
- ❌ Cifrar senha do `.pfx` em `config_json` (vem do `NfeCertificado` que já cifra)

## Wizard UI canon

Step 2 do `SheetNovoGateway.tsx` mostra indicador colorido do cert A1 ativo do business:
- ✅ Verde: cert ativo + >30d pra vencer
- ⚠️ Âmbar: cert ativo + ≤30d pra vencer
- ⚠️ Rose: cert vencido OU sem cert (deep-link `/fiscal/configuracao/certificado`)

Prop `nfeCertificadoAtivo` é passada pelo `PaymentGatewaysController::index` via método `nfeCertificadoAtivoPayload($businessId)`. Reusar essa prop em outros wizards de banco API.

## Multi-tenant Tier 0

`NfeCertificado` já usa `HasBusinessScope` (ADR 0093). Driver `carregarParaSefaz($cred->business_id)` retorna SOMENTE o cert do business da credencial. **NUNCA** chama `withoutGlobalScopes()` em queries de cert (exceto fallback institucional ADR 0186 — único caso autorizado).

Pest cross-tenant validação: `business_id=4` chama `carregarParaSefaz(4)`, `business_id=99` chama `carregarParaSefaz(99)` — paths/senhas distintos. Reutilizar pattern de `SicoobApiDriverMtlsTest::multi-tenant Tier 0`.

## Débito anotado — US-FIN-047

Hoje `Modules/PaymentGateway/Services/Drivers/*` importa de `Modules/NfeBrasil/Services/CertificadoService` — acoplamento cross-module semanticamente confuso ("por que driver de cobrança lê NfeBrasil?").

Quando 2º banco API entrar (Bradesco/Inter API mTLS / BB / Itaú / Santander), extrair pra módulo neutro:

- **Modelo:** `app/Models/CertificadoDigital` OU `Modules/Infra/Certificados/Models/CertificadoDigital`
- **Campo novo:** `proposito` enum (`nfe_sefaz` / `sicoob_mtls` / `bradesco_mtls` / `inter_mtls` / `bb_mtls` / `itau_mtls` / `santander_mtls`)
- **Validação CNPJ Subject CN** configurável por propósito (NFe exige bater, Sicoob exige bater, BB pode aceitar wildcard)
- **Service:** `CertificadoService` movido pra novo módulo, mantém método `carregarParaSefaz()` (alias) + novo `carregarParaProposito($bizId, $proposito)`
- **Migrar consumers** (NfeBrasil + PaymentGateway) pra novo model com aliases retrocompat

ETA: quando segundo banco API exigir refactor pra justificar.

## Lição registrada

Antes de criar storage/encrypt/upload em PR novo, **GREP por pattern canon existente**:

```bash
grep -r "NfeCertificado\|CertificadoService\|certificado_crt\|certificate_path\|\.pfx" Modules/ memory/reference/
```

Especialmente pra coisas que parecem "óbvio de criar do zero" (.pfx, cert digital, encrypt-at-rest, auth OAuth, mTLS) — provavelmente alguém já fez canonicamente. Confundir com decisão de implementação inicial gera 2 sistemas e cliente upload duplicado.

## Refs

- US-FIN-044 origem da Sicoob API (criou bug duplicação)
- US-FIN-046 fix do bug — driver reusa NfeCertificado
- US-FIN-047 backlog — extrair pra módulo neutro
- `Modules/NfeBrasil/Models/NfeCertificado.php`
- `Modules/NfeBrasil/Services/CertificadoService.php`
- `Modules/PaymentGateway/Services/Drivers/SicoobApiDriver.php` (template reuso)
- ADR 0093 multi-tenant Tier 0
- ADR 0105 cliente como sinal qualificado
- ADR 0186 fallback institucional cert (camada #3)
- Memory: `memory/sessions/2026-05-27-us-fin-044-sicoob-api-completion.md` (lições 7+8)
