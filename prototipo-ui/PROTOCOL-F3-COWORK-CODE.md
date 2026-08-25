# PROTOCOL-F3-COWORK-CODE.md — ponte de compatibilidade

> **Não é fonte operacional.** Este caminho permanece somente porque ADRs, handoffs e links
> históricos o referenciam.

O procedimento F3 que existia aqui misturava o protocolo v1, o caminho ZIP já aposentado,
testes locais proibidos e comandos de importação repetidos em outros arquivos. Manter essa cópia
ativa permitia que um agente escolhesse uma versão antiga e editasse o produto com preview
incompleto.

Use somente:

- [`PROTOCOL.md`](PROTOCOL.md) para política, papéis, autoridade e invariantes do loop v2;
- [`protocolo.config.mjs`](protocolo.config.mjs) para IDs, destinos, fases e comandos executáveis;
- o RUNBOOK por tela indicado pelo `protocolo.config.mjs` para a implementação de uma tela.

Para obter o painel vigente, execute `node prototipo-ui/protocolo.config.mjs`. A história anterior
continua disponível no git; não é copiada aqui para não voltar a competir com o cânone.

## Evolução

- 2026-08-18 — conteúdo operacional removido após a consolidação no
  `protocolo.config.mjs`; o path virou ponte de compatibilidade.
