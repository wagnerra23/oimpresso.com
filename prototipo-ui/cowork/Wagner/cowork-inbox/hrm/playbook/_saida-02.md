---
sessao: "_saida-02"
thread: "02 · Licenças — Page"
dono: "[C]"
data: 2026-09-23
tipo: recibo retroativo
entregue_em: "#6868 (2026-09-05)"
base_lida: wagnerra23/oimpresso.com@main 1418ca208
---
# _saida-02

## Entregue
Pelo #6868 (2026-09-05): *feat(essentials): tela Licenças em Inertia — PR-9 da onda HRM-O7*.

Recibo escrito depois, na sessão de recibos de 2026-09-23. A thread tinha todas as provas verdes
e nenhum `_saida`, então o placar a mostrava como `próximo` — abrir sessão nela refaria trabalho
já mergeado. Conferido o que a thread pede contra o `main` antes de escrever isto.

## Provas medidas no main (1418ca208)
1. Page, charter e casos da tela Licenças existem em `resources/js/Pages/Essentials/`
2. `essentials-licencas.contract.json` tem as chaves `alvo` e `secoes`; `e2e/essentials-licencas.spec.ts` existe
3. `EssentialsLeaveController.php` contém `Inertia::render('Essentials/Licencas`

## Nota
A tela foi entregue fora do playbook, antes de a thread existir — já estava registrado no `_saida-01.md` do HRM (Descobertas 2). O contrato mudou de pasta no #7224; a entrega é o #6868.
