import { describe, expect, it } from 'vitest';
import {
  adicionarDefeitos,
  camposDeReparo,
  dataParaServidor,
  defeitosParaTagify,
  reparoInicial,
  reparoValido,
} from '../../resources/js/Pages/Sells/_components/reparoVenda';

// UC-S04 (Sells/Create.casos.md) — o que a seção Reparo envia. O lado do servidor
// (campos gravados na venda) é tests/Feature/Sells/SellsRepairSubtipoContratoTest.php.
describe('UC-S04 · reparoVenda', () => {
  it('estado inicial traz o status padrão do Repair e nada mais', () => {
    expect(reparoInicial(7)).toMatchObject({ repair_status_id: 7, repair_serial_no: '', defeitos: [] });
    expect(reparoInicial(undefined).repair_status_id).toBeNull();
  });

  it('status é obrigatório, como o select required do POS Blade', () => {
    expect(reparoValido(reparoInicial(null))).toBe(false);
    expect(reparoValido(reparoInicial(3))).toBe(true);
  });

  it('data do input vai no formato que o servidor lê (uf_date)', () => {
    expect(dataParaServidor('2026-10-15T14:30')).toBe('15/10/2026 14:30');
    expect(dataParaServidor('')).toBeNull();
    expect(dataParaServidor('lixo')).toBeNull();
  });

  it('defeitos viram o JSON do Tagify que o show e o recibo decodificam', () => {
    expect(defeitosParaTagify(['tela', ' bateria ', 'tela', ''])).toBe('[{"value":"tela"},{"value":"bateria"}]');
    expect(JSON.parse(defeitosParaTagify(['tela']))[0].value).toBe('tela');
  });

  it('defeito digitado com vírgula vira vários, sem repetir', () => {
    expect(adicionarDefeitos(['tela'], 'bateria, tela ,  ')).toEqual(['tela', 'bateria']);
  });

  it('envio leva só os campos preenchidos, com os nomes do POS Blade', () => {
    const r = {
      ...reparoInicial(2),
      repair_brand_id: 5,
      repair_serial_no: '  SN-1 ',
      repair_due_date: '2026-10-15T14:30',
      defeitos: ['tela'],
    };
    expect(camposDeReparo(r)).toEqual({
      repair_status_id: 2,
      repair_brand_id: 5,
      repair_serial_no: 'SN-1',
      repair_due_date: '15/10/2026 14:30',
      repair_defects: '[{"value":"tela"}]',
    });
  });

  it('nada preenchido além do status não manda campos vazios', () => {
    expect(camposDeReparo(reparoInicial(2))).toEqual({ repair_status_id: 2 });
  });
});
