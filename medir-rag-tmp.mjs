import { coletadoPeloIndexador as c } from './.claude/hooks/doc-fora-do-rag.mjs';
const casos = [
  ['HOJE          ', 'memory/requisitos/MemCofre/ARCHITECTURE.md'],
  ['HOJE          ', 'memory/requisitos/MemCofre/adr/arq/0011-sidebar-e-topnav-duas-fontes-independentes.md'],
  ['_arquivo/     ', 'memory/requisitos/_arquivo/MemCofre/ARCHITECTURE.md'],
  ['_arquivo/     ', 'memory/requisitos/_arquivo/MemCofre/adr/arq/0011-x.md'],
  ['governance/   ', 'governance/archive/MemCofre/ARCHITECTURE.md'],
  ['reference/    ', 'memory/reference/memcofre-architecture.md'],
  ['DS adr/ui     ', 'memory/requisitos/_DesignSystem/adr/ui/0030-topnav.md'],
];
for (const [rot, p] of casos) {
  const r = c(p);
  console.log(`  ${rot} ${r === true ? 'DENTRO do RAG ' : r === false ? 'FORA do RAG   ' : 'n/a (fora do escopo requisitos)'} <- ${p}`);
}
