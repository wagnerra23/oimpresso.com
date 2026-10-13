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

---

# F3-1 · aba Negócio em React (2026-10-13, [CL])

Base: `origin/main` `d0e6c8b476`. Segundo PR da thread.

## Entregue
- `BusinessController::getBusinessSettings`: ramo Inertia atrás da flag `useV2ConfiguracoesEmpresa`
  (fora do `fallbackDefaults` → **desligada**). `?classico=1` abre a Blade mesmo com a flag ligada.
- `corpoDoSalvar()`: o corpo que a Page devolve inteiro ao `postBusinessSettings` (que **não mudou**).
- `resources/js/Pages/Configuracoes/Empresa/Index.tsx` + charter + casos (UC-EMP-01…05). Trilho com as 16 abas
  do protótipo; só **Negócio** é da tela nova, as outras abrem a Blade.
- `tests/Feature/Configuracoes/EmpresaContratoTest.php`, lane `acessos-pest` (MySQL).

## Valor (regra mestre) — antes → depois
Nenhum valor gravado muda: a flag nasce desligada, e com ela ligada o corpo devolvido sem edição deixa a linha
igual (UC-EMP-04, linha inteira comparada). Duas provas independentes:
1. **HTTP ida e volta** com o corpo convertido como o FormData do Inertia converte: margem 25,5 · desconto 12,25 ·
   pontos 0 / 1234,5 / 0,25 / 2500 · checkboxes · `pos_settings` · módulos · rótulos · atalhos · e-mail/SMS → iguais.
2. **Grade de números** pelo formatador + `num_uf`: 0 · 0,25 · 1 · 1,5 · 25,5 · 99,99 · 1234,5 · 2500 · 25000 ·
   123456,7891, com precisão de moeda 0 → todos voltam iguais (UC-EMP-05).

Diferença deliberada em relação à Blade: o corpo escreve números como `1234,5` (vírgula, sem milhar). O `@num_format`
da Blade usa a precisão da moeda e o separador dela, e por isso **a Blade** arredonda a margem quando a precisão é 0
(25,5 → 26) e, em moeda de ponto decimal com 3 casas, manda "25.000" que o `num_uf` lê como 25000. A tela nova não tem
esses dois desvios. A Blade continua igual.

## Achado
O segredo de e-mail/SMS não precisa de mudança no controller: o `only()` omite chave ausente, então o corpo não os
leva e eles ficam gravados (RUNBOOK §10 atualizado com data).

## Prova do índice
`resources/js/Pages/Configuracoes/Empresa/Index.tsx` passa a existir. Pela regra da thread, **não** marco `feito`:
faltam as outras abas.

## Falta
1. F3-2: Impostos · Produtos · Contatos. 2. Operação. 3. Sistema. 4. Integrações (e-mail/SMS só quando editados ali).
5. F4 smoke biz=1 com a flag só para biz=1. Cutover é decisão [W]; nenhuma chave foi ligada.
