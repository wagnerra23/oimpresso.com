# PROMPT PARA CLAUDE CODE — ONDAS DE QUALIDADE Q1–Q5 (fechar os elos abertos da governança)

> **Mandato [W] 2026-06-11:** "o que tem que ser feito, gere as ondas que o code precisa criar."
> **Origem [CC]:** proposta §10.4 — você valida TUDO contra `origin/main` antes de agir (Passo 0).
> Se algo daqui já estiver feito no `main`, marque SUPERADO em CODE_NOTES e pule — não refaça.
>
> **Premissas verificadas pelo [CC] @main em 2026-06-11** (✓lido no turno):
> - `.github/workflows/e2e-gate.yml` = ainda `workflow_dispatch`, NÃO-required. Harness JÁ estabilizado (MySQL 8 service + schema-squash + VisregTenantSeeder + OficinaAutoFsmSeeder + `/_visreg-login`). O próprio header do yml diz: 2 runs verdes seguidos → flip `pull_request` + required (ADR 0264 T2).
> - `scripts/casos-test-results.json` = manifesto com **5 UCs pass** (gerado 2026-06-10). G-7 tem a 1ª prova; cobertura ainda mínima.
> - ⚠ inferido (STATUS [CC] 06-09, validar no Passo 0): dicionário de domínio só `memory/dominio/oficina-auto.md`; `casos-coverage-baseline.json` com dívida legada grande; `visual-regression` ainda stub; `governance-drift.yml` + `memory-health.yml` existem mas conteúdo não-auditado pelo [CC].

## Regras transversais (valem pra TODAS as ondas)

1. **1 onda = 1 sessão sua, PRs pequenos e reversíveis.** Termina com: prova anexa (run-ID/número/screenshot) + entrada em `CODE_NOTES.md` + `SYNC_LOG.md`.
2. **Gate novo nasce ADVISORY → 2 verdes → required** (faseado, lição ADR 0261 — gate instável não trava merge de ninguém).
3. **Todo check é provado dos DOIS lados** antes de virar lei: visto FALHAR num bug sintético (sensibilidade) E PASSAR num caso legítimo (especificidade). Check de 1 lado só = inválido.
4. **Estender, nunca recriar** (REGRA 7): antes de criar script/gate, grep em `package.json` scripts + `scripts/` + `.github/workflows/`.
5. **Não cunhar nº de ADR** (soberania [W], ADR 0238). Decisão estrutural nova = `memory/decisions/proposals/<slug>.md`; [W] numera.
6. **Mudança em branch protection / required = preparar tudo e pedir o 1 clique de [W]** se exigir admin. O resto: merge autônomo se CI verde e não-Tier-0.

---

## ONDA Q1 — G-3 vira lei: E2E Playwright required

**Por quê:** é o único gate que cobra COMPORTAMENTO (a tela funciona), não estrutura. Enquanto for manual, todo "✅ funciona" é declaração.

1. Disparar `e2e-gate.yml` (workflow_dispatch) 2× seguidas. Verde estável?
   - Flaky → consertar a causa ANTES de flipar (nunca retry-até-passar).
2. Com 2 verdes: flip `on: workflow_dispatch` → `on: pull_request` com `paths:` (`resources/js/**`, `Modules/**`, `routes/**`, `app/**`, `database/**`, o próprio yml) + manter `concurrency` + timeout.
3. Promover a required no branch protection junto de `casos-gate`/`dominio-gate` (se exigir admin → deixar pronto + pedir 1 clique [W]).
4. **Prova:** 2 run-IDs verdes + 1 PR sintético com UC quebrado sendo BLOQUEADO + 1 PR inocente passando.

## ONDA Q2 — G-7 honesto em todo lugar + ratchet de cobertura de casos

**Por quê:** hoje 5 UCs têm veredito real; o resto dos `Status: ✅` em charters é não-verificado. "✅ sem prova" é a mentira mais cara do desenvolvimento com IA.

