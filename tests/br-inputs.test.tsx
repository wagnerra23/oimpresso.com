// @covers-us US-CRM-073
// BR inputs canon (ui/document-input · ui/phone-input · ui/numeric-input-ptbr) —
// comportamento de máscara/parse + a11y runtime (axe, idioma de tests/a11y-primitives.test.tsx).
//
// POR QUE: estes componentes substituem hand-wiring de máscara repetido (drawer
// Cliente Wave C-FE) e o NumericInputPtBR nasceu de bug real de produção
// (R$ digitado virando outro valor por locale do navegador — 2026-05-27).
// Este teste TRAVA os contratos: máscara progressiva, digits sem máscara pra
// persistir, valid mod 11 (true/false/null), round-trip focus→edit→blur do numérico.
//
// Validação client é UX-only — backend Rule\BR\CpfCnpj continua a verdade (ADR 0093).

import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup, fireEvent, screen, act } from '@testing-library/react';
import { createRef, useState } from 'react';
import axe from 'axe-core';

import { DocumentInput } from '@/Components/ui/document-input';
import { PhoneInput } from '@/Components/ui/phone-input';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Label } from '@/Components/ui/label';

afterEach(cleanup);

// Harness controlado — espelha como uma tela real consome (useState + onValueChange).
function DocumentHarness({ tipo }: { tipo?: 'cpf' | 'cnpj' | 'auto' }) {
  const [doc, setDoc] = useState('');
  const [last, setLast] = useState<{ digits: string; valid: boolean | null }>({ digits: '', valid: null });
  return (
    <div>
      <Label htmlFor="doc">CPF/CNPJ</Label>
      <DocumentInput id="doc" tipo={tipo} value={doc} onValueChange={(v) => { setDoc(v.digits); setLast(v); }} />
      <output data-testid="digits">{last.digits}</output>
      <output data-testid="valid">{String(last.valid)}</output>
    </div>
  );
}

function PhoneHarness() {
  const [tel, setTel] = useState('');
  const [digits, setDigits] = useState('');
  return (
    <div>
      <Label htmlFor="tel">Telefone</Label>
      <PhoneInput id="tel" value={tel} onValueChange={(v) => { setTel(v.digits); setDigits(v.digits); }} />
      <output data-testid="digits">{digits}</output>
    </div>
  );
}

function NumericHarness({ inicial = 0, precision }: { inicial?: number; precision?: number }) {
  const [n, setN] = useState(inicial);
  return (
    <div>
      <Label htmlFor="num">Valor</Label>
      <NumericInputPtBR id="num" value={n} onChange={setN} precision={precision} />
      <output data-testid="number">{n}</output>
    </div>
  );
}

describe('DocumentInput (CPF/CNPJ)', () => {
  it('mascara CPF progressivo e valida mod 11 (CPF válido)', () => {
    render(<DocumentHarness />);
    const input = screen.getByLabelText('CPF/CNPJ') as HTMLInputElement;

    // CPF de teste público com DV válido — não pessoa real // pii-allowlist
    fireEvent.change(input, { target: { value: '52998224725' } });
    expect(input.value).toBe('529.982.247-25'); // pii-allowlist (mesmo número de teste acima)
    expect(screen.getByTestId('digits').textContent).toBe('52998224725');
    expect(screen.getByTestId('valid').textContent).toBe('true');
    expect(input.getAttribute('aria-invalid')).toBeNull();
  });

  it('CPF completo ERRADO acende aria-invalid; incompleto NÃO (valid null)', () => {
    render(<DocumentHarness />);
    const input = screen.getByLabelText('CPF/CNPJ') as HTMLInputElement;

    // Incompleto — sem erro enquanto digita.
    fireEvent.change(input, { target: { value: '529982' } });
    expect(screen.getByTestId('valid').textContent).toBe('null');
    expect(input.getAttribute('aria-invalid')).toBeNull();

    // Completo e errado (sequência repetida é inválida por convenção).
    fireEvent.change(input, { target: { value: '11111111111' } });
    expect(screen.getByTestId('valid').textContent).toBe('false');
    expect(input.getAttribute('aria-invalid')).toBe('true');
  });

  it('tipo auto vira CNPJ acima de 11 dígitos (máscara NN.NNN.NNN/NNNN-NN)', () => {
    render(<DocumentHarness />);
    const input = screen.getByLabelText('CPF/CNPJ') as HTMLInputElement;

    // CNPJ de teste público com DV válido — não empresa real // pii-allowlist
    fireEvent.change(input, { target: { value: '11222333000181' } });
    expect(input.value).toBe('11.222.333/0001-81'); // pii-allowlist (mesmo número de teste acima)
    expect(screen.getByTestId('valid').textContent).toBe('true');
  });

  it('tipo=cpf trunca em 11 dígitos (não vira CNPJ)', () => {
    render(<DocumentHarness tipo="cpf" />);
    const input = screen.getByLabelText('CPF/CNPJ') as HTMLInputElement;

    fireEvent.change(input, { target: { value: '52998224725999' } });
    expect(screen.getByTestId('digits').textContent).toBe('52998224725');
    expect(input.value).toBe('529.982.247-25'); // pii-allowlist (número de teste público, DV válido)
  });
});

