#!/usr/bin/env node
// @ts-check
/**
 * reguas-cross-model.mjs — braço de verificação CROSS-MODEL (cross-VENDOR) da grade de réguas.
 *
 * POR QUE EXISTE (fraqueza "verificação same-model" 5,0 · dimensão orquestracao-adversarial):
 * o refutador da grade (`.claude/workflows/reguas-do-sistema.js` fase Refutar), o adversário e
 * o ultrareview rodam Opus×Opus by-design — um modelo tende a CONCORDAR consigo mesmo
 * (agreement-bias). A régua de mercado é o Amp Oracle (segundo modelo, cross-vendor, ataca o
 * que o primeiro deixaria passar). Hoje o cross-model só acontecia ad-hoc (Codex #4009), sem
 * ORÁCULO INSTITUCIONAL. Este é o oráculo: um refutador GPT (cross-vendor à Opus) re-ataca,
 * BLIND (contexto-zero), as claims que o Opus MANTEVE no ledger, e o resultado é diffado contra
 * o veredito do Opus — o CONTROLE NEGATIVO (mesmo lote Opus-only [ledger] vs +cross-model).
 *
 * O QUE É / O QUE NÃO É:
 *   - É uma TÉCNICA DE PROCESSO do agente (roda sob demanda), NÃO um gate de CI — nunca
 *     avermelha PR de ninguém. Só a lógica pura tem selftest advisory (governance-script-tests).
 *   - Reusa a fronteira LLM que JÁ existe (`scripts/pr-critic/critica.mjs` → resolverProvider +
 *     chamarAgente) — não abre fronteira nova.
 *   - Ataca o veredito `refutador` de cada claim (a claim de superioridade), não a `integracao`.
 *
 * LIMITE HONESTO (declarar sempre): o GPT aqui julga pelo PRIOR DE TREINO — a fronteira é
 * chat/completions, SEM busca web. O Opus, no workflow, refutou COM busca web. Logo:
 *   - CONCORDA  = confirmação FRACA (o 2º modelo não buscou; só não contradiz de cabeça);
 *   - DIVERGE_DERRUBA = sinal FORTE — um modelo independente REJEITA a claim que o Opus manteve;
 *     é exatamente o que o agreement-bias esconderia. Vai pro humano decidir, nunca auto-aplica.
 *
 * TRÊS fontes do 2º modelo (o classificador é o MESMO — decoupla invocação de julgamento):
 *   (A) HTTP frontier cross-vendor  → OPENAI_API_KEY=... node ...reguas-cross-model.mjs
 *   (B) verdicts externos (Codex/GPT/outro-Claude) → node ...reguas-cross-model.mjs --verdicts <f.json> --modelo-cross <label>
 *   (C) dry (só mostra o que atacaria) → node ...reguas-cross-model.mjs --dry
 * O modo (B) existe porque o cross-model "acontecia ad-hoc (Codex #4009)": agora QUALQUER 2º
 * modelo (Codex, GPT com busca, um Claude não-Opus via Agent/opts.model) dumpa verdicts e ESTE
 * script produz o mesmo controle-negativo — o julgamento (classificarDivergencia) é single-source.
 *
 * Uso:
 *   OPENAI_API_KEY=... node scripts/governance/reguas-cross-model.mjs            # (A) ataca ACIMA+EMPATADO do ledger
 *   node scripts/governance/reguas-cross-model.mjs --verdicts v.json --modelo-cross claude-sonnet-5-web  # (B)
 *   node scripts/governance/reguas-cross-model.mjs --dry        # (C) sem modelo: mostra o que ATACARIA
 *   node scripts/governance/reguas-cross-model.mjs --selftest   # hermético: só a lógica pura
 *
 * verdicts.json (modo B): [{"id":"<claim-id>","veredito":"REFUTADO|EMPATADO|ACIMA_CONFIRMADO","razao":"...","quem_ja_faz":"..."}]
 *
 * Provider do modo A: cross-VENDOR obrigatório. Default OpenAI (gpt-4o). Se só houver
 * ANTHROPIC_API_KEY, ABORTA (Claude julgando Claude = mesmo-vendor, defeito que este arm existe
 * pra evitar) — a não ser que `--allow-same-vendor` (só pra fumaça/CI local). Modelo: REGUAS_XM_MODEL.
 */

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { chamarAgente, resolverProvider } from '../pr-critic/critica.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

/* CORE-INI (funções PURAS — testáveis sem fs/rede) */

