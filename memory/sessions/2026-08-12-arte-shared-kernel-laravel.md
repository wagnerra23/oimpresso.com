---
date: "2026-08-12"
topic: "Estado da arte — onde mora código cross-cutting num monolito modular Laravel, e como corrigir a seta invertida núcleo→módulo"
authors: [C]
module: null
tags: [arquitetura, modular-monolith, shared-kernel, laravel, nwidart, deptrac, phpat, pest-arch, multi-tenant]
pii: false
---

# Estado da arte — shared kernel em monolito modular Laravel

> **Pergunta do dono:** onde mora código cross-cutting; qual a regra de direção de dependência
> `app/` × `Modules/`; o que fazer com `PiiRedactor`, `ScopeByBusiness`, `HasArquivos`, `Subscription`.
> **Método:** Fase 1 pesquisa limpa (sem ler `memory/` antes) → Fase 2 mede o repo → Fase 3 ranqueia gaps.
> **Disciplina aplicada:** [§5 2026-07-16](../proibicoes.md) — cada importação diz, na mesma frase,
> **por que o problema deles é o nosso** (ou por que não é). Nenhum número aqui é estimado: cada um
> tem o comando ao lado (§5 2026-07-28).

---

## 0. Correção de método antes de tudo (contestação da linha 4 do brief)

O brief listou 4 primitivas cross-cutting. **Três se confirmam. A quarta é artefato de medição.**

`Subscription` não é uma classe — são **duas**, homônimas, de domínios diferentes:

| classe | arquivos (todos · s/ Tests) | módulos | o que é |
|---|---|---|---|
| `Modules\Superadmin\Entities\Subscription` | 23 · 17 | 5 | assinatura **do tenant no oimpresso** (licença SaaS, pacote do Superadmin) |
| `Modules\RecurringBilling\Models\Subscription` | 33 · 16 | 2 | assinatura **do cliente do tenant** (produto de cobrança recorrente) |

A soma por **nome curto** — `use Modules\*\{Entities,Models}\Subscription;` — dá **56 arquivos** e
**7 módulos**, que é exatamente o "55 / 7" do brief. Ou seja: a linha mediu a *palavra*, não a *classe*.
Pelo critério declarado (símbolo importado por ≥5 módulos distintos), **nenhuma das duas qualifica
sozinha** (5 e 2). `Subscription` sai da lista de primitivas cross-cutting.

```
node scratchpad/reconciliar.mjs   # lê git ls-files, casa /^use <FQCN>\s*;/m por arquivo
```

⚠️ **Armadilha de medição que queimou 2 tentativas minhas hoje** e que explica por que isso passou:
`git grep -F 'Superadmin\Entities\Subscription'` devolve **0 linhas com rc=0** — o `\E` de `\Entities`
mata o pattern no caminho PCRE do git ([§5 2026-07-31](../proibicoes.md)). O mesmo padrão com `-i`
devolve 30. Controle positivo obrigatório antes de confiar em qualquer contagem por `git grep`.

---

## 1. Como os melhores fazem (Fase 1)

