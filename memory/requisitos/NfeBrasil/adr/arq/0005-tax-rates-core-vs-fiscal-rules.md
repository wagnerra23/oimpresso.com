# ADR ARQ-0005 (NfeBrasil) · `tax_rates` core e `nfe_fiscal_rules` convivem (bridge)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, ARQ-0004, US-NFE-010, `Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md` (mesmo padrão de "convivência")

## Contexto

UltimatePOS upstream tem `tax_rates` table desde 2017 (`database/migrations/2017_07_26_110000_create_tax_rates_table.php`):

```sql
tax_rates (
  id, business_id, name, amount FLOAT(22,4),
  is_tax_group BOOL, created_by, deleted_at, timestamps
)
group_sub_taxes (group_tax_id, tax_id)  -- N-N para grupar taxas
```

Modelo simples: 1 nome + 1 alíquota %. Aplica em produto via `Product.tax` ou inline na venda. Grupos somam taxas.

**Problema:** modelo é genérico (não-BR). Não tem:
- NCM (8 dígitos, classificação Receita Federal)
- UF origem × UF destino (ICMS muda por estado)
- CST/CSOSN (tipo tributário; varia por regime)
- CFOP (código fiscal de operação)
- MVA, FCP, DIFAL, ICMS-ST (specifics ICMS Brasil)
- IPI, PIS, COFINS, CBS, IBS

Connector e outros módulos upstream consomem `tax_rates` direto (`Product.tax_id` FK). Substituir = quebra cross-module.

3 estratégias:

| Estratégia | Pró | Contra |
|---|---|---|
| **Convive paralelo** (tax_rates pra tenant simples; nfe_fiscal_rules pra fiscal completo) | Não quebra Connector | Tenant confuso ("onde configuro?"); 2 fontes de verdade |
| **Substitui** (`tax_rates` deprecated, motor lê `nfe_fiscal_rules`) | UX clara, fonte única | Quebra core/Connector/Accounting; merge conflict no upgrade |
| **Bridge** (motor é fonte de verdade; `tax_rates` regenerada como cache) | UX clara + compat upstream | Sync overhead |

## Decisão

**Bridge — `nfe_fiscal_rules` é fonte de verdade quando NfeBrasil ativo. `tax_rates` continua existindo (compat upstream) e é regenerada por listener.**

Princípios:

1. **Quando NfeBrasil ATIVO no business:**
   - UI `/taxes` (core) ganha banner: "Use Configuração Fiscal Avançada — `/nfe-brasil/tributacao/regras`"
   - `tax_rates` rows são **derivadas** automaticamente das `nfe_fiscal_rules` (1 row por NCM × UF, com `name = "ICMS NCM-22021000-SP-RJ"`, `amount = ICMS+ICMS-ST+IPI+PIS+COFINS combined`)
   - Connector + Accounting + outros lêem `tax_rates` normalmente (zero mudança)
   - Edição direta em `/taxes` é bloqueada (read-only com link pro motor)

2. **Quando NfeBrasil INATIVO no business:**
   - `/taxes` funciona como upstream original (CRUD livre)
   - `tax_rates` é fonte de verdade (sem motor brasileiro)
   - Tenant aceita simplificação (mercado simples/não-BR ou sem fiscal)

3. **Listener bridge** `Modules\NfeBrasil\Listeners\SyncFiscalRuleToTaxRate`:
   ```php
   // Escuta: FiscalRuleCreated, FiscalRuleUpdated, FiscalRuleDeleted
   // Ação: upsert/delete em tax_rates com naming convention
   //   name = "[NfeBrasil] NCM {ncm} {uf_origem}->{uf_destino}"
   //   amount = soma efetiva calculada (sem ICMS-ST se interno; etc.)
   //   metadata.source = 'nfe_fiscal_rule'
   //   metadata.fiscal_rule_id = $rule->id
   ```

4. **Marker em tax_rates** pra UI saber:
   ```sql
   ALTER TABLE tax_rates ADD COLUMN source ENUM('manual', 'nfe_fiscal_rule') DEFAULT 'manual';
   ALTER TABLE tax_rates ADD COLUMN source_ref_id BIGINT NULL;
   ```

## Consequências

**Positivas:**
- Connector / Accounting / Stock continuam consumindo `tax_rates` sem mudança
- Tenant que ativa NfeBrasil tem motor brasileiro completo
- Zero merge conflict no upgrade Laravel (não toca core)
- Bridge é one-way (motor → tax_rates) — sem ciclo
- Tenant pode desativar NfeBrasil e voltar pro modelo simples (rows derivadas viram "frozen" com source mantido)

**Negativas:**
- 2 lugares conceituais pra "ver" tributos (mitigar: banner em `/taxes` + link claro)
- Tax_rates derivadas têm naming auto que pode ser feio (`[NfeBrasil] NCM 22021000 SP->RJ`) — UI esconde
- Sync overhead — cada FiscalRule mutation regenera 1-N tax_rate rows
- Conflito raro: tenant cadastra `tax_rate` manual com mesmo nome auto-gerado → bloquear ou prefix forçado