// Força relativa dos vereditos: quanto MAIOR, mais "acima do mercado" a claim afirma.
export const FORCA = { REFUTADO: 0, EMPATADO: 1, ACIMA_CONFIRMADO: 2 };

/**
 * Classifica a divergência entre o veredito do Opus (ledger) e o do modelo cross-vendor.
 *   CONCORDA         — mesmo veredito;
 *   DIVERGE_DERRUBA  — o cross-vendor é ESTRITAMENTE mais cético (derrubou o que o Opus manteve) ← o valioso;
 *   DIVERGE_ELEVA    — o cross-vendor é MAIS otimista que o Opus;
 *   INDEFINIDO       — algum veredito fora do vocabulário.
 */
export function classificarDivergencia(opusVeredito, crossVeredito) {
  if (!(opusVeredito in FORCA) || !(crossVeredito in FORCA)) return 'INDEFINIDO';
  if (opusVeredito === crossVeredito) return 'CONCORDA';
  return FORCA[crossVeredito] < FORCA[opusVeredito] ? 'DIVERGE_DERRUBA' : 'DIVERGE_ELEVA';
}

/** Resumo do controle negativo (puro). n>0 garantido pelo caller. */
export function resumoControleNegativo(rows) {
  const conta = (c) => rows.filter((r) => r.divergencia === c).length;
  const concorda = conta('CONCORDA');
  const derruba = conta('DIVERGE_DERRUBA');
  const eleva = conta('DIVERGE_ELEVA');
  const n = rows.length;
  return {
    n,
    concorda,
    diverge_derruba: derruba,
    diverge_eleva: eleva,
    // taxa de concordância cross-vendor: alta = o Opus não estava sozinho; baixa = agreement-bias exposto
    taxa_concordancia: n ? Math.round((concorda / n) * 100) / 100 : null,
  };
}

/** Filtra as claims do ledger que valem re-atacar (por veredito Opus mantido). */
export function selecionarClaims(claims, { only, limit } = {}) {
  const alvo = only && only.length ? only : ['ACIMA_CONFIRMADO', 'EMPATADO'];
  let sel = (claims || []).filter((c) => alvo.includes(c.refutador));
  if (typeof limit === 'number' && limit > 0) sel = sel.slice(0, limit);
  return sel;
}

const SYSTEM = `Você é um REFUTADOR adversarial de mercado, de um MODELO DIFERENTE do que gerou a claim (verificação cross-vendor — seu papel é pegar o que o outro modelo deixaria passar por concordar consigo mesmo). Contexto ZERO: você não herda pesquisa nenhuma, julga só pela claim e pelo seu conhecimento do mercado 2026. Default CÉTICO: só confirme superioridade se, pelo que você sabe, NENHUM produto/prática publicado faz igual ou melhor. Responda em PT-BR.`;

const VERDICT_SCHEMA = {
  type: 'json_schema',
  schema: {
    type: 'object',
    additionalProperties: false,
    required: ['veredito', 'razao', 'quem_ja_faz'],
    properties: {
      veredito: { type: 'string', enum: ['ACIMA_CONFIRMADO', 'EMPATADO', 'REFUTADO'] },
      razao: { type: 'string', description: '1-3 frases; ancore em quem faz igual/melhor, ou por que ninguém faz' },
      quem_ja_faz: { type: 'string', description: 'produto/prática que já faz igual/melhor (vazio se REFUTADO não se aplica)' },
    },
  },
};

/** Prompt do refuter cross-vendor pra UMA claim — blind, sem o peer/razão que o Opus achou. */
export function montarPromptRefuter(claim) {
  return [
    `CLAIM (do IA OS do ERP oimpresso — Laravel/Inertia, multi-tenant BR): o oimpresso estaria ${claim.refutador === 'ACIMA_CONFIRMADO' ? 'ACIMA do mercado' : 'na barra do mercado (empatado)'} em:`,
    `"${claim.titulo}" (dimensão ${claim.dimensao}).`,
    '',
    'Pela sua leitura INDEPENDENTE do mercado 2026: quem já faz igual ou melhor em produção?',
    'Achou par claro → REFUTADO (diga quem). Parecido/parcial → EMPATADO. Só ACIMA_CONFIRMADO se, pelo que você sabe, não há par publicado.',
  ].join('\n');
}

/* CORE-FIM */

function argVal(flag, def = null) {
  const i = process.argv.indexOf(flag);
  return i !== -1 ? process.argv[i + 1] : def;
}
const argFlag = (flag) => process.argv.includes(flag);

