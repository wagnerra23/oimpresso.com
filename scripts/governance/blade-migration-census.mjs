#!/usr/bin/env node
/**
 * blade-migration-census.mjs — o CONTRATO DE COMPLETUDE da ADR 0277, derivado da árvore.
 *
 * ── O QUE RESPONDE ──────────────────────────────────────────────────────────
 * "Quantos endpoints ainda servem Blade, e onde?" — por módulo/domínio, recalculado
 * do código, nunca lembrado.
 *
 * ── POR QUE EXISTE ──────────────────────────────────────────────────────────
 * A ADR 0277 ("Rota de migração do backbone Blade") decidiu, em 2026-06-13, que
 * "migrado" = route Blade MORTO ou 302 — nunca "existe tela React". E previu, na
 * Onda 10, um "contador de routes Blade vivos == 0". Medido em 2026-08-08: esse
 * contador NUNCA foi construído (0 scripts, 0 workflows). O censo A–L do
 * ROADMAP-ONDAS-BLADE-ADVERSARIOS.md foi digitado à mão e não é recalculado desde
 * então. É o caso-livro da ADR 0256: escrito+lembrado apodrece.
 *
 * ── FRONTEIRA (não duplica régua — proibicoes §5 2026-07-09) ────────────────
 *   · screen-coverage-map  = charter/e2e/scorecard por TELA React (Pages/**)
 *   · casos-coverage-guard = UC↔teste                            (required)
 *   · module-surface       = inventário de arquivos por módulo
 *   · ESTE                 = ENDPOINT serve Blade ou Inertia? (o eixo que ninguém mede)
 *
 * ── LIMITE HONESTO ──────────────────────────────────────────────────────────
 * É análise ESTÁTICA. O oráculo fiel seria `php artisan route:list` (runtime), mas
 * ele exige app bootado + vendor — indisponível em worktree e caro no CI. Por isso
 * este script NÃO afirma "esta rota responde"; afirma "este endpoint declarado
 * resolve para um método que renderiza Blade". Divergências conhecidas ficam em
 * `--report` como `indeterminado`, nunca silenciadas.
 *
 * Uso:
 *   node scripts/governance/blade-migration-census.mjs --report
 *   node scripts/governance/blade-migration-census.mjs --json
 *   node scripts/governance/blade-migration-census.mjs --selftest
 */

import fs from 'node:fs'
import path from 'node:path'

const ROOT = process.cwd()

// ── 1. Descoberta dos arquivos de rota ──────────────────────────────────────
/**
 * ⚠️ DEDUP OBRIGATÓRIO — o mesmo arquivo entrava DUAS vezes no Windows.
 *
 * Os módulos nWidart usam `Routes/web.php` (R maiúsculo). O NTFS é
 * case-INSENSITIVE, então `existsSync('routes/web.php')` também dá true e o
 * arquivo era contado 2×; em Linux (ext4, case-sensitive) não. Medido em
 * 2026-08-08 contra produção: 60 "arquivos" no Windows × 33 reais — **27
 * duplicados**, inflando o censo de 471 para 683 (+45%).
 *
 * Isso não era só um número feio: o baseline foi gerado no Windows e a catraca
 * roda no CI em **Linux**, então ela media 471 contra baseline 683, via
 * "progresso" e NUNCA morderia. O bite-test que a aprovou rodou no Windows, com
 * os dois lados igualmente inflados — por isso passou.
 *
 * `realpathSync.native` resolve o case REAL gravado no disco (Windows) e é
 * no-op onde o case já é o real (Linux), então dois arquivos legitimamente
 * distintos continuam distintos.
 */
export function rotaFiles(root = ROOT) {
  const vistos = new Map() // chave canônica -> path original
  const add = (p) => {
    if (!fs.existsSync(p)) return
    let chave
    try {
      chave = fs.realpathSync.native(p)
    } catch {
      chave = p
    }
    // no Windows o realpath já normaliza o case; o lower é o cinto de segurança
    const k = process.platform === 'win32' ? chave.toLowerCase() : chave
    if (!vistos.has(k)) vistos.set(k, p)
  }

  add(path.join(root, 'routes', 'web.php'))
  const mods = path.join(root, 'Modules')
  if (fs.existsSync(mods)) {
    for (const m of fs.readdirSync(mods)) {
      for (const cand of ['Http/routes.php', 'Routes/web.php', 'routes/web.php']) {
        add(path.join(mods, m, cand))
      }
    }
  }
  return [...vistos.values()]
}

