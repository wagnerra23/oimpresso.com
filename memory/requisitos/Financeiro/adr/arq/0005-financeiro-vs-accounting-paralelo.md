# ADR ARQ-0005 (Financeiro) · Financeiro paralelo a Accounting (não substitui, não estende)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, `Modules/Accounting/` (módulo upstream existente)

## Contexto

UltimatePOS upstream já tem o **módulo `Accounting`**, ativo em produção (`Modules/Accounting/`):

- 12 controllers (Chart of Accounts, Journal Entry, Trial Balance, Balance Sheet, Income Statement, Budget...)
- Tabelas `accounting_*` (não `fin_*`): `accounting_accounts`, `accounting_account_transactions`, `accounting_budget`, `accounting_journal_entries`, `accounting_acc_trans_mappings`
- Bridge com `transactions` core via `accounting_acc_trans_mappings` (mapeia venda/compra → conta contábil)
- DataController registrado: menu, permissões, license Superadmin
- **Foco:** contabilidade formal (partida dobrada), DRE auditável, plano de contas, fechamento contábil

Pergunta: o módulo `Financeiro` que estou criando **substitui** Accounting? Estende? Ignora?

## Análise comparativa

| Aspecto | Accounting (existente) | Financeiro (novo) |
|---|---|---|
| **Escopo** | Contabilidade formal | Operacional (caixa, contas a pagar/receber) |
| **Persona** | Contador (terceiro), auditor | Larissa-financeiro (interna) |
| **Modelo conceitual** | Partida dobrada (débito × crédito) | Título → Baixa → Movimento de caixa |
| **Tabela base** | `accounting_account_transactions` | `fin_titulos`, `fin_titulo_baixas` |
| **Origem dos dados** | Mapeamento de `transactions` core via `acc_trans_mappings` | Observer em `Transaction` cria título com `origem` rastreável |
| **Granularidade** | Lançamento contábil (1 venda → N journal entries) | Título (1 venda → 1 título recebível, com N parcelas opcionais) |
| **Output principal** | Balanço, DRE formal, Razão | Dashboard caixa, aging, fluxo projetado |
| **Compliance alvo** | SPED Contábil, CRC | Compliance gerencial interno |
| **Adoção esperada** | < 5% dos tenants (PME pequena raramente faz contabilidade formal) | > 80% dos tenants (todo mundo precisa "saber quem deve") |

**Problema:** sobrepor 100% = código duplicado, confusão de tabela autoritária, manutenção dupla.

## Decisão

**Manter os dois módulos paralelos. Financeiro NÃO estende Accounting nem é estendido por ele.**

Princípios:
1. **`fin_titulos`** é fonte autoritária do operacional (quem deve, quando, quanto)
2. **`accounting_account_transactions`** é fonte autoritária do contábil (débito/crédito formal)
3. **Cada um aponta pra `transactions` core** independentemente — não há FK direto entre `fin_*` e `accounting_*`
4. **Bridge futuro opt-in** (não obrigatório no MVP): listener `SyncFinanceiroToAccountingJob` consome eventos `TituloBaixado` e cria journal entries equivalentes em `accounting_*` quando tenant tem `accounting_sync_enabled=true`

## Consequências

**Positivas:**
- Tenant pequeno (90%) usa só Financeiro — UI simples, sem aprender débito/crédito
- Tenant que precisa SPED Contábil ativa Accounting + bridge automática (Pro/Enterprise)
- Ambos podem ser desabilitados independentemente (`module:disable Financeiro` não afeta Accounting)
- Sem coupling — refatorar Accounting (legado upstream) não quebra Financeiro
- Permite preço dual: Financeiro Pro R$ 199 / Accounting add-on R$ 99 quando aplicável

**Negativas:**
- Dois módulos contabilizando "a mesma coisa" pode confundir tenant que ativa ambos
  - **Mitigação:** UI explicita "Financeiro = caixa do dia-a-dia / Accounting = livros formais"
- Bridge `Financeiro → Accounting` é trabalho extra (não MVP; Onda 5+)
- Dois lugares pra debug em incidente de "número não bate"

## Pattern de bridge (futuro, não MVP)

```php
namespace Modules\Financeiro\Listeners;

class SyncTituloBaixaToAccounting implements ShouldQueue {
    public string $queue = 'financeiro_accounting_bridge';

    public function handle(TituloBaixado $event): void {
        $business = $event->titulo->business;
        if (! $business->accounting_sync_enabled) return;

        // Mapeia: baixa de fin_titulo (origem=venda) →
        //   journal entry: D Caixa, C Receita
        AccountingJournalEntry::create([/* ... */]);
    }
}
```

Habilitação por business via `businesses.metadata.accounting_sync_enabled = true`.

## Quando reavaliar

- Se > 30% dos tenants ativarem Accounting bridge → considerar consolidação
- Se Accounting upstream virar deprecated (raro mas possível) → migrar pra Financeiro estendido
- Se um cliente exigir "DRE formal" no Financeiro MVP → criar US específica e bridge mais cedo

## Alternativas consideradas

- **Substituir Accounting completamente** — rejeitado: quebra clientes que já usam (poucos mas alguns); upstream pode atualizar e gerar conflito de merge
- **Estender Accounting com `fin_*` views** — rejeitado: copla os módulos; perde flexibilidade
- **Apenas Accounting (sem Financeiro)** — rejeitado: tenant médio não entende partida dobrada; UX ruim
- **Apenas Financeiro (desabilitar Accounting)** — rejeitado: perde compliance pra tenant que precisa SPED Contábil

## Referências

- `Modules/Accounting/Providers/AccountingServiceProvider.php` — exemplo do pattern já em produção
- `Financeiro/ARCHITECTURE.md §5` — integração com core via observer
- ARQ-0001 (módulo isolado nwidart)
- `auto-memória: reference_ultimatepos_integracao.md` — pattern hooks
