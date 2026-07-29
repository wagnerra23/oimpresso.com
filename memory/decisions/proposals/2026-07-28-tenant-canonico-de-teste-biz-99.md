---
id: proposal-tenant-canonico-teste-biz-99
title: "Tenant canônico de teste passa de biz=1 para biz=99 (empresa fictícia)"
type: adr-proposal
status: proposto
owner: wagner
supersedes_candidate: ["0101-tests-business-id-1-nunca-cliente"]
last_updated: "2026-07-28"
---

# Tenant canônico de teste: biz=1 → biz=99

> ## ⚠️ ERRATA 2026-07-29 — o id passou a ser **98**, não 99
>
> Esta proposta escolheu o 99 apoiada numa premissa que **não se sustentou na medição**.
> Ela afirma abaixo que o 99 tinha *"zero consumidores, zero materialização"*. Falso:
> `seededSupportClientTenant()` — o helper que embrulha `SUPPORT_CLIENT_TENANT_ID` — tem
> **~33 call-sites em 6 arquivos** da suíte do Modo Suporte (`SuporteConcederCommandTest`,
> `SupportAccessHttpTest`, `SupportAccessLogTest`, `SupportAccessServiceTest`,
> `SupportAcessarComoTest`, `SupportClientViewServiceTest`, `SupportEmpresasHttpTest`), e
> materializa o 99 sob demanda desde [#3563](https://github.com/wagnerra23/oimpresso.com/pull/3563).
> A varredura original procurou o **nome da constante** e não o **helper** — classe LC-08
> (medir a fonte errada e concluir).
>
> **Consequência real:** com `SEEDED_TENANT_ID = SUPPORT_CLIENT_TENANT_ID = 99`, o tenant do
> **agente** e o da **empresa-cliente** viravam a mesma empresa, e a suíte de Modo Suporte
> passaria a ficar verde **sem provar isolamento cross-tenant** — verde tautológico. Pior:
> essa suíte não está em nenhuma das lanes que reprovaram, então **o CI não desmentiria**.
>
> **Correção aplicada:** tenant principal = **biz=98**; `SUPPORT_CLIENT_TENANT_ID` continua
> **99**. Os dois papéis exigem ids distintos por construção. O 98 está livre — prod tem 82
> businesses e nenhum entre 95 e 105 (medido 2026-07-28).
>
> Onde o texto abaixo disser "99" como **tenant principal**, leia **98**. Onde disser 99 como
> **cliente do Modo Suporte**, continua 99. O nome do arquivo preserva o slug original de
> propósito (evitar link morto); o valor canônico vive em
> [`tests/Support/WithSeededTenant.php`](../../../tests/Support/WithSeededTenant.php), não aqui.

> **Decisão de [W]**, comunicada por [M] em 2026-07-28: *"o teste inteiro no 99 porque o 1 está
> sendo usado pela WR sistema"*. Esta proposta materializa a decisão e pede ratificação formal,
> porque ela toca uma ADR aceita (0101) e o append-only proíbe editá-la no lugar.

## Contexto — o que está errado hoje

A [ADR 0101](../0101-tests-business-id-1-nunca-cliente.md) fixou **biz=1** como tenant de teste,
com a regra correta *"nunca biz=4"* (ROTA LIVRE, cliente real). O ponto cego: **biz=1 também é
empresa real** — a WR2 Sistemas, em operação.

Enquanto teste rodava só em CI isso era inofensivo: cada lane cria um MySQL **descartável**. Deixou
de ser quando o CT 100 entrou no fluxo. A base de lá é **clone de produção e NÃO se limpa entre
execuções** (registrado em `proibicoes.md` §Ambiente, 2026-07-28). Logo, teste rodando em biz=1
**semeia dado dentro do espelho da empresa real** — produtos `ESTFIX-*`, locais, unidades.

## O achado que sustenta a proposta

**O 99 já era canon e nunca existiu.** `tests/Support/WithSeededTenant.php` declara há tempo:

```php
/** id do CLIENTE fictício de teste (ADR 0101) — biz=99 (empresa NÃO-operadora), NUNCA biz=4. */
public const SUPPORT_CLIENT_TENANT_ID = 99;
```

Medido em 2026-07-28: **zero consumidores** da constante (`grep` no repo inteiro) e **zero seeds**
que criem o 99 — nem `.github/actions/pest-mysql-setup`, nem `scripts/tests/ct100-fullsuite.sh`,
nem produção (82 businesses, nenhum entre 95 e 105; `MAX(id)=226`). Regra escrita que nunca virou
máquina — exatamente o padrão que a [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)
diz que apodrece.

## Decisão proposta

1. **O seed passa a criar biz=99** (empresa fictícia, não-operadora) — CI e CT 100, mesma receita.
2. **`SEEDED_TENANT_ID` = 99**: o tenant principal dos testes deixa de ser a WR2.
3. **biz=1 e biz=2 continuam existindo** no seed — são o par cross-tenant e a paridade histórica.
4. **Fallback preservado**: sem o 99 no banco, `resolveSeededTenant()` continua caindo no primeiro
   business — quem monta o próprio schema não quebra.

## Raio de alcance (medido)

**54 arquivos de teste** usam `seededTenant()`. Todos passam a rodar no 99 sem mudança própria —
a virada é na constante e no seed, não caso a caso. Os fixtures criam local/unidade/esquema por
business, então nascem no 99 junto.

## O que esta proposta NÃO faz

- Não mexe em `biz=4` — segue proibido em teste e smoke, sem exceção.
- Não apaga dado nenhum do biz=1 já semeado no CT 100 por execuções passadas. Limpeza, se for
  desejada, é trabalho separado e com aprovação própria (é base clone de prod).
- Não altera o texto da ADR 0101 (append-only). Se ratificada, nasce ADR nova com `supersedes`.

## Risco e reversão

Risco concentrado no CI: 54 arquivos mudam de tenant de uma vez. O sinal é a própria suíte — se
algum teste dependia implicitamente do id 1, ele fica vermelho e aponta o lugar. Reversão é a
constante de volta a 1 (o seed do 99 pode ficar, é inerte).

## Pendência de ratificação

[W] decide. Merge deste PR = ratificação; então nasce a ADR com `supersedes: [0101]`.
