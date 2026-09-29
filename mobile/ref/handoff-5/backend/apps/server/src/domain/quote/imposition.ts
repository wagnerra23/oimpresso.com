// ──────────────────────────────────────────────────────────────
// domain/quote · imposição (nesting) — quantas peças saem do suporte.
// Modelo de "gutter" (espaço entre peças, sangria compartilhada no
// corte), realista para orçamento: 2 A4 numa SRA3, etc. Duas
// orientações + tira mista. Nesting avançado pode refinar depois.
// ──────────────────────────────────────────────────────────────

/** Peças por folha (gutter entre peças; margem = pinça/borda nas 2 bordas). */
export function pecasPorFolha(
  folhaLargura: number, folhaAltura: number,
  pecaLargura: number, pecaAltura: number,
  gutterMm = 3, margemMm = 0,
): number {
  const FL = folhaLargura - margemMm * 2;
  const FA = folhaAltura - margemMm * 2;
  const g = gutterMm;
  if (pecaLargura <= 0 || pecaAltura <= 0 || FL <= 0 || FA <= 0) return 0;

  // Quantos cabem numa malha, dada orientação (pw × ph).
  // +g de cada lado vira "compartilhado": n peças ocupam n*(p+g) - g ≈ (FL+g)/(p+g).
  const fit = (pw: number, ph: number): number => {
    const cols = Math.floor((FL + g) / (pw + g));
    const rows = Math.floor((FA + g) / (ph + g));
    return Math.max(0, cols) * Math.max(0, rows);
  };

  const a = fit(pecaLargura, pecaAltura);          // retrato
  const b = fit(pecaAltura, pecaLargura);          // paisagem

  // Tira mista: bloco em retrato + sobra preenchida em paisagem (guilhotina).
  const cols = Math.floor((FL + g) / (pecaLargura + g));
  const sobra = FL - cols * (pecaLargura + g);
  const mista = cols * Math.floor((FA + g) / (pecaAltura + g))
    + (sobra > 0 ? Math.floor((sobra + g) / (pecaAltura + g)) * Math.floor((FA + g) / (pecaLargura + g)) : 0);

  return Math.max(a, b, mista);
}

/** Wide-format (bobina): metros lineares e m² para a tiragem. */
export function consumoBobina(
  larguraBobinaMm: number, margemMm: number,
  pecaLargura: number, pecaAltura: number, gutterMm: number,
  tiragem: number,
): { metrosLineares: number; m2: number; pecasPorLinha: number } {
  const util = larguraBobinaMm - margemMm * 2;
  const g = gutterMm;
  const pecasPorLinha = Math.max(1, Math.floor((util + g) / (pecaLargura + g)));
  const linhas = Math.ceil(tiragem / pecasPorLinha);
  const metrosLineares = (linhas * (pecaAltura + g)) / 1000;
  const m2 = metrosLineares * (larguraBobinaMm / 1000);
  return { metrosLineares, m2, pecasPorLinha };
}