## Por que a convivência funciona (depende do cascade ARQ-0006)

A bridge **só é viável** porque o cascade em prioridade (ARQ-0006) **limita o que vai pra `tax_rates`**:

| Nível cascade | Quantas regras típico | Replica em tax_rates? |
|---|---:|---|
| **Nível 4** (default business) | 1 | ❌ Não — fallback dinâmico no motor |
| **Nível 3** (regra NCM padrão) | ~10 | ✅ Sim — 1 linha por (NCM, UF origem) |
| **Nível 2** (regra exata) | ~3 | ✅ Sim — 1 linha por (NCM, UF origem, UF destino) |
| **Nível 1** (override produto) | ~2 | ✅ Sim — vinculada via `products.fiscal_rule_override_id` |

**Total típico em `tax_rates` derivadas:** ~15 linhas. Manejável no UI core, sem inflar dropdown.

Se o cascade não existisse e tenant precisasse criar regra granular pra cada combinação `(ncm × uf_origem × uf_destino)`, `tax_rates` viraria ingovernável (36k+ linhas). **A escolha por prioridade é o que torna possível a coexistência sem que `tax_rates` exploda.**

## Pattern obrigatório

```php
namespace Modules\NfeBrasil\Listeners;

class SyncFiscalRuleToTaxRate implements ShouldQueue {
    public string $queue = 'nfe_bridge';
    public int $tries = 3;

    public function handle(FiscalRuleCreated|FiscalRuleUpdated $event): void {
        $rule = $event->rule;
        $business = $rule->business;

        if (! $business->nfe_ativo) return;  // tenant desativou

        $effective = (new MotorTributarioService)->calcularEfetivo($rule);

        TaxRate::updateOrCreate(
            [
                'business_id' => $rule->business_id,
                'source' => 'nfe_fiscal_rule',
                'source_ref_id' => $rule->id,
            ],
            [
                'name' => sprintf('[NfeBrasil] NCM %s %s->%s', $rule->ncm, $rule->uf_origem, $rule->uf_destino ?? 'all'),
                'amount' => $effective->aliquotaTotal,
                'is_tax_group' => false,
            ]
        );
    }
}

class SyncFiscalRuleDeleted implements ShouldQueue {
    public function handle(FiscalRuleDeleted $event): void {
        TaxRate::where([
            'source' => 'nfe_fiscal_rule',
            'source_ref_id' => $event->rule->id,
        ])->delete();
    }
}
```

## UI changes em `/taxes` (core upstream)

Quando NfeBrasil ativo:
- Banner top: "🇧🇷 Configuração Fiscal Avançada disponível → [Acessar /nfe-brasil/tributacao]"
- Tabela `tax_rates`: linhas com `source='nfe_fiscal_rule'` ganham badge "Auto" + ícone read-only (não permite edit/delete via UI)
- Botão "Adicionar" continua existindo pra `source='manual'` (uso edge: imposto que não passa pelo motor — taxa de serviço, etc.)

## Tests obrigatórios

- `BridgeFiscalRuleCreatedSyncsTaxRateTest` — criar fiscal_rule → 1 tax_rate auto criada
- `BridgeFiscalRuleDeletedRemovesAutoTaxRateTest` — delete fiscal_rule → tax_rate auto sumida
- `BridgeManualTaxRateNaoAfetadaTest` — manual rows preservadas em sync
- `BridgeNfeInativoNaoSyncTest` — NfeBrasil desativado → mutations não criam tax_rate auto

## Decisões em aberto

- [ ] `tax_rates.amount` derivada: somar tudo (ICMS+IPI+PIS+COFINS) ou só ICMS principal? Provavelmente "carga tributária efetiva" (motor calcula)
- [ ] Quando tenant desativa NfeBrasil: deletar rows auto ou manter como manual? Provável "manter, mudar source pra manual"
- [ ] Multi-CNPJ por business: tax_rates é por business, fiscal_rules é por (business, ncm, uf) — funciona?

## Alternativas consideradas

- **Convive paralelo (sem bridge)** — rejeitado: tenant precisa configurar 2x; 2 fontes de verdade
- **Substituir tax_rates** — rejeitado: quebra Connector/upstream; merge conflict iminente
- **Plugar motor em hooks de tax_rate** (sem bridge) — rejeitado: tax_rate é simples demais pra carregar metadata fiscal completo

## Referências

- ARQ-0001 (módulo isolado)
- ARQ-0004 (schema flexível CBS/IBS)
- US-NFE-010 (cadastro regra tributária)
- `Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md` (mesmo padrão "paralelo + bridge opt-in")
- `app/TaxRate.php` + `database/migrations/2017_07_26_110000_create_tax_rates_table.php`
