---
slug: 0381-jana-module-chave-de-assinatura-subdecisao-0088
number: 381
title: "Sub-decisão da 0088 — a chave de assinatura do módulo Jana passa a ser `jana_module`"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-26"
module: jana
tags: [jana, copiloto, assinatura, package, superadmin, fachada, rename, visibilidade]
supersedes: []
superseded_by: []
related:
  - 0088-module-rename-php-only
  - 0093-multi-tenant-isolation-tier-0
pii: false
---

> **Sub-decisão da [ADR 0088]**, que a prevê nominalmente: *"Reabrir 0088 quando Wagner
> decidir mover qualquer dimensão da fachada. Cada movimento vira ADR sub-decisão."*
> Decisão [W] no chat em 2026-08-26, textual: **"copiloto_module é erro"**.
> **Ratificação = merge [W].** Se não mergear, o gate segue lendo a chave antiga.
> (`kind: decision` porque o schema não tem `subdecisao`; a relação com a 0088 está em
> `related` e no corpo.)

# ADR 0381 — a chave de assinatura do módulo Jana é `jana_module`

## Contexto

A [ADR 0088] renomeou o módulo `Copiloto` → `Jana` **só no PHP** e preservou uma fachada
legacy user-visible, listando **8 dimensões** que não mudariam: URLs · permissions Spatie ·
config/env · log channel · Pages React · lang namespace · tabelas DB · route names.

**A chave de assinatura (`<x>_module` no `package_details`) NÃO está nessa tabela.** Quem a
chamou de "fachada 0088" foi o [session log de 2026-05-06](../sessions/2026-05-06-pr-9-tabela-rename-copiloto-jana.md) —
interpretação posterior, não texto da ADR. Esta sub-decisão registra isso: ela não está
*movendo uma dimensão prevista*, está **acrescentando uma que a 0088 não enxergou**.

O sinal de que era sobra de migração parcial, e não decisão, está no mesmo arquivo:

```
Modules/Jana/Http/Controllers/DataController.php:45   'name'  => 'copiloto_module'   ← legado
Modules/Jana/Http/Controllers/DataController.php:62   'value' => 'jana.access'       ← já migrado
```

A dimensão de **permissions Spatie** — que a tabela da 0088 mandava manter em `copiloto.*` —
**já andou** para `jana.*`. A chave de módulo ficou para trás sozinha.

### O custo que isso cobrou

O módulo Jana estava **em produção e invisível**: código deployado, 43 rotas registradas,
`/ia` respondendo, e nenhum item no menu. O gate lia `copiloto_module`, ausente em **12 de
12** assinaturas ativas; o que estava marcado no `biz=1` era `jana_module`, que o código não
lia. Cada tentativa de "marcar o checkbox" escrevia numa chave morta.

## Decisão

**A chave de assinatura do módulo Jana é `jana_module`.** O gate, a declaração no painel de
pacotes e o atalho do sidebar passam a lê-la. `copiloto_module` deixa de ser consultado por
qualquer caminho de código.

### O que muda

| Ponto | Antes | Depois |
|---|---|---|
| declaração em `/superadmin/packages/{id}/edit` | `copiloto_module` | `jana_module` |
| gate do menu (`DataController::modifyAdminMenu`) | `copiloto_module` | `jana_module` |
| atalho do sidebar (`HandleInertiaRequests::sidebarShortcuts`) | `copiloto_module` | `jana_module` |
| rótulo da caixa (`pt/copiloto.php`) | `Copiloto` | `Jana` |

### O que NÃO muda

As 8 dimensões da tabela da 0088 seguem intactas — inclusive o **lang namespace**
`copiloto::` e o **nome do arquivo** `Resources/lang/pt/copiloto.php`. Só o *valor* do
rótulo mudou, não a chave de tradução.

## Back-compat — medido antes, não presumido

Produção, 2026-08-26, leitura via SSH:

```
PACOTES (75):        com copiloto_module: 1 (pkg 11)  ·  com jana_module: 2 (pkg 1, 11)
ASSINATURAS ATIVAS:  com copiloto_module: 0           ·  com jana_module: 1 (sub=118, biz=1)
```

**Zero assinatura ativa concedia acesso pela chave antiga.** Ninguém perde o que não tinha.

E o rótulo:

```
users.language:  pt 129 · en 1        biz=1 pt 14 · biz=4 pt 11
config app.locale = pt
```

A hipótese de que o [W] estivesse vendo a **chave crua** por falta de locale foi **refutada**:
com `locale = pt`, ele via "Copiloto" mesmo.

## Consequências

**Boa, e imediata.** O `sub=118` (biz=1) tem `jana_module="1"` e passou a ser a assinatura
que o gate lê depois do [PR #6290](https://github.com/wagnerra23/oimpresso.com/pull/6290)
(`active_subscription()` deixou de ser não-determinística). Encadeado: o #6290 fez a
assinatura de 2025 vencer a zumbi de 2021, e esta troca faz o `jana_module` dela contar.
**O `biz=1` passa a enxergar a Jana sem ninguém clicar em nada.**

⚠️ **Não cobre o cliente.** O `biz=4` (ROTA LIVRE) não tem **nenhuma** das duas chaves — nem
a antiga nem a nova. Para ele e para os outros 8 businesses, marcar a chave no pacote com
*"Atualizar inscrições existentes"* segue necessário, e segue sendo ato de UI
([`proibicoes.md`](../proibicoes.md) é Tier 0 nisso: habilitar módulo por business nunca é
deploy).

⚠️ **Ao clicar, conferir a perda.** O botão reconstrói o `package_details` **do zero, sem
merge** (`PackagesController.php:271`): chave que a assinatura tem e o pacote não tem é
apagada. Medido no mesmo dia: clicar no **pacote 11** removeria `nfebrasil_module` do
`biz=164` (Martinho, piloto LIVE), porque o pacote carrega `nfe_brasil_module` e o código lê
`nfebrasil_module`. Isso é sintoma da mesma doença desta ADR, num outro módulo, e **fica
aberto** — não é escopo desta sub-decisão.

**Neutra.** Ninguém consumia a chave antiga; não há período de convivência a manter.

## Alternativas descartadas

- **Ler as duas chaves (`copiloto_module || jana_module`)** — seria back-compat para um
  conjunto vazio (0 assinaturas ativas com a antiga), ao custo de perpetuar o dialeto duplo
  que é exatamente o defeito. Descartada por medição, não por gosto.
- **Manter `copiloto_module` e mandar marcar essa** — é o que estava valendo, e é o que
  produziu meses de "marquei e não pegou". O [W] cortou.
- **Renomear a fachada inteira junto** (namespace, arquivo de lang, URLs) — blast radius que
  a 0088 já pesou e recusou. Cada dimensão continua sendo PR isolado.

## Enforcement

[`Modules/Jana/Tests/Feature/JanaModuleChaveCanonicaTest.php`](../../Modules/Jana/Tests/Feature/JanaModuleChaveCanonicaTest.php) — 3 casos:
o 1º é comportamental (chama `superadmin_package()`); os outros 2 verificam que os **dois
consumidores concordam** sobre a chave, com controle anti-verde-vazio. O docblock declara
que são de fonte e por quê, em vez de disfarçar.

## Quando reabrir

Se alguma outra dimensão da fachada da 0088 se mostrar sobra e não decisão — o candidato
visível hoje é a URL, que a tabela diz `/copiloto/*` mas que na prática já é `/ia` com 301.
Cada uma vira sub-decisão própria.
