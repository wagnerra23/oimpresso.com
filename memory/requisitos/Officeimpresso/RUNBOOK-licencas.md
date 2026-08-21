---
owner: W
last_validated: "2026-08-20"
slug: officeimpresso-runbook-licencas
title: "Officeimpresso — RUNBOOK Licencas/Index (Computadores Cadastrados)"
type: runbook
module: Officeimpresso
status: ativo
date: 2026-08-20
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0093  # Multi-tenant Tier 0
  - 0189  # PageHeader canon v3.1
  - 0275  # Gate nasce advisory + forward-only
  - 0358  # Doutrina de teste — tenant 98
---

# RUNBOOK — Officeimpresso `Licencas/Index` (Onda 2, tela 3/14)

> **Escopo:** a tela **#3** do [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — primeira das
> 3 telas P1 da Onda 2. As outras duas têm RUNBOOK próprio: [RUNBOOK-empresa.md](RUNBOOK-empresa.md)
> (#4) e [RUNBOOK-empresas.md](RUNBOOK-empresas.md) (#5).
> Artefato **F1 PLAN** que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) exige
> e que o hook [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs) procura
> — ele resolve pelo **kebab do subdir** (`Licencas/` → `RUNBOOK-licencas.md`), não pelo filename
> genérico `Index` (lógica em `block-mwart-violation.mjs:98-111`).

| # | Blade origem | Rota | Page alvo | Padrão de Tela |
|---|---|---|---|---|
| 3 | `licenca_computador/index.blade.php` (137 ln) | `GET /officeimpresso/licenca_computador` | `Officeimpresso/Licencas/Index` | [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) |

## Estado final esperado

Uma Page Inertia servida por `LicencaComputadorController@index`, **dentro do módulo dono**
(`Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/`), atrás da feature flag
`useV2OfficeimpressoLicencas`, com o Blade intacto como rota de fuga até o cutover (F5).

## 1. Objetivo

Migrar a lista de máquinas licenciadas **da própria empresa** para o shell React, sem perder um campo
e sem mudar a operação. Nenhuma capacidade nova — o ganho é o shell único (AppShellV2, busca global,
tema) e sair do AdminLTE/DataTables/jQuery.

**Quem usa:** [W] (superadmin) e o suporte com `officeimpresso.access` (leitura). Bloquear/liberar
máquina exige `officeimpresso.licencas.gerenciar` — **ver não basta**, e isso já tem teste
(`LicencasAcessoPermissionTest:151`).

**Diferença Tier 0 em relação às telas de `Logs/`:** esta tela **não é cross-empresa**.
`LicencaComputadorController::index()` chama `listarPorEmpresa(session('user.business_id'))` — o
escopo é o business da sessão, sempre, para qualquer nível de permissão. Não existe aqui o
`podeVerTodasEmpresas()` dos Logs. Preservar isso é requisito, não detalhe.

## 2. Pré-condições

| # | Pré-condição | Como conferir |
|---|---|---|
| 1 | Permissões `officeimpresso.access` e `.licencas.gerenciar` existem | `LicencasAcessoPermissionTest:84` já assere que estão no `user_permissions` |
| 2 | Item no topnav do shell React | já existe — `Resources/menus/topnav.php`, item "Licenças" com `can: officeimpresso.access` |
| 3 | Glob de módulo do Vite ativo nas **duas** pontas | `grep -n "Modules/\*/Resources/js/Pages" resources/js/app.tsx resources/js/ssr.tsx` |
| 4 | Casing `Resources/` **maiúsculo** | `git ls-files "Modules/Officeimpresso/Resources/js/**"` — o glob do Vite é case-sensitive |
| 5 | F2 mergeada antes de tocar `.tsx` | `licencas-parity.md` existe + Pest baseline verde no CT 100 |

## 3. Passo-a-passo

### F2 — BACKEND BASELINE (1 PR)

1. **Pest baseline ANTES de mexer** — fixtures cobrindo o `index()` de hoje: 403 sem permissão
   (já coberto), 200 com `access` (já coberto), **escopo por `business_id` da sessão** (máquina de
   outro business não aparece — este é o furo que só a fixture pega), e a contagem dos 3 KPIs.
2. **Payload seguro** — extrair `buildLicencasPayload()` devolvendo **DTO explícito**, nunca o model.
   Ver §10 pegadinha 1: isto é Tier 0, não estilo.
