---
slug: 0362-errata-0360-admin-nao-respondia-403-o-bypass-estava-ligado
number: 362
title: "Errata à 0360 — o Admin Center não respondia 403: o bypass do Tailscale estava LIGADO em produção"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-07-30"
accepted_via: "Correção do próprio autor, medida ao escrever o `## Infra Contract` do PR de remoção (#5062) — depois do merge da 0360. Não muda decisão nenhuma: corrige uma afirmação factual falsa no corpo da 0360, que o append-only impede editar no lugar (Constituição Art. 3)."
module: infra
quarter: 2026-Q3
tags: [governanca, admin, deprecacao, seguranca, tailscale, errata, medicao]
supersedes: []
superseded_by: []
related:
  - 0360-deprecacao-admin-center-supersede-0122
  - 0122-admin-center-ct100
  - 0062-separacao-runtime-hostinger-ct100
pii: false
---

## Contexto

A [ADR 0360](0360-deprecacao-admin-center-supersede-0122.md) (aceita 2026-07-29) justifica a depreciação do Admin Center com uma tabela de evidências. A primeira linha diz:

> | Acesso | Todo `/admin/*` responde **403** fora da CIDR `100.99.0.0/16` | `TailscaleOnly::handle` |

**Isso é falso.** A coluna "Fonte" denuncia o erro: `TailscaleOnly::handle` é *código*, não *medição*. Eu li o `abort(403, 'Acesso permitido apenas via Tailscale.')` no middleware e escrevi o resultado como se tivesse observado.

