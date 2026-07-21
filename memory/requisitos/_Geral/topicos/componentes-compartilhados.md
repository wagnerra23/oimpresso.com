---
id: componentes-compartilhados
module: _Geral
title: "Componentes compartilhados entre módulos"
kind: capacidade
status: ativo
updated_at: "2026-07-21"
review:
  state: revisado-central
  verdict: concordo
  confidence: alta
  central_reviewer: codex
  human_approver: null
critiques:
  - critic: codex-central
    at: "2026-07-21"
    verdict: concordo
    summary: "A porta geral reduz duplicação, desde que inventário não seja confundido com autorização de uso."
    evidence:
      - type: codigo
        ref: "scripts/reuse-index.mjs"
claims:
  - id: C01
    status: observado
    text: "O catálogo compartilhado é derivado das raízes gerais e separa componentes React e Blade por papel."
    evidence:
      - type: codigo
        ref: "scripts/governance/module-surface.mjs"
  - id: C02
    status: observado
    text: "A busca de símbolos reutilizáveis já possui um índice derivado, em vez de depender de lista manual."
    evidence:
      - type: codigo
        ref: "scripts/reuse-index.mjs"
---

# Componentes compartilhados entre módulos

## Escopo

- **Inclui:** componentes React em `resources/js/Components/` e componentes Blade em
  `resources/views/components/` que podem ser consumidos por mais de um contexto.
- **Não inclui:** componentes co-localizados em `resources/js/Pages/<Modulo>/`; eles
  pertencem à SUPERFÍCIE do módulo e só viram gerais após decisão explícita de ownership.

## Parecer crítico

**Concordo com uma porta geral única.** Ela responde “isso já existe?” antes de a IA criar
ou copiar outro componente. O inventário é derivado em [`../SUPERFICIE.md`](../SUPERFICIE.md),
e a busca por intenção/símbolo pertence a `reuse-index.mjs`.

## Fazer: benefícios e custos

- **Benefício:** reduz duplicação e dá um endereço estável para qualquer IA encontrar reuso.
- **Custo:** um componente geral exige contrato mais estável e raio de consumidores maior.

## Não fazer: benefícios e custos

- **Benefício de manter algo local:** preserva autonomia quando o comportamento ainda é
  específico ou experimental.
- **Custo de não promover um componente realmente comum:** cópias divergem e correções
  precisam ser repetidas.

## Limites

- Presença no catálogo não prova qualidade, compatibilidade nem status canônico.
- Antes de usar, confirme export, contrato, consumidores e registry do Design System.
- Antes de promover um componente local para geral, conte consumidores e preserve imports.
