# Handoff [CC] → Code — Onda D-core: write-path de CÓDIGO no cowork-inbox

> **Origem:** `_PROPOSTA-protocolo-v2-colapso-W.md` (Onda D). Decisão [W] 2026-06-16: **"Review-gated, sem auto-merge"**.
> **Quem implementa:** Code, em PR. Toca `.github/**` → CODEOWNERS exige review de `@wagnerra23` no próprio PR.
> **[CC] propõe/entrega este handoff; [CC] NÃO aplica.**
> **Verificado vs main nesta sessão (2026-06-16):** os dois arquivos abaixo + branch protection + CODEOWNERS + config auto-merge.

---

## 0. Por que isto encolheu (estado REAL do main, não o da proposta)

A proposta original assumia que era preciso **construir** o backstop de segurança. Não é — **já existe**:

- **`main` tem 16 required checks + `enforce_admins: true`** (ligado 2026-06-11, ADR 0271). Inclui `Frontend / Vite build`, `PHP / Pest (Unit)`, `PHP / Pest (Financeiro · MySQL)`, `E2E Playwright`, `visual-regression`, `PII scan`, `PHPStan ratchet`, etc. → **TSX autorado pelo Cowork não mergeia vermelho, nem por admin.**
- **Anti-escalada já duplo-travada:** `.github/` é path de CODEOWNERS (`@wagnerra23`) **e** está em `DENY_SUBSTRINGS` do script. Cowork não reescreve CI/guards por nenhum caminho.
- **`resources/js/` é LIVRE no CODEOWNERS** (política 2026-06-03 "merge verde, críticos não"): PR verde lá mergeia com 0 aprovações. Por isso [W] escolheu que **código vindo do inbox** seja explicitamente segurado por review — o workflow simplesmente **não mergeia** a PR de código (≠ alterar CODEOWNERS).
- **Bug latente consertado de brinde:** o workflow fazia `gh pr merge --squash` **imediato** (sem `--auto`). Sob os 16 checks + enforce_admins isso **falha** (PR não-mergeável com checks pendentes) → PRs ficariam abertas. `allow_auto_merge` do repo agora é `true`, então a correção é `--auto`. (Não houve push no inbox desde 2026-06-08, então isso ainda não foi exercido sob os gates — o próximo drop de doc bateria nele.)

**Resultado:** D-core = split de whitelist no `.py` + lógica de merge no `.yml`. Sem mexer em branch protection. Sem mexer em CODEOWNERS.

---

## 1. `.github/scripts/cowork-inbox.py` — conteúdo final (sobrescrever)

Mudanças: `validate_path` → `classify_path` (tiers `auto`/`review`/`None`); `ALLOWED_PREFIXES_REVIEW = ("resources/js/",)`; `process_file` devolve `(msg, tier)`; ao fim, escreve `review_required=true|false` em `$GITHUB_OUTPUT`.

