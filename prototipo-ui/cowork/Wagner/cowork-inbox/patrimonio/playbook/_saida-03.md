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
>
> **Cruzado com a thread 04 (medição):** o veredito D9 dela **confirma** esta medição, no
> mesmo arquivo e linha (`DataController.php:31`), e acrescenta o consumidor a montante que
> eu não tinha mapeado — `app/Http/Controllers/RoleController.php:101` e `:221`
> (`getModuleData('user_permissions')`), que é quem renderiza os checkboxes de
> `/roles/{id}/edit`. Ela também derrubou dois riscos que estavam em aberto: o prefixo
> (`asset.*` vive, `assetmanagement.*` não existe) e um **defeito real no meu teste**, que
> está consertado aqui — §2-bis (ii).

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

### 2-bis · Dois defeitos do próprio teste, achados e consertados na sessão

**(i) A limpeza não rodava.** A 1ª versão limpava os fixtures em `->afterEach()` encadeado
ao `it()`. **Medido no CT 100: não executou** — 4 rodadas deixaram **8 usuários e 1 role
órfãos** na base, que é clone de prod e **não se limpa entre runs**. A mesma função, chamada
à mão via `tinker`, apagou os 8 **sem uma exceção** → o defeito era o *gancho*, não a lógica.
Trocado por `try/finally` dentro do closure (limpa inclusive quando o assert falha) +
`withTrashed()` (o `App\User` usa SoftDeletes). **Órfãos removidos**; prova abaixo.

**(ii) O `MORDE` NÃO "teria passado" pelo motivo errado — ele PASSOU. Achado da thread 04,
e a correção é minha.** Ela mediu que, **num banco limpo, `asset.view` não existe na tabela
`permissions`**: o seeder do módulo é vazio (`AssetManagementDatabaseSeeder`) e as permissões
nascem sob demanda em `RoleController::__createPermissionIfNotExists()` (`:495`), só quando
alguém salva um Role. A minha 1ª versão só criava a Permission no ramo `comPermissao: true`.

⚠️ **Errata da minha própria 1ª redação, registrada e não apagada.** Escrevi aqui, no commit
e no PR que *"no CT 100 `asset.view` já existe, por isso o teste passou pelo motivo certo"*.
**É falso.** Medido:

```
asset.view  id=194  created_at=2026-09-08 10:02:15
outras asset.*: (nenhuma — nem asset.create existe no catálogo)
```

A permissão nasceu **hoje, na minha própria sessão**, criada pelo `firstOrCreate` do cenário
`CN`. Quando eu a "medi como pré-existente" (`asset_view_existe=SIM`), já tinha rodado o
teste várias vezes — eu estava medindo o meu próprio rastro. A prova de que não é do
ambiente: `asset.create` **não existe** no catálogo, embora a guarda de `create()` esteja em
produção há tempo; se o ambiente tivesse materializado as permissões do módulo, teria as duas.

E o defeito é pior do que "em CI seria": o teste era **não-determinístico**, porque o Pest
roda em ordem aleatória e só o `CN` criava a permissão. Na **primeira** rodada desta sessão
(seed `1788872533`) a ordem foi `cenário 1 → MORDE → cenário 3 → cenário 2 → CN → cenário 4`:
o `MORDE` executou **antes** do `CN`, com a tabela ainda sem a permissão. Ou seja, aquele
verde **passou pelo motivo errado**, e teria voltado a passar ou não conforme o seed.

Conserto em duas partes: o `firstOrCreate` da Permission subiu para **fora** do `if` (o que
varia entre cenários é o usuário **ter** a permissão, nunca ela existir), e o `MORDE` ganhou
um **canário de 2 asserts** que trava o pressuposto —

```php
expect(Permission::where('name', 'asset.view')->where('guard_name', 'web')->exists())->toBeTrue();
expect($user->can('asset.view'))->toBeFalse();
```

Sem eles, remover o `firstOrCreate` deixaria o teste verde pelo motivo errado em silêncio.
O bite-test pós-conserto é mais forte que o anterior: **os 2 asserts do canário passam** e
só o `assertStatus` falha (`received 200`) — ou seja, fica provado na mesma execução que o
`200` vem da **guarda ausente**, com o pressuposto verificado.

> ⚠️ **Ressalva honesta:** o canário **não foi provado por mutação**. A permissão agora existe
> no staging (criada pelo meu próprio teste, ver errata acima), então remover o `firstOrCreate`
> ali não o faria falhar hoje. Provar exigiria apagá-la primeiro. O canário é logicamente
> correto e passa; a demonstração de mordida fica para uma lane de DB fresco — que é
> exatamente onde ele importa.

### 2-ter · Risco de deploy levantado pela thread 04 — **medido, e a medição NÃO conclui**

