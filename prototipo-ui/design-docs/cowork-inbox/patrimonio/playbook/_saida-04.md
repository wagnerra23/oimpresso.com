---
sessao: "04"
titulo: Saída da thread 04 — remedição da frente 0 (read-only)
dono: "[CL]"
medido_em: 2026-09-08
base_medida: a875e200c044 (origin/main fresco; a base do playbook cb475c0ca2f4 está 10 commits atrás)
delta_no_modulo: "ZERO — `git diff cb475c0ca2f4..a875e200c044 -- Modules/AssetManagement/` vazio (controle positivo: 49 arquivos mudaram no repo). Toda divergência abaixo é de MEDIÇÃO, não de código novo."
arquivos_de_producao_tocados: 0
invalida: "thread 02 (premissa central falsa — ver §5) · thread 03 DESTRAVADA (não invalidada) · thread 05 corroborada como barrada · §0 do 00-INDICE.md: o ÍNDICE está errado ao declarar que D1 caiu"
---

# 04 · Saída — a frente 0 remedida

> **Read-only cumprido.** Nenhum arquivo de produção tocado. Todo achado abaixo está em
> formato de âncora (`arquivo :: símbolo :: linha :: sha`), pronto pra virar thread.
>
> **Como ler os vereditos:** `CONFIRMADO` = varredura contada + linha exata.
> `REFUTADO` = varredura contada provando o contrário. `INÉDITO` = não estava em nenhuma
> das 9 alegações nem no CODE_NOTES. Onde a busca foi limitada, está escrito.

---

## 0 · O resultado que muda o plano

**O §0 do `00-INDICE.md` está errado.** Ele declara *"D1 caiu — o `&&` não se reconfirmou;
as 40 ocorrências que li nos controllers são `! (can('superadmin') || ...)`"*.

Medido: o padrão `superadmin ||` existe **18 vezes em 5 controllers** — e **zero vezes**
no `AssetMaitenanceController`, que é justamente o arquivo do D1. O padrão com `&&` existe
**7 vezes, todas nesse arquivo, em nenhum outro**. A medição de 04/09 leu o padrão
majoritário e generalizou para o arquivo que é a exceção.

| padrão | AssetMaitenance | outros 6 controllers |
|---|---:|---:|
| `can('superadmin')` OR `hasThePermissionInSubscription` | **0** | 18 |
| `can('asset.X')` AND `auth()->user()->can(` | **7** | 0 |

A thread 04 existia pra evitar PR fantasma por retrato velho. Achou o inverso: **um defeito
enterrado vivo**. O passo 0 paga nos dois sentidos.

---

## 1 · D1 — `&&` no `AssetMaitenanceController` · **CONFIRMADO**

```
arquivo  Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php  371 ln  sha b9a20bcbb13c
símbolo  index()   :63     create() :208    store()  :243
         edit()    :286    update() :322    destroy() :354
forma    if (! ((can('asset.view_all_maintenance') && can('asset.view_own_maintenance'))
                || hasThePermissionInSubscription($business_id, 'assetmanagement_module')))
```

**São dois defeitos no mesmo `if`, e é preciso separá-los:**

**(a) o `&&` contradiz o filtro 10 linhas abaixo.** `:73` faz
`if (! can('view_all_maintenance') && can('view_own_maintenance'))` — a precedência do `!` em
PHP torna isso `(!view_all) && view_own`, que é **correto**: é o escopo "vejo só as minhas".
Ou seja, `:73` foi escrito para o usuário que tem `view_own` e não tem `view_all` — e o gate
`:63` exige **as duas**, barrando exatamente esse usuário. O gate e o filtro do mesmo método
descrevem perfis incompatíveis.

**(b) o `|| subscription` anula o gate inteiro.** Como o segundo operando é a assinatura do
módulo (verdadeira para todo usuário do business), o `if` colapsa em *"o módulo está
assinado"*. **É por isso que (a) nunca apareceu em produção** — e é o mesmo buraco que a
thread 03 conserta no `AssetController::index()`, aqui com aparência de checar permissão.

**Corolário para quem pegar:** consertar só o `&&` para `||` **não muda nada em runtime**
enquanto o `|| subscription` estiver lá (LC-30: verde no CI, inerte no ar). O conserto é o
formato do `create()` do `AssetController:271` — permissão de tela **antes** do gate de
assinatura, não em `OR` com ele.

**Nota menor, sem gravidade:** `show()` `:272-:275` é stub de scaffold
(`return view('assetmanagement::show')`, sem guarda). Código morto, não superfície.

---

## 2 · D5 — whitelist com coluna morta · **CONFIRMADO, e há um segundo achado maior**

### 2a · A alegação original (coluna morta na auditoria)

```
arquivo  Modules/AssetManagement/Entities/Asset.php  130 ln  sha 83cd06b0ff9a
símbolo  getActivitylogOptions() :25-:36
linha    :30   'purchase_date', 'purchase_amount',
coluna   purchase_amount  <- NÃO EXISTE em tabela nenhuma do sistema
```

Fonte de verdade: `database/schema/mysql-schema.sql:674-:691` (o baseline que o CI semeia).
Colunas reais de `assets`: `id · business_id · asset_code · name · quantity · model ·
serial_no · category_id · location_id · purchase_date · purchase_type · unit_price ·
depreciation · is_allocatable · description · created_by · created_at · updated_at`.
`git grep purchase_amount -- database/schema/mysql-schema.sql` devolve **rc=1, zero**.

**A consequência é o achado, não a coluna:** a coluna de valor real é **`unit_price`**, e ela
**não está no whitelist**. Logo a auditoria LGPD do módulo **nunca registra alteração de
valor do bem** — que é justamente o campo que um audit trail patrimonial existe para vigiar.

### 2b · O whitelist de retenção aponta para 4 tabelas inexistentes · **INÉDITO no playbook**

