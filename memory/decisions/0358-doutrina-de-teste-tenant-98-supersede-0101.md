---
slug: 0358-doutrina-de-teste-tenant-98-supersede-0101
number: 358
title: "Doutrina de teste do sistema — tenant canônico biz=98 (fictício); supersede a 0101-tests e a esquece fisicamente"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-29"
accepted_via: "[W] 2026-07-29, no chat: 'lapide não funcionou vai precisar remover fisicamente resolva isso definitivamente. não confio no indice ele falha' + 'faça e confira' + 'merge'. Materializa também a decisão de tenant comunicada por [M] em 2026-07-28 ('o teste inteiro no 99 porque o 1 está sendo usado pela WR sistema'), corrigida para 98 pela medição do conflito de papéis."
module: governance
quarter: 2026-Q3
tags: [tests, multi-tenant, governanca, tier-0, pest, ci, ct100, esquecimento, tombstone]
supersedes: [0101-tests-business-id-1-nunca-cliente]
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0347-deadlink-gate-required-emenda-0314
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0062-separacao-runtime-hostinger-ct100
pii: false
---

# ADR 0358 — Doutrina de teste do sistema (tenant canônico biz=98)

> Supersede **`0101-tests-business-id-1-nunca-cliente`**, que é **esquecida fisicamente** neste
> mesmo PR (`git rm` + lápide em [`governance/adr-tombstones.json`](../../governance/adr-tombstones.json), [ADR 0316](0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md)).
> Não é supersessão só de etiqueta: o arquivo **sai do disco**.

## Contexto — a canon estava dizendo o contrário do código

A `0101-tests` (aceita 2026-05-07) fixou `business_id = 1` como tenant default de teste, com a
regra correta *"nunca biz=4"* (ROTA LIVRE, cliente real). Ela tinha um ponto cego: **biz=1
também é empresa real** — a WR2 Sistemas, em operação.

Enquanto teste rodava só em CI isso era inofensivo — cada lane cria um MySQL descartável. Deixou
de ser quando o CT 100 entrou no fluxo: lá a base é **clone de produção e não se limpa entre
execuções** ([proibicoes.md §Ambiente](../proibicoes.md)). Teste em biz=1 passou a semear dado
dentro do espelho da empresa de verdade.