// ── 2. Resolver `use` → FQCN ────────────────────────────────────────────────
export function parseUses(src) {
  const map = new Map()
  const re = /^\s*use\s+([A-Za-z0-9_\\]+)\s*;/gm
  let m
  while ((m = re.exec(src))) {
    const fq = m[1]
    map.set(fq.split('\\').pop(), fq)
  }
  return map
}

/**
 * Namespaces declarados em `Route::group(['namespace' => 'X'])`.
 * É assim que os módulos nWidart resolvem 'FooController@bar' sem `use` —
 * ignorar isto subcontava a Jana em 3 métodos Blade (medido 2026-08-08).
 */
export function parseGroupNamespaces(src) {
  const out = []
  const re = /'namespace'\s*=>\s*'([A-Za-z0-9_\\]+)'/g
  let m
  while ((m = re.exec(src))) if (!out.includes(m[1])) out.push(m[1])
  return out
}

// ── 3. Extrair endpoints declarados ─────────────────────────────────────────
const VERBOS = 'get|post|put|patch|delete|any|match'

export function extrairRotas(src, root = ROOT) {
  const uses = parseUses(src)
  const nss = parseGroupNamespaces(src)
  const rotas = []
  const resolve = (n) => {
    const limpo = n.replace(/^\\/, '')
    if (limpo.includes('\\')) return limpo
    if (uses.has(limpo)) return uses.get(limpo)
    // nome curto sem `use`: tenta os namespaces de grupo, o que EXISTIR no disco vence
    for (const ns of nss) {
      const cand = `${ns}\\${limpo}`
      if (pathDoController(cand, root)) return cand
    }
    return nss.length === 1 ? `${nss[0]}\\${limpo}` : limpo
  }

  // (a) Route::verbo('uri', [Ctrl::class, 'metodo'])
  const reArr = new RegExp(
    `Route::(${VERBOS})\\(\\s*'([^']*)'\\s*,\\s*\\[\\s*([A-Za-z0-9_\\\\]+)::class\\s*,\\s*'([A-Za-z0-9_]+)'\\s*\\]`,
    'g',
  )
  let m
  while ((m = reArr.exec(src))) {
    rotas.push({ verbo: m[1], uri: m[2], ctrl: resolve(m[3]), metodo: m[4], forma: 'array' })
  }

  // (b) Route::verbo('uri', 'Ctrl@metodo')  — string legada
  const reStr = new RegExp(`Route::(${VERBOS})\\(\\s*'([^']*)'\\s*,\\s*'([A-Za-z0-9_\\\\]+)@([A-Za-z0-9_]+)'`, 'g')
  while ((m = reStr.exec(src))) {
    rotas.push({ verbo: m[1], uri: m[2], ctrl: resolve(m[3]), metodo: m[4], forma: 'string' })
  }

  // (c) Route::verbo('uri', fn/function → closure)
  const reClo = new RegExp(`Route::(${VERBOS})\\(\\s*'([^']*)'\\s*,\\s*(function\\s*\\(|fn\\s*\\()`, 'g')
  while ((m = reClo.exec(src))) {
    const corpo = src.slice(m.index, m.index + 900)
    rotas.push({ verbo: m[1], uri: m[2], ctrl: null, metodo: null, forma: 'closure', corpo })
  }

  // (d) Route::resource('uri', Ctrl::class | 'CtrlString') [, opts] [->only/except]
  // A forma STRING é a dos módulos nWidart (ex.: Jana `Route::resource('/metas','MetasController',[...])`).
  // Ignorá-la subcontava 4 endpoints Blade só na Jana (medido 2026-08-08).
  const reRes = /Route::(resource|apiResource)\(\s*'([^']*)'\s*,\s*(?:([A-Za-z0-9_\\]+)::class|'([A-Za-z0-9_\\]+)')/g
  const SETE = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']
  while ((m = reRes.exec(src))) {
    // janela até o `;` da própria declaração — evita capturar ->only() de outra rota
    const resto = src.slice(m.index + m[0].length)
    const fim = resto.indexOf(';')
    const tail = fim >= 0 ? resto.slice(0, fim) : resto.slice(0, 400)
    let metodos = SETE
    const only = tail.match(/->\s*only\(([^)]*)\)/)
    const exc = tail.match(/->\s*except\(([^)]*)\)/)
    const nomes = (s) => [...s.matchAll(/'([a-z]+)'/g)].map((x) => x[1])
    if (only) metodos = nomes(only[1])
    else if (exc) { const rm = nomes(exc[1]); metodos = SETE.filter((x) => !rm.includes(x)) }
    const alvo = m[3] || m[4]
    for (const met of metodos) {
      rotas.push({ verbo: 'resource', uri: `${m[2]}/${met}`, ctrl: resolve(alvo), metodo: met, forma: 'resource' })
    }
  }

  return rotas
}

