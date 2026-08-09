---
date: "2026-08-09"
time: "00:42 UTC"
slug: flip-required-always-run-e-o-derivado-que-o-runbook-nao-via
tldr: "Continuação da sessão de 2026-08-08. [W] disse 'flip' e o gate promovido a required foi o Required always-run — o único dos 3 candidatos que já era always-run; promover o IT8 como está deadlockaria o repo. O flip expôs um buraco na própria receita: o protection-drift ficou verde e havia drift em outro arquivo, porque índices derivados do baseline não estão no escopo dele."
prs: [5467, 5470, 5471]
decided_by: [W]
next_steps:
  - "IT8 (handoff-integrity) segue advisory: promovê-lo exige remover o paths: do pull_request antes — decisão [W]"
  - "Reversão do flip, se desejada: gh api -X DELETE .../required_status_checks/contexts com o mesmo payload (41→40)"
---

# Flip de `Required always-run` — e o derivado que o RUNBOOK não enxergava

> **Continuação** do [handoff das 22:58](2026-08-08-2258-it8-divida-paga-e-lint-de-yaml-de-workflow.md), que deixou o §12 passo 3 como única pendência. [W] respondeu **"flip"**.

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**.
- `my-work`: **5** tasks em REVIEW — nenhuma é deste trabalho.
- Protection viva: **41 contexts**, `enforce_admins=true`, `protection-drift` **🟢**.

## O que aconteceu

**O alvo não era óbvio, e a medição decidiu.** Havia 3 candidatos advisory. O `handoff-integrity` (o gate do §12 passo 3) **tem `paths:` no `pull_request`** — promovê-lo assim deadlockaria todo PR que não tocasse `COWORK_NOTES`, repetindo o incidente de 2026-08-05. O `governance script tests` é always-run mas tem ~166 steps (virar required é mudança de política). Sobrou o **`Required always-run`**: único já always-run, e é justamente quem impede **estaticamente** a classe de deadlock — além de carregar, desde ontem, o lint do `name:` que teria barrado o #5424.

**Segurança verificada antes:** dos 7 PRs abertos, 4 já reportavam o context; os 3 sem ele já estavam **CONFLICTING/DIRTY**. Nenhum PR saiu de *"pode mergear"* para *"nunca mergeia"* — a diferença exata pro #5318.

**A ordem importava, e eu errei primeiro.** Comecei editando o baseline; o `protection-drift` foi a **🔴 rc=1** (*"required SUMIU do vivo"*). Reverti e refiz certo: **vivo primeiro** (`required NOVO no vivo` = 🟡 aviso), **baseline depois**.

**O buraco que o flip revelou.** Segui a receita à risca — snapshot → PUT aditivo → `protection-drift` **🟢** — e mesmo assim havia drift, **em outro arquivo**: o `governance script tests` saiu vermelho no próprio PR do flip. O `protection-drift` compara **baseline ↔ vivo**; ele não sabe que outros artefatos **derivam** do baseline.

## Artefatos gerados

| PR | merge | conteúdo |
|---|---|---|
| [#5467](https://github.com/wagnerra23/oimpresso.com/pull/5467) | 00:01 | baseline reconciliado (40→41) + entrada em `_meta.promocoes` com o desvio da DR-2 declarado |
| [#5470](https://github.com/wagnerra23/oimpresso.com/pull/5470) | 00:21 | `_HOOKS-INDEX.md` regenerado (3 linhas derivadas) |
| [#5471](https://github.com/wagnerra23/oimpresso.com/pull/5471) | 00:40 | RUNBOOK ganha o passo dos índices derivados + a armadilha de controle |

## Persistência

- **git**: os 3 mergeados; verificado **no vivo** (`gh api`), não no worktree.
- **branch protection**: mudança aplicada e validada **por bytes**.
- **MCP**: nada a atualizar — trabalho sem task associada.

## Verificação

```
contexts            40 → 41        removidos: 0
strict              false → false
enforce_admins      true → true
allow_force_pushes  false → false
nome no vivo        342 200 224    (U+2014 — sem mojibake)
protection-drift    🟡 → 🟢
hooks-manifest      rc=1 → rc=0
```

Validei **por bytes, não por contagem**: o travessão do nome é a armadilha de mojibake de 2026-07-02, onde 10 contexts entraram tortos e deadlockaram o `main` com 54/54 verdes — e contagem não detecta isso. Usei o endpoint **aditivo** (`POST .../contexts`) com payload de 1 nome, evitando re-postar os 41 e a transcrição à mão.

## Lições catalogadas

- **O controle não pode conter o tratamento.** Investigando o vermelho do `_HOOKS-INDEX`, rodei o `--check` em `origin/main` e conclui *"pré-existente, não é meu"*. **Errado** — o `main` já continha o merge do flip. O controle correto é `<sha-do-flip>~1`, e o veredito **inverte** (rc=0 antes, rc=1 depois). Generalizável: *"isso já era assim?"* logo após mergear, com o próprio commit no `main`, é pergunta sem controle. Família **LC-08**; entrou no RUNBOOK.
- **`protection-drift` 🟢 não significa "sem drift".** Significa *"baseline casa o vivo"*. Índices derivados do baseline ficam fora do escopo dele — medido caso a caso: `_HOOKS-INDEX` precisa de ação manual; `PAINEL-SISTEMA`/`Jana-ARCHITECTURE`/`ONBOARDING` se curam no `schedule` diário; `MAQUINAS-INVENTARIO` não depende do baseline.
- **Um alarme falso meu, barrado a tempo:** vi `system-map --check` vermelho e ia reportar como achado do flip. Medi antes — vermelho **também** no commit anterior, e o job agendado passa todo dia. Stale entre refreshes é o desenho.
- **Desvio consciente registrado, não maquiado:** a DR-2 da ADR 0336 pede bite-log de ≥2 PRs contrafactuais e **não foi coletado**. Está escrito assim no `_meta.promocoes`, apoiado em 2 incidentes reais da mesma família em 4 dias + contrafactual do lint (0 → 1 na linha 53 → 0) + FP 0/121.

## Próximos passos pra retomar

```
node scripts/governance/protection-drift.mjs && node scripts/governance/required-always-run.mjs
```

Os dois devem sair **0**. Se o `protection-drift` acusar 🔴 *"required SUMIU do vivo"*, alguém removeu o context — demoção exige PR + ADR (`_meta.regra`), nunca `--write` no baseline.

## Pointers

- Auditoria que originou tudo: [handoff 21:53](2026-08-08-2153-teste-07-auditoria-15-e-a-lane-revivida.md) · IT8 + lint: [handoff 22:58](2026-08-08-2258-it8-divida-paga-e-lint-de-yaml-de-workflow.md)
- Receita (agora com o passo dos derivados): [`RUNBOOK-branch-protection.md`](../requisitos/Infra/RUNBOOK-branch-protection.md)
- Registro da promoção + desvio da DR-2: [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json) `_meta.promocoes`