O erro só apareceu no dia seguinte, ao escrever o `## Infra Contract` obrigatório do PR de remoção ([#5062](https://github.com/wagnerra23/oimpresso.com/pull/5062)) — que exige status HTTP literal e me obrigou a medir.

## O que a medição mostra

Produção, 2026-07-30, antes do merge da remoção:

```
$ curl -sv https://oimpresso.com/admin/screen-review 2>&1 | grep -E '^< HTTP|^< location'
< HTTP/1.1 302 Found
< Location: https://oimpresso.com/login

$ curl -sv https://oimpresso.com/admin/feature-flags 2>&1 | grep -E '^< HTTP|^< location'
< HTTP/1.1 302 Found
< Location: https://oimpresso.com/login

$ curl -sv https://oimpresso.com/login 2>&1 | grep -E '^< HTTP|^< location'
< HTTP/1.1 200 OK
```

`302` para `/login` é o middleware **`auth`** agindo. Na ordem declarada do grupo de rotas — `['web', 'tailscale-only', 'SetSessionData', 'auth', 'is-wagner', …]` — o `tailscale-only` vem **antes** do `auth`. Se ele tivesse abortado, a resposta seria `403` e o `auth` nunca rodaria. Logo: **o gate de IP deixou passar.**

Causa, medida no servidor:

```
.env:158  ADMIN_BYPASS_TAILSCALE=true

config resolvido em prod:
  bypass_tailscale => true
  bypass_local     => false
  env              => 'live'
```

O próprio `TailscaleOnly` previa esse caminho, e o comentário dele é explícito — *"Bypass Tailscale-only restrito (qualquer env, inclusive prod). Defense-in-depth preservado — middleware `is-wagner` continua validando user_id=1. Log WARN pra auditoria."* Eu li esse comentário no levantamento e ainda assim afirmei o 403.

## Consequência real

**O painel esteve acessível pela internet pública**, protegido por `auth` + `is-wagner` (user_id=1 AND business_id=1 AND role superadmin) — mas **sem** o gate de IP que a [ADR 0122](0122-admin-center-ct100.md) tratava como Princípio 1 e descrevia como *"internet pública zera vetor de ataque externo"*.

Não é vulnerabilidade explorável trivialmente: `is-wagner` é um check duro de identidade. Mas a postura declarada pela 0122 e repetida pela 0360 (*"zero superfície de ataque"*, *"D5 N/A por design"*) **não correspondia ao estado de produção**. A camada que justificava o `na_justified` de D5 no SPEC estava desligada por flag.

## O que esta errata NÃO muda

**A decisão de remover segue de pé, e o argumento central não depende do 403.** O que sustenta *"nunca entrou em operação"* é:

| Evidência | Medição | Quem mediu |
|---|---|---|
| `SELECT COUNT(*) FROM mcp_admin_audit_log` em prod | **0 linhas** | esta sessão **e**, independentemente, a do [#5061](https://github.com/wagnerra23/oimpresso.com/pull/5061) |
| `git ls-files '*.review.md'` | **0** no repo inteiro | git |
| Referências a `Modules\Admin\` fora do módulo | **0** | varredura por FQCN, 87 arquivos |
| `admin:ui-catalog-generate` em `php artisan list` | **ausente** | CT 100 |

Duas sessões independentes chegaram ao mesmo `0` no audit log. O 403 era ornamento retórico da tabela, não a base do raciocínio.

## O que esta errata MELHORA no entendimento

O efeito da remoção é **melhor** do que a 0360 declarou. Ela descreve o PR como remoção de código morto; na verdade ele também **elimina uma superfície administrativa exposta na internet** — 8 telas e 3 endpoints mutacionais atrás de um gate de IP desligado.

## Ação pendente (fora do git)

`ADMIN_BYPASS_TAILSCALE=true` continua no `.env` de produção, linha 158.

O [#5062](https://github.com/wagnerra23/oimpresso.com/pull/5062) **foi mergeado em 2026-07-30 12:37** — `Modules/Admin` e `resources/js/Pages/Admin` têm 0 arquivos no `main`. Com o middleware fora do repo, a flag ficou **órfã e inofensiva**: não há mais `TailscaleOnly` para lê-la, e as rotas `/admin/*` do módulo deixaram de ser registradas. A exposição descrita acima **terminou com o merge**, não com a limpeza da flag.

A remoção da linha é higiene, não correção de risco. O `.env` de produção não está no git: é ato manual do [W].

## Lição — a classe, não o deslize

Isto é [LC-08](../LICOES_CODE.md) (*afirmar/derivar/medir a partir da fonte errada*) cometido no artefato de maior autoridade do projeto: uma ADR canônica aceita. O agravante não é o erro de fato — é que a **própria tabela declarava a fonte errada** na coluna "Fonte" (`TailscaleOnly::handle`, um arquivo de código) e passou por revisão minha e por merge sem que ninguém — inclusive eu — estranhasse que uma linha sobre *comportamento em produção* fosse ancorada em *leitura de código*.

Dois corolários perenes:

1. **Linha de evidência cuja "Fonte" é um arquivo de código não é evidência de runtime.** Se a coluna Fonte aponta pra `Classe::metodo`, o que está sendo afirmado é o que o código *pretende*, não o que o sistema *faz*. Comportamento em prod se ancora em `curl`, `artisan`, `SELECT` ou log — nunca em `grep`.
2. **Flag de bypass conhecida obriga a medir o estado dela.** Quando o código tem escape valve explícito (`config('...bypass...')`), afirmar o comportamento do gate sem conferir se o bypass está ligado é afirmar metade do sistema. Ler o comentário que descreve o bypass e ainda assim afirmar o caminho bloqueado — o que eu fiz — é o pior caso.

O que pegou o erro foi um **gate exigindo formato de evidência** (`## Infra Contract`, que pede status HTTP literal), não revisão de conteúdo. Registro isso porque é o argumento mais forte a favor de gates que exigem *forma* de prova: eles alcançam o que a revisão semântica deixa passar.

## Refs

- [ADR 0360](0360-deprecacao-admin-center-supersede-0122.md) — a decisão que esta errata corrige (permanece válida)
- [ADR 0122](0122-admin-center-ct100.md) — Princípio 1 (Tailscale-only) que a flag desligava
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — separação de runtime que motivou o desenho CT 100
- [`memory/proibicoes.md`](../proibicoes.md) §"Claim sem evidência" — a regra que o `## Infra Contract` mecaniza
