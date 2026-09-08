---
sessao: "03"
titulo: Guarda `asset.view` no índice de Bens — saída
dono: "[CL]"
base: a875e200c0 (origin/main, lido 2026-09-08)
thread: 03-guarda-asset-view.md
prefixo_escrito: Modules/AssetManagement/Http/Controllers/AssetController.php · Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php
veredito: feito
---

# 03 · Saída — guarda `asset.view` no `index()`

> **PASSO 1 (a trava) PASSOU:** `asset.view` **existe registrada**. A thread seguiu.
> O defeito não era teórico: sem a guarda, o usuário sem permissão recebia **HTTP 200** na
> listagem do patrimônio inteiro — medido por bite-test, não deduzido.

## 1 · `can('asset.view')` em `index()` ✅

[`AssetController.php:71`](../../../../../Modules/AssetManagement/Http/Controllers/AssetController.php) — permissão de tela **antes** do gate de assinatura, mesma string de abort do `create()`:

```php
public function index(Request $request)
{
    if (! auth()->user()->can('asset.view')) {
        abort(403, 'Unauthorized action.');
    }

    $business_id = request()->session()->get('user.business_id');

    if (! (auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 'assetmanagement_module')))) {
        abort(403, 'Unauthorized action.');
    }
```

**Padrão de guarda por método, medido (não suposto)** — o `index()` era o único método de
leitura de dados sem permissão de tela. Linhas **pós-PR**:

| método | permissão de tela | gate assinatura |
|---|---|---|
| `index()` `:71` | `asset.view` **`:82` ← ESTE PR** | `:88` |
| `create()` `:282` | `asset.create` `:284` | `:290` |
| `edit()` `:350` | `asset.update` `:352` | `:358` |
| `destroy()` `:410` | `asset.delete` `:412` | `:418` |
| `store()` `:311` | via `StoreAssetRequest::authorize()` | idem |
| `update()` `:384` | via `UpdateAssetRequest::authorize()` | idem |
| `show()` `:339` | — (stub `return view('assetmanagement::show')`, não consulta dado) | — |
| `dashboard()` `:436` | **ausente** — mas por desenho, ver §3 | ausente |

As checagens que só desenham botão de linha dentro do `index()` seguem intactas em `:158`
(`view_all_maintenance` / `view_own_maintenance`), `:167` (`update`) e `:176` (`delete`) —
pré-PR eram `:145`, `:154` e `:163`.

⚠️ **Consequência da paridade literal, registrada:** como em `create`/`edit`/`destroy`, um
**superadmin sem `asset.view` também toma 403**. Isso não é regressão nova — é a regra já
vigente nos outros 3 métodos, agora estendida ao `index()`. Se [W] quiser que `superadmin`
faça bypass, a decisão vale para os **4** métodos, não só para este, e é PR próprio.

## 2 · Teste de 403 ✅ — e ele MORDE

[`SmokeRoutesTest.php`](../../../../../Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php), 2 cenários novos (`+148` linhas, **`-0`**):

- **MORDE** — usuário sem `asset.view` → `GET /asset/assets` → `403`.
- **CN (controle positivo)** — usuário **com** `asset.view` → status **≠ 403**.

**Bite-test (a prova de que o teste não é carimbo):** com o teste novo e o controller
**original**, o `MORDE` falha assim —

```
FAILED … it MORDE: usuário SEM asset.view recebe 403 em GET asset/assets
Expected response status code [403] but received 200.
```

O `200` é o defeito materializado: **qualquer usuário da empresa com o módulo assinado
listava o patrimônio inteiro** — não era só botão exposto.

**Por que o CN existe:** sem ele, o `403` do primeiro cenário seria indistinguível de um
`403` vindo do pipeline (`throttle`/`authh`/`auth`/`SetSessionData`/`AdminSidebarMenu`) —
verde que não prova a guarda. O CN asserta `status !== 403` (não `200`) **de propósito**:
responde *"o 403 veio da guarda?"*, não *"a listagem renderiza"* — o corpo do `index()`
fora do ramo ajax é pré-existente e este PR não o toca.

**Tenant:** 98 (fictício, ADR 0358) por `find`-ou-`forceCreate`; biz=4 **não** aparece.

