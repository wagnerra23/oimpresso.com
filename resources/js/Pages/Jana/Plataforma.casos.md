---
id: resources-js-pages-jana-plataforma-casos
casos: Jana Plataforma · metas cross-business cruas · gate real jana.superadmin · aba da área · /ia/superadmin/metas
irmaos: Plataforma.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-plataforma.md (runbook) · prototipo-ui/contrato/jana-plataforma.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-02"
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

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** Agregação cross-business — o que a plataforma quer medir é decisão [W].

## Trilha do tempo
- 2026-09-02 · trio nascido junto (charter + casos + Pest + e2e stub + contrato). Refs: UI-0013 · ADR 0264 G-1/G-2.
