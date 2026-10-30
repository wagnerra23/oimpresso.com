# Venda V3 — edição, onda 1

Em 2026-10-30, a frente preparou os serviços de acesso/base e o adaptador de carregamento/envio
da edição, sem alterar CreateV3.tsx. Detalhes, impacto e dependências na
[sessão](../sessions/2026-10-30-session-02-venda-v3-edicao.md).

Branch: `codex/venda-v3-edicao`, base final `664a23fb92` (#10226 já mergeado). Integração da Page foi reservada à sequência
coordenada. Sem merge/deploy/ativação. Pagamentos foram preservados como somente leitura;
subunidades/combos/modificadores/novas linhas ainda dependem de integração específica; reparo é onda 2.

## Estado MCP no momento do fechamento

As ferramentas próprias do oimpresso não estavam disponíveis no catálogo. Nenhum snapshot de
cycle/tasks/decisões foi inventado. A consulta remota inicial encontrou #10226 aberto e main em #10225.

## Validação

19 testes JS passaram; lint, tipagem dos arquivos novos e sintaxe PHP passaram. No CT100,
56 testes passaram e 2 de reparo falharam; ambas as falhas foram reproduzidas no main sem esta
mudança. PHPStan dos dois serviços passou. O teste de salvamento da base V3 conservou total,
linhas, estoque e pagamentos mesmo com `sell.payments` habilitado.

Publicação pendente: a revisão automática de aprovação recusou o push por ausência de autorização
explícita. Não houve push/PR. A branch e os commits foram mantidos locais, com recibos JUnit salvos.
