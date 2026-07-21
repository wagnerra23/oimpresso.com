---
module: _Geral
status: shared-infra
status_nota: "Porta transversal para herança e reuso entre módulos"
updated_at: "2026-07-21"
owner: W
related_adrs:
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
---

# BRIEFING — Geral / compartilhado

> **Função única:** orientar a IA para os artefatos herdáveis do núcleo. Este arquivo é
> índice; a lista de arquivos é gerada em `SUPERFICIE.md` e cada tema vive em um tópico.

## O que é

Contexto transversal usado quando uma tela ou módulo herda componentes, layouts ou
templates que não pertencem exclusivamente ao seu domínio.

## Portas canônicas

- **Superfície compartilhada derivada:** [`SUPERFICIE.md`](SUPERFICIE.md)
- **Componentes compartilhados:** [`topicos/componentes-compartilhados.md`](topicos/componentes-compartilhados.md)
- **Layouts e templates herdados:** [`topicos/templates-herdados.md`](topicos/templates-herdados.md)
- **Busca “reusar ou criar”:** `node scripts/reuse-index.mjs "<símbolo ou intenção>"`
- **Registry humano do Design System:** [`prototipo-ui/REGISTRY_DS_COMPONENTES.md`](../../../prototipo-ui/REGISTRY_DS_COMPONENTES.md)

## Regra de manutenção

1. Mudou uma raiz compartilhada: regenere `node scripts/governance/module-surface.mjs _Geral --write`.
2. Não copie a lista geral para BRIEFING de módulo; o módulo aponta para esta porta.
3. Existir no catálogo não significa “aprovado para qualquer uso”: valide contrato,
   consumidores e status antes de herdar.
4. Nova crítica entra no tópico correspondente; decisão de canon continua humana.
