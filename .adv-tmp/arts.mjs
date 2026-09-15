import { execFileSync } from 'node:child_process';

const gh = (p) => JSON.parse(execFileSync('gh', ['api', p], { encoding: 'utf8', maxBuffer: 64e6 }));

for (const r of process.argv.slice(2)) {
  let run, arts;
  try {
    run = gh(`repos/wagnerra23/oimpresso.com/actions/runs/${r}`);
    arts = gh(`repos/wagnerra23/oimpresso.com/actions/runs/${r}/artifacts`);
  } catch {
    console.log(`${r}\tERRO`);
    continue;
  }
  const dv = arts.artifacts.find((a) => a.name === 'pixel-diff-views');
  console.log(`${r}\t${run.created_at}\t${run.head_branch}\tdiffviews=${dv ? dv.id : 'NAO'}`);
}
