#!/usr/bin/env node
// ds-anchor-check.mjs — a ÂNCORA TEM DOIS ELOS: o protótipo E o design system.
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// O `anchor-content-check.mjs` (required desde 2026-07-08, ADR 0327) responde UMA pergunta:
// o `related_prototype` do charter EXISTE e não é o shell do app? O próprio workflow declara
// o escopo: "falha o merge só em MISSING/SHELL".
//
// Ninguém pergunta se o conteúdo daquele protótipo está CONFORME ao DS. E a tela de produção
// é DERIVADA dele — o charter de Arquivos diz isso com essas palavras ("derivada do protótipo
// prototipo-ui/cowork/arquivos-page.jsx") e o `Index.tsx` repete no comentário do `TOM_ACAO`
// ("espelha o mapa ACAO do protótipo").
//
// Resultado medido em 2026-08-25, no módulo Arquivos:
//   • o protótipo declarava buckets `common` e `public`, que NÃO existem no enum do banco.
//     A tela nasceu filtrando por valor inexistente e a lista voltava sempre vazia. Pego no
//     smoke de PRODUÇÃO, não por gate.
//   • 12 rótulos do protótipo são o próprio valor do enum em inglês (`upload`, `soft_delete`,
//     `signed_url`, `hard_delete`…) — foram pra tela cliente-facing assim.
//   • o protótipo declara tom `danger` (par soft) pra `sensitive`; a tela traduziu pra
//     `variant="destructive"`, que é FILL SÓLIDO (`bg-destructive text-white`, badge.tsx).
//     A variante soft correta — `danger` — existe no mesmo arquivo e é usada 3 linhas abaixo,
//     no `TOM_ACAO.hard_delete`. Mesmo .tsx, duas convenções.
//   • o protótipo reusa `kind="frescor"` (que é IDADE: recente/frio/distante) pra PRAZO,
//     produzindo a pílula "recente · em 1824 dias" — verde para cinco anos de distância.
//
// Nenhum desses quatro é pegável por gate de código: compilam, passam no lint, passam no
// pt-conformance, passam no anchor-content-check. O elo que falta é a comparação com o DS.
//
// O QUE ESTE GATE NÃO É: não é `dominio:check` (dicionário de termos PT-BR em prosa) nem
// `ds:canon:check` (cor crua fora de token). É a TRADUÇÃO protótipo→tela e a conformidade
// dos dois às variantes canônicas. Se algum destes elos já existir em máquina, ESTENDA-A —
// não crie a segunda.
//
// Uso:
//   node scripts/governance/ds-anchor-check.mjs                # relatório
//   node scripts/governance/ds-anchor-check.mjs --check         # rc≠0 se charter `live` violar
//   node scripts/governance/ds-anchor-check.mjs --selftest      # bite + controles
//
// Severidade por `status:` do charter (mesma regra da emenda de alcance):
//   draft → warn, rc=0 (trio novo NÃO nasce quebrando o CI — o furo do IT2b)
//   live  → fail, rc≠0 (não se promove draft→live com âncora infiel)

import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');

// Variantes SÓLIDAS do badge.tsx (fill + texto branco). Pill de ESTADO exige o par soft.
// Fonte: resources/js/Components/ui/badge.tsx — `success/warning/danger/info/neutral` são
// os tons soft tokenizados (`-soft`/`-fg`); `default/secondary/destructive` são fill.
const SOLIDAS = ['default', 'secondary', 'destructive'];
const SOFT = ['danger', 'warning', 'success', 'info', 'neutral'];

// ── R1 · Badge de estado com variante sólida ─────────────────────────────────
// Escopo deliberado: só `<Badge>`. `<Button variant="destructive">` é AÇÃO destrutiva e
// deve ser sólido — acusá-lo seria falso-positivo que faz o time desligar o gate.
export function r1BadgeSolido(src, arquivo) {
  const out = [];
  const re = /<Badge\b[^>]*?variant=(?:"([a-z]+)"|\{[^}]*?\?\s*'([a-z]+)'\s*:\s*'([a-z]+)'[^}]*\}|\{[^}]*?\?\s*"([a-z]+)"\s*:\s*"([a-z]+)"[^}]*\})/g;
  for (const m of src.matchAll(re)) {
    for (const v of [m[1], m[2], m[3], m[4], m[5]].filter(Boolean)) {
      if (SOLIDAS.includes(v)) {
        out.push(`${arquivo}: <Badge variant="${v}"> — variante SÓLIDA em pill de estado. Canon é o par soft (${SOFT.join('/')}) — AP7: fundo tintado 6% + borda 22%, nunca fill.`);
      }
    }
  }
  return out;
}

