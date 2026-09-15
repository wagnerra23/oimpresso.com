import { execFileSync } from 'node:child_process';

const runs = process.argv.slice(2);
const gh = (p) => JSON.parse(execFileSync('gh', ['api', p], { encoding: 'utf8', maxBuffer: 64e6 }));

for (const r of runs) {
  let jobs;
  try {
    jobs = gh(`repos/wagnerra23/oimpresso.com/actions/runs/${r}/jobs?per_page=100`);
  } catch (e) {
    console.log(`${r}\tERRO_API`);
    continue;
  }
  const j = jobs.jobs.find((x) => /visual-regression/i.test(x.name));
  if (!j) { console.log(`${r}\tSEM_JOB_VISREG`); continue; }
  const find = (re) => j.steps.find((s) => re.test(s.name));
  const modo = find(/Modo de execução/);
  const skipAsPass = find(/Skip-as-pass/);
  const s25 = find(/Estados isolados matriz/);
  const s24 = find(/Pixel-diff afetadas/);
  const fails = j.steps.filter((s) => s.conclusion === 'failure').map((s) => `${s.number}:${s.name}`);
  console.log(
    [
      r,
      j.head_branch ?? '',
      `job=${j.conclusion}`,
      `skipAsPass=${skipAsPass?.conclusion ?? '-'}`,
      `s24=${s24?.conclusion ?? '-'}`,
      `s25=${s25?.conclusion ?? '-'}`,
      `FAILS=[${fails.join(' | ')}]`,
    ].join('\t')
  );
}
