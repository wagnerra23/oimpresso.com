---
review_round: W31-R1
tela: /nfe-brasil/tributacao
component: resources/js/Pages/NfeBrasil/Tributacao/Index.tsx
charter: PRESENTE (Index.charter.md)
reviewer: claude (W31 bulk static)
review_date: 2026-05-17
modulo: NfeBrasil
status: live
loc: 384
---

# Review estático — NfeBrasil/Tributacao/Index

## Cabeçalho
- US: US-NFE-010 fase 2 + US-NFE-TPL-001 templates
- ADRs: 0029, satélite arq/0006 cascade-defaults-ncm-produto

## Pontos fortes
- Charter presente — boa governança
- 3 zonas claras: Templates rápidos → Config padrão → Regras NCM
- Templates por setor com ícones (`shopping-bag/package/printer`) + badges (regime/UF/modelo NFe)
- Config sem preencher = Card border destructive + Badge "Pendente" — sinalização forte
- Switch `auto_emission_enabled` per-business — gate explícito (ADR 0093 Tier 0)
- Mensagem do switch deixa claro: "Habilite só após validar smoke fiscal em homologação"
- Cascade ordem mostrada inline no footer: 1 Produto → 2 NCM+UF+UF → 3 NCM → 4 Padrão
- `confirm()` em aplicar template + remover regra
- `formatNcm` com pontuação `4901.99.00` correta
- Botões Import CSV + Nova regra agrupados

## Riscos / gaps
1. **`confirm()` nativo** em `aplicarTemplate` e `remover` — destrutivo em prod (template substitui config). P1
2. Template aplicar **sobrescreve config existente** sem diff visual prévio — user não vê o que muda antes. P1 PREVIEW MISSING
3. `Switch` toggle `auto_emission_enabled` sem confirm — clicar errado dispara emissão automática real em vendas. P0 CRITICAL
4. `regras.length === 0` → mensagem "defaults atendem todos NCMs". Mas se config também ausente, user fica sem nada e sem aviso. P2 EDGE
5. Tabela regras sem paginação — se 200+ regras render tudo. P2 PERF
6. Sem filtro por NCM/UF na lista — tabela longa = scroll. P2 UX
7. `auto_emission_enabled ?? false` — coerção nullable; se backend retorna undefined, default seguro mas Switch fica `checked=false` mesmo se DB tem `null` indeterminado. P3
8. Sem activity log inline mostrando quem ativou auto-emission e quando — audit gap. P2

## Multi-tenant
- `regras`/`config`/`templates` scoped backend. Switch auto-emission per-business (charter cita ADR 0093 Tier 0).

## Recomendação
1. **AlertDialog confirm pro Switch auto-emission** (P0 — pode disparar emissão real acidental)
2. Substituir `confirm()` por AlertDialog em template/remover (P1)
3. Modal preview "diff: o que muda" antes de aplicar template (P1)
4. Paginação + filtro NCM na tabela regras (P2)