// ── R2 · enum cru como texto visível ─────────────────────────────────────────
export function r2EnumCru(src, arquivo) {
  const out = [];
  // (a) mapa do protótipo: `upload: { t: "info", l: "upload" }` — rótulo = valor.
  for (const m of src.matchAll(/(\w+)\s*:\s*\{[^}]*?\bl\s*:\s*"([^"]+)"/g)) {
    if (m[1] === m[2]) out.push(`${arquivo}: rótulo "${m[2]}" é o próprio valor do enum — sem tradução PT-BR.`);
    else if (/^[a-z]+(_[a-z]+)+$/.test(m[2])) out.push(`${arquivo}: rótulo "${m[2]}" é snake_case cru na tela.`);
  }
  // (b) const de enum no .tsx usada direto como rótulo de chip.
  for (const m of src.matchAll(/const\s+([A-Z_]+)\s*=\s*\[([^\]]+)\]\s*as\s+const/g)) {
    const vals = [...m[2].matchAll(/'([^']+)'/g)].map((x) => x[1]);
    const crus = vals.filter((v) => /^[a-z][a-z_]*$/.test(v));
    if (crus.length) out.push(`${arquivo}: const ${m[1]} = [${crus.join(', ')}] renderizada como rótulo — enum cru, sem mapa de tradução.`);
  }
  return out;
}

