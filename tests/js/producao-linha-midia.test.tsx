// UC-PCLIN-01 · Linha do tempo: aviso "Mesma mídia: dá para agrupar" e o botão Agrupar.
// Fonte: alerta da vista `Linha` em prototipo-ui/cowork/Wagner/producao-cv-page.jsx e
// Modules/Producao/Resources/js/Pages/Producao/Linha/Index.casos.md.
//
// O que mede: a tela montada, com a prop `linha` entregue: o texto do aviso, o PUT da reprogramação e a troca
// para "Agrupado por mídia". CONTROLES: sem permissão não há botão; no próximo dia útil não há aviso.
// O que NÃO prova: a regra que decide o par (ProducaoLinhaContratoTest) nem a cor medida no navegador.
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

const { json } = vi.hoisted(() => ({ json: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ Deferred: ({ children }: { children: ReactNode }) => children, router: { reload: vi.fn(), visit: vi.fn() } }));
vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: { children: ReactNode }) => children }));
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }));
vi.mock('../../Modules/Producao/Resources/js/Pages/Producao/_components/ProducaoAbas', () => ({ default: () => null }));
vi.mock('../../Modules/Producao/Resources/js/Pages/Producao/Quadro/_lib/json', () => ({ json, Recusa: class extends Error {} }));

import LinhaIndex from '../../Modules/Producao/Resources/js/Pages/Producao/Linha/Index';

const D = '2026-11-03';
const bloco = { id: 7, os_tipo: 'comvis_os', os_id: 2, numero: 'OS-2', cliente: null, etapa: 'Imprimir', posto_id: 1, status: 'pendente', user_id: null, responsavel: null,
  inicio: `${D}T12:00`, fim: `${D}T13:00`, travas: [], estouro: false, prazo: null, passa_da_promessa: false };
const linha = { postos: [{ id: 1, nome: 'HP Latex', setor: 'Impressão', horas: 3, capacidade: 10, operadores: [] }], blocos: [bloco], sugestao: null,
  agrupamento: { programacao_id: 7, numero: 'OS-2', junto_de: 'OS-1', midia: 'Lona 440 g', posto_id: 1, posto: 'HP Latex', inicio: `${D}T09:15` } };
const props = { hoje: D, dias: { hoje: D, proximo: '2026-11-04' }, dia: 'hoje', turno: { inicio: '08:00', fim: '18:00' }, filtro: { q: '', so: 'todas', resp: '', setor: '' }, pode_mover: true, linha, eventos: [] };
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const abrir = (extra: object = {}) => render(<LinhaIndex {...({ ...props, ...extra } as any)} />);

describe('UC-PCLIN-01 · agrupar por mídia na Linha', () => {
  it('avisa e, ao agrupar, reprograma para o horário sugerido e mostra o resultado', async () => {
    json.mockResolvedValue({ user_id: null });
    abrir();
    expect(screen.getByText('Mesma mídia: dá para agrupar')).toBeTruthy();
    expect(screen.getByText(/OS-1 e OS-2 usam Lona 440 g na HP Latex/).textContent).toContain('(09:15)');

    fireEvent.click(screen.getByRole('button', { name: 'Agrupar' }));

    await waitFor(() => expect(screen.getByText('Agrupado por mídia')).toBeTruthy());
    expect(json).toHaveBeenCalledWith('PUT', '/producao/etapas/7/reprogramar', { posto_id: 1, inicio_previsto: `${D} 09:15:00` });
    expect(screen.queryByText('Mesma mídia: dá para agrupar')).toBeNull();
  });

  it('CONTROLE: sem producao.etapa.mover o aviso aparece sem o botão', () => {
    abrir({ pode_mover: false });
    expect(screen.getByText('Mesma mídia: dá para agrupar')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Agrupar' })).toBeNull();
  });

  it('CONTROLE: no próximo dia útil não há aviso, como no protótipo', () => {
    abrir({ dia: 'proximo' });
    expect(screen.queryByText('Mesma mídia: dá para agrupar')).toBeNull();
  });
});
