# Sessão 2026-07-15 — Protocolo design v2: §0.1 + hook de ativação

**Branch de trabalho:** `exciting-mccarthy-f4219c` (worktree stale −5254; produção via `origin/main` fresco)
**PRs:** [#4320](https://github.com/wagnerra23/oimpresso.com/pull/4320), [#4322](https://github.com/wagnerra23/oimpresso.com/pull/4322) — ambos merged
**Origem:** pedido "aplicar o financeiro, o que falta no protótipo pra descer?"

## Linha do tempo

1. **Apuração Financeiro** (via `origin/main` fresco): protótipo já desceu inteiro — 21 telas + primitivos (OCR, Fechamento, Apresentação, Anexos, Aprovação...). Não havia import pendente; resíduos são backend/credenciais.

2. **Erro** ao falar de campos de cartão no `SheetNovaCobranca`: respondi *"precisa vir do Cowork / me autorize a desenhar"* — tratei design como dependência externa.

3. **Correção Wagner** *("você tem acesso completo ao design")*: investiguei o PROTOCOL.md — o corpo §1 (v1: "Cowork gera / Code traduz") contradizia a v2 (§0 + §10.6 DesignSync). Adicionei **§0.1** (Code é designer-agente com acesso completo por 3 vias) + anti-padrão §8.

4. **"remova as adrs e memórias conflitantes"**: expliquei o limite append-only (não deleto ADR — é lei Tier 0 + já reconciliadas por 0282; nem handoff/session = histórico). Reconciliei os 3 docs de **estado vivo** que ainda pregavam o gate v1.

5. **"por que não foi ativado? deveria ser um hook?"**: certeiro. Meu 1º fix (CLAUDE.md + banner) era doc advisory (ADR 0315 = "canal que o agente prova não ler"). Criei o hook **`design-agente-ativa.mjs`** (UserPromptSubmit, cross-platform, espelha `design-compare-protocol.mjs`) — testado, dispara no prompt exato do incidente. Corrigi minha imprecisão sobre DesignSync (leitura livre, escrita gated — ADR 0315). Removi o banner .ps1 (redundante, Windows-only).

## Incidente de processo

O #4320 mergeou cedo (só o 1º commit). Ignorei o sinal `[new branch]` no 2º push (lição da minha auto-mem). Recuperei via #4322 — cherry-pick sobre `main` fresco, evitando reverter 2 arquivos de Produto que outra pessoa mergeou no meio. Branch órfã deletada, worktrees limpos.

## Resultado

Loop fechado com 2 gatilhos DIFERENTES (padrão ADR 0299, não "3 cópias da mesma frase"): **CLAUDE.md** (baseline passivo) + **hook** (injeção no momento). Ver handoff [2026-07-15-1821](../handoffs/2026-07-15-1821-protocolo-design-v2-hook-ativacao.md).
