# STATUS.md — ponteiro (o estado vivo mora no git)

> ⚠️ **Este arquivo NÃO é fonte de verdade.** Desde o modelo SSOT ([W] 2026-06-23,
> `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md`) o Cowork é **esteira, não armazém**:
> git (`wagnerra23/oimpresso.com@main`) é a fonte única; este projeto só carrega o
> **build** (`oimpresso.com.html` + `*-page.jsx/css`) e **ponteiros** pra verdade viva.
> Memória local = cache que envelhece (causa-raiz do erro recorrente L-42 / PORTÃO 1).
> **Não reintroduzir espinha local.** Conhecimento novo é destilado no charter/SPEC do git.

## Início de todo chat — leia no `main` (MCP/GitHub connector), nunca de cópia local
1. `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` — como o Cowork opera na estrutura SSOT (read-order, rotina, o que NÃO fazer).
2. `prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` — por tela: 🟠 desenvolver · 🔵 puxe o vivo (não refaça) · ⚪ fundação (espera [W]).
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisitos por tela (não inventar token/Model/componente; não repetir erro).
4. O **charter** da tela que vou mexer: `resources/js/Pages/<Mod>/<Tela>.charter.md` (+ `.casos.md`).
5. Lei/decisões: `memory/INDEX.md` + `memory/proibicoes.md` + `decisions-search` (MCP). Lições: `memory/LICOES_CC.md`.

## Regras que sobrevivem (as que não dependem de memória local)
- **Fato sobre o repo = só com leitura do `main` NESTE turno**, senão "não verifiquei". Cópia local ≠ git, sempre.
- **Escrevo só o build** em `prototipo-ui/cowork/` (jsx/tsx/css/html). Nunca memória, process-doc, charter, screenshot, dupe `?v=`.
- **Não escrevo no git** (read-only). Ponte pro `main` = você cola 1× (zero-toque) ou via `cowork-inbox`/Issue → PR. Nunca digo "commitei/mergeei".
- **Intake novo** = GitHub Issue (form `cowork-intake`) ou `cowork-inbox/`. `COWORK_NOTES.md` está congelada pra itens novos.
- **Prontidão de aplicação** = `node scripts/qa/prototipo-readiness.mjs` (máquina), não fila manual.
- Cor/token/identidade = canon do DS vivo (`_ds/…/colors_and_type.css` + `cockpit_domains.css`); roxo `oklch(0.55 0.15 295)` (ADR 0190/0235).

## O que vive AQUI (só isto)
- `oimpresso.com.html` (o app) + `*-page.jsx`/`*.css` + `data-*.jsx` — o **build**.
- `_ds/` — bundle do DS vivo (não editar; espelho do git).
- `CLAUDE.md` — config do projeto (lida todo chat) · este `STATUS.md` + `MEMORY_INDEX.md` — ponteiros.
