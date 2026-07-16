---
date: "2026-07-12"
topic: "Adversário formal sobre a grade de réguas do IA-OS — 9/11 notas sobrevivem, 3 números de máquina corrigidos, ranking refeito, 8 lições viram regra na skill"
authors: [W, C]
related_adrs: [0329-doutrina-documentacao-de-processo-executavel, 0330-mapa-dos-niveis-estado-real-2026-07-constituicao, 0333-emenda-0330-eixo-rodar-e-observar-submedido, 0105-cliente-como-sinal-guiar-sem-mandar]
---

# Sessão 2026-07-12 — Adversário formal sobre a grade de réguas (juiz: 9/11 mantidas, máquina e ranking refeitos)

**O que rodou:** workflow de 6 agentes (4 atacantes independentes — evidência · ranking · omissões · método — → 1 contraprojeto → 1 juiz), ~890k tokens, base FRESCA `origin/main @ 6c73f81a` (2026-07-12). Alvo: a grade de réguas do IA-OS emitida em prosa no mesmo dia (re-score manual sobre o retrato de mercado de 2026-07-10, sem workflow, sem refutador). Este log é o registro canônico exigido pela regra 6 da skill [`reguas-do-sistema`](../../.claude/skills/reguas-do-sistema/SKILL.md) — a grade original tinha ficado só em prosa.

## TL;DR — placar

