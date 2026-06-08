# Worklist de auditoria paralela — frente paralela-segura (read-only)

> **Origem:** Cowork [CC] 2026-05-31 ("feature A") + dossier [`memory/sessions/2026-05-30-arte-task-system-cowork-code.md`](../../memory/sessions/2026-05-30-arte-task-system-cowork-code.md).
> **Princípio-chave (Wagner, NÃO violar):** tela não fecha por OPINIÃO. Fecha por **EVIDÊNCIA OBJETIVA** reproduzível (regra mecanizada = 0 violações, `ds/*` = 0, print que bate golden). O agente CONFERE contra evidência — não narra.
> **Status:** scaffold (Code [CL]). GOLDEN-REFERENCE = draft a reconciliar com a cópia do Cowork.

## O que é

N agentes do Code rodam **read-only** (não tocam `Modules/*` nem `resources/js`), pontuam **cada tela** contra as **10 regras da [GOLDEN-REFERENCE](GOLDEN-REFERENCE.md) + `ds/*`**, e cospem **1 `design-report.json` por tela** em `reports/`. Zero colisão (1 agente = 1 tela = 1 arquivo). Um consolidador determinístico ([`consolidate.mjs`](consolidate.mjs)) junta tudo num **placar único** que estende o [`DS_ADOCAO_INDICE.md`](../DS_ADOCAO_INDICE.md).

É a **frente paralela-segura** porque é toda read-only + append-de-arquivo-próprio: pode rodar em paralelo com qualquer implementação sem race.

## Por que não duplica o board 2026-05-30

O [`SCREEN-GRADE-BOARD-2026-05-30`](../../memory/governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md) foi um run **one-off** (19 agentes, 1 JSON global + 1 MD). Esta worklist é a **versão repetível e mecanizada**:
- saída **por tela** (`design-report.json`), não 1 blob global → cada PR-C re-pontua só a tela que tocou;
- regra **pass/fail mecanizada** (regex/ESLint) separada do julgamento LLM (`mechanized: true/false`) → evidência ≠ opinião (anti-"Gaming the Judge");
- `measured_against_sha` em cada report → anti-stale (sabe-se contra qual HEAD foi medido).

## Como roda (2 fases + consolidação)

**Fase 1 — MECANIZADA (determinística, zero LLM):**
1. `node prototipo-ui/audit/score-mechanized.mjs` — varre TODAS as telas (`resources/js/Pages/**/*.tsx`), roda os regex das 7 regras mecanizáveis (R1,R2,R3,R4,R6,R7,R9), puxa a contagem `ds/*` real por arquivo de `config/eslint-baseline.json`, e escreve 1 `design-report.json` por tela. **Evidência reproduzível, custo zero de agente.** As 3 regras julgadas (R5,R8,R10) ficam `status:"n/a"` pendentes.

**Fase 2 — JULGADA (agentes LLM, OPCIONAL):**
2. *(Cowork/Wagner dispara)* N agentes read-only leem só as telas que importam e preenchem **só** R5/R8/R10 + refinam `nota`/`resumo` (o regex já fez o resto). 1 lote por agente, zero colisão. A Fase 1 já dá um placar utilizável sozinha — a Fase 2 é refino.

**Consolidação:**
3. `node prototipo-ui/audit/consolidate.mjs` → [`CONSOLIDADO.md`](CONSOLIDADO.md) (placar worst-first) + `CONSOLIDADO.json`.
4. **EXTEND** — o placar vira a dimensão "Adoção DS / Pre-Flight" no `DS_ADOCAO_INDICE.md` (link, não cópia).
5. **CLOSE-BY-EVIDENCE** — uma tela "sobe" só quando um novo `design-report.json` (medido contra HEAD mais novo) mostra a regra zerada. Ratchet: nota só sobe ([ADR 0236](../../memory/decisions/0236-screen-grade-ratchet.md)).

> **`nota` é TETO provisório**, não a nota holística do board. O mecanizado conta só as 7 regras-DS — uma tela com UX excelente (board 90+) pode ter nota mecanizada menor por ter cor crua/elemento nativo. Os dois sinais são verdadeiros e **complementares**: board = qualidade UX; mecanizado = conformidade-DS reproduzível.

## Regra de ouro da paralelização (zero colisão)

- **1 agente escreve SÓ os arquivos das telas do seu lote**, com nome `reports/<slug>.design-report.json` onde `<slug>` = caminho da tela com `/`→`__` (ex `NfeBrasil/Transactions/NfceStatus` → `NfeBrasil__Transactions__NfceStatus.design-report.json`).
- Nenhum agente toca `CONSOLIDADO.*` (só o consolidador, depois, single-threaded).
- Nenhum agente toca `Modules/`, `resources/`, migrations, rotas. **Read-only.** Viola = descartar o run.

## Prompt do agente-scorer (template — o "1 prompt" que o Cowork gera)

