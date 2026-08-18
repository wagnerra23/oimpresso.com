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

// intenção de PRODUZIR/APLICAR **ou ACESSAR/COMPARAR** UI (verbo/pergunta)
//
// Os verbos de ACESSO entraram em 2026-08-07 por buraco MEDIDO, não por intuição:
// na sessão da fusão das telas da Jana o prompt «tens acesso ao *protótipo* use o
// caminho» tem `prototipo` (DESIGN ✓) e NÃO disparou, porque `use`/`acesso` não
// eram verbos reconhecidos — justamente o prompt em que o lembrete era mais
// necessário (o agente tinha acabado de declarar "o protótipo não está no repo"
// sem consultar o DesignSync). Medição no corpus dos 19 prompts reais daquela
// sessão: falso-positivo 0 → 0, falso-negativo 4 → 3. O par INTENT × DESIGN é o
// que segura o FP — ampliar só INTENT não dispara nada sozinho.
// Prova: `node .claude/hooks/design-agente-ativa.mjs --selftest`.
const INTENT = /\b(aplic\w+|desc[eê]\w*|descer|fazer|faz\b|criar?|cri[ae]\w+|ger[ae]\w*|implement\w+|migr[ae]\w+|adicion\w+|falt\w+|precis\w+|desenh\w+|constr[ou]\w+|mont[ae]\w+|refaz\w+|refazer|mexer|us[ae]\w*|usar|utiliz\w+|acess\w+|pux\w+|import\w+|sincroniz\w+|compar\w+|olh[ae]\w*|confer\w+)\b/i;
// universo de design/tela — variantes EXPLÍCITAS incl. typos reais
const DESIGN = /\b(design|desing|dising|desgin|prot[oó]tipo|protipo|cowork|tela|telas|wizard|drawer|sheet|modal|layout|component\w+|\.tsx|Page\s+Inertia|Inertia)\b/i;

/** Núcleo puro — o predicado do gatilho. Exportado só pro selftest. */
export function dispara(prompt) {
  const p = String(prompt || '');
  return INTENT.test(p) && DESIGN.test(p);
}

// ── selftest: corpus REAL (os 19 prompts da sessão 2026-08-07 da fusão Jana) ──
// Não é fixture inventada: são os prompts que de fato passaram pelo hook naquele
// dia, com o veredito humano de "deveria ter disparado?". Guarda os DOIS lados —
// bite-test (os que o gatilho novo passa a pegar) e controle negativo (os curtos
// operacionais que NÃO podem disparar).
if (process.argv.includes('--selftest')) {
  const CORPUS = [
    // [prompt, deveriaDisparar]
    ['tens acesso ao prototipo use o caminho', true],          // ← BITE: o novo pega, o antigo não
    ['use o desing como faz para ter acesso? tens o login', true],
    ['aplica esse protótipo na tela', true],
    ['compara a tela com o design', true],
    ['confere o layout do drawer', true],
    // controle negativo — operacionais curtos desta mesma sessão
    ['sim', false], ['Merge', false], ['feche', false], ['foi', false],
    ['Faça', false], ['os tres', false], ['pode continuar fazendo todos', false],
    ['Vai faça use o computador', false],                       // tem verbo, NÃO tem design
    ['Continue depois do merge oque mais ?', false],
    ['travou todas as filas eu levei banimento?', false],
    ['pode conferir no crome estalogado', false],               // 'conferir' sozinho não basta
  ];
  let ok = 0, bad = 0;
  for (const [p, esperado] of CORPUS) {
    const got = dispara(p);
    if (got === esperado) ok++;
    else { bad++; console.error(`  ✗ ${JSON.stringify(p)} — esperado ${esperado}, veio ${got}`); }
  }
  console.log(`[design-agente-ativa --selftest] ${ok}/${CORPUS.length} ok${bad ? ` · ${bad} FALHA(S)` : ''}`);
  process.exit(bad ? 1 : 0);
}

