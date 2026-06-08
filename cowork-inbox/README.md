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
