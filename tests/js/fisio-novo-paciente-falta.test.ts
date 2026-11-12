// UC-FPI-21 (Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.casos.md).
// O cadastro de paciente diz o que falta em vez de só deixar o botão cinza. Regras iguais às
// do PacientesController@store: contato existente escolhido, ou nome e celular do contato novo.
import { describe, expect, it } from 'vitest';
import { faltaParaCadastrar } from '../../Modules/Fisio/Resources/js/Pages/Fisio/_components/novoPaciente';

describe('UC-FPI-21 · o que falta para cadastrar o paciente', () => {
  it('contato existente: falta escolher; escolhido, nada falta', () => {
    expect(faltaParaCadastrar('existente', null, '', '')).toBe('Escolha o contato para continuar.');
    expect(faltaParaCadastrar('existente', 7, '', '')).toBeNull();
  });

  it('contato novo: nomeia o que falta, inclusive só o celular', () => {
    expect(faltaParaCadastrar('novo', null, '', '')).toBe('Falta o nome e o celular do contato.');
    expect(faltaParaCadastrar('novo', null, 'Ana', '')).toBe('Falta o celular do contato.');
    expect(faltaParaCadastrar('novo', null, '  ', '4899')).toBe('Falta o nome do contato.');
    expect(faltaParaCadastrar('novo', null, 'Ana', '   ')).toBe('Falta o celular do contato.');
    expect(faltaParaCadastrar('novo', null, 'Ana', '48999990000')).toBeNull();
  });
});
