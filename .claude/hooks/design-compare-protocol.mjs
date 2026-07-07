#!/usr/bin/env node
// Hook UserPromptSubmit — ATIVA o protocolo de comparação design×prod (LC-06, strike 2).
//
// **Cross-platform** (Node.js — Windows / macOS / Linux). Vale pra Wagner + time MCP.
//
// Camada 2 de ativação (defesa em depth — mesmo pattern do R12):
//   Camada 1: skill `comparar-design-prod` Tier B (description-match).
//   Camada 2: ESTE hook — se a skill não disparar, o lembrete entra mesmo assim.
//
// Origem: Wagner 2026-07-07 — "isso teria que ter sido ativado, hook ou runbook sei lá".
//   O PROTOCOLO-COMPARACAO-RUNTIME existia desde 06/07 mas NADA o ativava no momento da
//   comparação → o agente comparou no olho de novo (strike 2, LICOES_CODE LC-06).
//   Lição-mãe: conhecimento sem gatilho não dispara (ADR 0315: doc advisory = o canal
//   que o agente provou não ler).
//
// Como funciona:
//   1. UserPromptSubmit recebe JSON via stdin com `prompt` do user
//   2. Regex case-insensitive contra padrões de INTENÇÃO DE COMPARAÇÃO design×prod
//   3. Match → emite markdown em stdout (vira <system-reminder> no contexto Claude)
//   4. Sem match → exit 0 silencioso (zero overhead)
//
// Anti-pattern: "compare" sozinho = false-positive (compara módulo, compara preço).
// Exige o par (comparar/conferir/igual/diferença/mudou) × (design/protótipo/tela/prod/cowork).
//
// Refs: LICOES_CODE LC-06 · PROTOCOLO-COMPARACAO-RUNTIME.md · prototipo-ui/design-diff.mjs · ADR 0299

import { stdin } from 'node:process';

async function readStdin() {
  const chunks = [];
  for await (const chunk of stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString('utf8');
}

// intenção de comparar (verbo/pergunta)
const INTENT = /\b(compar\w+|confir\w+|confer\w+|iguale?m?|igual(zinho)?\??|diferen[cç]\w+|mudou|mudan[cç]as?|bateu?|fiel|aplicou\s+certo|ficou\s+igual|est[aá]\s+igual)\b/i;
// universo de design — variantes EXPLÍCITAS incl. typos reais (desing/dising/protipo)
const DESIGN = /\b(design|desing|dising|desgin|prot[oó]tipo|protipo|cowork|mockup|figma)\b/i;

(async () => {
  try {
    const raw = await readStdin();
    if (!raw) process.exit(0);

    let payload;
    try { payload = JSON.parse(raw); } catch { process.exit(0); }
    const prompt = String(payload?.prompt || '');
    if (!prompt) process.exit(0);

    // intenção de comparação + menção a design/protótipo já basta (advisory: errar pra mais
    // custa 1 reminder; errar pra menos custou os strikes 1 e 2)
    if (!(INTENT.test(prompt) && DESIGN.test(prompt))) process.exit(0);

    console.log(`⚠️ **COMPARAÇÃO DESIGN×PROD detectada — protocolo LC-06 (comparação é MEDIDA, nunca no olho)**

Antes de qualquer veredito "igual/aplicado/fiel", cumpra o fluxo da skill \`comparar-design-prod\`:
1. **Fonte provada**: \`cowork-mirror-freshness --compare --check\` = SYNC (senão está comparando design velho).
2. **Mesmo tema** nos dois lados (o tema do Wagner).
3. **Mesma sonda medida**: \`node prototipo-ui/design-diff.mjs --probe\` nos DOIS renders → \`--compare a.json b.json --check\` (D2/D4/D6/D8 medidos).
4. **D1 rede sempre** (partial-reload) + D3/D5 pelo PROTOCOLO-COMPARACAO-RUNTIME.
5. **Canário**: valide a sonda contra 1 diferença conhecida antes de concluir.
⛔ Screenshot é ilustração, NÃO prova — print não distingue center×left (o erro dos strikes 1 e 2).`);
    process.exit(0);
  } catch {
    process.exit(0); // hook nunca quebra o fluxo
  }
})();