3. **`Inertia::render` viaja com a TELA, não com a F2.** Render apontando pra page inexistente é 500
   esperando a flag ligar, e o `OrphanRenderGateTest` (required) reprova — corretamente. A F2 entrega
   a extração do payload + o baseline; a flag e o render entram no PR do `.tsx`.
4. **Só a flag decide o caminho dual.** Não condicionar ao header `X-Inertia`: o primeiro
   carregamento do Inertia é um GET de HTML comum e **não manda esse header** — exigi-lo faria a
   React nunca abrir por navegação direta. Medido e registrado na Onda 1
   ([RUNBOOK-logs §F2](RUNBOOK-logs.md)); o único dual em produção (`SellController::create`) decide
   só pela flag.
5. **Flag** `useV2OfficeimpressoLicencas`, default **OFF**. Via `FeatureFlagService`/GrowthBook —
   **não existe** comando `enable-v2` neste projeto (`git grep -lE 'enable-v2|enableV2' -- '*.php'`
   = zero). O default OFF sai de graça: enquanto a chave não estiver no `fallbackDefaults`, `isOn()`
   devolve `false` e o Blade segue servindo.
6. **`Inertia::defer`** na lista (`licencas`) e nos KPIs. `permissions` fica eager (Gate, ~0ms).
7. **`licencas-parity.md`** — mapa campo-a-campo ([template](../_DesignSystem/PARITY-TEMPLATE.md)).

### F3 — FRONTEND (1 PR)

8. `Licencas/Index.tsx` + `Index.charter.md` + `Index.casos.md` — PT-01, 6 slots.

### F4 — QA

9. Todo item de severidade `alta` do `licencas-parity.md` com teste de **comportamento** que quebra
   se o campo parar de aparecer/persistir, citando o id do UC. **Presença do arquivo não conta.**
10. Smoke real com screenshot 1280 + 1440, sem scroll horizontal com sidebar aberta.

### F5 — CUTOVER

11. Módulo interno (WR2 + suporte), sem cliente externo na tela — não precisa janela de aviso.
    Flag ON → observar → remover Blade + flag.
12. **A tela só entra no gate visual AQUI.** Com a flag OFF a rota devolve o Blade, o
    `assertInertia(component: ...)` do `PixelBaselineTest` não acha nada e o `visual-regression`
    (required) fica vermelho **para o repo inteiro**. O caminho e os 4 pré-requisitos (override de
    flag por ambiente, entrada no `visreg-screens.json`, `.snap` no mesmo commit, aprovação visual
    do [W]) estão escritos uma vez em [RUNBOOK-logs §F5 item 11](RUNBOOK-logs.md) — vale igual aqui,
    e esta tela é **mais simples** que a Timeline porque a rota não tem `{id}` (não precisa de
    registro determinístico no seed do visreg).

## 4. Tokens CSS

O mapa `oi-*` → canon do DS é **um só para o módulo** e já está escrito em
**[RUNBOOK-logs §4](RUNBOOK-logs.md)** — `.oi-page`/`.oi-page-header` → `AppShellV2`+`PageHeader`,
`.oi-kpi` → `KpiCard`, `.oi-card`+`.oi-table` → `DataTable`, `.oi-pill-*` → `StatusBadge`,
`.oi-btn-*` → `Button`, `.text-mono` → `font-mono`, cor crua de ícone → token semântico (R-DS-002).
**Não reescrever aqui** — `layouts/partials/design-system.blade.php` é o mesmo arquivo nas 5 telas.

Específico desta tela, além do mapa:

| `oi-*` legado | Canon React |
|---|---|
| `.oi-btn-warning` (Bloquear) | `<Button variant="outline">` + ícone `Lock` |
| `.oi-btn-success` (Desbloquear) | `<Button variant="outline">` + ícone `Unlock` |
| `.oi-btn-ghost` (Log) | `<Button variant="ghost">` |
| `.oi-pill-ok` / `.oi-pill-blocked` | `<StatusBadge kind="licenca">` — **o mesmo `kind` que a Onda 1 cria**; se `Logs/Index` ainda não tiver mergeado, esta tela o cria e a Onda 1 reusa |

> ⚠️ Os rótulos divergem entre telas do módulo para o **mesmo** campo `bloqueado`: aqui é
> "Liberada"/"Bloqueada"; em `Empresa/Show` é "Ativa"/"Bloqueada". Ver `licencas-parity.md` §D2.