// ── 4. Localizar o arquivo do controller ────────────────────────────────────
export function pathDoController(fqcn, root = ROOT) {
  if (!fqcn) return null
  const p = fqcn.replace(/^\\/, '').split('\\')
  let rel = null
  if (p[0] === 'App') rel = path.join('app', ...p.slice(1)) + '.php'
  else if (p[0] === 'Modules') rel = path.join('Modules', ...p.slice(1)) + '.php'
  if (rel) { const abs = path.join(root, rel); if (fs.existsSync(abs)) return abs }
  // fallback: procurar pelo basename nas duas raízes
  const base = p[p.length - 1] + '.php'
  for (const raiz of ['app/Http/Controllers', 'Modules']) {
    const achado = buscar(path.join(root, raiz), base)
    if (achado) return achado
  }
  return null
}

function buscar(dir, alvo, prof = 0) {
  if (prof > 6 || !fs.existsSync(dir)) return null
  let ents
  try { ents = fs.readdirSync(dir, { withFileTypes: true }) } catch { return null }
  for (const e of ents) {
    const p = path.join(dir, e.name)
    if (e.isFile() && e.name === alvo) return p
    if (e.isDirectory() && !['node_modules', '.git', 'vendor'].includes(e.name)) {
      const r = buscar(p, alvo, prof + 1)
      if (r) return r
    }
  }
  return null
}

// ── 5. Extrair o corpo de UM método ─────────────────────────────────────────
export function corpoDoMetodo(src, metodo) {
  const re = new RegExp(`function\\s+${metodo}\\s*\\(`, 'g')
  const m = re.exec(src)
  if (!m) return null
  // varre por balanceamento de chaves a partir da primeira `{` após a assinatura
  let i = src.indexOf('{', m.index)
  if (i < 0) return null
  let nivel = 0
  for (let j = i; j < src.length; j++) {
    if (src[j] === '{') nivel++
    else if (src[j] === '}') { nivel--; if (nivel === 0) return src.slice(i, j + 1) }
  }
  return src.slice(i)
}

