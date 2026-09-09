// Adaptadores de resultados: os formatos mantêm seus próprios sinais de execução.
export function validarPlaywright(report, testes, raiz) {
  if (!report || !Array.isArray(report.errors) || report.errors.length || !Array.isArray(report.suites)
    || !report.stats || !Number.isInteger(report.stats.expected) || report.stats.expected < 1
    || ['unexpected','flaky','skipped'].some(k => report.stats[k] !== 0)) return false;
  const files = new Set(); let total = 0, ok = true;
  function suite(s) {
    if (!Array.isArray(s.specs)) { ok=false; return; }
    for (const spec of s.specs) {
      if (typeof spec.file !== 'string' || spec.file.includes('..') || spec.file.startsWith('/')
        || spec.file.includes('\\') || spec.file.includes(':') || spec.ok !== true
        || !Array.isArray(spec.tests) || !spec.tests.length) { ok=false; continue; }
      files.add(raiz === '.' ? spec.file : raiz + '/' + spec.file);
      for (const t of spec.tests) {
        total++;
        if (t.expectedStatus !== 'passed' || t.status !== 'expected' || !Array.isArray(t.results)
          || t.results.length !== 1 || t.results[0].status !== 'passed' || t.results[0].retry !== 0
          || t.results[0].error || !Array.isArray(t.results[0].errors) || t.results[0].errors.length) ok=false;
      }
    }
    if (s.suites !== undefined && !Array.isArray(s.suites)) ok=false;
    for (const sub of s.suites || []) suite(sub);
  }
  for (const s of report.suites) suite(s);
  return ok && total === report.stats.expected && testes.every(p => files.has(p));
}

export function validarRevisao(r, criterios) {
  return typeof r.revisor === 'string' && r.revisor.trim().length > 0
    && r.resultado === 'aprovado' && Array.isArray(r.criterios)
    && criterios.every(id => {
      const itens = r.criterios.filter(c => c.id === id);
      return itens.length === 1 && itens[0].resultado === 'aprovado'
        && typeof itens[0].justificativa === 'string' && itens[0].justificativa.trim().length > 0;
    });
}