```
arquivo  Modules/AssetManagement/Config/retention.php  75 ln  sha 6d4af578e839
símbolo  entities[]  :48-:53
chaves   am_assets · am_asset_transactions · am_maintenance_logs · am_warranties
```

Checado uma a uma contra o baseline schema:

| chave do config | existe? | tabela real |
|---|---|---|
| `am_assets` | **AUSENTE** | `assets` |
| `am_asset_transactions` | **AUSENTE** | `asset_transactions` |
| `am_maintenance_logs` | **AUSENTE** | `asset_maintenances` |
| `am_warranties` | **AUSENTE** | `asset_warranties` |

`git grep -E "CREATE TABLE .am_"` no schema devolve **rc=1**. Nenhuma migration do repo cria
o prefixo `am_`. Os próprios comentários do arquivo (`:43-:46`) nomeiam as tabelas **certas** —
só as chaves do array estão erradas.

**O prefixo fantasma vazou para 4 arquivos vivos:**

| arquivo | linha | natureza |
|---|---|---|
| `Config/retention.php` | :49-:52 | config |
| `Tests/Feature/LgpdComplianceTest.php` (sha 160b008925a8) | :155-:158, :174-:177 | **o "oráculo" da thread 05** |
| `Console/Commands/AssetManagementHealthCommand.php` (sha e2cccc1c4b55) | :199 | health check |
| `Http/Requests/UpdateAssetMaintenanceRequest.php` (sha 5afc44a61f7a) | **:49-:50** | **query de runtime** |

**O `LgpdComplianceTest` fica verde porque mede o array, nunca o banco** — `:152` faz
`require` do config e `:162` faz `array_key_exists`. É presence-gate (LC-11): confirma que a
chave foi digitada, não que a tabela existe. Foi ele que deixou isso passar.

O `UpdateAssetMaintenanceRequest:49` faz `DB::table('am_maintenance_logs as m')` com `join`
em `am_assets` — seria `QueryException` (base table not found) em runtime. **Não é 500 em
produção hoje só porque o Request é órfão** (ver §5).

---

## 3 · D6 — `purchase_amount` morto · **CONFIRMADO. Gravado: NÃO. Lido: NÃO.**

Não é gravado nem lido porque **não existe**. Varredura no repo inteiro: **4 ocorrências**,
nenhuma delas gravação ou leitura de banco.

| # | arquivo :: linha | o que é |
|---|---|---|
| 1 | `Modules/AssetManagement/Entities/Asset.php:30` | whitelist do activitylog |
| 2 | `prototipo-ui/cowork/patrimonio-data.jsx:126` | `CAMPOS_AUDITADOS: { valor: "purchase_amount" }` |
| 3 | `prototipo-ui/design-docs/charters/Patrimonio.charter.md:47` | **regra R6 do charter** |
| 4 | `playbook/04-remedir-frente-0.md:25` | a própria alegação |

**O alcance é maior que "coluna morta em um Model".** O erro foi copiado para o protótipo
(2) e **canonizado como lei no charter** (3) — a R6 declara textualmente o whitelist com
`purchase_amount` dentro. Pela regra de precedência de `proibicoes.md`, o charter é *lei*,
e uma tela nascida dele herdaria o campo inexistente. É a lápide de 2026-08-10 (um erro que
vira canon por cópia entre docs, sem ninguém re-medir a fonte).

**Quem pegar isto corrige os 3 sites no MESMO PR** — Model, protótipo e charter — trocando
`purchase_amount` por `unit_price`. Corrigir só o Model deixa a lei apontando para o erro.

---

## 4 · D8 — `depreciation` gravada e nunca calculada · **CONFIRMADO**

```
GRAVA   Modules/AssetManagement/Services/AssetService.php  sha ef1bd9901621
        criar()            :41   $request->only(... 'depreciation' ...)
        atualizar()        :90   idem
        normalizarCampos() :169-:170   num_uf() converte locale BR
ENTRA   Resources/views/asset/create.blade.php:84   ·  edit.blade.php:71  (texto livre)
VALIDA  StoreAssetRequest.php:67  ·  UpdateAssetRequest.php:57   ['nullable','string']
LÊ      Resources/views/asset/edit.blade.php:71  — e SÓ ele, para repopular o próprio form
CALCULA (ninguém)
```

Varredura contada: **32 ocorrências** de `depreciation` no repo. Descontando 16 arquivos de
tradução (`lang/*/lang.php:17`), o schema e os docs, **sobram os sites acima**. Nenhuma
aritmética: zero `book_value`, zero valor residual, zero relatório. O número é digitado,
normalizado, gravado — e relido apenas para ser reexibido.

**Não é achado órfão — já tem dono declarado:** `memory/requisitos/AssetManagement/SPEC.md:96`
carrega `US-ASSET-W01: Depreciação automática linear/SAC (depreciation_rate + book_value)`
marcada com cadeado. O SPEC **já sabe**. Isso conecta com a decisão [W] nº 6 do RESÍDUO
(*"linear ou SAC, com que fonte contábil?"*) — **é decisão de produto pendente, não bug a
consertar.** Nenhuma thread deve "arrumar" isto.

**Aviso Tier 0 para a thread de UI (06), não defeito hoje:** `unit_price` (`:167`),
`quantity` (`:161`) e `depreciation` (`:170`) passam por `Util::num_uf` — a função do
incidente de 2026-06-05 (venda inflada cerca de 100 mil vezes). Hoje a entrada vem de Blade
com máscara `input_number`, então o vetor do incidente (frontend mandando float
locale-ambíguo como `204.99605`) **não se aplica**. Ele passa a se aplicar no dia em que a
tela virar React. *Ressalva honesta: identifiquei o vetor por leitura; não medi o
comportamento do `num_uf` neste módulo.*

