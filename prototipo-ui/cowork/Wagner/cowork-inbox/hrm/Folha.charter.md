# Folha de pagamento (HRM) — charter

- **tela:** `/hrm/payroll` (lotes, contracheques, ganhos e deduções, custo)
- **related_prototype:** PT-01 + PT-05 (aba Custo)
- **build F1:** `hrm-extras.jsx` (`Folha`), `hrm-forms.jsx` (`FormFolha`, `FormPagamento`)
- **fonte:** `PayrollController` (lido no espelho local), `EssentialsAllowanceAndDeductionController`, `payroll/*.blade.php`
- **status:** draft · aguarda [W] em D2

## O que a tela faz
Gera contracheques do mês por localidade, agrupa em lote (`essentials_payroll_groups`), fecha o lote, lança pagamento por colaborador e mostra o custo por competência, setor e composição de ganhos.

## O que NÃO faz
**Não calcula encargo nenhum**: sem INSS, IRRF, FGTS, 13º, férias proporcionais, rescisão, eSocial ou guia. Soma ganhos, subtrai deduções e grava despesa (`Transaction type=payroll`).

## Regras
- **F1** Um contracheque por colaborador por competência: se já existe, o backend recusa com "folha já adicionada para determinados usuários".
- **F2** Lote nasce `draft` e vira `final`; **só `draft` pode ser excluído** (excluir apaga os contracheques do lote).
- **F3** Fechar com "notificar" dispara `PayrollNotification` a cada colaborador do lote.
- **F4** Situação de pagamento do lote é **derivada** dos itens: todos pagos = `paid`, nenhum = `due`, misto = `partial`.
- **F5** Ganhos calculados na geração: comissão de venda (percentual do colaborador × faturado, tipo de cálculo vem do `pos_settings`) e comissão de meta (faixa atingida; base sem imposto se a configuração estiver ligada).
- **F6** Ganhos e deduções recorrentes entram por valor fixo ou percentual do salário.
- **F7** Prefixo da referência vem de `essentials_settings.payroll_ref_no_prefix`.
- **F8** Nada é recalculado depois da geração — mudar salário, meta ou marcação não altera contracheque existente.

## Pendências antes de status: live
D2 (gerencial × título no Financeiro) · rótulo "folha gerencial" na UI · decidir se a hora vem do Essentials ou do Ponto (D1).
