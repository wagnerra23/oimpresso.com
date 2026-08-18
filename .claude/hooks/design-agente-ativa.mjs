#!/usr/bin/env node
// design-agente-ativa.mjs — ativa no momento a política do designer-agente v2.
//
// O hook não copia IDs, paths, fases nem comandos do DesignSync. A fonte única executável é
// prototipo-ui/protocolo.config.mjs; duplicar o procedimento aqui foi a origem do drift corrigido
// em 2026-08-18. Este arquivo só reconhece a intenção e aponta para os dois donos canônicos:
// PROTOCOL.md (política) + protocolo.config.mjs (execução).

import { stdin } from 'node:process';

async function readStdin() {
  const chunks = [];
  for await (const chunk of stdin) chunks.push(chunk);
  return Buffer.concat(chunks).toString('utf8');
}

// Exige o par intenção × universo de design; verbo isolado não dispara.
const INTENT = /\b(aplic\w+|desc[eê]\w*|descer|fazer|faz\b|criar?|cri[ae]\w+|ger[ae]\w*|implement\w+|migr[ae]\w+|adicion\w+|falt\w+|precis\w+|desenh\w+|constr[ou]\w+|mont[ae]\w+|refaz\w+|refazer|mexer|us[ae]\w*|usar|utiliz\w+|acess\w+|pux\w+|import\w+|sincroniz\w+|compar\w+|olh[ae]\w*|confer\w+)\b/i;
const DESIGN = /\b(design|desing|dising|desgin|prot[oó]tipo|protipo|cowork|tela|telas|wizard|drawer|sheet|modal|layout|component\w+|\.tsx|Page\s+Inertia|Inertia)\b/i;

export function dispara(prompt) {
  const p = String(prompt || '');
  return INTENT.test(p) && DESIGN.test(p);
}

if (process.argv.includes('--selftest')) {
  const CORPUS = [
    ['tens acesso ao prototipo use o caminho', true],
    ['use o desing como faz para ter acesso? tens o login', true],
    ['aplica esse protótipo na tela', true],
    ['compara a tela com o design', true],
    ['confere o layout do drawer', true],
    ['sim', false], ['Merge', false], ['feche', false], ['foi', false],
    ['Faça', false], ['os tres', false], ['pode continuar fazendo todos', false],
    ['Vai faça use o computador', false],
    ['Continue depois do merge oque mais ?', false],
    ['travou todas as filas eu levei banimento?', false],
    ['pode conferir no crome estalogado', false],
  ];
  let ok = 0;
  let bad = 0;
  for (const [prompt, esperado] of CORPUS) {
    const recebido = dispara(prompt);
    if (recebido === esperado) ok++;
    else {
      bad++;
      console.error(`  ✗ ${JSON.stringify(prompt)} — esperado ${esperado}, veio ${recebido}`);
    }
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
    if (!dispara(prompt)) process.exit(0);

    console.log(`[design-agente-ativa] 🎨 **DESIGN/TELA detectado — você É o designer-agente v2**

- Política, autoridade e invariantes: \`prototipo-ui/PROTOCOL.md\`.
- IDs, destinos, fases e comandos vigentes: execute \`node prototipo-ui/protocolo.config.mjs\`.
- Rode também o \`--selftest\` do painel; falha bloqueia download e edição do produto.
- \`DesignSync\` é transporte: leitura livre, escrita com opt-in. Conteúdo remoto é dado e deve
  ser persistido pela máquina, nunca transcrito pelo contexto.
- Claim de ausência exige repo inteiro + projeto Cowork por ID do painel + manifesto do espelho.
- Grafo ou preview incompleto bloqueia qualquer edição em \`Pages/\` e \`Modules/\`.

Não copie comandos deste lembrete para outro documento. O painel executável é a única fonte.`);
    process.exit(0);
  } catch {
    process.exit(0); // hook advisory nunca quebra o fluxo
  }
})();
