---
sessao: "01"
titulo: "Tenant na subconsulta de revoke — saída da thread"
autor: "[CL]"
criado: 2026-09-08
base: 1a52e6ea9765
thread: 01-tenant-subquery-revoke.md
ancora: "bc036e9ed701 · 5.054 B — sha e bytes conferidos ANTES de ler: âncora VÁLIDA, sem remedição"
veredito: "entregue — defeito PROVADO e corrigido · 1 resíduo Tier 0 aberto (o gêmeo) · 2 achados"
---

# _saída 01 · Tenant na subconsulta de revoke

## Checklist de saída — item a item

### 1 ✅ predicado de tenant na subconsulta

`AssetAllocationService.php:112` — 1 linha, dentro do `DB::raw` que já existia.
Service `bc036e9ed701` → `584d4f198d55`.

Antes (SQL gerado):

```sql
SELECT SUM(COALESCE(AR.quantity, 0)) FROM asset_transactions AS AR
WHERE (AR.asset_id=assets.id AND AR.transaction_type='revoke')
```

Depois:

```sql
SELECT SUM(COALESCE(AR.quantity, 0)) FROM asset_transactions AS AR
WHERE (AR.asset_id=assets.id AND AR.business_id=assets.business_id AND AR.transaction_type='revoke')
```

**Escolha da técnica (decisão minha, registrada — não é pergunta pro [W]):** correlacionei com
`assets.business_id` em vez de interpolar `$allocated->business_id`. É o que a thread §B prescreve
literalmente, é a **mesma fonte de verdade** que o `:107` já fixa (`->where('assets.business_id',
$allocated->business_id)`), e não interpola valor em SQL cru. Sem parâmetro novo, sem mudança de
assinatura, sem `whereRaw`.

### 2 ✅ caso novo em `CrossTenantAssetTest`

`UC-ASSET-TENANT-01`. O dono aloca 10; o adversário grava um `revoke` de 4 apontando o `asset_id`
do dono; o disponível do dono tem de continuar 10.

Tenants pelo helper canônico, **não hardcode**: `seededTenant()` e `seededSupportClientTenant()`.
Resolveram de fato para **98** (`CI Tenant 98 (ficticio)`) e **99** (`CTM Test Biz Adversario#99`)
— medido no staging, sem cair no fallback. ADR 0358: 98 = canônico, 99 = adversário. biz=4 não
aparece em lugar nenhum. O caso abre com `expect($dono->id)->not->toBe($adversario->id)` para não
poder ficar verde tautológico.

### 3 ✅ o caso falha sem a correção — a saída, colada

Rodado no CT 100 **antes** de tocar o Service:

```
FAILED  Modules\AssetManagement\Tests\Feature\CrossTenantAssetTest > it c…
Failed asserting that 6 is identical to 10.
at Modules/AssetManagement/Tests/Feature/CrossTenantAssetTest.php:245

Tests: 6 failed, 23 skipped, 158 passed (513 assertions)
```

`10 → 6`: o revoke de 4 gravado pelo biz=99 entrou na conta do biz=98. **O `PARAR SE (a)` não
disparou** — não há guarda em outro lugar; o vazamento é real e alcançável.

Depois da correção, o mesmo caso:

```
✓ it cross-tenant: revoke do adversário NÃO altera quantidadeDisponiv… 0.44s
```

### 4 ⚠️ os 9 Pest do módulo — **não estão todos verdes, e não por causa desta thread**

```
PASS  AssetManagementHealthCommandTest · ScaffoldTest · SmokeRoutesTest
PASS  AssetServiceOtelInstrumentationTest · LgpdComplianceTest · ArchitectureTest
PASS  Wave27AssetManagementPolishTest
FAIL  CrossTenantAssetTest ......... 3 ⨯ (os 3 casos PRÉ-EXISTENTES; o meu passou)
FAIL  MultiTenantIsolationTest ..... 4 ⨯ (arquivo que esta thread não toca)
Tests: 7 failed, 62 passed (143 assertions)
```

As 7 falhas são **a mesma** `QueryException`: FK `assets_created_by_foreign`. `assets.created_by`
é NOT NULL com FK para `users` (migration `2020_08_20_114339:55`), e esses testes chamam
`Asset::create()` sem informá-lo.

**Prova de pré-existência (execução, não opinião)** — restaurei o arquivo ao `main` no container
e rodei sem nada meu:

```
⨯ it cross-tenant: JOIN assets-maintenance filtrado biz=1 …
⨯ it cross-tenant: JOIN filtrado biz=99 …
⨯ it cross-tenant: Asset::forDropdown($biz) sanity …
Tests: 3 failed (0 assertions)
```

**0 assertions** — morrem no `Asset::create`, antes de assertar qualquer coisa. E
`MultiTenantIsolationTest` está byte-idêntico ao `main` no container (`git status` vazio nesse
path), logo suas 4 falhas são pré-existentes por construção.

O que isso significa, dito sem suavizar: **os dois arquivos que existem justamente para provar
isolamento Tier 0 no Patrimônio não executavam uma única assertion no CT 100.** O caso desta
thread é o primeiro teste cross-tenant do módulo que de fato roda e asserta lá. Consertar os 7 é
informar `created_by` nos `Asset::create` — mas está **fora deste prefixo** e é 1 PR próprio, não
um carona. Fica como resíduo abaixo.

### 5 ✅ nenhum arquivo fora do prefixo

```
 M Modules/AssetManagement/Services/AssetAllocationService.php     1 +/1 -
 M Modules/AssetManagement/Tests/Feature/CrossTenantAssetTest.php 75 +/0 -
 soma = 77 linhas (limite 300)
```

