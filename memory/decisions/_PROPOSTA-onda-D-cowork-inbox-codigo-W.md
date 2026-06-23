# _PROPOSTA · Onda D — write-path de CÓDIGO no cowork-inbox (derruba a Restrição 1)

> **Status:** PROPOSTA de [CC]. Toca `.github/**` (infra de CI) → **sensível, decisão [W]**. [CC] propõe, não aplica.
> **Origem:** `_PROPOSTA-protocolo-v2-colapso-W.md` (Onda D) — a onda de maior impacto. [W] aprovou avançar 2026-06-16.
> **verificado vs main:** `.github/scripts/cowork-inbox.py` + `.github/workflows/cowork-inbox.yml` lidos nesta sessão.

---

## 1. Como funciona HOJE (confirmado no main)
- `cowork-inbox.yml`: push em `main` tocando `cowork-inbox/**` → branch `cowork/inbox-<sha>` → roda o script → **`gh pr merge --squash` (AUTO-MERGE)**.
- `cowork-inbox.py`: lê header `<!-- cowork: target:/append-to:/commit: -->`, escreve no destino, **se** o path começa com `prototipo-ui/`/`memory/`/`docs/` (whitelist) e não contém `..`/`.github/`/`.claude/` (deny). Cap 1 MB.
- **Resultado:** Cowork JÁ escreve doc/memória/protótipo no git, automático. **Código não** — e o auto-merge atual seria inseguro pra `resources/js/**`.

## 2. O problema que a Onda D resolve
O `COLE_NO_CODE` + você colando manualmente existe **só** porque `resources/js/**` não tem write-path.
Estender o inbox pra código — **atrás de review, nunca auto-merge** — elimina o transporte manual de código.

## 3. D-core (barato, seguro) — 2 arquivos, ~40 linhas

### 3.1 `cowork-inbox.py` — segunda whitelist, tier "review"
```python
ALLOWED_PREFIXES        = ("prototipo-ui/", "memory/", "docs/")   # auto-merge ok
ALLOWED_PREFIXES_REVIEW = ("resources/js/",)                       # código → SÓ via review
# DENY continua: ("..", ".github/", ".claude/")  ← Cowork NUNCA reescreve CI/guards (anti-escalada)

def classify_path(path):
    if any(s in path for s in DENY_SUBSTRINGS):           return None        # bloqueado
    if any(path.startswith(p) for p in ALLOWED_PREFIXES): return "auto"      # fast-path
    if any(path.startswith(p) for p in ALLOWED_PREFIXES_REVIEW): return "review"
    return None
```
- `process_file` usa `classify_path`; se `None` → SKIP (como hoje).
- Coletar se **algum** arquivo processado foi tier `review` → no fim, se sim, **escrever um marcador** `cowork-inbox/.REVIEW_REQUIRED` (ou `print("REVIEW_REQUIRED")` que o workflow grepa).

### 3.2 `cowork-inbox.yml` — não auto-mergear quando há código
```yaml
      - name: Process inbox            # (após rodar o script)
        run: |
          ...
          gh pr create --title "..." --base main --head "$BRANCH"
          if [ -f cowork-inbox/.REVIEW_REQUIRED ] || grep -q REVIEW_REQUIRED proc.log; then
            gh pr edit "$BRANCH" --add-label needs-review --add-reviewer wagnerra23
            echo "Código presente → PR aberto para review humano + CI. NÃO auto-merge."
          else
            gh pr merge "$BRANCH" --squash --delete-branch   # doc/memória: fast-path como hoje
          fi
```
- **Branch protection no `main`** (se ainda não): required checks (typecheck · lint · testes · `a11y-axe-gate`) + 1 review. É a trava física que segura o código autorado pelo Cowork.

### 3.3 O que D-core elimina
- `COLE_NO_CODE.md` + relay manual de **código** → some. [CC] põe o `.tsx` final num arquivo `cowork-inbox/` com `target: resources/js/Pages/.../X.tsx`; vira PR atrás de CI+review.
- O transporte de **doc/memória** já era automático — agora código entra no mesmo cano, só que sem auto-merge.

## 4. D-full (mais fundo) — Cowork empurra sozinho, R1 cai de vez
D-core ainda depende de **uma** coisa: alguém faz o push do arquivo pra `cowork-inbox/`.
Pra eliminar isso: **GitHub App / token escopado** que o Cowork usa pra push direto em branches `cowork/*`
(NUNCA em `main`, NUNCA em `.github/**`). Aí nem o push do inbox precisa de você.
- **Custo:** provisionar o App, segredo, escopo. **Risco:** superfície de credencial — por isso o escopo é só `cowork/*` + deny `.github/`/`.claude/`, e `main` segue protegido por review.

## 5. Esforço honesto
| Peça | Tamanho | Risco |
|---|---|---|
| D-core (`.py` + `.yml` + branch protection) | **pequeno** (~40 linhas + config) | baixo — código nunca auto-mergeia; CI+review seguram |
| D-full (App/token escopado) | **médio** (provisionamento + segredo) | médio — credencial; mitigado por escopo `cowork/*` + deny `.github/` |

## 6. Riscos & mitigação (honesto)
- **TSX autorado por [CC] pode não compilar / não casar tipos.** → `typecheck` como **required check**: PR vermelho não mergeia. Review humano fecha.
- **Write-path é poderoso.** → DENY `.github/`/`.claude/` impede o Cowork reescrever os próprios guards (anti-escalada). Código sempre atrás de review.
- **Não vira bypass do design loop.** → o `.tsx` no inbox ainda nasce do F1 (protótipo) + tradução; o PR carrega os dois.

---

**Decisão = [W]** (toca `.github/**`). Recomendo **D-core primeiro** (pequeno, seguro, já remove o relay manual de código);
D-full depois, se você quiser zerar até o push do inbox. [CC] não aplica — Code implementa sob OK de [W], em PR.
Pergunta pra [W]: **gero o handoff do D-core pro Code** (diffs prontos no `.py`/`.yml`)?
