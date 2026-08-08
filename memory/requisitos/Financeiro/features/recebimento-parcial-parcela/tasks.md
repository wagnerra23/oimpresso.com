---
id: requisitos-financeiro-features-recebimento-parcial-parcela-tasks
feature: recebimento-parcial-parcela
module: Financeiro
---

# Tasks — recebimento parcial de parcela de cliente

> **Estado de workflow (todo/doing/done) vive no MCP** (`tasks-create ... parent_plan:"financeiro-recebimento-parcial-parcela"`,
> ADR 0070). Este arquivo é o plano versionado: ordem, dependências e DoD. Executar em ordem
> topológica de `blocked_by:`; nenhuma task autoriza escrita financeira em produção.

### T-01 · Congelar o contrato de valor com testes que falham
> blocked_by: — · covers: AC-1, AC-2, AC-3, AC-4, AC-5, AC-6 · us: US-FIN-003 · estimate: 1h

Estender `BaixaConservacaoValorContratoTest.php` e criar `BaixaManualLedgerContratoTest.php` com o
exemplo antes→depois, pré-condição anti-vácuo, baixa+caixa e retry idempotente. Não alterar runtime.

**DoD:** na lane MySQL do CT 100, os novos cenários falham pelas lacunas esperadas e continuam provando
que os UC-FUNI-01..04 existentes não regrediram; o PR mostra a tabela antes→depois e duas somas independentes.

### T-02 · Tornar a entrada HTTP validada e idempotente
> blocked_by: T-01 · covers: AC-4, AC-5, AC-7 · us: US-FIN-003 · estimate: 45min

Reutilizar `StoreBaixaRequest` na rota `{id}`, ajustar validação/ownership sem aceitar `business_id` e
fazer o `FinBaixaSheet` manter um UUID estável até sucesso ou cancelamento explícito.

**DoD:** teste envia o mesmo payload duas vezes com a mesma chave e demonstra que o request validado
chega idêntico ao caso de uso; charter/casos/PRE-MERGE-UI ficam atualizados se o `.tsx` for tocado.

### T-03 · Extrair a transação para BaixaService
> blocked_by: T-01, T-02 · covers: AC-1, AC-2, AC-3, AC-5, AC-6 · us: US-FIN-003 · estimate: 1h30

Criar `BaixaService::registrar()` com `businessId` explícito, locks, split/quitação, deduplicação e as
três escritas atômicas: título, baixa e movimento de caixa. Nenhuma leitura de `session()` no serviço.

**DoD:** os testes de T-01 passam no CT 100; falha injetada entre baixa e caixa deixa contagens e valores
iguais ao antes; PHPStan do módulo não introduz erro novo.

### T-04 · Afinar o controller e convergir o pagamento automático
> blocked_by: T-03 · covers: AC-2, AC-4, AC-7 · us: US-FIN-003 · estimate: 1h

Substituir a lógica inline de `UnificadoController::baixar` por chamada ao `BaixaService`. Depois de prova
de paridade, fazer `TituloAutoService::registrarPagamento` reutilizar o núcleo contábil sem perder
`transaction_payment_id`, conta opcional da ADR 0175 ou semântica de compra versus venda.

**DoD:** teste de paridade confirma que baixa manual e `TransactionPayment` produzem baixa+caixa uma única
vez; `UnificadoController::baixar` não contém `DB::transaction`, `TituloBaixa::create` ou geração de número.

### T-05 · Publicar evento e fechar a rastreabilidade
> blocked_by: T-04 · covers: AC-2, AC-7 · us: US-FIN-003 · estimate: 45min

Criar `TituloBaixado` after-commit com payload mínimo, registrar listener somente se houver consumidor real
e estender `Index.casos.md` com os casos de baixa+caixa e retry. Não duplicar casos em outra pasta.

**DoD:** teste prova que o evento não é publicado em rollback e é publicado uma vez após commit; cada novo
UC cita o teste correspondente e o casos-gate não acusa caso órfão.

### T-06 · Fechar o loop com gates e smoke real
> blocked_by: T-05 · covers: AC-1, AC-2, AC-3, AC-4, AC-5, AC-6, AC-7 · us: US-FIN-003 · estimate: 30min

Executar a lane Financeiro no CT 100, revisar a tabela antes→depois com aprovação [W], fazer smoke real de
R$ 300,00 → R$ 120,00 e atualizar a âncora da US somente com evidência verificável.

**DoD:** `node scripts/governance/feature-lint.mjs Financeiro/recebimento-parcial-parcela --check` passa;
`node scripts/governance/anchor-lint.mjs memory/requisitos/Financeiro/SPEC.md` retorna `anchored_ok`;
CI requerida fica verde e o PR contém ids/valores redigidos do smoke, o SHA e a confirmação dos dois caminhos.