## 5. Estados visuais

| Estado | Comportamento |
|---|---|
| **Loading** | skeleton da tabela dentro do `<Deferred>`; KPIs com skeleton próprio |
| **Vazio** | `<EmptyState>` com o texto do Blade: "Nenhuma licença cadastrada." + ação primária "Cadastrar" |
| **Erro** | toast via flash global (`app.tsx`) — o Blade de hoje não trata erro nesta tela |
| **Após bloquear/liberar** | o Blade faz `redirect()->back()->with('status', ...)`; em Inertia vira flash + reload parcial da prop `licencas` |
| **Sem permissão de gerenciar** | a coluna Ações mostra só "Log"; o botão de bloqueio **não renderiza** (hoje o Blade o mostra pra quem só tem `access` e o clique dá 403 — divergência D4 do parity) |

## 6. Responsividade

Alvo **1280px com sidebar aberta** (monitor do cliente piloto — [why-oimpresso](../../why-oimpresso.md)),
validar também 1440. São **8 colunas**, contra 10 da `Logs/Index` — cabe com folga. `Processador` é a
coluna larga (o Blade trunca em 35 com `title` completo); manter truncagem + tooltip. **Sem scroll
horizontal na página**; se precisar, o scroll é interno ao container da tabela.

## 7. Atalhos

Herdados do shell; nada específico nesta onda. Se algum for adicionado: `removeEventListener` no
cleanup e bloqueio quando o foco está em `<input>`.

## 8. Component contract

```tsx
// Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/Index.tsx
// namespace Inertia = 'Officeimpresso/Licencas/Index' (o local do arquivo NAO muda o namespace)
import AppShellV2 from '@/Layouts/AppShellV2';            // default
import { PageHeader } from '@/Components/PageHeader';      // NAMED (barrel)
import KpiCard from '@/Components/shared/KpiCard';         // default
import DataTable from '@/Components/shared/DataTable';     // default
import EmptyState from '@/Components/shared/EmptyState';   // default
import StatusBadge from '@/Components/shared/StatusBadge'; // default
import { Deferred } from '@inertiajs/react';
```

> ⚠️ O snippet do PT-01 importa `DataTable`, `BulkActionBar` e `EmptyState` como **named** — os três
> são **default**. Medido na Onda 1 com `grep -nE "^export (default|const)"` nos 5 arquivos. Use a
> lista acima; corrigir o PT-01 é PR à parte, e não deste módulo.

**Props:**

| Prop | Tipo | Defer? | Origem |
|---|---|---|---|
| `licencas` | `LicencaDTO[]` | **sim** | `LicencaService::listarPorEmpresa(business_id da sessão)` — ver pegadinha 1 |
| `kpis` | `{total, liberadas, bloqueadas}` | **sim** | hoje calculado no Blade com `collect()`; **passa a vir do backend** |
| `permissions` | `{pode_gerenciar}` | não | Gate (`officeimpresso.licencas.gerenciar`) |

`LicencaDTO` = `{id, hd, user_win, processador, memoria, versao_exe, bloqueado}`. **Sete campos, e só
esses** — a tabela tem 40 colunas e o model não tem `$hidden` (pegadinha 1).

## 9. DoD checklist

- [ ] F2: Pest baseline **rodado no CT 100** (nunca local — [proibicoes §Ambiente](../../proibicoes.md)), tenant 98
- [ ] F2: fixture provando que máquina de OUTRO business não aparece na lista
- [ ] F2: `licencas-parity.md` com todo campo do Blade mapeado + severidade
- [ ] F2: `buildLicencasPayload()` devolvendo DTO de 7 campos — **assert de que `senha`/`contra_senha`/`token`/`serial` NÃO estão na prop**
- [ ] F2: flag `useV2OfficeimpressoLicencas` default OFF
- [ ] F3: 1 PR, <=300 linhas, charter + casos ao lado do `.tsx`
- [ ] F3: `pages-colisao --check` verde
- [ ] F3: prova de bundle é o **manifest**, não o exit code do build
- [ ] F4: cada item `alta` da paridade com teste de comportamento citando o UC
- [ ] F4: smoke com screenshot 1280 + 1440
- [ ] F5: flag ON, observar, remover Blade + flag

## 10. Pegadinhas

