---
page: /ia/superadmin/metas
component: resources/js/Pages/Jana/Plataforma.tsx
owner: wagner
status: draft
parent_module: Jana
related_prototype: prototipo-ui/cowork/jana-telas-novas.jsx
# US-COPI-148 é a fusão da área `/ia` — a US que de fato cobre esta tela. NÃO uso
# US-COPI-010/011 ("listar metas" / "detalhe"): aquelas são o CRUD do tenant, em outra
# rota e outro controller (`MetasController`), e amarrá-las aqui faria o anchor apontar
# para código que esta tela não toca. Mesma escolha do charter irmão de Alertas (#6607)
# e do #6608. Se um dia nascer US própria da visão de plataforma, ela entra aqui.
related_us: [US-COPI-148]
runbook: memory/requisitos/Jana/RUNBOOK-plataforma.md
related_casos:
  - resources/js/Pages/Jana/Plataforma.casos.md
alcance:
  rota: /ia/superadmin/metas
  rota_nome: jana.superadmin.metas   # a rota JÁ EXISTIA — não foi criada nesta migração
  permission: jana.access            # gate do GRUPO /ia; o gate da TELA são as 2 portas do §Gate
  menu_hook: Modules/Jana/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: jana_module                # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Jana/Plataforma (DRAFT · carimbado do PT-01)

> Esqueleto carimbado do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013).
> Golden do arquétipo: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
>
> ⚠️ **Quem mede esta tela é o `contrato-de-tela`, NÃO o `pt-conformance`** — e isso é o desenho,
> não uma lacuna: o `pt-conformance` só avalia telas cujo `related_prototype` declara um token
> `PT-0X`; tela ancorada em protótipo Cowork fica **fora do escopo** dele de propósito (o
> `--selftest` dele carimba isso: `related_prototype: prototipo-ui/cowork/…` → sem PT declarado).
> Aqui a fonte de verdade visual é o protótipo, e a régua é
> [`prototipo-ui/contrato/jana-plataforma.contract.json`](../../../../prototipo-ui/contrato/jana-plataforma.contract.json),
> com mordida provada em 31/08 (mutar copy → `exit 1`; restaurar → `exit 0`).
> F1 do MWART: [`RUNBOOK-plataforma.md`](../../../../memory/requisitos/Jana/RUNBOOK-plataforma.md).
> Casos: [`Plataforma.casos.md`](./Plataforma.casos.md).
> Sobe de `draft` → `live` com screenshot aprovado por [W].

## Mission

Dar a quem administra a plataforma a visão das metas que **não** são de um tenant só: as da
própria plataforma (`business_id NULL`) e as de todos os clientes, lado a lado — a única tela
do produto que lê fora do escopo do negócio **por desenho**.

## Goals — Features (faz)

- Lista as **metas da plataforma** (`business_id NULL`): Nome · Unidade · Origem
- Lista as **metas de clientes** (cross-business): Business · Nome · Unidade · Período atual ·
  Última apuração — as 2 últimas colunas vindas do eager load `periodoAtual`/`ultimaApuracao`
  que o controller **já fazia** e a Blade não usava
- Esmaece a linha de meta **nunca apurada** (o `state: "archived"` da fonte de design)
- Estados reais: **vazio** (as 2 copies literais da Blade), **carregando** (`<Deferred>` de
  verdade — as props vêm por `Inertia::defer`) e **erro** (prop deferida com forma inesperada)
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

> ⚠️ Os dois itens abaixo **não são inferência** — cada um cita a fonte que o sustenta, como
> manda a lápide §5 2026-08-10. Non-Goal inventado parece canon e a próxima sessão obedece.
> Non-Goal **novo** é decisão [W]; estes só registram o que a fonte e a arquitetura já dizem.

- ❌ **Não agrega, soma nem totaliza cross-business.** A agregação que o docblock antigo do
  controller prometia não existe (medido em 27/08 e re-medido em 31/08/2026: zero
  `sum`/`count`/`groupBy`). **Fonte:** o próprio protótipo escreve a razão na tela —
  *"Somar aqui na tela seria inventar total de plataforma no cliente"*
  (`jana-telas-novas.jsx` §JmPlataforma). Os contadores de cabeçalho de seção são contagem
  **do que está listado**, não total de plataforma. O que a plataforma quer medir é decisão [W].
- ❌ **Não instala, atualiza nem desinstala o módulo.** A seção "Instalação do módulo" do
  `JmPlataforma` pertence a **`/ia/install`** — outro grupo de rotas, outro controller
  (`InstallController`), e `uninstall` derruba as tabelas `jana_*`. **Fonte:** as duas rotas são
  distintas em `Modules/Jana/Http/routes.php` (o protótipo as junta porque nele tudo é aba da
  tela única). Superfície destrutiva de outra rota não entra de carona — é F1 própria.
- ❌ **Não renderiza o `<Alert tone="danger">` do protótipo sobre o gate.** Ele descreve o
  vazamento cross-tenant que o [#6421](https://github.com/wagnerra23/oimpresso.com/pull/6421)
  **fechou em 28/08**, um dia depois de a fonte ser desenhada. **Fonte:** o próprio commit do
  fix. Exibir hoje um aviso de vulnerabilidade já corrigida é a classe LC-10.

## Gate — as DUAS portas (Tier 0, ADR 0093)

⛔ **`can('jana.superadmin')` NÃO é gate nesta tela.** O `Gate::before`
(`app/Providers/AuthServiceProvider.php:34-47`) devolve `true` em qualquer ability fora de
`['backup','superadmin','manage_modules']` para quem tem `Admin#{business_id}` — ou seja, para
**todo dono de negócio**. As portas legítimas, ambas em
`SuperadminController::podeVerPlataforma`:

1. `hasPermissionTo('jana.superadmin')` — Spatie direto, sem passar pelo Gate;
2. `user_type` em `('superadmin','user_oimpresso')` — coluna, fora do alcance do Gate.

O **ghost da faixa de abas usa o mesmo predicado**: aba visível que dá 403 ao clicar seria pior
que aba ausente. Recibo da medição em produção (quem de fato alcança, com controle positivo):
[`RUNBOOK-plataforma.md` §1.1](../../../../memory/requisitos/Jana/RUNBOOK-plataforma.md).

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE). As duas tabelas rolam
  dentro do próprio wrapper (`overflow-x-auto`), nunca o `body`
- A tela renderiza **vazia em produção hoje** (`jana_metas` = 0 linhas, medido em 31/08/2026),
  então o estado vazio não é caso de borda: é o caso comum, e a copy dele é contrato

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
- Processo: ADR 0104 (MWART) · Tier 0: ADR 0093 · Teste: ADR 0358 (tenant 98 × 99)
- Trava de sinal não se aplica (trabalho dirigido por [W]): ADR 0382