---

## 5 · D7 — `exists` sem tenant no `StoreAssetAllocationRequest` · **CONFIRMADO, e é inerte**

```
arquivo  Modules/AssetManagement/Http/Requests/StoreAssetAllocationRequest.php  61 ln  sha c1d3ad6d5709
símbolo  rules()  :47-:59
linha    :51   'asset_id' => ['required', 'integer', 'exists:assets,id']
falta    ->where('business_id', $businessId)  — a regra aceita asset de QUALQUER business
extra    :54   'receiver' => ['nullable','integer']  — sem exists nenhum (aloca p/ user de outro business)
```

**MAS — e isto é o veredito que importa — o Request é ÓRFÃO.**

```
Modules/AssetManagement/Http/Controllers/AssetAllocationController.php  sha 0371c2680548
:186   public function store(Request $request)       <- Illuminate\Http\Request CRU
:266   public function update(Request $request, $id) <- idem
imports (:5-:16): NENHUM FormRequest do módulo
```

Varredura no repo inteiro (`git grep StoreAssetAllocationRequest`): **8 linhas**, e nenhuma é
`use` / type-hint / `app()`. São: a declaração da classe, um comentário em
`StoreAssetMaintenanceRequest:15`, o `SUPERFICIE.md:31` (inventário derivado) e 5 linhas dos
próprios docs do playbook.

**Três dos cinco FormRequests do módulo são órfãos** (varredura no repo inteiro, contada):

| FormRequest | ocorrências no repo | injetado por | estado |
|---|---:|---|---|
| `StoreAssetRequest` | — | `AssetController:298` | **vivo** |
| `UpdateAssetRequest` | — | `AssetController:371` | **vivo** |
| `StoreAssetAllocationRequest` | 8 | — | **órfão** |
| `StoreAssetMaintenanceRequest` | 2 | — | **órfão** |
| `UpdateAssetMaintenanceRequest` | 3 | — | **órfão** |

Isto **não é descoberta minha** — está registrado desde 04/09 no cabeçalho do
`.github/workflows/modules-pest.yml:31-:35` (sha 2d2de2dbaa43), commit `d6457184ea`, PR #6784.
Minha medição **corrobora** e acrescenta o alcance (quais 3, e quem injeta os 2 vivos).

### AVISO À THREAD 02 (o pedido explícito desta thread)

A thread 02 declara, em `02-trava-de-saldo.md:34`: *"Mensagem de validação em PT-BR (C2), no
`StoreAssetAllocationRequest` — **é onde as outras regras do módulo já moram** —, não
`abort()` cru no Service."*

**A premissa está errada nas duas metades.** As regras desse arquivo **não moram lá: não
rodam**. `store()` recebe `Request` cru; nenhuma linha do `rules()` é executada. Uma trava de
saldo escrita ali passa no CI e **não trava nada em produção** — LC-30 na forma exata.

**As duas saídas honestas para a 02, ambas mudando o escopo dela:**

1. **Ligar o Request primeiro** — trocar a assinatura de `AssetAllocationController@store`
   para `StoreAssetAllocationRequest`. Isso **acende de uma vez** todas as regras hoje
   dormentes (`:50-:58`), incluindo o `exists` sem tenant do `:51` — que aí passa a ser
   vazamento **alcançável**. Ou seja: **D7 vira pré-requisito da 02, não recado paralelo.**
   E o arquivo sai do prefixo isolado dela (passa a tocar `AssetAllocationController`, que a
   thread 03 lista em `nao_toca`).
2. **Pôr a trava no Service**, onde o código vivo está — contrariando o texto da própria 02.

Escolher entre as duas é decisão de plano, não de código. Ela não é minha: **registro e paro.**

⚠️ **RISCO DE O AVISO NÃO CHEGAR — e isso é decisão do coordenador, não minha.** Medido:

- O `§3 · Abertura de thread` do `00-INDICE.md` manda a sessão nova ler **(1)** Constituição
  **(2)** índice §1/§2/§7 **(3)** o próprio `NN-*.md` **(4)** SCOPE **(5)** a faixa da âncora —
  e **não menciona os `_saida-NN.md` das outras threads**.
- O `02-trava-de-saldo.md` cita `_saida` **uma vez**, no passo 6 da execução, e é o *dela*.
  **Zero ponteiros** para este aviso.
- Enquanto isso, o `02-trava-de-saldo.md:34` segue instruindo, com todas as letras, escrever a
  trava no `StoreAssetAllocationRequest`.

Ou seja: uma sessão da 02 aberta pelo procedimento canônico **não lê este arquivo** e vai
direto para o Request órfão. O campo `invalida:` só corrige o plano se alguém o ler — e o
procedimento não manda ler. **Não posso fechar isso**: meu prefixo é só o `_saida-04.md`; quem
pode é o dono do índice e dos `NN-*.md`. Escalado.

**Duas saídas estruturais** (a escolha é do dono do índice): trocar a instrução do
`02-trava-de-saldo.md:34` e apontar este §5 — resolve **este** caso; ou acrescentar ao `§3` um
item *"leia os `_saida-NN.md` das threads já fechadas"* — fecha **a classe**, e vale para os
próximos módulos.

⛔ **Contorno JÁ CONSIDERADO E DESCARTADO — não re-propor** (decisão da thread 01, registrada
aqui para não ser reinventada): deixar um **comentário de aviso no `AssetAllocationService.php`**,
que está no prefixo das duas threads e por onde a 02 inevitavelmente passaria. **Rejeitado:**
comentário de código que afirma estado de *outro* arquivo é a lápide §5 de 2026-08-17
(*comentário que se autodefende com medição obsoleta*) — ele apodreceria no instante em que
alguém ligasse ou removesse o Request órfão, e passaria a instruir errado com cara de canon.
**O aviso pertence ao plano da 02, não ao código de ninguém.**