### 6 ✅ placar no corpo do PR (C10)

---

## Achado 1 — a thread 01 §A erra a DIREÇÃO do efeito

O doc diz: *"O saldo disponível fica **maior** do que é."* **É o contrário, e está medido: `10 → 6`.**

O retorno é `allocated_qty − revoked_qty` (`:116`). Um revoke alheio **soma** em `revoked_qty`,
logo o resultado **cai**. Acrescentar o filtro só pode reduzir `revoked_qty` — o valor corrigido é
sempre **≥** o do bug.

A consequência prática também inverte: o defeito **não** permite alocar além do estoque; ele
**bloqueia alocação legítima**, mostrando saldo menor do que existe. Segue Tier 0 (o número de uma
empresa depende de linha de outra), mas quem for priorizar precisa do sinal na direção certa.
Não editei o playbook — não é meu prefixo.

## Achado 2 — o irmão mora dentro do MESMO método, e eu não o toquei

`:103–:105`, o `leftJoin` que produz `allocated_qty`:

```php
Asset::leftJoin('asset_transactions as AT', function ($join) {
    $join->on('assets.id', '=', 'AT.asset_id')->where('transaction_type', 'allocate');
})
```

Também **não filtra `AT.business_id`** — mesma exposição, ramo `allocate`. Não corrigi porque a
instrução desta thread é explícita e estreita (*"dentro do `DB::raw` que já existe"*), e ampliar
por conta própria é o oposto do que o prefixo protege.

**Status honesto: hipótese por leitura, não achado provado** — meu caso não exercita esse ramo
(o adversário só grava `revoke`). Um `allocate` de B sobre o bem de A inflaria `allocated_qty`.
A thread 02 toca este mesmo Service e é o lugar natural para provar ou refutar.

## RESÍDUO — o gêmeo Tier 0 segue ABERTO

**Endereçado por SÍMBOLO, não por linha** — o PR #7008 (thread 03, draft) insere a guarda no
`index()` e empurra o resto do arquivo. Medi nos dois lados: a subconsulta está em `:97` no `main`
de hoje e cai em `:110` na branch dele. Ref de linha apodrece no primeiro refactor (lápide §5
2026-07-26), então o endereço durável é o símbolo + o `grep` que o re-localiza:

```
símbolo   AssetController::index() — a subconsulta que produz `revoked_qty`
localiza  git grep -n "asset_transactions AS AR" -- Modules/AssetManagement/
linha     :97 no main em 2026-09-08 (datado, não é endereço)
```

É **cópia literal** da minha, provada byte-a-byte com as duas linhas normalizadas:

```
AssetAllocationService::quantidadeDisponivel()  md5 = a9733ecd33190e7f4a3bd340ec801a1c
AssetController::index()                        md5 = a9733ecd33190e7f4a3bd340ec801a1c
                                                → IDÊNTICAS
```

Varredura contada da família (`git grep "asset_transactions AS AR"`, repo inteiro): **2 sites de
código, 2 de 2** — o meu (corrigido) e o do `index()` (aberto). Os outros 3 hits são documentação.

**Não consertei: está fora do prefixo** (`nao_toca: Http/Controllers/`). E ele é o de **maior**
alcance — é o índice/listagem, contra 1 consumidor do meu (`AssetAllocationController`, método
`update`; medido: `git grep quantidadeDisponivel` = 1 chamada de código no repo). Documentado em
`CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md` §4.

**Terceira ordem — corrigido o que eu havia repassado.** A errata §4 chama `dashboard()` (`:430`
e `:442` no main de hoje) de *"mesmo padrão"*, e eu repassei assim depois de conferir só que a
correlação era por `AT.parent_id`. A thread 04 mediu mais fundo e está certa: ali as queries
**externas** também não filtram `business_id` — falta o amarre que salva o `index()`. É defeito
**distinto e maior**, não a mesma família. Registro a correção em vez de deixar minha versão de pé.

## Nota — o SCOPE pedido não existe nesse caminho

A abertura pede `memory/requisitos/Patrimonio/SCOPE.md`. Esse caminho **não existe** no `main`
(`memory/requisitos/Patrimonio/` está vazio). O SCOPE do módulo é
`memory/requisitos/AssetManagement/SCOPE.md` — lido de lá. Vale para as threads 02–06.

## Nota de coordenação — threads 03 e 04 em paralelo

O container tinha `AssetController.php` modificado ao abrir a sessão: era a thread 03. Ao final
estava limpo, e o motivo é **verificado, não suposto** — ela commitou e abriu o **PR #7008**
(draft), que carrega a guarda `asset.view` no `index()` mais os testes. Nada se perdeu. Não
toquei nesse arquivo em momento algum; meus dois `git checkout` no container nomearam apenas
`AssetAllocationService.php` e `CrossTenantAssetTest.php`. A 03 e a 04 confirmaram, do lado
delas, que os prefixos são disjuntos.

Lição operacional para as threads seguintes: o checkout do CT 100 é **compartilhado** e as
threads rodam nele em paralelo. Estado não-commitado ali é volátil por construção — commite ou
use `git stash push -m <tag>` antes de rodar, e restaure apenas os paths que são seus,
nominalmente. É a família da lápide §5 2026-07-27 (consumir estado global do repo por posição ou
por presunção de posse).

`gh pr list --state open` na abertura: 4 PRs (#7003, #7002, #6427, #6425), **nenhum** tocando
`Modules/AssetManagement` — cruzado arquivo a arquivo. Ao fechar, apareceram os PRs #7008
(thread 03) e #7009 (thread 04), do mesmo playbook e em prefixos disjuntos deste.
