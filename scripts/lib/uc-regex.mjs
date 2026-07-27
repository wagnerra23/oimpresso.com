// scripts/lib/uc-regex.mjs — fonte ÚNICA do regex de UC-id (ADR 0264 · G-1/G-2/G-5/G-7).
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Tinha QUATRO regex de UC no fluxo (guard UC_RE · guard head-parser G-5 · guard head-parser
// G-7 · coletor do manifesto) que DEVIAM ser iguais e drifaram: em 2026-06-22 o guard foi
// pra {0,6}-? (pra enxergar UC-IMP-01/UC-FORJA-01) MAS o coletor + os 2 head-parsers ficaram
// em {0,3} — deixando os 35 UCs hifenados isentos de G-5/G-7 e fora do manifesto G-7 (um
// `Status: ✅` mentiroso neles passava batido). Esta é a fonte única: importar daqui mata a
// classe "regex que deviam ser iguais e drifam".
//
// Formato canônico: UC-<prefixo opcional>[-]<dígitos>[letra]. Prefixo = 1 letra + até 5
// alfanuméricos (dígito permitido DEPOIS da 1ª letra — nunca no início, senão vira ambíguo
// com o número do UC). Ex: UC-01, UC-F02, UC-10b, UC-IMP-01, UC-FORJA-01, UC-KBV2-01.
// Conservador de propósito: não captura "UC-" solto nem prosa solta.
//
// Por que dígito no prefixo (fix 2026-07-16): `[A-Z]{0,6}` truncava UC-KBV2-01..09
// (kb/Index.v2.casos.md) pro token UC-KBV2 — os 9 headings viravam 1 só no dedupe do guard
// (ucs_declared mentia 135 vs 143) e o G-2 dava match falso (teste citando UC-KBV2-0N
// "cobria" todos os 9 via substring). ADR 0264 G-2.
const UC_CORE = 'UC-(?:[A-Z][A-Z0-9]{0,5})?-?\\d{1,3}[a-zA-Z]?';

// Corpo (varredura) — flag `g`, pra matchAll em corpus / nome de <testcase> / texto.
// É FUNÇÃO (não const) pra devolver instância FRESCA: regex /g é stateful (lastIndex) e
// compartilhar uma instância global entre módulos com .test()/.exec() é footgun.
export const ucScanRe = () => new RegExp(`\\b${UC_CORE}\\b`, 'g');

// Heading — âncora `^(...)`, pra extrair o UC declarado de um bloco "UC-XX ..." (G-5/G-7 +
// ucsInCasos). Sem `g` (uso com .match). Instância fresca por chamada.
//
// ATENÇÃO — o `^` casa o início do BLOCO, não da linha do markdown. O heading canônico é
// `## UC-CEDI-01 · ...`, que começa em `##`; aplicar este regex na LINHA CRUA nunca casa.
// Use `ucsDeclaredInCasos()` abaixo em vez de reimplementar o split.
export const ucHeadRe = () => new RegExp(`^(${UC_CORE})\\b`);

/**
 * UC-ids declarados no conteúdo de um `<Tela>.casos.md` — split por BLOCO `## ` e então
 * `ucHeadRe()`. Devolve MAIÚSCULO, na ordem de declaração (com duplicatas, se houver).
 *
 * POR QUE EXISTE (2026-07-27): a lib nasceu pra matar "4 regex que deviam ser iguais e
 * drifaram", e conseguiu — mas o USO do regex (o `split(/^##\s+/m).slice(1)` obrigatório
 * antes do match) ficou copiado em 6 consumidores e drifou pela MESMA porta: a sentinela
 * `scripts/qa/exposicao-tier0.mjs` aplicava `ucHeadRe()` na linha crua, então
 * `hasCasosCoverage()` dava false pra TODA tela com heading markdown — a perna
 * casos_coverage estava morta e o piso Tier-0 subcontava (4 cobertas medidas vs 29 reais).
 * Sintoma: Cliente aparecia 0/7 tendo 21 UC citados por teste, divergindo de
 * `npm run screen:files`. Fonte única do PARSER, não só do regex.
 *
 * Comportamento extraído de casos-coverage-guard.mjs (o dono do formato, ADR 0264
 * G-2/G-5/G-7), que sempre fez o split certo. Os demais consumidores podem migrar pra cá
 * — o guard é gate REQUIRED, então migrá-lo é PR próprio, não carona.
 *
 * @param {string} content conteúdo bruto do arquivo `.casos.md`
 * @returns {string[]} UC-ids em MAIÚSCULO
 */
export function ucsDeclaredInCasos(content) {
  const out = [];
  for (const block of String(content).split(/^##\s+/m).slice(1)) {
    const m = block.match(ucHeadRe());
    if (m) out.push(m[1].toUpperCase());
  }
  return out;
}
