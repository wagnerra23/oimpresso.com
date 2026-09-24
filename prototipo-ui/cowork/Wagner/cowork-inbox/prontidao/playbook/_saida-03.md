---
sessao: "03"
titulo: casos.md com UC · Essentials Documents + Knowledge + Messages
executor: "[C]"
base: 317e1b4ec33
---
# _saida 03

## Checklist
1. ✅ Li `scripts/lib/uc-regex.mjs` — heading `## UC-<PREFIXO>-NN · …` casa `ucHeadRe()`; prefixos `EDOC`/`EKB`/`EMSG` com **0** ocorrências prévias no repo (`git grep -nE "UC-(EDOC|EKB|EMSG)-"` → rc=1).
2. ✅ UC derivados do **charter + controller real** (`DocumentController`, `KnowledgeBaseController@index`, `EssentialsMessageController`), nunca do protótipo nem do `.tsx`.
3. ✅ 8 UC, cada um citado no **título** de um `it()` (alcançável pelo manifesto G-7, não só docblock).
4. ✅ Tier 0: tenant 98 (fictício, ADR 0358) + adversário 2. Nenhum biz=4. Cada caso negativo tem **controle positivo** no mesmo `it()`; o registro de outro tenant é de **minha** autoria, pra que só o filtro de `business_id` o segure.
5. ✅ Nenhum `.tsx` nem `.charter.md` tocado. Nenhuma operação git.
6. ⚠️ `php -l` **não conferido**: `php` não está no PATH desta máquina. A sintaxe só é validada no CI / CT 100.
7. ⚠️ **PARAR SE (>300 linhas) acionado:** o total é 626 linhas. Divisão proposta, 1 PR por tela, todas abaixo de 300:

| PR | arquivos | linhas |
|---|---|---|
| Documents | `Documents/Index.casos.md` + `tests/Feature/Essentials/DocumentsIndexContratoTest.php` | 224 |
| Knowledge | `Knowledge/Index.casos.md` + `tests/Feature/Essentials/KnowledgeIndexContratoTest.php` | 189 |
| Messages | `Messages/Index.casos.md` + `tests/Feature/Essentials/MessagesIndexContratoTest.php` | 213 |

8. ✅ PARAR SE (charter × `.tsx`) **não** acionado. Conferido por grep nas três telas: defer, rotas, `is_mine`, `replaceState`, polling, gating `can.*`, render de texto sem `dangerouslySetInnerHTML` no mural — tudo bate. Uma diferença que **não** é contradição: o `.tsx` do Knowledge tem busca por título (estado `busca`) que o charter não lista — registrada como `[BACKLOG]`.

## UC criados

| tela | UC | o que prova |
|---|---|---|
| Documents | UC-EDOC-01 `[T0]` | memos: meu + compartilhado comigo aparecem; não-compartilhado e outro tenant não |
| Documents | UC-EDOC-02 | criar memo grava no tenant da sessão e redireciona com `type=memos` |
| Documents | UC-EDOC-03 | `DELETE` de item de terceiro não apaga; do próprio apaga |
| Knowledge | UC-EKB-01 | ACL: público · meu · `only_with` que me inclui aparecem; privado alheio e `only_with` sem mim não |
| Knowledge | UC-EKB-02 `[T0]` | livro público de minha autoria em outro tenant não aparece |
| Messages | UC-EMSG-01 | `POST` grava no tenant da sessão e a mensagem entra no mural |
| Messages | UC-EMSG-02 `[T0]` | mural não mostra mensagem de outro tenant |
| Messages | UC-EMSG-03 | polling devolve só de outros, mais novas que o último visto, do próprio tenant |

## `node scripts/casos-coverage-guard.mjs`

```
casos:check · 71 violações (telas: 220, casos.md: 161)
ESCOPO (fonte única scripts/qa/page-path.mjs · idêntico ao screen-coverage-map):
  inclui: resources/js/Pages/**/<Sub>/<Tela>.tsx (Page Inertia executável)
  exclui: dirs auxiliares (_*, components, partials, hooks, utils, lib, types,
          constants, schemas, stores, contexts) · .tsx na raiz de Pages/ · *.charter.tsx · *.test.tsx
✅ Sem violações novas DESTE PR (débito caiu −12 vs baseline).
```

**Controle positivo do guard:** acrescentei temporariamente `## UC-EDOC-09 · sonda` (sem teste) ao casos.md → o guard saiu `rc=1` com `🆕 uc-orphan:…#UC-EDOC-09` e **só** ele; removida a sonda, voltou ao verde acima. Logo os 8 UC reais não são órfãos. O `−12` não foi atribuído a este trabalho (não medi o guard antes de escrever).

## `node scripts/qa/prototipo-readiness.mjs` — antes × depois

| | antes | depois |
|---|---|---|
| ✅ PRONTAS | 59 | **62** |
| 🟡 1-CICLO | 35 | **32** |

As três telas saíram do balde 1-ciclo (antes: `falta: casos.md-com-UC`) e entraram em PRONTAS:

```
  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 62
       [core] Essentials/Documents/Index
       [core] Essentials/Knowledge/Index
       [core] Essentials/Messages/Index
  🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): 32
```

## Lane de CI — o teste NÃO roda em nenhuma lane de PR hoje

- `node scripts/governance/test-lane-coverage.mjs --json` **não enxerga** os arquivos novos: ele enumera por `git ls-files` (linha 199) e os arquivos estão fora do índice (esta thread não faz git). O veredito saiu da leitura direta das listas.
- `.github/workflows/essentials-pest.yml` (lane `PHP / Pest (Essentials · MySQL)`) tem allowlist inline; `grep "Feature/Essentials"` → rc=1. Controle positivo: `CalculoValorPayrollTest` (de `tests/`) **é** achado lá — a busca funciona.
- `.github/ci-sqlite-pest.list`: nenhuma entrada cobre `tests/Feature/Essentials` (rc=1); e os testes são MySQL-only (pulam no SQLite) de qualquer forma.
- `phpunit.xml` inclui `./tests/Feature` na suíte `Feature`, então eles entram no full-suite do CT 100 — mas **não** em PR.
- A lane natural **não é required** (`PHP / Pest (Essentials` ausente de `governance/required-checks-baseline.json`).
- **Wiring necessário (fora do prefixo, não feito):** acrescentar os 3 paths à allowlist do step `Run Pest` **e** ao `paths:`/`paths-filter` do `essentials-pest.yml`. Sem isso o UC fica 🧪 sem veredito para sempre.

## O que ficou de fora

- **Divergência charter × código (não × `.tsx`), a levar ao [W]:** o charter Documents diz que remover apaga "junto os compartilhamentos"; o `destroy` lido **não** apaga `essentials_document_shares` (sem model event, sem FK). Hipótese por leitura, sem teste — registrada como `[BACKLOG]`, charter não tocado.
- **Hipótese não medida (fora do escopo, tela Create):** `essentials_kb.status` é `NOT NULL` no schema baseline e o `KnowledgeBaseController@store` não o preenche. Pode quebrar a criação de livro no MySQL estrito — ou haver default/migration posterior que eu não li. Não vira UC aqui.
- Upload de arquivo, download com ACL, share por papel, cascade de exclusão do KB, gating 403 de mensagens, anti-spam de notificação: todos como `[BACKLOG]` sem id (1 fonte ou sem teste).
- Os testes existentes em `Modules/Essentials/Tests/Feature/` (`MessagesIndexTest`, `KnowledgeIndexTest`, `KnowledgeXssSanitizationTest`) não citam UC-id e estão fora do prefixo — não editados.