/** Resolve provider cross-VENDOR (não-Anthropic). Retorna {provider,modelo} ou {erro}. */
function resolverCrossVendor(env = process.env) {
  const allowSame = argFlag('--allow-same-vendor');
  // força OpenAI por default; se REGUAS_XM_PROVIDER pedir outro não-anthropic, respeita
  const preferido = env.REGUAS_XM_PROVIDER || 'openai';
  const cfg = resolverProvider({ ...env, PR_CRITIC_PROVIDER: preferido, PR_CRITIC_MODEL: env.REGUAS_XM_MODEL || null });
  if (!cfg) {
    if (allowSame) {
      const same = resolverProvider(env); // qualquer chave, pra fumaça local
      if (same) return { ...same, aviso: `--allow-same-vendor: usando ${same.provider} (NÃO é cross-vendor real)` };
    }
    return { erro: `nenhuma chave cross-vendor (OPENAI_API_KEY) disponível pra provider "${preferido}"` };
  }
  if (cfg.provider === 'anthropic' && !allowSame) {
    return { erro: 'só ANTHROPIC_API_KEY disponível — Claude julgando Claude é mesmo-vendor (o defeito que este arm evita). Forneça OPENAI_API_KEY ou passe --allow-same-vendor.' };
  }
  return cfg;
}

const selftest = () => {
  let pass = 0;
  const fails = [];
  const deve = (nome, fn) => { try { fn(); pass++; console.log(`  ✓ ${nome}`); } catch (e) { fails.push(`${nome}: ${e.message}`); console.log(`  ✗ ${nome}\n      ${e.message}`); } };
  const eq = (a, b, m) => { if (JSON.stringify(a) !== JSON.stringify(b)) throw new Error(`${m ?? ''} esperado ${JSON.stringify(b)}, veio ${JSON.stringify(a)}`); };

  // classificarDivergencia — o coração do controle negativo
  deq: {
    deve('CONCORDA quando vereditos iguais', () => eq(classificarDivergencia('EMPATADO', 'EMPATADO'), 'CONCORDA'));
    deve('DIVERGE_DERRUBA: Opus ACIMA, cross REFUTADO (o valioso)', () => eq(classificarDivergencia('ACIMA_CONFIRMADO', 'REFUTADO'), 'DIVERGE_DERRUBA'));
    deve('DIVERGE_DERRUBA: Opus EMPATADO, cross REFUTADO', () => eq(classificarDivergencia('EMPATADO', 'REFUTADO'), 'DIVERGE_DERRUBA'));
    deve('DIVERGE_ELEVA: Opus EMPATADO, cross ACIMA', () => eq(classificarDivergencia('EMPATADO', 'ACIMA_CONFIRMADO'), 'DIVERGE_ELEVA'));
    deve('INDEFINIDO com veredito fora do vocabulário', () => eq(classificarDivergencia('EMPATADO', 'TALVEZ'), 'INDEFINIDO'));
  }
  // resumoControleNegativo
  deve('resumo conta as 3 classes + taxa', () => {
    const r = resumoControleNegativo([
      { divergencia: 'CONCORDA' }, { divergencia: 'CONCORDA' },
      { divergencia: 'DIVERGE_DERRUBA' }, { divergencia: 'DIVERGE_ELEVA' },
    ]);
    eq([r.n, r.concorda, r.diverge_derruba, r.diverge_eleva, r.taxa_concordancia], [4, 2, 1, 1, 0.5]);
  });
  // selecionarClaims
  deve('selecionarClaims: default pega ACIMA+EMPATADO, ignora REFUTADO', () => {
    const sel = selecionarClaims([
      { id: 'a', refutador: 'ACIMA_CONFIRMADO' }, { id: 'b', refutador: 'EMPATADO' }, { id: 'c', refutador: 'REFUTADO' },
    ]);
    eq(sel.map((x) => x.id), ['a', 'b']);
  });
  deve('selecionarClaims: --only + --limit', () => {
    const sel = selecionarClaims([
      { id: 'a', refutador: 'ACIMA_CONFIRMADO' }, { id: 'b', refutador: 'ACIMA_CONFIRMADO' }, { id: 'c', refutador: 'EMPATADO' },
    ], { only: ['ACIMA_CONFIRMADO'], limit: 1 });
    eq(sel.map((x) => x.id), ['a']);
  });
  // montarPromptRefuter — blind (não vaza o peer do Opus) + cético
  deve('prompt cita o título e NÃO vaza o peer do Opus', () => {
    const p = montarPromptRefuter({ titulo: 'Foo bar', dimensao: 'd', refutador: 'ACIMA_CONFIRMADO', peer: 'SEGREDO_DO_OPUS' });
    if (!p.includes('Foo bar')) throw new Error('não citou o título');
    if (p.includes('SEGREDO_DO_OPUS')) throw new Error('vazou o peer do Opus (deveria ser blind)');
  });
  // resolverCrossVendor — mesmo-vendor barrado
  deve('resolverCrossVendor barra Anthropic-só (mesmo-vendor)', () => {
    const r = resolverCrossVendor({ ANTHROPIC_API_KEY: 'x' });
    if (!r.erro) throw new Error('deveria barrar Claude-julgando-Claude');
  });
  deve('resolverCrossVendor aceita OpenAI (cross-vendor)', () => {
    const r = resolverCrossVendor({ OPENAI_API_KEY: 'x' });
    if (r.erro) throw new Error(`deveria aceitar OpenAI: ${r.erro}`);
    eq(r.provider, 'openai');
  });

  console.log(`\nreguas-cross-model selftest: ${pass} ok, ${fails.length} falhas`);
  if (fails.length) { console.error(fails.map((f) => ` - ${f}`).join('\n')); process.exit(1); }
};

