---
id: requisitos-jana-runbook-plataforma
title: "RUNBOOK — Plataforma (superadmin) da Jana (Blade → Inertia)"
type: runbook
authority: canonical
lifecycle: ativo
status: ativo
owner: W
created: '2026-08-31'
last_validated: "2026-08-31"
modulo: Jana
telas:
  - Jana/Plataforma
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w
---

# RUNBOOK — Plataforma (superadmin) da Jana

> **F1 do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)),
> para `resources/js/Pages/Jana/Plataforma.tsx`. O hook `block-mwart-violation` resolve o
> RUNBOOK pelo **kebab do nome do arquivo** — `Plataforma` → `RUNBOOK-plataforma.md` — e
> **não há override** (o `/mwart-override` que a mensagem do hook anuncia não tem handler;
> lápide §5 2026-08-08).
>
> **Este documento não é o `RUNBOOK-metas.md`.** Aquele é a F1 de *Metas* (o CRUD do tenant,
> destino drawer no Painel). Este é a F1 de *Plataforma* — outra rota, outro controller, outro
> gate. O próprio `RUNBOOK-metas.md` §9.5 nomeia esta separação: *"cada tela migrada pede a sua
> F1"*, e lista `superadmin/metas` → `jana-telas-novas.jsx` §`JmPlataforma`.

## 0. O que `last_validated` cobre (e o que NÃO cobre)

Em **2026-08-31** rodou e bateu: o **inventário** (§2), o **contrato** (§3), o **gate** (§4) e a
**medição de produção** (§1). Todos foram lidos em `origin/main` fresco (`git rev-list
--left-right --count origin/main...HEAD` = `0 0`) ou medidos no banco de produção.

**NÃO cobre a migração** — as ondas do §7 não rodaram quando esta data foi escrita. Quem reabrir
isto depois de um PR de Plataforma tem que **re-rodar o inventário** e bumpar a data; um campo
dizendo 2026-08-31 depois que a tela virou React é carimbo, não recibo.

## 1. Por que agora — e o número, dito uma vez

[W] dirigiu o trabalho em 2026-08-31. A trava de sinal **não se aplica**
([ADR 0382](../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)):
pedido do dono é decisão, não proposta.

**Os números, ditos uma vez e não repetidos** (medidos em produção — banco `u906587222_oimpresso`,
2026-08-31, por sonda read-only com bootstrap do Laravel):

| O quê | Medido |
|---|---|
| `jana_metas` | **0** linhas |
| `jana_meta_periodos` | **0** linhas |
| `jana_meta_apuracoes` | **0** linhas |
| Businesses no ambiente | 88 |

Está aqui como **dado de contexto para quem for testar** — a tela renderiza os dois estados
vazios do §3 em produção hoje. **Não** é argumento contra fazer.

**O motivo técnico independente do dado:** a tela é Blade AdminLTE crua dentro de um app Inertia,
e por isso **não pode entrar na faixa de abas**. O ghost `metas` foi removido do `DataController`
com essa razão literal — *"MetasController@index ainda retorna Blade view, o que faz Inertia
`<Link>` silenciar (click no-op)"* — e a mesma trava vale aqui. Migrar devolve o acesso pela
navegação, e isso não depende de haver meta cadastrada.

## 1.1 A dependência declarada no pedido — VERIFICADA, e a premissa estava desatualizada

