# Sessão 2026-05-30 — Harmonização DS + Espinha de memória + Auto-integração

**Worktree:** Cowork (projeto "Oimpresso ERP Comunicação Visual")
**Operador:** Claude (Cowork · [CC])
**Solicitante:** Wagner [W]

---

## Pedido
1. Aplicar o novo DS aos protótipos **sem perder a qualidade** das telas atuais.
2. Evoluir o DS com o melhor das telas já produzidas (rumo v4.2).
3. Resolver **memória e organização** — decisões que voltam, arquivos perdidos.
4. Criar a constituição de integração do design + integrar à grande memória do git.
5. Auto-integrar ao projeto: ler e obedecer a lei do git; memória manda o git, design cuida [CC].

## O que foi feito
- **Auditoria** das telas-núcleo (`Auditoria - O Melhor de Cada Tela.html`) — lista de proteção por tela.
- **Piloto Vendas antes/depois** (`Piloto Vendas - Antes Depois.html`) — identidade preservada sobre DS.
- **Molde cadastro página-inteira** (`Cadastro Cliente - Pagina Inteira DS 4.2.html`) — proposta PT-03.
- **Spec v4.2** (`Design System v4.2 - Evolucao.html`) — proposta de promoção (cockpit/fiscal/readiness/shortcut).
- **Espinha de memória:** `STATUS.md` + `Painel Cowork - Estado Atual.html` + `MEMORY_INDEX.md` (índice temático T1–T9).
- **Auto-integração:** li `PROTOCOL.md` + `CLAUDE_DESIGN_BRIEFING.md` do git; rebaixei a "constituição" a `CARTA_DESIGN_CC.md` (subordinada); reclassifiquei propostas; corrigi referências de ADR.
- **Ponte zero-toque** (`PROMPT_PARA_CODE_MEMORIA.md`) pro [CL] commitar a memória no git.

## Decisões registradas
- **ADR 0236** (proposto no Cowork como 0200) — DS é piso não teto · identidade por token · PT-03 · escopo v4.2 (D-01 firme; D-02/D-03/D-04 = propostas F0).
- **ADR 0237** (proposto no Cowork como 0201) — `CARTA_DESIGN_CC.md` subordinada ao protocolo do git (substitui a "constituição suprema" retirada).

## Erros cometidos e correção (ver LICOES_CC.md)
- Criei "constituição acima dos ADRs" → **retirada**; a lei é do git (PROTOCOL/BRIEFING).
- Inventei paleta (oklch por tela) → vira **proposta F0**; valem tokens canônicos.
- Tratei HTML standalone como entrega canônica → entrega de F1 = `page.tsx`+COMPARISON+critique-score.json.

## Trabalho residual
- 🟡 Levar identidade-por-token e PT-03 pelo loop (F0 em `COWORK_NOTES.md` → F1.5 → ADR).
- 🟡 Corrigir colisão KPI Financeiro <1100px (container-query).
- 🟡 Executar limpeza canon/archive (`PLANO_ORGANIZACAO_CASA.md`).
- 🟡 Wagner colar `PROMPT_PARA_CODE_MEMORIA.md` no [CL] (1ª sync da espinha).

## Refs
- ADR 0236 (ex-0200), 0237 (ex-0201) · `CARTA_DESIGN_CC.md` · `STATUS.md` · `memory/INDEX_TEMATICO.md`
- Lei do git: `prototipo-ui/PROTOCOL.md`, `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`, ADR 0114/0110/0107/0104
- Memória git: `memory/INDEX.md`, `memory/proibicoes.md`, `memory/decisions/`

## Próximo passo sugerido
Entregar a 1ª tela no formato canônico — `prototipos/sells-create/page.tsx` + `COMPARISON.md` + `critique-score.json` (P0 da fila), seguindo CARTA §1.
