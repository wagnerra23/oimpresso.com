# COLE NO CLAUDE CODE — Protocolo v2: PR-A3 + Ondas B·C·D·E (1 paste · auto-contido · sem link externo)

Você é o **Claude Code [CL]** no repo `wagnerra23/oimpresso.com`, base `main`.
Contexto: a **Onda A** do colapso já está no `main` (PR #2877 · git=SSOT, espinha demovida; proposta-pai #2871; ADR-proposta da Onda A #2874, ainda sem número formal — soberania [W]).
Regras: **§10.4** (valide vs `main`; se já está lá, não refaça) · **1 onda = 1 PR DRAFT** · **NÃO auto-mergeie nada** (tudo aqui é Tier 0 / governança — [W] mergeia cada uma) · **não cunhe número de ADR** (soberania [W]; referencie como proposta).

São **5 itens**, em ordem de risco crescente. Faça PR-A3 primeiro; B–E podem ir como PRs draft paralelos, cada um aguardando o OK do [W].

---

## PR-A3 — write-path de MEMÓRIA (parar [W] de colar memória à mão) · risco BAIXO
**Por quê:** hoje o `cowork-inbox.py`/`.yml` já auto-PR→auto-merge pra `prototipo-ui/`/`memory/`/`docs/` — mas só dispara quando ALGUÉM faz push em `cowork-inbox/`. O atrito que sobra é esse push manual.
**O quê:** provisionar um **GitHub App / PAT escopado** que o lado-Cowork usa pra push **só em `cowork-inbox/`** (nunca `main`, nunca `.github/`, nunca `resources/js/`). O `cowork-inbox.py` já valida o whitelist no processamento — então mesmo o token escopado não escapa do allowlist.
- Doc: `prototipo-ui/RUNBOOK-cowork-write-path-memoria.md` (como o segredo é configurado, escopo, rotação).
- Guard: confirmar no `cowork-inbox.yml` que o job ignora pushes que toquem fora de `cowork-inbox/**` (o `paths:` já faz; reforçar no script se preciso).
**Reversível:** revogar o token. **Tier:** toca `.github/`/segredo → **[W] aprova**. **Status:** proposta, aguarda OK.

## ONDA B — intake para de ser fila markdown · risco BAIXO
**Objetivo:** aposentar `COWORK_NOTES → Pendentes` (o append-only que apodreceu: 18 órfãos + 19 refs mortas) em favor de **Issues** (ou itens `cowork-inbox`).
**O quê:**
- Congelar a fila: mover **tudo** de `Pendentes` pra abaixo da `LINHA D'ÁGUA` com um aviso "fila aposentada — intake agora é Issue".
- Template de Issue `.github/ISSUE_TEMPLATE/cowork-handoff.yml` (campos: tela/arquivo · setor · verificado-vs-main@SHA · onda).
- `PROCESSO_MEMORIA_CC.md`: trocar "fila markdown" por "Issue" como intake canônico (aditivo, atribuído à proposta #2874).
**Reversível:** descongelar a fila. **Tier:** governança → **[W] aprova**. **Status:** proposta.

## ONDA C — fases viram CHECKS, não papéis humanos · risco MÉDIO
**Objetivo:** F1.5 (critique) · F2 (screenshot) · F3.5 (a11y) deixam de ser papéis humanos síncronos e viram **required checks** de CI no PR.
**O quê (validar o que já existe — não recriar):**
- **F3.5 a11y** → `a11y-axe-gate.yml` **já existe**: só promovê-lo a **required check** na branch protection.
- **F1.5 critique** → o `critique-score` / `design:review` já são JSON/mjs no repo: rodar no PR e exigir ≥ limiar como check.
- **F2 screenshot** → screenshots no PR (Playwright/`tests/Browser` já existe no repo): publicar como artefato/preview, exigir o check verde.
- `PROTOCOL.md`: §2 marca F1.5/F2/F3.5 como **automáticos** (papéis [CD]/[CA]/[W2] → CI). Aditivo, atribuído à proposta.
**Reversível:** desmarcar required. **Tier:** mexe em CI/PROTOCOL → **[W] aprova**. **Status:** proposta.

## ONDA D — write-path de CÓDIGO (mata o COLE_NO_CODE) · risco MÉDIO
**Objetivo:** estender o `cowork-inbox` pra cobrir `resources/js/**`, **atrás de review, nunca auto-merge** — aí o transporte manual de código deixa de existir.
**O quê (D-core, ~40 linhas):**
- `cowork-inbox.py`: 2ª whitelist `ALLOWED_PREFIXES_REVIEW = ("resources/js/",)`; `classify_path()` retorna `auto|review|None`; se algum arquivo for `review`, escrever marcador `cowork-inbox/.REVIEW_REQUIRED`. DENY continua `("..",".github/",".claude/")` (anti-escalada — Cowork nunca reescreve os próprios guards).
- `cowork-inbox.yml`: se `.REVIEW_REQUIRED` existe → abre PR + label `needs-review` + reviewer `wagnerra23`, **sem `gh pr merge`**. Senão, fast-path squash de hoje (doc/memória).
- Branch protection no `main`: `typecheck`·`lint`·testes·`a11y-axe` como **required checks** (segura o TSX autorado pelo Cowork; PR vermelho não mergeia).
**Reversível:** remover a 2ª whitelist. **Tier:** toca `.github/` → **[W] aprova**. **Status:** proposta detalhada já no `main` em `memory/decisions/_PROPOSTA-onda-D-cowork-inbox-codigo-W.md`.

## ONDA E — ratificar PROTOCOL v2 (consolidar o colapso) · risco BAIXO, mas FINAL
**Objetivo:** depois que A–D estiverem no `main`, consolidar o colapso na lei.
**O quê:**
- Numerar a proposta-pai #2871 + a Onda A #2874 em ADR(s) formais (sob OK explícito de [W] — você cunha o número).
- `PROTOCOL.md`: 6 papéis → 2 ([CC] designer-agente + [W] aprovador; [CD]/[CA]/[W2] → CI; [CL] → [CC] commitando) · 7 fases → 3 (protótipo → PR+CI → review+merge).
- `CLAUDE.md` (Cowork): refletir o v2 (memória=git, intake=Issue, sem transporte manual).
**Reversível:** git revert. **Tier:** **Tier 0 puro — só [W]**, e só depois de A–D provadas. **Status:** proposta, NÃO executar antes de A–D.

---

## Como proceder
1. **PR-A3** primeiro (destrava o autosync de memória).
2. **B, C, D** como PRs **draft** paralelos — cada um espera o OK do [W] pra sair de draft e mergear.
3. **E** por último, só quando A–D estiverem no `main`.
4. Reporte cada PR em `prototipo-ui/CODE_NOTES.md`. **Nada de auto-merge.** Não cunhe número de ADR sem OK explícito do [W].
