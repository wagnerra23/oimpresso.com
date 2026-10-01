---
sessao: "_saida-03"
thread: "03 · Casos: Nfse Index + Emitir + Show"
dono: "[CL]"
data: 2026-09-30
prefixo_tocado: resources/js/Pages/Nfse/ (3 .casos.md) · tests/Feature/Modules/NFSe/ (1 teste)
fora_do_prefixo: .github/ci-sqlite-pest.list (+1 path, +3 linhas de comentário) — ver §3
base_lida: wagnerra23/oimpresso.com@main 7201ce433
---
# _saida-03

## 1 · O que entreguei

| Prova do json | Estado |
|---|---|
| `resources/js/Pages/Nfse/Index.casos.md` | criado · UC-NFSL-01..03 |
| `resources/js/Pages/Nfse/Emitir.casos.md` | criado · UC-NFSEM-01..03 |
| `resources/js/Pages/Nfse/Show.casos.md` | criado · UC-NFSD-01..04 |

Placar: **entregue 3 de 3** provas · 10 UC, todos citados por `tests/Feature/Modules/NFSe/NfseTelasContratoTest.php` (1 `it()` por UC).

- UC derivados dos 3 charters (Goals · Non-Goals · Anti-hooks) e de US-NFSE-006/008/009/010 do SPEC — não dos `.tsx`. Não há SDD do módulo NFSe.
- `Emitir.tsx` (o `nao_toca`) intocado; nenhum `.tsx` foi editado.
- Tier 0: UC-NFSL-01 (consulta da lista sai com `business_id` da sessão) e UC-NFSD-01 (route-model-binding exige `business_id`), tenant fictício 98 com contraparte 97. Nunca biz=4.
- Valor: UC-NFSEM-02 cobre só a **validação** da alíquota (fração 0..1) e do valor (>0). Não toquei cálculo nenhum; o cálculo de ISS/líquido do `Emitir.tsx` está declarado como fora do contrato.

## 2 · Conferências locais

- `node scripts/casos-coverage-guard.mjs` → sem violações novas; `--report` lista os 10 UC com ✓.
- `npm run screen:files -- Nfse/Index` → trio completo, sem UC órfão.
- `node scripts/qa/uc-id-lint.mjs` → 0 ids fora do formato. Prefixos `UC-NFSL`/`UC-NFSEM`/`UC-NFSD` não existiam no repo (`git grep UC-NFS` só devolvia `UC-NFST`, do NfeBrasil).
- `php -l` do teste: sem erro de sintaxe. **Não rodei o Pest** (CT 100/CI only) — a prova é o contador da lane `PHP / Pest (Unit)` no PR.

## 3 · Desvio de escopo, declarado

`.github/ci-sqlite-pest.list` fica fora do `prefixo` da thread. Toquei mesmo assim (1 path) porque
**nenhum teste NFSe roda em lane nenhuma**: `test-lane-coverage.mjs --json` deu os 10 arquivos de
`Modules/NFSe/Tests/` como órfãos, e `tests/Feature/Modules/NFSe/` não aparece em lista nenhuma. Sem
a linha, o teste citado pelos UC existiria e não executaria (§5 2026-08-02). O teste é DB-less de
propósito (`toSql()` + `Validator` + `authorize()` com usuário-dublê) para ser sqlite-safe.

## 4 · Pendente / para o dono

- **NFS-e × roteiro `fiscal`** (o índice §4 pedia conferir): o `fiscal` cobre `/fiscal/nfse` (`Pages/Fiscal/Nfse.tsx`), outra tela. `Pages/Nfse/*` não está em thread nenhuma do `fiscal`. Sem duplicação.
- **Lane NFSe MySQL não existe.** Os UC que precisam de banco ficaram como `[BACKLOG]` nos casos (pré-preenchimento só da venda do próprio business, `business_id` no job, cancelamento sem `forceDelete`, gate `nfse.view` no `index()`).
- **Observação, não corrigida:** o filtro da lista aceita `autorizada`, `pendente` e `rejeitada`, mas `NfseEmissao::statusLabel()` não tem rótulo para eles. Decisão do dono do módulo.
- Upload ao Cowork: não feito aqui (a sessão-mãe sobe junto).
