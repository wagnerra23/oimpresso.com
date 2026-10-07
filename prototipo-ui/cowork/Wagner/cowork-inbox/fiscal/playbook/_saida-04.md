---
sessao: "04"
titulo: Validação IBS/CBS no UpsertRegraTributariaRequest (UC-NFRF-05 · 06 · 07)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main 836619f64d
prefixo_tocado: UpsertRegraTributariaRequest · RegraTributariaIbsCbsValidacaoTest (novo) · RegraForm.casos.md · nfebrasil-pest.yml (allowlist) · SUPERFICIE.md (regenerado) · este arquivo
---
# _saida-04 · Validação IBS/CBS no UpsertRegraTributariaRequest

**Resposta curta:** o FormRequest passou a declarar os 5 campos de IBS/CBS. Antes eles saíam do
`validated()` e a regra ficava com IBS/CBS vazio. A prova vermelho→verde rodou no CT 100.

## 1 · Confirmação do `validated()` (C12, relido no turno)

`TributacaoController::store` grava `NfeFiscalRule::create(array_merge($request->validated(), …))`
(:179-181) e `::update` grava `$regra->update($request->validated())` (:236). O model já tinha os 5
no `$fillable`. Ou seja: o único ponto que descartava era o FormRequest, como a thread dizia.

## 2 · Feito

| arquivo | mudança |
|---|---|
| `Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php` | `c_class_trib` (6 dígitos) · `cst_ibs`/`cst_cbs` (3 dígitos) · `aliquota_ibs`/`aliquota_cbs` (decimal 0..1), todos `nullable`; `cst_ibs`/`cst_cbs` são `required_with:c_class_trib` e o inverso; mensagens PT-BR |
| `Modules/NfeBrasil/Tests/Feature/RegraTributariaIbsCbsValidacaoTest.php` | novo; 3 casos, valores lidos do banco; tenant 98 + usuário novo só com `nfe.tributacao.manage` |
| `resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md` | UC-NFRF-05, 06 e 07 (rastreabilidade + blocos + recibo) |
| `.github/workflows/nfebrasil-pest.yml` | o teste novo entra na allowlist da lane |

## 3 · Prova (CT 100, worktree isolado em `/tmp`, vendor copiado)

| rodada | resultado |
|---|---|
| branch | **3 passed** · 30 assertions |
| mesmo teste com o FormRequest do `main` | **3 failed** — UC-NFRF-05/07: os 5 voltam nulos; UC-NFRF-06: *"Session is missing expected key [errors]"* |

Guardas: `TributacaoControllerTest` e `MotorTributarioServiceTest` verdes no branch, sem alteração.
`TributacaoGatesContratoTest` tem 4 vermelhos no CT 100 que **não são desta thread**: o usuário do
biz 1 tem `Admin#1` no staging (já registrado na `_saida-18`) e o UC-NFRF-03 dá 500 no worktree
isolado (render). No CI eles valem.

## 4 · Diferença do pedido

- **Alíquotas sem IBS/CBS ficam `0`, não `null`.** A migration `2026_05_26_000001` declara
  `->default(0)` nas duas. O controle positivo do UC-NFRF-05 confere códigos nulos e alíquotas 0
  (a thread já aceitava "zerados/nulos").
- **Tenant 98, não biz 1.** O pedido citava o padrão do `TributacaoControllerTest` (biz 1). A regra
  vigente (ADR 0358) é tenant 98 via `seededTenant()`; o biz 1 no CT 100 é clone da empresa real.

- **UC-NFRF-05b virou UC-NFRF-07.** O `casos-coverage-guard` normaliza o id para maiúsculo
  (`UC-NFRF-05B`) e não casava a citação do teste (`05b`): dava `uc-orphan`. A thread dizia que os
  números eram provisórios; 07 é o próximo livre no destino.

## 5 · Fica para depois (fora do prefixo)

- Campos de IBS/CBS no `RegraForm.tsx` e no `edit()` do controller (que não devolve os 5 na prop
  `regra`): thread 05.
- Obrigatoriedade por regime/data: `D-OPERACAO`.
- Colunas IBS/CBS no CSV: outra thread, se [W] quiser.
