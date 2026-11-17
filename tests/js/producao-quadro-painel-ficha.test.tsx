// UC-PCOSF-08/09 · o painel lateral (Quadro e Linha do tempo) mostra a Ficha da OS aberta e pede as abas à tela (props `ficha_*`).
// Fonte: Modules/Producao/Resources/js/Pages/Producao/_components/FichaDaOs.casos.md (decisão [W] no #10850: a Ficha abre em painel).
//
// O que mede: a aba pedida vira um partial reload `async` só da prop dela; a resposta fica guardada com o id da OS, e
// a aba de uma OS não aparece na seguinte. CONTROLE: OS que não veio (outra empresa) diz "OS não encontrada" e não
// mostra Ficha; durante a troca de OS a Ficha anterior não aparece.
// O que NÃO prova: o servidor (Pest UC-PCOSF-07) nem o clique no cartão do navegador (smoke).
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

type Reload = { only: string[]; async?: boolean; onSuccess?: (page: { props: Record<string, unknown> }) => void };
const reloads: Reload[] = [];
vi.mock('@inertiajs/react', () => ({ router: { reload: (o: Reload) => { reloads.push(o); } } }));

import PainelDaFicha from '../../Modules/Producao/Resources/js/Pages/Producao/_components/PainelDaFicha';
import type { FichaBase } from '../../Modules/Producao/Resources/js/Pages/Producao/_components/FichaDaOs';
import { act } from 'react';

const fichaDe = (id: number): FichaBase => ({
  os: { id, numero: `OP ${id}`, produto: 'Banner', cliente: 'Cliente', venda: null, quantidade: '1', prazo: null, situacao: 'em_execucao', situacao_nome: 'Em execução' },
  etapas: [{ id: 1, nome: 'Impressão', status: 'iniciada', trilha: 0, junta: null, posto_id: 1, posto: 'Roland', setor: 'Impressão', user_id: null, responsavel: null, previsto_h: 2, apontado_h: 1 }],
  pode: { liberar: false, cancelar: false, custo: false },
});
const arte = { id: 3, versao: 2, arquivo: 'https://ex.test/arte-b.pdf', decisao: null, comentario: null, enviada_em: '2026-11-16 10:00', decidida_em: null, vigente: false };
const abrirArte = () => fireEvent.click(screen.getByRole('radio', { name: 'Arte e arquivos' }));

beforeEach(() => { reloads.length = 0; });

describe('UC-PCOSF-08 · painel da Ficha no Quadro', () => {
  it('abre a OS e pede a aba Arte só quando ela é aberta, como partial reload async', () => {
    render(<PainelDaFicha osId={7} ficha={fichaDe(7)} carregando={false} onFechar={vi.fn()} atualizar={['cartoes']} />);
    expect(screen.getByRole('dialog', { name: 'OP 7' })).toBeTruthy();
    expect(reloads).toHaveLength(0);
    abrirArte();
    expect(reloads.map((r) => [r.only, r.async])).toEqual([[['ficha_artes'], true]]);
    act(() => reloads[0].onSuccess?.({ props: { ficha_artes: [arte] } }));
    expect(screen.getByText('v2 · arte-b.pdf')).toBeTruthy();
  });

  it('a aba de uma OS não aparece na seguinte', () => {
    const { rerender } = render(<PainelDaFicha osId={7} ficha={fichaDe(7)} carregando={false} onFechar={vi.fn()} atualizar={['cartoes']} />);
    abrirArte();
    act(() => reloads[0].onSuccess?.({ props: { ficha_artes: [arte] } }));
    rerender(<PainelDaFicha osId={8} ficha={fichaDe(8)} carregando={false} onFechar={vi.fn()} atualizar={['cartoes']} />);
    abrirArte();
    expect(screen.queryByText('v2 · arte-b.pdf')).toBeNull();
    expect(reloads.map((r) => r.only)).toEqual([['ficha_artes'], ['ficha_artes']]);
  });

  it('controle: durante a troca a Ficha anterior não aparece; OS que não veio diz "OS não encontrada"', () => {
    // Fechou antes (`ficha` ficou nula) e clicou noutra OS: até a resposta, é "abrindo", não "não encontrada".
    const { rerender } = render(<PainelDaFicha osId={8} ficha={null} carregando onFechar={vi.fn()} atualizar={['cartoes']} />);
    expect(screen.getByRole('dialog', { name: 'Abrindo a OS…' })).toBeTruthy();
    // Reabrindo a mesma OS (F5 do painel, ação): a Ficha antiga não aparece enquanto a nova não chega.
    rerender(<PainelDaFicha osId={7} ficha={fichaDe(7)} carregando onFechar={vi.fn()} atualizar={['cartoes']} />);
    expect(screen.getByRole('dialog', { name: 'Abrindo a OS…' })).toBeTruthy();
    expect(screen.queryByText('Impressão', { selector: 'b' })).toBeNull();
    rerender(<PainelDaFicha osId={8} ficha={null} carregando={false} onFechar={vi.fn()} atualizar={['cartoes']} />);
    expect(screen.getByRole('dialog', { name: 'OS não encontrada' })).toBeTruthy();
    expect(screen.queryByRole('radio', { name: 'Arte e arquivos' })).toBeNull();
  });
});
