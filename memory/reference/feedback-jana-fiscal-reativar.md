---
name: Jana IA fiscal — reativar (foi removida por mal-entendido)
description: Wagner quer IA dentro do Fiscal (briefing mensal Jana + receita SEFAZ não-mapeada via Brain B + Q&A NL no ⌘K). Foi removida no PR #1605 por interpretação errada do Claude. Próxima tarefa fiscal — REATIVAR.
type: feedback
---

## O que aconteceu

Sessão 2026-05-26 (Onda Fiscal Cowork visual). Claude perguntou se podia
incluir chip "Jana resume o mês" no header do Cockpit Fiscal. Wagner
respondeu "isso é a jana? IA não tem nada haver com o modulo fiscal".

Claude **interpretou como exclusão definitiva** (separation of concerns
brutal — Constituição V2 princípio 5) e:
1. Removeu chip "Jana resume o mês" do Cockpit (PR #1605)
2. Removeu branding "Jana sugere" da receita SEFAZ (substituiu por "Como
   corrigir" sem badge IA) — PR #1614
3. Removeu `JanaBriefingButton`, `JanaInlineAnswer`, `JanaExplainCard` do
   inventário Onda 1+2+3
4. Manteve só receitas SEFAZ determinísticas (dicionário canônico estático
   em `_lib/sefaz-actions.ts` — 8 cstats mapeados)

## Correção Wagner (mesma sessão, fim)

> *"eu queria a ia, só não tinha entendido antes. mais vou deixar para outra tarefa"*

Wagner **quer IA dentro do Fiscal**. A pergunta dele inicial era genuína
curiosidade ("isso é a jana?"), não rejeição. Claude deveria ter explicado
o que era + perguntado, em vez de remover.

## O que reativar (próxima tarefa fiscal)

### Tarefa A — JanaBriefingButton no header
- Chip "Jana resume o mês" no `Cockpit.tsx` actions slot do FxShell
- Drawer 480px com briefing executivo do mês fiscal gerado por LLM
- Prompt template em `Modules/Fiscal/Services/JanaBriefingService.php`
- Dados: NF-e emitidas/autorizadas/rejeitadas mês + DF-e pendentes + cert
  vencimento + eventos semana (mesma data que já tá no controller)
- Brain B (Sonnet via Jana gateway interno) — segue ADR 0094 governance
- HITL: Wagner aprova prompt template ANTES de ligar prod

### Tarefa B — JanaExplainCard pra cstat não-mapeado
- Quando código SEFAZ rejeição NÃO está no `SEFAZ_ACTIONS` dicionário,
  invocar Jana pra explicar (vs hint genérico do `SEFAZ_CODES`)
- Card visual idêntico ao `SefazActionCard` atual mas com badge "Jana sugere"
- Footer: "Resposta gerada por IA · revisar com contador antes de aplicar"
- Renderizar dentro do `NotaDrawerV2` ao invés do hint genérico atual
- Determinístico continua sendo first-choice — IA é fallback

### Tarefa C — JanaInlineAnswer no ⌘K palette
- Quando user digita pergunta NL no `CmdKPalette.tsx` (que já existe), Jana
  responde inline com 1-3 frases curtas baseadas em dados fiscais reais
- Detector `looksLikeQuestion()` em `_lib/fiscal-helpers.ts` (port de
  `fiscal-ai.jsx`)
- Sempre cita fonte (mock data); nunca toma ação irreversível sozinho

## Princípio canon (correção retroativa)

**IA é bem-vinda em qualquer módulo, desde que:**
1. HITL (Human-In-The-Loop) — IA sugere, humano aprova ação destrutiva
2. Disclaimer claro ("resposta gerada por IA · revisar antes de aplicar")
3. Determinístico first — IA é fallback quando dicionário canon não tem
4. Audit log (`jana_audit_log`) — ADR 0094
5. Multi-tenant Tier 0 — ADR 0093

**Não é "SoC brutal contra IA"** como interpretei. É "IA com guardrails",
exatamente como `Jana/Chat.charter.md` descreve pra `/jana/chat`.

## Onde encontrar o código removido

Branch `feat/fiscal-onda-1-com-jana-pre-removal` **não existe** — removi
inline durante a sessão. Pra reativar, ports do protótipo Cowork:

- [`prototipo-ui/Oimpresso ERP Conunicação Visual. Ultimotopo/fiscal-ai.jsx`](https://github.com/wagnerra23/oimpresso.com/blob/main/prototipo-ui/Oimpresso ERP Conunicação Visual. Ultimotopo/fiscal-ai.jsx) — JSX original com 3 features (briefing/inline/explain)
- ADR 0094 (Constituição V2) §IA + audit
- `Jana/Chat.charter.md` (referência de IA cliente-facing canônica)

## Não confundir com

- `feedback-recomendado-quando-tecnico.md` continua válido — decisões
  TÉCNICAS Claude segue Recommended; decisões de PRODUTO/ESCOPO pergunta
  sempre. **Este caso era escopo (IA sim ou não no Fiscal), eu errei ao
  tratar como técnico.**

## Origem

Sessão 2026-05-26 Wagner @ MARTINHO CAÇAMBAS (biz=164). PRs afetados:
#1605 (removeu chip), #1614 (removeu badge Jana sugere), #1618/#1620
(seguiram zero-IA por consistência).
