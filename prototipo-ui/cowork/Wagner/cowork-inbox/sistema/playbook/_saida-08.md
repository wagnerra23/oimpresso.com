---
sessao: "08"
titulo: Configuração da empresa em React — saída (F1/F2)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@main f5f11b25
---
# _saida-08 · Configuração da empresa — F1/F2 (parcial)

A sessão anterior desta thread (09/10) parou no primeiro turno por limite de uso, sem branch nem PR.
Este é o primeiro PR da thread.

## Entregue neste PR
- `memory/requisitos/Configuracoes/RUNBOOK-empresa.md` — F1, com o fatiamento da F3 por grupo de abas
  do protótipo (Empresa → Operação → Sistema → Integrações).
- `memory/requisitos/Configuracoes/empresa-parity.md` — 18 linhas, uma por aba/regra da Blade.
- `tests/Feature/Configuracoes/EmpresaBaselineTest.php` — F2, tenant 98 × 99: 403 sem permissão,
  a tela abre a Blade com o negócio da sessão, salvar não alcança outro negócio, e o salvar é
  substituição total.
- `acessos-pest.yml` passa a disparar quando `BusinessController.php` muda (antes só o teste disparava).

## Achado que muda a F3
`postBusinessSettings` não é parcial. Checkbox ausente vira 0, `common_settings` vira `[]`,
`enabled_modules` vira `null`, atalhos viram `"null"`. Fatiar a Page por aba e mandar só a aba aberta
apagaria as outras. A F3 tem de mandar o formulário inteiro — os grupos ainda não desenhados viajam
ocultos. O teste da substituição prende isso.

Segundo achado: `email_settings`/`sms_settings` guardam senha e segredo. Props do Inertia ficam no HTML;
a aba Integrações não pode mandá-los em claro. Preservar segredo quando vier vazio exige mudar o
controller — fica para o PR daquela aba, com decisão registrada.

## Provas
- A prova do índice (`resources/js/Pages/Configuracoes/Empresa/Index.tsx`) **ainda não existe**: a
  thread segue aberta. O placar não deve dizer `feito`.
- O baseline roda na lane `PHP / Pest (Acessos · MySQL)`; não rodei Pest local (regra: CT 100 ou CI).

## Falta
1. F3 · grupo Empresa: flag `useV2ConfiguracoesEmpresa` (OFF), ramo Inertia em `getBusinessSettings`,
   Page + charter + casos, formulário completo.
2. F3 · grupos Operação, Sistema, Integrações (um PR cada).
3. F4 smoke biz=1 com a flag só para biz=1. Cutover é decisão [W]; nenhuma chave foi ligada.
