# LIÇÕES [CC] — erros a não cometer de novo

> Escopo: design/[CC]. Subordinado a `memory/proibicoes.md` (canônico no git).
> Lido no **início** de todo chat (junto com STATUS + PROTOCOL + BRIEFING). Append-only.
> Formato por entrada: **Erro · Sintoma · Regra · Ref.**

---

## L-01 — Legislar memória / criar "lei suprema" própria
- **Erro:** redigi uma "Constituição acima dos ADRs" — sendo que o git **já tem** ADR 0094 (Constituição Oimpresso V2) e ADR UI-0013 (Constituição UI v2).
- **Sintoma:** documento meu se colocando acima do git; reinvenção de algo que já existia.
- **Regra:** a lei é do git (ADR 0094 + UI-0013 + `PROTOCOL.md` + `CLAUDE_DESIGN_BRIEFING.md` + ADRs). **Procurar a constituição existente ANTES de propor uma.** Minha doc é **subordinada** (`CARTA_DESIGN_CC.md`).
- **Ref:** ADR 0201.

## L-02 — Inventar paleta
- **Erro:** criei identidade por tela com oklch próprio (verde 155, roxo 295…).
- **Sintoma:** cor fora dos tokens canônicos.
- **Regra:** BRIEFING §4/§7 — usar shadcn semântico + escala warm (`emerald/amber/rose/sky-50/700`). Identidade nova = **proposta F0**, vira lei só por ADR aprovado por [W].
- **Ref:** BRIEFING §7; ADR 0201.

## L-03 — Declarar proposta como decisão firme
- **Erro:** marquei "cadastro = página inteira (PT-03)" e "identidade escopada" como **firmes** no STATUS/Painel.
- **Sintoma:** proposta minha virando "decidido" sem passar pelo loop.
- **Regra:** default de toda ideia minha = **proposta**. Vira firme/charter só via F0→F1.5→ADR de [W]. PT-03 ainda toca a proibição "detalhe usa Sheet drawer".
- **Ref:** PROTOCOL §3; BRIEFING §7.

## L-04 — Confundir rascunho com entrega canônica
- **Erro:** tratei HTML standalone como a entrega.
- **Sintoma:** output fora do formato do protocolo.
- **Regra:** entrega de F1 = `prototipos/<tela>/page.tsx` + `COMPARISON.md` (15 dim) + `critique-score.json` (≥80). HTML standalone só serve pra [W] **ver e decidir**.
- **Ref:** PROTOCOL §4; BRIEFING §8.

## L-05 — Não reler a lei antes de propor estrutura
- **Erro:** propus organização/memória sem ler `PROTOCOL.md`, `INDEX.md`, `proibicoes.md` primeiro.
- **Sintoma:** reinventei o que o git já tem (sessions, INDEX, proibicoes).
- **Regra:** no início de todo chat, **ler primeiro**: STATUS + MEMORY_INDEX + git (`INDEX.md`, `proibicoes.md`, `PROTOCOL.md`, `CLAUDE_DESIGN_BRIEFING.md`, ADRs relevantes). Não reinventar.
- **Ref:** CLAUDE.md ritual.

## L-06 — Afirmar ação no git que não posso fazer
- **Erro (risco recorrente):** dizer "commitei/mergeei/PR atualizado".
- **Sintoma:** promessa que não cumpro (não escrevo no GitHub).
- **Regra:** só gero a **ponte** (patch + URLs + 1 prompt). Nunca afirmo que escrevi no git. "O Code vai resolver com este prompt."
- **Ref:** CLAUDE.md limite operacional; CARTA §6.
