import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
// Executa somente o comparador canônico, nunca command do recibo.
export function compararNoRepo(root, recibo, prova) {
  const run = spawnSync(process.execPath, [join(root,'prototipo-ui/design-diff.mjs'),
    '--compare', join(root,recibo.producao), join(root,recibo.prototipo),
    '--contrato', join(root,prova.contrato), '--check', '--check-shell', '--json'],
    {cwd:root,encoding:'utf8',timeout:30000,maxBuffer:4*1024*1024});
  if (run.status !== 0) return false;
  const start = run.stdout.indexOf('{\n  "rows"');
  if (start < 0) return false;
  try {
    const result=JSON.parse(run.stdout.slice(start));
    return result.sameTheme === true && result.bugs === 0 && result.shell === 0
      && Array.isArray(result.rows) && prova.dimensoes.every(dim => {
        const rows=result.rows.filter(r=>r.dim===dim);
        if (dim === 'SHELL') return rows.every(r=>r.veredito==='IGUAL');
        return rows.length > 0 && rows.every(r=>r.veredito==='IGUAL');
      });
  } catch { return false; }
}
