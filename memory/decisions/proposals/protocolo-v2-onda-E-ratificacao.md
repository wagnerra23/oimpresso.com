# PROPOSTA · Protocolo v2 (colapso) — Onda E: RATIFICAÇÃO — só [W] numera

> **Status:** ✅ **RATIFICADO como [ADR 0282](../0282-protocolo-v2-colapso-ratificacao.md)** (2026-06-17). _(Era proposta; o Code numerou sob autorização explícita de [W] — "pode numerar, eu autorizo". Soberania [ADR 0238](../0238-soberania-constituicao-wagner.md) preservada: [W] decide/autoriza, o Code cunha o número.)_ Este doc fica como rationale/histórico; o canon é a ADR 0282.
> **Pai:** [`_PROPOSTA-protocolo-v2-colapso-W.md`](../_PROPOSTA-protocolo-v2-colapso-W.md) (#2871). Capstone das Ondas A–D, **todas já executadas** (evidência §3).

---

## 1. Contexto

A proposta-pai (#2871, aprovada por [W]) argumentou que ~70% do protocolo custom era andaime de duas restrições (R1 Cowork read-only no git · R2 design fora do repo) e propôs **colapsar** apoiando no nativo (Issues + CI + branch protection + git=SSOT). As Ondas A–D foram executadas em PRs revisáveis, sem big-bang. Esta Onda E **ratifica** o resultado como o modelo canônico v2.

## 2. Decisão a ratificar (Protocolo v2)

| Eixo | v1 | v2 (ratificado) |
|---|---|---|
| **Papéis** | 6 ([W]/[CC]/[CD]/[CL]/[CA]/[W2]) | **2 humanos-no-loop**: [CC] designer-agente (F1 protótipo + abre PR) · [W] aprovador. [CD]/[CA]/[W2] → **CI**. [CL] → Cowork commitando / agente de tradução. |
| **Fases** | 7 (F0–F4 + 1.5/3.5) | **3**: F0 brief → F1 design + auto-checks → gates CI → merge. (Já vigente via overlay autônomo `PROTOCOL.md §2`, ADR 0241.) |
| **Memória** | espinha markdown espelhada = autoridade | **git = SSOT**; `STATUS.md`/`MEMORY_INDEX.md` = cache derivado (Onda A). |
| **Intake** | fila `COWORK_NOTES.md` | **GitHub Issue** (`cowork-intake`) ou `cowork-inbox/` (Onda B); fila congelada. |
| **Gates** | papéis humanos (F1.5/F2/F3.5/F4) | **checks de CI** (visual-regression + **a11y-axe required** · Onda C). |
| **Write-path** | [W] cola / [CL] coda | **`cowork-inbox`** — doc auto-merge; **código (`resources/js/**`) → PR + review humano, nunca auto-merge** (Onda D, #2876). |

## 3. Evidência (o que já shipou — esta ratificação não muda código, só consolida)

- **#2871** — proposta-pai (colapso).
- **Onda A · #2877** — git=SSOT, `STATUS.md` demovido a cache; piso (NÚCLEO 1 + §13.3) emendado aditivo. ADR-proposta em [`proposals/protocolo-v2-onda-A-memoria-git-ssot.md`](protocolo-v2-onda-A-memoria-git-ssot.md) (#2874).
- **PR-A3 · #2878** — [W] para de colar; memória via `cowork-inbox`.
- **Onda B · #2880** — intake via Issue (`cowork-intake.yml`) + `COWORK_NOTES` congelada.
- **Onda C** — `A11y axe · runtime nos componentes canon` agora **required** no branch protection do `main`; lado-doc já no overlay `PROTOCOL.md §2`.
- **Onda D · #2876** — `cowork-inbox` write-path de código com review-gate (código nunca auto-merge).

## 4. O que NÃO muda (o ouro — preservado em todas as ondas)

- **F1 · loop de protótipo visual** (a razão do Cowork) — intacto.
- **ADRs Nygard**, **tokens/personas/Cockpit** (CLAUDE_DESIGN_BRIEFING), **gates de CI** — intactos.
- **Multi-tenant Tier 0**, **soberania-[W]** (constituição/ADR/token = só [W]), **memory-health** (segredo + colisão-ADR) — todos intactos. Nenhuma "Regra 6" removida.

## 5. Consequências

**Positivas:** o protocolo deixa de re-implementar em markdown + revezamento humano o que o nativo faz melhor; [W] sai de carteiro manual; tudo reversível por PR.
**Risco/limite:** ratificar **não** apaga o v1 (append-only) — `PROTOCOL.md` ganha a marcação **v2** apontando pras ondas; o histórico fica. A metade soberania-[W] e os gates de segurança permanecem.

## 6. Passo de formalização (só APÓS [W] numerar este ADR)

1. **[W] numera** este doc como ADR `NNNN` (supersede/emenda do [ADR 0114](../0114-prototipo-ui-cowork-loop-formalizado.md) loop · [ADR 0241](../0241-loop-design-cowork-code-autonomo-zero-humano.md) autônomo).
2. [CL] abre **PR-E2**: marca `PROTOCOL.md` como **v2** (promove o overlay §2 a modelo principal, aditivo/atribuído) + linka este ADR. Diff mostrado antes do merge.
3. Carimba `#2874` → `ADR NNNN` nas emendas da Onda A (STATUS.md / PROCESSO_MEMORIA §13.3).

---

**Decisão = [W].** [CL] não numera nem marca `PROTOCOL.md` v2 sozinho. Diz o número (ou "ratifica") e eu abro o PR-E2 com o diff antes do merge.
