---
date: "2026-09-21"
time: "08:50 BRT"
slug: zero-baseline-tier0
tldr: "A auditoria comprovou que baselines de tolerância deixam requireds verdes com dívida conhecida; a ADR 0409 e a primeira catraca Tier 0 foram preparadas. Model grandfathered tocado agora precisa sair com escopo, e a exceção NfeSefazStatus virou contrato nominal separado. Wagner autorizou commit, push e PR após revisar o conjunto concreto."
prs: []
decided_by: [W]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0208-larastan-baseline-ratchet
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
next_steps:
  - "Commit, push e PR da branch codex/zero-baseline-tier0 sob autorização R10 concedida"
  - "Acompanhar a lane No hardcode business_id e o ADR frontmatter no CI"
  - "Depois do Tier 0, migrar analisadores e lints para zero violação por arquivo tocado"
---

# Zero baseline de tolerância — primeira entrega Tier 0

## Estado MCP no momento do fechamento

As tools MCP do projeto não estavam disponíveis nesta sessão. Cycle, tasks e sessões vivas
não foram inferidos. O trabalho ficou em worktree isolada e não alterou o checkout principal.

## Resultado

- Auditoria versionada com contagens e limites da proteção atual.
- ADR 0409 proposta: tolerância não concede conformidade; contratos e evidências ficam
  separados; visual compara protótipo canônico e aplicação na mesma execução.
- Workflow envia os Models alterados ao teste Tier 0.
- Model grandfathered tocado e ainda infrator reprova.
- A exceção global de NfeBrasil saiu do baseline e virou contrato nominal com decisão.
- JSON, whitespace e índice de ADRs verificados localmente.

## Estado operacional

Branch `codex/zero-baseline-tier0`, worktree
`D:\oimpresso.com\.worktrees\codex-zero-baseline-tier0`. Wagner autorizou commit, push e PR
após revisar o resultado. Pest/PHPStan ficaram para CI/CT 100 por regra do repositório. O
merge da ADR proposta ratifica a decisão.
