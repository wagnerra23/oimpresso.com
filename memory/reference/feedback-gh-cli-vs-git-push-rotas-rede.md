---
name: gh CLI api.github.com ≠ git push github.com — rotas diferentes podem cair separado
description: gh pr merge/create falha quando api.github.com timeout, mas git push em github.com:443 continua funcionando. UI navegador usa rota DNS diferente, geralmente passa.
type: feedback
---
Quando `gh pr merge <N>` ou `gh pr create` retorna `Post "https://api.github.com/graphql": dial tcp 4.228.31.149:443: connectex` consistentemente, **NÃO concluir que GitHub está fora**. Testar primeiro `git push` na branch — porque:

- `git push origin <branch>` usa `github.com:443` (HTTPS) ou `git@github.com:22` (SSH)
- `gh pr *` comandos usam **`api.github.com/graphql` ou `/rest`** (host diferente, IP diferente — 4.228.31.149)
- Wagner já viu `git push` passar normalmente enquanto `gh pr merge` retornava timeout por horas

**Why:** 2026-05-14 → 15. Durante sessão pivot CYCLE-05→06, `gh pr merge 853` deu timeout em ~7 tentativas espalhadas em 4h. Mesma sessão fez 3 `git push -u origin <branch>` sem nenhum problema. Não era queda de GitHub — era rota IPv4 do api.github.com bloqueada lado Wagner (proxy/firewall/DNS).

**How to apply:**
- Se `gh pr <ação>` falhar com `dial tcp 4.228.* timeout`, sugerir Wagner abrir UI navegador (Edge/Brave/Firefox) — DNS browser geralmente usa rota diferente
- Não retry `gh` em loop esperando rede voltar — abrir PR/merge pela UI é caminho de contorno canônico
- Workarounds de rede: Tailscale ON, hotspot celular (troca de ISP), VPN — mas só sugerir se Wagner não estiver com pressa
- Sempre que ferramenta CLI GitHub falhar consistente, mostrar URL UI clara: `https://github.com/<owner>/<repo>/pull/new/<branch>` ou `/pull/<N>`
- Push sempre tenta antes — se push falha junto, aí sim é rede ampla

**Não confundir com:**
- Token gh expirado — retorna 401, não timeout
- Rate limit — retorna 403, não timeout
- Push timeout em github.com:443 — aí é rede ampla mesmo, pular pra Tailscale ou esperar

**Refs:** sessão 2026-05-14 #853 + feat-secrets-fingerprint + wip-martinho-canary