A ressalva dela: em business onde ninguém nunca salvou um Role com `asset.view`, a permissão
não existe, `can()` devolve `false`, e a guarda nova dá **403 para todos** naquele tenant —
inclusive para quem hoje usa a tela.

Tentei medir no CT 100 e **o ambiente não sustenta a extrapolação**:

| medida (CT 100 staging) | valor |
|---|---:|
| `total_businesses` | **4** |
| businesses com bem cadastrado | 1 (o **98**, meu tenant de teste) |
| roles carregando `asset.view` | 0 |
| `asset.create` no catálogo | **não existe** |

**Prod tem 82 businesses** (proibicoes.md, medido 2026-07-28). Com 4, este ambiente **não é**
o clone de prod que eu havia suposto, e nenhum número acima extrapola. Não afirmo impacto
zero — **não medi produção**, e não tenho como daqui.

O que se pode dizer sem inventar: a guarda de `create()`/`edit()`/`destroy()` já está em
produção com o **mesmo** mecanismo, então tenant que cadastra bem já materializou as
permissões dele. O risco residual é o tenant que **só lista e nunca cadastrou**. É decisão de
[W] se isso pede canary — e é uma frase no PR, não uma suposição minha.

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

**Decisão é de [W]** — é escopo por dono, não `view`.

**Dado novo da thread 04, que torna o `dashboard()` PIOR do que eu havia medido:** ele não
tem **nem o gate de assinatura** — é o único método público que vai direto para a query
(`:441` pega o `business_id`, `:443` já consulta) sem nenhum `abort(403)`. Isso é um buraco
**diferente** do desta thread (assinatura ≠ permissão de tela) e **não** é "o mesmo buraco"
que o passo 4 manda absorver, então segue fora deste PR. Fica dito porque é barato e não
quebra perfil legítimo nenhum: quem chega pelo menu é do business que assina.

⚠️ **E há um motivo forte para NÃO encostar no corpo do `dashboard()`** — achado inédito da
thread 04 (`_saida-04.md` §7b/§7c): `:443` e `:455` filtram só por `receiver` sem
`business_id`, e o bloco de garantias tem `orWhereNull('aw.end_date')` **fora do closure**,
o que faz o SQL virar `(business_id = X AND …) OR (end_date IS NULL)` — **vazamento
cross-tenant real**. Não tem dono em thread nenhuma. Mexer ali agora quebraria 1 PR = 1
intent e invadiria o terreno da 01.

## 3-bis · O `orWhereNull` do `dashboard()` — **VEREDITO: confirmado, e ganha PR próprio AGORA**

O coordenador me devolveu esta decisão pedindo o veredito registrado. Aqui está.

**Primeiro, medi — não aceitei o enunciado.** `AssetController.php:480-489` (pós-PR):

```php
$expiring_assets = Asset::where('assets.business_id', $business_id)
        ->leftjoin('asset_warranties as aw', 'aw.asset_id', '=', 'assets.id')
        ->where(function ($q) {
            $q->whereRaw('CURDATE() BETWEEN start_date AND end_date')
                ->whereRaw('DATEDIFF(end_date, CURDATE()) <= 30')
                ->whereRaw('DATEDIFF(end_date, CURDATE()) > 0');
        })
        ->orWhereNull('aw.end_date')          // ← FORA do closure
        ->select('assets.name', 'asset_code', 'end_date')
```

**Confirmado.** `AND` liga mais forte que `OR`, então o SQL é
`(assets.business_id = X AND datas…) OR (aw.end_date IS NULL)` — o `OR` escapa do filtro de
tenant, e o `select` traz `assets.name` + `asset_code`. Todo bem **sem garantia**, de
**qualquer** empresa, entra no dashboard de todas. Não depende de dado corrompido: vaza sempre.

**Veredito: NÃO entra no PR #7008 — e NÃO fica sem dono.** O coordenador ofereceu duas saídas
("entra no seu PR" ou "fica aberto até o merge"). **Escolho uma terceira**, que ele não
considerou: **abrir PR próprio imediatamente**, empilhado sobre este. Razões:

1. **1 PR = 1 intent** (Tier A). "Mesmo arquivo" não é "mesmo intent": um é guarda de
   autorização, o outro é isolamento multi-tenant.
2. **Vazamento cross-tenant Tier 0 merece revisão dedicada, não carona.** Misturado a um PR
   de permissão, ele é aprovado junto com o resto — e é a metade mais perigosa das duas.
3. **A objeção do coordenador ("ficar sem dono") não sobrevive à terceira opção.** Ele propôs
   abrir thread *"assim que o seu PR mergear"*; abrir agora remove a dependência de merge.
4. **Lei 1 respeitada:** o arquivo é meu, então sou eu quem abre — não delego nem espero.

O PR do `:467` sai empilhado sobre esta branch (as regiões não se tocam: `index()` ~`:82`,
`dashboard()` ~`:485`), com teste de isolamento próprio.

