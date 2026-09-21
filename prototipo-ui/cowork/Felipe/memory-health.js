/**
 * memory-health.js — check de higiene da memória do Cowork (camada-5, cobrança por máquina)
 *
 * POR QUÊ: o conflito verde×roxo só apareceu porque [CC] leu 4 arquivos à mão e [W] percebeu.
 * Isso é S4 (cobrança manual). Este probe mecaniza os IT1–IT7 do PROCESSO_MEMORIA_CC §15,
 * que até hoje só existiam escritos. Rodar no FIM de cada chat e passar pro verificador.
 *
 * NÃO substitui o lado git: ADR lifecycle (status/superseded_by) + jana:health-check + retrieval
 * filtrado vivem no repo (Fases 2–3 da ponte ADR-LIFECYCLE-JANA-RETRIEVAL). Aqui é só o Cowork.
 *
 * Princípio L-31: todo check é validado dos DOIS lados — fica 🔴 num bug injetado E 🟢 no acervo
 * limpo (a função runNegativeControl() prova isso). Check que acusa inocente é ruído e morre.
 *
 * Uso (via run_script com os helpers readFile/ls):
 *   const checks = buildChecks({ readFile, ls });
 *   await checks.runAll();           // relatório completo
 *   await checks.runNegativeControl(); // prova de sensibilidade+especificidade
 */

const SPINE = [
  'STATUS.md',
  'MEMORY_INDEX.md',
  'PROCESSO_MEMORIA_CC.md',
  'memory/LICOES_CC.md',
  'CARTA_DESIGN_CC.md',
];

// Baseline só-desce do CHECK 8 (F2): dívida LEGADA tolerada pra não virar parede 🔴 no
// 1º run. Órfão/ref-morta NOVO (fora destas listas) TRAVA. A lista só pode ENCOLHER
// (mover processado p/ _processados/ · enfileirar o que é real · limpar citação morta).
// Snapshot 2026-06-16 (rodado de verdade via memory-health). Reconciliar vs CODE_NOTES@main.
const PROMPT_ORFAO_BASELINE = [
  'PROMPT_PARA_CODE_CENSO-ADOCAO-DS.md',
  'PROMPT_PARA_CODE_CICLO-DIARIO-GOVERNANCA.md', 'PROMPT_PARA_CODE_ERRADICA-LOCACAO-ACTIONS.md',
  'PROMPT_PARA_CODE_FA5-DRAWER-975.md', 'PROMPT_PARA_CODE_FINANCEIRO-ADVERSARIO-WAVE1.md',
  'PROMPT_PARA_CODE_FINANCEIRO-CONSERTAR-ELO-BUNDLE.md', 'PROMPT_PARA_CODE_FINANCEIRO-ONDA-MESTRE.md',
  'PROMPT_PARA_CODE_FINANCEIRO-ONDA2-TRIBUNAL.md', 'PROMPT_PARA_CODE_FINANCEIRO-REVIEW-W-CONSOLIDADO.md',
  'PROMPT_PARA_CODE_FORJA-ABSORCAO-TEAMMCP.md', 'PROMPT_PARA_CODE_GOVERNANCA-FECHAR-G3-G7-F3.md',
  'PROMPT_PARA_CODE_ONDAS-FINANCEIRO-APLICAR.md', 'PROMPT_PARA_CODE_ONDAS-QUALIDADE-GOVERNANCA.md',
  'PROMPT_PARA_CODE_PACOTE-QUALIDADE-9-OS.md', 'PROMPT_PARA_CODE_PRIMITIVOS-LAYOUT-V2.md',
  'PROMPT_PARA_CODE_UC-GUARDS-PROTOCOL-FRESHNESS.md', 'PROMPT_PARA_CODE_W28-FIREBIRD-DOMINIO-MARTINHO.md',
];
// Citações na fila que apontam pra arquivo inexistente (processados não-limpos). Baseline.
const PROMPT_REFMORTA_BASELINE = [
  'PROMPT_PARA_CODE_US-FIN-029-3-LENTES.md', 'PROMPT_PARA_CODE_DS-LINT-TSX-COR-CRUA.md',
  'PROMPT_PARA_CODE_COMPRAS-HEX-PARA-TOKEN.md', 'PROMPT_PARA_CODE_OFICINA-CONVERGE-CACAMBA-REPARO.md',
  'PROMPT_PARA_CODE_ADR-LIFECYCLE-JANA-RETRIEVAL.md', 'PROMPT_PARA_CODE_CONFORMANCE-GATE.md',
  'PROMPT_PARA_CODE_DARK-BACKFILL-SWEEP.md', 'PROMPT_PARA_CODE_DS-V6-TOKEN-DELTA.md',
  'PROMPT_PARA_CODE_DS-V6.md', 'PROMPT_PARA_CODE_OFICINA-DARK-STAGE-DS.md',
  'PROMPT_PARA_CODE_DANI-AUTORIZA-VENDA-FATURAMENTO.md', 'PROMPT_PARA_CODE_MARTINHO-GRADE-DECOUPLE.md',
  'PROMPT_PARA_CODE_OFICINA-KANBAN-F3-PORT.md', 'PROMPT_PARA_CODE_LIGAR-FISCAL-REAL-MARTINHO.md',
  'PROMPT_PARA_CODE_JANA-ADVISOR-MODE.md', 'PROMPT_PARA_CODE_JANA-LICOES-REFLEXION.md',
  'PROMPT_PARA_CODE_FISCAL-STATUS-UNIFICADO.md', 'PROMPT_PARA_CODE_CRM-TRIO.md',
  'PROMPT_PARA_CODE_PR-B-guard-ds.md',
];