**Backstop, não plano:** quando a 02 abrir, ela provavelmente trocará mensagens com as sessões
irmãs — foi o que as threads 01, 03 e 04 fizeram. Aí quem estiver de pé avisa. Isso é rede de
segurança acidental e depende de sessões vivas; **não substitui** corrigir o `02-trava-de-saldo.md`.

---

## 6 · D9 — `asset.*` (código) × `assetmanagement.*` (SCOPE) · **RESOLVIDO — a thread 03 está DESTRAVADA**

```
arquivo  Modules/AssetManagement/Http/Controllers/DataController.php  165 ln  sha 63a1b8ec25c4
símbolo  user_permissions()  :27-:65
linha    :31   'value' => 'asset.view'     <- A PERMISSÃO EXISTE
         :36 asset.create · :41 asset.update · :46 asset.delete
         :51 asset.view_all_maintenance · :58 asset.view_own_maintenance
```

**A condição de PARADA da thread 03** (*"usar `asset.view` só se ela existir no
seeder/registro; se não existir, a thread PARA"*) **não se aplica. Pode seguir.**

**A permissão é viva, não só declarada.** O consumidor é
`app/Http/Controllers/RoleController.php:101` e `:221` —
`$this->moduleUtil->getModuleData('user_permissions')` —, que renderiza os checkboxes em
`/roles/{id}/edit`. É o mesmo mecanismo do incidente de 2026-07-30 (handoff 12:10), onde
ficou provado que **o que não está declarado ali some no primeiro save**. Já há 2 consumidores
de `asset.view` no código: `Resources/views/layouts/nav.blade.php:21` (`@can`) e
`DataController.php:109` (item de sidebar).

**O prefixo do SCOPE não existe.** `memory/requisitos/AssetManagement/SCOPE.md` declara
`permission_prefix: assetmanagement.*`. Medido no repo inteiro:

- `assetmanagement.<palavra>` devolve **56 ocorrências**, e **zero** são permissão. São nomes
  de span OTel (`assetmanagement.asset.criar`, `assetmanagement.warranty.add`, ...) e chaves de log.
- Sonda direta por `can(` / `@can(` / `'value' =>` seguido de `'assetmanagement.` devolve
  **rc=1, zero**. Controle positivo, mesma sonda com prefixo `asset.`, devolve **30 hits**.

Logo o D9 **não é "código diverge do SCOPE, [W] escolhe"**. É: **o código tem um prefixo só
(`asset.*`) e o SCOPE declara um que não existe em lugar nenhum.** `assetmanagement_module`
é o nome da *feature de pacote* (subscription), não prefixo de permissão — o SCOPE confundiu
os dois. **Não é decisão de [W]: é errata do SCOPE**, e o item 2 do RESÍDUO do índice
(*"prefixo: `asset.*` ou `assetmanagement.*`?"*) pode ser fechado sem consultá-lo.

### Onde a permissão NASCE — e a ressalva de deploy que sai daí

O seeder do módulo é **vazio** (`AssetManagementDatabaseSeeder.php:15-:20`, só
`Model::unguard()`), e `asset.*` não aparece em catálogo nenhum fora do módulo
(`git grep "'asset\.[a-z_]+'"` excluindo o módulo devolve **rc=1**).

**Também não nasce na instalação.** `InstallController.php` não menciona `Permission` /
`permission` / `firstOrCreate` — **rc=1, zero**. Quem materializa é
`app/Http/Controllers/RoleController.php::__createPermissionIfNotExists()` `:495-:512`, e só
quando **alguém salva um Role** em `/roles/{id}/edit`. Antes disso a linha não existe na
tabela `permissions`.

*(Medido ao cruzar com a thread 03, que havia registrado "lido pelo módulo na instalação".
A leitura dela do `DataController` está correta; é a origem da materialização que é outra.)*

**Duas consequências, de naturezas diferentes:**

1. **Teste** — num banco limpo `asset.view` não existe, então um teste de 403 que não crie a
   Permission passa **pelo motivo errado** (`can()` false por ausência, não por negação).
   A thread 03 cobriu isso com `Permission::firstOrCreate` no setup, **e declarou que o verde
   dela não é evidência do registro** — a evidência é a leitura do `DataController:31`.

   ⚠️ **E não era risco hipotético: já tinha se materializado.** A thread 03 mediu no CT 100
   `asset_view_existe=SIM` e a princípio leu isso como "a permissão existe no ambiente"; ao
   reconferir, achou que **o próprio teste dela a criara** (`id=194`, `created_at` da mesma
   sessão — e `asset.create`, que ninguém cria, sequer está na tabela). Como o Pest roda em
   **ordem aleatória** e só um cenário criava a permissão, a primeira rodada da sessão dela
   (seed `1788872533`) executou o MORDE **antes** do cenário que a criava: aquele verde
   **passou pelo motivo errado**. Ela registrou como errata no `_saida-03.md`.

   **A lição generaliza para além deste teste:** *"a permissão existe no banco"* medido
   **depois** de rodar a suíte não distingue o que o ambiente tinha do que o teste acabou de
   criar. Com ordem aleatória, isso vira um teste que passa ou não conforme a seed — verde
   instável indistinguível de verde real.
2. **Deploy** — em business onde ninguém nunca salvou um Role com `asset.view` marcada, uma
   guarda nova dá **403 para todos** naquele tenant. **Calibrando:** o mesmo já vale para
   `create()` `:271`, `edit()` `:339` e `destroy()` `:399`, que estão em produção — quem
   cadastra bem já passou pela materialização. O risco fica restrito a tenant que só **lista**
   e nunca cadastrou. **Não medi o banco de produção** e não afirmo quantos estão nesse
   estado; fecha com um `SELECT` em `permissions where name like 'asset.%'` por business no
   CT 100, ou declara-se a ressalva no PR e o [W] decide sobre canary.

### O defeito é MATERIAL — medição da thread 03, não minha

Esta thread é read-only e não roda Pest, então eu só podia mostrar a **ausência** do gate.
A thread 03 rodou bite-test no CT 100 com o `AssetController` **original** e obteve
`Expected response status code [403] but received 200`: usuário sem `asset.view` recebia
**200** na listagem do patrimônio inteiro. **A consequência está provada, não inferida** — e o
crédito é dela.

---

## 7 · Item extra — `AssetController:97` × `AssetAllocationService:112`

### 7a · `:97` é o **mesmo defeito** — SIM, byte-a-byte

```
Modules/AssetManagement/Http/Controllers/AssetController.php  sha 085fd16d516a
símbolo  index()  :97
Modules/AssetManagement/Services/AssetAllocationService.php   sha bc036e9ed701
símbolo  quantidadeDisponivel()  :112
```

As duas linhas são a **mesma string**: subconsulta `SUM(COALESCE(AR.quantity,0))` sobre
`asset_transactions AS AR`, correlacionada por `AR.asset_id=assets.id` mais
`AR.transaction_type='revoke'`, **sem `AR.business_id`** — enquanto a query externa filtra
(`:91` no Controller, `:107` no Service). Corrobora o
`CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md §4`, que já registrou o gêmeo.

**⚠️ CALIBRAGEM CORRIGIDA (após cruzamento com a thread 01) — a 1ª redação subestimava, e fica
registrada.** Eu havia escrito: *"o vazamento só materializa se existir linha de
`asset_transactions` cujo `business_id` divirja do asset — é defesa-em-profundidade ausente,
não exfiltração garantida"*. A pré-condição é real (`assets` está travado no tenant e a
correlação por `asset_id` amarra ao dono), **mas ela não exige dado corrompido prévio** — e é
isso que eu não tinha medido.