⚠️ **E ele NÃO leva a guarda de permissão junto** — §3 continua valendo: `asset.view` no
`dashboard()` quebraria o colaborador que vê só o próprio bem. Consertar o vazamento **não**
implica fechar a porta; são decisões separadas, e a segunda é de [W].

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
| `app/Http/Controllers/RoleController.php` | `:101`, `:221` | `getModuleData('user_permissions')` — **é quem renderiza os checkboxes de `/roles/{id}/edit`**, ou seja, a permissão é viva, não só declarada *(mapeado pela thread 04)* |
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
| **baseline** (árvore 100% original) | 7 | 61 | 139 |
| **com a guarda** | 7 | **63** | **143** |
| **delta** | **0** | **+2** | **+4** |

O delta bate **exatamente**: `+2` testes e `+4` assertions — 3 no `MORDE` (2 do canário +
`assertStatus`) e 1 no `CN`. Prova de que eles **executaram**, não pularam (teste que pula
sai com exit 0 e não conta assertion).

⚠️ **Este par foi re-medido.** A 1ª medição desta sessão deu `8 → 8 failed` / `142 → 144`
porque a **thread 01 tinha o `CrossTenantAssetTest.php` modificado no mesmo container**, com
um teste novo dela que falhava pela mesma FK. Ela reverteu o arquivo no meio da sessão
(HEAD do container inalterado em `755f6de79`, `md5` do arquivo mudou, `git status` limpou),
o que mudou o denominador. Os números acima são o par **honesto**: baseline e depois medidos
com a **mesma** árvore, com o arquivo dela já revertido. Registro a 1ª medição em vez de
apagá-la — o delta era o mesmo (`0` regressões), só o denominador é que era outro.

As **7 falhas são pré-existentes** e nenhuma toca `AssetController` ou `SmokeRoutesTest`:
todas são `QueryException` — `SQLSTATE[23000] … foreign key constraint fails
(oimpresso_staging.assets, CONSTRAINT assets_created_by_foreign)` — fixtures de
`MultiTenantIsolationTest` e `CrossTenantAssetTest` inserindo `assets` sem `created_by`.
É dívida de fixture do módulo, **não deste PR**.

**Arquivo isolado, estado final:** `6 passed (8 assertions)` — os 4 cenários `Route::has()`
originais **intactos** (o diff é `+174 / -0` nos dois arquivos: puramente aditivo) + os 2 novos.

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
2. **`dashboard()` sem gate de assinatura** — ver §3. Confirmado pela thread 04: é o único
   método público que vai direto à query sem `abort(403)` nenhum. Buraco **diferente** do
   desta thread; fix pequeno e sem perfil legítimo quebrado, mas é outro intent.
3. **O `SCOPE.md` do módulo está errado, e isso NÃO é decisão pendente** — ele declara
   `permission_prefix: assetmanagement.*`, mas a thread 04 mediu que `assetmanagement.*`
   **não existe como permissão em lugar nenhum do repo** (as 56 ocorrências de
   `assetmanagement.<palavra>` são span OTel/log; controle positivo com `'asset\.` deu 30
   hits). O prefixo vivo é `asset.*`. Ou seja, o **item 2 do RESÍDUO é errata de doc, não
   fork de [W]** — some a única dúvida que poderia pesar sobre esta thread. Conserto do
   `SCOPE.md` fica fora do meu prefixo.
4. **Vazamento cross-tenant no corpo do `dashboard()`** — achado inédito da thread 04
   (`_saida-04.md` §7b/§7c): consultas filtrando só por `receiver` sem `business_id`, e
   `orWhereNull('aw.end_date')` fora do closure, virando
   `(business_id = X AND …) OR (end_date IS NULL)` na lista de garantias. **Sem dono em
   thread nenhuma.** Não encostei — seria 2º intent e terreno da 01.
5. **Thread irmã 01 ativa no mesmo container** — durante esta sessão o `oimpresso-staging`
   teve `AssetAllocationService.php` + `CrossTenantAssetTest.php` modificados por ela e
   depois **revertidos** no meio do meu trabalho (HEAD do container inalterado, `md5` do
   arquivo mudou, `git status` limpou). Isso mudou o denominador da suíte no meio da medição
   — daí o par ter sido re-medido (§5). **Não toquei** nos arquivos dela (LC-23), e ela não
   encostou no `AssetController.php`, como o §2 do índice previu.

## O que eu **não** fiz, e por quê

- **Não criei permissão** — `asset.view` já existia (§4). A trava do PASSO 1 nunca chegou a disparar.
- **Não apliquei a guarda no `dashboard()`** — parada (b), §3.
- **Não mexi em `Services/`, `AssetAllocationController` nem nas views Blade** — fora do prefixo.
- **Não mergeei** — merge é [W] (R10).
