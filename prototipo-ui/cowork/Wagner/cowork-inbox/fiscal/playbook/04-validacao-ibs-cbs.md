---
sessao: "04"
titulo: Validação IBS/CBS no UpsertRegraTributariaRequest — o motor lê, o formulário não grava
dono: "[CL]"
base: wagnerra23/oimpresso.com@main (árvore 3de6bdc5bf8b, lida 2026-10-06 13:20 UTC)
prefixo: Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php · Modules/NfeBrasil/Tests/Feature/RegraTributariaIbsCbsValidacaoTest.php · resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md
nao_toca: Modules/NfeBrasil/Services/ (motor — lei 2 do módulo) · resources/js/Pages/NfeBrasil/Tributacao/*.tsx (UI sem alvo medido) · Modules/NfeBrasil/Database/Migrations/ (as 5 colunas já existem)
depende: 18 (mesmo controller de tributação — em sequência). 🔴 fiscal: vai SOZINHO no PR, com o teste no mesmo PR (lei 6 do módulo).
implementa: UC-NFRF-05 · UC-NFRF-06 · UC-NFRF-05b
us: US-FISCAL-021 (p0, todo) — o cálculo já lê as colunas e o XML já serializa (PR-D #3778) quando `reforma_tributaria_modo` ∈ {full, hybrid_2026}; falta a porta de gravação. R-NFE-011: em `legacy` o XML não leva o grupo, e isso continua.
---
# 04 · Validação IBS/CBS

## Por que
A migration `2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules.php` (US-FISCAL-021, NT 2025.002) criou 5 colunas em `nfe_fiscal_rules`: `c_class_trib` char(6), `cst_ibs` char(3), `cst_cbs` char(3), `aliquota_ibs` decimal(7,4), `aliquota_cbs` decimal(7,4).
O `MotorTributarioService::aplicarRegra` **já lê as cinco** (`c_class_trib`, `cst_ibs`, `cst_cbs`, `aliquota_ibs`, `aliquota_cbs`).
O `UpsertRegraTributariaRequest::rules()` **não declara nenhuma**. Se o controller grava por `validated()`, o campo é descartado em silêncio e a regra fica com IBS/CBS vazio para sempre, exceto via CSV.

## Escopo (só formato — obrigatoriedade NÃO entra aqui)
Em `UpsertRegraTributariaRequest::rules()`, acrescentar:
- `c_class_trib` → `nullable|string|size:6|regex:/^[0-9]{6}$/`
- `cst_ibs`, `cst_cbs` → `nullable|string|size:3|regex:/^[0-9]{3}$/`
- `aliquota_ibs`, `aliquota_cbs` → `nullable|numeric|min:0|max:1` (decimal, mesmo padrão das outras alíquotas)
- coerência: `cst_ibs` e `cst_cbs` são `required_with:c_class_trib`, e o inverso também
- mensagens PT-BR no mesmo estilo das existentes (`'Alíquota é decimal (0.009 = 0,9%)'`)

**Confirmado por [CC] em 2026-10-06 (árvore 3f435ddc3f85):** `TributacaoController::store` grava `$request->validated()` (:179) e `::update` grava `$regra->update($request->validated())` (:235). Então os 5 campos são **descartados em silêncio** hoje, e o bug é exatamente este. Reconfirme a linha no seu turno antes de editar (C12).

## Fora do escopo (cada item é decisão [W] ou outra thread)
- Exigir IBS/CBS por regime (CRT 3 desde 01/08/2026, Simples em 2027): é regra fiscal com data → `D-OPERACAO`/vigência.
- Campos no `RegraForm.tsx`: UI sem alvo medido → thread 05 (bloqueada).
- Colunas IBS/CBS no CSV (`ImportRegrasCsvService`): outra thread se [W] quiser.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFRF-05 | A regra grava e relê os 5 campos de IBS/CBS | `must` `[fiscal]` | `RegraForm.casos.md` |
| UC-NFRF-06 | Formato de IBS/CBS inválido é recusado sem gravar | `must` `[fiscal]` | `RegraForm.casos.md` |
| UC-NFRF-05b | Editar a regra também grava IBS/CBS (não só criar) | `must` `[fiscal]` | `RegraForm.casos.md` (pode fundir no UC-NFRF-05) |

### UC-NFRF-05 · A regra grava e relê os 5 campos de IBS/CBS · `must` `[fiscal]`
- **Destino:** `RegraForm.casos.md`
- **Persona:** contador cadastrando a regra pra 2027 / regime normal
- **Aceite:** Dado um usuário com `nfe.tributacao.manage` · Quando ele cria uma regra com `c_class_trib=000001`, `cst_ibs=000`, `cst_cbs=000`, `aliquota_ibs=0.001`, `aliquota_cbs=0.009` · Então a linha em `nfe_fiscal_rules` tem os 5 valores (**lido do banco**, não da resposta). Controle positivo: uma regra **sem** os 5 continua sendo criada, e com eles zerados/nulos.
- **Teste:** `RegraTributariaIbsCbsValidacaoTest` — `UC-NFRF-05 · regra grava e relê c_class_trib, cst_ibs, cst_cbs e alíquotas IBS/CBS`
- **Contrato:** US-FISCAL-021 · migration `2026_05_26_000001` · `MotorTributarioService::aplicarRegra` (já lê os 5)
- **Regressão que defende:** hoje `store`/`update` gravam `validated()` (:179/:235) e o FormRequest não declara os campos: eles somem em silêncio e o motor lê nulo.

### UC-NFRF-06 · Formato de IBS/CBS inválido é recusado sem gravar · `must` `[fiscal]`
- **Destino:** `RegraForm.casos.md`
- **Persona:** qualquer usuário fiscal digitando errado
- **Aceite:** Dado `c_class_trib` com 5 dígitos, **ou** `aliquota_cbs=9`, **ou** `c_class_trib` sem `cst_ibs` · Quando salva · Então 422 no campo e **nenhuma** linha nova. Controle positivo: o mesmo payload corrigido grava.
- **Teste:** `RegraTributariaIbsCbsValidacaoTest` — `UC-NFRF-06 · formato inválido de IBS/CBS é recusado e não grava`
- **Contrato:** NT 2025.002 (cClassTrib 6 dígitos, CST 3) · padrão decimal das alíquotas (UC-NFCD-04)
- **Regressão que defende:** alíquota digitada como "0,9" vira 90% de CBS na nota.

### UC-NFRF-05b · Editar a regra também grava IBS/CBS (não só criar) · `must` `[fiscal]`
- **Destino:** `RegraForm.casos.md` (pode fundir no UC-NFRF-05)
- **Persona:** contador corrigindo cClassTrib
- **Aceite:** Dado uma regra existente sem IBS/CBS · Quando edito preenchendo os 5 campos · Então o `update` persiste os 5 (lido do banco). Controle positivo: editar só a alíquota de ICMS não zera IBS/CBS já gravados.
- **Teste:** `RegraTributariaIbsCbsValidacaoTest` — `UC-NFRF-05b · update grava e preserva IBS/CBS`
- **Contrato:** `TributacaoController::update` (:235, `validated()`)
- **Regressão que defende:** o caminho de edição continuar descartando os campos.

## Prova
1. `RegraTributariaIbsCbsValidacaoTest.php` (lane do módulo, padrão de `TributacaoControllerTest.php`):
   - POST com os 5 campos válidos → a regra persiste **com os 5 valores** (ler do banco, não da resposta)
   - `c_class_trib` com 5 dígitos → 422 no campo
   - `aliquota_cbs = 9` → 422 (alíquota é decimal)
   - `c_class_trib` sem `cst_ibs` → 422
   - regra sem nenhum dos 5 → continua 201/302 (não quebra Simples/legado)
2. `RegraForm.casos.md` ganha 1 UC citando o teste (G-2: UC só com teste que o cite).
3. Guardas: `MotorTributarioServiceTest.php` e `TributacaoControllerTest.php` continuam verdes, sem alteração.

Terminou: `_saida-04.md` com o sha, o resultado da confirmação do `validated()` e a saída dos testes. Pare.
