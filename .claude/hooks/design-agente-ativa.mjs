#!/usr/bin/env node
// design-agente-ativa.mjs — ATIVA no momento: "você É o designer-agente v2, NÃO espera insumo externo".
//
// **Cross-platform** (Node.js — Windows / macOS / Linux). Vale pra Wagner + time MCP.
//
// Camada de ativação NO MOMENTO (defesa em depth — mesmo pattern do design-compare-protocol.mjs):
//   Baseline: CLAUDE.md §"Onde NÃO inventar" (always-on, passivo) + PROTOCOL §0.1.
//   ESTE hook: quando o prompt pede pra APLICAR/GERAR/FALTAR design/tela, o lembrete entra FRESCO
//   no momento — resolve o "doc advisory = canal que o agente prova não ler" (ADR 0315) e o
//   "always-on perde foco em sessão longa" (ADR 0225).
//
// Origem: incidente 2026-07-15 — ao "vamos aplicar o financeiro, o que falta no protótipo pra
//   descer?" o agente respondeu "precisa nascer no Cowork / me autorize a desenhar", tratando
//   design como dependência EXTERNA. Wagner: "por que não foi ativado? deveria ser um hook".
//   Lição-mãe: conhecimento sem gatilho não dispara (ADR 0315 · 0225).
//
// Como funciona (idêntico ao design-compare-protocol):
//   1. UserPromptSubmit recebe JSON via stdin com `prompt` do user
//   2. Regex case-insensitive: par INTENÇÃO-DE-PRODUZIR × UNIVERSO-DE-DESIGN
//   3. Match → emite markdown em stdout (vira <system-reminder> no contexto Claude)
//   4. Sem match → exit 0 silencioso (zero overhead) · hook NUNCA quebra o fluxo
//
// Anti-pattern: verbo sozinho = false-positive. Exige o PAR (produzir × design/tela/protótipo).
//   Advisory: errar pra mais custa 1 reminder; errar pra menos custou o incidente 2026-07-15.
//
// Refs: PROTOCOL §0.1 · ADR 0282 (v2) · 0241 (overlay) · 0315 (DesignSync não-fonte) · 0299 (fonte)

import { stdin } from 'node:process';

async function readStdin() {
  const chunks = [];
  for await (const chunk of stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString('utf8');
}

// intenção de PRODUZIR/APLICAR UI (verbo/pergunta)
const INTENT = /\b(aplic\w+|desc[eê]\w*|descer|fazer|faz\b|criar?|cri[ae]\w+|ger[ae]\w*|implement\w+|migr[ae]\w+|adicion\w+|falt\w+|precis\w+|desenh\w+|constr[ou]\w+|mont[ae]\w+|refaz\w+|refazer|mexer)\b/i;
// universo de design/tela — variantes EXPLÍCITAS incl. typos reais
const DESIGN = /\b(design|desing|dising|desgin|prot[oó]tipo|protipo|cowork|tela|telas|wizard|drawer|sheet|modal|layout|component\w+|\.tsx|Page\s+Inertia|Inertia)\b/i;

(async () => {
  try {
    const raw = await readStdin();
    if (!raw) process.exit(0);

    let payload;
    try { payload = JSON.parse(raw); } catch { process.exit(0); }
    const prompt = String(payload?.prompt || '');
    if (!prompt) process.exit(0);

    if (!(INTENT.test(prompt) && DESIGN.test(prompt))) process.exit(0);

    console.log(`🎨 **DESIGN/TELA detectado — você É o designer-agente v2 (PROTOCOL §0.1), NÃO espera insumo externo**

Antes de dizer *"precisa vir do Cowork"*, *"esperar handoff"* ou *"me autorize a desenhar"* — PARE. Na v2 (ADR 0282/0241):
- **Fonte de design** = protótipo Cowork (\`prototipo-ui/prototipos/<tela>/\`) + Design System em git + charter (ADR 0239/0299). claude.ai/design e Figma são **NÃO-fonte**.
- **Falta a fonte visual de uma tela? VOCÊ GERA** — ancorado no DS canon, via plugin Claude Design (\`design:design-system\`/\`-critique\`/\`-handoff\`). Valida por CI (visual-regression + PR UI Judge), abre PR.
- **\`DesignSync\`**: LEITURA livre (\`list_files\`/\`get_file\`) pra puxar/comparar o projeto fresco; **ESCRITA exige opt-in** (ADR 0315, gated — não é fonte, é transporte).
- **Soberania [W] Tier 0** = merge · produto · token/componente novo — **não** "posso desenhar".
⛔ "Design é dependência externa" é anti-padrão v1 morto (incidente 2026-07-15 wizard cartão Financeiro).`);
    process.exit(0);
  } catch {
    process.exit(0); // hook nunca quebra o fluxo
  }
})();