| Player | Mecanismo concreto | Por que é referência | Tradução → vale aqui? |
|---|---|---|---|
| **Mohamed Said** — *Modular Monolith in Laravel: enforcing bounded contexts* (15/jun/2026) | Módulo `SharedKernel` explícito e **deliberadamente pequeno** (value objects, exceções-base). Cada módulo é pacote com `composer.json` próprio + **path repository**; Deptrac quebra o CI em import cruzado | Autor Laravel-nativo, receita completa (layout + `composer.json` + `deptrac.yaml`), não é DDD genérico | **Parcial.** O "kernel pequeno" vale. O path-repository **não** — nossos 30 `composer.json` de módulo já existem e estão **inertes** (medido abaixo); ligá-los é ADR própria |
| **nWidart/laravel-modules v11→v13** (v13 = Laravel 13, mar/2026) | Deixou de exigir `"Modules\\": "Modules/"` no root; autoload passa a vir de **`composer.json` por módulo** via `wikimedia/composer-merge-plugin` | É a lib que usamos; a v13 é a direção oficial do mantenedor | **Ainda não.** Estamos em v10 com PSR-4 root. Migrar muda autoload no deploy — e o projeto já teve prod 500 por classmap stale. Não é subproduto deste refactor |
| **Deptrac 4.7.1** (23/jul/2026; `qossmic/deptrac` arquivado 17/fev/2025 → `deptrac/deptrac`, 10M+ installs) | Camadas por diretório/namespace/classe em YAML, **whitelist**: sem regra declarada, nada é permitido. Baseline pra adoção gradual | Ferramenta mais expressiva do ecossistema PHP pra este problema exato | **Não agora.** Dep nova (ADR) + lane nova + 2º baseline + YAML pra manter. Expressividade que não precisamos pra 1 regra |
| **PHPat** (extensão PHPStan) + **Pest v4 `arch()`** | Regras de dependência/herança escritas em PHP, rodando **dentro do analisador que o projeto já tem**. Pest: `expect('App')->not->toUse('Modules')`, com `->ignoring()` pro legado | Custo marginal ~zero: reaproveitam pipeline existente em vez de criar um | **Sim — Pest `arch()`.** Pest 4 já está no `composer.json`; **zero dependência nova** (a proibição de dep-sem-ADR não dispara). PHPat exigiria ADR |
| **Shopify Packwerk** (Rails; v3.3.0 mai/2026 · ~2M classes, 4k+ componentes) | Violações registradas num **arquivo de dívida** (`deprecated_references`): CI reprova só o **novo**, a lista só encolhe. Endurece uma fronteira por vez ("strangler fig" dentro do processo) | Único caso público em escala real de fronteira imposta sobre monolito legado | **Sim, o padrão.** É literalmente o desenho do nosso `governance/multi-tenant-scope-baseline.json`. Confirma: baseline + forward-only, nunca big-bang |

**Convergência dos 5, em uma frase:** *o shared kernel é uma pasta pequena e **leaf** (não importa
ninguém), a direção de dependência é imposta por análise estática com **baseline de dívida**, e a
adoção é uma fronteira por vez.* Nenhum dos 5 recomenda "mover tudo agora".

---

## 2. Direção de dependência: a regra, e o que cada ferramenta NÃO cobre

**Regra canônica (unânime nos 5):** o núcleo/shared kernel **nunca** importa módulo. A seta é sempre
`Modules/* → app/`. Um símbolo só pode viver no núcleo se for **leaf** — se ele importa uma Entity ou
um Service de módulo, o lugar dele **não é** o núcleo; movê-lo pra lá apenas **inverte a seta de novo,
com um passo a mais**.

| Ferramenta | Cobre | **NÃO cobre** | Custo aqui |
|---|---|---|---|
| **Pest `arch()`** | `use`, type-hints, `new X`, `X::class` — referência estática resolvida pelo parser | FQCN em **string** (`'Modules\...'`), `app('chave')`, Facades por alias, Blade, `config()`, FQCN gravado no **banco** | **0 dep nova.** Roda nas lanes Pest que já existem. `arch()` não é usado em lugar nenhum do repo hoje (`git grep -ln "arch()->"` → 0) |
| **PHPat** | O mesmo + herança/mixin/naming, com mensagens melhores | O mesmo conjunto acima | Dep nova → **ADR**. Entraria na lane `PHPStan / Larastan · ratchet vs baseline` (já required) |
| **Deptrac** | O mais expressivo: camadas, whitelist, grafo, baseline próprio | O mesmo conjunto acima (é estático também) | Dep + lane + YAML + 2º baseline. **Duplicaria régua** se o Pest arch já resolve |
| **`bin/check-scope.php`** (existente) | Declaração `SCOPE.md.contains[]` ↔ árvore, 2 direções, required em `scope-guard.yml` | Não olha `use` nenhum — só controllers vs frontmatter | **0.** É o dono natural de uma 3ª direção (deps declaradas) |

**O que NENHUMA cobre, e por isso é o risco real deste refactor:** a chave do global scope do Eloquent
(`get_class($scope)`, resolvida em runtime), `arquivable_type`/`subject_type` (FQCN **persistido em
linha de banco**) e `app(X::class)` de classe sem interface. Ferramenta estática dá verde e o
comportamento quebra. Isso não é argumento contra ligar a ferramenta — é argumento pra que a prova de
não-quebra seja **teste comportamental**, não análise estática (§5).

---

## 3. Compara — estado oimpresso hoje (Fase 2)

