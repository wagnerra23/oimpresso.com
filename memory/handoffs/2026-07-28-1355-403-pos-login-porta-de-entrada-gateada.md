---
date: "2026-07-28"
time: "13:55 UTC"
slug: "403-pos-login-porta-de-entrada-gateada"
tldr: "Funcionária trancada em 403 logo após o login: o #4859 gateou /ia e /home redirecionava incondicionalmente pra lá — perder o checkbox não custava a Jana, custava o ERP inteiro. Fix: /home escolhe destino pela habilidade. PR #4949 aberto, CI 93 verde."
prs: [4949]
decided_by: [W]
next_steps:
  - "Merge do #4949 + deploy + smoke logando com a conta afetada"
  - "Marcar `jana.access` no papel dela em /roles/{id}/edit (desbloqueio imediato, independe de deploy)"
---

# Handoff 2026-07-28 13:55 — o 403 pós-login era a porta de entrada atrás de permissão de feature

> Um PR: [#4949](https://github.com/wagnerra23/oimpresso.com/pull/4949) (aberto, CI verde, **não mergeado**).

## Estado MCP no momento do fechamento

**MCP indisponível.** `cycles-active` deu `MCP error -32001: Request timed out` e `my-work`
respondeu `Server unavailable`. Snapshot substituído por inspeção direta de git/gh — registrado
aqui como estado, não como omissão.

## O que aconteceu

[W] relatou: *"depois do login da maiara está trancado com erro 403 (...) WR23 vai para dashboard
e ela não tem acesso"*. Não era conta perdendo acesso — era **a porta de entrada do ERP atrás de
uma permissão de feature**. Cadeia, toda determinística:

1. `LoginController::redirectTo()` devolve `/home`
2. `/home` redirecionava **incondicionalmente** pra `/ia/dashboard`
3. [#4859](https://github.com/wagnerra23/oimpresso.com/pull/4859) (27/07) ligou `can:jana.access` no grupo `/ia`
4. `jana.access` nasce `default => false` no registry

→ quem não tem o checkbox leva 403 na **única porta que o login abre**.

**Por que o dono não sentia:** `Gate::before` devolve `true` pra `Admin#{business_id}` — a
permissão só morde funcionário. É exatamente o contraste que [W] descreveu (WR23 entra, ela não).

O comentário do próprio #4859 previu o risco (*"funcionária sem o checkbox perde a Jana"*) mas
**errou o alcance**: não era perder a Jana, era perder o ERP. A lição não é "faltou avisar" — o
aviso existia; é que **estimar alcance por leitura do módulo não vê o que redireciona pra dentro dele**.

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| [`routes/web.php`](../../routes/web.php) | `/home` escolhe destino pela habilidade: com `jana.access` → `/ia/dashboard`; sem → `/dashboard-legacy` (rota que já existe, sem `can:`). O gate do #4859 fica de pé. |
| [`Modules/Jana/Tests/Feature/JanaAccessGateTest.php`](../../Modules/Jana/Tests/Feature/JanaAccessGateTest.php) | +2 casos travando o **limite** do gate (sem a permissão → legado, nunca 403; com ela → destino canon). |

## Persistência

- **git:** branch `claude/maiara-403-access-denied-678be5`, commit `0f5f7adc0f`, PR #4949 com `## Infra Contract`.
- **CI:** **93 pass / 0 fail** (2 `skipping` são jobs de cron, não rodam em PR).
- **MCP:** não registrado — servidor fora do ar (ver snapshot acima).

## Próximos passos pra retomar

```bash
gh pr view 4949
```

Depois do merge+deploy, o smoke que **fecha de verdade** é login real com a conta afetada
(`/home` → `/dashboard-legacy`, sem 403) e com WR23 (`/home` → `/ia/dashboard`, inalterado).

## Lições catalogadas

- **O `curl` anônimo não exercita contrato sessão-dependente.** Os 3 endpoints dão `302 → /login`
  antes e depois do fix — isso prova que `auth` dispara antes do `can:`, **não** prova o fix. Está
  escrito assim no Infra Contract em vez de virar evidência de fachada.
- **`laravel.log` não é oráculo de 403.** Laravel não reporta 4xx por padrão; deu `0` ocorrências
  de `AccessDeniedHttpException` e isso não prova nada. Cheguei a rodar antes de concluir — família
  do `crontab -l` (§5 2026-07-17): saída vazia de instrumento que não mede aquilo ≠ evidência.
- **`pass` de lane não é execução (LC-13).** `JanaAccessGateTest` tem 4 caminhos de
  `markTestSkipped`. Fui ao log ler os `✓` com timing e o `33 passed (94 assertions)` antes de
  dizer que o guard morde — o arquivo tem 1 caso legitimamente skipped (sem `Admin#biz` no CI).
- **Limite honesto desta sessão:** não confirmei a linha de permissão da usuária no banco — 2
  leituras do DB de prod foram **bloqueadas pelo classifier**. O mecanismo está provado no código e
  o gate está confirmadamente deployado (li a linha 50 no servidor), mas *"é ela especificamente"*
  segue inferência. Está dito no PR, não maquiado.

## Achado colateral (não tocado — fora do escopo)

[`tests/Feature/Home/HomeIndexInertiaTest.php`](../../tests/Feature/Home/HomeIndexInertiaTest.php)
— 5 casos afirmam `/home` → `200` com componente `Home/Index`. `/home` é `302` **desde 22/05**. O
arquivo **não está em lane nenhuma do CI**, então nunca rodou pra denunciar a contradição. É a
mesma forma do §5 2026-07-28 (*"defeito em teste que não roda é invisível até a lane ligar"*).
Decisão [W]: PR separado.
