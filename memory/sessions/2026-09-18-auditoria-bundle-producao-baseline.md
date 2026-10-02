---
date: "2026-09-18"
topic: "Comprovação da importação, recibos até produção e limites dos baselines"
authors: [C]
prs: []
---

# Auditoria do bundle e produção

## TL;DR

Auditoria em leitura a pedido de Wagner. No snapshot main 63ad9591c4435a29a135589cc199fa0d695def2a, 705 fontes de Wagner conferiram por SHA-256, sem extras/ausentes/divergentes. Recálculo do motor canônico encontrou 164 relações fonte→destino (salvo: 162); 4 validated exclusivamente host=ci, 61 compared, 71 anchored e 28 to-create. Não se comprovou ponta a ponta em produção. Nenhum baseline, fonte de produto ou ledger operacional foi modificado.

PR #7445 MERGED; deploy do merge foi sync leve, sem runtime. Verde visual do PR foi skip-as-pass: pixel-diff skipped. Proteção viva de main não exige visual-regression; zona cinza autoaprovada nos PRs no workflow consultado. Quatro suites Node do protocolo passaram com controles negativos. Jobs antigos citados no ledger executaram Pest; diff vazio no escopo consultado sustenta reuso limitado, sem crédito visual. Smoke real /arquivos redirecionou ao login; faltou sessão autenticada no biz=1. MCP do projeto indisponível, sem estado vivo inventado.

Provas e limites: [relatório local](../../.audit/bundle-2026-09-18/RELATORIO.md), com evidence.json e JSONs GitHub ao lado. Nenhum commit/push/merge nesta sessão. Não substituir tolerância de baseline por declaração de conformidade total.
