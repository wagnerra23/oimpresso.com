// ──────────────────────────────────────────────────────────────
// domain/quote · QuoteEngine — custeio por processo + preço.
// Custo real = material + chapa + máquina + acabamento + mão de obra
// (com refugo). Preço pela margem. Tiragem maior → unitário menor
// (setup amortizado) = a regressiva que o protótipo não tinha.
//
// Tudo em CENTAVOS inteiros (sem float em dinheiro).
// ──────────────────────────────────────────────────────────────
import {
  CHAPAS_POR_CORES, type FichaTecnica, type Suporte,
  type ParametrosCusto, type OrcamentoBreakdown,
} from "./types";
import { pecasPorFolha, consumoBobina } from "./imposition";

const round = Math.round;

/**
 * Calcula o orçamento. `margemPct` é PASSADO SEPARADAMENTE porque é política
 * da empresa (cadastro do produto), e não um parâmetro de produção nem da tela.
 * O router deve lê-la do produto — nunca aceitá-la da entrada do usuário.
 */
export function calcularOrcamento(
  ficha: FichaTecnica, suporte: Suporte, p: ParametrosCusto, margemPct: number,
): OrcamentoBreakdown {
  const sangria = ficha.sangriaMm ?? 3;
  const chapas = CHAPAS_POR_CORES[ficha.cores];

  let pecasFolha = 0;
  let folhasNecessarias = 0;
  let custoMaterialCents = 0;
  let metrosLineares: number | undefined;
  let m2Consumidos: number | undefined;

  if (suporte.tipo === "folha") {
    pecasFolha = pecasPorFolha(
      suporte.larguraMm, suporte.alturaMm ?? 0,
      ficha.larguraMm, ficha.alturaMm, sangria, suporte.margemMm,
    );
    const folhasUteis = pecasFolha > 0 ? Math.ceil(ficha.tiragem / pecasFolha) : 0;
    folhasNecessarias = round(folhasUteis * (1 + p.refugoPct / 100));
    custoMaterialCents = folhasNecessarias * suporte.custoCents;
  } else {
    const c = consumoBobina(
      suporte.larguraMm, suporte.margemMm,
      ficha.larguraMm, ficha.alturaMm, sangria, ficha.tiragem,
    );
    pecasFolha = c.pecasPorLinha;
    metrosLineares = c.metrosLineares;
    m2Consumidos = round(c.m2 * (1 + p.refugoPct / 100) * 100) / 100;
    custoMaterialCents = round(m2Consumidos * suporte.custoCents);
  }

  // Chapa (offset). Digital não tem.
  const custoChapaCents = p.digital ? 0 : chapas * p.custoChapaCents;

  // Tempo de máquina = setup + impressão.
  const tempoImpressaoMin = suporte.tipo === "folha" && p.velocidadeFolhasPorH > 0
    ? (folhasNecessarias / p.velocidadeFolhasPorH) * 60
    : Math.max(5, (metrosLineares ?? 0) * 0.5); // wide-format aproximado
  const tempoEstimadoMin = round(p.setupMin + tempoImpressaoMin);
  const custoMaquinaCents = round(tempoEstimadoMin * p.maquinaCentsPorMin);

  // Acabamento por peça.
  const custoAcabamentoCents = round(
    ficha.acabamentos.reduce((s, a) => s + (p.acabamentoCentsPorPeca[a] ?? 0), 0) * ficha.tiragem,
  );

  // Mão de obra (proporcional ao tempo de máquina + acabamento).
  const custoMaoObraCents = round(tempoEstimadoMin * p.maoDeObraCentsPorMin);

  const custoTotalCents =
    custoMaterialCents + custoChapaCents + custoMaquinaCents +
    custoAcabamentoCents + custoMaoObraCents;

  // Preço pela margem do CADASTRO do produto. margemPct=40 → preço = custo / 0.6
  const fator = 1 - Math.min(95, margemPct) / 100;
  const precoCents = fator > 0 ? round(custoTotalCents / fator) : custoTotalCents;
  const precoUnitCents = ficha.tiragem > 0 ? round(precoCents / ficha.tiragem) : 0;

  return {
    pecasPorFolha: pecasFolha, folhasNecessarias,
    metrosLineares, m2Consumidos,
    custoMaterialCents, custoChapaCents, custoMaquinaCents,
    custoAcabamentoCents, custoMaoObraCents, custoTotalCents,
    precoCents, precoUnitCents, tempoEstimadoMin,
  };
}
