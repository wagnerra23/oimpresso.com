---
date: "2026-08-10"
hour: "17:30 BRT"
topic: "Eixo 2 do test-lane-coverage: dos 40 testes que uma lane alcança e o driver faz pular, 12 ligados e 28 classificados — e o skip escondia um crash de produção"
authors: [C]
prs: [5524, 5526, 5528]
outcomes:
  - "12 testes saíram do mudo: +105 passed, +409 assertions que não existiam em lane de PR nenhuma"
  - "--mudos 40 → 28, medido no main pós-merge"
  - "Bug de produção achado por ligar o teste: RetentionCleanupCommand.php usa $businessId numa closure que não o capturou"
  - "Fantasma de 2ª ordem catalogado: teste que pula MESMO na lane com o driver certo"
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
---

# Os mudos do eixo 2 — 12 ligados, 28 classificados, e um crash que o `skip` escondia

Continuação direta do próximo-passo deixado pelo [handoff das 13:30](../handoffs/2026-08-10-1330-jana-lida-inteira-56-gaps-e-o-eixo-que-faltava.md):
*"os 40 testes mudos exigem CT 100 antes de ligar"*.

## O ponto de partida quase deu errado

O brief mandava rodar `node scripts/governance/test-lane-coverage.mjs --mudos`. A flag
**não existia** no meu worktree — o [#5522](https://github.com/wagnerra23/oimpresso.com/pull/5522)
que a entrega tinha mergeado 40 min antes e meu `origin/main` estava stale. O script
**ignorou a flag em silêncio** e imprimiu o relatório do eixo 1 (alcançado × órfão), que
responde outra pergunta.

Se eu tivesse lido aquele número como "a lista dos mudos", teria trabalhado o corpus errado
a sessão inteira. O que salvou foi conferir a interface do script antes de confiar na saída —
não a saída em si, que era plausível.

**Lição operacional:** flag desconhecida em CLI Node costuma ser ignorada, não rejeitada.
`--mudos` num script que não a conhece imprime o default e sai 0.

## O que foi feito

| PR | grupo | delta medido vs `main` |
|---|---|---|
| [#5524](https://github.com/wagnerra23/oimpresso.com/pull/5524) | 2 sqlite (Jana + KB) | +20 passed · **+92 assertions** |
| [#5526](https://github.com/wagnerra23/oimpresso.com/pull/5526) | 3 Arquivos | +20 passed · **+96 assertions** |
| [#5528](https://github.com/wagnerra23/oimpresso.com/pull/5528) | 7 NfeBrasil + Fiscal | +65 passed · **+221 assertions** |

Em nenhum deles usei "check verde" como prova. A régua foi sempre **delta de `passed` e
`assertions` contra o `main`**, porque é exatamente o defeito que o eixo 2 mede: `skip` sai
`exit 0`, então verde não distingue "rodou e passou" de "não rodou".

No #5524 o delta bateu `+20 = 6 + 14`, exatamente os casos dos dois arquivos, com `skipped`
**inalterado** em 66 — prova de que não foram adicionados-e-pulados. No #5528, `+65` bateu a
soma prevista arquivo a arquivo (16+12+11+10+7+7+2).

Entre o que passou a rodar: **`R-COPI-202-003`**, a trava cross-tenant Tier 0 ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))
do `BriefDiarioAgent`, que nunca tinha corrido em lane alguma.

## As duas direções do guard (e por que a decisão muda)

Os 40 não eram um grupo só:

- **2 arquivos** (Jana `BriefDiarioAgentTest`, KB `LgpdComplianceTest`) exigem **sqlite** e
  estavam pendurados em lane **MySQL**. Os dois guards vieram do **mesmo commit**
  `cb120f2346f` (*"quarentena em massa de corruptores era-sqlite"*, 193 arquivos) — guard
  **defensivo**, para proteger o MySQL compartilhado de testes que fazem `dropIfExists`.
  Ambos nasceram escritos para sqlite; a lane sqlite era o habitat original, não um contorno.
- **38 arquivos** exigem **MySQL** com schema UltimatePOS e estavam na matriz **sqlite** do
  `modules-pest.yml`.

## O achado que paga a sessão

Ligar os 5 do Arquivos derrubou a lane com `9 failed`. Cinco dessas falhas eram **bug de
produção**, não defeito de teste:

```
ErrorException: Undefined variable $businessId
  at Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php:194
```

A closure abre na `:124` com `use ($dryRun, $retentionDays, &$stats)` e usa `$businessId`
na `:194`. A variável existe no escopo externo (`:63`) e nunca foi capturada.

**Por que ficou invisível:** a linha 194 fica **depois** do `foreach`, dentro do `->chunk()`.
Com **0 linhas** a closure nem é chamada e o comando "passa". Ele só crasha **quando há
arquivo de fato para limpar** — o caso de produção. E o teste que provaria isso saía verde
por `skip`.

**Não consertei, de propósito.** Não há transação envolvendo o `chunk`: hoje o comando apaga
até um lote, remove os arquivos do disco, grava audit log e crasha **sem rollback**.
Consertar faz ele percorrer todos os chunks, ou seja **apagar mais por invocação**. É o
comportamento pretendido, mas é mudança de comportamento destrutivo — decisão [W].
Mitigante: não está agendado no `Kernel.php` (só o `arquivos:health-check` está).

## Fantasma de 2ª ordem — o padrão mais reaproveitável

`Modules/Fiscal/Tests/Feature/NfseCockpitControllerTest` rodou na lane MySQL e deu
**`passed=0 · failed=0 · errors=0 · skipped=4`**. Ele tem um **segundo guard** que o faz
pular **mesmo com o driver certo**.

Ou seja: *"mover pra lane com o driver certo" nem sempre basta*. Adicioná-lo trocaria
"pula por driver" por "pula por outra razão", com a mesma aparência de cobertura. Deixei-o
fora e declarei no workflow.

Isso valida o limite que o `--mudos` declara de si mesmo: *"só skip por DRIVER é medido;
skip por env/flag/todo() não é visto"*.

Corolário de método: o veredito por arquivo veio do **sumário JUnit** (`junit-summary.mjs`),
não do console do Pest. No console esse arquivo aparece como `WARN`/verde — é o
`junit-summary` que expõe `passed=0`.

## Os 28 que restam

| grupo | n | por quê |
|---|---|---|
| ComVis 7 · Repair 3 · Vestuario 3 | 13 | **não existe lane MySQL** — criar uma é workflow novo (`gates-registry.json` Check G + teto [ADR 0298](../decisions/0298-teto-de-governanca-anti-proliferacao-gates.md)) |
| Fiscal 7 · NfeBrasil 6 · Arquivos 2 | 15 | dívida real, agora visível e datada nos comentários dos workflows |

A dívida dos 15: `PermissionDoesNotExist` em 5 (o seed da lane não cria as permissions que o
teste exige), `QueryException` em 2, `BadMethodCallException` em 1, asserção em 4, e os 2 do
Arquivos (1 é o bug acima; o outro, `ExportZipCommandTest`, tem 3 falhas por
`Artisan::output()` chamado 2× — `BufferedOutput::fetch()` esvazia o buffer — e 1 não
diagnosticada, `audit log 'exported'` ausente).

⚠️ **Tier 0 ainda mudo:** `NfeEventoMultiTenantIsolationTest`. Registrado, não escondido.

## Erros meus nesta sessão

Registro porque a disciplina de medição é o objeto do trabalho.

1. **Atribuí um vermelho do KB à minha remoção** comparando placares (`main` 106+14 skipped ×
   PR 105+1 failed). A aritmética pressupõe ordem determinística, e `phpunit.xml:7` tem
   **`executionOrder="random"`** — comparar runs de seeds diferentes não prova causa. Era o
   flaky nominal do `V2b`, que o próprio [`Modules/KB/Tests/Helpers.php:311+`](../../Modules/KB/Tests/Helpers.php)
   documenta e **nomeia**. Contexto: a lane está **12 vermelho / 16 verde em 30 runs no
   `main`** (~43%), sem PR meu envolvido.
2. **Afirmei que o `DanfeServiceTest` dropava `business`** e que isso inviabilizaria o grupo
   NfeBrasil numa lane required. Conclui de `grep` de `dropIfExists` **sem ler o contexto do
   guard**. Todos estão dentro de `if (driver === 'sqlite')`; os testes que criam `business`
   sintético pulam explicitamente em MySQL. É dual-mode por desenho.
3. **Concluí "Fiscal não tem lane MySQL"** porque não existe `fiscal-pest.yml` — inferência
   por **convenção de nome de arquivo**. Fiscal é coberto pela `nfebrasil-pest.yml`, que roda
   `Modules/Fiscal/Tests/Feature/*CockpitMultiTenantTest` desde antes. Isso mudou a
   decomposição de "22 sem lane" para **13**.
4. **Quase reportei um 2º vermelho do KB como "o mesmo flaky"** — era `##[error] self-signed
   certificate` no `dorny/paths-filter`, flake de **infra**: o job morreu antes de rodar Pest
   (log de 48 KB contra centenas de KB de uma run real).
5. **Construí um helper** que contava os 40 mudos como **234**. Descartei em vez de confiar, e
   só voltei a usá-lo depois de bater com o número do dono (o bug era passar a allowlist só
   pro `ci.yml`; o CLI passa sempre).
6. **Título do #5526 mergeado diz "5 testes"** quando entraram 3 — reduzi na revisão e não
   atualizei o título antes do merge. [Errata registrada no PR](https://github.com/wagnerra23/oimpresso.com/pull/5526#issuecomment-5243643378);
   corrigi o mesmo defeito no #5528 **antes** do merge daquele.

Em todos os casos o conserto veio de consultar a fonte certa, não de reler a própria conclusão.

## Ressalva de validação

**O CT 100 ficou fora do ar a sessão inteira** (3 tentativas, `502 Bad Gateway` no dial da
`:22`), e Pest local é bloqueado por hook ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).
Usei as **lanes dos próprios PRs como executor** — um vermelho ali bloqueia só o PR em
questão — e declarei a substituição em cada PR antes de qualquer merge.

Para o grupo NfeBrasil isso **não foi perda**: o cabeçalho daquela lane já registra que o
`oimpresso-staging` **não tem as tabelas do módulo** (medido 2026-07-27), então a suíte
pularia lá de qualquer forma.