// ── R3 · o ELO NOVO: a tradução protótipo→tela é fiel? ───────────────────────
// É a única regra que lê os DOIS arquivos. Foi ela que pegou `danger`→`destructive`.
export function r3TraducaoInfiel(proto, tsx) {
  const out = [];
  const mapa = new Map();
  for (const m of proto.matchAll(/(\w+)\s*:\s*\{\s*t\s*:\s*"([a-z]+)"/g)) mapa.set(m[1], m[2]);
  for (const m of tsx.matchAll(/===\s*'(\w+)'\s*\?\s*'([a-z]+)'/g)) {
    const esperado = mapa.get(m[1]);
    if (esperado && esperado !== m[2]) {
      out.push(`ÂNCORA INFIEL: "${m[1]}" — o protótipo declara tom "${esperado}", a tela usa variant "${m[2]}". A âncora existe e o conteúdo divergiu.`);
    }
  }
  return out;
}

// ── R4 · `frescor` (idade) reusado pra prazo (vencimento) ────────────────────
// `kind="sla"` fica de fora: prazo É o domínio dele.
export function r4FrescorComoPrazo(src, arquivo) {
  const out = [];
  for (const m of src.matchAll(/<[A-Z]\w*[^>]*\bkind="frescor"[^>]*?\/?>/g)) {
    if (/prazo|vence|em \$\{|em \d/.test(m[0])) {
      out.push(`${arquivo}: kind="frescor" com rel de PRAZO — frescor é IDADE (recente/frio/distante), não contagem pra vencimento. Produz pílula verde "recente · em 1824 dias".`);
    }
  }
  return out;
}

// ── R5 · rótulo de <Select>/<option> que é o próprio valor do enum ───────────
// Irmã da R2, outro eixo: a R2 pega mapa de domínio, esta pega formulário. Achado ao
// corrigir o protótipo de Arquivos — `visibility` (private/internal/public) passou pela R2
// por não estar em mapa `l:`, e estava cru na tela do mesmo jeito.
export function r5OptionCrua(src, arquivo) {
  const out = [];
  for (const m of src.matchAll(/\{\s*value:\s*"([a-z_]+)",\s*label:\s*"([^"]+)"/g)) {
    const lab = m[2].split('—')[0].trim();
    if (lab === m[1]) out.push(`${arquivo}: option "${m[1]}" com rótulo igual ao valor do enum.`);
  }
  for (const m of src.matchAll(/<option value="([a-z_]+)">([^<]+)<\/option>/g)) {
    if (m[1] === m[2]) out.push(`${arquivo}: <option ${m[1]}> com rótulo igual ao valor do enum.`);
  }
  return out;
}

// ── R6 · `tone` colorido no StatusBadge = fill SÓLIDO no espelho ───────────────
// O ACHADO QUE MUDA A CULPA. No espelho DS (`_ds_bundle.js`), o caminho `tone` do
// StatusBadge é sólido por construção: `danger: { bg: var(--color-destructive), fg: '#fff' }`.
// No repo (`badge.tsx`), `danger` é o par SOFT e o sólido chama-se `destructive`.
// **Mesma palavra, renderização oposta.** Logo: quando a travessia trocou `tone="danger"`
// por `variant="destructive"`, ela foi VISUALMENTE FIEL ao espelho — o AP7 já estava
// violado na fonte. A R3 acusa a divergência de palavra; a R6 acusa a origem.
//
// As únicas famílias soft do espelho são as namespaced (`sla-*`, `fresc-*`, `tipo-*`,
// `canal-*`), alcançadas por `kind`+`value` — não por `tone`. Não existe família soft
// genérica de severidade: essa é a lacuna do espelho a reportar pro [W].
//
// ESCOPO: só StatusBadge. `<Alert>`/`Nota` com `tone` é tintado 6% POR ESPECIFICAÇÃO do DS
// — acusá-lo seria o falso-positivo que faz o time desligar o gate (medido: a primeira
// versão desta regra pegou 3 `Nota` legítimas no próprio protótipo).
export function r6ToneSolido(src, arquivo) {
  const out = [];
  for (const m of src.matchAll(/<StatusBadge\b[^>]*\btone=\{?["']?(danger|success|warning|info)["']?\}?[^>]*>/g)) {
    out.push(`${arquivo}: <StatusBadge tone="${m[1]}"> — caminho SÓLIDO do espelho. Use kind+value de uma família soft (sla-*/fresc-*).`);
  }
  for (const m of src.matchAll(/<StatusBadge\b[^>]*\btone=\{([^}]+)\}/g)) {
    if (!/^["']?(neutral|outline)/.test(m[1].trim())) out.push(`${arquivo}: <StatusBadge tone={${m[1].trim()}}> — tone dinâmico pode cair no caminho sólido.`);
  }
  return out;
}

// ── R7 · rótulo de coluna: inglês cru, ou divergente da tela viva ─────────────
// Buraco achado pelo verificador em 2026-08-25: a trilha do protótipo tinha `label: "Payload"`
// e o `Index.tsx` do main chama a mesma coluna de "Detalhe". As R1–R6 olham badge, mapa de
// domínio, option e tone — nenhuma olha CABEÇALHO DE COLUNA, que é texto grande na tela.
// Duas sondas:
//   (a) termo cru em inglês num `label:` de coluna (lista fechada, só o que aparece em ERP);
//   (b) divergência protótipo×tela: header do `.tsx` que não existe no conjunto do protótipo.
// A (b) só roda quando os dois arquivos existem — é o mesmo elo duplo da R3.
const INGLES_EM_COLUNA = ['payload', 'status', 'owner', 'amount', 'due', 'date', 'time', 'user', 'action', 'size', 'type', 'name', 'created', 'updated', 'deleted', 'file', 'details', 'detail', 'value', 'total', 'id'];

export function r7ColunaCrua(src, arquivo) {
  const out = [];
  for (const m of src.matchAll(/\blabel:\s*"([^"]+)"/g)) {
    const t = m[1].trim().toLowerCase();
    if (INGLES_EM_COLUNA.includes(t)) out.push(`${arquivo}: coluna "${m[1]}" — termo cru em inglês em cabeçalho de tabela.`);
  }
  return out;
}

/** Headers do .tsx (`header: 'X'`) que não têm par no protótipo (`label: "X"`) — e vice-versa. */
export function r7bHeaderDivergente(proto, tsx) {
  const pega = (s, re) => new Set([...s.matchAll(re)].map((m) => m[1].trim()));
  const noProto = pega(proto, /\blabel:\s*"([^"]+)"/g);
  const noTsx = pega(tsx, /\bheader:\s*'([^']+)'/g);
  const out = [];
  for (const h of noTsx) if (!noProto.has(h)) out.push(`HEADER DIVERGENTE: a tela usa "${h}" e o protótipo não tem esse rótulo em nenhuma coluna.`);
  return out;
}

export const analisarArquivo = (src, arq) => [...r1BadgeSolido(src, arq), ...r2EnumCru(src, arq), ...r4FrescorComoPrazo(src, arq), ...r5OptionCrua(src, arq), ...r6ToneSolido(src, arq), ...r7ColunaCrua(src, arq)];

// ── varredura: charter → (component, related_prototype) ──────────────────────
function charters(root = ROOT) {
  const achados = [];
  const varrer = (dir) => {
    let ents; try { ents = readdirSync(dir, { withFileTypes: true }); } catch { return; }
    for (const e of ents) {
      const p = join(dir, e.name);
      if (e.isDirectory()) { varrer(p); continue; }
      if (!e.name.endsWith('.charter.md')) continue;
      let txt = ''; try { txt = readFileSync(p, 'utf8'); } catch { continue; }
      const g = (k) => (txt.match(new RegExp(`^${k}:\\s*(.+)$`, 'm')) || [])[1]?.trim() || null;
      achados.push({ charter: p, status: g('status') || 'draft', component: g('component'), proto: g('related_prototype') });
    }
  };
  varrer(join(root, 'resources', 'js', 'Pages'));
  return achados;
}

export function auditar(root = ROOT) {
  const rel = [];
  for (const c of charters(root)) {
    const achados = [];
    const ler = (p) => { try { return readFileSync(join(root, p), 'utf8'); } catch { return null; } };
    const tsx = c.component ? ler(c.component) : null;
    if (tsx) achados.push(...analisarArquivo(tsx, c.component));
    const temProto = c.proto && !/^n\/a/i.test(c.proto);
    const proto = temProto ? ler(c.proto.split(/\s+/)[0]) : null;
    if (proto) {
      achados.push(...analisarArquivo(proto, c.proto.split(/\s+/)[0]));
      if (tsx) achados.push(...r3TraducaoInfiel(proto, tsx), ...r7bHeaderDivergente(proto, tsx));
    }
    if (achados.length) rel.push({ ...c, achados });
  }
  return rel;
}

// ── SELFTEST — bite + controle em CADA sonda (§5: `=== 0` verde também fica ──
// verde se o regex for cego). 15 casos, todos verificados em 2026-08-25.
if (process.argv.includes('--selftest')) {
  let f = 0;
  const t = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); f++; } };

  t(r1BadgeSolido('<Badge variant="destructive">x</Badge>', 'f').length === 1, 'BITE R1: acusa variante sólida em Badge');
  t(r1BadgeSolido('<Badge variant="danger">x</Badge>', 'f').length === 0, 'CN R1: variante soft canônica não é acusada');
  t(r1BadgeSolido('<Button variant="destructive">Apagar</Button>', 'f').length === 0, 'CN R1: Button destructive é AÇÃO, não estado — fora do escopo');
  t(r1BadgeSolido(`<Badge variant={a === 'x' ? 'destructive' : 'secondary'}>y</Badge>`, 'f').length === 2, 'BITE R1: acusa os dois lados do ternário');

  t(r2EnumCru('upload: { l: "upload" }', 'f').length === 1, 'BITE R2: acusa rótulo igual ao valor do enum');
  t(r2EnumCru('upload: { l: "Enviado" }', 'f').length === 0, 'CN R2: rótulo traduzido não é acusado');
  t(r2EnumCru('soft_delete: { l: "soft_delete" }', 'f').length === 1, 'BITE R2: acusa snake_case cru');
  t(r2EnumCru("const BUCKETS = ['sensitive', 'active'] as const", 'f').length === 1, 'BITE R2: acusa const de enum renderizada como rótulo');
  t(r2EnumCru("const ROTULOS = ['Sensível', 'Ativo'] as const", 'f').length === 0, 'CN R2: const já em PT-BR não é acusada');

  t(r3TraducaoInfiel('a: { t: "danger" }', `=== 'a' ? 'destructive'`).length === 1, 'BITE R3: acusa tom soft do protótipo virando sólido na tela');
  t(r3TraducaoInfiel('a: { t: "danger" }', `=== 'a' ? 'danger'`).length === 0, 'CN R3: tradução fiel não é acusada');
  t(r3TraducaoInfiel('a: { t: "danger" }', `=== 'b' ? 'destructive'`).length === 0, 'CN R3: chave sem par no protótipo não inventa erro');

  t(r4FrescorComoPrazo('<StatusBadge kind="frescor" rel={`em ${r} dias`} />', 'f').length === 1, 'BITE R4: frescor com prazo interpolado');
  t(r4FrescorComoPrazo('<StatusBadge kind="frescor" rel={r <= 0 ? "prazo vencido" : "x"} />', 'f').length === 1, 'BITE R4: frescor com "prazo vencido"');
  t(r4FrescorComoPrazo('<StatusBadge kind="frescor" rel="há 3 dias" />', 'f').length === 0, 'CN R4: frescor com idade real passa');
  t(r4FrescorComoPrazo('<StatusBadge kind="sla" rel="em 2 dias" />', 'f').length === 0, 'CN R4: kind="sla" (prazo é o domínio dele) fora do escopo');

  t(r5OptionCrua('{ value: "private", label: "private — só quem tem o dono" }', 'f').length === 1, 'BITE R5: option com rótulo igual ao valor');
  t(r5OptionCrua('{ value: "private", label: "Restrito — só quem alcança o dono" }', 'f').length === 0, 'CN R5: option traduzida não é acusada');
  t(r5OptionCrua('<option value="public">public</option>', 'f').length === 1, 'BITE R5: <option> crua no fallback sem DS');
  t(r5OptionCrua('<option value="public">Aberto</option>', 'f').length === 0, 'CN R5: <option> traduzida passa');

  t(r6ToneSolido('<StatusBadge tone="danger" label="x" />', 'f').length === 1, 'BITE R6: StatusBadge tone sólido literal');
  t(r6ToneSolido('<StatusBadge tone={BK(b).t} label="x" />', 'f').length === 1, 'BITE R6: StatusBadge tone dinâmico');
  t(r6ToneSolido('<StatusBadge kind="sla" value="expired" label="x" />', 'f').length === 0, 'CN R6: kind+value (família soft) passa');
  t(r6ToneSolido('<Nota tone="danger" title="x">y</Nota>', 'f').length === 0, 'CN R6: Alert/Nota tone é tintado por spec — fora do escopo');
  t(r6ToneSolido('<StatusBadge tone="neutral" label="x" />', 'f').length === 0, 'CN R6: tone neutral não é fill colorido');

  t(r7ColunaCrua('{ key: "payload", label: "Payload" }', 'f').length === 1, 'BITE R7: coluna "Payload" em inglês');
  t(r7ColunaCrua('{ key: "payload", label: "Detalhe" }', 'f').length === 0, 'CN R7: coluna traduzida passa');
  t(r7ColunaCrua('{ label: "Vinculado a" }', 'f').length === 0, 'CN R7: rótulo PT-BR de várias palavras passa');
  t(r7bHeaderDivergente('label: "Detalhe"', "header: 'Detalhe'").length === 0, 'CN R7b: header igual nos dois não é acusado');
  t(r7bHeaderDivergente('label: "Payload"', "header: 'Detalhe'").length === 1, 'BITE R7b: header da tela sem par no protótipo');

  console.log(f ? `\nSELFTEST FALHOU (${f})` : '\nSELFTEST OK — a sonda morde e libera certo.');
  process.exit(f ? 1 : 0);
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const rel = auditar();
const live = rel.filter((r) => r.status === 'live');
const draft = rel.filter((r) => r.status !== 'live');

for (const r of [...live, ...draft]) {
  const sev = r.status === 'live' ? '❌ FAIL' : '⚠️  warn';
  console.log(`\n${sev}  ${r.charter.replace(ROOT + '/', '')}  (status: ${r.status})`);
  for (const a of r.achados) console.log(`        • ${a}`);
}
if (!rel.length) console.log('✅ Nenhuma divergência de âncora×DS.');
else console.log(`\n${live.length} charter(s) live com divergência · ${draft.length} draft (só aviso).`);

if (process.argv.includes('--check') && live.length) {
  console.error('\n❌ Âncora infiel ao DS em charter `live`. Corrija ou volte o charter pra draft.');
  process.exit(1);
}
