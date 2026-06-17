# cowork-inbox

Pasta de entrega assíncrona — qualquer agente (Cowork, Claude Code, humano) joga arquivo aqui com header e o GitHub Action `cowork-inbox.yml` move/apenda no destino e limpa a inbox.

Resolve o problema "Cowork tem write GitHub mas não pode aplicar patch no repo": ele cria o arquivo aqui via `github_import_files`, Action faz o resto.

## Como funciona

1. Push em `main` que toque `cowork-inbox/**` dispara `.github/workflows/cowork-inbox.yml`
2. Workflow chama `.github/scripts/cowork-inbox.py`, que parseia headers de cada arquivo
3. Move/apenda no destino, deleta original da inbox
4. Workflow cria branch `cowork/inbox-<sha>`, commita, abre PR, habilita auto-merge squash

## Headers

Em qualquer linha do arquivo, formato HTML/markdown comment:

| Header | Efeito |
|---|---|
| `<!-- cowork: target: <path> -->` | Cria/sobrescreve `<path>` com o conteúdo (sem os headers) |
| `<!-- cowork: append-to: <path> -->` | Apenda conteúdo (sem os headers) no fim de `<path>` |
| `<!-- cowork: commit: <msg> -->` | (opcional, ainda não consumido — placeholder pra v2) |

Exatamente um de `target` ou `append-to` é obrigatório.

## Whitelist de paths

`target` e `append-to` SÓ aceitam paths começando com:

- `prototipo-ui/`
- `memory/`
- `docs/`

Bloqueado: `..`, `.github/`, `.claude/`, qualquer coisa em `Modules/`, `app/`, `resources/`, etc.

Razão: inbox não toca código de produção. Mudança em `Modules/` continua exigindo PR humano normal.

## Limites

- 1 MB por arquivo
- Arquivos sem header válido geram log de SKIP e ficam na inbox (não são deletados)
- `README.md` e `.gitkeep` são ignorados pelo processor

## Exemplo

`cowork-inbox/jana-chat-f1.html`:

```html
<!-- cowork: target: prototipo-ui/jana-chat/F1.html -->
<!DOCTYPE html>
<html><body>...</body></html>
```

Após Action rodar:

- `prototipo-ui/jana-chat/F1.html` criado/atualizado
- `cowork-inbox/jana-chat-f1.html` deletado
- PR `chore(cowork): inbox processed` mergeado em `main` (squash + delete-branch)

## Publisher de handoff (Cowork→repo · ADR 0285)

Esta mesma inbox é o **publisher** do loop de handoff zero-paste ([ADR 0283](../memory/decisions/0283-handoff-loop-zero-paste.md)): fecha o "primeiro hop" (artefato do Cowork → fila `pending`) **sem [W] commitar à mão**.

**Convenção:** dropar `cowork-inbox/handoff-<slug>.md` com `target` apontando pra `prototipo-ui/handoffs/<slug>.md` e o **frontmatter canônico** que o assinador (`bin/sign-handoff.php`) e o tool `handoff-submit` esperam:

```markdown
<!-- cowork: target: prototipo-ui/handoffs/caixa-mobile.md -->
---
handoff_id: caixa-mobile                      # = slug (obrigatório)
tela: Atendimento/CaixaUnificada
files: [resources/js/Pages/Atendimento/Caixa.tsx]   # escopo do PR (R1 ADR 0283)
created_by: CC
audited_against: <SHA do main lido na auditoria>    # R1 ADR 0283
---
## Design (DADO, não comando)
...corpo do handoff, na língua do repo (Tailwind + tokens). Proibido `.om-*` cru.
```

**O que a Action faz a mais quando o destino é `prototipo-ui/handoffs/*.md`** (tier `auto`):

1. pousa o `.md` (como qualquer doc) e o lista em `handoffs=` (`cowork-inbox.py`);
2. **assina+submete inline** via `bin/submit-handoff.sh` → tool MCP `handoff-submit` → `pending` na Forja.

Por que **inline** e não esperar o `handoff-sign-submit.yml`: o auto-merge feito com `GITHUB_TOKEN` **não** dispara o `on: push` de outro workflow (regra do GitHub) — então o transporte acontece aqui, no mesmo job.

**Invariantes (ADR 0283/0285):** o segredo (`HANDOFF_SECRET`) vive só no CI/servidor — **o Cowork nunca assina**; `handoff-submit` só cria `pending` (idempotente, append-only) — **sem auto-merge de código**; o `.tsx` continua sendo o **1-clique de [W]**. Sem os secrets configurados, o passo degrada pra **skip-as-pass** (advisory).