| Dimensão | Estado-da-arte (Fase 1) | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Casa do shared kernel** | Pasta/módulo `SharedKernel` pequeno e leaf | `app/` **já tem** as casas certas: `Concerns/`, `Support/Errors`, `Domain/{Fsm,Inventory}`, `Util/`, `Facades/`. Falta só **conteúdo no lugar** | **curta** |
| **Direção núcleo→módulo** | Proibida, imposta em CI | **60 imports / 31 arquivos** de `app/` pra `Modules/` (Jana 27 · OficinaAuto 16 · PaymentGateway 8 · NfeBrasil 3 · outros 6). Zero enforcement | **média** |
| **Primitiva mais grave** | Kernel é leaf por definição | `app/Concerns/HasBusinessScope.php` (trait Tier 0) importa `Modules\Jana\Scopes\ScopeByBusiness`. O irmão `BelongsToBusinessViaParent` importa `ScopeByBusinessViaParent`. **Dois** casos, não um | **média** |
| **Enforcement de fronteira** | Deptrac/Packwerk com baseline | Nenhum gate mede `use` cross-módulo. Mas o **padrão** já é doutrina viva aqui: `MultiTenantScopeArchitectureTest` + `governance/multi-tenant-scope-baseline.json` + bite-tests é Packwerk com outro nome | **curta** (é replicar receita de casa) |
| **Fronteira declarada** | `composer.json` por módulo declara `require` | 32 `SCOPE.md` com `not_contains` (31/32), **sem campo `depends_on`**. 58 pares módulo→módulo em produção, nenhum declarado como dependência | **média** |
| **Empacotamento por módulo** | Path repository + merge-plugin (v11+) | 30/32 módulos **têm** `composer.json` — e são **inertes**: root usa `"Modules\\": "Modules/"`, e `merge-plugin` não aparece nem no `composer.json` nem no `composer.lock` | **longa** (e não é pra fechar agora) |
| **Análise estática** | Deptrac/PHPat no CI | Larastan 3.10 nível 5, `paths: [app, Modules]`, lane required com ratchet. Pest 4 presente. **Infra pronta, regra ausente** | **curta** |

**Onde o oimpresso já bate ou supera o mercado** — e isto não é elogio de cortesia:

1. O **baseline forward-only com bite-test** do `MultiTenantScopeArchitectureTest.php` é mais rigoroso
   que o Packwerk padrão: além da lista de dívida que só desce, ele tem **controle positivo**
   (`expect(count(coletados))->toBeGreaterThan(150)`) e **controle negativo** (fixture que prova que o
   detector morde). O Packwerk não traz isso de fábrica.
2. `bin/check-scope.php` com as **duas direções** (árvore→`contains` e `contains`→árvore, esta última
   nascida 2026-08-10) é mais completo que o `package.yml` do Packwerk, que só olha uma direção.
3. `module-surface.mjs` gera o inventário **derivado da árvore** em vez de lista escrita — a lição
   que Said e Packwerk documentam, mas que só o Shopify implementa em escala.

**O que isso significa:** não falta *maturidade de mecanismo* aqui. Falta **aplicar o mecanismo que já
existe a um eixo que ninguém ligou** — o eixo `use`.

---

## 4. Recomendação por primitiva

### 4.1 `ScopeByBusiness` (+ `ScopeByBusinessViaParent`) → **`App\Scopes\`, movimento ATÔMICO**

**Destino:** `app/Scopes/ScopeByBusiness.php` e `app/Scopes/ScopeByBusinessViaParent.php`.

**Por quê:** é o único caso que fecha a seta invertida do trait Tier 0, e é **leaf** — `ScopeByBusiness`
importa só `Illuminate\Database\Eloquent\{Builder,Model,Scope}`. Um global scope de tenant é, por
definição, do núcleo: 8 módulos + 20 arquivos de `app/` dependem dele; a Jana é apenas onde ele nasceu.

**Tamanho:** 207 arquivos importam (`87` fora de Tests). Como o **nome curto não muda**, o diff é a
linha `use` de cada arquivo: **207 add / 207 del**, 1:1, mecânico.

**⚠️ O achado que decide a sequência — a migração NÃO pode ser gradual.** A chave do global scope no
Eloquent é `get_class($scope)`. Medido:

```
addGlobalScope(new ScopeByBusiness) À MÃO: 2  (prod: 1 — o próprio app/Concerns/HasBusinessScope.php)
só importam pra usar ::class:            204
```

