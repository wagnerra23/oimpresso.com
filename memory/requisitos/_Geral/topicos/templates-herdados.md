---
id: templates-herdados
module: _Geral
title: "Layouts e templates herdados"
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
    summary: "Separar herança geral de ownership modular evita copiar shells e templates em cada briefing."
    evidence:
      - type: codigo
        ref: "scripts/governance/criar-tela.mjs"
claims:
  - id: C01
    status: observado
    text: "Layouts React, layouts Blade e templates de construção possuem raízes compartilhadas distintas."
    evidence:
      - type: codigo
        ref: "scripts/governance/module-surface.mjs"
  - id: C02
    status: observado
    text: "A criação de telas aplica arquétipos por máquina e importa layouts compartilhados."
    evidence:
      - type: codigo
        ref: "scripts/governance/criar-tela.mjs"
---

# Layouts e templates herdados

## Escopo

- **Layouts React:** `resources/js/Layouts/`.
- **Layouts Blade:** `resources/views/layouts/`.
- **Templates de construção do Design System:**
  `memory/requisitos/_DesignSystem/templates/`.
- **Não inclui:** layout exclusivo de um módulo nem cópia histórica dentro de um RUNBOOK.

## Parecer crítico

**Concordo com a seção geral**, mas “herdado” significa “candidato compartilhado com dono
único”, não “todo módulo usa automaticamente”. A tela continua responsável por declarar e
testar qual shell/layout realmente consome.

## Fazer: benefícios e custos

- **Benefício:** mudanças no shell e nos padrões de criação têm um dono encontrável.
- **Custo:** alterações compartilhadas possuem raio amplo e exigem mapa de consumidores.

## Não fazer: benefícios e custos

- **Benefício de um layout local:** permite uma necessidade genuinamente específica.
- **Custo de copiar template herdado:** cria variantes quase iguais que deixam de receber
  correções do canon.

## Regra operacional

1. Consulte [`../SUPERFICIE.md`](../SUPERFICIE.md) para localizar o arquivo atual.
2. Confirme os consumidores reais antes de editar um layout compartilhado.
3. Para tela nova, use `scripts/governance/criar-tela.mjs`; não copie uma Page antiga.
4. Se o contrato compartilhado não servir, registre a divergência no tópico antes de
   criar uma variante.
