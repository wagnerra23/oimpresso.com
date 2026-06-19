---
date: "2026-06-13"
hour: "17:00 BRT"
topic: "pii-redactor.ps1 (hook PreToolUse) passa pra opção B — escaneia só git commit (mensagem + staged diff) e libera debug de ERP por CPF/CNPJ. Reduz escopo do enforcement do PII redactor estabelecido na ADR 0085."
authors: [W, C]
outcomes:
  - "Hook .claude/hooks/pii-redactor.ps1 deixa de bloquear comandos Bash arbitrários com CPF/CNPJ"
  - "Proteção de commit mantida (mensagem do commit + staged diff)"
  - "Bypass --allow-pii preservado; testes 13/13; CI PII-scan verde via # pii-allowlist nas fixtures"
  - "Branch feat/governance-ds-rollout-ledger confirmada como gêmea stale do main (109 commits atrás, trabalho já landado por PRs individuais)"
prs: [2683]
related_adrs:
  - "0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor"
  - "0061-conhecimento-canonico-git-mcp-zero-automem"
---

# pii-redactor → opção B (commit-only)

## Contexto

Wagner perguntou se o `pii-redactor.ps1` "está removendo informações do ERP" e se,
pro uso no ERP, ele faz "mais mal que bem".

Diagnóstico: o `pii-redactor.ps1` **não toca no ERP** — apesar do nome, nunca redige
nem apaga nada. É um hook `PreToolUse` (matcher `Bash`) que, na máquina do dev, podia
apenas **bloquear** (`decision: deny`) um comando antes de ele rodar. Origem: US-COPI-086,
LGPD Art. 7 (ver `related_adrs` 0085 — lista o redactor como `review_trigger`).

O problema real: a versão anterior bloqueava **qualquer** comando Bash com CPF/CNPJ/cartão
no texto — **sem bypass**. Num ERP brasileiro, isso quebrava operação legítima do dia a dia:
`mysql ... WHERE cpf=...`, `grep <CNPJ> log`, `ssh` em produção, snapshot Firebird
(skill `officeimpresso-financial-snapshot`). Fazia mais mal que bem.

## Decisão — opção B

O hook passa a inspecionar **somente `git commit`**:

- escaneia a **mensagem do commit** (texto do comando) **+** o **staged diff** — as duas coisas
  que vão pro histórico git e sincronizam pro MCP visível ao time;
- comandos **não-commit** (`mysql`/`grep`/`ssh`/`cat`/`echo`) passam direto, sem inspeção;
- **bypass `--allow-pii`** mantido (fixtures/justificado);
- regex de `cartão` inalterada (ampla, 16 dígitos sem Luhn) — mas agora só incomoda em commit,
  com escape disponível.

Racional: o que efetivamente vaza pro git/MCP do time são **commits** — e essa proteção
ficou intacta. Comandos não-commit rodam localmente; a saída vai pro transcript do Claude Code,
nunca pro git nem pro MCP. Logo, afrouxar não cria nova rota de vazamento.

## Verificação

- `test-pii-redactor.ps1` reescrito: **13/13 verdes** (não-commit passam; commit com PII real
  na mensagem bloqueia; fixtures fake passam; `--allow-pii` passa; `mysql WHERE cpf` passa).
- CI `PII scan (CPF/CNPJ literal)` (governance-gate) exigiu `# pii-allowlist` nas linhas de
  fixture do teste — o scanner faz grep do arquivo inteiro e só ignora linhas com esse marcador.
  Aplicado; scan verde.
- PR [#2683] — 24/24 checks verdes, squash merge `1dc97fef6` no `main`.

## Nota de processo — branch stale

`feat/governance-ds-rollout-ledger` estava **109 commits atrás** do main e praticamente todo o
seu trabalho "único" já tinha sido landado por PRs individuais (DS Rollout #2621, EVAL-001 #2478,
tokens oklch #2639/#2651, cliente server-side #2622–#2625, tela-linda #2655, cliente lupa/Auditoria
#2685). Tentar mergear a branch inteira (PR #2682, fechado como superado) regrediria o main. O único
delta real era o fix do hook — landado isolado via #2683. **Lição:** quando sessões paralelas commitam
na mesma branch e também abrem PRs individuais pro main, a branch vira gêmea stale; usar `git cherry`
(patch-id) antes de mergear pra detectar o que já está no main.

## Princípio reafirmado por Wagner

Toda **decisão canônica** deve ir pro `memory/` + push (sincroniza pro MCP via webhook GitHub→MCP),
não só o código no repo. Wagner: *"sim eu quero claro, nunca diferente."* Este session log é a
aplicação dessa regra — o código já estava no main; faltava o registro da decisão no índice de
conhecimento do MCP. Ver `related_adrs` 0061 (conhecimento canônico git+MCP, zero auto-mem).
