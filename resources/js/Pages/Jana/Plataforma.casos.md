---
id: resources-js-pages-jana-plataforma-casos
casos: Jana Plataforma · metas cross-business cruas · gate real jana.superadmin · aba da área · /ia/superadmin/metas
irmaos: Plataforma.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-plataforma.md (runbook) · prototipo-ui/contrato/jana-plataforma.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-03"
---

# Casos de uso — /ia/superadmin/metas (aba Plataforma da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Plataforma.charter.md`, do `jana-plataforma.contract.json` e da âncora
> (`node prototipo-ui/ancora.mjs Jana/Plataforma` → `jana-telas-novas.jsx` §`JmPlataforma`) — **não**
> do `Plataforma.tsx`. Os dados da âncora (Martinho, ROTA LIVRE, Gráfica Sul) são mock; a forma vale.

## UC-PLAT-00 — A aba e a rota só existem para quem tem `jana.superadmin` DE VERDADE
Status: 🧪 (`PlataformaContratoTest` — aguarda run verde na lane MySQL)

**Aceite:** Dado usuário com `jana.access` mas **sem** `jana.superadmin` atribuída (e sem `user_type`
superadmin) · Quando abre `/ia/superadmin/metas` · Então **403**, e o `shell.menu` da Jana **não** tem
o ghost `plataforma`. Dado o mesmo usuário **com** a permissão atribuída no Spatie · Então 200 com
`Jana/Plataforma` e o ghost `{key: plataforma, label: Plataforma}` na **6ª** posição.

**Regressão que defende:** o link aparecer para dono de empresa (que tomava 403) — o gate do menu
divergindo do gate da rota; e o P0 #6421 voltar (`can()` bypassado pelo `Gate::before`).

## UC-PLAT-01 — As duas listas vêm cruas, cross-business, com o formato da tela
Status: 🧪 (`PlataformaContratoTest`)

**Aceite:** com a permissão · `metasPlataforma` só tem `business_id NULL`; `metasDeClientes` traz
metas de **outros** businesses (é o caso legítimo do ADR 0093), cada uma com `business_id`,
`empresa`, `nome`, `unidade`, `periodo` (`data_ini`/`data_fim` ou `null`) e `ultima` (ou `null`);
`instalacao` traz `migrations`/`seeders`/`permissoes` **iguais ao disco/registry** e `podeOperar`
igual a `can('superadmin')`.

**Regressão que defende:** agregação inventada no payload; contagem digitada no bloco de instalação.

## UC-PLAT-02 — Copy e ordem do contrato estão na tela
Status: 🧪 (`PlataformaContratoTest` — asserção de ARQUIVO sobre o `alvo`)

**Aceite:** toda seção do `jana-plataforma.contract.json` tem âncora `data-contract` no alvo, toda
copy pinada está presente, e a ordem respeita `ordem`. E o **negativo**: a frase da âncora
*"Gate desta tela não separa dono de empresa de superadmin"* **não** está no alvo (caducou no #6421).

## UC-PLAT-03 — Meta de outro tenant chega com período atual e última apuração preenchidos
Status: 🧪 (`PlataformaContratoTest` — grupo `tier0`)

**Aceite:** com a permissão · Dada meta de **outro** business com um `MetaPeriodo` vigente e uma
`MetaApuracao` · Quando abre `/ia/superadmin/metas` · Então a linha dela em `metasDeClientes`
traz `periodo = {data_ini, data_fim}` e `ultima = Y-m-d` — **não** `null`.

**Regressão que defende:** o bug de produção de 2026-09-03 — `MetaPeriodo`/`MetaApuracao` usam
`BelongsToBusinessViaParent`, e o eager load (`->with(...)`, mesmo com `withoutGlobalScopes()` na
closure, porque `latestOfMany` reinstancia o model) filtrava as filhas pela sessão: a tela dizia
"—" e "nunca apurada" para **todos** os clientes. UC-PLAT-01 não pega: lá a meta alheia não tem
filhas e `null` é o esperado. Fonte: ADR 0093 (o escopo sai por desenho; o que se defende é o QUEM).

## UC-PLAT-04 — O payload não agrega
Status: 🧪 (`PlataformaContratoTest`)

**Aceite:** com a permissão · o `props` da página **não** tem chave `totais`/`total`/`agregado`/
`resumo`/`kpis`. A agregação cross-business é Non-Goal do charter e decisão [W] (Backlog abaixo).

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** Agregação cross-business — o que a plataforma quer medir é decisão [W].

## Trilha do tempo
- 2026-09-03 · UC-PLAT-03 (filhas fora do escopo — bug de produção achado na lane MySQL) + UC-PLAT-04 (sem agregação), vindos do #6627 sobre o trio do #6609.
- 2026-09-02 · trio nascido junto (charter + casos + Pest + e2e stub + contrato). Refs: UI-0013 · ADR 0264 G-1/G-2.
