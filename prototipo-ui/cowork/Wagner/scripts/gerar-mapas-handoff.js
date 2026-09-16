/* ═══════════════════════════════════════════════════════════════════
   GERADOR DE MAPAS DE HANDOFF — máquina anti-apodrecimento
   Reconstrói MAPA_TELAS.md + MAPA_TOKENS.md a partir da FONTE VIVA
   (app.jsx router · lista de arquivos · CSS · DS · ds-v6). NÃO editar
   os .md à mão — rodar isto (L-43: derivado = gerado, nunca cópia).

   Como rodar (run_script):
     const gen = eval(await readFile('scripts/gerar-mapas-handoff.js'));
     const r = await gen({ readFile, ls });
     await saveFile('MAPA_TELAS.md', r.telasMd);
     await saveFile('MAPA_TOKENS.md', r.tokensMd);
     log(r.frescor);
   ═══════════════════════════════════════════════════════════════════ */
async function gerarMapasHandoff({ readFile, ls }) {
  const hoje = new Date().toISOString().slice(0, 10);
  const B = String.fromCharCode(96); // backtick, pra não brigar com template

  // ── índice componente→arquivo (baked · atualizar se der ⚠ no frescor) ──
  const LOOKUP = {
    JanaCockpit: 'chat-jana.jsx', TasksPage: 'tasks.jsx', PerfilPage: 'perfil-page.jsx',
    UsuariosPage: 'usuarios-page.jsx', OsListPage: 'os-page.jsx', CliListPage: 'clientes-page.jsx',
    OrcListPage: 'orc-page.jsx', ProdListPage: 'produtos-page.jsx', VendasModule: 'vendas-page.jsx',
    ProducaoPage: 'producao-page.jsx', FinanceiroPage: 'financeiro-page.jsx', BoletosPage: 'boletos-page.jsx',
    CobrancaPage: 'pg-shell-adapters.jsx', PaymentGatewaysPage: 'pg-shell-adapters.jsx',
    SellsCobrancaPreviewPage: 'pg-shell-adapters.jsx', ComprasPage: 'compras-page.jsx',
    OficinaPage: 'oficina-page.jsx', OficinaOSPage: 'oficina-os-page.jsx', CrmPage: 'crm-page.jsx',
    CrmFicha: 'crm-ficha.jsx', InboxPage: 'inbox-page.jsx', EquipePage: 'equipe-page.jsx',
    KBPage: 'kb-page.jsx', ForjaPage: 'forja-page.jsx', CobrancaRecorrentePage: 'cobranca-recorrente-page.jsx'
  };

  const frescor = [];
  const rootFiles = new Set(await ls(''));

  // ── 1. ROTAS: parse do roteador do app.jsx ──
  const appjsx = await readFile('app.jsx');
  const rx = /(?:else\s+)?if\s*\(([^{)]*?route === [^{)]*?)\)\s*content\s*=\s*<(?:window\.)?(\w+)/g;
  const rotas = []; let m;
  while ((m = rx.exec(appjsx))) {
    const cond = m[1], comp = m[2];
    const rs = [...cond.matchAll(/route === "([^"]+)"/g)].map(x => x[1]);
    if (rs.length) rotas.push({ routes: rs, comp });
  }
  // agrupa por componente
  const porComp = {};
  for (const r of rotas) { (porComp[r.comp] ||= []).push(...r.routes); }

  const telas = [
    '# MAPA DE TELAS — protótipo Cowork → repo (handoff [CL])',
    '',
    '> ⚙️ **GERADO por `scripts/gerar-mapas-handoff.js` — NÃO editar à mão.** Última geração: ' + hoje + '.',
    '> Fonte viva: roteador do `app.jsx` + índice componente→arquivo. Rode o gerador quando rota mudar (L-43).',
    '>',
    '> ⚠️ Coluna "destino repo" = CONVENÇÃO (`resources/js/Pages/<Mód>/<Tela>.tsx`), NÃO fato do git — [CL] confirma (L-42).',
    '> Nota/estado canônico de cada tela = `STATUS.md → Quadro de telas` (fonte única).',
    '',
    '| Rota(s) `route` | Componente | Arquivo-fonte | Destino repo (convenção) |',
    '|---|---|---|---|'
  ];
  const comps = Object.keys(porComp).sort();
  for (const comp of comps) {
    const file = LOOKUP[comp] || '⚠ não no índice';
    if (!LOOKUP[comp]) frescor.push('⚠ componente sem arquivo no índice LOOKUP: ' + comp + ' (atualizar o gerador)');
    else if (!rootFiles.has(file)) frescor.push('⚠ arquivo do índice não existe na raiz: ' + file + ' (' + comp + ')');
    const rs = [...new Set(porComp[comp])].map(x => B + x + B).join(' · ');
    telas.push('| ' + rs + ' | ' + B + comp + B + ' | ' + B + file + B + ' | `Pages/…` |');
  }
  telas.push('', '**Regra de ouro:** portar tela = conformar ao DS (ver `MAPA_COMPONENTES.md` + `MAPA_TOKENS.md`), NUNCA copiar o CSS bespoke do protótipo. O protótipo prova a INTENÇÃO; o repo materializa com os componentes canônicos.');
  const telasMd = telas.join('\n') + '\n';

  // ── 2. TOKENS: análise usado × DS × ds-v6 (só .css, rápido) ──
  const cssF = (await ls('')).filter(f => /\.css$/.test(f) && !/^_/.test(f));
  const uses = s => { const set = new Set(); const re = /var\(\s*(--[\w-]+)/g; let x; while ((x = re.exec(s))) set.add(x[1]); return set; };
  const decls = s => new Set([...s.matchAll(/(--[\w-]+)\s*:/g)].map(x => x[1]));
  const USED = new Set();
  for (const f of cssF) { for (const t of uses(await readFile(f))) USED.add(t); }
  const dsPath = '_ds/office-impresso-design-system-019dd02f-d2d0-7ba6-a57f-24b3ddd073ac/colors_and_type.css';
  const domPath = '_ds/office-impresso-design-system-019dd02f-d2d0-7ba6-a57f-24b3ddd073ac/cockpit_domains.css';
  const DS = new Set([...decls(await readFile(dsPath)), ...decls(await readFile(domPath).catch(() => ''))]);
  const ADAPTER = decls(await readFile('styles.css')); // ds-v6 foi DOBRADO aqui em 2026-07-10 (arquivo ds-v6 deletado)
  const fromDS = [...USED].filter(t => DS.has(t)).sort();
  const local = [...ADAPTER].filter(t => !DS.has(t) && USED.has(t)).sort();
  const missing = [...USED].filter(t => !DS.has(t) && !ADAPTER.has(t)).sort(); // maioria = locais de página

  frescor.push('tokens: usados=' + USED.size + ' · DS vivo (colors+domains) fornece=' + fromDS.length + ' · adapter styles.css fornece=' + local.length);

  // tabela de apelidos semânticos (curada · estável · muda só se o DS renomear)
  const apelidos = [
    '## Apelidos (nome no protótipo → token do DS)',
    '_Curado — vem do adaptador dobrado no `styles.css` (ex-ds-v6). Muda só se o DS renomear._',
    '',
    '| Protótipo | DS |',
    '|---|---|',
    '| `--sunken` | `--bg-2` |',
    '| `--raised` | `--surface` |',
    '| `--hairline` | `--border-2` |',
    '| `--text-2` | `--text-dim` |',
    '| `--text-3` | `--text-mute` |',
    '| `--accent-hi` | `--accent-2` |',
    '| `--r-1` / `--r-2` / `--r-3` | `--radius-sm` / `--radius` / `--radius-lg` (6/8/12) |',
    '| `--info` / `--info-soft` | `--accent` / `--accent-soft` |',
    '| `--bg-1`·`--panel`·`--ink-1`·`--gh`·`--primary-page` | `--bg`·`--surface`·`--text`·`--hairline`·`--accent` |'
  ];

  const tokens = [
    '# MAPA DE TOKENS — protótipo → DS',
    '',
    '> ⚙️ **GERADO por `scripts/gerar-mapas-handoff.js` — a seção INVENTÁRIO é da máquina.** Última geração: ' + hoje + '.',
    '> Protótipo LINKADO ao DS vivo (`<html class="cockpit">` + `_ds/…/colors_and_type.css` + `cockpit_domains.css`). Adapter dobrado no `styles.css` (ds-v6 deletado 2026-07-10).',
    '> ⚠️ Domínio (origins/sla/canal/kind/kpi): confirmar contra o SSOT `semantic.tokens.json` — NUNCA afirmar ausência do git daqui (L-42).',
    '',
    '## Inventário (máquina · ' + hoje + ')',
    '- Tokens usados no CSS: **' + USED.size + '** · DS vivo (colors+domains) fornece: **' + fromDS.length + '** · adapter local (`styles.css`) fornece: **' + local.length + '**',
    '- 🟢 vêm do DS vivo (canon): **' + fromDS.length + '**',
    '- 🟡 vêm do adapter local `styles.css` (aliases/escalares que o DS não expõe): **' + local.length + '** — ' + local.map(t => B + t + B).join(' '),
    '- (Nota: ~' + missing.length + ' "sem def global" = tokens LOCAIS de página `--cmp-*`/`--vd-*`/`--omd-*`, definidos no CSS da própria tela — não é quebra.)',
    '',
    ...apelidos,
    '',
    '**Regra:** no `.tsx` do repo, mirar o token do DS, nunca o nome do protótipo.'
  ];
  const tokensMd = tokens.join('\n') + '\n';

  return { telasMd, tokensMd, frescor: 'FRESCOR ' + hoje + ':\n- ' + frescor.join('\n- ') };
}
gerarMapasHandoff
