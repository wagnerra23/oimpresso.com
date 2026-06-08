# CHARTER — Governança [CC] × Jana (rumo a 9.7)

> **Proposta §10.4.** [W] numera/ratifica se promover a ADR (soberania, 0238). Espelha a **conclusão** do report `rep-cc-vs-jana` (`metricas.html`, Cowork) pro git — pro **raciocínio** (não só os números do scorecard) ficar durável e ser lido pelo [CL]/próximo [CC]. Irmão de `CHARTER_GOVERNANCA_W.md` + `CHARTER_CHAMPION_AGENTES.md`.
> **Frescor:** medido vs origin/main 74bc2ea · 2026-06-03. Benchmark re-checado vs estado-da-arte 2026.

## A tese (o que fica decidido)
Duas governanças, **não a mesma corrida**:
- **[CC]** = governança de **julgamento + memória** (ADR append-only, lições, soberania [W], "propõe nunca aplica").
- **Jana** = governança de **operação + runtime** (gates bloqueantes, observabilidade/OTel, RAGAS, LGPD).

Não competem; especializam. O ganho não é uma vencer — é **cada uma emprestar força à outra**, no mesmo cano (`jana:health-check`, que já hospeda os checks dos dois).

## A métrica (contável, não opinião)
**9.7 = razão de graduação** = lições graduadas em check rodável ÷ total, nos **dois** ledgers.
Estado 06-03 (verificado @main): **Jana 3/3 (100%)** · **[CC] ~0/25 (~0%)** · combinado **≈11%**. Fonte do número = `storage/reports/governanca-scorecard.json` (quando a ponte `governanca:scorecard` mergear).

## O caminho (dois degraus)
- **9.5 — paridade** (copiar a execução do estado-da-arte): prosa→executável (graduar as ~25 lições [CC] em hooks: 0%→100%) · política contextual com rationale (Policy-as-Prompt, 2509.23994) · memória auto-gerida + bi-temporal (Zep/Graphiti, 2501.13956) · eval-as-CI + drift simulado · ligar observabilidade (CT 100, infra [W]).
- **9.7 — vantagem** (a categoria que os silos não ocupam): **fundir os 2 ledgers num cano só** — ledger→MEC(check)/JULG(regra)→gate no mesmo health-check, pra erro de design E de operação. Ancorado em **self-evolving agents** (2507.21046) e **mnemonic sovereignty** (2604.16548): a fronteira 2026 migrou de *recall* pra **governo da memória** — o eixo onde [CC]+Jana já lideram (ADR append-only + soberania [W] + retention LGPD).

## Anti-regressão (o +0.2 que nenhum líder reivindica)
Se **toda** lição é obrigada a graduar num check rodável, o piso não pode cair pro mesmo erro: "REGRESSÃO É INACEITÁVEL" (STATUS §5) vira **propriedade demonstrável**. O survey de memória 2026 (2603.07670) põe "evitar repetir erros caros" como função central — mas ninguém citado *prova*; este loop pode.

## Como se mantém vivo (3 camadas, anti-prosa)
1. **Frescor** por re-sync ([CC] re-deriva do `main` a cada sessão).
2. **Medir** — nota vira número (`governanca-scorecard.json`).
3. **Mecanizar** — check `governanca_graduation_ratio` regenera o placar sozinho.

## Gap aberto — frescor do PROTOCOL (responsabilidade)
O PROTOCOL **defasa** quando `casos.md`/tests acumulam sem serem refletidos. **Lei = [W]** (Tier 0). **Detecção = mecanismo que falta** (`protocol_freshness` — acende quando há casos/tests sem referência no PROTOCOL); hoje é responsabilidade de ninguém = o bug. **Proposta de reconciliação = [CL]** (§10.4, validável contra git) ou [CC] (se design). Precedente: [CL] já reconciliou PROTOCOL §2 stale (CODE_NOTES 05-31).

## Benchmark (re-checado vs estado-da-arte 2026 — fontes citáveis)
Voyager (2305.16291) · Policy-as-Prompt (2509.23994) · Zep/Graphiti bi-temporal (2501.13956) · self-evolving agents survey (2507.21046) · memória de agente (2603.07670) · mnemonic sovereignty (2604.16548). **Descartados por não-verificáveis:** "Atlan 2026", "CONSECA".

## REPROVADO (anti-pattern)
- Placar de governança **digitado à mão** (prosa que envelhece) — tem que vir do scorecard.
- Afirmar nota sem fonte citável (`⚠ inferido` proibido — L-26/27).
- Tratar 9.7 como "mais máquina" — é fusão + graduação, não volume.