async function main() {
  if (argFlag('--selftest')) return selftest();

  const ledgerDir = argVal('--ledger', join(ROOT, 'memory', 'reguas'));
  const only = (argVal('--only', '') || '').split(',').map((s) => s.trim()).filter(Boolean);
  const limitRaw = argVal('--limit', null);
  const limit = limitRaw ? Number(limitRaw) : null;
  const outDir = argVal('--out', join(ROOT, 'storage', 'reguas-cross-model'));
  const dry = argFlag('--dry');
  const verdictsFile = argVal('--verdicts', null);

  // modo (B): verdicts externos (Codex/GPT/outro-Claude) — join por id, mesmo classificador
  let verdictsMap = null;
  if (verdictsFile) {
    const arr = JSON.parse(readFileSync(verdictsFile, 'utf8'));
    verdictsMap = new Map(arr.map((v) => [v.id, v]));
  }

  const claims = JSON.parse(readFileSync(join(ledgerDir, 'claims.json'), 'utf8'));
  const selecionadas = selecionarClaims(claims, { only, limit });
  if (!selecionadas.length) { console.log('[cross-model] nenhuma claim mantida (ACIMA/EMPATADO) no ledger — nada a re-atacar.'); process.exit(0); }
  console.log(`[cross-model] ${selecionadas.length}/${claims.length} claims selecionadas (veredito Opus ∈ ${JSON.stringify(only.length ? only : ['ACIMA_CONFIRMADO', 'EMPATADO'])})`);

  let cfg = null;
  if (!dry && !verdictsFile) {
    cfg = resolverCrossVendor();
    if (cfg.erro) { console.log(`[cross-model] ${cfg.erro} — passe cross-model pulado (advisory, nunca falha).`); process.exit(0); }
    if (cfg.aviso) console.log(`[cross-model] AVISO ${cfg.aviso}`);
    console.log(`[cross-model] refutador cross-vendor: ${cfg.provider}:${cfg.modelo} (LIMITE: sem busca web — prior de treino; ver doc do script)`);
  }
  const modeloCross = dry ? '(dry)' : verdictsFile ? (argVal('--modelo-cross', 'externo')) : `${cfg.provider}:${cfg.modelo}`;

  const rows = [];
  for (const c of selecionadas) {
    if (dry) { rows.push({ id: c.id, titulo: c.titulo, dimensao: c.dimensao, opus: c.refutador, cross: null, divergencia: 'DRY', razao: '(--dry: não chamou o modelo)', quem_ja_faz: '', peer_opus: c.peer || '' }); continue; }
    let v;
    if (verdictsMap) {
      v = verdictsMap.get(c.id);
      if (!v || !v.veredito) { console.log(`[cross-model]   ${c.id}: sem veredito no --verdicts — pulado`); continue; }
    } else {
      try {
        v = await chamarAgente(cfg, {
          system: SYSTEM, schema: VERDICT_SCHEMA, userContent: montarPromptRefuter(c),
          vazioSeRecusa: (m) => ({ veredito: c.refutador, razao: `recusa do provider tratada como CONCORDA fail-safe (${m})`, quem_ja_faz: '' }),
        });
      } catch (e) {
        console.log(`[cross-model]   ${c.id}: ERRO ${e.message} — pulado`);
        continue;
      }
    }
    const divergencia = classificarDivergencia(c.refutador, v.veredito);
    rows.push({ id: c.id, titulo: c.titulo, dimensao: c.dimensao, opus: c.refutador, cross: v.veredito, divergencia, razao: v.razao, quem_ja_faz: v.quem_ja_faz || '', peer_opus: c.peer || '' });
    const selo = divergencia === 'DIVERGE_DERRUBA' ? '⚠️ DERRUBA' : divergencia === 'DIVERGE_ELEVA' ? '↑ eleva' : divergencia === 'CONCORDA' ? '= concorda' : divergencia;
    console.log(`[cross-model]   ${c.id}: Opus=${c.refutador} × ${modeloCross}=${v.veredito} → ${selo}`);
  }

  const resumo = rows.length ? resumoControleNegativo(rows) : { n: 0 };
  mkdirSync(outDir, { recursive: true });
  writeFileSync(join(outDir, 'divergencias.json'), JSON.stringify({
    gerado: argVal('--data', null) || 'sem-data (passe --data ISO)',
    modelo_cross: modeloCross,
    web_search: verdictsFile ? 'depende da fonte do --verdicts (declare em --modelo-cross)' : 'não (HTTP frontier = prior de treino)',
    ledger: ledgerDir,
    limite_metodologico: 'GPT sem busca web (prior de treino) vs Opus com busca web — CONCORDA é confirmação fraca; DIVERGE_DERRUBA é o sinal forte',
    resumo, rows,
  }, null, 2) + '\n');
  const limiteNota = verdictsFile
    ? `o 2º modelo é \`${modeloCross}\`; a cobertura de busca/independência depende da fonte declarada em --modelo-cross.`
    : 'o modelo cross-vendor (HTTP frontier) julga pelo PRIOR de treino (SEM busca web); o Opus refutou COM web.';
  writeFileSync(join(outDir, 'relatorio.md'), montarRelatorio(rows, resumo, modeloCross, limiteNota) + '\n');

  console.log(`\n[cross-model] CONTROLE NEGATIVO — Opus-only (ledger) vs +cross-model:`);
  console.log(`  ${resumo.n} claims · concorda ${resumo.concorda} · DIVERGE_DERRUBA ${resumo.diverge_derruba} · DIVERGE_ELEVA ${resumo.diverge_eleva} · taxa concordância ${resumo.taxa_concordancia}`);
  console.log(`  saída: ${outDir}/{divergencias.json,relatorio.md}`);
  if (!dry && resumo.diverge_derruba > 0) console.log(`  ⇒ ${resumo.diverge_derruba} divergência(s) REAL(is): o cross-vendor derrubou o que o Opus manteve — leva pro humano.`);
}

