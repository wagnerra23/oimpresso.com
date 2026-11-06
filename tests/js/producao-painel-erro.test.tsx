// UC-PCPNL-01 · Painel da Produção: a carga deferida que falha vira estado de erro, não skeleton eterno.
// Fonte: estado "erro" de prototipo-ui/cowork/Wagner/producao-cv-page.jsx ("Não foi possível carregar a
// produção" + "Tentar de novo") e Modules/Producao/Resources/js/Pages/Producao/Painel/Index.casos.md.
//
// O que mede: o hook `useFalhaDoDeferido` que o Painel usa, com os eventos reais do Inertia
// (`inertia:httpException` e `inertia:networkError` no document, cancelable como o core os dispara).
// CONTROLE: com a prop já carregada, o mesmo evento não vira erro e o modal do Inertia não é cancelado.
// O que NÃO prova: o Painel inteiro montado (o `<Deferred>` precisa do contexto de página do Inertia);
// o encaixe do hook no Painel se confere no smoke pós-deploy.
import { act, fireEvent, render, screen } from '@testing-library/react';
import { router } from '@inertiajs/react';
import { describe, expect, it, vi } from 'vitest';
import { useFalhaDoDeferido } from '../../Modules/Producao/Resources/js/Pages/Producao/_lib/falhaDoDeferido';

function Harness({ ausente }: { ausente: boolean }) {
  const { falhou, tentarDeNovo } = useFalhaDoDeferido('painel', ausente);
  return falhou ? <button onClick={tentarDeNovo}>Tentar de novo</button> : <p>carregando</p>;
}

const disparar = (nome: string) => {
  const ev = new CustomEvent(`inertia:${nome}`, { cancelable: true, detail: nome === 'httpException' ? { response: { status: 500 } } : { error: new Error('rede') } });
  act(() => { document.dispatchEvent(ev); });
  return ev;
};

describe('UC-PCPNL-01 · erro ao carregar o Painel', () => {
  it('resposta 500 enquanto a prop não chegou mostra o erro e cancela o modal do Inertia', () => {
    render(<Harness ausente />);
    expect(screen.getByText('carregando')).toBeTruthy();
    const ev = disparar('httpException');
    expect(screen.getByRole('button', { name: 'Tentar de novo' })).toBeTruthy();
    expect(ev.defaultPrevented).toBe(true);
  });

  it('queda de rede também vira erro', () => {
    render(<Harness ausente />);
    disparar('networkError');
    expect(screen.getByRole('button', { name: 'Tentar de novo' })).toBeTruthy();
  });

  it('CONTROLE: com a prop carregada, o mesmo 500 não vira erro e o modal segue', () => {
    render(<Harness ausente={false} />);
    const ev = disparar('httpException');
    expect(screen.getByText('carregando')).toBeTruthy();
    expect(ev.defaultPrevented).toBe(false);
  });

  it('"Tentar de novo" volta a carregar só a prop do Painel', () => {
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined as never);
    render(<Harness ausente />);
    disparar('httpException');
    fireEvent.click(screen.getByRole('button', { name: 'Tentar de novo' }));
    expect(reload).toHaveBeenCalledWith({ only: ['painel'] });
    expect(screen.getByText('carregando')).toBeTruthy();
  });
});
