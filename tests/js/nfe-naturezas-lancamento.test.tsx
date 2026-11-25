/**
 * "Como fica no lançamento" — prévia por destino das naturezas de operação (RUNBOOK-tributacao §Naturezas).
 *
 * @covers-us UC-NFTR-70
 *
 * Casos derivados do protótipo aprovado (`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`,
 * `lancamento(N, d)`, e o que ele mostra para "Venda de produção do estabelecimento" no Lucro Presumido em SC),
 * e as alíquotas do motor (`MotorTributarioService::PIS_COFINS_DO_REGIME`, `nfebrasil.ibs_cbs_por_ano`).
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import ComoFicaNoLancamento from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/ComoFicaNoLancamento';
import { lancamento } from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/lancamento';
import { vazia, type EmpresaResumo, type LinhaNatureza, type Natureza } from '@/Pages/NfeBrasil/Tributacao/_components/Naturezas/natureza';

const EMPRESA: EmpresaResumo = { nome: 'Empresa Teste', cnpj: null, municipio: 'Tubarão', uf: 'SC', ano: 2026, ibs: 0.001, cbs: 0.009 };

function venda(): Natureza {
  return { ...vazia(), nome: 'Venda de produção do estabelecimento', cfop: { in: '5101', out: '6101', ext: '' } };
}

function porRotulo(n: Natureza, d: 'in' | 'out' | 'ext', regime: 'LP' | 'LR' | 'SN') {
  return Object.fromEntries(lancamento(n, d, regime, EMPRESA).map((x) => [x.rotulo, x]));
}

afterEach(() => vi.unstubAllGlobals());

describe('Como fica no lançamento', () => {
  it('UC-NFTR-70 · a prévia diz o CFOP, o CST e de onde vem cada alíquota, por destino e regime', () => {
    // Lucro Presumido, dentro de SC — a mesma leitura que o protótipo mostra.
    const dentro = porRotulo(venda(), 'in', 'LP');
    expect(dentro['CFOP'].codigo).toBe('5101');
    expect(dentro['CFOP'].texto).toBe('Venda de produção do estabelecimento');
    expect(dentro['ICMS'].codigo).toBe('CST 00');
    expect(dentro['ICMS'].texto).toBe('· base: valor da operação');
    expect(dentro['ICMS'].sub).toBe('Alíquota interna de SC');
    expect(dentro['IPI'].texto).toBe('Não destaca');
    expect(dentro['PIS'].sub).toBe('0,65% do Lucro Presumido (cumulativo) · sem o ICMS na base');
    expect(dentro['COFINS'].sub).toBe('3% do Lucro Presumido (cumulativo) · sem o ICMS na base');
    expect(dentro['IBS e CBS'].codigo).toBe('CST 000 · cClassTrib 000001');
    expect(dentro['IBS e CBS'].sub).toBe('IBS 0,1% e CBS 0,9% (ano de teste)');

    // Fora do estado: CFOP 6xxx, alíquota interestadual e DIFAL/FCP.
    const fora = porRotulo(venda(), 'out', 'LP');
    expect(fora['CFOP'].codigo).toBe('6101');
    expect(fora['ICMS'].sub).toBe('Alíquota interestadual pela UF do cliente (4%, 7% ou 12%)');
    expect(fora['DIFAL e FCP']).toBeTruthy();
    expect(dentro['DIFAL e FCP']).toBeUndefined();

    // Sem CFOP para o exterior, a prévia avisa em vez de inventar um.
    expect(porRotulo(venda(), 'ext', 'LP')['CFOP'].texto).toBe('Sem CFOP para exterior');

    // Lucro Real usa as alíquotas não cumulativas do motor.
    expect(porRotulo(venda(), 'in', 'LR')['PIS'].sub).toBe('1,65% do Lucro Real (não cumulativo) · sem o ICMS na base');

    // Simples: CSOSN 102 não destaca ICMS próprio e PIS/COFINS vão no DAS.
    const sn = porRotulo(venda(), 'in', 'SN');
    expect(sn['ICMS'].codigo).toBe('CSOSN 102');
    expect(sn['ICMS'].sub).toBe('Sem destaque de ICMS próprio; recolhido no DAS');
    expect(sn['PIS'].sub).toBe('Recolhido no DAS, sem destaque');
    expect(sn['PIS'].codigo).toBe('CST 49');
  });

  it('UC-NFTR-70 · só entram na prévia as naturezas criadas pela tela; a Venda padrão fica de fora', () => {
    const linhas: LinhaNatureza[] = [
      { id: 1, editavel: false, padrao: true, pendencias: null,
        natureza: { nome: 'Venda', tipo: 'S', fin: '1', ativo: true, ref: false, cfop: { in: '?102', out: '', ext: '' } } },
      { id: 2, editavel: true, padrao: false, pendencias: 0, natureza: venda() },
    ];
    render(<ComoFicaNoLancamento linhas={linhas} regime="LP" empresa={EMPRESA} />);

    expect(screen.getByRole('heading', { name: 'Como fica no lançamento' })).toBeTruthy();
    const cfop = document.querySelector('[data-lanc="CFOP"]') as HTMLElement;
    expect(within(cfop).getByText('5101')).toBeTruthy();
    expect(screen.queryByText('?102')).toBeNull();

    // Só a Venda do sistema ativa: não diz "cadastre uma natureza ativa", porque há uma ativa na tabela.
    const { unmount } = render(<ComoFicaNoLancamento linhas={[linhas[0]]} regime="LP" empresa={EMPRESA} />);
    expect(screen.queryByText(/Cadastre uma natureza ativa/)).toBeNull();
    expect(screen.getByText('Venda já é usada pelo cálculo da nota e não é montada nesta tela. Crie uma natureza aqui para ver como ela fica no lançamento.')).toBeTruthy();
    unmount();

    // Nenhuma natureza ativa: aí sim pede o cadastro.
    const inativa = { ...linhas[0], natureza: { ...linhas[0].natureza, ativo: false } };
    render(<ComoFicaNoLancamento linhas={[inativa]} regime="LP" empresa={EMPRESA} />);
    expect(screen.getByText('Cadastre uma natureza ativa para ver como ela fica no lançamento.')).toBeTruthy();
  });
});
