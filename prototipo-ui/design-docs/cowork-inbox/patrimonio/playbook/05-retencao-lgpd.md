---
sessao: "05"
titulo: Retenção automática — tarefa retirada da execução
dono: "[CL]"
base: c7bd83944f
prefixo: nenhum
---
# 05 · Retenção automática — não executar

**Reconciliação em 2026-09-08:** a instrução de criar assetmanagement:retention-purge,
mesmo desligado, foi retirada. Contradizia a decisão de [W] de 27/07 registrada em
memory/proibicoes.md (§5, varredura automática por TTL), corroborada por _saida-04.md §9.

A configuração existente não autoriza implementar o executor. A medição também encontrou
tabelas inexistentes nessa configuração; o presence-test dela não comprovava execução.

O índice registra o bloqueio e não despacha esta tarefa. Não há decisão pendente sobre
“quando ligar”: eventual reabertura exige mudança explícita da decisão canônica de [W].
O texto anterior permanece no histórico Git (PR #6999); não é instrução vigente.
