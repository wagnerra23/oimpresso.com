// UC-PAT-02 · Patrimonio/Index — número SEM FONTE mostra `—`, nunca `R$ 0,00`.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Dois números do protótipo não têm fonte no banco, e o painel tem de dizer isso em vez de
// preencher o buraco:
//   • **valor residual** — `assets.depreciation` é gravada como texto livre
//     (`StoreAssetRequest:67` valida `['nullable','string']`) e relida SÓ pelo
//     `edit.blade.php:71` pra repopular o próprio form. Nenhuma aritmética no repo. A regra
//     (linear ou SAC) é decisão [W] em aberto — RESÍDUO 6, dono `SPEC.md:96 US-ASSET-W01`.
//   • **custo de manutenção** — `asset_maintenances` não tem coluna de valor (o
//     `additional_cost` mora em `asset_warranties` e é outra coisa). RESÍDUO 3.
//
// A regressão que isto mata é barata de cometer e cara de perceber: trocar o `—` por `0`
// "pra não ficar feio". `R$ 0,00` AFIRMA que não há valor residual e que não se gastou nada
// em manutenção — e nenhuma das duas é o que se sabe. O traço diz a verdade; o zero mente
// com aparência de dado.
//
// ── O QUE ELE MEDE (componente REAL) ─────────────────────────────────────────
// Importa a Page de produção. Os `vi.mock` cobrem só a CASCA (shell, subnav, header) — os
// KPIs, a lista de manutenções, o `brl()` e o `qtd()` são os de verdade. Reimplementar
// qualquer um deles mediria a minha cópia, não a tela (§5 2026-06-05, teste tautológico).
//
// ── A MORDIDA (provada, não afirmada) ────────────────────────────────────────
// Mutação aplicada ao código real em 2026-09-08: trocando
// `value={kpis.valorResidual === null ? SEM_FONTE : brl(kpis.valorResidual)}` por
// `value={brl(kpis.valorResidual ?? 0)}` — que é exatamente o "conserto" tentador — os dois
// primeiros casos ficam VERMELHOS. O `.tsx` foi restaurado e o verde reconfirmado. Sem esse
// par o arquivo seria carimbo: verde que não sabe ficar vermelho.
//
// O caso 1 é CONTROLE POSITIVO do harness: ele exige o rótulo PRESENTE. Se os mocks tivessem
// quebrado o render, ele falharia — e só então os demais significam alguma coisa.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre o SERVIDOR. Que `painelKpis()` devolve `null` (e não `0`) é contrato do
//     controller; um teste disso exige `Modules/AssetManagement/Tests/`, fora do prefixo
//     desta thread. Aqui a prop `null` é fixture.
//   - Nada sobre CÁLCULO de valor: nenhum assert soma dinheiro, e o `bruto` da fixture é
//     constante (REGRA MESTRE, memory/proibicoes.md).
//
// @see resources/js/Pages/Patrimonio/Index.casos.md (UC-PAT-02, UC-PAT-03)
// @see memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md §3

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: {} }),
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));
// O header é canon (`@/Components/PageHeader`, export NOMEADO) desde a migração do
// `shared/PageHeader`. O mock segue o import da Page: apontá-lo pro caminho antigo
// deixaria de interceptar em silêncio e o header real entraria no render — o teste
// continuaria verde medindo outra coisa.
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }));

import PainelPatrimonio from '@/Pages/Patrimonio/Index';

const BASE = {
  is_admin: true,
  pode: { ver: true, criar: true },
  apurado_em: '2026-09-08T09:42:00-03:00',
  porCategoria: [],
  garantia: [],
};

/** KPIs com fonte para tudo, MENOS o valor residual — que é o estado real do sistema hoje. */
const KPIS_SEM_RESIDUAL = {
  bruto: 305230,
  valorResidual: null as number | null,
  unidades: 21,
  totalBens: 11,
  alocados: 4,
  alocaveis: 13,
  garantiaCritica: 5,
};

