---
titulo: Onda 0d — Artefato + gate de paridade de migração (Blade↔React)
status: proposto
owner: W
criado: '2026-07-02'
etapa: onda-0-fundacao
related: ../PLANO-MESTRE.md
---

# Onda 0d — Paridade de migração (a pior dimensão: 8/100)

> Status vivo deste programa: [PLANO-MESTRE.md](../PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro).

## Objetivo

Fechar a dimensão de **pior nota da régua (8/100)**: 31 telas migradas de Blade→React sem
nenhuma verificação de que a migração preservou função — e **nenhum gate** cobrindo isso.
Âncora SOTA: **parallel-run / GitHub Scientist / strangler fig** ("proof rather than hope").
A auditoria da `/perfil` (2026-07-02) provou o valor: a paridade manual achou divergências
reais (tooltips perdidos, validação de senha divergente) que nenhuma régua via.

## O que construir

### 1. Artefato de paridade (template)

`memory/requisitos/<Mod>/<tela>-parity.md` — mapa campo-a-campo Blade↔React:

| Feature do Blade | Está no React? | Evidência (arquivo:linha) | Severidade se perdido |
|---|---|---|---|

Gerado por agente (barato — a auditoria da `/perfil` custou ~1 agente read-only), revisado
no PR da migração. O template nasce da auditoria `/perfil` desta sessão (formato validado).

### 2. Plug no processo MWART (não gate novo de presença)

- **Skill `mwart-process` (ADR 0104):** a fase F2 (BACKEND BASELINE) passa a exigir o
  `-parity.md` como entregável; F4 (QA) verifica os itens de severidade alta com teste.
- **Enforcement de comportamento** (lição da proibicoes §descartados): o gate NÃO é "o
  arquivo parity existe" — é o(s) teste(s) derivado(s) dele: campos-chave persistem
  (ex: `POST /perfil/update` grava `custom_field_1..4`). Presença ≠ correção.

### 3. Backfill das 31 migrações existentes — via ondas, não big-bang

Cada onda de módulo (template Passo 3) inclui a paridade das telas migradas **daquele módulo**.
Sells na Onda 1 cobre `Sells/Create` (charter cita `sell.create.blade.php`, live, sem paridade).
Não se abre PR de backfill global — o débito desce módulo a módulo, rastreado pela sentinela (0c).

## Critério de pronto

- Template `-parity.md` canônico publicado + skill `mwart-process` atualizada (F2/F4).
- 1 paridade piloto completa: a da `/perfil` (já auditada nesta sessão) formalizada como artefato.
- O censo da sentinela (0c) passa a contar "migração sem paridade" como dimensão do débito.

## Verificação

- Migração MWART nova sem `-parity.md` → F2 não fecha (processo, hook `block-mwart-violation`).
- O teste derivado da paridade **falha** se um campo mapeado deixar de persistir (red/green).

## Fora de escopo

- Parallel-run de tráfego real (verification proxy) — over-engineering pro tamanho atual;
  o artefato + teste derivado dá 80% do valor. Se um dia houver cutover de tela de dinheiro
  de alto volume, aí sim shadow-diff (registrar como emenda).