A thread 01 mediu **como a linha órfã nasce**. Verifiquei os dois ramos, e o quadro é pior do
que os dois enunciados iniciais:

| ramo | grava sem amarrar `asset_id` ao business? |
|---|---|
| **allocate** — `AssetAllocationController@store` `:186` → `AssetAllocationService::criar()` `:34-:53` | **sim** — `Request` cru (o arquivo tem **0** `validate()`), `$request->only(… 'asset_id' …)`, `business_id` da sessão em `:39`, `AssetTransaction::create()` em `:53`, sem checar o dono do asset |
| **revoke** — `RevokeAllocatedAssetController` `:151-:153` | **sim** — **0** `validate()` e 0 FormRequest no arquivo inteiro (rc=1); `asset_id` cru do `$request->only()` |

Um usuário autenticado em B, com o módulo assinado, posta o `asset_id` de A e a linha nasce.
**Formulação correta: "pré-condição alcançável por request autenticado"** — nem *"exfiltração
demonstrada"*, nem *"só com dado corrompido"*.

⚠️ **Correção ao enunciado da própria 01, e ela importa:** a thread 01 listou
`StoreAssetAllocationRequest:51` (`exists:assets,id` global) como um dos ramos. **Ele não é
ramo — é o Request órfão do §5.** Nenhum controller o injeta; o `rules()` nunca executa. Citá-lo
como caminho faz o próximo agente consertar aquele `exists` achando que fechou a porta, sem
fechar nada — a lápide de 2026-08-02 (*o fix pousa na cópia que o consumidor não usa*)
reproduzida dentro do enunciado. **O caminho vivo é o `store()` cru → `Service::criar()`**, que
é a linha do quadro acima.

**Limite do que se afirma:** ninguém exercitou o POST end-to-end (fora do prefixo de todas as
threads). Isto é medição do **gate** por leitura, não exploração demonstrada.

**Consequência prática:** um `CrossTenantAssetTest` com dados normais **fica verde antes do
fix** — gate que não morde (LC-11). O teste da 01 tem de **fabricar** a transaction órfã
(`asset_transactions.business_id = 99` apontando para asset de `business_id = 1`) para ficar
vermelho primeiro. Sem isso, a 01 entrega prova que não prova.

### 7b · `:430` e `:442` são **defeito diferente**, e maior

```
símbolo  dashboard()  :423-:490
:427   AssetTransaction::where('receiver', auth()->user()->id)       <- SEM business_id
:430   (SELECT SUM(quantity) FROM asset_transactions as AT
        WHERE AT.parent_id=asset_transactions.id AND ...='revoke')   <- SEM business_id
:435   AssetTransaction::where('asset_transactions.receiver', ...)   <- SEM business_id
:442   idem :430, dentro do SUM por categoria
```

O `CODE_NOTES §4` os chama de *"terceira ordem, mesmo padrão"*. **Medido, é mais que isso:**
não é só a subconsulta — as **queries externas** `:427` e `:435` também não filtram
`business_id`, e correlacionam por `parent_id` em vez de `asset_id`, então **falta o amarre
que salva o `:97`**. Não é a mesma família do `:112`.

**A assimetria dentro do mesmo método é a prova:** o bloco `if ($is_admin)` logo abaixo
filtra `business_id` nas **4** queries (`:454`, `:458`, `:467`, `:478`). O autor sabia; as
duas queries do usuário comum ficaram de fora. `$business_id` está capturado em `:425` e não
é usado até `:454`.

### 7c · **INÉDITO** — `orWhereNull` fora do closure quebra o filtro de tenant

```
arquivo  Modules/AssetManagement/Http/Controllers/AssetController.php  sha 085fd16d516a
símbolo  dashboard()  :467-:476   ($expiring_assets — "garantias vencendo")
:467   Asset::where('assets.business_id', $business_id)
:469       ->where(function ($q) { ...CURDATE() BETWEEN start_date AND end_date... })
:474       ->orWhereNull('aw.end_date')          <- FORA do closure
:475       ->select('assets.name', 'asset_code', 'end_date')
```