// ── 6. Classificar um trecho de código ──────────────────────────────────────
export function classificarCorpo(corpo) {
  if (!corpo) return 'indeterminado'
  const inertia = /Inertia::render|inertia\s*\(/.test(corpo)
  // `view(` mas NÃO `->view(` de mailable, e nem `view()` dentro de string
  const blade = /(?<![-=>\w])view\s*\(\s*['"]/.test(corpo) || /return\s+view\s*\(/.test(corpo)
  if (inertia && blade) return 'hibrido'
  if (inertia) return 'react'
  if (blade) return 'blade'
  return 'outro'
}

/**
 * Classifica SEGUINDO a indireção `$this->helper(...)`.
 *
 * Por que existe (medido 2026-08-08, não suposto): `ChatController@show` tem 8 linhas
 * e só delega — quem renderiza é `renderChat()`. Classificação de 1 nível dizia
 * "outro" para uma rota que serve React. Delegação a helper privado é padrão comum
 * em Laravel, então 1 nível subcontava os DOIS lados (react e blade).
 *
 * Limites explícitos: profundidade 3, sem ciclo, e só segue `$this->` (não resolve
 * herança nem trait — esses continuam caindo em 'outro'/'indeterminado', jamais
 * num palpite).
 */
export function classificarComIndirecao(src, metodo, prof = 0, visto = new Set()) {
  if (prof > 3 || !metodo || visto.has(metodo)) return 'indeterminado'
  visto.add(metodo)
  const corpo = corpoDoMetodo(src, metodo)
  if (!corpo) return 'indeterminado'
  const direto = classificarCorpo(corpo)
  if (direto !== 'outro') return direto
  // delegou? segue cada `$this->x(` do corpo
  const chamadas = [...corpo.matchAll(/\$this->([A-Za-z0-9_]+)\s*\(/g)].map((m) => m[1])
  const tipos = new Set()
  for (const c of chamadas) {
    const t = classificarComIndirecao(src, c, prof + 1, visto)
    if (t === 'react' || t === 'blade' || t === 'hibrido') tipos.add(t)
  }
  if (tipos.has('hibrido') || (tipos.has('react') && tipos.has('blade'))) return 'hibrido'
  if (tipos.has('react')) return 'react'
  if (tipos.has('blade')) return 'blade'
  return 'outro'
}

// ── 7. Censo ────────────────────────────────────────────────────────────────
export function censo(root = ROOT) {
  const cacheCtrl = new Map()
  const linhas = []
  for (const rf of rotaFiles(root)) {
    const src = fs.readFileSync(rf, 'utf8')
    const escopo = rf.includes(`${path.sep}Modules${path.sep}`)
      ? rf.split(path.sep)[rf.split(path.sep).indexOf('Modules') + 1]
      : 'core'
    for (const r of extrairRotas(src, root)) {
      let tipo
      if (r.forma === 'closure') {
        tipo = classificarCorpo(r.corpo)
      } else {
        const cp = pathDoController(r.ctrl, root)
        if (!cp) tipo = 'indeterminado'
        else {
          let csrc = cacheCtrl.get(cp)
          if (csrc === undefined) { csrc = fs.readFileSync(cp, 'utf8'); cacheCtrl.set(cp, csrc) }
          tipo = classificarComIndirecao(csrc, r.metodo)
        }
      }
      linhas.push({ escopo, uri: r.uri, ctrl: r.ctrl, metodo: r.metodo, forma: r.forma, tipo })
    }
  }
  return linhas
}

export function agregar(linhas) {
  const porEscopo = new Map()
  for (const l of linhas) {
    if (!porEscopo.has(l.escopo)) porEscopo.set(l.escopo, { blade: 0, react: 0, hibrido: 0, outro: 0, indeterminado: 0, total: 0 })
    const a = porEscopo.get(l.escopo)
    a[l.tipo]++; a.total++
  }
  return porEscopo
}

// ── 8. Catraca (peça 3) ─────────────────────────────────────────────────────
/**
 * A regra da catraca: NENHUM escopo pode subir vs baseline. Só desce.
 *
 * ⚠️ FRONTEIRA DECLARADA — esta regra existe em DOIS lugares, de propósito:
 *   · AQUI (JS)  → roda no PR, sem PHP nem DB. Pega a regressão ANTES do merge.
 *   · BladeMigrationSentinelCommand::avaliar (PHP) → roda por cron, com DB.
 *     Pega também ESTAGNAÇÃO (temporal) e escala pro brief.
 *
 * Não é duplicação de régua (proibicoes §5): são MOMENTOS diferentes (pré-merge ×
 * pós-merge) e efeitos diferentes (vermelho no PR × task no brief), e o eixo
 * temporal não é computável no PR. O que É compartilhado — e por isso não pode
 * divergir — é o BASELINE: um arquivo só, `governance/blade-migration-baseline.json`.
 * Os dois lados têm teste da mesma regra; se um dia divergirem, o baseline único
 * faz a divergência aparecer como veredito contraditório no mesmo dado.
 */
export function ratchet(atual, baseline) {
  const regressoes = []
  for (const [escopo, dados] of Object.entries(atual.por_escopo || {})) {
    const antes = Number(baseline.por_escopo?.[escopo]?.blade ?? 0)
    const agora = Number(dados.blade ?? 0)
    if (agora > antes) regressoes.push({ escopo, de: antes, para: agora, delta: agora - antes })
  }
  const totalAtual = Number(atual.total_blade ?? 0)
  const totalBase = Number(baseline.total_blade ?? 0)
  return { ok: regressoes.length === 0, regressoes, delta: totalAtual - totalBase, totalAtual, totalBase }
}

// ── 9. Selftest ─────────────────────────────────────────────────────────────
function selftest() {
  let ok = 0, fail = 0
  const t = (nome, cond) => { if (cond) { ok++; console.log(`  [PASS] ${nome}`) } else { fail++; console.log(`  [FAIL] ${nome}`) } }

  // classificarCorpo
  t('classifica Inertia::render como react', classificarCorpo('return Inertia::render("X");') === 'react')
  t('classifica return view( como blade', classificarCorpo('return view("metas.index");') === 'blade')
  t('classifica os dois como hibrido', classificarCorpo('if($a) return Inertia::render("X"); return view("y");') === 'hibrido')
  t('classifica JSON como outro', classificarCorpo('return response()->json([]);') === 'outro')
  t('corpo vazio = indeterminado', classificarCorpo(null) === 'indeterminado')
  // CONTROLE NEGATIVO: ->view( de mailable NAO conta como blade
  t('NEG: ->view( de mailable nao vira blade', classificarCorpo('return $this->view("emails.x");') !== 'blade')

  // extrairRotas — as 4 formas
  const amostra = `
use App\\Http\\Controllers\\FooController;
Route::get('/a', [FooController::class, 'index']);
Route::post('/b', 'BarController@store');
Route::get('/c', fn () => view('x'));
Route::resource('items', ItemController::class)->only('index', 'show');
`
  const rs = extrairRotas(amostra)
  t('extrai forma array', rs.some((r) => r.uri === '/a' && r.metodo === 'index' && r.ctrl.endsWith('FooController')))
  t('extrai forma string', rs.some((r) => r.uri === '/b' && r.metodo === 'store'))
  t('extrai closure', rs.some((r) => r.forma === 'closure' && r.uri === '/c'))
  t('resource ->only expande 2', rs.filter((r) => r.forma === 'resource').length === 2)
  t('resolve use → FQCN', rs.find((r) => r.uri === '/a').ctrl === 'App\\Http\\Controllers\\FooController')

  // BITE-TEST dos 2 bugs achados contra a árvore real (2026-08-08, Jana subcontada):
  // (1) resource com STRING — forma dos módulos nWidart
  const nwidart = `
Route::group(['namespace' => 'Modules\\Jana\\Http\\Controllers'], function () {
    Route::resource('/metas', 'MetasController', ['names' => ['index' => 'jana.metas.index']]);
    Route::get('/alertas', 'AlertasController@index')->name('jana.alertas.index');
});
`
  const rn = extrairRotas(nwidart)
  t('BITE: resource com STRING expande 7', rn.filter((r) => r.forma === 'resource').length === 7)
  t('BITE: namespace de grupo resolve nome curto',
    rn.find((r) => r.uri === '/alertas')?.ctrl === 'Modules\\Jana\\Http\\Controllers\\AlertasController')
  // (2) CONTROLE NEGATIVO: ->only() de OUTRA rota não vaza para o resource anterior
  const vaza = `
Route::resource('a', AController::class);
Route::get('/b', [BController::class, 'x'])->only('nada');
`
  t('NEG: ->only de outra rota nao encolhe o resource', extrairRotas(vaza).filter((r) => r.forma === 'resource').length === 7)

  // corpoDoMetodo — balanceamento
  const cls = `class X { public function a(){ if(true){ return view("a"); } } public function b(){ return Inertia::render("B"); } }`
  t('corpoDoMetodo isola o metodo a', classificarCorpo(corpoDoMetodo(cls, 'a')) === 'blade')
  t('corpoDoMetodo isola o metodo b', classificarCorpo(corpoDoMetodo(cls, 'b')) === 'react')

  // BITE-TEST da indireção (caso real: ChatController@show → renderChat → Inertia)
  const indir = `class C {
    public function show($id){ return $this->renderChat($id); }
    protected function renderChat($c){ return Inertia::render('Jana/Chat', []); }
    public function velha(){ return $this->pintaBlade(); }
    protected function pintaBlade(){ return view('x.y'); }
    public function nada(){ return response()->json([]); }
    public function ciclo(){ return $this->ciclo(); }
  }`
  t('BITE: segue $this-> ate o Inertia (1 nivel)', classificarComIndirecao(indir, 'show') === 'react')
  t('BITE: segue $this-> ate o view (1 nivel)', classificarComIndirecao(indir, 'velha') === 'blade')
  t('NEG: json puro continua outro', classificarComIndirecao(indir, 'nada') === 'outro')
  t('NEG: ciclo nao trava nem mente', ['outro', 'indeterminado'].includes(classificarComIndirecao(indir, 'ciclo')))
  t('NEG: metodo inexistente = indeterminado', classificarComIndirecao(indir, 'naoExiste') === 'indeterminado')

  // ── dedup de rotaFiles (bug do case-insensitive, 2026-08-08) ──────────────
  // Invariante que vale nos DOIS sistemas de arquivos: nenhum arquivo real pode
  // aparecer 2× na lista. Antes do fix eram 60 entradas para 33 arquivos no
  // Windows (27 duplicados), inflando o censo em +45% e cegando a catraca no CI.
  {
    const fl = rotaFiles()
    const canon = fl.map((p) => {
      let r
      try { r = fs.realpathSync.native(p) } catch { r = p }
      return process.platform === 'win32' ? r.toLowerCase() : r
    })
    t(`BITE: rotaFiles sem duplicata (${fl.length} entradas / ${new Set(canon).size} reais)`,
      new Set(canon).size === fl.length)
    t('NEG: rotaFiles nao ficou vazio ao dedupar', fl.length > 0)
  }

  // ── catraca (peça 3) ──────────────────────────────────────────────────────
  const base = { total_blade: 100, por_escopo: { core: { blade: 60 }, Crm: { blade: 40 } } }
  t('BITE: escopo que sobe reprova a catraca',
    ratchet({ total_blade: 102, por_escopo: { core: { blade: 60 }, Crm: { blade: 42 } } }, base).ok === false)
  t('BITE: escopo NOVO (ausente do baseline) reprova',
    ratchet({ total_blade: 103, por_escopo: { core: { blade: 60 }, Crm: { blade: 40 }, Novo: { blade: 3 } } }, base).ok === false)
  t('NEG: igual ao baseline passa',
    ratchet({ total_blade: 100, por_escopo: { core: { blade: 60 }, Crm: { blade: 40 } } }, base).ok === true)
  t('NEG: descer passa (a catraca so impede SUBIR)',
    ratchet({ total_blade: 90, por_escopo: { core: { blade: 55 }, Crm: { blade: 35 } } }, base).ok === true)
  t('NEG: escopo que SOME do censo nao reprova',
    ratchet({ total_blade: 60, por_escopo: { core: { blade: 60 } } }, base).ok === true)
  // o que a catraca NÃO faz: julgar tempo. Estagnação é do sentinela (PHP, cron).
  t('NEG: catraca ignora tempo (baseline antigo com numeros iguais passa)',
    ratchet({ total_blade: 100, por_escopo: { core: { blade: 60 }, Crm: { blade: 40 } } },
      { ...base, gerado_em: '2020-01-01' }).ok === true)

  console.log(`\n  ${ok} passou · ${fail} falhou`)
  process.exit(fail === 0 ? 0 : 1)
}

// ── main ────────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2)
const optVal = (nome) => {
  const p = argv.find((a) => a.startsWith(`${nome}=`))
  return p ? p.slice(nome.length + 1) : null
}

if (argv.includes('--selftest')) selftest()
else if (argv.includes('--ratchet')) {
  const bPath = optVal('--baseline') || path.join(ROOT, 'governance', 'blade-migration-baseline.json')
  if (!fs.existsSync(bPath)) {
    console.error(`baseline não encontrado: ${bPath}`)
    process.exit(2)
  }
  const baseline = JSON.parse(fs.readFileSync(bPath, 'utf8'))
  const inPath = optVal('--input')
  let atual
  if (inPath) {
    atual = JSON.parse(fs.readFileSync(inPath, 'utf8'))
  } else {
    const ag = agregar(censo())
    const porEscopo = {}
    let t = 0
    for (const [esc, a] of [...ag.entries()].sort()) {
      if (a.blade === 0 && a.hibrido === 0) continue
      porEscopo[esc] = { blade: a.blade, hibrido: a.hibrido }
      t += a.blade
    }
    atual = { total_blade: t, por_escopo: porEscopo }
  }

  const r = ratchet(atual, baseline)
  if (r.ok) {
    const nota = r.delta < 0 ? ` (−${-r.delta} desde o baseline — regrave quando quiser)` : ''
    console.log(`✅ catraca Blade→React OK — nenhum escopo subiu. ${r.totalAtual} endpoints em Blade${nota}`)
    process.exit(0)
  }
  console.error('⛔ CATRACA Blade→React: rota Blade NOVA (a migração só pode descer)\n')
  for (const g of r.regressoes) console.error(`   ${g.escopo.padEnd(20)} ${g.de} → ${g.para}   (+${g.delta})`)
  console.error(`\n   total: ${r.totalBase} → ${r.totalAtual}`)
  console.error('\n   Se a subida foi CONSCIENTE, regrave o baseline no MESMO PR, com o motivo:')
  console.error('     node scripts/governance/blade-migration-census.mjs --resumo-json  (e atualize governance/blade-migration-baseline.json)')
  console.error('   ADR 0277 §1: enquanto os dois caminhos coexistem, a função NÃO conta como migrada.')
  process.exit(1)
} else {
  const linhas = censo()
  if (argv.includes('--resumo-json')) {
    // Mesmo shape do governance/blade-migration-baseline.json — consumido pelo
    // sentinela (BladeMigrationSentinelCommand) e usado pra regravar o baseline.
    const ag = agregar(linhas)
    const porEscopo = {}
    let totalBlade = 0
    for (const [esc, a] of [...ag.entries()].sort()) {
      if (a.blade === 0 && a.hibrido === 0) continue
      porEscopo[esc] = { blade: a.blade, hibrido: a.hibrido }
      totalBlade += a.blade
    }
    console.log(JSON.stringify({ total_blade: totalBlade, por_escopo: porEscopo }, null, 2))
  } else if (argv.includes('--json')) {
    console.log(JSON.stringify({ total: linhas.length, linhas }, null, 2))
  } else {
    const ag = agregar(linhas)
    const tot = { blade: 0, react: 0, hibrido: 0, outro: 0, indeterminado: 0, total: 0 }
    console.log('\n=== Censo de migração Blade → React (ADR 0277 · contrato de completude) ===\n')
    console.log('  escopo                blade  react  hibr  outro  indet  total')
    const ord = [...ag.entries()].sort((a, b) => b[1].blade - a[1].blade)
    for (const [esc, a] of ord) {
      for (const k of Object.keys(tot)) tot[k] += a[k]
      if (a.blade === 0 && a.hibrido === 0) continue
      console.log(
        `  ${esc.padEnd(20)} ${String(a.blade).padStart(5)} ${String(a.react).padStart(6)} ${String(a.hibrido).padStart(5)} ${String(a.outro).padStart(6)} ${String(a.indeterminado).padStart(6)} ${String(a.total).padStart(6)}`,
      )
    }
    console.log(`  ${'TOTAL'.padEnd(20)} ${String(tot.blade).padStart(5)} ${String(tot.react).padStart(6)} ${String(tot.hibrido).padStart(5)} ${String(tot.outro).padStart(6)} ${String(tot.indeterminado).padStart(6)} ${String(tot.total).padStart(6)}`)
    console.log(`\n  Endpoints que ainda servem Blade: ${tot.blade}  (+${tot.hibrido} híbridos)`)
    console.log(`  Indeterminados (o script não afirma): ${tot.indeterminado}\n`)
  }
}
