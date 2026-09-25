/* conferir-export.cowork.js — o mesmo porteiro do `conferir-export.mjs`, na forma que
   RODA AQUI no Cowork (não há node no ambiente; há o sandbox do run_script).

   Como chamar — um run_script por etapa (são quatro):

     const src = await readFile('conferir-export.cowork.js');
     await new Function('ctx', src + '\nreturn conferir(ctx);')({ readFile, readFileBinary, ls, log, etapa: 'ds' });

   Trocando 'ds' por 'raiz-1'…'raiz-4', 'sub-1a', 'sub-1b', 'sub-2', 'sub-3' e 'crlf'. Dez etapas
   porque o sandbox corta em 30s e lê ~60 arquivos por rodada:
     ds        → testes 1, 2, 3, 5, 6 (espelho, bundle, base, fontes)
     raiz-1..4 → testes 4 e 7 nos quatro quartos da raiz (~70 arquivos por etapa)
     sub-1a/1b → testes 4, 7, 8 e 11 em erp-shell-v2, metade por etapa
     sub-2, 3  → os mesmos testes em handoffs+importados · telas
     (24/09/2026: o antigo 'sub-1' lia erp-shell-v2 inteiro, 56 arquivos e 1,65 M caracteres,
      e estourou os 30 s duas vezes seguidas. Nenhum teste mudou: só a divisão da pasta e um
      cache de leitura, porque o teste 11 relia os mesmos .jsx já lidos pelos testes 4 e 7.)
     crlf      → teste 10 por byte, numa amostra de 10 arquivos

   Testes 9 (sha1 de tudo) e o teste 8 completo ficam só no `conferir-export.mjs`, que roda
   no Code. Aqui eles são custosos demais para o orçamento de tempo do sandbox — está
   declarado, não esquecido.

   Arquivos com acento no nome (4 em erp-shell-v2/) são RECUSADOS pelo leitor do sandbox.
   Eles aparecem na saída como "não lidos" — não são "sem defeito", são não medidos. O
   `conferir-export.mjs` no Code cobre esses. */