### 2-bis · Um defeito do próprio teste, achado e consertado na sessão

A 1ª versão limpava os fixtures em `->afterEach()` encadeado ao `it()`. **Medido no CT 100:
não executou** — 4 rodadas deixaram **8 usuários e 1 role órfãos** na base, que é clone de
prod e **não se limpa entre runs**. A mesma função, chamada à mão via `tinker`, apagou os 8
**sem uma exceção** → o defeito era o *gancho*, não a lógica. Trocado por `try/finally`
dentro do closure (limpa inclusive quando o assert falha) + `withTrashed()` (o `App\User`
usa SoftDeletes). **Órfãos deixados por mim: removidos**; prova abaixo.

## 3 · Veredito sobre `dashboard()` — ❌ NÃO entra junto (parada (b) do playbook)

O `dashboard()` (`:436`) **não tem guarda nenhuma** — nem `asset.view`, nem sequer o gate de
assinatura. Mas **não é o mesmo buraco**, e aplicar `asset.view` ali seria o erro que a
thread manda evitar:

- Ele é uma tela **pessoal por construção**: as duas primeiras consultas filtram
  `where('receiver', auth()->user()->id)` — *"os bens alocados **a mim**"*. O único bloco com
  dado da empresa inteira já está protegido por `if ($is_admin)`.
- É **exatamente** o perfil que o `PARAR SE (b)` descreve: *"colaborador que vê só o próprio
  bem alocado"*. A regra ali não é `view`, é **escopo por dono**.
- E quebraria um caminho vivo: `DataController.php:109` mostra o item de menu do Patrimônio
  para quem tem `asset.view` **ou** `asset.view_own_maintenance` **ou**
  `asset.view_all_maintenance`, e esse item aponta para o `dashboard()`. Um usuário só com
  `view_own_maintenance` tomaria 403 no item de menu que o próprio sistema exibiu para ele.

**Decisão é de [W]**, e conversa com o RESÍDUO §2 (`asset.*` × `assetmanagement.*`).

## 4 · Permissão confirmada como existente — **a origem, colada**

`asset.view` **não foi inventada**. Registro canônico em
[`Modules/AssetManagement/Http/Controllers/DataController.php:31`](../../../../../Modules/AssetManagement/Http/Controllers/DataController.php), método `user_permissions()` — o mecanismo
UltimatePOS que popula a lista de permissões de `/roles/{id}/edit`:

```php
    public function user_permissions()
    {
        return [
            [
                'value' => 'asset.view',
                'label' => __('assetmanagement::lang.view_asset'),
                'default' => false,
            ],
```

**Consumidores já existentes** (a rota passa a honrar o que a UI já declarava):

| onde | linha | uso |
|---|---|---|
| `Resources/views/layouts/nav.blade.php` | `:21` | `@can('asset.view')` envolvendo o link **"Ativos"** — a nav já dizia que esta tela é `asset.view` |
| `DataController.php` | `:109` | item de sidebar do módulo (ADR 0180) |

⚠️ **O grep ingênuo mente aqui**, como a errata do playbook (§3) já registrara: `asset.view`
casa por **prefixo** com `asset.view_all_maintenance` (`:158` pós-PR, `:145` pré) e volta
falso-positivo — o próprio `03-*.md` cita essa linha como se fosse a permissão da tela. A
busca que discrimina é a string exata `'asset.view'` / `can('asset.view')` — foi a usada,
com controle positivo (`'asset.create'`) para provar que o padrão de fato casa.

## 5 · Suíte do módulo — veredito honesto: **NÃO são 9 verdes**, e não é por causa deste PR

A checklist pedia *"9 Pest verdes"*. **Eles não estão verdes**, e já não estavam antes de eu
tocar em nada. Medido no CT 100 com **antes→depois**, mesma árvore, mesmo comando:

| | failed | passed | assertions |
|---|---:|---:|---:|
| **baseline** (meus 2 arquivos originais) | 8 | 61 | 142 |
| **com a guarda** | 8 | **63** | **144** |
| **delta** | **0** | **+2** | **+2** |

O delta bate **exatamente** com os 2 cenários novos (1 assertion cada) — prova de que eles
**executaram**, não pularam (teste que pula sai com exit 0 e não conta assertion).