(async () => {
  try {
    const raw = await readStdin();
    if (!raw) process.exit(0);

    let payload;
    try { payload = JSON.parse(raw); } catch { process.exit(0); }
    const prompt = String(payload?.prompt || '');
    if (!prompt) process.exit(0);

    if (!(INTENT.test(prompt) && DESIGN.test(prompt))) process.exit(0);

    console.log(`[design-agente-ativa] 🎨 **DESIGN/TELA detectado — você É o designer-agente v2 (PROTOCOL §0.1), NÃO espera insumo externo**

Antes de dizer *"precisa vir do Cowork"*, *"esperar handoff"* ou *"me autorize a desenhar"* — PARE. Na v2 (ADR 0282/0241):
- **Fonte de design** = protótipo Cowork (\`prototipo-ui/prototipos/<tela>/\`) + Design System em git + charter (ADR 0239/0299). claude.ai/design e Figma são **NÃO-fonte**.
- **Falta a fonte visual de uma tela? VOCÊ GERA** — ancorado no DS canon, via plugin Claude Design (\`design:design-system\`/\`-critique\`/\`-handoff\`). Valida por CI (visual-regression + PR UI Judge), abre PR.
- **\`DesignSync\`**: LEITURA livre (\`list_files\`/\`get_file\`) pra puxar/comparar o projeto fresco; **ESCRITA exige opt-in** (ADR 0315, gated — não é fonte, é transporte).
- **Soberania [W] Tier 0** = merge · produto · token/componente novo — **não** "posso desenhar".
⛔ "Design é dependência externa" é anti-padrão v1 morto (incidente 2026-07-15 wizard cartão Financeiro).

**⛔ ANTES de dizer "o protótipo não está no repo" / "não existe fonte visual" / "você tem o arquivo pra subir?" — RODE:**
    1. git       →  git grep <nome>          (repo INTEIRO — não só prototipo-ui/)
    2. Cowork    →  DesignSync{method:"list_files", projectId:"019dcfd3-6ef2-7ee6-8512-b1b0e5544e58"}
    3. espelho   →  node scripts/governance/cowork-mirror-freshness.mjs --manifest --all

⚠️ **\`list_projects\` NÃO É PROVA DE AUSÊNCIA.** Ele enumera **só design-systems**. O protótipo
do ERP vive num projeto **REGULAR** (\`type: PROJECT_TYPE_PROJECT\` — medido 2026-08-11 via
\`DesignSync{get_project}\`) e **não aparece** naquela lista. Vá direto ao \`list_files\` com o
projectId acima, ou abra \`claude.ai/design\` → aba **Projects**.

LEITURA é **livre** e usa o login do [W] — sem senha, sem opt-in (só a ESCRITA é gated, ADR 0315).
Claim de ausência exige consultar os TRÊS (§5 2026-07-28 — repo inteiro **+** dono do inventário).

**⛔ PUXOU? PERSISTA PELA ROTA CANÔNICA — nunca escrevendo o conteúdo você mesmo:**
    # SHELL COMPLETO (preferido): payload servido Cowork + DS, sem teto de 256 KiB
    node scripts/design-sync/aplicar-payload.mjs <cowork.json> <ds.json> --dry --require-complete-shell
    node scripts/design-sync/aplicar-payload.mjs <cowork.json> <ds.json> --require-complete-shell
    node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir-com-os-JSONs-do-get_file>
    node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir> --ds   # destino Design System
    node scripts/governance/cowork-mirror-freshness.mjs --export-from <dir> --ds-runtime  # bundle/CSS/fontes
    node scripts/governance/cowork-mirror-freshness.mjs --preview-ds              # PORTÃO: precisa sair 0

Shell completo só existe quando o applier imprime \`GRAFO COMPLETO\`: ele percorre HTML/CSS/JS
transitivamente, exige \`missing:[]\` em TODOS os payloads e grava \`_ds/**\` no snapshot persistente.
Se qualquer JSON trouxer \`truncated:true\`, ou o \`--preview-ds\` acusar bundle/fonte ausente,
**PARE. Não edite \`Pages/\` nem \`Modules/\`**. O preview degradado esconde Drawer/Skeleton/
DropdownMenu e não é evidência visual do protótipo.

O agente BUSCA (só ele fala MCP) e o **SCRIPT ESCREVE** o \`raw.content\` — hash idêntico
**por construção**, sem transcrição (ADR 0374, ratificada 2026-08-13 · FASE −1 do
\`prototipo-ui/protocolo.config.mjs\`). Transcrever pelo contexto é o que produziu o STALE de
2026-08-11.

_Este bloco existe porque as 3 linhas acima ensinavam só a **LER** (\`list_files\`/\`get_file\`/
\`--manifest\`) e paravam um passo antes da PERSISTÊNCIA. Medido em 2026-08-18: um agente puxou
\`templates/pt-05-dashboard/Pt05Dashboard.dc.html\` do Design System, leu no contexto, **não gravou
byte** (\`git ls-files | grep -c pt-05-dashboard\` = 0) e ainda construiu um baixador PARALELO ao
\`--export-from\` — que é o dono. [W]: **"máquina que não é seguida é bug"**. O bug era este hook
disparar na hora certa e não nomear a rota de escrita._

_Reincidência 2026-08-07 (fusão das telas da Jana): o agente varreu só o git, declarou "jana-merge.jsx não existe" e pediu ao [W] que subisse o arquivo._
_**Reincidiu em 2026-08-11 com este hook JÁ ATIVO** — porque ele mandava rodar \`list_projects\` como PROVA, e aquele tool não enxerga projeto regular: a defesa era parte da causa. Pior: dois documentos MERGEADOS de 3 dias antes já diziam onde o arquivo morava (\`git grep jana-merge\` → 21 sites). Canon negou canon, e o oráculo custava 1 comando._
_⚠️ O template de Dashboard chama-se \`pt-05-dashboard\` no design-system, mas no repo **Dashboard é PT-04** (PT-05 é Kanban). O dono da numeração é o repo (\`memory/requisitos/_DesignSystem/padroes-tela/\`)._`);
    process.exit(0);
  } catch {
    process.exit(0); // hook nunca quebra o fluxo
  }
})();
