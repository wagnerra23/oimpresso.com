---
id: requisitos-estoque-spec
module: Estoque
title: "SPEC — Estoque (cross-cutting: saldo, reserva, movimentação)"
status: ativo
version: "1.0"
last_updated: "2026-06-04"
owner: wagner
owners: [W]
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0129-state-machine-canonica-fsm-rbac"
  - "0192-auto-faturar-os-venda-jobsheet-observer"
doc_raiz: ./DOC-RAIZ-ESTOQUE.md
---

# SPEC — Estoque

> Módulo **cross-cutting** (não é um `Modules/<X>`): governa o saldo de estoque
> (`variation_location_details.qty_available`), reserva (FSM `stock_reservations`) e toda
> movimentação (compra/venda/devolução/transferência/ajuste/opening/OS). A arquitetura,
> invariantes e riscos vivem no **[DOC-RAIZ-ESTOQUE.md](./DOC-RAIZ-ESTOQUE.md)** — leitura
> obrigatória antes de qualquer US deste SPEC.

## Invariantes (do doc raiz §7)

- INV-1 saldo só por caminho auditável · INV-2 rascunho não movimenta · INV-3 tudo em transação ·
  INV-4 reserva ≠ baixa · INV-5 `enable_stock=0` não movimenta · INV-6 tenant fixado por IDs globais.

## User Stories

<!-- US-ESTOQUE-* criadas via MCP tasks-create -->

### US-ESTOQUE-002 · Contagem de inventário: abrir, contar com coletor, ver divergência e aprovar o ajuste

> owner: wagner · priority: p0 · estimate: 24h · status: doing · type: story · origin: lacunas-concorrencia-2026-10-25
> blocked_by: —

**Implementado em:** _pendente_ — parte 1 feita (abrir, contar, fechar e divergência em `App\Services\Estoque\ContagemInventarioService`, prova em `tests/Feature/Estoque/ContagemInventarioContratoTest.php`); faltam aprovar o ajuste e a tela com coletor.

Lacuna P0 do levantamento de 2026-10-25: `CatalogoProcessos.php` (processo `inventario`) dizia que não existe contagem, nem tabela nem rota, e que o ajuste de estoque só tira e não vem de contagem.

Aceite:

- **Abrir:** escolhe o local; só uma contagem aberta por local de cada vez.
- **Contar:** o coletor lê o SKU da variação (ou o do produto, quando ele tem uma só) e soma 1 a cada leitura; digitar a quantidade substitui. Produto sem controle de estoque é recusado. Quantidade negativa é recusada.
- **Saldo congelado por item:** na primeira contagem do item o sistema guarda o saldo daquele local. A divergência é `contado − saldo guardado`; venda feita depois de contar a prateleira não vira falta falsa. A loja não para durante a contagem.
- **Fechar:** depois de fechada, ninguém conta mais. A tela mostra falta e sobra por item, com o valor pela custo de compra.
- **Aprovar ajuste (parte 2):** só quem tem a permissão de aprovar inventário. Aplica a diferença sobre o saldo do momento da aprovação, tirando a falta e lançando a sobra, com um movimento auditável por contagem. Regra mestre de estoque: antes→depois apresentado ao [W] e dupla prova antes do merge.
- **Tela (parte 3):** abrir, contar pelo coletor, ver a divergência e aprovar.
- **Tier 0:** contagem, local e produto sempre da mesma empresa; outra empresa não vê, não conta e não fecha.
- **Fora desta US:** zerar itens não contados (decidir se a contagem é total ou por amostra), custo médio (outra US).

### US-ESTOQUE-001 · Toda transferência cai pendente na requisição e só move o estoque quando aprovada

> owner: wagner · priority: p2 · status: todo · type: story · origin: decisao-felipe-2026-10-15
> blocked_by: —

**Implementado em:** _pendente_ — não iniciada. Hoje quem preenche a transferência escolhe a situação (pendente, em trânsito ou concluída) num campo do formulário, e a concluída já move o saldo; não existe a tela da requisição.

Decisões de [F] em 2026-10-15. A tela da requisição é do [W].

- Toda transferência feita em /stock-transfers/create nasce **pendente** na tela da requisição e não mexe no estoque. **Não existe baixa automática** — não há configuração da empresa para pular a requisição.
- Na requisição, quem tem a permissão de aprovar decide: **aprovar** executa a transferência (o saldo sai da origem e entra no destino); **recusar** deixa a situação nova "recusada", que fica no histórico (não exclui).
- Responsável = permissão (ex.: aprovar transferência), não pessoa fixa.
- O campo situação sai do formulário de transferência.
- Depois disso: o "Transferir" do produto (painel e menu da linha da lista de produtos) abre a tela de transferência com produto e local de origem preenchidos.
- Estoque Tier 0: dupla confirmação do saldo (só muda na aprovação, nunca duas vezes) e antes→depois apresentado antes do merge.
- Detalhe: [`StockTransfer/Create.casos.md`](../../../resources/js/Pages/StockTransfer/Create.casos.md) e [`Index.casos.md`](../../../resources/js/Pages/StockTransfer/Index.casos.md), §Backlog.

### US-ESTOQUE-003 · Sugestão de reposição e curva ABC por produto

> owner: wagner · priority: p2 · estimate: 12h · type: story · origin: lacunas-concorrencia-2026-10-25
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/Compras/Services/CurvaAbcReposicao.php` · `config/compras.php` — (2026-10-27): o cálculo, atrás da flag `COMPRAS_CURVA_ABC_REPOSICAO` (desligada). Falta a tela.

**Testado em:** `Modules/Compras/Tests/Feature/CurvaAbcReposicaoTest.php` (lane `compras-pest.yml`, UC-ABC-01..05).

Hoje existe alerta de estoque baixo e requisição por estoque mínimo (`RequisicaoEstoqueMinimoService`). Faltam a curva ABC (por faturamento e por margem, no período) e a sugestão de quanto comprar pelo giro e pelo prazo do fornecedor. Só leitura: não muda valor nem estoque.

Regras do cálculo (2026-10-27), na empresa inteira, período padrão de 90 dias:

- Vendido e faturamento das vendas finalizadas do período (quantidade − devolvida, preço com imposto).
- Margem = faturamento − custo do lote da venda (o mesmo do relatório de lucro). Venda sem lote amarrado deixa a margem do item nula.
- Curva por faturamento e, à parte, por margem: A enquanto o acumulado antes do item é menor que 80%, B até 95%, C o resto. Margem nula ou negativa é C.
- Sugestão = giro por dia × (prazo do fornecedor da última compra + dias de cobertura) − estoque − a caminho, para cima, nunca negativa. Sem prazo cadastrado, prazo 0.
- Não duplica o MRP (US-COM-030): o MRP olha reservas das OS e estoque mínimo por local; aqui é a velocidade de venda.

**Aceite:**
- [x] Curva ABC por faturamento e por margem no período (UC-ABC-01, UC-ABC-02).
- [x] Sugestão de compra pelo giro, prazo do fornecedor e o que está a caminho (UC-ABC-03).
- [x] Tier 0: venda e estoque de outra empresa não entram (UC-ABC-04). Só leitura (UC-ABC-05).
- [ ] Tela com a curva e a sugestão, e o botão que leva a sugestão para a requisição de compra.
