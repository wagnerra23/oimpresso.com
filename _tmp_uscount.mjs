import fs from 'node:fs';
import matter from 'gray-matter';
const files = fs.readFileSync(process.argv[2],'utf8').trim().split('\n').map(s=>s.trim()).filter(Boolean);
const US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/;
let comCount=0, drift=[], comList=0, driftList=[], comProject=0;
for (const f of files) {
  const raw = fs.readFileSync(f,'utf8');
  let data={}; try{({data}=matter(raw));}catch{continue;}
  const heads = raw.split('\n').filter(l=>US_HEAD_RE.test(l)).length;
  if (data.project !== undefined) comProject++;
  if (data.us_count !== undefined) { comCount++; if (Number(data.us_count) !== heads) drift.push(`${f}: us_count=${data.us_count} × headings=${heads}`); }
  if (Array.isArray(data.us_list)) { comList++; if (data.us_list.length !== heads) driftList.push(`${f}: us_list.len=${data.us_list.length} × headings=${heads}`); }
}
console.log(`SPECs: ${files.length} | com us_count: ${comCount} | com us_list(array): ${comList} | com project: ${comProject}`);
console.log(`us_count DRIFADO: ${drift.length}/${comCount}`); drift.slice(0,12).forEach(x=>console.log('   ',x));
console.log(`us_list DRIFADO: ${driftList.length}/${comList}`); driftList.slice(0,12).forEach(x=>console.log('   ',x));