describe('PhoneInput (telefone BR)', () => {
  it('celular 11d usa pattern 9 separado (canon Cowork aprovado Wagner 2026-05-21)', () => {
    render(<PhoneHarness />);
    const input = screen.getByLabelText('Telefone') as HTMLInputElement;

    fireEvent.change(input, { target: { value: '11988887777' } });
    expect(input.value).toBe('(11) 9 8888-7777');
    expect(screen.getByTestId('digits').textContent).toBe('11988887777');
  });

  it('fixo 10d formata (00) 0000-0000 e progressivo não quebra', () => {
    render(<PhoneHarness />);
    const input = screen.getByLabelText('Telefone') as HTMLInputElement;

    fireEvent.change(input, { target: { value: '11' } });
    expect(input.value).toBe('(11');

    fireEvent.change(input, { target: { value: '1133334444' } });
    expect(input.value).toBe('(11) 3333-4444');
  });
});

describe('NumericInputPtBR (promovido de Sells — contrato do bug R$ Larissa)', () => {
  it('digitar com vírgula emite número canônico e blur reformata pt-BR', () => {
    render(<NumericHarness />);
    const input = screen.getByLabelText('Valor') as HTMLInputElement;

    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: '1234,5' } });
    expect(screen.getByTestId('number').textContent).toBe('1234.5');

    fireEvent.blur(input);
    expect(input.value).toBe('1.234,50');
  });

  it('entrada inválida no blur volta pro último valor canônico (não NaN)', () => {
    render(<NumericHarness />);
    const input = screen.getByLabelText('Valor') as HTMLInputElement;

    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: '25' } });
    fireEvent.blur(input);
    expect(input.value).toBe('25,00');

    fireEvent.focus(input);
    fireEvent.change(input, { target: { value: ',,,' } });
    fireEvent.blur(input);
    expect(input.value).toBe('25,00');
    expect(screen.getByTestId('number').textContent).toBe('25');
  });
});

