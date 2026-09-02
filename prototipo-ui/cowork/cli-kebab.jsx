// CliKebab — adaptador FINO do Kebab do Design System.
//
// ── POR QUE EXISTE ────────────────────────────────────────────────────────────────
// A auditoria de 2026-09-01 achou SEIS implementações praticamente idênticas de kebab
// (modulos, officeimpresso, superadmin, comissionados, usuarios, connector): mesmo
// `{items}`, mesmo `useState(open)`, mesmo listener de mousedown pra fechar, mesmo SVG
// de três pontos, mesma pele `.cli-kebab-wrap/.cli-kebab-btn/.cli-kebab-menu`. Seis
// cópias do mesmo componente, cada uma com o seu bug em potencial.
//
// O DS publica `Kebab` (bundle:4606), e ele NÃO reimplementa menu: desenha o botão
// icônico e delega itens, teclado (↑/↓/esc), clique-fora e ancoragem ao `DropdownMenu`.
// Isso é mais do que as seis versões tinham — nenhuma delas navegava por teclado.
//
// Este arquivo só traduz vocabulário, porque a API local e a do DS divergem:
//   local            → DS
//   { label }        → { label }
//   { action }       → { onSelect }
//   { danger: true } → { tone: "danger" }
//   { sep: true }    → { separator: true }
//   { disabled }     → { disabled }
// `label` (título do botão) continua por chamador: cada tela tinha o seu ("Ações do
// módulo", "Ações do client", "Mais ações") e isso é conteúdo, não desenho.
(() => {
function CliKebab({ items = [], label = "Mais ações", align, size, width, disabled }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const Kebab = DS.Kebab;
  if (!Kebab) return null;
  return (
    <Kebab
      label={label}
      align={align}
      size={size}
      width={width}
      disabled={disabled}
      items={items.filter(Boolean).map((it, i) => it.sep
        ? { id: "sep" + i, separator: true }
        : { id: "i" + i, label: it.label, disabled: it.disabled,
            tone: it.danger ? "danger" : undefined,
            onSelect: it.action })} />);
}
window.CliKebab = CliKebab;
})();