As **8 falhas são pré-existentes** e nenhuma toca `AssetController` ou `SmokeRoutesTest`:
todas são `QueryException` — `SQLSTATE[23000] … foreign key constraint fails
(oimpresso_staging.assets, CONSTRAINT assets_created_by_foreign)` — fixtures de
`MultiTenantIsolationTest` (4) e `CrossTenantAssetTest` (4) inserindo `assets` sem
`created_by`. É dívida de fixture do módulo, **não deste PR**.

**Arquivo isolado, estado final:** `6 passed (6 assertions)` — os 4 cenários `Route::has()`
originais **intactos** (o diff é `+145 / -0` nos dois arquivos: puramente aditivo) + os 2 novos.

**Prova de não deixar rastro na base:** após a rodada final, a varredura por fixtures órfãos
devolveu `encontrados=0`. O checkout do container foi restaurado (`git checkout --` só nos
**meus** dois paths, conferidos limpos antes).

```
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test Modules/AssetManagement/Tests/ --colors=never"
```

## 6 · Placar

```
Patrimonio: entregue 0 de 6 · próximo 2 · em curso 3 · pendente 0 · bloqueada 1
  03 [em curso ] Guarda asset.view no indice — sem _saida     ← antes deste arquivo
```

⚠️ **O comando do §2-bis do índice aponta para um caminho que não existe.** Ele manda
`node scripts/qa/placar-indice.mjs`; o script vive em
`prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs` (`scripts/qa/` não tem
`placar-indice`, varrido pelo índice do git). Ponteiro podre no índice — **conserto é do dono
do 00-INDICE**, não do meu prefixo.

E, como a errata §3 previu, a prova declarada da thread 03 (`contem "asset.view"`) **já
passava antes de qualquer trabalho**, por casar com `asset.view_all_maintenance`. Agora ela
passa de verdade — mas quem quiser uma prova que discrimina deve usar `can('asset.view')`.

---

## Achados fora do meu escopo — registrados, **não** consertados

Ficam aqui para não virarem "descoberta" futura (§5 2026-08-08).

1. **O gêmeo Tier 0 está DENTRO do meu arquivo** — `AssetController.php:110` **(medido
   pós-PR; `:97` pré-PR)** tem a **cópia literal** da subconsulta sem `business_id` que a
   thread 01 conserta no Service: `(SELECT SUM(COALESCE(AR.quantity, 0)) FROM
   asset_transactions AS AR WHERE(AR.asset_id=assets.id AND AR.transaction_type='revoke'))
   as revoked_qty`. Já catalogado na
   [errata do playbook §4](../../../../CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md),
   que nota o **alcance invertido** (o site do Controller é o **índice**; o do Service tem 1
   consumidor). **Não entrou neste PR** porque (a) 1 PR = 1 intent e (b) o oráculo dele é o
   `CrossTenantAssetTest`, que é **prefixo da thread 01** — consertar aqui invadiria a Lei 1.
   O mesmo padrão sem tenant, via `AT.parent_id`, está em `:443` e `:455` (pré-PR `:430`
   e `:442`), ambos dentro do `dashboard()`.
2. **`dashboard()` sem gate de assinatura** — além da §3, ele não checa
   `assetmanagement_module`. Provavelmente deliberado (é a landing do menu), mas fica dito.
3. **Thread irmã 01 ativa no mesmo container** — durante esta sessão o
   `oimpresso-staging` acumulou `AssetAllocationService.php` + `CrossTenantAssetTest.php`
   modificados (prefixo dela, **disjunto do meu**, como o §2 do índice previu). **Não toquei**
   nos arquivos dela (LC-23). Um dos 8 vermelhos é o teste novo dela, pela mesma FK
   `assets_created_by_foreign` — vale ela conferir.

## O que eu **não** fiz, e por quê

- **Não criei permissão** — `asset.view` já existia (§4). A trava do PASSO 1 nunca chegou a disparar.
- **Não apliquei a guarda no `dashboard()`** — parada (b), §3.
- **Não mexi em `Services/`, `AssetAllocationController` nem nas views Blade** — fora do prefixo.
- **Não mergeei** — merge é [W] (R10).
