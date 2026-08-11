---
slug: o-403-da-lane-kb-nunca-foi-flake
date: "2026-08-11"
time: "19:00"
tldr: "O 403 'intermitente' da lane KB era 100% determinístico: kbActAsUser entregava um modelo pela metade (save() sem refresh) e o CheckUserLogin, que lê do MODELO e roda antes do can:, abortava 403. Provado por experimento de 2 braços (25/25 falha → 0/25 após o fix). As 3 correções anteriores miravam contaminação Spatie — modelo errado. #5606 + #5604 mergeados."
autor: "[CL] Claude Code"
sessao: eloquent-easley-37d93a
prs: [5606, 5604]
next_steps:
  - "Confirmar a run de kb-pest em main (31523262616) — estava pending no fechamento"
  - "Corrigir header do kb-pest.yml + anchor do gates-registry.json (ainda culpam 'flakiness Spatie order-dependent' — agora falso)"
  - "Decidir descarte da branch claude/kb-flake-403-probe (andaime de diagnóstico, não vazou pra main)"
  - "Avisar a sessão da branch claude/kb-403-arraystore — a hipótese do array store foi refutada por medição"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0062-separacao-runtime-hostinger-ct100
---

# Handoff — o 403 da lane KB nunca foi flake

> **Cumprindo R12** via skill `encerrar-sessao` (ativação lazy, hook `UserPromptSubmit`).

## A causa-raiz

`kbActAsUser` (Modules/KB/Tests/Helpers.php), no ramo de **criação**:

```php
$user = new $userClass();  $user->id = $userId;  …  $user->save();
```

O `save()` insere e o MySQL aplica os DEFAULTs (`users.user_type`=`'user'`,
`users.allow_login`=`1`), mas o Eloquent **não recarrega o modelo**. A instância em
memória fica com os dois `NULL` — e é ela que o `actingAs()` põe no guard. O
[`CheckUserLogin`](../../app/Http/Middleware/CheckUserLogin.php) roda **antes** do `can:`
e lê do **modelo**, não do banco:

```php
if ($request->user()->user_type != 'user' || $request->user()->allow_login != 1)
    abort(403, 'Unauthorized action.');
```

Fix: `$user->refresh()` após o `save()` — em vez de setar as 2 colunas à mão, porque é
imune a qualquer coluna futura com DEFAULT que outro middleware venha a ler (mesmo
raciocínio "robusto a drift de schema" do `kbCreateBusinessRow`).

**Não é bug de produção**: em prod o guard resolve o usuário do banco, com a linha
inteira. É defeito de test-infra.

## Por que parecia flake (e por que 3 correções erraram)

O bug dispara **uma vez por banco** (o da lane é efêmero por run), e
`MultiTenantTraitTest` chama `kbActAsUser` **sem fazer request** — quando ele sorteia
primeiro, o usuário nasce lá e o bug é **consumido em silêncio** (run verde). Quando quem
sorteia primeiro é um teste HTTP, ele toma o 403. O aleatório nunca foi o bug: era **quem
leva o golpe**.

Medido nos artefatos JUnit de 6 falhas (`31425398494` · `31482695145` · `31491029075` ·
`31513833674` · `31514548359` · `31517070137`): a vítima é **sempre o 1º request
autenticado do processo, nunca o 2º**. Recibo mais claro — run `31491029075`: `V3` falha
como 1º teste da run e `V2b`, logo em seguida, mesmo helper e mesma permissão, **passa**.

Os BLOQUEADORES 1/2/3 do `Helpers.php` miravam **contaminação entre testes por ordem
aleatória** — modelo errado. Duas premissas deles caem por medição:

| premissa | medição |
|---|---|
| "CACHE_STORE=file → cache do Spatie persiste entre testes" | `config/cache.php:18` lê `CACHE_DRIVER`; a lane grava `array` → o `config(store=array)` é **no-op** na CI |
| "registry Spatie acumula estado entre testes" | `grep -rn "static \$" vendor/spatie/laravel-permission/src` = **ZERO** |

## O experimento (job `probe-403`, temporário)

1 processo = 1 amostra, então N iterações valem N runs da lane inteira. Dois braços
separavam as 2 causas que ficaram confundidas nas 6 falhas:

| braço | condição | antes do fix | depois |
|---|---|---|---|
| warmup | banco virgem | 1/1 falhou | **0/1** |
| A | usuário já existia | 0/25 | 0/25 (controle — não muda) |
| **B** | usuário recriado a cada iteração | **25/25 falharam** | **0/25** |

Runs [31519646171](https://github.com/wagnerra23/oimpresso.com/actions/runs/31519646171)
(diagnóstico) e
[31520525185](https://github.com/wagnerra23/oimpresso.com/actions/runs/31520525185)
(prova de mordida). O diagnóstico capturou a exceção literal
`HttpException: Unauthorized action.` — mensagem do `CheckUserLogin`, **não** do `can:` —
com a linha do banco **já correta**, isolando a divergência em banco × memória.

## O buraco Tier 0 que estava junto ([#5604](https://github.com/wagnerra23/oimpresso.com/pull/5604))

Os 7 casos de `CrossTenantIsolationTest` tinham **só asserts negativos**
(`toBeIn([403,404])`). Uma sessão que devolvesse 403 pra tudo satisfaz todos — inclusive o
403 que o docblock do próprio arquivo já classifica como *"falso-verde Tier 0"*. **Sem
nenhum caso exigindo 200, o gate não conseguia ficar vermelho.**

Não era hipotético: o run **31502400773 fechou VERDE** tendo como 1º request autenticado do
processo um caso cego desse arquivo. E quando o controle positivo entrou, ele **pegou o bug
na hora** — o `PUT cross-tenant` falhou em `CrossTenantIsolationTest.php:82`, exatamente a
linha `assertOk()` do controle. Verdadeiro-positivo, não regressão.

## Guards que ficam (ambos na allowlist da lane)

- **L5 BITE** — `userId` inédito força o ramo de criação em **qualquer** ordem → morde
  deterministicamente; sem o fix, falha.
- **L6 CONTROLE NEGATIVO** — usuário íntegro mas **sem** a coarse → o 403 legítimo continua
  403. Impede que um "conserto" que afrouxe authz passe pelo L5.
- **`kbControlePositivoBiz1()`** nos 6 casos cross-tenant com HTTP.

Lane no PR do fix: `108 passed (605 assertions)` (era 106/597).

## Erros meus nesta sessão (registrados, não apagados)

1. **Parser de JUnit quebrado** — `([^>]*)` engolia o `/` do self-closing e agrupava vários
   `<testcase>` num match, jogando o "FAIL" no primeiro. A primeira leitura de ordem foi
   lixo, incluindo uma hipótese de adjacência com `KbAutoClassifierTest`. Morreu quando
   validei o parser contra os totais declarados (106 = 106).
2. **Declarei `CheckUserLogin` "eliminado por medição"** — medi os defaults do *schema*
   (corretos) e conclui que o middleware passava. A pergunta certa era outra: ele lê o
   **modelo em memória**, não o banco. LC-08 puro; quem pegou foi o experimento.
3. **`cmd || echo` mentindo** — o check "job probe-403 ausente de main" saiu `✓` de um
   comando que **falhou** por MSYS mangling do `:`. Refeito com controle positivo (rc=0
   acha `kb-pest`, rc=1 não acha `probe-403`).

## Estado no fechamento

| item | estado |
|---|---|
| [#5606](https://github.com/wagnerra23/oimpresso.com/pull/5606) conserto + guards | **MERGED** 18:31 (`8f377abccda`) |
| [#5604](https://github.com/wagnerra23/oimpresso.com/pull/5604) controle positivo | **MERGED** 18:32 (`2487263b1a0`) |
| lane kb-pest em `main` com os dois | ⏳ `pending` ([31523262616](https://github.com/wagnerra23/oimpresso.com/actions/runs/31523262616)) — **não confirmado** |
| andaime `probe-403` / `FlakeProbe403Test` | **ausente de `main`** (verificado com controle positivo) |
| branch `claude/kb-flake-403-probe` | viva no remoto — descarte é decisão [W] |

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 10 tasks, **todas em REVIEW** (US-TR-309/310/305/306/311, US-PROD-027/025,
  US-INFRA-023/048, US-KB-002) — nenhuma tocada nesta sessão
- handoffs do dia: `2026-08-11-1810`, `-1345`, `-1336`, `-1245`, `-1200`
- Nenhuma ADR nova. O conserto é test-infra, não muda decisão arquitetural.
