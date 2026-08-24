#!/usr/bin/env node
// Selftest de block-pergunta-que-eu-resolvo (R16).
//
// ── FP MEDIDO ANTES DE LIGAR (canon: "Ligar != criar") ───────────────────────
// Corpus real: ~946 transcripts de C:/Users/wagne/.claude/projects, só mensagens de
// FIM DE TURNO (assistant seguido de user). Número no handoff da sessão 2026-08-24.
// O predicado exige as TRÊS condições juntas — fecho com '?', padrão de devolução,
// e ausência de escalação legítima — justamente pra não punir o turno que pergunta
// o que R10 MANDA perguntar (merge/push/PR/deploy).
//
// ── O QUE ESTE TESTE TRAVA ───────────────────────────────────────────────────
// As fixtures RUIM são meus dois erros REAIS de 2026-08-24 (a devolução de 3 leituras
// e o "qual delas?"). As fixtures BOM são os casos que NÃO podem ser bloqueados —
// principalmente "Abro o PR?", que o R10 obriga a perguntar: bloquear ali empurraria
// o agente a violar uma regra Tier 0 pra satisfazer esta.

import { deveBloquear, MOTIVO } from './block-pergunta-que-eu-resolvo.mjs';

let fails = 0;
const check = (label, cond, detail = '') => {
  if (cond) console.log(`[OK] ${label}`);
  else { console.log(`[FAIL] ${label}${detail ? '  → ' + detail : ''}`); fails++; }
};

// ── RUIM: tem que BLOQUEAR ───────────────────────────────────────────────────

// Erro REAL 2026-08-24 — devolvi 3 leituras da missão pro [W] em vez de resolver.
const meuErro1 = `## O que eu não sei

Não sei a qual parte você se referiu com "isso". Três leituras levam a trabalhos diferentes:

1. **0 aplicadas de 69** — o loop entrega e nada pousa.
2. **Advisory não força** — o trabalho seria política de enforcement.
3. **Contratos e testes se perdendo** — alargar o detector.

Qual delas? Prefiro perguntar a gastar uma sessão na errada.`;
check('BLOQUEIA: devolucao de 3 leituras da missao ("Qual delas?")', deveBloquear(meuErro1));

check('BLOQUEIA: "o que voce quer que eu faca primeiro?"',
  deveBloquear('Terminei a medição.\n\nO que você quer que eu faça primeiro?'));
check('BLOQUEIA: "quer que eu siga por A ou por B?"',
  deveBloquear('Levantei as duas rotas.\n\nQuer que eu siga por A ou por B?'));
check('BLOQUEIA: "qual dos dois?"',
  deveBloquear('Os dois caminhos resolvem.\n\nQual dos dois?'));
check('BLOQUEIA: "me diga qual" ',
  deveBloquear('Achei 4 candidatos.\n\nMe diga qual eu ataco.'));

// ── BOM: NAO pode bloquear ───────────────────────────────────────────────────

// R10 EXIGE perguntar antes de merge/push/PR. Bloquear aqui seria empurrar o agente
// a violar Tier 0 pra satisfazer este hook. É o caso mais importante do teste.
check('LIBERA: "Abro o PR?" (R10 obriga a perguntar — soberania [W])',
  !deveBloquear('Bateria toda verde, 6 arquivos.\n\nAbro o PR?'));
check('LIBERA: "posso mergear?" (soberania [W])',
  !deveBloquear('CI verde, 112 checks.\n\nPosso mergear?'));
check('LIBERA: "promovo o gate a required?" (soberania [W])',
  !deveBloquear('Mordida provada em 2 PRs.\n\nPromovo o gate a required?'));
check('LIBERA: "apago o modulo?" (irreversivel/soberania)',
  !deveBloquear('Inventário fechado, zero consumidor.\n\nApago o módulo?'));

// R13 permite menu explicitamente pra gosto/preferencia.
check('LIBERA: "prefere o primario roxo ou azul?" (gosto — R13 permite)',
  !deveBloquear('Montei as duas paletas.\n\nPrefere o primário roxo ou azul?'));

// Turno que EXECUTA nao pode ser bloqueado por ter pergunta no meio do relatorio.
check('LIBERA: pergunta no MEIO, turno termina em execucao',
  !deveBloquear('Perguntei-me se era o medidor ou o dado. Rodei os dois e era o medidor.\n\nCorrigi, rodei a suíte: 80/80 verde. Está no ar.'));
check('LIBERA: relatorio sem pergunta nenhuma',
  !deveBloquear('232 de 232 byte-idênticos. Zero divergência.'));
check('LIBERA: texto vazio / ausente (fail-open)',
  !deveBloquear('') && !deveBloquear(null) && !deveBloquear(undefined));

// ── FALSO-POSITIVO REAL da 1ª execução (2026-08-24) ──────────────────────────
// O hook bloqueou o turno em que eu EXPLICAVA por que ele existe, porque a explicação
// citava a devolução entre aspas. Citar o padrão não é cometê-lo — sem o `despirCitacao`
// o hook é presence-gate (LC-11) e ainda se auto-dispara (§5 2026-07-26: "a própria
// mensagem do mecanismo contém o texto que ele procura"). Estes 3 asserts são o bite:
// revertendo o strip de citação, o primeiro reprova.
const explicandoOHook = `- Selftest **15/15**; R13 não regrediu.

Por que um hook novo e não o do R13: o do R13 é advisory e o regex dele testa \`qual (deles|...)\`; eu terminei com *"Qual delas?"*. Regra escrita com detector furado é regra que não morde.

CI rodando; te aviso quando assentar.`;
check('LIBERA: turno que CITA a devolucao entre aspas (FP real da 1a execucao)',
  !deveBloquear(explicandoOHook));
check('LIBERA: padrao dentro de crase (codigo/identificador), nao e fala',
  !deveBloquear('Rodei tudo.\n\nO regex antigo era \`qual (voce|deles|opcao)\` e por isso furava.'));
// Controle POSITIVO do mesmo par: sem aspas, a devolucao real continua mordendo.
check('BLOQUEIA: mesma frase SEM aspas segue sendo devolucao (o strip nao cegou o hook)',
  deveBloquear('Tres leituras possiveis:\n\n1. um\n2. dois\n3. tres\n\nQual delas?'));

// ── contrato da mensagem ─────────────────────────────────────────────────────
check('MOTIVO cita R16, manda RODAR a porta viva e preserva a escalacao legitima',
  /R16/.test(MOTIVO) && /RODE/.test(MOTIVO) && /soberania/i.test(MOTIVO));
// LC-15: mecanismo NAO anuncia saida que nao implementa. As 3 saidas citadas no
// MOTIVO (soberania, irreversivel, gosto) sao exatamente as que o predicado libera.
check('LC-15: as saidas anunciadas no MOTIVO existem no predicado',
  !deveBloquear('Abro o PR?') && !deveBloquear('Prefere roxo ou azul?'));

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ block-pergunta-que-eu-resolvo: morde a devolucao e LIBERA a escalacao legitima (R10/R13)');
process.exit(fails ? 1 : 0);
