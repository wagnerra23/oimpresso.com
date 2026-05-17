# Review Round 1 — Auditoria/Detail.tsx

**Tela:** `/auditoria/{id}` · **ADRs:** 0079, 0127 · **Charter:** ❌ ausente (apenas Index tem)
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Detail de 1 activity — Metadata grid (Quando/Entidade/Quem/business_id) + JSON properties (diff) em `<pre>` colorido. Fallback elegante "Atividade não encontrada" (pode não existir OU outro tenant). Link voltar pra `/auditoria`.

## Pontos fortes

- Empty state explícito menciona multi-tenant ("pertencer a outro tenant") — boa comunicação Tier 0
- Metadata em `<dl>` semântico (acessível)
- JSON pretty-print 2-space indent em `<pre>` com overflow-x-auto (não quebra layout)
- Footer cita Art. 9 + US-AUDIT-008 (RevertService pendente)
- `subject_type.split('\\').pop()` mostra só class name (não FQCN poluído)

## Riscos / gaps (top 5)

1. **R1 — Charter ausente** ([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)). Edit futura bloqueada. Criar `Detail.charter.md`.
2. **R2 — RevertService US-AUDIT-008 pendente mencionado mas sem UI placeholder.** Botão "Reverter ação" disabled com tooltip "em desenvolvimento" daria visibilidade da roadmap. Hoje footer só fala.
3. **R3 — JSON `properties` sem diff visual** (verde/vermelho) — `spatie/activity-log` salva `old`/`attributes`. Hoje despeja JSON cru — usuário precisa ler manual. Refator: diff component renderizando `old.X → attributes.X` lado a lado.
4. **R4 — `causer_type?.split('\\').pop()` hardcoded fallback `'User'`.** Se um Job dispara activity (`causer_type=Job\...`), exibe "User" errado. Usar `causer_type ? split[-1] : 'sistema'`.
5. **R5 — Sem link clicável pra subject** (`#{subject_id}`). Tipo "Sale #4321" deveria ser link pra `/sells/4321`. Gap UX — usuário precisa copiar ID e navegar manual.

## Veredito round 1

Detail enxuto e correto, mas **gaps de UX em diff/links** + RevertService pendente conhecido. **Pendências:** charter (R1), diff visual (R3), link subject (R5).

**Status:** APROVA com pendências P2.
