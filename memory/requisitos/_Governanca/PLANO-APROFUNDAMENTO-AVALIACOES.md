---
slug: plano-aprofundamento-avaliacoes
title: "Plano de aprofundamento das avaliações — 5 ondas (execução IA-pair, tier da sessão)"
date: "2026-07-05"
status: proposto
authors: [F, C]
related_adrs: ["0093", "0101", "0105", "0106", "0155", "0230", "0264", "0275", "0294", "0314"]
esforco_estimado: "5 ondas · ~4-6 sessões IA-pair (ADR 0106) · Onda 4 exige CT100 · Onda 5 gated Wagner/Eliana"
topic: "programa de aprofundamento das lentes de avaliação do projeto — telas stale, módulos piores, segurança, ops/DR, LGPD/performance"
---

# Plano de aprofundamento das avaliações — execução por sessão IA-pair (tier da sessão)

> **Origem (2026-07-05, Felipe [F] + Claude):** validação das máquinas de governança de conhecimento (grade ~74/100) expôs que as lentes existentes NÃO cobrem o projeto inteiro. Lentes vivas: SDD 70/100 · Jana RAG ~46% · module-grade média 76.7 (36 módulos) · 17 CAPTERRA-FICHA · RAGAS canary. Buracos sem lente: **segurança ofensiva, ops/DR, performance prod, LGPD dedicada** — e 217 screen-scorecards STALE (Check B).
> **Este doc é o contrato de execução**: cada onda tem pré-reqs, passos, DoD verificável por sentinela e gate humano. Sessão executora marca progresso AQUI (bump `reviewed_at`) — fonte única, sem roadmap paralelo (regra T6).

## Status vivo

