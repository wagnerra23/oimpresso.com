import matter from 'gray-matter';
const casos = {
  'PROPOSTA (owners:[{{W}}] + project:{{SIGLA}} + module:{{PascalCase}})': `---
module: {{PascalCase}}
version: "0.1.0"
last_updated: "{{YYYY-MM-DD}}"
owners: [{{W}}]
status: rascunho
project: {{SIGLA}}
us_count: 0
us_list: []
related_adrs: []
anchor_format: "v1"
---
corpo`,
  'TEMPLATE ATUAL (owners:[W] curado, last_updated sem aspas)': `---
module: {{PascalCase}}
last_updated: {{YYYY-MM-DD}}
version: "0.1.0"
owners: [W]
status: rascunho
---
corpo`,
  'so owners: [{{W}}]': `---
module: X
owners: [{{W}}]
---
c`,
  'so project: {{SIGLA}}': `---
module: X
project: {{SIGLA}}
---
c`,
};
for (const [nome, raw] of Object.entries(casos)) {
  try { const { data } = matter(raw); console.log('OK   ', nome, '→', JSON.stringify(data)); }
  catch (e) { console.log('ERRO ', nome, '→', String(e.message).split('\n')[0]); }
}
