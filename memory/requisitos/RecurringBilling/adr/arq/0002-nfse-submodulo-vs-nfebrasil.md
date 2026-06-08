# ADR ARQ-0002 (RecurringBilling) · NFSe é sub-módulo dedicado, não estende NfeBrasil direto

- **Status**: ⚠️ parcialmente superseded em 2026-04-30
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, `NfeBrasil/adr/arq/0002-lib-sped-nfe-vs-acbr.md`
- **Superseded por**: `NFSe/adr/arq/0001-cliente-oimpresso-modulo-standalone.md` — Wagner decidiu (2026-04-30) que NFSe vira `Modules/NFSe/` standalone (NÃO dentro de RecurringBilling). Cliente é a empresa oimpresso (não ROTA LIVRE) e UltimatePOS já tem `recurring_invoice` nativo. Esta ADR fica como referência arquitetural (NFSe separada de NfeBrasil) mas a localização no RecurringBilling está cancelada.

## Contexto

NFSe (Nota Fiscal de Serviço Eletrônica) tem 2 caminhos:

1. **Estender NfeBrasil** — adicionar modelo NFSe junto com NF-e/NFC-e/MDF-e/CT-e
2. **Sub-módulo separado dentro de RecurringBilling**

Diferenças relevantes:

| Aspecto | NF-e (NfeBrasil) | NFSe |
|---|---|---|
| Padrão | Nacional (SEFAZ) | Municipal hoje, Federal a partir LC 214/2025 |
| Webservices | 27 UFs convergidos | **5570 municípios** com layouts diferentes (até LC 214 vigorar) |
| Lib PHP | `eduardokum/sped-nfe` | `rafwell/laravel-focusnfe`, `nfeio/php-sdk`, `plugnotas/api` |
| Cert digital | Mesmo A1 do NfeBrasil | Mesmo cert (compartilha) |
| Quando | Venda de produto | Venda de serviço (mensalidade, assinatura) |
| Integração | Observer venda → emite | Trigger billing → emite |

Em RecurringBilling, ~95% das emissões são NFSe (mensalidade = serviço, geralmente). Forçar passar por NfeBrasil cria coupling pesado entre módulos.

## Decisão

**NFSe é sub-módulo dedicado dentro de RecurringBilling**.

Estrutura:

```
Modules/RecurringBilling/
Modules/NFSe/                ← sub-módulo separado
├── Providers/
│   └── NfseServiceProvider.php
├── Adapters/
│   ├── FocusNFeAdapter.php
│   ├── PlugNotasAdapter.php
│   └── NFEioAdapter.php
├── Models/
│   ├── NfseEmissao.php
│   └── NfseProvider.php
└── Services/
    └── NfseEmissaoService.php
```

Compartilha com NfeBrasil:
- Cert A1 (mesma tabela `nfe_certificados`)
- Padrões de timezone, idempotência, retenção XML
- Tabelas core UltimatePOS (`businesses`, `contacts`)

Não compartilha:
- Schemas (`nfse_*` vs `nfe_*`)
- Service providers
- Permissões
- Lib (NFSe usa `rafwell/laravel-focusnfe` ou similar)

## Consequências

**Positivas:**
- NFSe pode evoluir independente — adicionar provider novo (NFE.io) sem mexer em NfeBrasil
- Falha em emissão NFSe não trava emissão de NF-e (módulo isolado, queue separada)
- NfeBrasil mantém-se focado em produtos (NF-e/NFC-e/MDF-e/CT-e — escopo mercadoria)
- Tenant que só vende serviço (academia, software, agência) pode usar **só NFSe**, sem NfeBrasil
- Simplifica spec: não vira "NfeBrasil + NFSe + ..."

**Negativas:**
- Algum código duplicado (cert validation, audit log)
- Mais 1 módulo nwidart pra gerenciar (pequeno custo)
- Documentação espalhada (resolvido: docs cross-link entre módulos)

## Quando converge com NfeBrasil

A partir da Lei Complementar 214/2025, NFSe vira **federal** com layout nacional unificado. Em algum momento (provavelmente 2027-2028), faz sentido reunificar:

- Quando NFSe federal substituir municipal completamente
- Quando emissor aceitar layout único (sem fallback municipal)
- Decisão re-avaliada em ADR futuro com base na adoção real

Hoje (2026-04): manter separação porque municípios ainda dominam emissão NFSe.

## Pattern de provider

```php
interface NfseProvider {
    public function emitir(NfseEmissaoPayload $p): NfseEmissaoResult;
    public function consultar(string $protocolo): NfseStatus;
    public function cancelar(NfseEmissao $emissao, string $motivo): void;
}

class FocusNFeAdapter implements NfseProvider { /* ... */ }
class PlugNotasAdapter implements NfseProvider { /* ... */ }
class NFEioAdapter implements NfseProvider { /* ... */ }
```

Tenant escolhe provider em config; adapter agnóstico do município.

## Razão pela escolha de provider (vs implementar do zero)

5570 municípios = impossível implementar todos. Providers fazem isso:

- **Focus NFe** (Focusnfe.com.br) — preço justo, BR-only, suporte sólido. Recomendado MVP.
- **PlugNotas** — concorrente direto Focus, similar em preço/feature
- **NFE.io** — mais corporate, preço maior

Custo per-emissão ~R$ 0,50 a R$ 1,50; embutir como custo (não cobrar tenant separado).

## Alternativas consideradas

- **Estender NfeBrasil** — rejeitado: coupling, falha cruzada, escopo conflituoso
- **Implementar do zero (5570 municípios)** — rejeitado: insanidade
- **Provider único hard-coded** — rejeitado: lock-in, sem fallback se Focus down

## Referências

- ARQ-0001 (mesmo padrão event-driven)
- `NfeBrasil/adr/arq/0002`
- LC 214/2025 — NFSe federal
- Focus NFe documentação
- `rafwell/laravel-focusnfe` — wrapper Laravel