```python
#!/usr/bin/env python3
"""Process files in cowork-inbox/ — move/append to whitelisted paths, then delete from inbox.

Triggered by .github/workflows/cowork-inbox.yml on push to main touching cowork-inbox/**.

Header syntax (HTML/markdown comment, anywhere in file):
    <!-- cowork: target: <path> -->        # write/overwrite at <path>
    <!-- cowork: append-to: <path> -->     # append (after newline) to <path>
    <!-- cowork: commit: <message> -->     # reserved; not yet wired (workflow hardcodes the message)

Whitelist enforced — paths must start with an allowed prefix and must not contain a denied substring.

Two tiers (Onda D):
    ALLOWED_PREFIXES        -> "auto":   doc/memory/prototype, fast-path (--auto merge once CI is green).
    ALLOWED_PREFIXES_REVIEW -> "review": code (resources/js/**), PR opened for human review — NEVER auto-merged.
Anything else, or any denied substring, is SKIPPED.

If any processed file is "review" tier, this script writes `review_required=true` to $GITHUB_OUTPUT
so the workflow opens the PR for review instead of auto-merging.
"""
import os
import re
import sys
from pathlib import Path

INBOX = Path("cowork-inbox")
ALLOWED_PREFIXES = ("prototipo-ui/", "memory/", "docs/")       # auto-merge once green
ALLOWED_PREFIXES_REVIEW = ("resources/js/",)                   # code -> human review, never auto-merge
DENY_SUBSTRINGS = ("..", ".github/", ".claude/")               # never reachable, even via review tier
MAX_SIZE_BYTES = 1_000_000
SKIP_FILES = {"README.md", ".gitkeep"}

HEADER_RE = re.compile(r"<!--\s*cowork:\s*([\w-]+):\s*(.+?)\s*-->")


def parse_headers(content: str) -> dict[str, str]:
    return {m.group(1): m.group(2) for m in HEADER_RE.finditer(content)}


def strip_headers(content: str) -> str:
    return HEADER_RE.sub("", content).lstrip("\n")


def classify_path(path: str) -> tuple[str | None, str | None]:
    """Return (tier, error). tier is 'auto', 'review', or None when blocked."""
    if any(s in path for s in DENY_SUBSTRINGS):
        return None, f"denied substring in {path!r}"
    if any(path.startswith(p) for p in ALLOWED_PREFIXES):
        return "auto", None
    if any(path.startswith(p) for p in ALLOWED_PREFIXES_REVIEW):
        return "review", None
    return None, f"path {path!r} not in whitelist {ALLOWED_PREFIXES + ALLOWED_PREFIXES_REVIEW}"


def process_file(filepath: Path) -> tuple[str, str | None]:
    """Return (log_message, tier). tier is the written file's tier, or None when skipped."""
    size = filepath.stat().st_size
    if size > MAX_SIZE_BYTES:
        return f"SKIP {filepath} (size {size} > {MAX_SIZE_BYTES})", None

    content = filepath.read_text(encoding="utf-8")
    headers = parse_headers(content)
    body = strip_headers(content)

    target = headers.get("target")
    append_to = headers.get("append-to")

    if target and append_to:
        return f"SKIP {filepath} (both target and append-to set)", None
    if not target and not append_to:
        return f"SKIP {filepath} (no target/append-to header)", None

    dest = target or append_to
    tier, err = classify_path(dest)
    if tier is None:
        return f"SKIP {filepath} ({err})", None

    dest_path = Path(dest)
    dest_path.parent.mkdir(parents=True, exist_ok=True)

    if target:
        dest_path.write_text(body, encoding="utf-8")
        action = "WRITE"
    else:
        existing = dest_path.read_text(encoding="utf-8") if dest_path.exists() else ""
        sep = "\n" if existing and not existing.endswith("\n") else ""
        with dest_path.open("a", encoding="utf-8") as f:
            f.write(sep + body)
        action = "APPEND"

    filepath.unlink()
    return f"{action} [{tier}] {filepath} -> {dest}", tier


def emit_review_flag(review_required: bool) -> None:
    value = "true" if review_required else "false"
    gh_output = os.environ.get("GITHUB_OUTPUT")
    if gh_output:
        with open(gh_output, "a", encoding="utf-8") as fh:
            fh.write(f"review_required={value}\n")
    print(f"review_required={value}")


def main() -> int:
    if not INBOX.exists():
        print("cowork-inbox/ does not exist; nothing to do")
        emit_review_flag(False)
        return 0

    files = sorted(
        f for f in INBOX.iterdir() if f.is_file() and f.name not in SKIP_FILES
    )
    if not files:
        print("inbox empty; nothing to do")
        emit_review_flag(False)
        return 0

    print(f"Found {len(files)} file(s) in inbox")
    tiers: list[str] = []
    for f in files:
        try:
            result, tier = process_file(f)
            print(f"  {result}")
            if tier is not None:
                tiers.append(tier)
        except Exception as e:
            print(f"  ERROR processing {f}: {e}", file=sys.stderr)
            return 1

    emit_review_flag(any(t == "review" for t in tiers))
    return 0


if __name__ == "__main__":
    sys.exit(main())
```

> Nota: o `import subprocess` original era morto — removido. O header `commit:` continua parseado mas não-usado (igual ao main de hoje); fora de escopo wirar/remover agora.

---

## 2. `.github/workflows/cowork-inbox.yml` — conteúdo final (sobrescrever)

Mudanças: step `Process inbox` ganha `id: process` e exporta `changed`/`branch`/`review_required`; **novo** step decide **merge (--auto) vs review** com base em `review_required`. Para code: cria label `needs-review` (idempotente) + atribui `@wagnerra23` + **NÃO mergeia**.

```yaml
name: cowork-inbox

on:
  push:
    branches: [main]
    paths:
      - 'cowork-inbox/**'

permissions:
  contents: write
  pull-requests: write

concurrency:
  group: cowork-inbox
  cancel-in-progress: false

jobs:
  process:
    runs-on: ubuntu-latest
    # Loop guard: skip the commit this very workflow produces.
    if: "${{ !contains(github.event.head_commit.message, 'chore(cowork): inbox processed') }}"
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 2

      - uses: actions/setup-python@v5
        with:
          python-version: '3.12'

      - name: Configure git
        run: |
          git config user.name "cowork-inbox[bot]"
          git config user.email "cowork-inbox@users.noreply.github.com"

      - name: Process inbox
        id: process
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: |
          set -euo pipefail

          BRANCH="cowork/inbox-${GITHUB_SHA::7}"
          git checkout -b "$BRANCH"

          # Script writes `review_required=true|false` to $GITHUB_OUTPUT.
          python3 .github/scripts/cowork-inbox.py

          if [ -z "$(git status --porcelain)" ]; then
            echo "No changes after processing inbox; exiting."
            echo "changed=false" >> "$GITHUB_OUTPUT"
            exit 0
          fi

          echo "changed=true" >> "$GITHUB_OUTPUT"
          echo "branch=$BRANCH" >> "$GITHUB_OUTPUT"

          git add -A
          git commit -m "chore(cowork): inbox processed"
          git push origin "$BRANCH"

          gh pr create \
            --title "chore(cowork): inbox processed" \
            --body "Auto-processed by \`.github/workflows/cowork-inbox.yml\` from push ${GITHUB_SHA::7}." \
            --base main \
            --head "$BRANCH"

      - name: Merge (doc/memory) or request review (code)
        if: steps.process.outputs.changed == 'true'
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: |
          set -euo pipefail
          BRANCH="${{ steps.process.outputs.branch }}"

          if [ "${{ steps.process.outputs.review_required }}" = "true" ]; then
            echo "Código presente (resources/js/**) → PR aberto para review humano + CI. NÃO auto-merge."
            gh label create needs-review \
              --color FBCA04 \
              --description "Código autorado pelo Cowork; aguardando review do Wagner" \
              2>/dev/null || true
            gh pr edit "$BRANCH" --add-label needs-review --add-reviewer wagnerra23
          else
            echo "Somente doc/memória/protótipo → auto-merge quando os checks ficarem verdes."
            gh pr merge "$BRANCH" --auto --squash --delete-branch
          fi
```

