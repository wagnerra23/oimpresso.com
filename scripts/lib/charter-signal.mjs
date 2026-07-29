/**
 * charter-signal.mjs — FONTE ÚNICA da regra "o charter tem sinal de prod?".
 *
 * POR QUE EXISTE: a regra vivia COPIADA em dois lugares —
 *   · `governance/charter-live-signal.mjs`    (PUNE `status: live` sem sinal)
 *   · `governance/charter-promote-signal.mjs` (PROMOVE `draft` COM sinal)
 * O próprio cabeçalho do promote diz "FONTES DE SINAL (idênticas ao charter-live-signal —
 * SoC: mesma verdade de prod)". Duas cópias que DEVEM ser iguais é exatamente a doença que
 * `scripts/lib/uc-regex.mjs` documenta (4 drifts) e que `page-path.mjs` matou pro escopo de
 * tela. Já havia drift silencioso aqui: o live-signal lê `ultima_data` do ledger, o promote
 * ignora — mesma regra, respostas diferentes. Com o 3º consumidor (`requisitos-status.mjs`,
 * seção "Prontidão de tela") seriam TRÊS cópias. Extraído antes disso acontecer.
 *
 * FRONTEIRA: esta lib só RESPONDE "há sinal, e de qual fonte?". Não decide o que fazer com a
 * resposta — punir (gate), promover (script de escrita) ou exibir (painel) é de cada consumidor,
 * e cada um formata o próprio rótulo. Por isso a lib devolve DADO estruturado, nunca string
 * pronta: foi o que permitiu extrair sem mudar um byte da saída dos dois consumidores atuais.
 *
 * AS 3 FONTES (ordem de força — flag diz "pode servir", hit diz "serviu"):
 *   1. `governance/prod-flags.json` → `live[<key>]` com ≥1 business_id
 *   2. `governance/route-hits.json` → `pages[<key>].hits > 0` (ledger do middleware
 *      ContadorHitsRota, janela do último `route-hits:export`)
 *   3. campo `smoke:` no frontmatter do charter (ref a um smoke datado)
 * `<key>` = `component` sem o prefixo `resources/js/Pages/` e sem `.tsx`.
 *
 * fs-puro (2 JSON). Sem deps/DB/PHP. Nunca chama `process.exit`: erro de parse vira `throw`,
 * e quem chama decide a mensagem e o código de saída (era o comportamento dos dois consumidores).
 */

import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

/** Bloco do frontmatter (sem os `---`). String vazia se não houver. */
export function frontmatterDe(body) {
  const m = body.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  return m ? m[1] : '';
}

/** Campo escalar do frontmatter, sem aspas. `null` se ausente. */
export function campo(fm, chave) {
  const m = fm.match(new RegExp(`^${chave}:\\s*(.+)$`, 'm'));
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : null;
}

/** `resources/js/Pages/X/Y.tsx` → `X/Y`. `null` se o component não for uma Page. */
export function compKeyDe(component) {
  if (!component) return null;
  const m = component.replace(/\\/g, '/').match(/resources\/js\/Pages\/(.+)\.tsx$/);
  return m ? m[1] : null;
}

/**
 * Carrega as 2 fontes de arquivo. Ausente = `{}` (o projeto pode não ter ledger ainda).
 * JSON inválido = `throw` com o caminho relativo na mensagem — quem chama prefixa o nome
 * do próprio script e sai com 2, como já fazia.
 */
export function carregarFontes(root) {
  const ler = (rel, prop) => {
    const p = join(root, rel);
    if (!existsSync(p)) return {};
    try { return JSON.parse(readFileSync(p, 'utf8'))[prop] || {}; }
    catch (e) { throw new Error(`${rel} não parseia (${e.message})`); }
  };
  return {
    live: ler('governance/prod-flags.json', 'live'),
    hits: ler('governance/route-hits.json', 'pages'),
  };
}

/**
 * O sinal de um charter, como DADO.
 *
 * @returns {{fonte: 'prod-flags'|'route-hits'|'smoke'|null, key: string|null,
 *            biz: string[], hits: number|null, ultima_data: string|null, smoke: string|null}}
 * `fonte: null` = sem sinal nenhum (o `live_sem_sinal` do gate; o "não promovível" do promote).
 */
export function sinalDe(fm, fontes) {
  const key = compKeyDe(campo(fm, 'component'));
  const smoke = campo(fm, 'smoke');
  const biz = key && Array.isArray(fontes.live[key]) ? fontes.live[key] : [];
  const hit = key && fontes.hits[key] && fontes.hits[key].hits > 0 ? fontes.hits[key] : null;
  const base = { key, biz, hits: hit ? hit.hits : null, ultima_data: hit ? hit.ultima_data ?? null : null, smoke };
  if (biz.length) return { ...base, fonte: 'prod-flags' };
  if (hit) return { ...base, fonte: 'route-hits' };
  if (smoke) return { ...base, fonte: 'smoke' };
  return { ...base, fonte: null };
}

/**
 * Placeholder de revisão humana ainda aberto no charter (`TODO Wagner`, `❌ TODO`).
 *
 * Regra do `charter-promote-signal`: charter com placeholder NÃO é promovido mesmo COM sinal —
 * os Non-Goals/Anti-hooks ainda esperam o [W] (anti-alucinação, skill `charter-write`). Mora
 * aqui porque o painel precisa dizer a mesma coisa que o promotor faria.
 */
export function temPlaceholderAberto(src) {
  return /TODO Wagner|❌ TODO/.test(src);
}
