---
slug: 0416-pii-redactor-arquivado-emenda-0224
number: 416
title: "Hook pii-redactor arquivado — sai do settings.json até voltar escopado à Jana"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-28"
module: governance
tags: [hooks, lgpd, pii, commit, claude-code, jana]
supersedes: []
supersedes_partially: [0224-hooks-block-vs-advisory-claude-4.8-aware]
superseded_by: []
related:
  - 0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

# ADR 0416 — Hook `pii-redactor` arquivado

> O merge do [W] é o ato (R10). Implementação no mesmo PR (#8040).

## Decisão expressa

Em 2026-09-28, [W] determinou: **"conserta a mensagem do hook pii-redactor, pode arquivar ele por
enquanto, ele vai ser usado só na JANA"**.

- O hook `.claude/hooks/pii-redactor.mjs` (PreToolUse · Bash, bloqueava `git commit` com
  CPF/CNPJ/cartão na mensagem ou no staged diff) **sai do `.claude/settings.json`** e deixa de rodar
  nas sessões do Claude Code.
- O **arquivo e o teste ficam** no repo, para a volta. Religar = devolver o wiring no
  `settings.json` e a linha do BACKSTOP em `scripts/governance/settings-backstop-registration.test.mjs`.
- Esta ADR substitui **só** a linha do `pii-redactor` na tabela da [ADR 0224](0224-hooks-block-vs-advisory-claude-4.8-aware.md)
  (*"KEEP block — LGPD"*). O resto da 0224 segue valendo.

## O que ela NÃO muda

- **O `PiiRedactor` da Jana** (serviço PHP que redige PII em log e resposta de IA) é outra peça e
  não foi tocado.
- **A regra de conteúdo continua**: `memory/proibicoes.md` §Multi-tenant — *"PII reais (CPF/CNPJ
  cliente) NUNCA em PR/commit/log"*. O que acaba é a **checagem automática** dela no commit; ela
  passa a depender de revisão humana.
- **O gate de valores em R$** (`brl-scan`) é outro mecanismo e segue ligado.

## Consequências

- Não há mais bloqueio automático de PII em commit feito pelo agente. Os documentos que afirmavam o
  contrário foram corrigidos no mesmo PR (`lgpd-mapa-tratamento.md`, `regras-time.md`,
  `AUTOMATIONS.md`).
- O cenário A6 do corpus de red-team (`prompt-injection-corpus.mjs`) continua verde, porque invoca
  o arquivo direto; ele passa a provar só a **lógica** do hook, não a defesa em uso. Está anotado
  no próprio cenário.
- A mensagem do hook foi consertada antes do arquivamento: ela mandava passar `--allow-pii` como
  opção do `git commit`, que o git recusa (rc=129). A forma válida é a marca `--allow-pii <motivo>`
  dentro da mensagem do commit, e o teste prova isso num git de verdade.

## Em aberto (para quando voltar)

- **Escopo "só na Jana"** não foi detalhado. A forma provável é o hook inspecionar só commits que
  tocam `Modules/Jana/**` (e o que a Jana lê), mas isso é decisão [W] na volta, não desta ADR.
- **Furo conhecido**: `isGitCommit` exige o comando começando por `git commit`; qualquer prefixo
  (`cd x && git commit …`) escapa. Corrigir antes de religar.