async function conferir(ctx) {
  const { readFile, readFileBinary, ls, log } = ctx;
  const etapa = ctx.etapa || 'ds';
  const ehDs = etapa === 'ds', ehSub = /^sub-(1a|1b|2|3)$/.test(etapa);
  const R = [];
  const ok = (n, t, d = '') => R.push(['  ok  ', n, t, d]);
  const falha = (n, t, d) => R.push([' FALHA', n, t, d]);
  const info = (n, t, d) => R.push([' info ', n, t, d]);
  const naoLidos = [];

  const CODIGO = /\.(html|jsx|js|mjs|css)$/i;
  // O próprio porteiro cita os nomes das pastas apagadas (teste 7) e o namespace (teste 4).
  // Varrer a si mesmo faria o teste reprovar sempre.
  const EU = /^conferir-export\.(mjs|cowork\.js)$/;
  const cache = new Map();
  const ler = async (p) => { if (cache.has(p)) return cache.get(p);
    let t = null; try { t = await readFile(p); } catch (e) { naoLidos.push(p); }
    cache.set(p, t); return t; };
  const tam = async (p) => { try { return (await (await readFileBinary(p)).arrayBuffer()).byteLength; } catch (e) { naoLidos.push(p); return null; } };

  // ── espelho
  let espelho = null;
  const pastasDs = (await ls('_ds')).filter((n) => !/\./.test(n));
  if (ehDs) {
    if (pastasDs.length === 1) { espelho = '_ds/' + pastasDs[0] + '/'; ok(1, 'espelho único em _ds/', pastasDs[0]); }
    else falha(1, 'espelho único em _ds/', pastasDs.length + ' pastas: ' + pastasDs.join(', '));
  } else { espelho = '_ds/' + pastasDs[0] + '/'; }

  let NS = null;
  const src = espelho ? await ler(espelho + '_ds_bundle.js') : null;
  if (src) {
    const cab = src.match(/@ds-bundle:\s*(\{[\s\S]*?\})\s*\*\//);
    try { const m = JSON.parse(cab[1]); NS = m.namespace;
      if (ehDs) ok(2, 'namespace lido do cabeçalho @ds-bundle', NS + ' · ' + (m.components || []).length + ' componentes');
    } catch (e) { if (ehDs) falha(2, 'namespace lido do cabeçalho @ds-bundle', 'cabeçalho ausente ou ilegível'); }
    if (ehDs) {
      const al = [...src.matchAll(/^\s*window\.([A-Za-z0-9_$]+)\s*=\s*window\.([A-Za-z0-9_$]+)\s*;/gm)];
      if (al.length) falha(3, 'bundle sem alias acrescentado', al.map((a) => a[1] + ' = ' + a[2]).join(' · '));
      else if (!/\}\)\(\);\s*$/.test(src)) falha(3, 'bundle sem alias acrescentado', 'não termina em })();');
      else ok(3, 'bundle sem alias acrescentado', 'termina em })();');
    }
  } else if (ehDs) falha(2, 'bundle do DS presente', 'sem _ds_bundle.js no espelho');

  if (ehDs && espelho) {
    // ── 5. linha de base
    // A nota de tamanhos mora na RAIZ, não em _ds/: _ds/ é cópia do design system, e nota
    // do projeto dentro dela se perde na próxima regeneração.
    const b = await ler('_export-baseline.json');
    if (!b) falha(5, 'espelho bate com a linha de base', '_export-baseline.json não existe na raiz — rode o .mjs com --baseline logo após regenerar o espelho');
    else {
      const base = JSON.parse(b), difs = [];
      for (const [a, n] of Object.entries(base.arquivos || {})) {
        const real = await tam(espelho + a);
        if (real === null) difs.push(a + ': sumiu');
        else if (real !== n) difs.push(a + ': ' + real + ' B ≠ ' + n + ' B');
      }
      if (base.namespace && NS && base.namespace !== NS) difs.push('namespace: ' + NS + ' ≠ ' + base.namespace);
      difs.length ? falha(5, 'espelho bate com a linha de base', difs.join(' · '))
                  : ok(5, 'espelho bate com a linha de base', Object.keys(base.arquivos).length + ' arquivos');
    }
    // ── 6. fontes
    const fs = (await ls(espelho + 'assets/fonts')).filter((n) => /ibm-plex-sans-\d+\.woff2$/.test(n));
    const porTam = new Map();
    for (const n of fs) { const s = await tam(espelho + 'assets/fonts/' + n); porTam.set(s, [...(porTam.get(s) || []), n]); }
    const iguais = [...porTam.values()].filter((v) => v.length > 1);
    const leves = [...porTam.entries()].filter(([s]) => s < 55000).flatMap(([, v]) => v);
    if (iguais.length) falha(6, 'fontes com pesos distintos', iguais.map((v) => v.join(' = ')).join(' · ') + ' — é a 400 copiada');
    else if (leves.length) falha(6, 'fontes com pesos distintos', leves.join(', ') + ' abaixo de 55 KB');
    else ok(6, 'fontes com pesos distintos', [...porTam.entries()].map(([s, v]) => v[0].match(/\d+/)[0] + ':' + (s / 1000).toFixed(1) + 'k').join(' '));
  }

  // ── etapa 'crlf': só o teste 10, por byte, numa amostra
  if (etapa === 'crlf') {
    const am = ['oimpresso.com.html', 'Consulta de Produtos.dc.html', 'manufacturing-page.jsx', 'CLAUDE.md',
      'erp-shell-v2/app.jsx', 'erp-shell-v2/styles.css', 'erp-shell-v2/Compras.html',
      'importado_prototipo_ui/HANDOFF.md', 'importado_telas/vendas/vendas-page.jsx',
      'importado_telas/ds-galerias/ds-v6-showcase.html'];
    const cr = [];
    for (const p of am) { let u; try { u = new Uint8Array(await (await readFileBinary(p)).arrayBuffer()); } catch (e) { naoLidos.push(p); continue; }
      for (let i = 0; i < u.length - 1; i++) if (u[i] === 13 && u[i + 1] === 10) { cr.push(p); break; } }
    info(10, 'quebra de linha LF (amostra de ' + am.length + ')', cr.length ? cr.length + ' em CRLF: ' + cr.join(', ') : 'todos em LF');
    log('\nconferir-export · etapa crlf\n');
    for (const [m, n, ti, d] of R) log(m + ' ' + n + '. ' + ti + '  ' + d);
    if (naoLidos.length) log('\nNÃO LIDOS: ' + naoLidos.join(', '));
    return 0;
  }

  // ── 4 e 7. varredura de código
  const DIRS = {
    'sub-2': ['handoff_fabricacao', 'handoff_fabricacao/contexto', 'handoff_fabricacao/design',
      'handoff_produtos_consulta', 'handoff_produtos_consulta/contexto', 'handoff_produtos_consulta/design',
      'importado_ds_git', 'importado_ds_git/css', 'importado_prototipo_ui'],
    'sub-3': ['importado_telas/clientes', 'importado_telas/compras-grade-matrix', 'importado_telas/ds-galerias',
      'importado_telas/mockups', 'importado_telas/oficina', 'importado_telas/pageheader-canon-v3',
      'importado_telas/sidebar', 'importado_telas/vendas'],
  };
  let alvos = [];
  if (ehDs) { /* etapa ds não varre código */ }
  else if (/^raiz-[1-4]$/.test(etapa)) {
    const raiz = (await ls('')).filter((n) => CODIGO.test(n) && !EU.test(n));
    const q = Math.ceil(raiz.length / 4), i = Number(etapa.slice(-1)) - 1;
    alvos = raiz.slice(i * q, (i + 1) * q);
  }
  else if (/^sub-1[ab]$/.test(etapa)) {
    const todos = (await ls('erp-shell-v2')).filter((n) => CODIGO.test(n)).sort().map((n) => 'erp-shell-v2/' + n);
    const meio = Math.ceil(todos.length / 2);
    alvos = etapa === 'sub-1a' ? todos.slice(0, meio) : todos.slice(meio);
  }
  else for (const d of (DIRS[etapa] || [])) { let it; try { it = await ls(d); } catch (e) { continue; } for (const n of it) if (CODIGO.test(n)) alvos.push(d + '/' + n); }

  const reNs = /window\.([A-Za-z0-9_$]*(?:DesignSystem|PontoWR2)[A-Za-z0-9_$]*)/g;
  const reTag = /component-from-global-scope=["']([A-Za-z0-9_$]*(?:DesignSystem|PontoWR2)[A-Za-z0-9_$]*)\./g;
  const MORTOS = ['019dd02f', 'd7f88676', 'office-impresso-atual'];
  const fora = new Map(), mortos = [];
  const htmlRefs = [];
  for (const p of alvos) {
    const t = await ler(p);
    if (t === null) continue;
    if (NS) for (const m of [...t.matchAll(reNs), ...t.matchAll(reTag)]) {
      if (m[1] === NS) continue;
      if (!fora.has(m[1])) fora.set(m[1], new Set());
      fora.get(m[1]).add(p);
    }
    for (const d of MORTOS) if (t.includes(d)) mortos.push(p + ' → ' + d);
    if (ehSub && /\.html$/i.test(p)) for (const m of t.matchAll(/(?:src|href)=["']([^"'#]+)["']/g))
      if (!/^(https?:|data:|mailto:|\/\/)/.test(m[1])) htmlRefs.push([p, m[1]]);
  }
  const escopo = etapa;
  if (ehDs) { /* sem varredura nesta etapa */ }
  else if (fora.size === 0) ok(4, 'código lê só ' + NS + ' (' + escopo + ')', alvos.length + ' arquivos');
  else falha(4, 'código lê só ' + NS + ' (' + escopo + ')',
    [...fora].map(([n, s]) => n + ' em ' + s.size + ': ' + [...s].slice(0, 4).join(', ')).join(' | '));
  if (!ehDs) mortos.length ? falha(7, 'sem caminho para espelho apagado (' + escopo + ')', mortos.join(' · '))
                : ok(7, 'sem caminho para espelho apagado (' + escopo + ')', 'nenhum');

  // ── 11. página que monta tela do DS tem de carregar o bundle (senão abre EM BRANCO)
  if (!ehDs && NS) {
    const idx11 = new Map();
    const existe11 = async (cam) => { const i = cam.lastIndexOf('/');
      const d = i < 0 ? '' : cam.slice(0, i), n = cam.slice(i + 1);
      if (!idx11.has(d)) { try { idx11.set(d, new Set(await ls(d))); } catch (e) { idx11.set(d, new Set()); } }
      return idx11.get(d).has(n); };
    const resolver = (base, u) => { const dir = base.split('/').slice(0, -1).join('/');
      if (u.startsWith('/')) return u.slice(1);
      const pil = []; for (const s of (dir + '/' + u.split('?')[0]).split('/')) {
        if (s === '.' || s === '') continue; if (s === '..') pil.pop(); else pil.push(s); } return pil.join('/'); };
    const faltando = [];
    for (const p of alvos.filter((x) => /\.html$/i.test(x))) {
      const t = await ler(p); if (t === null) continue;
      const carregados = [...t.matchAll(/<script[^>]+src=["']([^"']+)["']/g)].map((x) => x[1]);
      if (carregados.some((u) => /_ds_bundle\.js/.test(u))) continue;
      const cons = [];
      for (const u of carregados) {
        if (/^(https?:|data:|\/\/)/.test(u) || !/\.jsx?$/.test(u)) continue;
        const alvo = resolver(p, u);
        if (!(await existe11(alvo))) continue;
        const src2 = await ler(alvo); if (src2 && src2.includes(NS)) cons.push(u);
      }
      if (cons.length) faltando.push(p + ' carrega ' + cons.length + ' arquivo(s) que leem o DS e nenhum _ds_bundle.js');
    }
    faltando.length ? falha(11, 'página que usa o DS carrega o bundle (' + etapa + ')', faltando.join(' | ') + ' — abre em branco sem erro no console')
                    : ok(11, 'página que usa o DS carrega o bundle (' + etapa + ')', 'nenhuma página órfã');
  }

  if (ehSub) {
    // ── 8. referências locais das HTML das subpastas (as da raiz ficam com o .mjs)
    // Existência por índice de diretório (um `ls` por pasta), não por leitura de arquivo:
    // ler cada referência custa centenas de idas ao disco e estoura o tempo do sandbox.
    const indice = new Map();
    const existe = async (caminho) => {
      const i = caminho.lastIndexOf('/');
      const dir = i < 0 ? '' : caminho.slice(0, i), nome = caminho.slice(i + 1);
      if (!indice.has(dir)) { try { indice.set(dir, new Set(await ls(dir))); } catch (e) { indice.set(dir, new Set()); } }
      return indice.get(dir).has(nome);
    };
    const quebr = [], vistos = new Set();
    for (const [p, u] of htmlRefs) {
      const dir = p.split('/').slice(0, -1).join('/');
      let alvo = u.split('?')[0];
      if (!alvo.startsWith('/')) { const partes = (dir + '/' + alvo).split('/'); const pilha = [];
        for (const s of partes) { if (s === '.' || s === '') continue; if (s === '..') pilha.pop(); else pilha.push(s); } alvo = pilha.join('/'); }
      if (vistos.has(alvo)) continue; vistos.add(alvo);
      if (!(await existe(alvo))) quebr.push(p + ' → ' + u);
    }
    quebr.length ? falha(8, 'referências locais resolvem (' + etapa + ')', quebr.length + ': ' + quebr.slice(0, 6).join(' · '))
                 : ok(8, 'referências locais resolvem (' + etapa + ')', vistos.size + ' alvos distintos');
  }

  const larg = Math.max(...R.map((r) => r[2].length));
  log('\nconferir-export · etapa ' + etapa + ' · ' + alvos.length + ' arquivos de código\n');
  for (const [marca, n, t, d] of R.sort((a, b) => a[1] - b[1])) log(marca + ' ' + String(n).padStart(2) + '. ' + t.padEnd(larg) + '  ' + d);
  if (naoLidos.length) log('\nNÃO LIDOS (o leitor do sandbox recusa o caminho — não é "sem defeito"): ' + naoLidos.join(', '));
  const nf = R.filter((r) => r[0] === ' FALHA').length;
  log(nf ? '\n' + nf + ' teste(s) reprovado(s) — ver pre-export.md, seção de mesmo número.\n' : '\nEtapa ' + etapa + ' liberada.\n');
  return nf;
}
