---
name: Nunca publicar credenciais no chat
description: Senhas, tokens, API keys, secrets NUNCA aparecem em texto no chat — mesmo as que já estão em handoff/session log/contexto. Repetir amplifica exposição.
type: feedback
---
**Regra:** NUNCA ecoar valor literal de credencial (senha, token, API key, secret, certificado privado, cookie de sessão) em mensagem do chat — mesmo se a credencial já apareceu em handoff, session log, ADR, ou contexto carregado.

**Why:** Cada repetição expande o blast radius. O transcript do Claude Code é logado (Anthropic + máquina local + memória do dev). Se a credencial está num handoff de 1 sessão, ela já está vazada uma vez — repetir no chat de uma sessão nova multiplica a exposição. Wagner corrigiu 2026-05-11 após eu ecoar uma senha admin Langfuse do handoff 2026-05-10-2340 no início de uma sessão nova.

**How to apply:**
- Referenciar credencial por *handle* / *nome no Vaultwarden* / *path do .env*, NÃO pelo valor: "senha admin Langfuse do handoff" / "item `langfuse-admin-wagner` no Vaultwarden" / "valor em `oimpresso-local/vault-refs.md`" — NUNCA o valor literal
- Pra rotacionar: gerar nova senha em variável PowerShell + `Set-Clipboard` → colar no form via Ctrl+V → confirmar saída via "está no clipboard, cole em X" sem ecoar.
- Senha velha pra fazer login no fluxo de rotação: também pelo clipboard (`Get-Content ~/.claude/oimpresso-local/vault-refs.md | Select-String langfuse | Set-Clipboard`) — Chrome paste, não chat.
- Em logs/screenshot/output de ferramentas: redact com `[REDACTED]` antes de citar.
- Vale igual pra tokens MCP, HOSTINGER_API, NEXTAUTH_SECRET, ENCRYPTION_KEY (que NUNCA muda), MINIO_ROOT_PASSWORD, qualquer chave SSH, cookies de sessão.
- Se a credencial já foi exposta em uma sessão (handoff registra), tratá-la como **comprometida** e candidata a rotação — não como "documentada e ok".

**Anti-padrão:** "Pega a senha X do handoff" / "uso a senha foo do .env" mostrando o valor por "didática". Não tem didática que justifique. Confirme com Wagner *qual* credencial, não *qual valor*.
