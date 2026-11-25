/**
 * Tributação — aplicar modelo pede confirmação antes de mexer na configuração.
 *
 * @covers-us UC-NFTR-78
 *
 * Por que RENDER: o Pest prova o que o servidor faz ao aplicar (UC-NFTR-03). O `confirm()` é do
 * navegador, e só o render prova que o clique não chega ao servidor sem o "OK" de quem está na tela,
 * e que o aviso diz o que vai ser substituído.
 *
 * Contrato: Index.charter.md §Goals (confirmação explícita ao aplicar template sobre config existente).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => children,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  usePage: () => ({ url: '/nfe-brasil/tributacao', props: { flash: {} } }),
  router: { post: (...a: unknown[]) => post(...a), get: vi.fn(), delete: vi.fn(), reload: vi.fn() },
}));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
// Seções vizinhas fazem fetch e não interessam a este caso.
vi.mock('@/Pages/NfeBrasil/Tributacao/_components/ConfigurarPeloCertificado', () => ({ default: () => null }));
vi.mock('@/Pages/NfeBrasil/Tributacao/_components/SimuladorNota', () => ({ default: () => null }));
vi.mock('@/Pages/NfeBrasil/Tributacao/_components/Naturezas/NaturezasSecao', () => ({ default: () => null }));
vi.mock('@/Pages/NfeBrasil/Tributacao/_components/SaudeFiscal', () => ({ default: () => null }));

import Index from '@/Pages/NfeBrasil/Tributacao/Index';

const template = {
  slug: 'comercio-varejo-simples-sp', titulo: 'Comércio varejista · Simples', descricao: 'Varejo no Simples Nacional',
  icon: 'shopping-bag', setor: 'comercio', regime: 'simples', uf: 'SP', modelo_nfe: '55', recomendado_para: '',
  tributacao_default: {}, observacoes: [],
};
const config = { regime: 'lucro_presumido', tributacao_default: {}, auto_emission_enabled: false };
const regra = {
  id: 1, ncm: '84439100', uf_origem: 'SP', uf_destino: null, cfop: '5102', csosn: '102', cst: null,
  aliquota_icms: 0.18, aliquota_pis: 0.0065, aliquota_cofins: 0.03, aliquota_ipi: 0, mva: null, fcp: null,
};

function abrir(props: Record<string, unknown>) {
  render(<Index regras={[]} templates={[template]} config={null} {...props} />);
  fireEvent.click(screen.getByRole('button', { name: 'Aplicar template' }));
}

beforeEach(() => post.mockClear());
afterEach(() => vi.unstubAllGlobals());

describe('Aplicar modelo de tributação', () => {
  it('UC-NFTR-78 · cancelar a confirmação não envia nada ao servidor', () => {
    const confirmar = vi.fn(() => false);
    vi.stubGlobal('confirm', confirmar);

    abrir({ config, regras: [regra] });

    expect(confirmar).toHaveBeenCalledTimes(1);
    expect(post).not.toHaveBeenCalled();
  });

  it('UC-NFTR-78 · com configuração existente, o aviso diz que ela será substituída e que as regras ficam', () => {
    const confirmar = vi.fn(() => true);
    vi.stubGlobal('confirm', confirmar);

    abrir({ config, regras: [regra] });

    const aviso = String(confirmar.mock.calls[0][0]);
    expect(aviso).toContain('SUBSTITUIR a configuração atual');
    expect(aviso).toContain('Regras NCM existentes (1) permanecem inalteradas');
    // CONTROLE POSITIVO — com o OK, o pedido sai para a rota do modelo escolhido.
    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][0]).toBe('/nfe-brasil/tributacao/templates/comercio-varejo-simples-sp/aplicar');
  });

  it('UC-NFTR-78 · sem configuração, o aviso fala em criar a configuração inicial', () => {
    const confirmar = vi.fn(() => true);
    vi.stubGlobal('confirm', confirmar);

    abrir({ config: null });

    const aviso = String(confirmar.mock.calls[0][0]);
    expect(aviso).toContain('criar a configuração tributária inicial');
    expect(aviso).not.toContain('SUBSTITUIR');
    expect(post).toHaveBeenCalledTimes(1);
  });
});