function montarRelatorio(rows, resumo, modelo, limiteNota) {
  const l = [
    '# Cross-model — controle negativo da grade de réguas',
    '',
    `2º modelo (não-Opus): \`${modelo}\` · claims re-atacadas: ${resumo.n} · **DIVERGE_DERRUBA: ${resumo.diverge_derruba}** · DIVERGE_ELEVA: ${resumo.diverge_eleva} · concorda: ${resumo.concorda} (taxa ${resumo.taxa_concordancia}).`,
    '',
    `> Limite: ${limiteNota || 'ver doc do script.'} CONCORDA = confirmação fraca; **DIVERGE_DERRUBA = sinal forte (um modelo independente rejeita o que o Opus manteve)** — decisão do humano, nunca auto-aplica.`,
    '',
    '| claim | dimensão | Opus | cross | divergência | por quê (cross) |',
    '|---|---|---|---|---|---|',
  ];
  const ordem = { DIVERGE_DERRUBA: 0, DIVERGE_ELEVA: 1, CONCORDA: 2, DRY: 3, INDEFINIDO: 4 };
  for (const r of [...rows].sort((a, b) => (ordem[a.divergencia] ?? 9) - (ordem[b.divergencia] ?? 9))) {
    const razao = (r.razao || '').replace(/\|/g, '\\|').slice(0, 240);
    const quem = r.quem_ja_faz ? ` — quem: ${r.quem_ja_faz.replace(/\|/g, '\\|').slice(0, 120)}` : '';
    l.push(`| ${(r.titulo || '').replace(/\|/g, '\\|').slice(0, 70)} | ${r.dimensao} | ${r.opus} | ${r.cross ?? '—'} | ${r.divergencia} | ${razao}${quem} |`);
  }
  return l.join('\n');
}

if (process.argv[1] && import.meta.url.endsWith(basename(process.argv[1]))) {
  main().catch((e) => { console.error(`[cross-model] ERRO: ${e.message}`); process.exit(1); });
}
