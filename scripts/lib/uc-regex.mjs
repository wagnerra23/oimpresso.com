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
// Formato canônico: UC-<prefixo letras opcional>[-]<dígitos>[letra]. Prefixo ATÉ 6 letras +
// hífen opcional. Ex: UC-01, UC-F02, UC-10b, UC-IMP-01, UC-FORJA-01, UC-SC-08.
// Conservador de propósito: não captura "UC-" solto nem prosa solta.

// Corpo (varredura) — flag `g`, pra matchAll em corpus / nome de <testcase> / texto.
// É FUNÇÃO (não const) pra devolver instância FRESCA: regex /g é stateful (lastIndex) e
// compartilhar uma instância global entre módulos com .test()/.exec() é footgun.
export const ucScanRe = () => /\bUC-[A-Z]{0,6}-?\d{1,3}[a-zA-Z]?\b/g;

// Heading — âncora `^(...)`, pra extrair o UC declarado de um bloco "UC-XX ..." (G-5/G-7 +
// ucsInCasos). Sem `g` (uso com .match). Instância fresca por chamada.
export const ucHeadRe = () => /^(UC-[A-Z]{0,6}-?\d{1,3}[a-zA-Z]?)\b/;
