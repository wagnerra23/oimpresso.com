---
name: Auto-merge sempre quando CI verde
description: Wagner não quer ser bottleneck em revisar/clicar merge — habilitar auto-merge em todo PR aberto pelo Claude assim que conteúdo estiver aprovado por ele
type: feedback
---
Quando Wagner aprovar o conteúdo de um PR (verbalmente: "merge", "manda", "faz", "pode mergear", etc), habilitar **auto-merge** ao invés de tentar mergear no momento. Comando canônico:

```bash
gh pr merge <NUM> --auto --squash --delete-branch
```

`--auto` faz o GitHub mergear sozinho assim que todos os required checks ficarem verdes — não precisa esperar/poll.

**Why:** 2026-05-09, P0 hotfix #301 (drift Fase 2b rotas Jana). Wagner não quer aguardar/clicar manual em PR de hotfix; quer pipeline fluído. Disse explicitamente: *"habilite em todos, ficou verde merge sempre"*.

**How to apply:**
- Em **PRs abertos pelo Claude** (com aprovação explícita Wagner), aplicar `--auto` por padrão
- Em **PRs de outros** (Felipe, Maiara, Eliana, Luiz), só com aprovação ativa do Wagner — não presumir
- Continua respeitando publication-policy: Wagner é quem **aprova**, mas Claude pode usar `--auto` pra mecânica do merge
- `--squash --delete-branch` é o estilo canônico do projeto (histórico limpo)

**Não confundir com:** mergear sem aprovação. A aprovação ainda vem do Wagner; o auto-merge só substitui o "espera CI verde + clica botão" por "GitHub faz quando puder".