const COLOR = /verde|roxo|âmbar|ambar|azul|indigo|navy|magenta/i;
const norm  = c => c.toLowerCase().replace('â', 'a');

function buildChecks({ readFile, ls }) {
  // --- CHECK 1 · FRESCOR (ratchet): índice não pode estar atrás da sessão mais nova ---
  async function freshness() {
    const sessions = (await ls('memory/sessions')).filter(f => /^\d{4}-\d{2}-\d{2}/.test(f));
    const dateOf = s => (s.match(/(\d{4}-\d{2}-\d{2})/) || [])[1];
    const newest = sessions.map(dateOf).sort().pop();
    const idx = await readFile('MEMORY_INDEX.md');
    const stamp = (idx.match(/[Úu]ltima att\.?:?\s*\*{0,2}(\d{4}-\d{2}-\d{2})/) || [])[1];
    const ok = stamp && stamp >= newest;
    return { name: 'frescor', ok, detail: `MEMORY_INDEX=${stamp} vs sessão-mais-nova=${newest}` + (ok ? '' : ' ⚠ STALE') };
  }

  // --- CHECK 2 · FONTE ÚNICA: identidade de tela não pode ter 2 cores no Quadro de telas ---
  function scanIdentities(statusTxt) {
    const start = statusTxt.indexOf('Quadro de telas');
    if (start < 0) return [];
    const end = statusTxt.indexOf('\n## ', start);
    const seg = statusTxt.slice(start, end > 0 ? end : start + 4000);
    const rows = seg.split('\n').filter(l => /^\|/.test(l) && !/Identidade|----/.test(l));
    const byScreen = {};
    for (const r of rows) {
      const cols = r.split('|').map(s => s.trim());
      const screen = (cols[1] || '').toLowerCase().split('/')[0].split('(')[0].trim();
      const m = (cols[2] || '').match(COLOR);
      if (!screen || !m) continue;
      (byScreen[screen] = byScreen[screen] || new Set()).add(norm(m[0]));
    }
    return Object.entries(byScreen).filter(([, s]) => s.size > 1).map(([s, set]) => `${s}={${[...set].join(',')}}`);
  }
  async function singleSource() {
    const conflicts = scanIdentities(await readFile('STATUS.md'));
    return { name: 'fonte-única (identidade)', ok: conflicts.length === 0, detail: conflicts.length ? conflicts.join(' · ') : 'sem conflito' };
  }

  // --- CHECK 3 · ESPINHA EXISTE (IT1) + sem ref morta na espinha (IT7) ---
  async function spineExists() {
    const missing = [];
    for (const f of SPINE) { try { await readFile(f); } catch { missing.push(f); } }
    return { name: 'espinha existe (IT1)', ok: missing.length === 0, detail: missing.length ? 'FALTANDO: ' + missing.join(', ') : `${SPINE.length} ok` };
  }

  // --- CHECK 5 · FRESCOR DO CENSO DE GATES (a tabela de gates do STATUS é CACHE de .github/workflows;
  //     se ficou pra trás da atividade recente, força re-derivação. Cobra L-33 por máquina, não memória) ---
  async function gateCensusFresh() {
    const status = await readFile('STATUS.md');
    const stamp = (status.match(/CENSO COMPLETO[\s\S]{0,200}?✓ lido @main\s*(\d{4}-\d{2}-\d{2})/) || [])[1];
    if (!stamp) return { name: 'frescor censo-de-gates (L-33)', ok: false, detail: '⚠ carimbo "✓ lido @main" do CENSO ausente — re-derivar de .github/workflows/*.yml' };
    // Compara com a sessão mais nova: se trabalhei recente mas não refresquei o censo há >14d, avisa.
    let newest = stamp;
    try {
      const sessions = (await ls('memory/sessions')).filter(f => /^\d{4}-\d{2}-\d{2}/.test(f));
      newest = sessions.map(s => (s.match(/(\d{4}-\d{2}-\d{2})/) || [])[1]).filter(Boolean).sort().pop() || stamp;
    } catch { /* sem sessões = só valida presença */ }
    const days = (a, b) => Math.round((Date.parse(a) - Date.parse(b)) / 864e5);
    const lag = days(newest, stamp);
    const stale = lag > 14;
    return { name: 'frescor censo-de-gates (L-33)', ok: true, advisory: stale,
      detail: stale ? `censo @${stamp} está ${lag}d atrás da sessão-mais-nova @${newest} — re-derivar de .github/workflows/` : `censo @${stamp} fresco (lag ${lag}d)` };
  }

  // --- CHECK 4 · ADR sem marcador de status (advisory; cobertura real = git/[CL]) ---
  async function adrStatus() {
    let files;
    try { files = (await ls('memory/decisions')).filter(f => /\.md$/.test(f) && f !== 'README.md'); }
    catch { return { name: 'ADR status (advisory)', ok: true, detail: 'sem decisions locais' }; }
    const semStatus = [];
    for (const f of files) {
      const t = await readFile('memory/decisions/' + f);
      if (!/status:\s*\w|Status:\s*\w|SUPERSEDED|PROPOSTA|RETIRADO/i.test(t)) semStatus.push(f);
    }
    return { name: 'ADR status (advisory · canon=git)', ok: true, advisory: semStatus.length > 0, detail: semStatus.length ? 'sem status: ' + semStatus.join(', ') : `${files.length} com status` };
  }

  // --- CHECK 6 · LIÇÃO → ASSERÇÃO (P4 · PROCESSO §8.2): erro novo na sessão mais recente TEM que apontar
  //     o check que passa a pegá-lo (G#/IT#/UC-) ou declarar não-mecanizável. Lição sem sensor = literatura. ---
  function lessonAssertionGap(txt) {
    var m = txt.match(/##\s*Erros[^\n]*\n([\s\S]*?)(?:\n##\s|$)/i);
    if (!m) return null;                                   // sem seção de erros = nada a cobrar
    var body = m[1].trim();
    if (!body || /^[-·\s]*nenhum/i.test(body)) return null; // "nenhum" = ok
    var ok = /\bG\d\b|\bIT\d\b|\bUC-\w|licao_sem_assercao|n[aã]o-mecaniz[aá]vel|qa-conformance|memory-health|coberto pel[ao]|inst[aâ]ncia de L-\d/i.test(body);
    return ok ? null : 'erro registrado sem asserção (§8.2): apontar G#/IT#/UC- novo-ou-existente, ou declarar "não-mecanizável: <motivo>"';
  }
  async function licaoSemAssercao() {
    var sessions;
    try { sessions = (await ls('memory/sessions')).filter(f => /^\d{4}-\d{2}-\d{2}.*\.md$/.test(f)).sort(); }
    catch (e) { return { name: 'lição→asserção (P4)', ok: true, detail: 'sem sessões' }; }
    var newest = sessions[sessions.length - 1];
    if (!newest) return { name: 'lição→asserção (P4)', ok: true, detail: 'sem sessões' };
    var gap = lessonAssertionGap(await readFile('memory/sessions/' + newest));
    return { name: 'lição→asserção (P4 · §8.2)', ok: !gap, detail: gap ? newest + ' — ' + gap : newest + ' ok' };
  }

  // --- CHECK 7 · ÁRVORE PROIBIDA (IT8 · lápide dedup 2026-06-10): duplicata estrutural não pode renascer ---
  async function arvoreProibida() {
    const bad = [];
    try { await ls('resources'); bad.push('resources/ (espelho de repo — Regra 6)'); } catch (e) {}
    try {
      const up = await ls('uploads');
      up.filter(f => /\(\d+\)\.[a-z]+$/i.test(f)).slice(0, 3).forEach(f => bad.push('uploads/' + f + ' (duplicata "(N)")'));
    } catch (e) {}
    try { await ls('_arquivo/legado/uploads'); bad.push('_arquivo/legado/uploads (cópia aninhada)'); } catch (e) {}
    try { await ls('_arquivo/legado/backups'); bad.push('_arquivo/legado/backups (snapshot integral)'); } catch (e) {}
    return { name: 'árvore proibida (IT8 · dedup 06-10)', ok: bad.length === 0, detail: bad.length ? 'RENASCEU: ' + bad.join(' · ') : 'sem duplicata estrutural' };
  }

  // --- CHECK 8 · PROMPT ÓRFÃO / REF MORTA (F2 · "O Adversário do Protocolo" 2026-06-16) ---
  //     O Code lê SÓ a fila (COWORK_NOTES → Pendentes via README). Logo:
  //     (a) todo prototipo-ui-patch/PROMPT_PARA_CODE_*.md na RAIZ tem que estar CITADO em
  //         COWORK_NOTES — senão a tarefa é invisível pro Code (bug 06-16, o que disparou isto);
  //     (b) todo PROMPT citado em COWORK_NOTES tem que EXISTIR (ref morta = handoff quebrado).
  //     Processado sai da raiz pra _processados/ (some dos dois lados, não flagra). Baseline
  //     só-desce p/ o legado (PROMPT_ORFAO_BASELINE) — só órfão NOVO trava, igual gate do repo.
  function promptProblems(files, notesTxt, baseline, refBaseline) {
    const roots = files.filter(f => /^PROMPT_PARA_CODE_.*\.md$/.test(f));
    const orfaos = roots.filter(f => !notesTxt.includes(f) && !(baseline || []).includes(f));
    const cited = [...new Set(notesTxt.match(/PROMPT_PARA_CODE_[\w-]+\.md/g) || [])];
    const mortas = cited.filter(c => !roots.includes(c) && !(refBaseline || []).includes(c));
    return { orfaos, mortas };
  }
  async function promptOrfao() {
    let files;
    try { files = await ls('prototipo-ui-patch'); }
    catch { return { name: 'prompt órfão (F2)', ok: true, detail: 'sem prototipo-ui-patch' }; }
    const notes = await readFile('COWORK_NOTES.md');
    const { orfaos, mortas } = promptProblems(files, notes, PROMPT_ORFAO_BASELINE, PROMPT_REFMORTA_BASELINE);
    const probs = [];
    if (mortas.length) probs.push('REF MORTA NOVA (citado na fila, sem arquivo): ' + mortas.join(', '));
    if (orfaos.length) probs.push('ÓRFÃO NOVO (na raiz, fora da fila → invisível pro Code): ' + orfaos.join(', '));
    const debt = PROMPT_ORFAO_BASELINE.length + PROMPT_REFMORTA_BASELINE.length;
    return { name: 'prompt órfão / ref morta (F2)', ok: probs.length === 0,
      detail: probs.length ? probs.join(' · ') : `sem órfão/ref-morta NOVO (dívida baseline: ${debt} — reconciliar vs CODE_NOTES@main)` };
  }

  async function runAll() {
    const results = [await freshness(), await singleSource(), await spineExists(), await adrStatus(), await gateCensusFresh(), await licaoSemAssercao(), await arvoreProibida(), await promptOrfao()];
    for (const r of results) console.log(`${r.ok ? (r.advisory ? '🟡' : '🟢') : '🔴'} ${r.name}: ${r.detail}`);
    const fail = results.filter(r => !r.ok).length;
    console.log(fail ? `BLOQUEIA: ${fail} 🔴` : 'memória sã ✓');
    return { results, fail };
  }

  // --- CONTROLE-NEGATIVO (L-31): prova sensibilidade (pega bug) + especificidade (limpo passa) ---
  async function runNegativeControl() {
    const clean = await readFile('STATUS.md');
    const espec = scanIdentities(clean).length === 0;                 // limpo → sem conflito
    const bug = clean.replace('| Vendas | **roxo 295**', '| Vendas | verde 155 |\n| Vendas | **roxo 295**');
    const sens = scanIdentities(bug).some(c => c.startsWith('vendas')); // bug → flagra
    console.log(`controle-negativo · sensibilidade(pega bug)=${sens ? '🔴 OK' : 'FALHOU'} · especificidade(limpo passa)=${espec ? '🟢 OK' : 'FALHOU'}`);
    // CHECK 5 controle-negativo: censo sem carimbo → flagra (sens); com carimbo → passa (espec)
    const censoStamp = /CENSO COMPLETO[\s\S]{0,200}?✓ lido @main\s*\d{4}-\d{2}-\d{2}/.test(clean);
    const censoSens = !/CENSO COMPLETO[\s\S]{0,200}?✓ lido @main\s*\d{4}-\d{2}-\d{2}/.test(clean.replace(/✓ lido @main\s*\d{4}-\d{2}-\d{2}/, 'xx'));
    console.log(`controle-negativo censo · sensibilidade(sem carimbo flagra)=${censoSens ? '🔴 OK' : 'FALHOU'} · especificidade(com carimbo passa)=${censoStamp ? '🟢 OK' : 'FALHOU'}`);
    // CHECK 6 controle-negativo: erro sem asserção → flagra; erro com G#/não-mecanizável → passa; "nenhum" → passa
    const l6sens = !!lessonAssertionGap('## Erros + correção\n- esqueci o accent-color de novo\n## Refs');
    const l6espec = !lessonAssertionGap('## Erros + correção\n- esqueci o accent-color → coberto pelo G2\n## Refs') && !lessonAssertionGap('## Erros + correção\nNenhum novo nesta sessão.\n## Refs');
    console.log(`controle-negativo lição→asserção · sensibilidade=${l6sens ? '🔴 OK' : 'FALHOU'} · especificidade=${l6espec ? '🟢 OK' : 'FALHOU'}`);
    // CHECK 8 controle-negativo: órfão (prompt na raiz fora da fila) flagra; tudo citado passa; ref morta flagra
    const _files = ['PROMPT_PARA_CODE_A.md', 'PROMPT_PARA_CODE_B.md'];
    const p8sens = promptProblems(_files, 'fila cita só PROMPT_PARA_CODE_A.md', []).orfaos.includes('PROMPT_PARA_CODE_B.md');
    const p8espec = promptProblems(_files, 'cita PROMPT_PARA_CODE_A.md e PROMPT_PARA_CODE_B.md', []).orfaos.length === 0;
    const p8morta = promptProblems(_files, 'cita PROMPT_PARA_CODE_A.md e PROMPT_PARA_CODE_C.md', []).mortas.includes('PROMPT_PARA_CODE_C.md');
    console.log(`controle-negativo prompt-órfão · sensibilidade(órfão flagra)=${p8sens ? '🔴 OK' : 'FALHOU'} · especificidade(tudo na fila passa)=${p8espec ? '🟢 OK' : 'FALHOU'} · ref-morta=${p8morta ? '🔴 OK' : 'FALHOU'}`);
    return { sens, espec, censoStamp, censoSens, l6sens, l6espec, p8sens, p8espec, p8morta };
  }

  return { runAll, runNegativeControl, scanIdentities, lessonAssertionGap, promptProblems };
}

if (typeof module !== 'undefined') module.exports = { buildChecks };