// Selecionar tudo ao entrar no campo — medido em produção em 2026-10-13 (janela de estoque
// inicial): clicar num campo com "10,00" e digitar "4" dava "410", porque a seleção feita no foco
// era desfeita quando o texto trocava de "10,00" para "10". O jsdom reproduz a mesma perda: ao
// receber um `value` novo, o cursor vai para o fim e a seleção some.
//
// O foco aqui é o REAL (`input.focus()`), não `fireEvent.focus`: o componente confere
// `document.activeElement`, e `fireEvent.focus` não move o foco do documento.
describe('NumericInputPtBR — entrar no campo seleciona tudo (digitar troca o valor, não soma)', () => {
  const selecao = (el: HTMLInputElement) => [el.selectionStart, el.selectionEnd];
  const campo = () => screen.getByLabelText('Valor') as HTMLInputElement;

  it('entrar pelo teclado (Tab) num campo com valor seleciona o valor inteiro', () => {
    render(<NumericHarness inicial={10} />);
    const input = campo();
    expect(input.value).toBe('10,00');

    act(() => input.focus());

    expect(input.value).toBe('10');
    expect(selecao(input)).toEqual([0, 2]);
  });

  it('clicar num campo com valor seleciona o valor inteiro, mesmo com o cursor posto pelo clique', () => {
    render(<NumericHarness inicial={10} />);
    const input = campo();

    // A ordem medida no navegador: mousedown → focus → (cursor no ponto do clique) → mouseup.
    fireEvent.mouseDown(input);
    act(() => input.focus());
    input.setSelectionRange(0, 0);
    fireEvent.mouseUp(input);

    expect(input.value).toBe('10');
    expect(selecao(input)).toEqual([0, 2]);
  });

  it('segundo clique, com o campo já focado, NÃO reseleciona — dá para pôr o cursor num dígito', () => {
    render(<NumericHarness inicial={1234.5} />);
    const input = campo();
    fireEvent.mouseDown(input);
    act(() => input.focus());
    fireEvent.mouseUp(input);
    expect(selecao(input)).toEqual([0, input.value.length]);

    fireEvent.mouseDown(input);
    input.setSelectionRange(2, 2);
    fireEvent.mouseUp(input);

    expect(selecao(input)).toEqual([2, 2]);
  });

  it('valor com mais casas: o texto de edição inteiro fica selecionado', () => {
    render(<NumericHarness inicial={12.125} precision={4} />);
    const input = campo();
    expect(input.value).toBe('12,1250');

    act(() => input.focus());

    expect(input.value).toBe('12,125');
    expect(selecao(input)).toEqual([0, 6]);
  });

  it('quando o texto não muda ao focar (sem casas decimais), a seleção também cobre tudo', () => {
    render(<NumericHarness inicial={5} precision={0} />);
    const input = campo();
    expect(input.value).toBe('5');

    act(() => input.focus());

    expect(input.value).toBe('5');
    expect(selecao(input)).toEqual([0, 1]);
  });

  it('valor zero continua abrindo vazio para digitar', () => {
    render(<NumericHarness />);
    const input = campo();
    expect(input.value).toBe('0,00');

    act(() => input.focus());

    expect(input.value).toBe('');
  });

  it('depois de digitar, a seleção não volta sozinha (o cursor fica onde a digitação deixou)', () => {
    render(<NumericHarness inicial={10} />);
    const input = campo();
    act(() => input.focus());

    fireEvent.change(input, { target: { value: '45' } });
    input.setSelectionRange(2, 2);
    fireEvent.change(input, { target: { value: '456' } });

    expect(input.value).toBe('456');
    expect(selecao(input)).toEqual([3, 3]);
    expect(screen.getByTestId('number').textContent).toBe('456');
  });

  it('quem usa o campo continua recebendo a ref e os próprios eventos de mouse', () => {
    const ref = createRef<HTMLInputElement>();
    const vistos: string[] = [];
    render(
      <div>
        <Label htmlFor="num">Valor</Label>
        <NumericInputPtBR
          id="num"
          ref={ref}
          value={7}
          onChange={() => {}}
          onMouseDown={() => vistos.push('down')}
          onMouseUp={() => vistos.push('up')}
        />
      </div>,
    );
    const input = campo();
    expect(ref.current).toBe(input);

    fireEvent.mouseDown(input);
    fireEvent.mouseUp(input);

    expect(vistos).toEqual(['down', 'up']);
  });
});

describe('a11y runtime (axe — serious/critical = 0, idioma a11y-primitives)', () => {
  async function expectNoSeriousViolations(container: HTMLElement) {
    const results = await axe.run(container, {
      resultTypes: ['violations'],
    });
    const relevant = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(relevant.map((v) => `${v.id}: ${v.help}`)).toEqual([]);
  }

  it('DocumentInput com Label associado passa axe', async () => {
    const { container } = render(<DocumentHarness />);
    await expectNoSeriousViolations(container);
  });

  it('PhoneInput com Label associado passa axe', async () => {
    const { container } = render(<PhoneHarness />);
    await expectNoSeriousViolations(container);
  });

  it('NumericInputPtBR com Label associado passa axe', async () => {
    const { container } = render(<NumericHarness />);
    await expectNoSeriousViolations(container);
  });
});
