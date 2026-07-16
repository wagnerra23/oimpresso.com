# Handoff 2026-07-10 14:43 — Fase Integração na grade + adversário confirma atrofia da inteligência de negócio (ADR 0334) + CYCLE-BI-01

**Sessão:** `ecstatic-taussig-e2a4df` (worktree stale −5001; TODO commit partiu de `git checkout -B … origin/main` no worktree `reguas-base-fresh`). **Off-cycle.** **Base:** `origin/main` fresco a cada PR.

## TL;DR

Wagner pediu "rode a grade de réguas" → o arco descobriu que a própria grade tinha 2 furos, consertou os dois, e no meio disso Wagner levantou a suspeita central da sessão — *"posso ter perdido minha inteligência de negócio pra me adequar ao mercado"* — que um adversário formal **confirmou**. Loop inteiro fechado: **MEDIR → CORRIGIR → TRAVAR → RE-MEDIR** (a régua consertada se auto-validou).

**5 PRs MERGED:**
1. **#4074** — Fase **Integração** no `reguas-do-sistema.js` (+ 7ª regra dura na skill + §5). Mata o "**0 acima falso**": o refutador julga slice-a-slice → falácia de composição (soma de partes com peer ≠ todo com peer). Novo veredito `DIFERENCIAL_SISTEMA` (à-frente-por-integração) vs `REFUTADO_TB`.
2. **#4077** — **ADR 0334** (modelo 3-camadas A ERP · B Jana-BI · C IA-OS + invariante anti-atrofia) + alarme `negocio-vs-governanca-ratio.mjs` (self-test 15/15) + **3º eixo da grade `SERVIR-O-NEGÓCIO`** (a régua que estava cega).
3. **#4078** — ratifica 0334 `proposto→aceito` (label `adr-metadata-normalization`; "aceito" [W] = ato).
4. **#4080** — fix `args.base` tolera string OU objeto (o tool Workflow serializa args; bugou 2×, agentes se auto-curavam lendo origin/main na mão) + corrige minha própria nota ERRADA na skill ("passe como objeto" era o oposto do problema).

**Adversário `adversario-inteligencia-negocio` (8 agentes, verificado):** SIM, perdeu — **2 PERDA_FORTE, 3 PARCIAL, 0 sem-perda**. Diagnóstico do juiz (refutou a rebatida da defesa: os "293 docs de negócio" eram ilusão do commit de restauração `8cd20a3486`; authoring real do mês = **3 SPEC, 0 domínio**): **não é atrofia do músculo** (Martinho/OficinaAuto biz=164 dispara com cliente pagante) — **é atrofia do NERVO**. Evidência: `client_signal` = **0 ocorrências** 14 meses após ADR 0105; **nenhum cycle ativo**; `SaleInsightAgent` congelado no #1040; memória **80:1** processo:negócio; escopo governança **8×** no crossover de junho (alarme confirma: mai 38% → jun 64% → jul **78%**).

**Re-avaliação (77 agentes) validou as 3 correções:** placar 0 acima-de-categoria + **8/8 à-frente-por-integração** (sem "0 falso"); creditou **11 gaps já shipados**; o eixo novo deu nota **2 🔴** ao `client_signal`; gerou **5 lápides novas pro §5** sozinha.

## Recuperação teed up (o próximo esforço é camada A/B, não mais governança)

- **CYCLE-BI-01** criado (planning, 10→24/jul): goal canon (Larissa recebe resposta da Jana com dado real) + métricas `jana_bi_context_recall 0.38→0.60` e `client_signal 0→≥1`.
- **US-COPI-132** (p0, 12h, owner wagner): descongelar a Jana-BI. **Insight-chave (do adversário):** o recall é baixo porque a Jana-BI recupera do índice de **processo**, não de **fatos de negócio** → bipartir o corpus (ADR 0334 §4). Guard-rails Tier-0 escritos (pré-flight, CT 100, cross-tenant biz=4, dupla-conferência R$, não-ir-pra-prod-sem-validação-Wagner).
- **Wagner esclareceu:** a Jana-BI **"ainda não coloquei pra ela usar, está só em teste"** → o nervo nunca disparou porque o produto nunca chegou na Larissa (e está em teste porque recall 0.38 é baixo demais pra confiar dado de negócio ao cliente — classe do R$ inflado). Caminho: confiabilidade → mão da Larissa → perguntas dela viram o `client_signal`.

## Estado MCP no momento do fechamento

- `cycles-active` COPI → **"Nenhum cycle ATIVO"** (CYCLE-BI-01 está `planning`, de propósito — a métrica `client_signal≥1` só faz sentido depois que a Larissa usar; não fingir sinal).
- `my-work` @wagner → **30 tasks** (9 review, 8 blocked, 13 todo). **FIN-4 (FIN-004 cobrança ROTA LIVRE) = blocked** (HITL). US-COPI-132 nova (todo).
- ADRs aceitas no intervalo: **0334** (esta sessão).
- Artifact navegável da grade (rev.2, com diferenciais recuperados): publicado (privado claude.ai).

## Próximo (sessão LIMPA — Jana é Tier-0)

1. **US-COPI-132 em sessão fresca:** pré-flight `Modules/Jana` → bipartir corpus (índice de negócio ≠ processo) → descongelar `SaleInsightAgent` → medir recall antes→depois **no CT 100** (nunca local). Não scopear Jana no fim de sessão saturada.
2. Depois da confiabilidade: cruzar test→prod (colocar na mão da Larissa) → ativar CYCLE-BI-01 quando o `client_signal` começar a fazer sentido.
3. Backlog de recuperação restante (ADR 0334 §Consequências): bipartir corpus formal · descongelar wr-comercial (415 arquivos, 26 anos, 0 commits desde 09/jun) como camada viva pro Vestuário.

## Lições

- **Refutar por slices fabrica "0 acima" falso** (falácia de composição) — grade precisa de teste de integração (§5, #4074). O diferencial do oimpresso é a **montagem recursiva** das peças num ERP em prod, não peça-de-categoria (nenhuma sobrevive isolada).
- **Documentar sem testar falha** — minha nota "passe args como objeto" estava errada (o tool serializa pra string); só o teste real pegou (#4080). Exemplo vivo da doutrina 0329.
- **A cura da atrofia não é mais régua** — é produto (A/B) guiado por sinal de cliente. Toda esta sessão foi camada C; o próximo passo (US-COPI-132) é o primeiro A/B.
- **Sinal antes de confiabilidade = mostrar faturamento errado ao cliente.** A ordem é: confiável → na mão dela → sinal.