```
Você é um auditor de UI READ-ONLY. NÃO edite nenhum arquivo de produção.
Telas do seu lote: <LISTA DE PATHS .tsx>
Para CADA tela:
  1. Leia o .tsx (e o .charter.md ao lado, se houver).
  2. Rode os 10 checks da prototipo-ui/audit/GOLDEN-REFERENCE.md. Para os mecanizados,
     baseie-se na evidência textual exata (cite o trecho). Para os julgados, marque mechanized:false.
  3. Puxe a contagem ds/* do módulo (se disponível em config/eslint-baseline.json).
  4. Escreva prototipo-ui/audit/reports/<slug>.design-report.json conforme o schema.
     measured_against_sha = HEAD atual (git rev-parse --short HEAD).
NÃO escreva mais nada. NÃO consolide. 1 arquivo por tela.
```

## Arquivos

| Arquivo | Papel |
|---|---|
| [`GOLDEN-REFERENCE.md`](GOLDEN-REFERENCE.md) | As 10 regras + `ds/*` · fonte canon de cada uma · método de detecção · peso |
| [`score-mechanized.mjs`](score-mechanized.mjs) | **Fase 1** — scorer determinístico (regex + `ds/*`), zero LLM. Gera os reports de todas as telas |
| [`design-report.schema.json`](design-report.schema.json) | Contrato do `design-report.json` por tela |
| `reports/*.design-report.json` | 1 por tela (gerado — **gitignored**, regenerável via `score-mechanized.mjs`; agente Fase 2 sobrescreve só R5/R8/R10) |
| [`example.design-report.json`](example.design-report.json) | 1 exemplo versionado (amostra do schema) |
| [`consolidate.mjs`](consolidate.mjs) | Determinístico: lê `reports/` → `CONSOLIDADO.md` + `CONSOLIDADO.json` |
| `CONSOLIDADO.md` | Placar único versionado (gerado — Cowork lê daqui; nunca editado à mão) |
| [`review-gen.mjs`](review-gen.mjs) | **`design:review <tela>`** — renderiza o `design-report.json` (Fase 1) num `<Tela>.review.md` **ON-DEMAND** (não persistido/gateado — [ADR 0255](../../memory/decisions/0255-contrato-view-deterministico-charter-design-spec.md)) |

## Review por tela — `design:review` (ON-DEMAND, rebaixado · ADR 0255)

> **Rebaixado em 2026-06-06 ([ADR 0255](../../memory/decisions/0255-contrato-view-deterministico-charter-design-spec.md)):** os 157 `<Tela>.review.md` persistidos + o gate de frescor (`review-freshness.mjs` + `DesignReviewFreshnessTest` + baseline) foram **removidos** — apodreciam e exigiam manutenção. O **frescor estrutural determinístico** agora vive no `<Tela>.design-spec.json` (`design-spec:check`, machine-checkable, sem juiz LLM). O review LLM (opinião de design subjetiva) vira **on-demand**: gera quando quiser, não persiste, não gateia.

```bash
npm run design:review Jana/Pro        # gera o review LLM da tela ON-DEMAND (não commita)
node scripts/design-spec-gen.mjs resources/js/Pages/Jana/Pro.tsx   # contrato estrutural (determinístico, este SIM gateado)
```

**Por quê:** review.md era LLM-judge persistido com gate de frescor pra combater o apodrecimento — mas o que importava (estrutura: componentes/tokens/layout) é DERIVÁVEL e agora está no design-spec (determinístico). O resíduo (opinião subjetiva de design) não precisa de frescor gateado — gera sob demanda.

`stale` é **advisory na v1** (reviews legados de 2026-05-17 não têm `measured_against_sha`); vira
HARD quando regenerados. PROTOCOL §6 ganha `design_review_missing` + `design_review_stale`. A
**Fase 2 (juiz LLM)** preenche R5/R8/R10 + nota holística + `best_of_class` — cadência paga [W].
Proposta: [`memory/decisions/proposals/design-review-por-tela-charter-page.md`](../../memory/decisions/proposals/design-review-por-tela-charter-page.md).

## Calibração & honestidade (v1, 2026-05-31)

- **R6 (emoji)** calibrado: pega só emoji real (plano `1F000-1FAFF`). Dingbats BMP (`✓ ✕ ★ ✦ ⚙ ⬇`) **não** contam — são glyph de UI (smell de R4 "usar lucide"), não emoji. Sem isso, falso-positivava goldens.
- **R7 (status bg-fill)** é **heurística ampla** (80/239 telas): pega qualquer `bg-*-100`, não só em badge de status. Mecanizado mas **baixa-precisão** — o agente Fase 2 confirma se é mesmo violação AP7. Tratar como sinal, não veredito.
- **Validação contra o board:** goldens batem de forma explicável (Inbox 91→88, Sells/Index 90→72 por R1/R2/R4 que o board já notava). A divergência é a dívida-DS que a nota holística mascarava — não é bug.

## Pendências (Wagner decide)

1. **Reconciliar GOLDEN-REFERENCE** com as "10 regras" da cópia do Cowork (COWORK_NOTES → Pendentes, fora do git).
2. **Disparar o run completo** (222 telas) — gated em "avisa antes" (Cowork/Wagner). O scaffold + pilot de 2 telas já validam o contrato.
