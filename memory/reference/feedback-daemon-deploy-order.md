---
name: Deploy daemon ANTES (ou junto) quick-sync Hostinger quando payload muda
description: Quando PR muda payload Laravel→daemon (ex chave renomeada), deploy daemon DEVE ir ANTES (ou simultâneo). quick-sync Hostinger é automático e rápido — janela de dessincronia quebra outbound em segundos.
type: feedback
---
# Sincronizar deploy daemon ANTES (ou junto) de quick-sync Hostinger quando payload muda

Quando 1 PR muda o payload que Laravel envia pro daemon (ex: PR #692 renomeou chave `mime` → `mimetype` no `SendMediaJob.php` + atualizou Zod schema do daemon pra aceitar ambas), a ordem de deploy importa:

1. **GitHub Actions `quick-sync.yml`** pega Hostinger automaticamente em merge na main (em segundos)
2. **Daemon CT 100** precisa rebuild manual via SSH Tailscale + `docker compose build` (mais lento)

Se eu **só admin merge e fico esperando**: quick-sync já atualizou Laravel pra mandar `mimetype`, mas daemon prod ainda só aceita `mime` → outbound de mídia **quebra na janela**.

**Why:** Caí 2026-05-12 com PR #692. Mesmo que o Zod no PR #692 ACEITASSE ambas chaves (back-compat 30d), o daemon prod CT 100 ainda rodava versão velha que só aceitava `mime` → o Laravel novo mandando `mimetype` ficaria rejeitado por validation Zod até o daemon ser rebuildado.

**How to apply:**
1. **Antes de admin merge** de PR que muda payload Laravel→daemon, garantir que: (a) o daemon prod **JÁ** aceita o payload novo (deploy daemon first), OU (b) faço o deploy daemon **imediatamente** após merge (segundos depois)
2. **Pattern recomendado** quando PR mexe AMBOS Laravel e daemon:
   - Step 1: rebase + admin merge
   - Step 2: imediatamente `tailscale ssh root@ct100-mcp` + deploy daemon (1 comando longo encadeado)
   - Step 3: NÃO avisar Wagner pra usar UI até deploy daemon concluído
3. **Schema com `.transform()` Zod aceitando AMBAS chaves antiga + nova** (com migration window 30d em comentário) é a forma segura de fazer rename sem race condition
4. **Pra PRs que só tocam Laravel ou só tocam daemon (não AMBOS)**: ordem normal — admin merge, deixar quick-sync rodar
5. **Pegadinha extra**: rebuild daemon = mass restart = QR-fest (ver feedback-daemon-qrfest.md). Combinar com Wagner janela antes
