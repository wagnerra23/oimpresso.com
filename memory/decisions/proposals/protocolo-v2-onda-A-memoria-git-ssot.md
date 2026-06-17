# PROPOSTA · Protocolo v2 — Onda A: memória para de espelhar (git = SSOT) + carve-out de segurança

> **Status:** PROPOSTA. **NÃO é lei, NÃO é ADR numerado.** [CL] rascunha; **[W] decide e numera** (Tier 0, soberania [ADR 0238](../0238-soberania-constituicao-wagner.md)).
> **Pai:** [`_PROPOSTA-protocolo-v2-colapso-W.md`](../_PROPOSTA-protocolo-v2-colapso-W.md) (mergeado PR #2871). [W] aprovou **começar a Onda A**.
> **Por que existe:** ao mapear os mecanismos reais antes de tocar em nada, a Onda A **não bateu** com a descrição da proposta-pai ("menor risco, não toca nada crítico"). Este doc fixa o que a Onda A pode fazer **com segurança** e o que ela **não pode** fazer.

---

## 1. Contexto — o que a investigação achou (2026-06-16, [CL])

A proposta-pai descreve a Onda A como *"parar de tratar a espinha como autoridade; apontar tudo pro git; aposentar **Regra 6** + **memory-health anti-órfão**. Não toca código de produto."* Três achados mudam isso:

| # | Achado | Evidência | Implicação |
|---|---|---|---|
| **A1** | `memory-health` **não é "anti-órfão"** — é gate Tier-0 multiuso, incl. **segurança** | `scripts/governance/memory-health.mjs:11-22` — 🔴 fail = **Check A** (colisão de ADR) + **Check C** (segredo em `memory/`). Não existe fail-class "anti-órfão". | "Aposentar memory-health" = perder varredura de segredo + colisão de ADR. **Regressão de segurança, não alívio.** |
| **A2** | **"Regra 6" não mapeia** pra nenhum andaime de R1 | Todo "Regra 6"/"R6" concreto é proteção: NÚCLEO inv. 6 = piso de DS (`prototipo-ui/PROCESSO_MEMORIA_CC.md:18`); UI-Lint R6 = emoji; **RC-01 "afirmar sem ler"** (`prototipo-ui/evals/REPLAY_CASES.md:7`, reincidiu 3× em 2026-06-08). | Não dá pra "aposentar Regra 6" — nenhuma das que existem deve sair. Precisa de desambiguação de [W]/[CC]. |
| **A3** | O direcional **git = SSOT já é canon** — falta só o resíduo | [ADR 0238](../0238-soberania-constituicao-wagner.md) (soberania-[W]), [ADR 0239](../0239-governanca-design-system-git-ssot-regressao-ia.md) (git=SSOT), [ADR 0236](../0236-governanca-evolucao-doc-design.md) (governança-doc). `prototipo-ui/MEMORY_INDEX.md` **já** se declara "derivado / NUNCA autoridade". | A Onda A **completa e ratifica** algo já começado; o resíduo é `STATUS.md`, que ainda se auto-declara *"single source of truth"* (`prototipo-ui/STATUS.md:1`). |

**Trava mecânica relevante:** `STATUS.md` e o NÚCLEO de `PROCESSO_MEMORIA_CC.md` são guardados pelo check **required** `Append-only canon (ADRs, handoffs, Constituição)` (1 dos 16 contexts required do `main`). Logo o próprio repo **bloqueia** a Onda A como edit silencioso — ela precisa de ADR aceito por [W] que autorize o supersede. (`memory-health` é enforce-no-job mas **não** está entre os 16 required.)

## 2. O piso que a Onda A toca (e por isso depende de [W])

NÚCLEO invariante 3 — `prototipo-ui/PROCESSO_MEMORIA_CC.md:246`:

> *"Piso intocável: posso reescrever o processo, **nunca abaixo do piso** — espinha always-read (STATUS→PROCESSO) + soberania de [W] (constituição/ADR/token = só [W])."*

A Onda A muda a **metade "espinha always-read STATUS→PROCESSO"** desse piso (STATUS deixa de ser autoridade). A metade **"soberania de [W]"** fica **intacta**. Mudar piso = Tier 0 = só [W], append-only (supersede, nunca mutação silenciosa).

## 3. Decisão proposta (Onda A — escopo fechado)

1. **Git é a memória autoritativa** — ratificar explícito: `git history` + `memory/decisions/` (ADRs) + `memory/sessions/` + docs canônicos no repo são o SSOT. (Já é o direcional de 0238/0239/0236; aqui vira regra escrita do loop Cowork.)
2. **`STATUS.md` + `MEMORY_INDEX.md` viram cache de leitura derivado** — não autoridade. `STATUS.md` para de se auto-declarar *"single source of truth"*; passa a "snapshot de conveniência, pode estar stale, **nunca bloqueia**". (`MEMORY_INDEX.md` já está lá.)
3. **Cowork escreve memória via `cowork-inbox`** (já automático pra `memory/`, `prototipo-ui/`, `docs/` — `.github/scripts/cowork-inbox.py` + `.github/workflows/cowork-inbox.yml`), **não** via [W] colando à mão.
4. **Emenda ao NÚCLEO invariante 3:** a "espinha always-read" passa a ser **o canon do git** (CLAUDE.md / PROTOCOL.md / PROCESSO_MEMORIA_CC.md / ADRs / charter da tela), removendo o status de autoridade do `STATUS.md`. **A metade soberania-[W] do piso continua intocada.**

## 4. Carve-outs (inegociáveis — o que a Onda A NÃO faz)

- **C1 · `memory-health.mjs` continua 100% ligado.** Check A (colisão ADR) + Check C (segredo em `memory/`) são segurança/governança, **fora do escopo** de "aposentar memory-health". Relaxar qualquer *warn* (B/D/E) é decisão separada e nomeada — não entra aqui.
- **C2 · Nenhuma regra chamada "Regra 6" é aposentada.** As concretas (NÚCLEO inv. 6 piso-DS; UI-Lint R6 emoji; RC-01 afirmar-sem-ler) **ficam**. Se a proposta-pai quis dizer uma obrigação de *espelhamento* de memória, [W]/[CC] precisa **nomear a regra exata** antes de qualquer toque.
- **C3 · Zero código de produto.** Nada em `resources/js/**`, `Modules/**`, `app/**`. Só docs de processo + um header de doc.
- **C4 · Soberania-[W] do piso intacta.** Constituição / ADR / token continuam só-[W].

## 5. Consequências

**Positivas:** mata a classe "STATUS espelho stale"; tira [W] de carteiro manual; reversível; não toca produto.
**Riscos & mitigação:** (a) edit do NÚCLEO/STATUS dispara o `Append-only canon` required — por isso vai como **supersede via ADR aceito**, revisado, nunca silencioso; (b) preserva segurança (C1); (c) reverter = `git revert` do ADR + restaurar header do `STATUS.md`.

## 6. Fora de escopo (ondas seguintes — só pra orientar, não decididas aqui)

- **Onda B** · intake → Issues / `cowork-inbox`; congelar fila `COWORK_NOTES`.
- **Onda C** · fases F1.5/F2/F3.5/F4 → checks de CI (vários já existem).
- **Onda D** · estender `cowork-inbox` pra cobrir write-path de **código** (`resources/js/**`) atrás de review — nunca auto-merge.
- **Onda E** · ratificar PROTOCOL v2 formal.

## 7. Plano de execução (PRs pequenos, **após [W] numerar este ADR** — sem auto-merge)

1. **PR-A1** — este doc (proposta). *(atual)*
2. **PR-A2** *(só após aceite)* — editar header do `prototipo-ui/STATUS.md` ("cache derivado, não autoridade; git é SSOT") + emendar NÚCLEO inv. 3 com nota de supersede append-only. Toca o `Append-only canon` required → revisado peça por peça.
3. **PR-A3** *(opcional)* — confirmar/cablear `cowork-inbox` cobrindo o write-path de memória pra [W] parar de colar.

---

**Decisão = [W].** [CL] não numera nem aplica nada disto. Se [W] aprovar, [CL] ratifica em ADR Nygard sob OK de [W], e só então abre PR-A2/A3.
**Pergunta pra [W]:** confirma o escopo+carve-outs acima? E o que era "Regra 6" / "memory-health anti-órfão" na sua cabeça — só o espelho do `STATUS.md`, ou alguma obrigação que eu ainda não localizei?
