---
name: Quick Sync action quebrada (descoberto 2026-04-26)
description: GH Action quick-sync.yml falhou em todos os 10 últimos pushes pra 6.7-bootstrap; falha no Setup SSH em ~5s; secrets SSH precisam ser revisados
type: project
originSessionId: 866e50c8-744a-42e4-8e79-7470bb472801
---
`.github/workflows/quick-sync.yml` está **falhando há 24+ horas** em TODOS os pushes pra `6.7-bootstrap`. Sintoma: action completa em 5-8s com `Process completed with exit code 1` no step "Setup SSH". Significa Hostinger NÃO recebe deploys automáticos via essa action.

**Why:** Provável rotação/expiração da `SSH_PRIVATE_KEY` (ou `SSH_HOST`/`SSH_PORT`/`SSH_USER` mudaram) sem atualizar os GH secrets. Erro silencioso — ninguém percebeu até 2026-04-26 quando Wagner reportou que módulo Copiloto não aparecia no menu (na verdade o código nem chegou lá).

**How to apply:**
- Quando Wagner perguntar "tá no Hostinger?" sempre verificar PRIMEIRO `gh run list --workflow=quick-sync.yml --limit=5` antes de assumir deploy aconteceu.
- Se `failure` recente → alertar Wagner que precisa fixar secret + re-rodar workflow ou deploy manual.
- Se Wagner pedir verificação online sem mencionar deploy: rodar `curl -s4 -o /dev/null -w "HTTP %{http_code}\n" https://oimpresso.com/<rota-nova>` antes de afirmar que está deployado.
- SSH manual **funciona** em `148.135.133.115:65002 u906587222` com chave `~/.ssh/id_ed25519_oimpresso` (comprovado em deploy manual `039a810d` no fim de 2026-04-26). Detalhes em `reference_hostinger_ssh_credenciais.md` e bloco "Acesso à produção" do CLAUDE.md.
- Se quick-sync.yml falhar, fazer deploy manual via SSH em vez de esperar fix do workflow:
  ```bash
  ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    "cd domains/oimpresso.com/public_html && git pull origin 6.7-bootstrap && \
     php artisan optimize:clear && composer dump-autoload"
  ```

**Próximo passo**: Wagner pode rodar deploy manual (receita acima) sempre que push falhar. Pra resolver de vez, atualizar `SSH_PRIVATE_KEY`/`SSH_HOST=148.135.133.115`/`SSH_PORT=65002`/`SSH_USER=u906587222` em GitHub Secrets do repo.
