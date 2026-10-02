# _DECISOES-W-2026-10-02b — Vendas (Code → Cowork)

> **Estatuto:** pedido de edição do `00-INDICE.md` DO COWORK. O Code não edita o espelho.
> **Fonte:** [W] 2026-10-02 (2ª rodada), resposta textual à D3 depois que a thread 05 parou nela (`_saida-05.md`, seção "D3 — PARADO") e devolveu as opções.
> **Precedência:** onde esta rodada contradiz a 1ª (`_DECISOES-W-2026-10-02.md`, PR #8513), **vale esta**.

| id | resposta [W] (textual) | efeito | revoga |
|---|---|---|---|
| D3 | *"opção 2 no D3"* | o reverter de lote importado **continua apagando** as vendas, endurecido: (1) antes de apagar grava no log um retrato completo do lote (por venda: id, fatura, business, contato por id, total, linhas, pagamentos e o que volta ao estoque); (2) **tudo ou nada** — se qualquer venda do lote não pode ser apagada (hoje: já tem devolução), nada é apagado e a resposta diz quais vendas impediram e por quê. O resto do apagar (estoque, custeio FIFO, pagamentos, título no Financeiro) é o mesmo `deleteSale()` de hoje. | 1ª rodada D3 "cancelar sem apagar" |

## Por que a 1ª resposta foi revogada (medido na thread 05)

Não existe hoje caminho de **cancelar** venda sem efeito externo:

- o cancelamento canônico (FSM `cancelar_venda` → `CancelarVendaCascade`) cancela NF-e na SEFAZ, estorna gateway e avisa por WhatsApp, **não devolve** o estoque baixado e não muda `status` — a venda segue nos totais;
- um `status = 'cancelled'` novo exigiria revisar **93** consultas `DB::table('transactions')` cruas, além das que filtram só `status != 'draft'` (saldo e LTV do cliente);
- `transactions` não tem `deleted_at`.

## Implementação e pendência

- PR do reverter tudo-ou-nada: branch `claude/vendas-thread-05-reverter-tudo-ou-nada`, empilhado sobre o backend da thread 05 (#8516). Testes `tests/Feature/Sells/ImportSalesReverterContratoTest.php`, **UC-IMPREV-01..04**.
- **Pendência para o `_saida-05.md`** (ele nasce no PR da tela, #8520, e por isso não é editado aqui — editar daqui criaria o mesmo arquivo em dois PRs): quando #8516, #8520 e este estiverem no main, o `ImportSales/Index.casos.md` precisa ganhar o UC do tudo-ou-nada (UC-IMPREV-02: lote com venda devolvida é recusado inteiro e a resposta diz qual venda) e o `[BACKLOG]` UC-IMP-05 ("lote some da lista") deixa de depender da D3 — passa a ser o UC-IMPREV-01.

## Edição pedida no json
```json
[{ "id": "D3", "respondida": true, "resposta": "opção 2: reverter segue apagando, com retrato no log antes e tudo-ou-nada (venda impedida recusa o lote inteiro)" }]
```
