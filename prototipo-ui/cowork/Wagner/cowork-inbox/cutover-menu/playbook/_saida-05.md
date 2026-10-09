---
sessao: "_saida-05"
thread: "05 · Relatórios Compra × Venda e Representantes"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main fe17bb457
---
# _saida-05

## Entregue: Compra × Venda
Decisão D1 ([W] 2026-10-07): *"por tela: React vira padrão, Blade só com ?classico=1"*.
Molde `UnitController@index`.

- `ReportController@getPurchaseSell`: GET comum (o menu) abre `Relatorios/CompraVenda/Index`;
  `?classico=1` abre o Blade; `?tela=nova` continua abrindo o React. O fetch da Page
  (`X-Requested-With`, sem `X-Inertia`) e o AJAX do Blade seguem recebendo o JSON dos totais,
  que é a mesma fonte dos números nas duas telas. A condição já vinha antes do `ajax()`.
- O charter diz *"responde em `?tela=nova` (sem ele, Blade)"*, que deixa de valer. **Não foi
  tocado:** charter tocado precisa declarar `related_us` (lint `charter related_us join`), e esta
  tela não tem US no SPEC. Fica para quem der US à tela.

## Não entregue: Representantes (`@getSalesRepresentativeReport`)
Fica no Blade pelo menu. A Page `Report/SalesRepresentative/Index` só tem o **resumo**
(totais de venda, despesa e comissão). As 4 abas de listagem — vendas adicionadas, vendas com
comissão, despesas, pagamentos com comissão (`report/sales_representative.blade.php:93-106`) —
só existem na Blade. O comentário do próprio método já registrava a convivência
(*"até as 4 abas de listagem serem portadas"*, playbook `comissoes/02`). Virar padrão
esconderia as listagens de quem paga comissão pelo menu. O teste trava esse estado; muda
quando as abas forem portadas.

## Provas
`tests/Feature/CutoverMenu/RelatoriosSemXInertiaTest.php` (6 casos), ligado na lane
`acessos-pest.yml` (os dois filtros de path + a linha de comando, ao lado de
`tests/Feature/Relatorios/`): GET comum → Page; `?classico=1` → Blade; visita Inertia real →
Page; `?tela=nova` → Page; fetch da Page → JSON; GET comum de Representantes → Blade.
Pest local é proibido: o veredito é a lane.

## Para o [W] antes do merge (cutover)
- Vale para todas as empresas, incluindo a ROTA LIVRE (D1 = por tela).
- A thread pedia os dois relatórios. Só o Compra × Venda foi; Representantes espera as abas.