Ou seja: **1 arquivo registra o scope, 204 apenas o desregistram** via
`withoutGlobalScope(ScopeByBusiness::class)`. Se o trait passar a instanciar `App\Scopes\...` e os 204
continuarem citando `Modules\Jana\...`, esses 204 passam a remover uma chave inexistente — a query
**continua filtrada**. Direção da falha: **sobre-filtragem, não vazamento** (fail-secure em relação à
[ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — nenhum tenant vê dado alheio). Mas
quebra silenciosamente toda operação superadmin/batch que depende do escape.

**E `class_alias` NÃO resolve** (verificado contra a semântica da linguagem, não contra opinião):
`Foo::class` é constante de compilação e devolve a string escrita, não o alvo do alias; e
`get_class()` num objeto de classe aliasada devolve o nome **original**. Alias não reconcilia a chave.
Logo: **rename atômico ou nada**.

**Risco residual medido:** `0` referências por string (`'Modules\Jana\Scopes\ScopeByBusiness'`) em
código, `0` em config `json/neon/yml`, `0` bindings em ServiceProvider. Só 4 menções em `.md` de
`memory/` (2 delas em session logs, que são fósseis datados e **não se editam**).

**Tensão honesta com o canon:** [§5 2026-07-12](../proibicoes.md) bane backfill big-bang de legado. Ela
fala de *normalização mecânica que acorda gate diff-aware sem pagar a dívida de conteúdo*. Aqui é
diferente: a atomicidade é **exigência semântica** da linguagem, não zelo estético. Ainda assim, 414
linhas num PR excede o ≤300 da `commit-discipline`. **Isso é decisão [W]**: aceitar 1 PR mecânico de
1 intent, ou partir em PR-prod (87 arquivos) + PR-tests (120) — sabendo que **entre os dois PRs a
suíte fica vermelha por construção**, o que na prática obriga o PR único.

### 4.2 `PiiRedactor` → **`App\Support\Privacy\PiiRedactor`**

**Por quê:** 62 arquivos fora de Tests, **23 módulos distintos** — a mais transversal das três, e a
única que é 100% núcleo por natureza (redação LGPD antes de log/LLM/UI). É quase leaf: importa apenas
`App\Util\OtelHelper` — que **já é do núcleo**, então o movimento é estritamente pra baixo.

**Tamanho:** 90 linhas `use` (90 arquivos). Sem chave de identidade em runtime, sem binding de
container (`git grep -F 'PiiRedactor' -- '*ServiceProvider.php'` → 0) ⇒ **pode ser gradual com shim**,
ao contrário do scope. Mesmo assim recomendo atômico: 90 é menor que 207 e o shim vira dívida.

**Bônus que fecha uma lápide:** `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` tem
`PII_PATTERNS` próprio e **não importa** o `PiiRedactor` (medido: `tem PII_PATTERNS: true`,
`importa PiiRedactor: false`) — é a duplicata LC-18. Com o serviço no núcleo, a consolidação deixa de
ter desculpa arquitetural. ⚠️ Mas os dois conjuntos de padrões **não são equivalentes** (o do
`PiiRedactor` tem o desempate de CPF cru vs run-id do GitHub, catalogado no próprio arquivo em
2026-08-02). Consolidar é trabalho de **conteúdo**, não de lugar — PR separado.

### 4.3 `HasArquivos` → **FICA onde está. Mover seria piorar.**

**Por quê:** não é leaf. Importa `Modules\Arquivos\Entities\Arquivo` e
`Modules\Arquivos\Services\ArquivosService` (que é `singleton` no `ArquivosServiceProvider`). Levar a
trait pro núcleo **criaria** uma seta `app/ → Modules/Arquivos` — exatamente o defeito que este
trabalho existe pra remover. E `Arquivo` participa de relação **morph**: `arquivable_type` guarda FQCN
cru no banco (`enforceMorphMap`/`morphMap`: **0 ocorrências no repo inteiro**), então mover a Entity
junto quebraria linhas históricas.

**O certo não é mover, é declarar.** A [ADR 0123 §4](../decisions/0123-modules-arquivos-backbone.md) já
nomeia `Modules/Arquivos` como *"backbone transversal que todo módulo passa a usar"* — o projeto **já
tem** o conceito de "módulo do qual todos podem depender", só não tem o campo que o declara. 8 arquivos
fora de Tests, 6 módulos: volume baixo, seta legítima. Ação: campo declarativo no `SCOPE.md`
(§5.4 abaixo), zero movimentação de código.

### 4.4 `Subscription` — **nenhuma ação de lugar; e a pergunta de domínio tem resposta**

A linha caiu por método (§0). Mas a pergunta *"de quem é o conceito assinatura"* tem resposta clara e
**não gera trabalho**: são **dois conceitos**, cada um no dono certo.

- `Superadmin\Entities\Subscription` = o tenant assinando **o oimpresso** (licenciamento da plataforma).
- `RecurringBilling\Models\Subscription` = o cliente do tenant assinando **um plano do tenant** (produto).

O acoplamento vale a pena olhar mas é pequeno: `Superadmin\Subscription` é importado por Connector,
Officeimpresso, PaymentGateway e VozDoCliente (4 fora de casa); `RecurringBilling\Subscription` só
pelo Financeiro (1 fora de casa).

**Se algum dia doer, o remédio é desambiguar o NOME — e é caro:** `Superadmin\Subscription` usa
`Spatie\Activitylog\Traits\LogsActivity`, que grava `subject_type` = FQCN **em linha de banco**, lido
hoje pelo `Modules/Auditoria` (`AuditEntryService`, filtro indexado por `subject_type`). Sem
`enforceMorphMap`, renomear a classe **cega o histórico de auditoria**. É decisão [W] com migration de
dados, não refactor. **Recomendação: não mexer.**

---

## 5. Avalia — gaps ranqueados (Fase 3)

Estimativas em **IA-pair** ([ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md):
10× humano + margem 2×). Nenhum item aqui é P0: **não há vazamento cross-tenant nisto**. A seta
invertida é dívida de arquitetura, não incidente — e dizer o contrário seria tom inflado.

| # | Gap | Impacto | Esforço (IA-pair) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **Direção `app/ ↛ Modules/` não é medida por nada.** 60 imports podem virar 61 hoje sem sinal nenhum | alto (é o que impede a dívida de crescer) | **~45-90 min** — 1 arquivo `tests/Feature/Architecture/DependencyDirectionTest.php` + baseline de 31 arquivos via `->ignoring()` | **não** |
| 2 | `ScopeByBusiness` + `ViaParent` no módulo errado — a seta invertida do trait **Tier 0** | alto (simbólico e real: é a fundação multi-tenant) | **~2-3 h** — codemod 207 arquivos + teste comportamental novo + lane MySQL | teste do §6 tem de existir **antes** |
| 3 | `PiiRedactor` no módulo errado — 23 módulos dependem da Jana pra cumprir LGPD | médio | **~1-1,5 h** — codemod 90 arquivos + ajuste de 3 refs por string em teste + 2 em config | não (mas fazer **depois** do #2, pra validar o codemod no caso barato… ou antes, pelo mesmo motivo — ver §7) |
| 4 | **Dependência módulo→módulo não é declarável.** 58 pares em prod, `SCOPE.md` não tem campo `depends_on` | médio | **~1,5-2 h** — campo no frontmatter + 3ª direção no `bin/check-scope.php` (dono existente) + selftest | #1 pronto (o dado sai do mesmo scanner) |
| 5 | Duplicata LC-18 (`PII_PATTERNS` em `IndexarMemoryGitParaDb`) | médio (é PII, e os dois conjuntos divergem) | **~1-2 h** — trabalho de **conteúdo**: provar equivalência dos padrões, incl. o desempate de CPF cru | #3 |
| 6 | Os 30 `composer.json` de módulo são inertes — ou ligar (merge-plugin, v11+) ou apagar | baixo | **~4-8 h + canary** — muda autoload no **deploy**; o projeto já teve prod 500 por classmap stale | **ADR própria.** Não é subproduto deste trabalho |
| 7 | `.claude/rules/modules.md` ainda cita ADR 0101 (biz=1) como doutrina de teste; [ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) manda **98** | baixo | ~10 min | não — mas é **fora do escopo deste doc**; registro como achado lateral, não como tarefa que eu inventei |

---

## 6. Como PROVAR que não quebrou (o teste que hoje NÃO existe)

Os testes de isolamento existentes (`Modules/Jana/Tests/Feature/MultiTenantIsolationTest.php` e o
`...Comprehensive`) são **estruturais**: verificam por reflexão que a Entity usa a trait e que a classe
implementa `Scope`. **Nenhum deles pegaria a regressão do §4.1** — a classe continuaria existindo e
implementando `Scope`; o que quebra é a *chave*. (Os dois ainda citam ADR 0101/biz=99; o tenant
canônico de teste hoje é o **98**, [ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).)

**O teste que precisa nascer antes do passo #2** — comportamental, lane MySQL (CT 100 / CI, nunca
local, **nunca biz=4**), 3 asserções:

1. **Isolamento preservado (o invariante Tier 0).** Com sessão em tenant 98 e linhas semeadas em 98 e
   num segundo tenant fictício, `Model::all()` devolve **só** as de 98. Falhar aqui = P0.
2. **A chave do scope é a canônica.** `expect((new X)->getGlobalScopes())->toHaveKey(App\Scopes\ScopeByBusiness::class)`
   — é esta asserção que morre se o rename for parcial. (`toHaveKey` é legítimo: o lint que o acusava
   foi medido em 100% FP e desligado, [§5 2026-07-26](../proibicoes.md).)
3. **O escape ainda escapa.** `Model::withoutGlobalScope(App\Scopes\ScopeByBusiness::class)->count()`
   devolve as linhas dos **dois** tenants. Sem esta, um rename parcial passa verde e 204 arquivos
   viram no-op silencioso.

**Bite-test obrigatório** (convenção da casa, ver `MultiTenantScopeArchitectureTest`): fixture com Model
sem a trait ⇒ asserção 1 **vermelha**; com a trait ⇒ verde. Sem controle negativo, verde é
indistinguível de não-execução ([LC-13](../LICOES_CODE.md)).

**Rede de segurança que já existe:** **58 arquivos de teste** usam `withoutGlobalScope` no setup. Um
rename parcial provavelmente já derruba boa parte deles — mas "provavelmente" não é prova, e só nas
lanes que rodam. O teste acima é o que transforma isso em veredito.

---

## 7. O que NÃO fazer — armadilhas específicas de Laravel

1. **Não fazer o rename do scope em partes.** Chave = `get_class($scope)`. Medido: 1 arquivo registra,
   204 desregistram por `::class`. Parcial = 204 no-ops silenciosos.
2. **Não usar `class_alias` como ponte.** `::class` é constante de compilação (devolve o nome escrito);
   `get_class()` devolve o original. O alias não reconcilia a chave — dá falsa sensação de segurança.
3. **Não mover Entity que participa de morph ou de `activity_log`.** `arquivable_type` e `subject_type`
   guardam FQCN **na linha do banco**, e `enforceMorphMap`/`morphMap` tem **0 ocorrências** no repo
   (6 migrations com `morphs()`). Vale pra `Arquivo` e pra `Superadmin\Subscription`.
4. **Não ligar merge-plugin / path repositories "de passagem".** Muda o autoload no **deploy**
   (`composer dump-autoload`, classmap otimizado). Já houve prod 500 por classmap stale. ADR própria.
5. **Não registrar nada em ServiceProvider por causa deste refactor.** `PiiRedactor` e os dois scopes
   têm **0 bindings** — resolvem por autoload. Registrar "pra garantir" adiciona ordem de boot ao
   problema. (`ArquivosService` **é** singleton — mais um motivo pra `HasArquivos` não sair de casa.)
6. **Não confiar em `Module::has()` / `modules_statuses.json` como fronteira.** Com PSR-4 no root, a
   classe de um módulo desligado **continua autoloadable**; só o ServiceProvider deixa de registrar.
   Hoje só `Accounting` está `false` (38 entradas).
7. **Não abrir gate novo.** Os donos existem: `tests/Feature/Architecture/` + baseline em `governance/`
   (lane required `Tier-0 guards`) pro eixo `use`; `bin/check-scope.php` pro eixo declaração
   ([§5 2026-07-09](../proibicoes.md), "duplica régua consolidada"). `gates-registry.json` tem de
   receber a entrada **no mesmo commit** ([§5 2026-08-08](../proibicoes.md)).
8. **Não medir com `git grep -F` sem controle positivo.** `\E` no padrão devolve 0 com rc=0
   ([§5 2026-07-31](../proibicoes.md)) — foi o que produziu a linha errada de `Subscription`.
9. **Não tratar a distância como P0.** Nada disto vaza tenant. Vender como emergência é o tom inflado
   que o §"Comportamento Claude" bane.

---

## 8. Recomendação final

**Comece pelo #1 — o teste de direção de dependência.** Alto impacto, ~45-90 min IA-pair, **zero
pré-requisito bloqueante, zero dependência nova, zero linha de código de produção tocada.** É o único
passo que pode ser feito hoje sem decisão de [W] sobre tamanho de PR, e é o que **impede a dívida de
crescer enquanto os passos 2 e 3 não acontecem** — que é exatamente a ordem que Packwerk e Said
recomendam (congela primeiro, endurece uma fronteira por vez).

Ele também **produz o dado** dos passos seguintes: o mesmo scanner que gera o baseline de 31 arquivos
gera a lista de 58 pares módulo→módulo do #4.

**Próxima ação hoje:** criar `tests/Feature/Architecture/DependencyDirectionTest.php` com
`arch()->expect('App')->not->toUse('Modules')->ignoring([...31 arquivos...])`, gerar o baseline com o
comando abaixo, e **plugar na lane required que já existe** (`multi-tenant-gate.yml` — mesmo dono do
`MultiTenantScopeArchitectureTest`, com o `paths:` estendido pra `app/**`), advisory na primeira leva
([ADR 0275](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) forward-only),
com bite-test provando que morde.

```bash
# baseline dos 31 arquivos (a lista que vira ->ignoring())
git ls-files 'app/**/*.php' | xargs grep -l '^use Modules\\' | sort
```

**Decisão que precisa de [W] antes do passo #2** (não antes do #1): o rename do `ScopeByBusiness` é
atômico por exigência da linguagem — 207 arquivos, 414 linhas, 1 intent. Aceitar como PR único, ou
partir sabendo que a suíte fica vermelha entre os dois?

---

### Recibos (comandos re-rodáveis)

| Número | Comando |
|---|---|
| 60 imports / 31 arquivos `app/`→`Modules/` | `git ls-files 'app/**/*.php' \| xargs grep -c '^use Modules\\'` (ou `scratchpad/medir.mjs`) |
| 207 / 87 importadores de `ScopeByBusiness` | `git grep -lF 'use Modules\Jana\Scopes\ScopeByBusiness;' \| wc -l` |
| 1 registra / 204 só usam `::class` | `scratchpad/rename-size.mjs` |
| 90 / 62 / 23 módulos — `PiiRedactor` | `git grep -lF 'use Modules\Jana\Services\Privacy\PiiRedactor;' \| wc -l` |
| 10 / 8 / 6 módulos — `HasArquivos` | `git grep -lF 'use Modules\Arquivos\Concerns\HasArquivos;' \| wc -l` |
| 23 vs 33 — as duas `Subscription` | `scratchpad/reconciliar.mjs` (⚠️ `-F` cru falha por `\E`) |
| 58 pares módulo→módulo | `scratchpad/medir.mjs` §3 |
| 0 `enforceMorphMap` no repo | `git grep -lE 'enforceMorphMap\|morphMap\(' \| wc -l` |
| 0 bindings das primitivas | `git grep -nF 'PiiRedactor' -- '*ServiceProvider.php'` |
| merge-plugin ausente | `grep -c merge-plugin composer.json composer.lock` |

> Os `.mjs` citados são scripts de medição desta sessão, no scratchpad — **não** foram commitados
> (ferramenta de uso único; se virarem recorrentes, o dono é `scripts/governance/`, não um paralelo).

### Fontes (Fase 1)

- [Mohamed Said — Laravel Modular Monolith: Bounded Contexts Guide](https://msaied.com/articles/modular-monolith-in-laravel-enforcing-bounded-contexts-without-a-framework) (15/jun/2026)
- [Laravel Modules v13 — Installation & Setup (merge-plugin)](https://laravelmodules.com/docs/13/getting-started/introduction) · [nWidart/laravel-modules releases](https://github.com/nWidart/laravel-modules/releases)
- [deptrac/deptrac no Packagist](https://packagist.org/packages/deptrac/deptrac) (4.7.1, 23/jul/2026) · [qossmic/deptrac arquivado](https://github.com/qossmic/deptrac) (17/fev/2025)
- [PHPat](https://github.com/carlosas/phpat) (extensão PHPStan)
- [Pest — Architecture Testing](https://pestphp.com/docs/arch-testing)
- [Shopify Engineering — Enforcing Modularity in Rails Apps with Packwerk](https://shopify.engineering/enforcing-modularity-rails-apps-packwerk)
