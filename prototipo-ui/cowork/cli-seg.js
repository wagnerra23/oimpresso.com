// CliSeg — adaptador FINO do Segmented do Design System (window.CliSeg).
//
// ── 2026-09-01: deixou de ser dono do desenho ─────────────────────────────────────
// Este arquivo nasceu porque o DS não publicava segmented, e 14 telas tinham escrito o
// seu (3 desenhos incompatíveis pra mesma função). Era a "9ª pendência do DS" — foi
// atendida: o bundle de hoje publica `Segmented`. Então o desenho (trilho, pílula ativa,
// altura, tipografia, setas ←/→) passou a ser DO DS, e a folha `cli-seg.css` saiu de
// circulação junto com este corpo antigo. O que resta aqui é tradução de vocabulário.
//
// O que traduz:
//   · `key` → `value`   (a API do app sempre falou `key`; a do DS fala `value`)
//   · `n`   → `count`
//   · `label`/`icon`/`disabled`/`size`/`full`/`ariaLabel` passam direto
//
// ── LACUNA CONHECIDA: `role` (pendência 12 do DS) ─────────────────────────────────
// O Segmented do DS crava `role="tablist"`. A API daqui aceitava `group` e `radiogroup`,
// e dois call sites são semanticamente rádio, não aba — "Pessoa física / jurídica"
// (cliente-form, cliente-drawer760): a escolha muda o FORMULÁRIO, não a visão. Com
// tablist o leitor de tela anuncia "aba", que é impreciso mas navegável; com o markup
// bespoke anterior era `radiogroup` correto. Preferi perder a nuance a manter uma pele
// paralela viva — mas está registrado como pedido ao DS, não como decisão fechada.
// O parâmetro continua sendo ACEITO para não quebrar chamador nenhum; só não tem efeito.
(function () {
  function CliSeg(props) {
    var p = props || {};
    var Segmented = (window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).Segmented;
    if (!Segmented) return null;
    var itens = (p.options || []).filter(Boolean);
    return React.createElement(Segmented, {
      options: itens.map(function (o) {
        return {
          value: o.key,
          label: o.label,
          icon: o.icon,
          count: o.n,
          disabled: o.disabled };
      }),
      value: p.value,
      onChange: p.onChange,
      size: p.size === "sm" ? "sm" : p.size === "lg" ? "lg" : "md",
      full: !!p.full,
      iconOnly: !!p.iconOnly,
      ariaLabel: p.ariaLabel });
  }

  window.CliSeg = CliSeg;
})();