- **Notas por dimensão: 9/11 sobreviveram** ao ataque. 2 corrigidas: `seguranca-do-agente` 7→**6** e `design-to-code` 7→**8**.
- **Números de máquina: 3/3 corrigidos** (composta SDD, "alerta 291", Module Grade Jana).
- **Ranking impacto÷esforço: refeito por inteiro** — dos 7 itens originais, 3 tinham premissa mecânica falsa e as 3 maiores alavancas do dia estavam AUSENTES (nightly OOM, distiller 0→6, Vaultwarden 30min).
- **Erro factual herdado corrigido:** client_signal=0 desde a [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (`decided_at: 2026-05-08`) = **~2 meses**, NÃO "14 meses". O erro nasceu no handoff 2026-07-10-1443 e a grade herdou sem checar (correção só em doc novo — handoffs antigos são append-only, ficam como estão).
- **Veredito do juiz — "o adversário projetou melhor?": SIM em números de máquina e ranking; em notas mudou pouco e certo** (as 2 mudanças têm evidência; retórica sem evidência não moveu nada).

## Correções de nota (com evidência do juiz)

| Dimensão | Original | Final | Fundamento |
|---|---|---|---|
| `seguranca-do-agente` | 7 (Δ+1) | **6** | Evidência citada (tag `business_id` no trace Langfuse, commit `85161085c6`) é auditabilidade multi-tenant — **fora do escopo** da régua da dim (injection + fronteira instrução-vs-dado + permissão de tools, `reguas-do-sistema.js:33`) **e dupla-conta** o mesmo commit já creditado em observabilidade. In-scope real do período: PR #4070 prompt-injection-corpus (advisory, não gate armado) + 6 hooks Tier 0 portados (~20 `.ps1` restantes, plano #4041). Volta a 7 quando o corpus virar gate armado ou a superfície de hooks for auditada. |
| `design-to-code` | 7 (=) | **8** | O "=" era cópia cega do retrato de 10/jul — entre retrato e grade shipou o dia mais denso do stream: charters **100% de cobertura** (~15 PRs #4123-#4142), detector **M1** de mudança de UI não-declarada (`42af777bc0` #4134) e **charter-live-signal** promovendo `/login` draft→live com 28 hits reais de prod (`d12eb1f2c3` #4153). Residual honesto: corretude do vínculo segue parcialmente grandfathered — 8, não 9. |

Mantidas com rótulo corrigido (nota fica, Δ cai):
- `observabilidade-agente` **7** — mas o Δ+4 era **correção de retrato stale**: os commits Langfuse (`511f901672`, `cc7ef583db`) são de **2026-07-02**, 8 dias ANTES do retrato-base de 10/jul (a ADR 0333 avaliou com premissa velha "#4 pendente"). Δ real ≈ 0/+1 (só a tag business_id é nova). Ressalva do juiz contra o contraprojeto: dispatch Langfuse em prod é **sync deliberado** fail-open 5s (loop-4 `done_nota`), não incidente de fila.
- `memoria-conhecimento` **7** — `applyTimeDecay` wired e testado (`MeilisearchDriver.php:194`, verificado), mas o código existe desde **maio/2026** (Onda 5); 12/jul foi reconciliação de doc (#4144). "Medido" é **falso** (NDCG@10 pendente por confissão do próprio SPEC). A dim carrega a única regressão armada da máquina: `distiller_freshness` 0→6, omitida na grade original.
- `inteligencia-de-negocio` **2 🔴** — a nota sobrevive intacta (cycles-active vazio, CYCLE-BI-01 em planning **de propósito** — "não fingir sinal"; Jana-BI só em teste). Só o fato "14 meses" cai (~2 meses). E a citação executável estava quebrada: **US-COPI-132 foi consumida** pela tag Langfuse no próprio 12/jul — retomar BI exige US com número NOVO (risco concreto: agente futuro "retoma" a 132 e mexe na tag Tier 0).
- `spec-governanca` **8**, `orquestracao-adversarial` **8**, `qualidade-drift-ia-producao` **5**, `evals-outcome` **5**, `erp-ia-produto` **5**, `custo-eficiencia` **3** — sobreviveram à verificação (valores brutos do scorecard batem 1:1 com `governance/sdd-scorecard.json`; `agent-cost-per-pr.mjs` ausente confirmado; recall 0,3839 real).

## Números de máquina corrigidos (os 3)

1. **"SDD composta 64,1 (Δ+14,1)"** → o 64,1 é **snapshot CT100 parcial (k=6 armadas) NÃO-reproduzível do repo**; o juiz recomputou a composta v1 dos arquivos versionados: **41,0 com k=7**. O gerador canônico (`sdd-scorecard.mjs:413`) **PROÍBE** compor com `not_yet_measured` (há 3). O composto honesto do dia é o do avaliador adversarial canônico: **69/100** ([session SDD](2026-07-12-sdd-avaliacao-adversarial-processo.md)) — sem delta, runs não-comparáveis por design. Três "compostas" circulavam no mesmo dia (64,1 · 69 · 41,0); a grade apresentou a menos honesta sem rótulo.
2. **"alerta full_suite_pass_rate=291"** → **falso**: 291 < baseline 298 = **MELHORA** (e pela lógica do `summarize()` nem gera alerta). O alerta armado real do estado versionado é **`distiller_freshness` 0→6** (única regressão armada), omitido por completo. Agravante: o próprio 291 vinha de nightly **morta por OOM** há 6 dias (`computed_at 20260706`) — valor E "melhora" de fonte congelada.
3. **"Module Grade Jana 73 (71→73)"** → o 73 vive **só em prosa de handoff**; o artefato versionado `governance/module-grades-baseline.json` segue **71**. Ler como "71 em máquina / 73 medição reportada, bump do baseline pendente" — se a catraca consome o baseline, o 73 não trava nada.

## Ranking final (refeito pelo juiz — 7 itens)

1. **Nightly CT100 verde**: validar a 1ª noite com junit sharded pós-#4166/#4172/#4183 e reabrir o burn-down P04 medível (autorizado por Wagner no próprio dia, ~80% pago; atacar primeiro a classe cascata-de-isolamento = 57% das falhas; R1 mantém relógio de 7+ noites). *Ausente da grade original.*
2. **LGPD prep**: dry-run `jana:retention-purge` em staging CT100 + evidence pack antes→depois + pedido formal de flip (código 100% pronto e agendado; só o flip+canary 7d é HITL; ~0,5d — melhor razão impacto÷esforço do lote).
3. **Chip Wagner — Vaultwarden 30min**: user claude-agent + API key em `/root/.vaultwarden-agent-creds`; `get-secret.sh` já deployado (#4165). Fecha o Tier 0 gap de segredos de 2026-05-28. *O gated mais barato do repo, ausente da lista GATED original.*
4. **Pagar `distiller_freshness` 0→6** — a ÚNICA regressão armada da máquina (destilar os 6 BRIEFINGs >7d; trilho KL-E3 já em uso, ex. commit `7b8a9f9e6a`).
5. **Jana-BI recall 0,38→0,60**: abrir **US com número NOVO** (132 consumida) + **spike 2h reabrindo a lane hybrid** em staging (recall@5=0,815 já medido) ANTES de decidir bipartir corpus+eval negócio≠processo — único caminho pro 🔴, impacto **condicional a 2 gates humanos** (Wagner → mão da Larissa → pergunta dela).
6. **Micro-wiring**: `drift_alarms` (1 linha `GH_TOKEN` no step Medir de `sdd-scorecard-publish.yml`) + `read_path_hops` (extrair a mediana que `knowledge-drift.mjs` já computa) — minutos-horas; `recall_eval_violations` vira task separada MÉDIA (write-side novo).
7. **custo/PR sobre cc-sessions/MCP** (não Langfuse — que traceia o custo do PRODUTO Jana por conversa, sem dimensão PR): espelhar `agent-pr-outcomes.mjs` com gaps declarados; advisory, impacto médio.

Grade final de notas: observabilidade 7 · segurança 6 · memória 7 · spec-governança 8 · orquestração-adversarial 8 · design-to-code 8 · qualidade-drift 5 · evals-outcome 5 · erp-ia-produto 5 · custo-eficiência 3 · inteligência-de-negócio 2 🔴.

## Veredito — o adversário projetou melhor?

**SIM no que importa, com ressalva** (palavras do juiz): em **números de máquina e ranking** o adversário foi claramente melhor — 3/7 itens do ranking original com premissa mecânica falsa verificada, 3 omissões maiores, composta não-reproduzível. Em **notas** mudou pouco e certo: 9/11 sobreviveram; o valor do adversário foi nos **rótulos** (quase todo Δ positivo era correção-de-retrato-stale, não capacidade nova do intervalo 10→12/jul). Onde o adversário NÃO foi melhor: 1 imprecisão própria (residual "represa na fila" — dispatch é sync deliberado), 1 exagero do atacante 1 ("recall-eval não roda em lugar nenhum" — roda semanal via Kernel), e o único 🔴 + a leitura estratégica central (recall→Larissa→sinal) a grade original **já tinha acertado** — o adversário refinou, não descobriu.

Modo de falha raiz (comum a quase tudo): **a grade foi escrita em 12/jul sem reconciliar com os merges do próprio 12/jul** — o dia mais denso do trimestre. As 8 lições do juiz viraram regras 8-15 + anti-padrões na skill [`reguas-do-sistema`](../../.claude/skills/reguas-do-sistema/SKILL.md) (emendada neste mesmo PR).

## Pendência estrutural (decisão de formato pra Wagner — NÃO implementada aqui)

**As notas por dimensão de cada retrato precisam de artefato VERSIONADO no repo.** Hoje vivem em artifact privado claude.ai → todo Δ futuro é **inauditável** (foi exatamente o que permitiu o Δ+4 fabricado de observabilidade passar 2 dias sem contestação). Proposta de caminho (formato a decidir): `memory/reguas/YYYY-MM-DD-notas.json` — 1 arquivo por retrato com `{dim, nota, evidencia_chave, retrato_base}`, gerado pelo próprio workflow na fase de registro, consumível por recompute (mesmo padrão do `governance/sdd-scorecard.json`). Alternativas: bloco YAML no próprio session log de réguas, ou entrada em `governance/`. **Não criei o mecanismo** — formato e localização são decisão do Wagner (pareia com a regra 12/lição v da skill emendada).

## Erratas factuais consolidadas (valem daqui pra frente)

- client_signal=0 **desde 2026-05-08 (ADR 0105) = ~2 meses** — não "14 meses". Origem do erro: handoff 2026-07-10-1443 (append-only, não editar; corrigir sempre em doc novo).
- **US-COPI-132 = tag `business_id` no trace Langfuse (done 12/jul)**. Descongelar Jana-BI exige US com número novo.
- Composta SDD: **não citável como "/100 do sistema"** enquanto houver `not_yet_measured`; citar o composto adversarial do dia (69/100) ou a composta v1 rotulada com k=N + proveniência.
