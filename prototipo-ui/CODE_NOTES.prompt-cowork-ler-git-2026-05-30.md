# PROMPT pra o Cowork — "lê o git e te arruma" (cole no chat do Design)

> **De:** [CL] Claude Code → **Para:** [CC] Cowork · **Data:** 2026-05-30
> **O que é:** prompt único, auto-suficiente. Wagner cola isto no chat do Design. Append-only (ADR 0003).

---

## O modelo em que você opera (entenda antes de mexer)

É um **loop de reconciliação**, igual GitOps (Argo CD/Flux): o **git é o estado desejado** (lei); você é a **cópia viva que sofre drift**. Seu trabalho não é ser uma autoridade paralela — é **ler o estado desejado, medir sua diferença, e se puxar de volta pra ele**. Você **propõe**; o Code **aplica** no git sob OK do Wagner.

- **git = fonte canônica** (sistema de registro, append-only, imutável).
- **você (Cowork) = store derivado** (STATUS/MEMORY_INDEX/CARTA): regenerável a partir do git, nunca acima dele.
- **drift** = você citar canon aposentado (ex.: navy quando a lei já é roxo) ou cunhar número que é do git.

---

## Passo 1 — Leia o git NESTA ordem (não pule)

`wagnerra23/oimpresso.com@main`:

1. **`memory/decisions/0094-…` (Constituição V2)** + **`memory/requisitos/_DesignSystem/adr/ui/0013-…` (UI v2)** — lei suprema.
2. **`prototipo-ui/PROTOCOL.md`** — 6 papéis × 7 fases, gates.
3. **`prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`** — leia o bloco **CANON ATUAL (2026-05-30)** no topo primeiro. Tokens, proibições.
4. **`memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md`** — **a entrada única de design** (ADR 0236). Por tela: PRE-FLIGHT → GOLDEN-REFERENCE → REGISTRY → LICOES_F3 → SCREEN-GRADE.
5. **ADRs-chave:** `0235` (roxo `oklch(0.55 0.15 295)` universal) · `0236` (governança da doc de design) · `0110` (Cockpit V2) · `0107` (gate visual F1.5) · `0104` (MWART) · `0003` (append-only) · `0028` (numeração monotônica).
6. **`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`** — anti-padrões.

> Toda vez que terminar de ler, **declare sob qual versão da constituição** você está indexado (ADR 0094 + UI-0013 + ADR 0235/0236).

## Passo 2 — Reconcilie-se (a grade · estado → meta → ação)

Para cada item: **mover/lápide, nunca apagar** (ADR 0003).

| Drift | Conserto |
|------|----------|
| **Números de ADR cunhados** (`0200-ds-harmonizacao`, `0201-ratificacao`) — colidem com git (0200=Contacts, 0201=SEFAZ). | Renomeie pra `_PROPOSTA-…md` (sem número), como você já fez com a soberania-W. **Você NUNCA atribui número do git — só o Code.** No MEMORY_INDEX troque "ADR 0200/0201" por "proposta → Code numera". O canon real já é ADR 0235 (roxo) + 0236 (governança). |
| **Staleness** — `CLAUDE.md.proposto` diz navy `#1f3a5f`; STATUS D-02 diz "cor por tela". | Marque a seção como `STALE → ADR 0235 (roxo 295 universal)`. Navy = **débito a migrar**, não identidade. "Cor por tela" segue **proposta F0**, não norma. |
| **Faxina rasa** — `uploads/` (3,7 MB, c/ handoff antigo aninhado recursivo), `backups/`, `scraps/`, `memory-para-github/` ficaram fora da base limpa **e** do `_arquivo/`. | Mova-os pra `_arquivo/legado/` e registre no `_arquivo/INDEX.md` (bump **v1.1**). O handoff recursivo é o que infla o export (14 MB) — arquive inteiro. |
| **Índices concorrentes** — MEMORY_INDEX (você) vs INDEX_TEMATICO (git) vs INDEX-DESIGN-MEMORIAS (git, fonte única). | No topo do MEMORY_INDEX declare: "fonte única de design = INDEX-DESIGN-MEMORIAS (git); este é **derivado/temático**". Pare de re-tabular ADR que já vive no git — **aponte, não duplique**. |
| **Ritual de ponte morto** — `SYNC_LOG.md` e `CODE_NOTES.md` vazios. | A cada handoff, anexe 1 linha no `SYNC_LOG.md`. |

## Passo 3 — Emita o handoff do jeito que a lei nova pede (ADR 0236)

Todo export futuro carrega no fim este bloco (é o gatilho que faz o Code reconciliar — gatilho **G2**):

```
## new_design_memories
- tipo: golden | conflito | anti-padrao | token | doc-novo
- ref: <path ou ADR>
- resumo: <1 linha>
```

---

## Regras duras (não cedem)

1. **Você não escreve no git** e **não numera ADR.** Propõe (F0) e gera a ponte; o Code leva.
2. **Append-only (ADR 0003):** registro vira lápide + índice, nunca delete silencioso.
3. **Não inventa** cor/componente/persona/golden — quando não existe no git, **pergunta**.
4. **Onde divergir do git, o git vence.** Sua CARTA/STATUS são subordinados.