SQL gerado: `WHERE assets.business_id = ? AND (...) OR aw.end_date IS NULL`. Como `AND` tem
precedência sobre `OR`, isso é `(business_id = X AND ...) OR (aw.end_date IS NULL)` — e **todo
asset de qualquer business sem garantia cadastrada entra na lista**, com `name` e `asset_code`
renderizados em `dashboard.blade.php`.

Diferente do `:97`, aqui **não há nada correlacionando ao tenant** no ramo do `OR`. É o único
dos três com vazamento cross-tenant sem pré-condição de dado corrompido.

**PROVA FECHADA — executado no CT 100** (`docker exec oimpresso-staging php artisan tinker`,
`toSql()` do encadeamento real; nenhuma linha lida ou escrita no banco). A 1ª redação desta
seção dizia *"medido por construção, não executado"* — o acesso ao staging veio da thread 01,
e com ele a prova saiu de inferência para recibo:

```sql
-- COMO ESTÁ NO MAIN (defeito)
... where `assets`.`business_id` = ? and (CURDATE() BETWEEN start_date AND end_date
    and DATEDIFF(end_date, CURDATE()) <= 30 and DATEDIFF(end_date, CURDATE()) > 0)
    or `aw`.`end_date` is null

-- CONTROLE POSITIVO — a forma correta (orWhereNull DENTRO do closure)
... where `assets`.`business_id` = ? and ((CURDATE() BETWEEN start_date AND end_date
    and DATEDIFF(end_date, CURDATE()) <= 30 and DATEDIFF(end_date, CURDATE()) > 0)
    or `aw`.`end_date` is null)
```

O `business_id` fica **fora** do grupo do `OR` na forma atual. A diferença entre vazar e não
vazar é **um par de parênteses**, e o controle positivo prova que a sonda distingue os dois
casos — não é leitura minha do builder, é o SQL que o Laravel emite.

`orWhereNull` é a **única** ocorrência no módulo, e nenhum doc do Patrimônio o registra
(`git grep -lni "precedencia|precedência"` nos docs do módulo devolve rc=1).

**Nenhuma das 6 threads tinha este site** quando ele foi medido — vira thread nova ou entra na
01 com escopo ampliado, e ampliar a 01 conflitaria com o `nao_toca: Http/Controllers/` dela.

**✅ DESFECHO (2026-09-08, ainda nesta rodada):** deixou de ser órfão. A thread 03 abriu o
**PR #7015** (`fix(patrimonio): o orWhereNull do dashboard escapava do filtro de tenant`),
tocando `AssetController.php` + `SmokeRoutesTest.php`, empilhado sobre a branch do #7008 —
PR próprio, para preservar 1 PR = 1 intent sem criar dependência de merge. Verificado por
`gh pr view 7015` (OPEN). **Sai da lista de órfãos escalados.**

As duas provas são complementares e nenhuma sozinha bastava: esta thread mostrou, por
`toSql()`, que **o SQL está errado** (o `business_id` fora do grupo do `OR`); a thread 03
mostrou, por bite-test, que **o dado alheio atravessa de fato** — a lista do dono contém o bem
do tenant vizinho (`⨯ MORDE: Expecting […] not to contain 'AST-DASH-TNT99'`). Diagnóstico e
consequência, medidos por vias diferentes.

### 7d · Mapa de gates do `AssetController` — o passo 4 da thread 03, respondido

Medido a pedido da sessão coordenadora. `AssetController.php` sha `085fd16d516a`, todos os
`can(` / `abort(` do arquivo cruzados com os métodos públicos:

| método | linha | gate |
|---|---|---|
| `index()` | :71 | só `:75` assinatura — **o alvo da thread 03** |
| `create()` | :269 | `:271` `asset.create` + `:277` assinatura — **o modelo correto** |
| `store()` | :298 | via `StoreAssetRequest` (FormRequest vivo) |
| `show()` | :326 | **nenhum** — mas é stub scaffold (`return view('assetmanagement::show')`), código morto |
| `edit()` | :337 | `:339` `asset.update` + `:345` assinatura |
| `update()` | :371 | via `UpdateAssetRequest` (FormRequest vivo) |
| `destroy()` | :397 | `:399` `asset.delete` + `:405` assinatura |
| `dashboard()` | :423 | **NENHUM — nem permissão, nem assinatura** |

**Veredito sobre `dashboard()`: o buraco é PIOR que o do `index()`.** O `index()` ao menos
tem o gate de assinatura (`:75`); o `dashboard()` não tem nada — `:425` pega o `$business_id`
e `:427` já consulta. É o único método público do arquivo sem `abort(403)`.

**⚠️ ERRATA (mesma sessão, após cruzamento com a thread 03) — a 1ª redação desta ressalva
estava ERRADA e fica registrada, não apagada.** Eu havia escrito: *"o `dashboard()` cabe na
thread 03 pelo critério do passo 4 dela, mas só a guarda"*. **Pôr `can('asset.view')` no
`dashboard()` quebra perfil legítimo** — é o `PARAR SE (b)` do playbook da 03, e eu o
atravessei. Medido depois que ela discordou:

```
DataController.php:109   gate do item de sidebar — aceita can('asset.view_own_maintenance') SOZINHO (é um ||)
DataController.php:117   e o item aponta para  action([AssetController::class, 'dashboard'])
```

