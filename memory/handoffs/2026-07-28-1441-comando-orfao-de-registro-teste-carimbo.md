---
date: "2026-07-28"
time: "14:41 BRT"
slug: "comando-orfao-de-registro-teste-carimbo"
tldr: "O comando nao era orfao de schedule, era orfao de REGISTRO — nunca chegou ao Artisan, e o teste que afirmava 'registrado' media app(Class) por 2,4 meses. Fix mergeado (#4968), smoke real verde. O gap que motivava ligar o cron e ZERO: as 30 P0 sem dono ja sao vistas pelo cron das 06:45."
prs: [4968]
decided_by: [W]
next_steps:
  - "[W] decide: aposentar os 2 health commands OU estender o predicado do mcp:tasks:unassigned (nao agendar um 2o medidor)"
  - "Aberto: nenhum dos 2 modulos tem lane de CI, entao o assert corrigido nao e vigiado"
  - "Aberto: mcp_projects tem 0 linhas em prod — a recomendacao do WARN active_projects esta errada"
---

# Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` (@wagner) → **10 tasks em REVIEW** (US-COPI-123 p0 · US-TR-309/310/311 · US-PG-008 · US-PROD-027 · US-INFRA-023 · US-TR-305/306 · US-KB-001)
- Handoffs irmãos de hoje (sessões paralelas): `1310` varredura-do-não-salvo · `1355` 403-pós-login · `1440` regra-do-dono-vira-teste · `1450` gitattributes-inerte · `1520` rb-investigação
- Duplicação checada antes de escrever (Tier 0): nenhum session log ou handoff de hoje toca este tema

# O que aconteceu

Um briefing pedia decidir entre **(a) ligar** ou **(b) aposentar** o `project-mgmt:health` — "máquina órfã: existe, tem checks reais, zero invocadores". A medição refutou a premissa nas duas pontas.

**A causa é mais grave.** Não era órfão de *schedule*, era órfão de *registro*: `ProjectMgmtServiceProvider::register()` estava **vazio**, e o artisan respondia `There are no commands defined in the "project-mgmt" namespace`. Não rodava nem à mão. **31 de 33** módulos registram os próprios comandos; os **2** que não (`ProjectMgmt`, `ProductCatalogue`) são exatamente os 2 com comando morto. Vivos assim desde maio/2026 (Wave 17), ~2,4 meses.

**A justificativa é menor.** Medido em prod (o CT 100 tem `mcp_tasks` **vazia** e teria dado o número errado): das P0 ativas sem dono, **todas as 30 estão em `status=todo`** e já são vistas pelo cron `mcp:tasks:unassigned` das 06:45. O **delta exclusivo é zero** — era 1 na primeira medição, e a task em `review` saiu em ~1h. Somado a threshold `FAIL>=5` (nasceria vermelho permanente) e 2 dos 4 checks serem carimbo, o caminho (a) se autodestrói.

**O teste que devia pegar isso era carimbo.** `"F6 … registrado + signature canon"` provava registro com `app(Class::class)`, que resolve qualquer classe do disco. Bite-test: `1 passed (3 assertions)` no **mesmo container** onde o artisan não conhece o comando. **LC-11, 3ª instância em produção em 3 dias** — e apareceu pelo predicado que a nota do LC-11 já prescrevia: confrontar **saída × fonte**, não reler código.

# Artefatos gerados

| arquivo | o quê |
|---|---|
| `Modules/{ProjectMgmt,ProductCatalogue}/Providers/*ServiceProvider.php` | `$this->commands([...])` em `runningInConsole()` (padrão `VestuarioServiceProvider`) |
| `Modules/{ProjectMgmt,ProductCatalogue}/Tests/Feature/Wave23SaturationTest.php` | assert que exerce o verbo: `expect(array_keys(Artisan::all()))->toContain(...)` |
| `memory/proibicoes.md` §5 | lápide 2026-07-28 — o limite: provar registro com `app()`/`class_exists()`/`file_exists()`; cada registry tem seu oráculo |
| `memory/LICOES_CODE.md` LC-11 | Ocorrências 4→5 + nota do Gate com o recorte medido (5 testes do padrão, 2 escondiam defeito) |
| `memory/sessions/2026-07-28-comando-orfao-de-registro-e-teste-carimbo.md` | narrativa completa |

# Persistência

- **git:** [#4968](https://github.com/wagnerra23/oimpresso.com/pull/4968) **MERGED** por [W] 17:12 UTC (commit `abae73fb78`), deploy `success`, **96 checks pass / 0 fail**
- **prod:** smoke real cumprido — `login`/`/`/`business/register` = **200/200/302** idêntico à baseline pré-merge (boot intacto); os 2 comandos **presentes** no `artisan list`; `project-mgmt:health --json` rodou pela 1ª vez desde que nasceu
- **MCP:** nenhuma task criada — o que sobrou é decisão [W], não trabalho a enfileirar

# Próximos passos pra retomar

```
gh pr view 4968 && cat memory/sessions/2026-07-28-comando-orfao-de-registro-e-teste-carimbo.md
```

Três pontas abertas, todas do [W]:

1. **Aposentar os 2 comandos** OU **estender o predicado do `mcp:tasks:unassigned`** (`where('status','todo')` → status ativos). **Não** agendar um 2º medidor em paralelo (§5 2026-07-09 "duplica régua consolidada").
2. **Nenhum dos 2 módulos tem lane de CI** (`modules-pest.yml` = allowlist de 6). O registro funciona em runtime; o *teste* segue sem gate. Ligar a lane avermelharia dívida acumulada — o normal, não regressão de quem liga.
3. **`mcp_projects` tem 0 linhas em prod.** O WARN `active_projects` recomenda *"backlog parado OU projects em draft"* — a causa real é tabela nunca populada.

# Lições catalogadas

- **LC-11 (5x)** — presence-gate em produção: teste que afirma um verbo forte (*registrado*) medindo existência de classe. O nome do teste é promessa, e promessa não testada apodrece calada.
- **Erro meu:** `gh run rerun` pra revalidar gate que lê o PR body **não funciona** — reruns reusam o payload do evento original. O caminho é disparar evento novo (`gh pr edit`). Nota deixada no corpo do PR pra quem cruzar com o run vermelho no histórico.
- **Recibo datado não se edita:** a lápide cita **31** e o número virou 30 no mesmo dia. Mantido — o §5 manda *"re-rode a query, não edite o número"*.
- **Oráculo certo importou 2×:** medir população no CT 100 (`mcp_tasks` vazia) teria dado zero; e o registro só se prova no registry vivo (`Artisan::all()`), nunca no disco.

# Pointers detalhados

- Narrativa + medições completas: `memory/sessions/2026-07-28-comando-orfao-de-registro-e-teste-carimbo.md`
- Lápide e o limite generalizado: `memory/proibicoes.md` §5 (2026-07-28, entrada "Teste que afirma registrado…")
- Contador e o ponto cego da classe: `memory/LICOES_CODE.md` LC-11
