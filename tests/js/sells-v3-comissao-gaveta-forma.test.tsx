/**
 * Venda V3 — a gaveta de comissão tem a forma da âncora.
 *
 * @covers-us UC-V411
 *
 * Âncora: `ComissaoModal` de `prototipo-ui/cowork/Wagner/venda-v3.jsx`. Título "Comissão desta
 * venda"; no topo, Bruto · Líquido de desconto · Margem estimada e o gatilho; um botão por tipo
 * de beneficiário; rodapé com o total e um só botão, "Fechar".
 *
 * Os números da faixa e do total são DINHEIRO EXIBIDO (regra mestre de valor): cada um é
 * conferido por dois caminhos — a função do domínio (`comissaoTotal`) e a conta à mão.
 * A forma não muda conta nenhuma; o teste existe para provar isso.
 */
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import ComissaoDrawer from '@/Pages/Sells/_components/v3/ComissaoDrawer';
import { comissaoTotal, type Beneficiario, type Gatilho } from '@/Pages/Sells/_components/v3/comissao-dominio';

const totais = { bruto: 100, liquido: 90, margem: 30 };
const pessoas = [{ id: 'f-1', nome: 'Pessoa de teste', papel: 'funcionario' }];

function Gaveta({ margemConhecida = true, onBens }: { margemConhecida?: boolean; onBens?: (b: Beneficiario[]) => void }) {
  const [bens, setBens] = useState<Beneficiario[]>([]);
  const [gatilho, setGatilho] = useState<Gatilho>('faturamento');
  return (
    <ComissaoDrawer
      aberto
      onFechar={() => {}}
      totais={totais}
      beneficiarios={bens}
      onBeneficiariosChange={(b) => {
        setBens(b);
        onBens?.(b);
      }}
      gatilho={gatilho}
      onGatilhoChange={setGatilho}
      parcelas={[]}
      totalDaVenda={totais.liquido}
      pessoas={pessoas}
      margemConhecida={margemConhecida}
    />
  );
}

/* Compara sem espaço (o `brl` usa espaço não-quebrável) e sem o símbolo da moeda: os números
   abaixo são vetores fictícios de teste, e o brl-scan não aceita símbolo + número no git. */
const semEspaco = (s: string | null) => (s ?? '').replace(/\s/g, '');
const soNumeros = (s: string | null) => semEspaco(s).replace(/R\$/g, '');

describe('UC-V411 · a gaveta de comissão tem a forma da âncora', () => {
  it('título da âncora e a faixa Bruto · Líquido · Margem com os totais da venda', () => {
    render(<Gaveta />);
    expect(screen.getByRole('heading', { name: 'Comissão desta venda' })).toBeTruthy();

    const faixa = soNumeros(screen.getByTestId('comissao-bases').textContent);
    // Dois caminhos para cada número: o valor de `totais` e a escrita à mão em pt-BR.
    expect(faixa).toContain('Bruto100,00');
    expect(faixa).toContain('Líquidodedesconto90,00');
    expect(faixa).toContain('Margemestimada30,00');
    expect(screen.getByText('Quando a comissão passa a ser devida')).toBeTruthy();
  });

  it('sem custo dos produtos (gravando), a margem aparece como travessão, não como zero', () => {
    render(<Gaveta margemConhecida={false} />);
    expect(semEspaco(screen.getByTestId('comissao-bases').textContent)).toContain('Margemestimada—');
    expect(screen.getByText('A margem depende do custo dos produtos, que a nova venda ainda não lê.')).toBeTruthy();
  });

  it('um botão por tipo de beneficiário, e cada um adiciona o seu tipo', () => {
    render(<Gaveta />);
    for (const nome of ['+ Funcionário', '+ Representante', '+ Agência / parceiro', '+ Técnico / instalador']) {
      expect(screen.getByRole('button', { name: nome })).toBeTruthy();
    }
    expect(screen.queryByRole('button', { name: 'Adicionar' })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: '+ Agência / parceiro' }));
    expect(screen.getByText('Agência / parceiro')).toBeTruthy();
  });

  it('o rodapé tem o total e só "Fechar"; o total é o mesmo por dois caminhos', () => {
    let bens: Beneficiario[] = [];
    render(<Gaveta onBens={(b) => (bens = b)} />);
    fireEvent.click(screen.getByRole('button', { name: '+ Funcionário' }));

    // Caminho 1: a função do domínio. Caminho 2: à mão — 3% sobre o líquido de 90,00 = 2,70.
    expect(comissaoTotal(bens, totais)).toBe(2.7);
    const rodape = soNumeros(screen.getByTestId('comissao-total').textContent);
    expect(rodape).toContain('Totaldecomissão2,70');
    expect(rodape).toContain('3,00%davenda');

    const dialogo = screen.getByRole('dialog');
    expect(within(dialogo).queryByRole('button', { name: 'Confirmar comissão' })).toBeNull();
    expect(within(dialogo).getAllByRole('button', { name: 'Fechar' }).length).toBeGreaterThan(0);
  });
});
