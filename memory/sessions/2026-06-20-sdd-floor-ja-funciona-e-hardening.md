---
date: "2026-06-20"
topic: "SDD floor — JÁ funciona (correção da revisão 30-threads) + plano de hardening PR-A/PR-B; estado do swarm de governança e limite de sessão"
authors: [W, C]
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-elo-medir-governar-floor-nightly
  - 0062-separacao-runtime-hostinger-ct100
---

# SDD floor já funciona + hardening (2026-06-20)

## Por que este doc existe
A investigação de 30 threads (`wf_6abb774e-768`) **bateu no limite de sessão** (reset 7:10pm BRT): só **1/30** completou (`floor-writeside`) + crítico e síntese falharam. Mas a thread que rodou trouxe uma correção material à revisão adversarial de hoje, que precisa ser registrada antes de se perder.

## CORREÇÃO da revisão: o transporte do floor NÃO está faltando — está funcionando
A revisão 30-threads (`2026-06-20-sdd-avaliacao-30threads.md`) apontou como **alavanca #1** "conectar o floor do CT100 ao read-side que o CI lê — hoje vive em branch órfã que o CI nunca lê". **Isso está STALE.** Verificação LIVE (git+gh+SSH CT100):

- **Write-side construído e ativo:** PR #2961 mergeou o transporte; deploy key `ct100-nightly-floor-push` (ED25519, write) criada 2026-06-18; runs **06-19 e 06-20 publicaram `floor_count=274`** com `[skip ci]`, author `ct100-nightly-floor`.
- **As 3 nightlies mortas (06-16/17/18, JUnit 0 bytes)** tinham causa-raiz concreta: colisão de símbolo global `Cannot redeclare function insertAuditLog()` (Jana `ImmutabilityTriggersTest` × Arquivos `AuditLogCommandTest`). PHP morria antes do 1º teste → exit 255 → junit 0 bytes. **Já corrigido** (#2953/#2954 add Detector 2 de colisão + fonte renomeada; `HealthCheckCommandTest` usa `insertAuditLogHc`). Por isso 06-19/06-20 vieram válidos.

→ **Não chase um gap que já fechou.** O floor mede de verdade hoje (piso 274, interseção de runs válidos).

## O gap REAL = hardening (não transporte)
Quatro lacunas de robustez até "7 verdes consecutivos":

1. **Sem watchdog de frescura** — `sdd-scorecard.mjs` lê `computed_at` mas nunca checa idade. CT100 pode morrer semanas e o scorecard segue `measured` com 274 velho (regressão silenciosa; o plano-mãe §2 exige "fonte parada = vermelho ≤48h").
2. **Sem alerta de falha** no `ct100-fullsuite.sh` (sem curl/webhook/mcp_alert) — o triplo-fracasso 06-16/17/18 só foi notado por inspeção manual.
3. **SPOF total** — 1 host CT100, 1 cron, 1 deploy key, 1 imagem (arquitetural por ADR 0062; mitigar = decisão Wagner).
4. **Edge-cases** — quando <2 runs válidos, `floor-compute` escreve `floor_count:null` e o push procede, sobrescrevendo um 274 bom (sem guarda); cosmético: `sha=null` no run mais recente porque `floor-compute` lê `sha` do `run.log` (escrito só no `=== done ===`, após o passo [floor]) em vez do `sha.txt` que já tem o valor.

### Fix (sequência recomendada)
- **PR-A (read-side, SEM CT100, mergeável hoje, ~40-60 linhas):** watchdog de frescura em `measureFullSuiteFloor()` — parsear `computed_at` (BRT -03:00), se idade >48h marcar `stale:true` + warning (NÃO virar `notYet`; o número ainda é o melhor dado), pintar amarelo no dashboard/brief. + 2 fixtures no meta-teste `sdd-floor-read.test.mjs` (recente→stale:false; 3d atrás→stale:true). Opcional: `::warning::` advisory no `sdd-scorecard.yml` se floor >48h.
- **PR-B (write-side, micro, precisa scp pro CT100 + Wagner):** no `ct100-fullsuite.sh` passo [floor], não publicar `floor_count:null` por cima de non-null (fetch remoto antes); ler `sha` de `sha.txt` antes do regex; notificar falha de run (PEST_EXIT==255 ou summary ausente) via `mcp_alertas` OU um check `verificacao_sdd` no `php artisan jana:health-check` que SSH-lê o `computed_at` e alarma >48h (fecha (1)+(2) do lado Hostinger).
- Depois: acumular 7 nightlies válidos consecutivos (06-19, 06-20 já são 2 → faltam 5) antes de promover qualquer gate de full-suite a required.

> SSH key-based `root@100.99.207.66` VERIFICADO disponível nesta sessão. Deploy key de push já funciona.

## Estado do swarm de governança (por que o "drain" não concluiu hoje)
5 worktrees/sessões paralelas VIVAS na mesma superfície (`bitemporal-slice2/3`, `ia-os-onda1`, `wt-3069`, `.sdd-main-check`) + branch-protection strict + `_INDEX-GENERATED.md` regenerado por-PR = **treadmill de conflito** que nenhum ator único drena ao vivo. Provado: o push do fix do #3069 foi **rejeitado** (a branch andou 3 commits em minutos).

Progresso real desta sessão: #2611 (zumbi SA-A4) **fechado**; #3068 (ADR 0294 dual-track) + #3073 (0295) **landaram**; SDD adicionado ao PLANS-INDEX ([#3070](https://github.com/wagnerra23/oimpresso.com/pull/3070)); revisão 30-threads salva ([#3066](https://github.com/wagnerra23/oimpresso.com/pull/3066)).

### Colisão ADR 0294 (pendente — fix pronto, bloqueado por race)
`#3069` adiciona `0294-mcp-audit-log-hash-chain-tamper-evident.md` colidindo com `0294-metodo-dual-track` (já em main). **Decisão Wagner: (b)** — hash-chain é **T5 da 0294**, não ADR própria → remover o arquivo duplicado. Fix verificado verde (1 só 0294, `adr-index --check` passa) mas push rejeitado (branch viva). Quem estiver no `wt-3069` aplica:
```bash
git rm memory/decisions/0294-mcp-audit-log-hash-chain-tamper-evident.md
node scripts/governance/adr-index-generate.mjs --write
git add -A && git commit -m "fix(governance): remove ADR 0294 dup — hash-chain é T5 da 0294 (decisão b)"
```

## Próximos passos (pós reset de sessão 7:10pm BRT + árvore quieta)
1. Pausar as 5 sessões paralelas (única forma de drenar sem corrida).
2. Drenar a fila em ordem (#3069 colisão → #3075 → #2765) + matar o motor de conflito (parar de commitar `_INDEX-GENERATED` por-PR; alocar nº de ADR coordenado por slug, ADR 0274).
3. **PR-A do floor** (independe do swarm — read-side puro).
4. Construir a máquina de planos (gerador `plans-index` + sentinela `plan-health` + gate + canário) sobre a base assentada.

## Lição-mãe
6+ sessões de IA em paralelo na mesma superfície de governança, com strict-protection, produzem ADR/PR mais rápido do que dá pra serializar → colisão de número, índice em conflito par-a-par, treadmill de rebase. **IA paralela precisa de disciplina de fila tanto quanto de velocidade** — senão a ferramenta de organizar planos vira a fonte do caos.
