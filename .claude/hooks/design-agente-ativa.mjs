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
const INTENT = /\b(aplic\w+|desc[eê]\w*|descer|fazer|faz\b|criar?|cri[ae]\w+|ger[ae]\w*|implement\w+|migr[ae]\w+|adicion\w+|falt\w+|precis\w+|desenh\w+|constr[ou]\w+|mont[ae]\w+|refaz\w+|refazer|mexer|us[ae]\w*|usar|utiliz\w+|acess\w+|pux\w+|import\w+|sincroniz\w+|compar\w+|olh[ae]\w*|confer\w+|abr[ie]\w*|abrir)\b/i;
const DESIGN = /\b(design|desing|dising|desgin|prot[oó]tipo|protipo|cowork|tela|telas|wizard|drawer|sheet|modal|layout|component\w+|\.tsx|Page\s+Inertia|Inertia)\b/i;

// DIVERGÊNCIA — o vocabulário com que se APONTA um desvio, que não é verbo de ação e por isso
// escapava do INTENT. Medido em 2026-08-18 nas frases reais da sessão da paridade da Jana:
//   "Ainda não chegou no protótipo? Tá diferente ainda sem pageheader"  → NÃO disparava
//   "Não é só o drawer seria o pageheader"                              → NÃO disparava
// As duas são pedidos de COMPARAÇÃO, e foram exatamente as que precederam o erro: comparei
// string literal do protótipo com o código, em vez de medir os dois lados. Continua exigindo
// o par com DESIGN — "tá diferente" sozinho não dispara nada.
const DIVERGENCIA = /\b(diferente|divergent\w*|n[ãa]o (chegou|bate|est[áa] igual|ficou)|ainda n[ãa]o|falt[ao]\w*|sem (o )?(pageheader|header|avatar|drawer)|seria|igual)\b/i;

// COMPARAÇÃO — quando a intenção é confrontar design × produção, o agente precisa da MÁQUINA
// que mede, não do procedimento geral de design. Ver o bloco de saída.
const COMPARA = /\b(compar\w+|confer\w+|igual|paridade|diferen\w+|divergent\w*|o que mudou|ficou igual|aplicou certo)\b/i;

export function ehComparacao(prompt) {
  const p = String(prompt || '');
  // DIVERGENCIA entra junto: apontar um desvio ("seria o pageheader", "tá diferente",
  // "ainda não chegou") É pedir comparação, mesmo sem o verbo "comparar". Foi assim que
  // [W] abriu o assunto em 2026-08-18, e o bite-test pegou a omissão.
  return COMPARA.test(p) || DIVERGENCIA.test(p);
}

export function dispara(prompt) {
  const p = String(prompt || '');
  return (INTENT.test(p) || DIVERGENCIA.test(p)) && DESIGN.test(p);
}

if (process.argv.includes('--selftest')) {
  const CORPUS = [
    ['tens acesso ao prototipo use o caminho', true],
    ['use o desing como faz para ter acesso? tens o login', true],
    ['aplica esse protótipo na tela', true],
    ['compara a tela com o design', true],
    // Corpus REAL da sessão da paridade da Jana (2026-08-18) — as 3 primeiras NÃO disparavam
    // antes desta correção, e são exatamente as que precederam a lista de gap errada.
    ['Ainda não chegou no protótipo ? Tá diferente ainda sem pageheader e outras coisa', true],
    ['Não é só o drawer seria o pageheader', true],
    ['eu aceito a regressão é o que eu espero mudar a forma dele para ficar igual ao prototipo', true],
    ['pode comparar a Jana e criar as ondas para aplicar o prototipo em produção inteira', true],
    // Negativos da MESMA sessão: aprovação e decisão não são pedido de design.
    ['merge aprovado', false], ['aprovo f1.5', false], ['Sua decisão, gate F1.5 ok', false],
    ['São erros e precisam ser corrigidos', false], ['Pode fazer', false],
    ['não podemudar em silencio', false],
    // ⚠️ CONHECIDO e aceito: typo grudado não dispara — o `` antes de `abr` não casa em
    // "podeabrir". Tirar a fronteira faria "sabre"/"cabrito" dispararem; o FP não compensa.
    ['podeabrir o prototipo atulizado dajana', false],
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

    // COMPARAÇÃO tem dono PRÓPRIO, e não é este hook. Medido em 2026-08-18: o hook disparava
    // em "pode comparar a Jana..." e apontava só pra PROTOCOL + protocolo.config — nenhum dos
    // dois ensina a COMPARAR. `grep comparar-design-prod` neste arquivo dava 0. O agente
    // seguiu por grep de string literal do protótipo contra o código e reportou como "falta"
    // o que estava lá com a copy adaptada (drawer da Jana: as 5 seções existiam com outros
    // títulos). O gap REAL — o avatar do header — só apareceu ao medir o DOM dos dois lados.
    if (ehComparacao(prompt)) {
      console.log(`[design-agente-ativa] 🔬 **COMPARAÇÃO design × produção — é MEDIDA, não grep**

- Skill dona: \`comparar-design-prod\` (Tier B) — carrega o PROTOCOLO-COMPARACAO-RUNTIME D1–D8.
- Máquina que mede: \`node prototipo-ui/design-diff.mjs --probe\` → injete a MESMA sonda nos dois
  lados → \`--compare prod.json design.json --check\`.
- Dono do inventário POR TELA: \`memory/requisitos/<Mod>/<Tela>-visual-comparison.md\` — cada item
  já tem veredito e data. Leia ANTES de listar gap; grep no lugar dele produz lista errada.
- ⛔ Copy adaptada de propósito NÃO é ausência de capacidade: rótulo do protótipo não é chave de
  busca em código que renomeou o rótulo (§5 2026-07-15 · LC-08).`);
    }

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
