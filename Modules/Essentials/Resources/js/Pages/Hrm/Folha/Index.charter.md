---
id: resources-js-pages-hrm-folha-index-charter
page: /hrm/payroll
component: Modules/Essentials/Resources/js/Pages/Hrm/Folha/Index.tsx
related_prototype: n/a (herda PT-01 Lista + PT-05 na aba Custo; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-09-04"
parent_module: Essentials
related_adrs: [93, 104, 114, 264]
tier: B
charter_version: 1
---

# Page Charter — /hrm/payroll · Folha de pagamento (HRM) (DRAFT)

> **Status:** draft. A `.tsx` **ainda não existe** — este charter aterrissa no PR-1 do
> [`PEDIDO-CL-hrm.md`](../../../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)
> (onda HRM-O5) e a Page vem no PR-9. **Bloqueado por D2** do HRM-O0 (folha gerencial × título
> no Financeiro).
>
> ⚠️ **Limite de proveniência declarado:** as regras F1-F8 abaixo vêm do `PayrollController`
> lido **no espelho local** do Cowork, **não** no `main`. O arquivo tem ~60 KB e nem o pedido
> (21/ago) nem o export (04/set) o leram no `main` — os dois declaram esse buraco. Tratar F1-F8
> como **hipótese datada a confirmar no PR-9**, não como fato medido.
>
> **Sobre `related_prototype`:** o build F1 é `hrm-extras.jsx` (`Folha`) + `hrm-forms.jsx`
> (`FormFolha`, `FormPagamento`); o hub se declara porte do `nav_hrm.blade` (lápide §5
> 2026-08-28). A âncora declarada é o Padrão de Tela.

---

## Mission
Gera contracheques do mês por localidade, agrupa em lote (`essentials_payroll_groups`), fecha o
lote, lança pagamento por colaborador e mostra o custo por competência, setor e composição de
ganhos.

---

## Goals — Features (faz)
- Gera contracheque por colaborador/competência e agrupa em lote.
- Fecha lote (`draft` → `final`) e lança pagamento por colaborador.
- Mostra custo por competência, setor e composição de ganhos.

---

## Non-Goals — Features (NÃO faz)
- ❌ **Não calcula encargo nenhum**: sem INSS, IRRF, FGTS, 13º, férias proporcionais, rescisão,
  eSocial ou guia. Soma ganhos, subtrai deduções e grava despesa (`Transaction type=payroll`).
- ❌ Não recalcula contracheque existente (F8).
- ❌ Não é fonte de jornada legal — a hora vem do Essentials hoje; **D1** decide se passa a vir
  do Ponto WR2.

---

## Regras de domínio (hipótese — ver limite de proveniência acima)
- **F1** Um contracheque por colaborador por competência: se já existe, o backend recusa.
- **F2** Lote nasce `draft` e vira `final`; **só `draft` pode ser excluído** (excluir apaga os
  contracheques do lote).
- **F3** Fechar com "notificar" dispara `PayrollNotification` a cada colaborador do lote.
- **F4** Situação de pagamento do lote é **derivada** dos itens: todos pagos = `paid`, nenhum =
  `due`, misto = `partial`.
- **F5** Ganhos calculados na geração: comissão de venda (percentual do colaborador × faturado,
  tipo de cálculo do `pos_settings`) e comissão de meta (faixa atingida; base sem imposto se a
  configuração estiver ligada).
- **F6** Ganhos e deduções recorrentes entram por valor fixo ou percentual do salário.
- **F7** Prefixo da referência vem de `essentials_settings.payroll_ref_no_prefix`.
- **F8** Nada é recalculado depois da geração — mudar salário, meta ou marcação não altera
  contracheque existente.

---

## Anti-hooks
- ⛔ Não rotular a tela como "folha de pagamento" sem o qualificador **gerencial** enquanto o
  Financeiro estiver ao lado: prometer guia que o sistema não calcula é o risco que **D2** trata.
- ⛔ **Regra mestre de VALOR (Tier 0 · [proibicoes.md](../../../../../../../memory/proibicoes.md)):**
  esta tela grava `Transaction` e mexe em dinheiro. Qualquer PR que altere cálculo aqui exige
  dupla prova por caminhos independentes + tabela antes→depois + aprovação [W] — antes de mergear.
- ⛔ Não escrever UC sobre F1-F8 antes de ler o `PayrollController` no `main`: são hipótese
  herdada do espelho, e UC derivado de fonte não confirmada é a §5 2026-07-15.

---

## Pendências antes de `status: live`
1. **D2** (gerencial × título no Financeiro) · 2. **D1** (a hora vem do Essentials ou do Ponto?)
3. Ler `PayrollController` no `main` e confirmar F1-F8 · 4. Rótulo "folha gerencial" na UI
5. PR-9 cria a `Index.tsx`.