Ou seja: um colaborador com **só** `view_own_maintenance` vê o item "Patrimônio" no menu, e o
destino dele **é** o `dashboard()`. Com a guarda `asset.view` ali, ele tomaria **403 num menu
que o próprio sistema exibiu para ele**. Some-se que o `dashboard()` filtra
`receiver = auth()->user()->id` (`:427`, `:435`) — é a **tela pessoal** do colaborador; o bloco
da empresa já está sob `if ($is_admin)` (`:453`). A regra ali não é "view", é escopo por dono.

**O que continua verdadeiro:** `dashboard()` não tem **nem o gate de assinatura** que o
`index()` tem no `:75` — é o único método público do arquivo sem nenhum `abort(403)`. Isso é
buraco real, mas o fix é **assinatura**, não permissão de tela, e é intent separado (a 03 o
registrou assim). O corpo do método continua fora de qualquer thread por causa de §7b/§7c.

**Por que a errata fica escrita:** a proibição do projeto é explícita — anti-padrão inventado
em doc canônico é pior que ausente, porque parece canon e a próxima sessão obedece. A 1ª
redação teria mandado alguém quebrar um caminho vivo.

**Falso-positivo por prefixo, confirmado:** `asset.view` **não aparece** no `AssetController`
(busca por string exata devolve rc=1). O `:145` é `asset.view_all_maintenance` /
`asset.view_own_maintenance`. Quem buscar por prefixo conclui, errado, que o controller já usa
`asset.view` — a thread 03 vai introduzir o **primeiro** uso dele nesse arquivo.

### 7e · A falsa cobertura tinha DUAS camadas — as defesas Tier 0 não executam uma assertion

Achado da **thread 01**, que rodou a suíte no CT 100; **verifiquei independentemente por
leitura** e confirma.

`CrossTenantAssetTest` e `MultiTenantIsolationTest` — os dois arquivos que existem para provar
isolamento Tier 0 (ADR 0093) — **morrem antes de assertar**. Todo `Asset::create()` neles omite
`created_by`, que é `int unsigned NOT NULL` com FK `assets_created_by_foreign` → `users(id)`
(`database/schema/mysql-schema.sql:674-:691`). A inserção estoura na FK.

| via | evidência | estável? |
|---|---|---|
| execução (thread 01) | falhas todas na mesma FK; restaurando ao main: `Tests: 3 failed (0 assertions)` | **não** — ver ressalva |
| leitura (esta thread) | `grep -c created_by` nos dois arquivos = **0** e **0**; coluna NOT NULL + FK no schema | **sim** |

**Ressalva sobre o número de falhas:** a contagem oscilou (8 → 7) durante as medições, porque
a thread 01 tinha o `CrossTenantAssetTest.php` modificado no container e reverteu no meio da
sessão — a thread 03 detectou pelo md5 e teve de re-medir o par dela. **Portanto o número
exato de falhas é datado e volátil, e não deve ser citado como fato do módulo.** O que é
estável, e basta para o veredito, é a leitura: `created_by` ausente em 2 de 2 arquivos contra
coluna `NOT NULL` com FK. O `0 assertions` é o dado que importa, não o `N failed`.

**Por que isto fecha o círculo do módulo.** O cabeçalho do `modules-pest.yml:23-:30` registrou
em 04/09 que os 9 testes estavam no `phpunit.xml` e **nenhuma lane os disparava** — falsa
cobertura, camada 1. Agora que passaram a rodar, descobre-se a camada 2: **os dois testes Tier 0
não executam uma assertion sequer**. Zero assertions, não zero falhas — é o LC-13 exato
(*"`0 failed` nunca prova execução; leia assertions"*), e é o mesmo vício do
`LgpdComplianceTest` do §2b, que fica verde medindo `array_key_exists` num array.

**Três dos quatro instrumentos de defesa do módulo estavam mudos ao mesmo tempo.** O conserto é
informar `created_by` — PR próprio, fora do prefixo de qualquer thread aberta.

---

## 8 · Achado de plano — a D-ENDERECO pode já estar decidida

O `00-INDICE.md §7` registra `{"id": "D-ENDERECO", "respondida": false}` e bloqueia a thread
06 (*44 arquivos / 20 PRs*). O `06-ui-bloqueada.md:20` repete a pergunta em aberto.

**Mas o `main` carrega uma decisão datada em sentido contrário:**

```
.github/workflows/modules-pest.yml  sha 2d2de2dbaa43
:36   # O endereco de UI do modulo e Pages/Patrimonio/** por decisao [W] de 2026-09-04
:37   # (modulo proprio; ADR 0180/0182 divergiam e o SCOPE.md aguardava a decisao).
:48   - 'resources/js/Pages/Patrimonio/**'      <- a máquina já está configurada p/ esse endereço
:71   - 'resources/js/Pages/Patrimonio/**'
commit  d6457184ea · Fri Sep 4 23:27:23 2026 · PR #6784
```

**Não estou declarando a 06 destravada** — e a distinção importa:

- É **fato datado em passado**, a forma que `proibicoes.md` permite (LC-10), e o commit que o
  introduziu é verificável.
- Mas é **uma fonte só**, e ela é um comentário de workflow. Varri os outros donos:
  `git grep "Pages/Patrimonio"` devolve **7 linhas**, e as outras 5 são o próprio playbook
  perguntando. **Nenhuma ADR** decide o endereço. O `SCOPE.md` ainda diz
  `migracao_ui: "bloqueado-escopo — aguarda decisao [W]"` — ou seja, **os dois donos canônicos
  se contradizem**, e nenhum dos dois é a ata.
- O playbook foi gerado em **08/09**, quatro dias depois da decisão que o comentário afirma.

**Isto é [W], não código.** Se a decisão de 04/09 vale, ela destrava 44 arquivos e o `SCOPE.md`
está desatualizado (errata, no mesmo PR do item 2 do RESÍDUO — §6 acima). Se não vale, a linha
`:36` do workflow é uma afirmação a corrigir. **Registro e paro** — o que eu não podia fazer
era deixar a próxima sessão descobrir isso sozinha.

