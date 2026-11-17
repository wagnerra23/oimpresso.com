// UC-PCOSF-05 · o conteúdo da Ficha da OS (FichaDaOs) pede cada aba uma vez, quando ela é aberta e ainda não chegou.
// Fonte: Modules/Producao/Resources/js/Pages/Producao/Os/Show.casos.md. O componente é o mesmo da página e do painel
// lateral do Quadro (decisão [W] no #10850); o painel pede as abas sob demanda, por isso o `carregar`.
//
// O que mede: abrir a aba Arte sem as versões chama `carregar('artes')` uma vez e mostra o esqueleto; com as versões
// em mãos mostra a lista e não pede de novo. CONTROLE: a aba Etapas não pede nada.
// O que NÃO prova: a consulta no servidor (Pest UC-PCOSF-05/07) nem o painel montado no Quadro (smoke).
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import FichaDaOs, { type FichaBase } from '../../Modules/Producao/Resources/js/Pages/Producao/_components/FichaDaOs';

const ficha: FichaBase = {
  os: { id: 7, numero: 'OP 7', produto: 'Banner', cliente: 'Cliente', venda: null, quantidade: '1', prazo: null, situacao: 'em_execucao', situacao_nome: 'Em execução' },
  etapas: [{ id: 1, nome: 'Impressão', status: 'iniciada', trilha: 0, junta: null, posto_id: 1, posto: 'Roland', setor: 'Impressão', user_id: null, responsavel: null, previsto_h: 2, apontado_h: 1 }],
  pode: { liberar: false, cancelar: false, custo: false },
};
const abrirArte = () => fireEvent.click(screen.getByRole('radio', { name: 'Arte e arquivos' }));

describe('UC-PCOSF-05 · FichaDaOs pede a aba sob demanda', () => {
  it('controle: na aba Etapas nada é pedido', () => {
    const carregar = vi.fn();
    render(<FichaDaOs ficha={ficha} abas={{}} carregar={carregar} recarregar={vi.fn()} />);
    expect(screen.getByText('Impressão', { selector: 'b' })).toBeTruthy();
    expect(carregar).not.toHaveBeenCalled();
  });

  it('abrir Arte sem as versões pede `artes` uma vez só', () => {
    const carregar = vi.fn();
    const { rerender } = render(<FichaDaOs ficha={ficha} abas={{}} carregar={carregar} recarregar={vi.fn()} />);
    abrirArte();
    rerender(<FichaDaOs ficha={ficha} abas={{}} carregar={carregar} recarregar={vi.fn()} />);
    expect(carregar.mock.calls).toEqual([['artes']]);
  });

  it('com as versões em mãos mostra a lista e não pede de novo', () => {
    const carregar = vi.fn();
    render(<FichaDaOs ficha={ficha} carregar={carregar} recarregar={vi.fn()}
      abas={{ artes: [{ id: 3, versao: 2, arquivo: 'https://ex.test/arte-b.pdf', decisao: null, comentario: null, enviada_em: '2026-11-16 10:00', decidida_em: null, vigente: false }] }} />);
    abrirArte();
    expect(screen.getByText('v2 · arte-b.pdf')).toBeTruthy();
    expect(carregar).not.toHaveBeenCalled();
  });
});
