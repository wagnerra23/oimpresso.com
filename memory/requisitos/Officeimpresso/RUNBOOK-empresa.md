---
owner: W
last_validated: "2026-08-20"
slug: officeimpresso-runbook-empresa
title: "Officeimpresso — RUNBOOK Empresa/Show (ficha da empresa + computadores)"
type: runbook
module: Officeimpresso
status: ativo
date: 2026-08-20
related:
  - 0104  # Processo MWART canônico (mãe)
  - 0093  # Multi-tenant Tier 0
  - 0066  # format_date shift +3h preservado
  - 0189  # PageHeader canon v3.1
  - 0358  # Doutrina de teste — tenant 98
---

# RUNBOOK — Officeimpresso `Empresa/Show` (Onda 2, tela 4/14)

> **Escopo:** a tela **#4** do [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — segunda das
> 3 telas P1 da Onda 2. As outras duas: `RUNBOOK-licencas.md` (#3) e
> `RUNBOOK-empresas.md` (#5).
> Artefato **F1 PLAN** que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
> exige e que o hook [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs)
> procura — ele resolve pelo **kebab do subdir** (`Empresa/` → `RUNBOOK-empresa.md`), não pelo
> filename genérico `Show`.

| # | Blade origem | Rotas | Page alvo | Padrão de Tela |
|---|---|---|---|---|
| 4 | `licenca_computador/computadores.blade.php` (182 ln) | `GET /officeimpresso/computadores` **e** `GET /officeimpresso/licenca_computado/licencas/{id}` | `Officeimpresso/Empresa/Show` | [PT-03 Detalhe](../_DesignSystem/padroes-tela/PT-03-Detalhe.md) |

> ⚠️ **Duas rotas, uma view — e isso não é engano.** `computadores()` (empresa da sessão) e
> `viewLicencas($id)` (empresa arbitrária, entrada do `businessall`) fazem `return view(...)` para o
> **mesmo** Blade, com as **mesmas** 4 variáveis. Uma Page React serve as duas; a diferença é só de
> onde vem o `business_id`. Ver §10 pegadinha 1 — a diferença de **escopo** entre elas é Tier 0 e
> precisa de decisão [W]. O path da segunda tem um typo histórico (`licenca_computado`, sem o "r");
> **não corrigir nesta onda** — é rota nomeada (`empresa.licencas`) e o `businessall` aponta pra ela.

## Estado final esperado

Uma Page Inertia servida pelas duas actions, **dentro do módulo dono**
(`Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresa/`), atrás da flag
`useV2OfficeimpressoEmpresa`, com o Blade intacto como rota de fuga até o cutover (F5).

## 1. Objetivo

Migrar a ficha de uma empresa licenciada — dados cadastrais, situação da assinatura, e a tabela de
computadores dela — para o shell React, sem perder um campo e sem mudar a operação. É a tela de
trabalho do suporte: dela saem o bloqueio da empresa inteira, a edição da versão obrigatória e o
deep-link pro log.

**Quem usa:** [W] (superadmin) e o suporte com `officeimpresso.access` (leitura). As duas ações de
escrita têm guardas **diferentes e mais restritas**, já testadas:
`officeimpresso.licencas.gerenciar` pro toggle da máquina (`LicencasAcessoPermissionTest:151`) e
`officeimpresso.empresa.gerenciar` pro bloqueio da empresa e pra edição (`:197`). **Ter uma não
concede a outra** — há teste de no-leak pros dois sentidos (`:179`, `:218`).

## 2. Pré-condições

| # | Pré-condição | Como conferir |
|---|---|---|
| 1 | As 3 permissões existem e estão no `user_permissions` | `LicencasAcessoPermissionTest:84` já assere |
| 2 | Item no topnav do shell React | já existe — `Resources/menus/topnav.php`, "Computadores" com `can: officeimpresso.access` |
| 3 | Glob de módulo do Vite ativo nas **duas** pontas | `grep -n "Modules/\*/Resources/js/Pages" resources/js/app.tsx resources/js/ssr.tsx` |
| 4 | Casing `Resources/` **maiúsculo** | `git ls-files "Modules/Officeimpresso/Resources/js/**"` |
| 5 | Decisão [W] sobre o escopo do `viewLicencas` (§10.1) | **antes da F2** — muda o que a fixture assere |
| 6 | F2 mergeada antes de tocar `.tsx` | `empresa-parity.md` existe + Pest baseline verde no CT 100 |

## 3. Passo-a-passo

### F2 — BACKEND BASELINE (1 PR)

1. **Pest baseline ANTES de mexer** — 403 sem permissão (já coberto), 200 com `access` (já coberto),
   os dois no-leak de escrita (já cobertos), a ficha com `package`/`active` **ausentes** (os dois
   blocos são condicionais e a tela tem que abrir sem eles), e o escopo das duas rotas conforme a
   decisão do item 5 das pré-condições.
2. **Payload seguro** — `buildEmpresaPayload()` + `buildComputadoresPayload()` devolvendo **DTO
   explícito**, nunca os models. Vale pras duas pontas: `Licenca_Computador` carrega `senha` e
   `contra_senha` (ver `RUNBOOK-licencas §10.1`) e `Business` é um model gordo,
   com dados de todo o ERP que esta tela não usa.
3. **`Inertia::render` viaja com a TELA, não com a F2.** Render apontando pra page inexistente é 500
   esperando a flag ligar, e o `OrphanRenderGateTest` (required) reprova — corretamente.
4. **Só a flag decide o caminho dual.** Não condicionar ao header `X-Inertia`: o primeiro
   carregamento do Inertia é um GET de HTML comum e **não manda esse header**. Medido na Onda 1
   ([RUNBOOK-logs §F2](RUNBOOK-logs.md)).
5. **Flag** `useV2OfficeimpressoEmpresa`, default **OFF** — via `FeatureFlagService`/GrowthBook.
   **Não existe** comando `enable-v2` neste projeto (`git grep -lE 'enable-v2|enableV2' -- '*.php'`
   = zero); default OFF sai do `fallbackDefaults` não listar a chave.
   > ⚠️ A flag tem que valer para as **duas** rotas. Ligar só uma deixa o suporte navegando entre
   > shells diferentes ao clicar "Ver computadores desta empresa" no `businessall`.
6. **`Inertia::defer`** em `computadores` (lista) e em `assinatura` (`Subscription::active_subscription`
   + `Package::find` = 2 queries). `empresa` e `permissions` ficam eager — 1 row e Gate.
7. **`empresa-parity.md`** — mapa campo-a-campo ([template](../_DesignSystem/PARITY-TEMPLATE.md)).

### F3 — FRONTEND (1 PR)

8. `Empresa/Show.tsx` + `Show.charter.md` + `Show.casos.md` — PT-03, com o modal de edição virando
   `<Dialog>` do DS (§10 pegadinha 6).

### F4 — QA

9. Todo item de severidade `alta` do `empresa-parity.md` com teste de **comportamento** que quebra se
   o campo parar de aparecer/persistir, citando o id do UC. **Presença do arquivo não conta.**
10. Smoke real com screenshot 1280 + 1440, **nas duas rotas**.

### F5 — CUTOVER

11. Módulo interno (WR2 + suporte), sem cliente externo na tela. Flag ON → observar → remover Blade
    + flag. **As duas rotas saem juntas.**
12. **A tela só entra no gate visual AQUI**, pelo mesmo motivo da Onda 1: com a flag OFF a rota
    devolve o Blade e o `assertInertia(component: ...)` não acha nada, deixando o `visual-regression`
    (required) vermelho pro repo inteiro. Os 4 pré-requisitos estão escritos uma vez em
    [RUNBOOK-logs §F5 item 11](RUNBOOK-logs.md). **Esta tela tem a mesma dificuldade da Timeline:**
    a rota `viewLicencas` exige `{id}`, logo precisa de uma empresa determinística no seed do visreg
    — a entrada no `visreg-screens.json` deve usar a rota **`/officeimpresso/computadores`** (sem
    parâmetro), que renderiza a mesma Page a partir da sessão.

## 4. Tokens CSS

O mapa `oi-*` → canon do DS é **um só para o módulo** e mora em
**[RUNBOOK-logs §4](RUNBOOK-logs.md)**. **Não reescrever aqui.** Específico desta tela:

| `oi-*` legado | Canon React |
|---|---|
| `.oi-company` (bloco de dados da empresa) | seção de conteúdo do PT-03 (slot 3), `<dl>` semântica |
| `.oi-company .actions` | slot 5 do PT-03 (ações contextuais) |
| `.oi-pill` no `hdr` do card | `<StatusBadge kind="empresa">` — **`kind` novo**, distinto do `kind="licenca"`: aqui o sujeito é a EMPRESA (`officeimpresso_bloqueado`), não a máquina |
| `.oi-btn-danger` / `.oi-btn-success` (toggle empresa) | `<Button variant="destructive">` / `<Button variant="outline">` |
| `modal fade` + `Form::open` (Bootstrap 3 + shim spatie) | `<Dialog>` do DS + `useForm` do Inertia |
| ícones `fa fa-*` da ficha | `Icon` (Lucide) — `Building2`, `User`, `MapPin`, `Phone`, `GitBranch`, `Download`, `Database`, `Clock`, `Monitor`, `Calendar`, `Hourglass` |

## 5. Estados visuais

| Estado | Comportamento |
|---|---|
| **Loading** | skeleton do bloco de assinatura + skeleton da tabela, dentro dos `<Deferred>` |
| **Sem `package`** | o bloco "Limite de Máquinas" **não renderiza** (hoje é `@if(isset($package) && !empty($package))`) — preservar a ausência, não inventar "—" |
| **Sem `active`** (sem assinatura vigente) | os blocos de vencimento e de dias restantes **não renderizam** — mesma regra |
| **Tabela vazia** | `<EmptyState>` com `lang.no_records_found` = "Nenhum registro encontrado" |
| **Após salvar o modal** | hoje é `redirect()->back()->with('status', ...)` injetado por jQuery **acima do header**; em Inertia vira toast do flash global + fecha o diálogo |
| **Erro ao salvar** | o controller devolve `with('error', ...)` — o Blade de hoje **só renderiza o `status`, nunca o `error`** (o `@if(session('status'))` do `@section('javascript')` é o único). Em React os dois viram toast — divergência D5 do parity |
| **Sem permissão de escrita** | "Editar" e o toggle da empresa não renderizam (o servidor segue sendo a autoridade) |

## 6. Responsividade

Alvo **1280px com sidebar aberta** (monitor do cliente piloto — [why-oimpresso](../../why-oimpresso.md)),
validar também 1440. A tabela tem **10 colunas** — mesmo número da `Logs/Index`, que **estoura** a
1280 em porte literal. Colapsar as de menor densidade (`Banco`, `Executável` — as duas já são
truncadas em 20/30 chars pelo Blade) para segunda linha da célula principal. A ficha usa o layout
2-col do PT-03 (8/4). **Sem scroll horizontal na página.**

## 7. Atalhos

Herdados do shell. O `<Dialog>` traz `Esc` para fechar — o modal Bootstrap de hoje também tem, e
ainda faz `form.reset()` no `hidden.bs.modal`; preservar o descarte do rascunho ao fechar.

## 8. Component contract

```tsx
// Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresa/Show.tsx
// namespace Inertia = 'Officeimpresso/Empresa/Show' (o local do arquivo NAO muda o namespace)
import AppShellV2 from '@/Layouts/AppShellV2';            // default
import { PageHeader } from '@/Components/PageHeader';      // NAMED (barrel)
import DataTable from '@/Components/shared/DataTable';     // default
import EmptyState from '@/Components/shared/EmptyState';   // default
import StatusBadge from '@/Components/shared/StatusBadge'; // default
import { Dialog } from '@/Components/ui/dialog';           // NAMED
import { Deferred, useForm } from '@inertiajs/react';
```

**Props:**

| Prop | Tipo | Defer? | Origem |
|---|---|---|---|
| `empresa` | `EmpresaDTO` | não | `Business::where('id', $business_id)->first()` — 1 row |
| `computadores` | `ComputadorDTO[]` | **sim** | `LicencaService::listarPorEmpresa()` — ver pegadinha 2 |
| `assinatura` | `{fim, dias_restantes, limite_maquinas} \| null` | **sim** | `Subscription::active_subscription()` + `Package::find()` |
| `permissions` | `{pode_gerenciar_maquina, pode_gerenciar_empresa}` | não | Gate |

`EmpresaDTO` = `{id, name, razao_social, rua, telefone, versao_obrigatoria, versao_disponivel,
caminho_banco_servidor, dt_ultimo_acesso, officeimpresso_bloqueado}` — **10 campos, e só esses**.

`ComputadorDTO` = `{id, dt_cadastro, user_win, pasta_instalacao, versao_exe, ip_interno,
caminho_banco, dt_ultimo_acesso, bloqueado}` — **9 campos**. Nunca o model (pegadinha 2).

## 9. DoD checklist

- [ ] F2: Pest baseline **rodado no CT 100** (nunca local — [proibicoes §Ambiente](../../proibicoes.md)), tenant 98
- [ ] F2: fixture com `package`/`active` ausentes — a tela abre sem os dois blocos
- [ ] F2: fixture do escopo das duas rotas, conforme a decisão [W] do §10.1
- [ ] F2: DTOs explícitos — **assert de que `senha`/`contra_senha`/`serial`/`token` NÃO estão na prop**
- [ ] F2: `empresa-parity.md` com todo campo do Blade mapeado + severidade
- [ ] F2: flag `useV2OfficeimpressoEmpresa` default OFF, valendo pras **duas** rotas
- [ ] F3: 1 PR, <=300 linhas, charter + casos ao lado do `.tsx`
- [ ] F3: `pages-colisao --check` verde
- [ ] F3: prova de bundle é o **manifest**, não o exit code do build
- [ ] F4: cada item `alta` da paridade com teste de comportamento citando o UC
- [ ] F4: smoke 1280 + 1440 **nas duas rotas**
- [ ] F5: flag ON, observar, remover Blade + flag

## 10. Pegadinhas

1. **Tier 0 — as duas rotas têm a MESMA guarda e escopos DIFERENTES.** `computadores()` usa
   `session('user.business_id')`; `viewLicencas($id)` usa o `{id}` da URL **sem filtrar nada**. As
   duas chamam só `authorizeAccess()`. Efeito medido no código: quem tem `officeimpresso.access`
   abre a ficha de **qualquer** empresa trocando o id na URL. Isso pode ser **intencional** — o
   módulo é a ponte da WR2 pros clientes dela, e as telas de `Logs/` têm um cross-empresa
   igualmente deliberado — mas ali existe um `podeVerTodasEmpresas()` explícito, e **aqui não existe
   guarda nenhuma nomeando essa escolha**. **É decisão [W], e ela precede a F2**, porque define o
   que a fixture assere: (a) manter como está e nomear a intenção numa guarda explícita, ou (b)
   escopar `viewLicencas` a quem tem permissão de ver todas. Não decidir por conta própria.
2. **O model inteiro vira JSON na prop, e ele carrega credencial.** Mesmo mecanismo da
   `RUNBOOK-licencas §10.1`, e esta tela consome o **mesmo** Service: em Blade
   inofensivo, em Inertia a prop vai serializada no `data-page`. `Licenca_Computador` não tem
   `$hidden` e o `$fillable` lista `senha`, `contra_senha`, `serial` e `token`. Vale também pro
   `Business`, que é um model gordo do ERP inteiro.
3. **`@format_date` no `end_date` carrega o shift +3h.** É customização **preservada de propósito**
   ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)). A React
   não pode formatar a data por conta própria e "consertar" o deslocamento: ou o backend manda a
   string já formatada, ou a divergência aparece pro cliente que convive com ela há anos.