1. **Tier 0 — o model inteiro vira JSON na prop, e ele carrega credencial.**
   `LicencaService::listarPorEmpresa()` devolve `Licenca_Computador::where(...)->get()` — o **model**.
   Em Blade isso é inofensivo (a view imprime só 7 campos); em Inertia **a prop inteira vai
   serializada no `data-page` do HTML**. E o model não tem `$hidden` nem `$casts`
   (`Entities/Licenca_Computador.php`: o `$fillable` lista `senha`, `contra_senha`, `serial` e
   `token`, e as quatro colunas existem no schema de `licenca_computador`). Portar "passando
   `$licencas` direto pra prop" **publica credencial de acesso remoto no fonte da página**. Por isso
   a F2 entrega DTO explícito e a F4 tem assert negativo. O mesmo vale pra `Empresa/Show`, que
   consome o mesmo Service.
2. **O subtítulo mente e o título não bate com o menu.** O H1 é "Computadores Cadastrados"
   (`lang.computadores_cadastrados`), o menu chama a tela de "Licenças", e o subtítulo diz "Todas as
   licenças de desktop cadastradas **no sistema**" — mas `index()` filtra pelo business da sessão.
   Não é "todas do sistema"; é "todas da minha empresa". Corrigir a copy é decisão [W]
   (item 2 do parity); **não** renomear a rota nesta onda.
3. **`toggle-block` é `GET` que muda estado.** `Route::get('/licenca_computador/{id}/toggle-block')`,
   protegido só pelo `title` do tooltip — esta tela **nem tem `confirm()`**, ao contrário da
   `Logs/Index`. Em React vira `POST` (divergência deliberada D1) **e ganha confirmação**: é a única
   capacidade nova desta tela, justificada por segurança, não por gosto.
4. **O botão "Cadastrar" leva pro Blade.** `licenca_computador/create` é a tela **#6, P2**, fora desta
   onda. Durante o dual-run o botão aponta pra rota Blade — e isso é correto. Não migrar o form aqui.
5. **`store()`/`update()` respondem `response()->json()`, não redirect.** Quem reusar essas actions a
   partir da tela React esperando o padrão Inertia recebe JSON cru. Fora do escopo desta onda, mas é
   a razão de o form ficar pra depois.
6. **`destroy` existe na API e não existe na UI.** A rota `DELETE` do resource + a permissão
   `officeimpresso.licencas.excluir` existem e têm teste, mas **nenhum Blade chama**. Não inventar
   botão de excluir na React — seria capacidade nova, e o delete é **hard** (a tabela não tem
   `deleted_at`), deixando o histórico em `licenca_log` órfão.
7. **DataTables faz busca, ordenação e paginação no cliente.** Não há nada disso no servidor: o
   Service faz `->get()` sem `paginate`. Com o `<DataTable>` do DS o comportamento segue client-side
   nesta onda — a lista é de uma empresa só. Se um dia estourar, paginação server-side é outra US.
8. **A ordenação default é por `id` desc** (`order: [[0,'desc']]`), não por data. Preservar.
9. **`Str::limit($licenca->processador, 35)` com `title` completo.** Truncar sem o tooltip perde
   informação; o Blade faz os dois.
10. **`hostname` não aparece nesta tela, `user_win` sim** — e `user_win` é nullable, sem tratamento
    no Blade: renderiza célula vazia, sem travessão. Em `Empresa/Show` o mesmo campo cai pra `—`.
    Escolher um comportamento é decisão [W] (item 5 do parity).
11. **A nav Blade some, e um teste assere ela.** `LicencasAcessoPermissionTest:290` verifica os links
    de `layouts/nav.blade.php` no HTML. Com a flag ON a tela deixa de renderizar essa nav (o shell
    React usa `topnav.php`). O teste **não quebra na F3** (a flag está OFF e ele bate no Blade), mas
    **quebra no F5** quando o Blade sair — a asserção precisa migrar pro topnav junto com o cutover.
12. **Tenant de teste é o 98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca** biz=4.

## 11. ADR de origem

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART, caminho único
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (aqui o escopo é o business da sessão, sem exceção)
- [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — PageHeader canon v3.1
- [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (camada 3 = Padrão de Tela)
- [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — plano do módulo (14 telas); esta é a #3
- [RUNBOOK-logs.md](RUNBOOK-logs.md) — Onda 1; dono do mapa `oi-*` e do caminho de entrada no gate visual

---

**Última atualização:** 2026-08-20 — criado na F1 da Onda 2 (escopo escolhido por [W]).
