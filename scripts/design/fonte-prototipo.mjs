// fonte-prototipo.mjs — onde mora a FONTE do protótipo de cada dono (ADR 0433).
//
// [W] 2026-10-15, textual: "Sim: passa a fonte do protótipo para o git. Primeiro traz a versão
// atual do Cowork (com o celular) para o espelho; depois eu peço as mudanças aqui e você edita
// direto."
//
// A partir de `desde`, `prototipo-ui/cowork/<dono>/` deixa de ser cópia do projeto Cowork e passa
// a ser a fonte: edição commitada ali é autoria, não remendo. O Cowork vira ferramenta opcional.
// Fato datado que pesou: o claude.ai/design standalone fecha em 2026-12-14 (PROTOCOL.md §10.8).
//
// Módulo pequeno de propósito: é importado pelo check `--unverified` do cowork-mirror-freshness
// e pelo hook charter-validate (PreToolUse), e nenhum dos dois repete a data.
export const FONTE_GIT = Object.freeze({ dono: 'Wagner', desde: '2026-10-15T00:00:00-03:00', adr: '0433' });

/** `true` quando o path do espelho (relativo a `prototipo-ui/cowork/`) pertence a um dono cuja fonte é o git. */
export function fonteNoGit(relCowork, fonte = FONTE_GIT) {
  if (!fonte || !relCowork) return false;
  return String(relCowork).replace(/\\/g, '/').startsWith(`${fonte.dono}/`);
}