O código foi corrigido em 2026-07-28 ([#4974](https://github.com/wagnerra23/oimpresso.com/pull/4974)):
`SEEDED_TENANT_ID = 98` em [`tests/Support/WithSeededTenant.php`](../../tests/Support/WithSeededTenant.php),
com o seed do CI ([`pest-mysql-setup`](../../.github/actions/pest-mysql-setup/action.yml)) e o do
CT 100 ([`ct100-fullsuite.sh`](../../scripts/tests/ct100-fullsuite.sh)) criando o 98. **A ADR nunca
foi atualizada** — a decisão viveu 1 dia só em código, com a canon afirmando o oposto.

### Por que 98 e não 99

A proposta original escolheu 99 e **errou por medir a fonte errada**: procurou o nome da constante
`SUPPORT_CLIENT_TENANT_ID` em vez do helper `seededSupportClientTenant()`, que tem ~33 call-sites
em 6 arquivos da suíte do Modo Suporte. Apontar tenant principal e cliente-do-suporte pro mesmo
id faria agente e cliente virarem a **mesma empresa** — a suíte ficaria verde sem provar
isolamento nenhum. Os dois papéis exigem ids distintos por construção. O 98 está livre (prod tem
82 businesses, nenhum entre 95 e 105, medido 2026-07-28).

## Decisão — os cinco papéis, cada id com um dono

| id | Papel | Regra |
|---|---|---|
| **98** | **Tenant canônico de teste** — empresa FICTÍCIA não-operadora | Default de todo teste/fixture. Resolvido por `seededTenant()`, nunca hardcode novo |
| **99** | **A outra empresa fictícia** | Adversário de isolamento cross-tenant **e** cliente do Modo Suporte (`SUPPORT_CLIENT_TENANT_ID`) |
| **2** | Segundo tenant mínimo do seed | Paridade histórica + par cross-tenant nas lanes de CI |
| **1** | **WR2 Sistemas — empresa REAL** | **Deixa de ser default de teste.** Permanece no seed por paridade. Único caso vivo: smoke fiscal manual (§Carve-out) |
| **4** | ROTA LIVRE — cliente real | **Proibido**, sem exceção, em teste, fixture, smoke ou exemplo |

**O que a 0101 acertou e continua valendo, sem mudança:** a proibição do biz=4 e o **99 como
adversário cross-tenant** (cláusula 3). Isso não é retórica — é o que o código faz: **91 arquivos
de teste** usam `'business_id' => 99` (173 ocorrências), contra 14 que usam o 2. A supersessão
atinge **só** as cláusulas 1 e 2 (o default `= 1`).

## Carve-out — smoke fiscal em homologação (absorve a proposta de 2026-06-24)

A distinção que faltava é entre **teste automatizado** e **smoke fiscal manual**:

- **Teste automatizado** (Pest, CI, CT 100): tenant **98**, sempre. Não fala com a SEFAZ.
- **Smoke fiscal manual contra SEFAZ homologação**: **biz=1** (certificado do próprio [W]) é a
  preferência. É permitido usar o `business_id` de um cliente real **exclusivamente em
  homologação** e **exclusivamente para ler a configuração fiscal dele** (regime, tributação
  default, CFOP/CSOSN/CST, CSC de homologação), quando o objetivo for validar emissão contra o
  setup real do cliente.
- **Emissão contra SEFAZ produção**: só biz=1. Sem exceção.

Origem: [W] 2026-06-24, textual — *"teste de nota pode usar meu certificado empresa 1, em
homologação. Ok se for em homologação pode fazer no cliente tbm para pegar as configurações do
cliente. preferencia no meu."* A proposta ficou 35 dias sem ratificação; entra aqui.

Se o carve-out virar teste Pest tocando `business_id` de cliente, ele carrega marcador explícito
(`// homologacao-only — ADR 0358`) e o guard ganha allowance **estreita** só pro caso anotado.

## Onde o teste roda (reafirmação, não decisão nova)

Não havia ADR sobre isto — a regra vivia só em [`proibicoes.md`](../proibicoes.md). Fica registrada:
Pest e análise estática rodam no **CT 100**, nunca na máquina local nem no Hostinger
([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)); e **verde no CT 100 não substitui o gate
de merge** — o CI é que decide, porque lá cada lane tem DB fresco e aqui a base persiste.

## A dívida que esta ADR NÃO finge ter resolvido

O tenant virou 98 no helper, não na suíte inteira. Medido em `origin/main` (2026-07-29):

| Âncora | Arquivos | Resolve em |
|---|---|---|
| `seededTenant()` | 54 | **98** ✅ |
| `Business::first()` | 65 | menor id presente (**1**) ⚠️ |
| `'business_id' => 1` hardcoded | 270 | **1** ⚠️ |

Inclui [`tests/Contract/AutosaveContractRunner.php:200`](../../tests/Contract/AutosaveContractRunner.php)
— o runner canônico dos contract tests ([ADR 0205](0205-contract-tests-autosave-padrao-canonico.md))
ancora em `Business::first()`, ou seja grava no espelho da WR2 quando roda no CT 100. É exatamente
o risco que motivou o 98.

**Não é big-bang.** Migrar 335 arquivos de uma vez acorda os gates diff-aware que hoje os
grandfathered ([§5 2026-07-12](../proibicoes.md)). O caminho é **forward-only + oportunístico**:
arquivo migra quando trabalho real já o tocar.

**A máquina que falta (follow-up nomeado, não esquecido):** o
[`tests/Unit/BusinessIdGuardTest.php`](../../tests/Unit/BusinessIdGuardTest.php) tem 7 regex,
**todas mirando o 4** — nada impede biz=1 novo entrar hoje. O follow-up é uma catraca com baseline
grandfathering os 270 (idioma dos `*.mjs` de governança: só-desce, novo/piorado reprova). Fica
fora deste PR de propósito: mexe num check **required** (`Tier-0 guards`), e gate novo exige FP
medido antes ([ADR 0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)).

## Consequências

- ✅ Canon e código voltam a dizer a mesma coisa — o intervalo foi de 1 dia, e só porque foi pego.
- ✅ A `0101-tests` **sai do disco**: some de `ls`, `grep`, `Glob`, do índice gerado e do recall.
- ✅ A **colisão de número 0101** (duas ADRs com o mesmo número, [ADR 0274](0274-referencia-adr-por-slug-alias-map-13-colisoes.md))
  deixa de existir — resolvida fisicamente, não remendada por alias-map. Sobra a
  `0101-sistema-charter-capterra-governanca-escopo`, e "ADR 0101" volta a ser referência
  inequívoca.
- ✅ Duas propostas paradas (tenant 2026-07-28, homologação 2026-06-24) saem do limbo.
- ⚠️ As ~177 referências à ADR esquecida resolvem pelo ledger + git history, não por link
  clicável. É o trade-off que a [0316](0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md)
  aceitou explicitamente. **Não** foram reescritas em massa: 103 dos arquivos afetados são
  SPEC/BRIEFING/charter legados, e tocá-los acorda gate que os grandfathered.
- ⚠️ O tenant da suíte inteira ainda não é 98 (tabela acima). Honesto e registrado.

## Reversão

Constante de volta a 1 em `WithSeededTenant` (o seed do 98 pode ficar, é inerte). A ADR esquecida
volta com `git show <last_sha>:<path>` — o `last_sha` está na lápide.

## Refs

- Lápide: [`governance/adr-tombstones.json`](../../governance/adr-tombstones.json) — entrada 0101
- [ADR 0316](0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md) — esquecimento real (o mecanismo)
- [ADR 0347](0347-deadlink-gate-required-emenda-0314.md) — deadlink-gate required (a lei que travava o esquecimento até o PR #5027)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL
- [`tests/Support/WithSeededTenant.php`](../../tests/Support/WithSeededTenant.php) — valor canônico vive aqui, não na prosa