1. Validar (Passo 0) que `casos:check` marca todo UC `✅` SEM entrada no manifesto como `unverified` — se não marca, fazer marcar (advisory primeiro).
2. Ratchet de cobertura: `casos-coverage-baseline.json` vira baseline só-desce; meta = zerar tela a tela, começando pelas P0 (Sells/Create, Sells/Index, Financeiro/Unificado, OficinaAuto/Board).
3. +E2E dos próximos UCs críticos do fluxo de negócio (o fio que [W] quer proteger: **venda → estoque → faturamento → caixa**):
   - venda balcão Sells/Create (criar venda a prazo)
   - título a receber gerado e baixado (Financeiro)
   - emissão NF-e/NFS-e em homologação (mock/stub SEFAZ, o wire já existe)
   - O Pest `RetencaoLoopE2ETest` já prova a cadeia no backend — espelhar os UC-IDs dele nos charters pra contar no manifesto.
4. **Prova:** manifesto com ≥10 UCs reais · baseline desceu (números antes/depois) · zero `✅` não-verificado nas telas P0.

## ONDA Q3 — Dicionários de domínio: estoque, faturamento/fiscal, vendas, financeiro, compras

**Por quê:** é a defesa anti-alucinação de domínio (a classe de erro da "locação fantasma" da Oficina, L-38). [W] vai construir estoque+faturamento agora — o dicionário tem que existir ANTES das telas.

1. Replicar o padrão `memory/dominio/oficina-auto.md` para 5 domínios: `vendas.md`, `financeiro.md`, `fiscal-faturamento.md`, `estoque.md`, `compras.md`.
2. Conteúdo grounded no CÓDIGO real (grep models/controllers/UltimatePOS), não de cabeça: termos canônicos PT-BR, sinônimos proibidos, conceitos-chave (ex.: faturamento = emissão fiscal + título, nunca só "nota"; estoque: movimento/reserva/baixa/inventário conforme o que o schema realmente tem).
3. `dominio-gate` passa a cobrar os 5 (mesmo faseamento: advisory → required).
4. **Prova:** gate verde nos 5 + 1 violação sintética pega + 1 termo legítimo passando (2 lados).

## ONDA Q4 — Gate visual de pixel deixa de ser stub (US-GOV-013)

**Por quê:** regressão visual hoje só é pega por olho humano — [W] como detector é anti-padrão (L-38).

1. Reusar o harness provado do e2e/visual-regression (boot idêntico) para capturar baseline PNG das telas núcleo-6 (Financeiro/Unificado, Compras, Cliente, OficinaAuto, Sells Index+Create).
2. Diff com threshold (pixelmatch ou similar já no repo? Passo 0) — diff acima do limiar = 🔴 com update-de-baseline consciente (`npm run visreg:update`, nunca automático).
3. Faseado: advisory → required.
4. **Prova:** 1 mudança visual sintética pega + 1 PR inocente verde (2 lados) + baseline commitada.

## ONDA Q5 — Meta-gates: o processo se autocobra (sobrevivência no tempo)

**Por quê:** regra que ninguém cobra morre (foi assim que o DESIGN.md §16.2 virou mentira).

1. **Auditar primeiro** (Passo 0): `governance-drift.yml` + `memory-health.yml` já existem no main — ler o que cobrem ANTES de criar qualquer coisa.
2. Registry canônico de gates + meta-test: workflow novo tocando `resources/**`/`Modules/**` fora do registry = 🔴 (pega gate novo mecanicamente).
3. Check de frescor: doc-cache com carimbo `✓lido @main <data>` (censo de gates, tabelas derivadas) >14 dias = amarelo no health-check.
4. Check `licao_sem_assercao` repo-side: lição nova em `memory/LICOES_CC.md` sem apontar G#/IT#/gate ou `não-mecanizável:` = amarelo.
5. **Prova:** controle-negativo 2 lados de cada meta-check.

---

## Ordem e gatilhos

```
Q1 (E2E required)  ──→  Q2 (G-7 + ratchet)  ──→  Q3 (dicionários)  ──→  Q4 (visual)  ──→  Q5 (meta)
     destrava a              honestidade            protege estoque/      mata o último     mantém tudo
     prova de                de TODO ✅              faturamento que       detector-humano   vivo no tempo
     comportamento                                  [W] vai construir
```

- Sem novo pedido de [W] entre ondas: terminou Q-n com prova → segue pra Q-n+1.
- Travou em decisão Tier 0 (required flip, baseline visual, prioridade) → entrada curta em CODE_NOTES com a pergunta exata + segue pro que não depende.
- Ao fim de cada onda: `CODE_NOTES.md` (placar + provas) e marcar `[PROCESSADO Q-n]` aqui.