4. **"Dias restantes" usa `Carbon::today()->diffInDays($active->end_date)`.** `diffInDays` devolve
   **valor absoluto**: assinatura vencida há 10 dias mostra "10 dias restantes", não "-10". É o
   comportamento de hoje; **portar como está** e registrar (item 14 do parity, severidade `média`) —
   corrigir é decisão [W], não conserto silencioso.
5. **`officeimpresso_limitemaquinas == 0` significa "Ilimitado"**, não "zero". Tratar 0 como falsy
   inverte a regra: o cliente com plano ilimitado veria limite zero.
6. **O modal de edição não é drawer 760.** O canon do drawer ([ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md))
   é pra **entidade cadastral**; aqui são 3 campos de configuração do desktop legacy de uma empresa.
   `<Dialog>` do DS é a forma certa — e isso é escolha declarada, não omissão.
7. **O `businessupdate` valida um campo que o form não manda.** A validação pede `caminho_banco` e o
   `$request->only(...)` lê `caminho_banco_servidor`; o input do modal se chama
   `caminho_banco_servidor`. Como a regra é `nullable`, nada quebra hoje — a validação simplesmente
   **não protege o campo que de fato é salvo**. Ao migrar, validar o nome real. É correção de
   segurança de dado, e entra declarada como divergência D3 do parity.