---

## 9 · Campo `invalida:` — detalhado

| thread | veredito desta medição |
|---|---|
| **01** — tenant na subconsulta | **VÁLIDA, com calibragem.** Âncora `:112` confirmada. Duas correções ao enunciado: (a) o gêmeo `AssetController:97` é o mesmo defeito e tem **maior** alcance (é o índice); (b) o teste precisa **fabricar** a linha órfã, senão fica verde antes do fix (§7a). |
| **02** — trava de saldo | **PREMISSA CENTRAL FALSA.** `StoreAssetAllocationRequest` é órfão; `store()` recebe `Request` cru. A trava escrita ali é inerte em runtime. O escopo tem de mudar antes de executar (§5). **Não morta — mal endereçada.** |
| **03** — guarda `asset.view` | **DESTRAVADA.** A condição de parada não se aplica: `asset.view` está registrada (`DataController:31`) e é consumida (`RoleController:101/:221`). Insumo extra: o seeder é vazio, o teste precisa criar a `Permission` (§6). |
| **04** — esta | cumprida; 0 arquivos de produção tocados. |
| **05** — retenção LGPD | **JÁ BARRADA** pelo `CODE_NOTES §5` (lápide §5 de 2026-07-27: *num ERP não se apaga PII*; item #6 do loop IA-OS `descartado: true`). **Corroboro com medição própria:** mesmo se reaberta, o `retention.php` que ela usaria de base tem **4 de 4** chaves apontando para tabelas inexistentes, e o `LgpdComplianceTest` que seria seu oráculo é presence-gate sobre o array (§2b). Reabrir é ADR sucessora de [W], não PR. |
| **06** — UI bloqueada | **BLOQUEIO EM DÚVIDA** — ver §8. Não declarada destravada; escalada a [W] com o recibo. |
| **§0 do `00-INDICE.md`** | **ERRADO.** *"D1 caiu"* é refutado por varredura contada (§0 e §1). O índice precisa de errata: D1 é defeito vivo, não pedido fantasma. |

---

## 10 · Checklist de saída

| # | item | estado |
|---|---|---|
| 1 | veredito de **D1** | **CONFIRMADO** — 6 sítios, `:63/:208/:243/:286/:322/:354` (§1) |
| 2 | veredito de **D5** | **CONFIRMADO** — `Asset.php:30` (`purchase_amount`) mais achado maior: `retention.php:49-:52`, 4/4 tabelas inexistentes (§2) |
| 3 | veredito de **D6** | **CONFIRMADO** — não gravado, não lido, não existe; propagado a 3 sites incl. o charter (§3) |
| 4 | veredito de **D7** e aviso à 02 | **CONFIRMADO** `:51`, e **inerte** (Request órfão). Aviso à 02 emitido em §5 |
| 5 | veredito de **D8** | **CONFIRMADO** — grava `:41/:90/:169`, lê só `edit.blade.php:71`, calcula ninguém; dono = `SPEC US-ASSET-W01` (§4) |
| 6 | veredito de **D9** e destrava/trava a 03 | **RESOLVIDO** — **destrava a 03**; o errado é o SCOPE, não o código (§6) |
| 7 | campo `invalida:` preenchido | frontmatter mais §9 detalhado |
| 8 | nenhum arquivo de produção tocado | **0** — este PR contém só este arquivo |

### Como cada número foi obtido (reprodutível)

```bash
# base: origin/main fresco, clone NAO raso (git rev-parse --is-shallow-repository = false)
git diff --stat cb475c0ca2f4..HEAD -- Modules/AssetManagement/   # vazio (modulo congelado)
git diff --stat cb475c0ca2f4..HEAD | tail -1                     # 49 arquivos (controle positivo)

# D1 - contagem dos dois padroes nos 7 controllers
grep -cE "can\('superadmin'\) \|\|" Modules/AssetManagement/Http/Controllers/*.php   # 18, zero no Maitenance
grep -cE "can\('asset\.[a-z_]+'\) && auth\(\)->user\(\)->can\(" .../*.php            # 7, todas no Maitenance

# D5 - as 4 chaves, uma a uma, contra o baseline que o CI semeia
git grep -E "CREATE TABLE .(am_)?assets." -- database/schema/mysql-schema.sql

# D9 - sonda de permissao + controle positivo (rc do rg, nunca de um pipeline)
rg --hidden -g '!.git/**' "(can\(|@can\(|'value' =>\s*)'assetmanagement\."   # rc=1  (zero)
rg --hidden -g '!.git/**' "(can\(|@can\(|'value' =>\s*)'asset\."             # rc=0  (30)

# D7 - orfaos: varredura no repo inteiro, nao so no modulo
git grep -n StoreAssetAllocationRequest    # 8 linhas, nenhuma e use/type-hint
```

**Ressalvas de método, declaradas:**

- §7c estava *"medido por construção"* na 1ª redação; **hoje está executado** (`toSql()` no
  CT 100, SQL colado, com controle positivo da forma correta ao lado).
- §7e é medição da **thread 01** (execução) mais verificação independente minha (leitura).
- O aviso `num_uf` do §4 é **vetor identificado por leitura**, não comportamento medido.
- Não medi o banco de **produção** em ponto nenhum — onde o veredito dependeria disso (quantos
  businesses têm `asset.*` materializada; se existe hoje linha órfã em `asset_transactions`),
  está escrito que não medi, com a receita de quem quiser fechar.
- Tudo o mais foi lido de `a875e200c044` com varredura contada; onde a sonda podia mentir
  (vazio-que-é-erro, rc de pipeline, falso-positivo por prefixo — `asset.view` casa com
  `asset.view_all_maintenance`, por isso as sondas usam borda de palavra ou aspa de
  fechamento) há controle positivo registrado acima.