---

## 3. Pré-requisitos / notas honestas pra Code surfar pro [W]

1. **Label `needs-review` não existe hoje** (verificado). O step a cria de forma idempotente (`|| true`) — sem ação manual.
2. **`@wagnerra23` é reviewer válido** (owner do repo; PR é autorada pelo bot, então pedir o owner é permitido).
3. **Branch protection NÃO é tocada** (escolha [W]). O "código espera review" é garantido por **convenção** (o workflow não mergeia + label + reviewer atribuído), **não** por branch protection — porque `resources/js/` é LIVRE no CODEOWNERS e `required_approving_review_count = 0`. Se algum dia [W] quiser **enforcement físico** (impossível mergear sem aprovação dele), o caminho é adicionar `resources/js/ @wagnerra23` ao CODEOWNERS — **fora de escopo agora**, registrado como opção.
4. **O PR deste handoff toca `.github/**`** → CODEOWNERS exige review de `@wagnerra23` no próprio PR de implementação. Bom: [W] revisa a mudança do workflow antes de ir pro main.
5. **DENY inalterado** (`..`, `.github/`, `.claude/`): Cowork não reescreve CI/guards por nenhum tier. Anti-escalada preservada.
6. **Doc/memória/protótipo agora usam `--auto`**: além de habilitar Onda D, conserta o merge imediato que quebraria sob os 16 checks.

---

## 4. Plano de teste

### 4.1 Local (script Python — rápido, determinístico)

```bash
cd <repo>
mkdir -p cowork-inbox _t
# (a) code tier -> review_required=true
printf '<!-- cowork: target: resources/js/Pages/Demo/_OndaDTest.tsx -->\nexport const x = 1\n' > cowork-inbox/code.md
GITHUB_OUTPUT=_t/out python3 .github/scripts/cowork-inbox.py
grep -q '^review_required=true$' _t/out && echo "OK code->review" || echo "FAIL"
# (b) doc tier only -> review_required=false
git checkout -- . ; git clean -fd cowork-inbox resources/js/Pages/Demo 2>/dev/null
printf '<!-- cowork: target: docs/_ondad_test.md -->\nhello\n' > cowork-inbox/doc.md
GITHUB_OUTPUT=_t/out2 python3 .github/scripts/cowork-inbox.py
grep -q '^review_required=false$' _t/out2 && echo "OK doc->auto" || echo "FAIL"
# (c) denied -> SKIP (não escreve, não conta tier)
printf '<!-- cowork: target: .github/workflows/evil.yml -->\nx\n' > cowork-inbox/evil.md
GITHUB_OUTPUT=_t/out3 python3 .github/scripts/cowork-inbox.py   # log: SKIP ... denied substring
# limpar artefatos de teste antes de commitar
git checkout -- . ; rm -rf _t cowork-inbox/code.md cowork-inbox/doc.md cowork-inbox/evil.md docs/_ondad_test.md
```

### 4.2 E2E (no main, 1 vez, depois do merge do PR de implementação)

- Dropar um arquivo inofensivo de **code tier** via inbox (`target: resources/js/Pages/Demo/_OndaDSmoke.tsx`) num commit em `main`.
- Esperado: workflow abre PR `cowork/inbox-<sha>`, **com label `needs-review` + `@wagnerra23` reviewer, e NÃO mergeada**. Os 16 checks rodam na PR.
- Fechar/limpar a PR de smoke.
- Repetir com **doc tier** (`target: docs/_smoke.md`) → esperado: auto-merge quando os checks ficarem verdes.

---

## 5. Fora de escopo (D-full, depois)

D-core ainda depende de **alguém** dar o push do arquivo pra `cowork-inbox/`. D-full (GitHub App / token escopado `cowork/*`, nunca `main`, nunca `.github/`) elimina até esse push. **Não** está neste handoff — decisão [W] separada, custo médio (provisionar App + segredo).

---

**Checklist de PR pra Code:** 1 PR, conventional commit (`feat(infra): cowork-inbox write-path de código com review-gate (Onda D-core)`), ≤300 linhas, toca só os 2 arquivos `.github/**`, descrição linka este handoff + `_PROPOSTA` de origem. Espera review `@wagnerra23` (CODEOWNERS `.github/`).
