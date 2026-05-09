# Cowork Inbox — automação validada

Workflow `.github/workflows/cowork-inbox.yml` testado end-to-end em **2026-05-09**.

## Pipeline confirmado

1. Push em `main` que toque `cowork-inbox/**` dispara workflow
2. Script `.github/scripts/cowork-inbox.py` parseia headers
3. Move conteúdo (sem header) pra path destino na whitelist
4. Deleta arquivo original da inbox
5. Action cria branch `cowork/inbox-<sha>`, commita, abre PR auto-merge squash

## Origem deste arquivo

Drop em `cowork-inbox/test-automation-validation.md` com header `target: docs/cowork-automation-validated.md`. Se este arquivo está em `docs/` e não na inbox, o pipeline funcionou.