O pedido condicionava este trabalho a *"verificar se `/ia/superadmin/metas` ficou inacessível"*,
descrevendo que o [#6421](https://github.com/wagnerra23/oimpresso.com/pull/6421) *"deixou como
única porta viva o `hasPermissionTo('jana.superadmin')`"*.

**Medido: são DUAS portas, não uma** — o próprio #6421 já as escreveu, e o comentário dele diz
por quê (*"pra NÃO trancar a tela no caso de a permissão nunca ter sido atribuída a ninguém"*).
Estado de cada uma em produção:

| Porta | Código | Estado medido em prod |
|---|---|---|
| (a) `hasPermissionTo('jana.superadmin')` | Spatie direto, sem passar pelo `Gate::before` | ✅ **VIVA** — 5 usuários |
| (b) `user_type` em `('superadmin','user_oimpresso')` | coluna, fora do alcance do Gate | ❌ **morta** — 0 usuários (todos os 130 são `user`) |

**Controle positivo da porta (a)** — sem ele, "a rota abre" seria inferência a partir da contagem
de linhas de uma tabela de junção. `User::find($id)->hasPermissionTo('jana.superadmin')` devolveu
`true` para os **5** (ids 1 · 12 · 74 · 569 · 607), todos `business_id=1`, todos pelo papel
**`Operacional#1`**, que é o único papel do ambiente com a permissão.

⇒ **A tela NÃO ficou inacessível.** A dependência do pedido está satisfeita e o trabalho não é cego.

⚠️ **Duas observações que ficam declaradas, e NÃO são conserto de passagem:**

1. **`model_has_roles` tem 8 linhas, não 5.** As 3 excedentes têm `model_type` = `AppUser`
   (sem namespace) em vez de `App\User`. O Spatie resolve pelo morph map e **ignora** as 3 — por
   isso a contagem honesta é 5, não 8. Dado torto pré-existente, alheio a esta tela.
2. **O papel que carrega a permissão chama-se `Operacional#1`.** Um papel de nome operacional
   segurando a chave da visão cross-business é decisão de quem administra os papéis — **decisão
   [W]**, não conserto deste PR. Registrado porque quem for testar precisa saber por onde entra.

## 2. Superfície atual (medida em `origin/main`, 2026-08-31)

| Rota | Verbo | Controller | View Blade | Nome |
|---|---|---|---|---|
| `/ia/superadmin/metas` | GET | `SuperadminController@metas` | `copiloto::superadmin.metas` | `jana.superadmin.metas` |

Uma rota, um método, uma view. `Modules/Jana/Http/routes.php:181`, dentro do grupo `/ia`
(o `middleware` do grupo inclui `can:jana.access` e `throttle:120,1`).

**A rota NÃO tem `can:` próprio** — o gate vive dentro do controller (§4).

**Item de menu:** o dropdown legacy do `DataController` **já tem** "Plataforma"
(`__('copiloto::copiloto.menu.plataforma')`, chave existente em
`Resources/lang/pt/copiloto.php:29`), condicionado a `can('superadmin') || can('jana.superadmin')`,
apontando para `route('jana.superadmin.metas')`.
**A faixa de abas (`ghosts`) NÃO tem** — hoje só `dashboard` · `copiloto` · `memorias`.

## 3. Contrato preservado (o que NÃO pode mudar)

Derivado da view Blade, não inventado (`Modules/Jana/Resources/views/superadmin/metas.blade.php`):

**Título da página** — `Jana — Superadmin`; header `Jana` + subtítulo `visão da plataforma`.

**Seção 1 — "Metas da plataforma (business_id NULL)"**
colunas `Nome · Unidade · Origem`; vazio literal **"Nenhuma meta da plataforma cadastrada."**

**Seção 2 — "Metas de clientes (cross-business)"**
colunas `Business · Nome · Unidade`; vazio literal **"Nenhum cliente configurou metas ainda."**

⚠️ **As duas copies de vazio são contrato.** São o que a tela mostra em produção hoje (§1) —
mudá-las é decisão [W], não tradução.

⚠️ **O `#` antes do id do business é literal** no Blade (`#{{ $m->business_id }}`). Preservar.

## 3.1 O que o protótipo ACRESCENTA (fonte de design soberana na FORMA)

Fonte: `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmPlataforma` (+ `jana-telas-novas.css`),
descida no [#6379](https://github.com/wagnerra23/oimpresso.com/pull/6379).
O `jana-merge.jsx` a monta como aba quando `can(papel,'jana.superadmin')`.

| Acréscimo | Fonte do dado | Já existe no controller? |
|---|---|---|
| Contador por seção (`business_id NULL · N metas`) | `count()` da própria coleção | sim — é a coleção já carregada |
| Coluna `Período atual` na seção 2 | `periodoAtual` | **sim** — `->with('periodoAtual', …)` |
| Coluna `Última apuração` na seção 2 | `ultimaApuracao` | **sim** — `->with(…, 'ultimaApuracao')` |
| Linha em estado `archived` quando nunca apurada | derivado de `ultimaApuracao` nula | derivável |
| Rótulo de origem por extenso (`sistema` vira "consulta do sistema") | `origem` | sim |

⇒ O eager load `with('periodoAtual', 'ultimaApuracao')` **já está no controller e hoje não é
usado pela Blade**. As 2 colunas novas são dado que já vem do banco — não são query nova.

### 3.2 Duas coisas do protótipo que NÃO entram, e por quê

1. **O `<Alert tone="danger">` sobre o gate.** O protótipo foi desenhado em **27/08** e alerta que
   *"um dono de empresa que chegue nesta URL veria meta de outro cliente"*. Esse defeito foi
   **fechado em 28/08** pelo #6421. Renderizar hoje um aviso de vulnerabilidade já corrigida é a
   classe **LC-10** (artefato afirmando estado em presente, já falso). A fonte é soberana na
   FORMA — não em fato datado que caducou.
2. **A seção "Instalação do módulo".** Ela pertence a **`/ia/install`** — outro grupo de rotas
   (`prefix: ia/install`), outro controller (`InstallController`), e a ação `uninstall` **derruba
   as tabelas `jana_*`**. O `JmPlataforma` cobre as duas rotas porque no protótipo tudo é aba da
   tela única; aqui são duas rotas de verdade. Trazer botões destrutivos de outra rota para dentro
   desta tela amplia o escopo para superfície irreversível — **fica fora, declarado**, e é F1
   própria se [W] quiser.

## 4. Gate e multi-tenant — Tier 0, e aqui o `withoutGlobalScope` é o CASO LEGÍTIMO

Esta tela **mostra dado de outros tenants por desenho**. É o caso que o
[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) prevê: sair do escopo global
**deliberadamente**, com o `withoutGlobalScope` explícito e comentado.

O que estava errado antes do #6421 **não era o escopo — era o QUEM**:

- `Gate::before` (`app/Providers/AuthServiceProvider.php:34-47`) devolve `true` em **qualquer**
  ability fora de `['backup','superadmin','manage_modules']` para quem tem `Admin#{business_id}`;
- `jana.superadmin` não está nessa allowlist, então `$user->can('jana.superadmin')` é `true` para
  **todo dono de negócio**.

⛔ **Consequência dura para esta migração: `can('jana.superadmin')` NÃO é gate.** Nem no
controller, nem em `props` que decidam o que a tela mostra. As portas legítimas são as duas do
§1.1 — e elas **já estão no controller**. Não afrouxar.

⚠️ **O ghost do menu é a superfície nova, e ela tem a armadilha.** O item do dropdown legacy usa
`can('superadmin') || can('jana.superadmin')` — ou seja, **aparece para todo dono de negócio
hoje**. Ele já era assim antes deste trabalho (é pré-existente, não regressão), mas o ghost da
faixa é código novo: **ele tem que usar o mesmo predicado das duas portas do controller**, senão
a aba aparece para quem levará 403 ao clicar.

## 5. Padrão de Tela

**PT-01 Lista** — a tela é duas listas de entidade em seções. Não é PT-04 (não há KPI: o
protótipo mostra contadores textuais no cabeçalho de cada seção, não `KpiCard`), não é PT-03
(não há entidade única em detalhe).

Consistente com o `RUNBOOK-metas.md` §5, que mapeou `metas/index` para PT-01.

Shell: `AppShellV2` + `JanaAreaHeader` (que já embute `<PageHeader>` + `JanaSubNav`) — o mesmo
que `Index.tsx` · `Chat.tsx` · `Memoria.tsx` usam. **Não** é modo FOCO: a tela é uma área da
Jana, e o protótipo a desenha com a faixa de abas visível.

## 6. Riscos declarados

1. **Sem rede de pixel** — `visual-regression` saiu do required em 2026-08-26 (#6278, decisão [W]).
   O gate é olho humano + `contrato-de-tela`.
2. **A tela renderiza vazia em produção** (§1). Um smoke que só veja "carregou sem erro" não
   distingue *tela certa* de *tela quebrada que também não mostra nada* — por isso o §8 exige os
   dois estados vazios **literais** no smoke, não "abriu".
3. **`ativo=false` não é filtrado** — o controller não filtra por `ativo`. Meta desativada
   apareceria nas duas listas. É o comportamento atual da Blade; mudá-lo é decisão [W].
4. **A agregação cross-business não existe** e **não vai ser inventada** — ver §6.1.
5. **`MetaApuracao` não tem `business_id`** — o escopo é indireto via `meta_id`. Aqui isso é
   inofensivo (a `Meta` já vem resolvida pelo eager load), mas qualquer prop nova que parta de um
   `$id` cru de apuração **vazaria**. Não introduzir.

## 6.1 A agregação — a pendência que fica DECLARADA, não prometida

O docblock antigo do controller prometia agregação cross-business. Medido em 27/08 e re-medido em
31/08: **não existe** — zero `sum`, zero `count`, zero `groupBy` no `SuperadminController`. As
duas coleções são listadas cruas.

O protótipo tomou a mesma posição, e escreve a razão na própria tela:
*"Listagem crua, de propósito … Somar aqui na tela seria inventar total de plataforma no
cliente — a pendência fica declarada até alguém decidir o que a plataforma quer medir."*

⇒ **A tela nova NÃO promete agregado.** Nem em KPI, nem em rodapé de tabela, nem em subtítulo.
O que ela mostra é **contagem do que está listado** (`N metas em M empresas`), que é fato sobre a
lista renderizada, não total de plataforma. O que a plataforma quer medir é **decisão [W]**.

## 7. Ondas (1 PR = 1 intent, ≤300 linhas)

- **F2 · baseline** — o `SuperadminController` é GET-only, e o gate já tem baseline: o
  `SuperadminMetasCrossTenantTest.php` (#6421) cobre as 2 portas, o 403 do dono e o **controle
  positivo** de que `can()` é `true` (sem ele o 403 poderia vir de outra trava). O que falta é
  baseline do **payload**: hoje a Blade recebe `$metasPlataforma` e `$metasDeClientes`.
- **PR-1 · a tela + cutover** — `Inertia::render('Jana/Plataforma')` no lugar do `view()`,
  `Plataforma.tsx` no PT-01, trio (charter + casos + contrato de tela) pelo `criar-tela.mjs` com
  `--prototipo`, o ghost `plataforma` na faixa com o **mesmo predicado das duas portas**, e a
  remoção de `superadmin/metas.blade.php`.

  > **Emenda 2026-09-03 — o cutover era PR-2 e virou o mesmo PR.** Duas razões, e nenhuma é
  > pressa: (a) **precedente do módulo, da mesma semana** — o [#6607](https://github.com/wagnerra23/oimpresso.com/pull/6607)
  > entregou a aba Alertas e apagou a `alertas/index.blade.php` no mesmo PR; abrir exceção aqui
  > seria divergir do irmão sem motivo; (b) trocado o `view()` pelo `Inertia::render`, a Blade é
  > **órfã imediata** — varredura contada em 2026-09-03: nenhum consumidor de código, só a rota
  > (que fica) e prosa histórica em docs. Deixá-la seria código morto que a próxima sessão não
  > sabe se pode apagar.
  >
  > O argumento que sustentava o PR-2 — *"só depois do smoke"* — **não se sustenta**: revert de
  > PR é atômico no git, então a Blade voltaria junto com o controller de qualquer forma. Ela
  > nunca foi rede de segurança separada.

**Fora destas ondas, e nomeado:** a seção "Instalação do módulo" (§3.2 item 2) — F1 própria,
rota `/ia/install`, superfície destrutiva.

## 8. Definição de pronto

`/ia/superadmin/metas` renderiza Inertia · aba `Plataforma` acende na faixa **e o `<Link>` navega**
(não silencia) · a aba **não aparece** para quem não passa nas duas portas · charter + casos com
UC citado por teste (`casos-gate` é required) · Pest cross-tenant **98 × 99**
([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) — **nunca biz=4**,
**nunca biz=1**) verde no CT 100, com **controle positivo** de que `can('jana.superadmin')` é
`true` para o dono (senão o 403 é falso-verde) · as **duas copies de vazio do §3 conferidas
literais** no smoke real em prod (R1), porque em produção é isso que a tela mostra (§1) · zero
arquivo em `Modules/Jana/Resources/views/superadmin/`.
