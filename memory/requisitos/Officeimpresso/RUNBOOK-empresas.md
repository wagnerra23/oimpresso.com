---
owner: W
last_validated: "2026-08-20"
slug: officeimpresso-runbook-empresas
title: "Officeimpresso — RUNBOOK Empresas/Index (Empresas Licenciadas)"
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

# RUNBOOK — Officeimpresso `Empresas/Index` (Onda 2, tela 5/14)

> **Escopo:** a tela **#5** do [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — última das
> 3 telas P1 da Onda 2. As outras duas: `RUNBOOK-licencas.md` (#3) e
> `RUNBOOK-empresa.md` (#4).
> Artefato **F1 PLAN** que a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
> exige e que o hook [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs)
> procura — ele resolve pelo **kebab do subdir** (`Empresas/` → `RUNBOOK-empresas.md`).

| # | Blade origem | Rota | Page alvo | Padrão de Tela |
|---|---|---|---|---|
| 5 | `licenca_computador/businessall.blade.php` (140 ln) | `GET /officeimpresso/businessall` | `Officeimpresso/Empresas/Index` | [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) |

> ⚠️ **`Empresas/` (esta, lista) e `Empresa/` (a #4, ficha) são subdirs distintos e RUNBOOKs
> distintos** — `RUNBOOK-empresas.md` × `RUNBOOK-empresa.md`. O hook resolve por nome exato
> (case-insensitive), então não há colisão mecânica; a confusão é humana. Ao editar, conferir o
> plural. Consolidar as duas telas num subdir só (como a Onda 1 fez com `Logs/`) seria mais legível,
> mas contraria o inventário que [W] fixou — **é decisão dele, não conserto de rota**.

## Estado final esperado

Uma Page Inertia servida por `LicencaComputadorController@businessall`, **dentro do módulo dono**
(`Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresas/`), atrás da flag
`useV2OfficeimpressoEmpresas`, com o Blade intacto como rota de fuga até o cutover (F5).

## 1. Objetivo

Migrar a lista das empresas com desktop legacy licenciado para o shell React. É a porta de entrada
do suporte: dela se chega na ficha de cada empresa (tela #4) e no log dela (Onda 1).

**Quem usa:** [W] (superadmin) e o suporte com `officeimpresso.access`. **Esta tela é global por
design** — `listarEmpresasComDesktop()` faz `Business::where('is_officeimpresso', true)->get()`, sem
recorte por sessão. É a lista de *todos* os clientes da WR2, e é literalmente o que o título promete
("Empresas Licenciadas"). Diferente da tela #3, onde o escopo é a empresa da sessão.

## 2. Pré-condições

| # | Pré-condição | Como conferir |
|---|---|---|
| 1 | Permissão `officeimpresso.access` existe e está atribuída | `LicencasAcessoPermissionTest:84` já assere |
| 2 | Item no topnav do shell React | já existe — `Resources/menus/topnav.php`, "Empresas Licenciadas" com `can: officeimpresso.access` |
| 3 | Glob de módulo do Vite ativo nas **duas** pontas | `grep -n "Modules/\*/Resources/js/Pages" resources/js/app.tsx resources/js/ssr.tsx` |
| 4 | Casing `Resources/` **maiúsculo** | `git ls-files "Modules/Officeimpresso/Resources/js/**"` |
| 5 | **Decisão [W] sobre as 4 colunas mortas** (§10.1) | **antes da F2** — muda o payload e o número de colunas |
| 6 | F2 mergeada antes de tocar `.tsx` | `empresas-parity.md` existe + Pest baseline verde no CT 100 |

## 3. Passo-a-passo

### F2 — BACKEND BASELINE (1 PR)

1. **Pest baseline ANTES de mexer** — 403 sem permissão (já coberto), 200 com `access` (já coberto),
   o filtro `is_officeimpresso = true` (empresa sem o flag **não** aparece — a fixture que trava a
   regra), e a contagem dos 3 KPIs.
2. **Payload seguro** — `buildEmpresasPayload()` devolvendo **DTO explícito**, nunca o model:
   `Business` carrega o cadastro inteiro do ERP e a prop de Inertia é serializada no `data-page` do
   HTML.
3. **`Inertia::render` viaja com a TELA, não com a F2** — render apontando pra page inexistente é 500
   esperando a flag ligar, e o `OrphanRenderGateTest` (required) reprova, corretamente.
4. **Só a flag decide o caminho dual.** Não condicionar ao header `X-Inertia`: o primeiro
   carregamento do Inertia é um GET de HTML comum e **não manda esse header**. Medido na Onda 1
   ([RUNBOOK-logs §F2](RUNBOOK-logs.md)).
5. **Flag** `useV2OfficeimpressoEmpresas`, default **OFF** — `FeatureFlagService`/GrowthBook.
   **Não existe** comando `enable-v2` neste projeto (`git grep -lE 'enable-v2|enableV2' -- '*.php'`
   = zero).
6. **`Inertia::defer`** na lista e nos KPIs.
7. **`empresas-parity.md`** — mapa campo-a-campo ([template](../_DesignSystem/PARITY-TEMPLATE.md)).

### F3 — FRONTEND (1 PR)

8. `Empresas/Index.tsx` + `Index.charter.md` + `Index.casos.md` — PT-01, 6 slots.

### F4 — QA

9. Todo item de severidade `alta` do `empresas-parity.md` com teste de **comportamento** que quebra
   se o campo parar de aparecer, citando o id do UC. **Presença do arquivo não conta.**
10. Smoke real com screenshot 1280 + 1440.

### F5 — CUTOVER

11. Módulo interno, sem cliente externo na tela. Flag ON → observar → remover Blade + flag.
12. **A tela só entra no gate visual AQUI**, pelo motivo já medido na Onda 1: com a flag OFF a rota
    devolve o Blade e o `assertInertia(component: ...)` não acha nada, deixando o
    `visual-regression` (required) vermelho pro repo inteiro. Os 4 pré-requisitos estão escritos uma
    vez em [RUNBOOK-logs §F5 item 11](RUNBOOK-logs.md). Esta tela é das **mais fáceis** de entrar:
    rota sem parâmetro e sem estado de sessão relevante.

## 4. Tokens CSS

O mapa `oi-*` → canon do DS é **um só para o módulo** e mora em
**[RUNBOOK-logs §4](RUNBOOK-logs.md)**. **Não reescrever aqui.** Específico desta tela:

| `oi-*` legado | Canon React |
|---|---|
| `.oi-pill-ok` / `.oi-pill-blocked` | `<StatusBadge kind="empresa">` — o **mesmo `kind`** que a tela #4 cria (sujeito = empresa, campo `officeimpresso_bloqueado`). Se a #4 ainda não tiver mergeado, esta o cria e a #4 reusa |
| `.oi-btn-primary oi-btn-xs` (ícone só, "Ver computadores") | `<Button variant="outline" size="icon">` + `Monitor`, **com `aria-label`** — hoje o único rótulo é o `title` |
| `.oi-btn-ghost oi-btn-xs` (ícone só, "Ver log") | `<Button variant="ghost" size="icon">` + `ClipboardList`, idem |
| `style="margin-bottom: 14px"` inline nos KPIs | grid do PT-01 — **estilo inline não atravessa** |

## 5. Estados visuais

| Estado | Comportamento |
|---|---|
| **Loading** | skeleton da tabela dentro do `<Deferred>`; KPIs com skeleton próprio |
| **Vazio** | `<EmptyState>` com `lang.no_records_found` = "Nenhum registro encontrado". Vale dizer **por que** está vazio: nenhuma empresa tem `is_officeimpresso = true` |
| **Vazio com busca** | "Nenhuma empresa encontrada" (`zeroRecords` do DataTables) |
| **Erro** | toast via flash global (`app.tsx`) — o Blade de hoje não trata erro nesta tela |

## 6. Responsividade

Alvo **1280px com sidebar aberta** (monitor do cliente piloto — [why-oimpresso](../../why-oimpresso.md)),
validar também 1440. São **11 colunas** — a maior contagem das 5 telas do módulo, e **estoura a
1280** em porte literal. Duas coisas ajudam antes de recorrer a colapso: a decisão do §10.1 pode
**remover 4 colunas** (as que nunca mostram dado), e `Razão Social` costuma repetir `Nome`, cabendo
como segunda linha da mesma célula. **Sem scroll horizontal na página.**

## 7. Atalhos

Herdados do shell; nada específico nesta onda.

## 8. Component contract

```tsx
// Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresas/Index.tsx
// namespace Inertia = 'Officeimpresso/Empresas/Index' (o local do arquivo NAO muda o namespace)
import AppShellV2 from '@/Layouts/AppShellV2';            // default
import { PageHeader } from '@/Components/PageHeader';      // NAMED (barrel)
import KpiCard from '@/Components/shared/KpiCard';         // default
import DataTable from '@/Components/shared/DataTable';     // default
import EmptyState from '@/Components/shared/EmptyState';   // default
import StatusBadge from '@/Components/shared/StatusBadge'; // default
import { Deferred } from '@inertiajs/react';
```

**Props:**

| Prop | Tipo | Defer? | Origem |
|---|---|---|---|
| `empresas` | `EmpresaListaDTO[]` | **sim** | `LicencaService::listarEmpresasComDesktop()` |
| `kpis` | `{total, ativas, bloqueadas}` | **sim** | hoje calculado no Blade com `collect()`; **passa a vir do backend** |
| `permissions` | `{}` (a tela não tem ação de escrita) | não | — |

`EmpresaListaDTO` = `{id, name, razao_social, cnpj, versao_disponivel, officeimpresso_bloqueado}` —
**6 campos**, mais os que a decisão do §10.1 acrescentar. Nunca o model `Business`.

## 9. DoD checklist

- [ ] F2: Pest baseline **rodado no CT 100** (nunca local — [proibicoes §Ambiente](../../proibicoes.md)), tenant 98
- [ ] F2: fixture provando que empresa **sem** `is_officeimpresso` não aparece
- [ ] F2: DTO explícito — **assert de que o model `Business` cru não vai na prop**
- [ ] F2: `empresas-parity.md` com todo campo do Blade mapeado + severidade
- [ ] F2: flag `useV2OfficeimpressoEmpresas` default OFF
- [ ] F3: 1 PR, <=300 linhas, charter + casos ao lado do `.tsx`
- [ ] F3: `pages-colisao --check` verde
- [ ] F3: prova de bundle é o **manifest**, não o exit code do build
- [ ] F4: cada item `alta` da paridade com teste de comportamento citando o UC
- [ ] F4: smoke com screenshot 1280 + 1440
- [ ] F5: flag ON, observar, remover Blade + flag

## 10. Pegadinhas

1. **Quatro das 11 colunas nunca mostram dado — e a migração é a hora de decidir.** `versao_minima`,
   `quantidade_maquinas`, `caminho_banco` e `ultimo_acesso` **não existem** na tabela `business` nem
   como accessor do model. Medido: `versao_minima`, `quantidade_maquinas` e `ultimo_acesso` (sem o
   prefixo `dt_`) aparecem **1 vez cada no repo inteiro — no próprio Blade**; `business.caminho_banco`
   dá **0** no `mysql-schema.sql` (a coluna com esse nome existe em `licenca_computador`, que é outra
   tabela). Resultado prático: as 4 células renderizam `—` para toda empresa, desde sempre. **Os
   equivalentes reais existem** e estão na mesma tabela: `versao_obrigatoria`,
   `officeimpresso_numerodemaquinas`, `caminho_banco_servidor` e `dt_ultimo_acesso`.
   **Decisão [W], antes da F2**, entre:
   **(a)** não portar as 4 — a tela fica com 7 colunas, cabe a 1280, e nada é perdido porque nada era
   mostrado; ou
   **(b)** portar apontando pros campos reais — a tela passa a mostrar dado que nunca apareceu.
   É **capacidade nova**, provavelmente o que sempre se quis, e por isso não se decide sozinho.
   *Recomendação:* (b) para `dt_ultimo_acesso` e `versao_obrigatoria` — são os dois que o suporte usa
   pra decidir atualização de cliente — e (a) para as outras duas, que já têm dono melhor na ficha.
   Enquanto não houver decisão, a F2 entrega o DTO de 6 campos do §8 e o parity mantém os 4 itens
   como `⏳` com a decisão apontada.
2. **O model `Business` não pode ir cru pra prop.** Ele carrega o cadastro inteiro do ERP (dados
   fiscais, configuração, chaves de integração) e a prop de Inertia é **serializada no HTML da
   página**. Em Blade isso não acontece — a view imprime só as colunas que usa. DTO explícito, sempre.
3. **`is_officeimpresso` é o filtro que define a tela.** `Business::where('is_officeimpresso', true)`
   — sem ele a lista viraria "todas as empresas do ERP". Vira fixture na F2.
4. **As duas ações são ícones sem rótulo.** O único texto é o `title` do `<a>` — invisível pra leitor
   de tela e pra quem navega por teclado. Na React, `aria-label` obrigatório. É correção de
   acessibilidade, declarada como divergência (D3), não port literal.
5. **"Ver computadores" aponta pro path com typo** (`/officeimpresso/licenca_computado/licencas/{id}`,
   sem o "r"). É rota nomeada (`empresa.licencas`) e serve a tela #4. **Não corrigir nesta onda** —
   renomear rota é mudança de contrato, e o Blade e a React precisam apontar pro mesmo lugar durante
   o dual-run.
6. **Esta tela é global por design, e isso é coerente.** Ela lista todos os clientes da WR2 e o
   título diz exatamente isso. Não confundir com a pergunta aberta da tela #4 (§10.1 do
   `RUNBOOK-empresa.md`), que é sobre uma rota **de ficha** aceitar id arbitrário
   sem guarda nomeando a intenção. Aqui a intenção está no nome da tela; lá, não está em lugar nenhum.
7. **KPIs são calculados no Blade**, com `collect($business)->where(...)`, não no controller. Na React
   vêm do backend como prop (mesma decisão das telas irmãs) — o número não muda, a origem sim.
8. **DataTables faz busca, ordenação e paginação no cliente**; o Service faz `->get()` sem `paginate`.
   O `<DataTable>` do DS mantém os três client-side. São ~37 empresas (ordem de grandeza citada no
   inventário do RUNBOOK do módulo); se um dia crescer, paginação server-side é outra US.
9. **A ordenação default é por `id` desc** (`order: [[0,'desc']]`). Preservar.
10. **A nav Blade some, e um teste assere ela.** `LicencasAcessoPermissionTest:290` verifica os links
    de `layouts/nav.blade.php`. Não quebra na F3 (flag OFF), **quebra no F5** — a asserção migra pro
    `topnav.php` junto com o cutover, e como esta é a última tela da onda, é aqui que o débito vence.
11. **Tenant de teste é o 98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca** biz=4.

## 11. ADR de origem

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART, caminho único
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 (aqui a visão global é por design, e o título a declara)
- [ADR 0189](../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — PageHeader canon v3.1
- [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (camada 3 = Padrão de Tela)
- [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — plano do módulo (14 telas); esta é a #5
- [RUNBOOK-logs.md](RUNBOOK-logs.md) — Onda 1; dono do mapa `oi-*` e do caminho de entrada no gate visual
- `RUNBOOK-empresa.md` — a tela #4, destino do botão "Ver computadores"

---

**Última atualização:** 2026-08-20 — criado na F1 da Onda 2 (escopo escolhido por [W]).