8. **`officeimpresso_numerodemaquinas` está no `$request->only()` e não existe no formulário.** O
   campo nunca chega, então o `?? $empresa->...` do Service preserva o valor. Não inventar o input na
   React — seria capacidade nova.
9. **Esta tela não usa DataTables.** Diferente das telas #3 e #5, o `@section('javascript')` só faz o
   flash e o reset do modal: **não há busca, ordenação nem paginação** na tabela de computadores.
   Adicionar as três com o `<DataTable>` do DS é ganho real, mas **é capacidade nova** — declarar
   como divergência (D4) em vez de deixar acontecer por acaso.
10. **O `<h1>` é "Licenças Office Impresso", não "Empresa".** A Page se chama `Empresa/Show` porque a
    entidade é a empresa, mas o título da tela é o do Blade. Mesma classe da pegadinha #2 da Onda 1
    (nome da Page ≠ título visível). Não "consertar" renomeando a rota nesta onda.
11. **"Ver pacote" sai do módulo.** Aponta pro `SubscriptionController@index` do `Modules/Superadmin`,
    que **não** faz parte desta migração. Link normal, sem `<Link>` do Inertia se a tela destino for
    Blade — senão o Inertia tenta interpretar a resposta HTML como página.
12. **A nav Blade some, e um teste assere ela.** `LicencasAcessoPermissionTest:290` verifica os links
    de `layouts/nav.blade.php` no HTML. Não quebra na F3 (a flag está OFF), **quebra no F5** quando o
    Blade sair — a asserção migra pro `topnav.php` junto com o cutover.
13. **Tenant de teste é o 98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca** biz=4.

## 11. ADR de origem

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART, caminho único
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (e a pergunta aberta do §10.1)
- [ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) — `format_date` shift +3h preservado
- [ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md) — drawer 760 é pra entidade cadastral (por isso aqui é `Dialog`)
- [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (camada 3 = Padrão de Tela)
- [PT-03 Detalhe](../_DesignSystem/padroes-tela/PT-03-Detalhe.md) — padrão desta tela (golden `Sells/Show.tsx`)
- [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — plano do módulo (14 telas); esta é a #4
- [RUNBOOK-logs.md](RUNBOOK-logs.md) — Onda 1; dono do mapa `oi-*` e do caminho de entrada no gate visual

---

**Última atualização:** 2026-08-20 — criado na F1 da Onda 2 (escopo escolhido por [W]).
