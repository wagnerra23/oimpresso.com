---
date: "2026-09-22"
time: "10:51 BRT"
slug: cobertura-modular-funil-design
tldr: "O funil entende paths nWidart, mas não cobre todos os módulos: há 80 Pages e 37 charters em sete módulos, nenhuma rota modular no smoke e a persistência descartava reviews modulares. A #7648 corrige a persistência, inclusive para o primeiro review não rastreado."
prs: [7648]
decided_by: [W]
related_adrs:
  - 0410-ratificacao-zero-baseline-no-funil-design
next_steps:
  - "Cadastrar rota de smoke para cada Page modular que deve chegar a produção"
  - "Classificar as 43 Pages sem charter e criar charters para as que forem telas reais"
  - "Produzir recibo de produção por tela modular e SHA implantado"
---

# Cobertura modular do funil de design

## Resultado

**Não há cobertura universal.** A mecânica estrutural reconhece
`Modules/<Módulo>/Resources/js/Pages`, mas a árvore atual tem 80 Pages em sete módulos, 37
charters e zero rota modular no catálogo de smoke. Sem rota, a #7648 passa a falhar como
`NÃO MEDIDO`, que é o resultado correto.

Também foi corrigido o último elo de persistência: o workflow copiava `review.md` modular,
mas só verificava e adicionava paths do core. Agora usa a lista exata de arquivos copiados e
`git status --porcelain`, que inclui o primeiro review ainda não rastreado.

## Estado MCP no momento do fechamento

As tools MCP de ciclos, tarefas, sessões e decisões não estavam disponíveis nesta sessão do
Codex. A cobertura foi medida diretamente na árvore Git e nos scripts/workflows da PR #7648.