/** Uma manutenção aberta. `custo: null` porque a tabela não tem coluna de valor. */
const MANUTENCAO_SEM_CUSTO = [
  { id: 1, bem: 'Roland VersaCAMM VS-640', codigo: 'PAT-0007', status: 'in_progress', abertaEm: '2026-08-14', custo: null as number | null },
];

afterEach(cleanup);

describe('UC-PAT-02 · valor residual sem fonte', () => {
  it('CONTROLE POSITIVO: o card "Valor residual" existe (o harness renderiza)', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS_SEM_RESIDUAL} manutencoes={[]} />);
    expect(screen.getByText('Valor residual')).toBeDefined();
  });

  it('com `valorResidual: null`, o card mostra o traço e NENHUM valor em reais', () => {
    const { container } = render(<PainelPatrimonio {...BASE} kpis={KPIS_SEM_RESIDUAL} manutencoes={[]} />);
    // O card é o ancestral que contém o rótulo; o valor é irmão dele dentro do KpiCard.
    const card = screen.getByText('Valor residual').closest('div[class*="rounded-xl"]');
    expect(card, 'KpiCard do valor residual').not.toBeNull();
    expect(card!.textContent).toContain('—');
    expect(card!.textContent, 'zero mentiria: afirmaria que não sobrou valor').not.toMatch(/R\$/);
    // E o resto da tela segue mostrando dinheiro — o traço é local, não um apagão geral.
    expect(container.textContent).toMatch(/R\$/);
  });

  it('a prosa do "Resumo de hoje" também não inventa o residual', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS_SEM_RESIDUAL} manutencoes={[]} />);
    const resumo = document.querySelector('[data-contract="resumo"]');
    expect(resumo).not.toBeNull();
    expect(resumo!.textContent).toContain('—');
  });
});

describe('UC-PAT-03 · custo de manutenção sem coluna', () => {
  it('a linha da manutenção mostra o traço no lugar do custo', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS_SEM_RESIDUAL} manutencoes={MANUTENCAO_SEM_CUSTO} />);
    const linha = screen.getByText(/Roland VersaCAMM VS-640/).closest('li');
    expect(linha, 'linha da manutenção').not.toBeNull();
    expect(linha!.textContent).toContain('—');
    expect(linha!.textContent).not.toMatch(/R\$/);
  });

  it('o total do ano é traço, não R$ 0,00', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS_SEM_RESIDUAL} manutencoes={MANUTENCAO_SEM_CUSTO} />);
    const total = screen.getByText(/custo de manutenção no ano/);
    expect(total.textContent).toContain('—');
    expect(total.textContent).not.toMatch(/R\$\s*0/);
  });
});

describe('UC-PAT-04 · quantidade é decimal e não se arredonda', () => {
  it('`quantity` fracionária sobrevive ao render (decimal(22,4), não contagem de registros)', () => {
    // Os cards do painel somam QUANTIDADE. Truncar pra inteiro perderia dado real — foi o
    // "conserto" que quase virou defeito quando os cards do Blade mostravam 0,00.
    const fracionado = { ...KPIS_SEM_RESIDUAL, alocados: 4.5, alocaveis: 13.25 };
    render(<PainelPatrimonio {...BASE} kpis={fracionado} manutencoes={[]} />);
    // `selector: 'span'` desambigua o LABEL do KPI do CHIP de navegação homônimo que o
    // painel ganhou junto com o resumo (o chip é um `<a data-slot="button">`). A palavra
    // se repete na tela porque se repete no protótipo; quem tem de ser específico é o
    // seletor. O que o caso mede segue idêntico — os dois asserts abaixo não mudaram.
    const card = screen.getByText('Alocados', { selector: 'span' }).closest('div[class*="rounded-xl"]');
    expect(card!.textContent).toContain('4,5');
    expect(card!.textContent).toContain('13,25');
  });
});