- status: proposto
- reviewed_at: 2026-07-05
- proximo_passo: Wagner aprova a ordem das ondas; o executor (qualquer tier de sessão) começa por Onda 0 (baseline) depois Onda 1.
- adversário: red-team deste plano em [`memory/sessions/2026-07-05-adversario-plano-aprofundamento.md`](../../sessions/2026-07-05-adversario-plano-aprofundamento.md) — LER antes de criar qualquer doc de avaliação (evita re-duplicar este plano · regra T6).
- 2026-07-05 (C): de-pinado de "Opus 4.8" → tier da sessão (Fable 5 é o tier GA mais alto; ver Regra global #9 + adversário).

## Máquina de cobertura (construída nesta sessão)

Enquanto este plano é executado, o **Check X** do `memory-health` (`scripts/governance/memory-health.mjs`) responde mecanicamente a cada PR "isso está auditado?": flaga módulo Tier-0 OU nota module-grade < 70 sem NENHUM `AUDIT*.md` no dir de requisitos. Hoje aponta **1** — `PaymentGateway` (nota 60, Tier-0). É o DoD vivo da Onda 3: a onda fecha ⟺ o Check X zera. Advisory, determinístico, zero-FP (teste físico em `tests/memoryHealth.spec.ts`).

## Regras globais pra sessão executora (LER ANTES DE QUALQUER ONDA)

1. **Pré-flight de sessão:** `brief-fetch` primeiro (Tier A). Base fresca: trabalhar A PARTIR de `origin/main` (`git fetch` + branch nova) — nunca de checkout stale (guard SessionStart).
2. **Gates humanos:** merge = Wagner (R10; escopo aprovado corre até o fim — R11). `tasks-create` em lote = humano confirma 1× (publication-policy). Nenhuma onda auto-cria task.
3. **Tier 0 sempre:** `business_id` global scope; Pest/PHPStan **só no CT100** (nunca local/Hostinger); smoke com biz=1, NUNCA biz=4; sem PII/valores BRL em git; evidência literal (curl/screenshot) antes de declarar "pronto".
4. **Commit-discipline:** 1 PR = 1 intent, ≤300 linhas, conventional commit PT-BR, `Refs:` da US quando existir.
5. **Paralelização:** padrão `how-trabalhar.md` §"Paralelização N agents" — áreas isoladas por agent, zero git ops nos agents, parent consolida.
6. **Anti-duplicação (T6):** antes de criar doc novo em `memory/`, `Glob`/`Grep` o tema e **estender** o canon existente — nunca abrir paralelo.
7. **Toda onda entrega catraca**, não só relatório: senão o gap volta invisível (lição ADR 0264/0275 — sentinela conta, catraca morde).
8. Ordem recomendada: **Onda 0 → 1 → 2 → 3** (1-3 independentes, 1 é a mais barata). Onda 4 só em sessão com Tailscale/CT100. Onda 5 só com OK Wagner/Eliana.
9. **Modelo/tier — NÃO force Opus.** Fable 5 é o tier GA mais alto (acima de Opus); qualquer sessão executa este plano no seu próprio tier — não troque de modelo pra "subir". ⚠️ Os agentes `capterra-senior` / `audit-senior-expert` / `estado-da-arte` têm `model: opus` **hard-pinned** no frontmatter (`.claude/agents/*.md`) → cada `Agent(...)` deles **troca a sessão pra Opus** em runtime. Pra manter no tier da sessão, passe o override `model: fable` no `Agent(subagent_type: "capterra-senior", model: "fable", ...)` — o override tem precedência sobre o frontmatter e preserva o system-prompt/skill do agente. Só deixe cair pra Opus se um passo específico justificar (não é o caso de nenhuma onda aqui).

---

## Onda 0 — Baseline consolidado (½ sessão, pré-req)

**Objetivo:** um único doc que junta todas as notas atuais, pra não re-medir o que já está medido.

**Passos:**
1. Colher notas vivas: `module:grade --all --json` (CT100) · `governance/sdd-scorecard.json` · `governance/jana-ragas-baseline.json` · `node scripts/qa/screen-grade-report.mjs` · `node scripts/governance/memory-health.mjs`.
2. Escrever `memory/governance/BASELINE-QUALIDADE-2026-07.md` — 1 tabela: lente · nota · fonte · frescor · dono. **Sem valores BRL.**

**DoD:** o doc baseline existe com as 6 lentes (module-grade, SDD, Jana RAG, RAGAS, screen-grade, memory-health) com nota + data + link de fonte.
**Esforço:** ½ sessão.

---

## Onda 1 — Re-grade das telas stale (Check B: 217 scorecards)

**Objetivo:** zerar (ou reduzir a <50) os scorecards com `graded_at` anterior à última mudança da tela — a lente de UX existe, está só velha.

**Pré-reqs:** nenhum. Ferramentas já existem: skill `screen-grade` (16 dims, score-as-code YAML), `scripts/qa/screen-grade-report.mjs`, `scripts/qa/screen-grades-ratchet.mjs`, scorecards em `memory/governance/scorecards/*.yaml`.

**Passos:**
1. `node scripts/qa/screen-grade-report.mjs` → lista das telas stale com módulo + data. Salvar a lista como work-list da onda.
2. Priorizar por Peso Real: 1º **Sells/POS** (biz=4 = 99% volume), 2º **Financeiro**, 3º **Fiscal/NfeBrasil**, depois cauda.
3. Re-gradear em lotes de ~10-15 telas por sessão/agent (fan-out paralelo por MÓDULO — áreas isoladas). Cada agent: roda o Pré-Flight da skill `screen-grade`, re-pontua as 16 dimensões, atualiza o YAML (`graded_at` + notas + gaps).
4. Se uma nota CAIU vs baseline: NÃO maquiar — registrar o gap como achado (vai pro batch de tasks da onda, humano-gated).
5. `node scripts/qa/screen-grades-ratchet.mjs` verde antes de cada PR. 1 PR por lote (docs/YAML only, ≤300 linhas).
6. Ao final: rodar `node scripts/governance/memory-health.mjs` e colar o count do Check B no PR final.

**DoD (verificável):** Check B do memory-health reporta **<50** scorecards stale (medição, não promessa) + ratchet verde + lista de notas-que-caíram entregue a Wagner como proposta de batch tasks.
**Esforço:** ~1-2 sessões IA-pair (fan-out).

---

## Onda 2 — Aprofundar os piores módulos: Compras (58) e PaymentGateway (60)

**Objetivo:** os dois piores do baseline são justamente onde mora dinheiro/estoque (REGRA MESTRE). Sair de nota-fria pra diagnóstico profundo + backlog priorizado.

**Pré-reqs:** ler `memory/requisitos/Compras/SPEC.md` + `PaymentGateway` (⚠️ ADR 0170 status "later" — docs only) + proibições §REGRA MESTRE valor/estoque ANTES de qualquer Edit.

**Passos:**
1. **Compras:** `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan module:grade Compras --detail --evolve"` → dimensões D1-D9 + top gaps.
2. Spawn agent `capterra-senior` pra Compras (com override `model: fable` — Regra global #9, não trocar pra Opus) → `memory/requisitos/Compras/CAPTERRA-FICHA.md` (10 seções, nota 0-100 vs 10-15 concorrentes).
3. `/comparativo Compras` → CAPTERRA-INVENTARIO (✅/🟡/❌) + batch tasks proposto → **Wagner aprova antes de criar**.
4. Repetir 1-3 pra PaymentGateway **respeitando o status ADR 0170** (se seguir "later phase=2", entregar só o diagnóstico — não implementar). Isto também zera o Check X (cobertura de auditoria) pra PaymentGateway.
5. **Qualquer fix que toque cálculo/estoque em Compras:** REGRA MESTRE — dupla confirmação por 2 caminhos independentes + tabela antes→depois + aprovação Wagner explícita. Sem exceção.

**DoD:** ficha + inventário + batch proposto pros 2 módulos; Check X zera; re-grade pós-fixes mostra Compras ≥70 (meta) SEM label de regressão em outros módulos.
**Esforço:** ~1-2 sessões.

---

## Onda 3 — Auditoria de segurança (única área Tier-0 sem lente nenhuma)

**Objetivo:** primeira auditoria ofensiva do app inteiro. Guards pontuais existem (multi-tenant gates, gitleaks, XSS ratchet, PII scan); nunca houve revisão de superfície completa.

**Pré-reqs:** autorização Wagner explícita pro escopo (é o dono; pentest de app próprio = defensivo). Sem testes contra prod que gerem carga/escrita — análise de código + staging CT100. Base: skill `/security-review` + agente `audit-senior-expert` por dimensão (com override `model: fable` — Regra global #9). **Baseline existente (não é lente zero):** `memory/audits/2026-05-pre-sales/03-security-review-quick.md` (2026-05-09) já achou **A-1 Critical** (`POST /install/install-alternate` público → wipe da DB de prod) — 1º item da onda é confirmar se A-1 foi corrigido; ver adversário.

**Escopo (checklist do auditor):**
- Auth/session (login, remember, reset, 2FA se houver) e fixação de sessão.
- **Isolamento multi-tenant** (o risco nº 1): varrer `withoutGlobalScopes` sem `// SUPERADMIN:`, queries raw sem `business_id`, IDs sequenciais em rotas (IDOR cross-tenant) — provar com Pest cross-tenant biz=1 vs biz=99 pros achados.
- 260+ permissions Spatie: rotas sem `can()`/middleware; escalada horizontal role#biz; superadmin sem 2ª barreira.
- Injeção: `DB::raw`/`whereRaw` com input, XSS Inertia (`dangerouslySetInnerHTML`), SSRF nos fetchers externos (Asaas/Inter/WhatsApp/SEFAZ).
- Entradas externas: uploads (`Modules/Arquivos`), webhooks (idempotência/replay), APIs públicas (ConsultaOs/ConsultaNfe).
- Segredos: token em código/log, `.env` versionado, MCP exposto no Hostinger (proibicoes.md).
- Fiscal/pagamento: replay de webhook, refund sem flag, valor manipulável no submit (REGRA MESTRE).

**Verificação adversarial:** cada achado alto/crítico passa por 1 refutador (é explorável de verdade? caminho concreto?) — descartar teórico-não-explorável.

**Máquina (catraca):** pra cada classe confirmada, propor 1 gate advisory determinístico (estender o gate de `withoutGlobalScopes` sem comentário; novo gate "rota sem middleware auth"). Registrar em `gates-registry.json` com `promote_by`.

**DoD:** `memory/requisitos/_Governanca/AUDITORIA-SEGURANCA-2026-07.md` — nota 0-100 + achados por severidade (CVSS-like) + PoC de cada crítico + tasks propostas; todo crítico com caminho provado OU refutado; ≥1 catraca proposta.
**Esforço:** ~2-3 sessões.

> ⚠️ Ético/escopo: auditoria **defensiva** do próprio código. Sem atacar prod ao vivo, sem DoS. Leitura de código + no máximo smoke em staging CT100.

---

## Onda 4 — Infra / Ops / DR (CT100 + Hostinger) — exige sessão com Tailscale

**Objetivo:** zero grade hoje, e já houve incidente silencioso (handoff SDD: cópia `/opt` do CT100 desatualizada = 3 noites de floor morto; SSH Hostinger flaky). Ninguém mediu backup/restore.

**Pré-reqs:** sessão com acesso Tailscale ao CT100. Nunca tocar prod pra "testar" — drill em staging.

**Passos:**
1. **Inventário** (read-only): o que roda onde (ADR 0062). Mapear crons (`app/Console/Kernel.php` + `cron-watchdog.mjs`), daemons (Centrifugo/FrankenPHP/Baileys), SPOFs.
2. **Drill de restore** (staging CT100, nunca prod): existe backup do DB? Restaura? RTO/RPO real? Documentar gap se não houver.
3. **Sync canon↔servidor:** o `/opt` do CT100 bate com `origin/main`? (o incidente). Propor catraca "cópia deployada == HEAD canon" (estende `cron-watchdog`/`baseline-tamper-guard`).

**DoD:** `memory/requisitos/Infra/AUDITORIA-OPS-DR-2026-07.md` — nota + tabela SPOF + gap backup/DR + 1 catraca de drift deploy↔canon; drill de restore tentado (sucesso OU gap honesto).
**Esforço:** ~1-2 sessões (CT100).

---

## Onda 5 — LGPD dedicada + Performance prod (gated Wagner/Eliana)

**Objetivo:** duas lentes finais — compliance e escala — hoje só existem como dimensão do module-grade.

**5a — LGPD (gated Eliana, advogada+financeiro):** inventário de PII por tabela, retenção/consent, `PiiRedactor` cobre os fluxos? DPO formal está adiado (regras-time.md) — entregar só o mapa + gaps, não decisão jurídica.
**5b — Performance:** baseline p95/p99 por rota do OTel/Jaeger CT100 (7-30d) → top-10 telas lentas; checar N+1 (`paginate(` sem `->with(`) e `Inertia::defer` nas props caras (skill `inertia-defer-default`).

**DoD:** `AUDITORIA-PERFORMANCE-2026-07.md` com baseline p95 por tela + 5 piores ofensores N+1 com fix proposto; mapa LGPD entregue a Eliana. **Sem valores BRL.**
**Esforço:** ~1 sessão (perf) + gate Eliana (LGPD).

---

## Ordem e esforço (IA-pair, ADR 0106)

| Onda | Tema | Prioridade | Esforço | Gate |
|---|---|---|---|---|
| 0 | Baseline consolidado | pré-req | ½ sessão | — |
| 1 | Re-grade telas stale (Check B 217) | 3 (barato/alto valor) | 1-2 sessões | ratchet |
| 2 | Compras(58)+PaymentGateway(60) | 2 (Tier-0 fraco) | 1-2 sessões | Wagner (batch) |
| 3 | Segurança multi-tenant | **1 (Tier-0, zero lente)** | 2-3 sessões | Wagner (escopo) |
| 4 | Ops/DR CT100+Hostinger | 4 (incidente real) | 1-2 sessões | CT100 |
| 5 | LGPD + Performance | 5 | 1 sessão + Eliana | Eliana |

**Quick-win se o tempo for curto:** Onda 1 (½-1 sessão, refresca 217 telas) + começar Onda 3 (o único Tier-0 sem lente). O Check X já vigia a Onda 2/3 a cada PR.
