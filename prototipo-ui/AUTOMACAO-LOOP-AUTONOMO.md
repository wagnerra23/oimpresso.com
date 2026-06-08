# AUTOMACAO-LOOP-AUTONOMO.md — loop DS-migration com mínima intervenção humana

> **Origem:** 2026-05-31 — Wagner: *"essa vai ser o padrão de comunicação? 0 humano? faça o automatismo, decida como ser melhor, pode usar a máquina o chrome. crie e documente e vá evoluindo até não ter mais intervenção humana. vai documentando o que deu certo com melhor custo-benefício."*
> **Tipo:** doc VIVO. Cada Onda apêndice no §5 (custo-benefício). Evolui até zero intervenção.
> **Princípio:** o humano sai do **loop**, não da **supervisão** — transparência (Constituição v2 §7) + reversível (revert) + gate automático no lugar do gate humano. Nunca bypassa segurança (Tier 0 fica humano).

## 1. O loop (1 módulo = 1 ciclo)

| # | Passo | Quem | Custo | Gate |
|---|---|---|---|---|
| 1 | eslint-scan `ds/*` do módulo | [CL] `npx eslint -f json` + parser node | baixo | — |
| 2 | migrar só as linhas apontadas (transform canônico PR-C-WORKLIST) | [CL] Edit | médio | — |
| 3 | `npm run build` + `build:inertia` | [CL] | ~15s | compila |
| 4 | `lint:baseline:write` + `:check` (delta ≤ 0) | [CL] | baixo | baseline cai |
| 5 | commit + push + `gh pr create` | [CL] | trivial | — |
| 6 | **CI (gate automático — §2)** | GitHub Actions | ~3-4 min | **substitui o humano** |
| 7 | merge se CI verde (§3) | [CL] | trivial | branch protection |
| 8 | sync placar (`ds:report --write` + SYNC_LOG + HANDOFF) | [CL] | baixo | — |
| 9 | próximo módulo da fila | [CL] | — | — |

## 2. Os gates automáticos = substituto do humano

A suite CI já cobre o que o humano fazia — **incluindo o gate visual [W2]**:

| Check CI | Cobre |
|---|---|
| **PR UI Judge · Claude Sonnet 4.5** | **review visual/UX → substitui o screenshot [W2]** |
| **visual-regression** | diff visual vs baseline |
| ESLint · ratchet vs baseline | drift `ds/*` (delta > 0 falha) |
| UI Lint · ratchet (PHP R1–R6) | cor crua, FontAwesome, emoji, PT-01, origins, blade |
| Frontend / Vite build | compila React/Inertia |
| PHP / Pest | testes backend |
| charter-gate · module-grades-gate · ADR 0216 scan · secrets:scan | governança/segurança |

**Regra de decisão:** *todos os checks required verdes → merge.* Vermelho → [CL] corrige (causa clara) **ou** escala (ambíguo / Tier 0).

## 3. Merge — o único ponto que ainda toca "humano" (e como tirá-lo)

Branch protection `main`: **1 approving review** + `strict` (up-to-date) + `enforce_admins:false`. GitHub **proíbe self-approve** → o autor (`wagnerra23`) não aprova o próprio PR. Esse é o ÚNICO bloqueio real ao zero-humano.

| Mecanismo | Custo | Auditoria | Status |
|---|---|---|---|
| Wagner aprova+mergeia manual | alto (humano no loop) | ✅ | era o padrão |
| `gh --admin` (wagnerra23 é admin) | trivial | atribuído a conta real, **sem registro de review** | **interim (Wagner autorizou 2026-05-31)** |
| Chrome dirige GitHub UI como Wagner | alto (pixels) + cria approve "do Wagner" que foi do Claude | ⚠️ registro enganoso | **descartado** |
| 🎯 Bot `grokwr2` auto-approve+merge (Action) | ~zero/ciclo | ✅ approve real de conta ≠ autor | **alvo da evolução** |

**Decisão atual (custo-benefício):** `gh --admin` — mais barato + atribuição honesta + CI verde é o gate. **NÃO** Chrome (caro + registro falso de approve de Wagner).

**🔑 Único bootstrap humano p/ zero intervenção:** Wagner provisiona o token do `grokwr2` (collaborator com push, conta ≠ autor) como secret → uma Action aprova+mergeia quando todos os checks passam. Aí o `--admin` sai e fica auditável **sem humano**.

## 4. Lista ENCOLHENDO de intervenção humana

- ❌ ~~Wagner mergeia cada PR~~ → automático (`--admin` agora / bot depois)
- ❌ ~~Wagner aprova screenshot [W2]~~ → PR UI Judge + visual-regression
- ❌ ~~Wagner responde Tipo 1 vs Tipo 2~~ → regra documentada (PR-C-WORKLIST §EMENDA)
- ❌ ~~Wagner roda o sync do placar~~ → `ds:report --write` automático
- ⏳ Bootstrap único restante: token `grokwr2` (1×)
- ✅ FICA humano (Tier 0): ADR novo · mudança multi-tenant · segredos/Vaultwarden · lógica de lint/tooling · decisão de produto

## 5. Custo-benefício — log (apêndice por Onda)

### Onda 1 — 2026-05-31 — Onda G badge (#2025) + Fase A Sells (#2026)
**O que deu certo (repetir):**
- **Worktree off `origin/main` + junction `node_modules`/`vendor`** — PR base limpa sem mexer no checkout principal (estava em `feat/staging-ct100`, `main` locked). Custo: 1 checkout (~14k files). Benefício: diff limpo + tooling (`ds:report`) certo.
- **Editar só as linhas do eslint (`-f json` + parser node)** — `-f compact` foi REMOVIDO no ESLint 9; usar `-f json`. Migração cirúrgica 42→17.
- **`build:inertia` valida o React** (`vite.config.js`/`npm run build` só compila Tailwind). Usar `build:inertia` como gate de compilação.
- **`--admin` merge com CI verde** — 2 PRs mergeados sem fricção; honesto (conta real).

**O que mordeu (cuidar):**
- **ui:lint (PHP) NÃO exclui `Components/ui`** (o eslint `ds/*` exclui). Badge variants (cor canônica) → R1 0→20 → CI vermelho. Fix: absorver no `config/ui-lint-baseline.json` (fe74f5720). **Melhor seria** excluir `Components/ui` + `_Showcase` do ui:lint R1 (paridade eslint) — escalado a Wagner (mexe no `UiLintCommand`).
- **`ds:report` "Próximo: Sells (17)" é ingênuo** — conta hits brutos, não sabe que os 17 são Tipo-2/Fase D. Curar no HANDOFF até `ds:report` distinguir fase.
- **Branch protection exige review + proíbe self-approve** → sem bot, só `--admin` fecha o loop.

**Bottleneck humano eliminado nesta Onda:** merge + sync + handoff + dúvidas de classificação.

## 6. Próximos passos (evolução)
1. **[bootstrap Wagner]** token `grokwr2` → mata o `--admin`.
2. **[CL]** `.github/workflows/ds-automerge.yml` (label `ds-auto` + todos checks green → `grokwr2` approve + squash merge).
3. **[CL]** auto-advance: ao fechar o sync, disparar o próximo módulo da fila sem novo pedido.
4. **[decisão Wagner]** excluir `Components/ui` + `_Showcase` do ui:lint R1 (evita baseline-absorb futuro).
5. **[CL]** `ds:report` ciente de fase (Fase A done vs Tipo-2/Fase D pendente).
