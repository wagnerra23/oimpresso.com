---
sessao: "08b"
titulo: Configuração da empresa em React — saída (F3-1, aba Negócio)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@main d0e6c8b476
---
# _saida-08b · F3-1 · aba Negócio em React

Continua o `_saida-08.md` (F1/F2). Arquivo à parte porque o `_saida-08` já foi verificado no Cowork e não se edita depois.

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
