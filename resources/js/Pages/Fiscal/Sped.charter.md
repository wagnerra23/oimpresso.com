---
page_id: fiscal-sped
url: /fiscal/sped
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_adrs: [0093, 0094, 0101, 0104]
prototypes:
  - "prototipo-ui/.../fiscal-data.jsx SPED_PERIODOS/LIVROS"
---

# Charter — `Fiscal/Sped`

## Mission

**Placeholder no PR #3** — gerador SPED Fiscal (EFD ICMS/IPI + EFD-Contribuições) é integração complexa que merece PR dedicado. Esta tela apresenta panorama dos 5 últimos meses com contagens de notas autorizadas como referência cru, e botões de export desabilitados.

## Goals (DoD PR #3)

1. 5 últimos meses → contagem `NfeEmissao` autorizadas + valor
2. Status estimado (mês atual = aberto · M-1 = pronto · M-2+ = entregue) — visual apenas
3. Notice claro "em desenvolvimento"
4. Permissão `fiscal.sped.export`
5. Export buttons desabilitados (PR futuro)

## Non-Goals (PR #3)

- ❌ Gerador SPED real (TXT layout SPED Fiscal) — PR dedicado
- ❌ EFD-Contribuições (PIS/COFINS) — PR dedicado
- ❌ Livros fiscais (Apuração ICMS/ISS, Conciliação SEFAZ × ERP) — backlog
- ❌ Workflow de validação contador → entrega SEFIN — backlog

## Anti-hooks

- 🚫 NÃO emitir SPED real até implementação canônica (formato CONFAZ é crítico — gerar TXT inválido pode causar penalidade fiscal)
- 🚫 NÃO sugerir prazo de entrega via cron auto (legal/contador decide)
- 🚫 Notice visual obrigatório — deixar claro que é placeholder
