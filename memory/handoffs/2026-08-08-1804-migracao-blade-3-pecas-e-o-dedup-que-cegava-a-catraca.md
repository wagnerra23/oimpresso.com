---
date: "2026-08-08"
time: "18:04 BRT"
slug: migracao-blade-3-pecas-e-o-dedup-que-cegava-a-catraca
tldr: "A rota de migração Blade→React da ADR 0277 ganhou as 3 peças que faltavam (medidor · cobrança · catraca) e um bug meu quase as envenenou: o censo contava rota 2× no Windows, inflando o baseline 45% e cegando a catraca no CI. Achado rodando o sentinela em PROD, não por leitura."
prs: [5422, 5424, 5425, 5431, 5438]
decided_by: [W]
related_adrs: [0277-rota-migracao-blade-ondas-completude, 0256-knowledge-survival-meia-vida-catraca-sentinela]
next_steps:
  - "Rodar o sentinela 1× manual em prod com --dry-run antes de agendar cron (decisão [W])"
  - "Ratificar (ou recusar) a proposal da emenda do Audit Card — é pré-condição da fatia D da Jana"
  - "2 chips rodando: benchmark §11 do design-memory; 2 schedules quebrados em prod"
---

# Migração Blade→React: as 3 peças + o dedup que cegava a catraca

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **8 tasks em REVIEW** (US-TR-309/310/305/306 · US-PG-008 · US-PROD-027 · US-INFRA-023/048)
- handoffs irmãos de hoje: 4 (modificadores-gate · deadlock-required · jana-memoria-fatia-d · permissoes-classe-d) — **nenhum** sobre migração Blade
- ADRs no intervalo: 0371 ratificada [W]; proposal do Audit Card criada (#5422)

## O que aconteceu

Começou como "fase 2 da Jana" ([CC] `JANA-FUSAO-2026-08-06`) e virou outra coisa quando [W] apontou a raiz: **Blade e React coexistindo é o que suja o design**. A pergunta virou *"como saber a pendência de cada tela e migrar de modo eficiente"*.

**A estrutura já existia** — [ADR 0277](../decisions/0277-rota-migracao-blade-ondas-completude.md), aceita 2026-06-13, com 10 ondas e censo por 12 domínios. Ela **nasceu de um pedido quase idêntico do próprio [W]**. Não funcionou porque o contrato ("migrado = route Blade morto") **nunca ganhou máquina**: 0 scripts, 0 workflows contavam route Blade vivo, e o censo A–L era digitado à mão. Caso-livro da [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md).

Entregues as 3 peças que faltavam — e a auditoria em prod pegou um bug **meu** que teria envenenado todas.

## Artefatos gerados

| PR | o que | estado |
|---|---|---|
| [#5422](https://github.com/wagnerra23/oimpresso.com/pull/5422) | US-COPI-127 reancorada (sujeito não existe: tabela dropada, `acoes` é `useMemo`) + proposal da emenda do Audit Card | merged |
| [#5424](https://github.com/wagnerra23/oimpresso.com/pull/5424) | **peça 1 — medidor** `blade-migration-census.mjs` (derivado) | merged |
| [#5425](https://github.com/wagnerra23/oimpresso.com/pull/5425) | **peça 2 — cobrança** `BladeMigrationSentinelCommand` → `mcp_tasks` → brief | merged |
| [#5431](https://github.com/wagnerra23/oimpresso.com/pull/5431) | **peça 3 — catraca** só-desce, job advisory no `governance-gate` | merged |
| [#5438](https://github.com/wagnerra23/oimpresso.com/pull/5438) | **fix do dedup** — baseline 683 → **471** | merged |

## O achado que pagou a sessão

Rodando o sentinela em **prod** (`--dry-run`, autorizado por [W]), ele disse *"progresso: 212 endpoints saíram do Blade (683 → 471)"*. Não houve progresso: **o censo contava rota 2× no Windows**.

`rotaFiles()` testava `Routes/web.php` e `routes/web.php`; o NTFS é case-insensitive, então o mesmo arquivo entrava duas vezes. Medido: **60 "arquivos" × 33 reais = 27 duplicados**, censo 471 → 683 (+45%).

**Por que era grave:** o baseline nasceu no Windows (683) e a catraca roda no CI em **Linux** (471). Ela comparava 471 contra 683, via "progresso" e **nunca morderia**. O bite-test que a aprovou rodou no Windows, com os dois lados igualmente inflados — **verde por simetria de erro**. E o sentinela sugeria *"regrave o baseline"*: segui-lo teria enterrado 212 endpoints de dívida.

Fix: dedup por `realpathSync.native()` (resolve o case real no Windows, no-op em Linux). Local passou a bater com prod. Selftest 27 → 29; mordida re-provada com baseline correto.

## Persistência

- **git:** 5 PRs merged em `main` (acima)
- **MCP:** propaga via webhook após push deste handoff
- **BRIEFING:** não atualizado — o `Modules/Governance/BRIEFING.md` descreve capacidades de produto; um comando de console de governança não muda a capacidade descrita lá

## Lições catalogadas

1. **Diferença entre ambientes é oráculo.** Nenhum dos 96 checks pegaria o dedup — o CI só roda Linux, onde os dois lados eram consistentes entre si. Só rodar em **prod** denunciou.
2. **`$?` depois de pipe mede o `head`, não o comando.** Cometi 2× hoje (integrity-check e a própria catraca) e quase concluí do número errado. Refiz sem pipe nas duas.
3. **Hipótese de causa ≠ causa.** Afirmei que o sentinela quebraria em cron por falta de node; medi com `env -i` (proxy ruim) e com `shell_exec` (que está em `disable_functions`). O teste certo — `proc_open`, que é o que o Symfony `Process` usa — deu `rc=0 v24.15.0`. Hipótese refutada por medição minha mesmo.
4. **`Governance` não está na matriz do `modules-pest.yml`** — teste em `Modules/Governance/Tests` não roda em lane nenhuma (LC-13). Contornado pela lane de lógica pura; ligar a matriz rodaria 56 Feature de uma vez.

## Próximos passos pra retomar

```bash
node scripts/governance/blade-migration-census.mjs --report    # onde está a dívida (471 endpoints)
```

Decisões [W] pendentes: (a) agendar o cron do sentinela — 1ª execução manual com `--dry-run`; (b) ratificar/recusar a proposal do Audit Card (pré-condição da fatia D da Jana).

## Pointers detalhados

- Censo/catraca: [`scripts/governance/blade-migration-census.mjs`](../../scripts/governance/blade-migration-census.mjs) (cabeçalho traz limite honesto + fronteira vs sentinela)
- Sentinela: [`Modules/Governance/Console/Commands/BladeMigrationSentinelCommand.php`](../../Modules/Governance/Console/Commands/BladeMigrationSentinelCommand.php)
- Baseline: [`governance/blade-migration-baseline.json`](../../governance/blade-migration-baseline.json)
- Rota das 10 ondas: [`memory/requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md`](../requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md)
- Achados de prod fora do escopo: 2 schedules falhando (chip `task_4677a802`)
