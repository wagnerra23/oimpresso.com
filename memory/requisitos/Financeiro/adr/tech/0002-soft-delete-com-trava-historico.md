# ADR TECH-0002 (Financeiro) · Soft delete com trava de integridade histórica

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: ARQ-0001, TECH-0001, `auto-memória: cliente_rotalivre.md` (Larissa decora horários históricos)

## Contexto

Tenant historicamente quer "deletar" entidade financeira que não usa mais:
- Conta bancária antiga fechada
- Categoria criada por engano
- Plano de contas que não bate com o do contador novo
- Cliente desativado

Hard delete em Financeiro **quebra histórico**:
- Conta bancária com 5 anos de movimentação → DELETE quebra Razão histórico
- Plano de contas deletado → DRE histórico fica órfão
- Categoria deletada → relatório por categoria do ano passado vira "categoria-removida"

Larissa-ROTA-LIVRE caso real: quase deletou conta antiga porque "não usa mais" — DRE de 2025 sumiu (`auto-memória: cliente_rotalivre.md`).

## Decisão

**Toda entidade financeira é soft-deleted, com trava extra: bloqueia delete se há histórico vinculado.**

```php
class ContaBancaria extends Model {
    use SoftDeletes;

    public function delete()
    {
        if ($this->caixa_movimentos()->exists()) {
            throw new BloqueadoComHistoricoException(
                'Conta com movimentos não pode ser removida. Inative em vez disso.'
            );
        }
        return parent::delete();
    }
}
```

Tabelas onde isso vale:
- `fin_contas_bancarias` (bloqueia se tem `caixa_movimentos`)
- `fin_planos_conta` (bloqueia se tem `titulo` ou `caixa_movimento` apontando)
- `fin_categorias` (bloqueia se tem `titulo` apontando — ou substitui em massa)
- `fin_titulos` (NUNCA hard delete; só status='cancelado')
- `fin_titulo_baixas` (NUNCA delete — usar evento `EstornoBaixa` que cria nova row negativa)

Status alternativo: `inativo`. Aparece em relatório histórico, mas filtrado fora de selects de novo lançamento.

## Consequências

**Positivas:**
- Razão histórico nunca quebra
- DRE de 5 anos atrás continua reproduzível
- Auditor SEFAZ + contador veem dado consistente
- Tenant pode "limpar UI" com inativar (sem perder dado)
- Compliance fiscal BR: legislação exige guardar 5 anos (`auto-memória: reference_ultimatepos_integracao.md`)

**Negativas:**
- UI tem 2 estados (ativo/inativo) que tenant precisa entender — UX precisa explicar bem
- Hard delete não é opção, mesmo a pedido: força conversa com cliente "esses dados não podem sumir, posso inativar?"
- Espaço em disco cresce — particionamento por ano em `fin_caixa_movimentos` quando passar 10M rows (futuro)

## Pattern obrigatório

1. **Trait** `Modules\Financeiro\Models\Concerns\HardDeleteBlockedByHistory` que verifica relations definidas em `$blocksHardDeleteIfPresent = ['caixa_movimentos', ...]`
2. **Mensagem clara em PT** na exception (vai pro toast `sonner`)
3. **Tela** sempre tem botão **Inativar** (não **Excluir**)
4. **Exception** `BloqueadoComHistoricoException` retorna 422 + JSON `{message, related_count}`

## Política de "estorno"

Onde delete é semanticamente correto (corrigir baixa errada), usar **estorno**:
- Cria nova `fin_titulo_baixas` com `valor_baixa = -original.valor_baixa` + `estorno_de_id = original.id`
- `fin_caixa_movimentos` ganha entrada negativa correspondente
- Original NÃO é tocada (audit log)

Pattern análogo a `Marcacao::anular()` em PontoWr2 (`PontoWr2/adr/arq/0001-marcacoes-append-only-por-forca-de-lei.md`).

## Alternativas consideradas

- **Hard delete livre** — rejeitado: quebra histórico, falha compliance
- **Soft delete sem trava** — rejeitado: confusão "deletei mas o que aconteceu com o relatório?"
- **Lock total (sem inativar)** — rejeitado: UI vira poluída com dados antigos
- **Versionamento (slowly changing dimension)** — rejeitado: overkill; basta soft delete + status

## Referências

- `auto-memória: cliente_rotalivre.md` — Larissa quase deletou conta histórica
- `PontoWr2/adr/arq/0001-marcacoes-append-only-por-forca-de-lei.md` — mesmo princípio com regulação MTP
- `auto-memória: feedback_carbon_timezone_bug.md` — exemplo de "fix in-place quebra histórico"; aprendeu a separar API
