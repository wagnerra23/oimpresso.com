# Sessão — revisão dos fluxos do sistema

Pedido: permitir que o Code pesquise e execute todos os passos necessários para revisar os fluxos
das máquinas, com cuidado explícito para não usar baseline como prova.

Entregue: revisor Node sem dependências, teste hermético, wiring no workflow, fluxo canônico e
inventário regenerado. Modos: relatório humano, `--json`, `--check`, `--execute`, `--live` e
`--strict`. Exit `2` representa medição viva solicitada e indisponível.

Provas executadas: bite-test verde; inventário 609/609 com zero missing/ghost; catracas verdes;
jornada completa verde; GitHub API viva respondeu pelos endpoints de rules e branch protection.

Achados não escondidos: 17 pendências. Treze são dimensões ausentes nos fluxos Cancelamento,
Deploy e Venda; quatro são máquinas citadas sem prova localizada. O `selftest-registry` também
encontrou quatro órfãos anteriores, fora do escopo desta implementação.
