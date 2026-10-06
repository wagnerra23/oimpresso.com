---
sessao: "02"
titulo: Relatório de comissão por vendedor → Inertia
dono: "[CL]"
base: 23c3f080aa94
prefixo: app/Http/Controllers/ReportController.php (só o render) · resources/js/Pages/Report/SalesRepresentative/ (trio)
nao_toca: as queries de cálculo (getSalesRepresentativeTotal*) · Modules/
depende: A1 · D-COM-2
---
# 02 · Relatório de comissão por vendedor

Troca **só a tela**: as 4 funções `getSalesRepresentative*` (ReportController :1221–1313) seguem sendo a fonte. A Page chama os mesmos endpoints. **Lei:** o número da tela nova = o número do Blade para o mesmo filtro.

## Casos de uso
### UC-COM-03 · Mesmo filtro, mesmo total que o relatório antigo · `must` `[T0]`
- **Aceite:** Dado vendedor, período e local · Então total de vendas, despesas e comissão na Page = respostas JSON de `getSalesRepresentativeTotal{Sell,Expense,Commission}`. Controle positivo: trocar o vendedor muda os três.
- **Teste:** `SalesRepresentativeReportPageTest` — `UC-COM-03`
- **Contrato:** ReportController :1244–1313
- **Regressão que defende:** tela nova com número diferente da planilha da Eliana.

### UC-COM-04 · Vendedor só vê a própria comissão · `must` `[T0]`
- **Aceite:** Dado usuário sem permissão de ver todos · Então o filtro de vendedor vem travado nele. **Medir antes** qual permissão o Blade usa hoje e manter a mesma. Controle positivo: admin escolhe qualquer vendedor do business.
- **Teste:** `SalesRepresentativeReportPageTest` — `UC-COM-04`
- **Contrato:** permissões existentes (não criar nova)
- **Regressão que defende:** vendedor vendo comissão do colega.

Trio (`.tsx` + charter + casos) no mesmo PR. Terminou: `_saida-02.md`. Pare.
