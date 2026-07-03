---
titulo: Template de Onda por Módulo (gabarito reusável)
status: proposto
owner: W
criado: '2026-07-02'
related: PLANO-MESTRE.md
---

# Template — Onda por Módulo `<Mod>`

> Gabarito reusável do ciclo-padrão de 4 passos (ver [PLANO-MESTRE.md](PLANO-MESTRE.md)).
> Copie este arquivo para `onda-N-<mod>/` e troque `<Mod>` pelo módulo. **Não abrir onda sem OK [W].**

## Pré-check de encaixe (T6 — antes de abrir)

- [ ] O módulo pertence ao `_Roadmap_Faturamento.md`? → a onda **estende** aquele roadmap (nova seção/Onda numerada), **não** cria paralelo.
- [ ] O módulo tem roadmap ativo próprio (ex: OficinaAuto ROADMAP.md, PaymentGateway PLANO-ONDA5)? → **não interromper**; encaixar a régua no que já roda.
- [ ] É operacional sem programa (Compras/Produto/Cliente)? → precisa **OK [W]** explícito antes de abrir (toca o core).
- [ ] Existe sinal de cliente (ADR 0105)? Sem sinal + sem drift de métrica → é feature-wish, não onda ativa.

## Passo 1 — Adversário concorrente

- Rodar `capterra-senior <Mod>` → gera/atualiza `memory/requisitos/<Mod>/CAPTERRA-FICHA.md`.
- **Pronto quando:** ficha 0-100 + P0-P3 + 10-15 concorrentes globais/BR, formato canônico (10 seções).

## Passo 2 — Gaps + backlog + changelog

- Rodar `/comparativo <Mod>` → `CAPTERRA-INVENTARIO.md` (buckets ✅🟡❌) + batch `tasks-create` (MCP, **aguarda OK [W]** — publication-policy) + apenda US ao `<Mod>/SPEC.md` + changelog.
- **Pronto quando:** inventário + tasks propostas com `parent_audit` metadata.

## Passo 3 — Régua por tela (com comportamento plugado)

- Para cada tela do módulo: `screen-grade` (UX) **+** `casos_coverage` (UC-IDs que a defendem + status ✅/🧪/⬜) **+** dente de cálculo (D1) se toca valor/estoque.
- **Pronto quando:** cada scorecard mostra UX **e** cobertura de comportamento; gaps rankeados por exposição Tier-0.

## Passo 4 — Catraca + sentinela

- Registrar novos baselines (screen-grade + casos + exposição); `casos-gate` passa a defender os UCs do módulo; sentinela de cadência vigia o módulo.
- **Pronto quando:** simular regressão em qualquer camada → CI bloqueia.

## Ordem sugerida pós-Sells

**Compras (nota 59)** → **Produto** → **Cliente**. Compras primeiro por ser o mais fraco + tocar dinheiro/estoque + não ter FICHA/INVENTARIO/roadmap.
