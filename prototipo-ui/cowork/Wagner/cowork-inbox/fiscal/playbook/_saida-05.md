---
sessao: "05"
titulo: Campos IBS/CBS no RegraForm (UC-NFRF-08)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main 03f5d9d822 (já com o #8889, thread 04)
prefixo_tocado: RegraForm.tsx · RegraForm.casos.md · RegraForm.charter.md (§Goals) · TributacaoController::edit · RegraTributariaIbsCbsValidacaoTest · e2e/nfe-tributacao-regra.spec.ts (novo) · este arquivo
---
# _saida-05 · Campos IBS/CBS no RegraForm

**Resposta curta:** o formulário de regra ganhou a seção "Reforma tributária" (cClassTrib, CST IBS,
CST CBS, IBS, CBS) e o `edit()` passou a devolver os 5 na prop `regra`. Antes a tela não mandava os
campos que a thread 04 passou a aceitar, e a edição não os mostrava.

## 1 · Confirmação dos símbolos (C12, relidos no turno)

- O #8889 (thread 04) já estava **mergeado** quando a thread começou: o `UpsertRegraTributariaRequest`
  declara os 5. Esta thread monta em cima dele e não toca `Http/Requests/` nem `Services/`.
- `TributacaoController::edit` montava a prop `regra` à mão e **não** incluía os 5 — a _saida-04
  já apontava isso. Sem o conserto, o form novo abriria a seção vazia e o "Atualizar" gravaria nulo.
- `FieldDecimal` já existia no `RegraForm.tsx`.

## 2 · Feito

| arquivo | mudança |
|---|---|
| `resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx` | card "Reforma tributária": `FieldCodigo` novo (só dígitos, 6 ou 3) para cClassTrib/CST IBS/CST CBS; IBS/CBS via `FieldDecimal` |
| `Modules/NfeBrasil/Http/Controllers/TributacaoController.php` | `edit()` devolve `c_class_trib`, `cst_ibs`, `cst_cbs`, `aliquota_ibs`, `aliquota_cbs` |
| `Modules/NfeBrasil/Tests/Feature/RegraTributariaIbsCbsValidacaoTest.php` | caso UC-NFRF-08 (reabrir traz os 5 do banco + controle sem IBS/CBS) |
| `e2e/nfe-tributacao-regra.spec.ts` | novo: o POST leva os 5; trocar CSOSN→CST→CSOSN não apaga a seção. O POST é abortado, não grava |
| `RegraForm.casos.md` · `RegraForm.charter.md` | UC-NFRF-08 + recibo; bullet da seção em §Goals |

## 3 · Prova (CT 100, worktree isolado em `/tmp/wt-t05`, sha `c565e22bb`)

| rodada | resultado |
|---|---|
| branch · `RegraTributariaIbsCbsValidacaoTest` | **4 passed** · 54 assertions |
| mesmo teste com o `TributacaoController` do `main` | UC-NFRF-08 **1 failed** — *"two arrays are identical"* |
| `TributacaoControllerTest` | 7 passed |
| `TributacaoGatesContratoTest` | 3 failed **iguais com o controller do `main`** (o `Admin#1` do staging, já registrado na _saida-18/04) |

O e2e roda na lane `e2e-gate`; não roda local (ADR 0062).

## 4 · Diferença do pedido

- **UC-NFRF-08, não 07.** A thread 04 já usou o 07 (o antigo 05b). 08 é o próximo livre.
- **Alíquota em decimal na tela, não em %.** O UC do playbook dizia "% na tela". O charter
  (§Goals) fixa decimal via `FieldDecimal` para todas as alíquotas do form, e o corpo da thread
  pede `FieldDecimal`. Pôr só IBS/CBS em % deixaria duas convenções no mesmo formulário.
- **Ambiente do CT 100:** o worktree isolado precisou de vendor copiado, `manifest.json` do Vite
  copiado e 7 entradas do classmap estático filtradas (classes que o checkout velho do container
  tinha e o branch não). Nada disso entra no PR.

## 5 · Fica para depois

- Exibir IBS/CBS na listagem (`Index.tsx`) e no CSV: outra thread.
- Obrigatoriedade por regime/data: `D-OPERACAO` (thread 07 em diante).
