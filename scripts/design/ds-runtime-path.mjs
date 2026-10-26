// @ts-check
/**
 * ds-runtime-path.mjs — onde um artefato de RUNTIME do Design System pousa no cache de preview.
 *
 * Dono único da regra que o render do protótipo usa para servir `_ds/**`. Morava no
 * `cowork-mirror-freshness.mjs`, junto do transporte do Cowork; saiu de lá em 2026-10-23
 * (ADR 0438, onda 2c-3) quando o transporte saiu, porque os consumidores que ficam são de
 * RENDER: `render-proto-baseline.mjs`, `design-diff-lote.mjs` e o `--preview-ds`.
 *
 * O shell pode pedir `_ds/<slug>/...` (bind do Wagner, com slug) ou `_ds/...` (bind do Felipe,
 * sem slug). O destino é o mesmo: o caminho relativo dentro de `prototipo-ui/design-system/`.
 * Template/componente-fonte não entra aqui — lança.
 */

const DS_RUNTIME_RE = /^(?:_ds_bundle\.js|colors_and_type\.css|cockpit_domains\.css|styles\.css|assets\/)/;

export function dsRuntimeRelPath(path) {
  const p = String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');
  const inseguro = (rel) => !rel || rel.startsWith('/') || rel.split('/').includes('..');
  // DUAS FORMAS de bind, e as duas são legítimas (medido 2026-09-17, PR-A9):
  //   Wagner  `_ds/<slug>/colors_and_type.css`   — o bind carimba o slug do projeto DS
  //   Felipe  `_ds/colors_and_type.css`          — bind sem slug (`Felipe/venda-v3/index.html`)
  //
  // ORDEM: tenta COM slug primeiro e cai pra SEM slug — mas cada tentativa só vale se o
  // resultado BATER a whitelist:
  //   `_ds/assets/fonts/a.woff2`        com-slug daria `fonts/a.woff2` (não bate) ⇒ cai pro
  //                                     sem-slug e resolve `assets/fonts/a.woff2`.
  //   `_ds/assets/colors_and_type.css`  AMBÍGUO: as duas leituras batem. Com-slug vence, por
  //                                     regra declarada — nunca por acaso.
  // Os dois casos estão no teste do dono; sem eles a ordem viraria detalhe de implementação.
  const comSlug = p.replace(/^_ds\/[^/]+\//, '');
  if (comSlug !== p && !inseguro(comSlug) && DS_RUNTIME_RE.test(comSlug)) return comSlug;

  const semSlug = p.replace(/^_ds\//, '');
  if (semSlug !== p && !inseguro(semSlug) && DS_RUNTIME_RE.test(semSlug)) return semSlug;

  const candidato = comSlug !== p ? comSlug : semSlug;
  if (inseguro(candidato)) throw new Error(`ds-runtime: caminho inseguro "${path}"`);
  throw new Error(`ds-runtime: "${path}" não é bundle/CSS/asset de runtime; use --ds para fonte/template`);
}
