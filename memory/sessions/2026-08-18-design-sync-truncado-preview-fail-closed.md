# Sessão — DesignSync truncado e preview fail-closed

**Data:** 2026-08-18 10:45 BRT  
**Pedido [W]:** ler o protocolo Claude Code → Design, explicar por que o DesignSync baixou CSS mas
sem drawers/eventos e corrigir o fluxo sem continuar editando produto.

## Diagnóstico

- `jana-merge.jsx` já continha drawer e eventos;
- o JSON real de `_ds_bundle.js` tinha `truncated:true` e terminava no meio de uma string;
- o exportador ignorava o metadado e o preview aceitava bundle/fontes ausentes com exit 0;
- a degradação do runtime ocultava os componentes interativos;
- seis PRs de produto já mergeados confirmaram que o agente foi além do download.

## Entrega

PR [#5910](https://github.com/wagnerra23/oimpresso.com/pull/5910), commit `2a0e30f17`:

- rejeição atômica de payload truncado;
- decodificação correta de base64 binário;
- rota `--ds-runtime` com destino único e proteção contra traversal;
- `--preview-ds` fail-closed e validação sintática do bundle;
- testes BITE/E2E e propagação do STOP ao hook, protocolo e skill.

Nenhum arquivo de produto foi editado ou revertido nesta branch.

## Validação e pendência

Suites do comparador, configuração e hooks DesignSync passaram; `node --check` e `git diff --check`
passaram. O bundle completo e três fontes mono ainda dependem de login Claude/DesignSync. O preview
permanece vermelho por desenho até esses quatro artefatos chegarem completos.

MCP/DesignSync não estava disponível como ferramenta nesta sessão; o navegador interno parou no
login. Não foi criada ADR: a mudança fecha uma violação do protocolo existente, sem escolher nova
arquitetura.
