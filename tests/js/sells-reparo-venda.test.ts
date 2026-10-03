import { describe, expect, it } from 'vitest';
import {
  adicionarDefeitos,
  camposDeReparo,
  checklistParaEnvio,
  itensDoChecklist,
  modelosFiltrados,
  tocarPonto,
  dataParaServidor,
  defeitosParaTagify,
  reparoInicial,
  reparoValido,
} from '../../resources/js/Pages/Sells/_components/reparoVenda';

// UC-S05 (Sells/Create.casos.md) — o que a seção Reparo envia. O lado do servidor
// (campos gravados na venda) é tests/Feature/Sells/SellsRepairSubtipoContratoTest.php.
describe('UC-S05 · reparoVenda', () => {
  it('estado inicial traz o status padrão do Repair e nada mais', () => {
    expect(reparoInicial(7)).toMatchObject({ repair_status_id: 7, repair_serial_no: '', defeitos: [] });
    expect(reparoInicial(undefined).repair_status_id).toBeNull();
  });

  it('status é obrigatório, como o select required do POS Blade', () => {
    expect(reparoValido(reparoInicial(null))).toBe(false);
    expect(reparoValido(reparoInicial(3))).toBe(true);
  });

  it('data do input vai no formato que o servidor lê (uf_date)', () => {
    expect(dataParaServidor('2026-10-15T14:30')).toBe('2026-10-15 14:30');
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
      repair_due_date: '2026-10-15 14:30',
      repair_defects: '[{"value":"tela"}]',
    });
  });

  it('nada preenchido além do status não manda campos vazios', () => {
    expect(camposDeReparo(reparoInicial(2))).toEqual({ repair_status_id: 2 });
  });
});

// UC-S06 — checklist pré-reparo, senha e padrão, e modelos filtrados por marca/aparelho.
describe('UC-S06 · checklist, senha/padrão e modelos', () => {
  const modelos = [
    { id: 1, name: 'A1', brand_id: 10, device_id: 20, checklist: ['Liga'] },
    { id: 2, name: 'A2', brand_id: 10, device_id: 21, checklist: [] },
    { id: 3, name: 'B1', brand_id: 11, device_id: 20, checklist: ['Tela', 'Liga'] },
  ];

  it('modelos seguem marca e aparelho; sem nenhum, todos', () => {
    expect(modelosFiltrados(modelos, null, null).map((m) => m.id)).toEqual([1, 2, 3]);
    expect(modelosFiltrados(modelos, null, 20).map((m) => m.id)).toEqual([1, 3]);
    expect(modelosFiltrados(modelos, 10, 20).map((m) => m.id)).toEqual([1]);
  });

  it('itens do checklist: padrão primeiro, depois o do modelo, sem repetir', () => {
    expect(itensDoChecklist(['Liga', 'Carrega'], modelos[2])).toEqual(['Liga', 'Carrega', 'Tela']);
    expect(itensDoChecklist(['Carrega'], undefined)).toEqual(['Carrega']);
  });

  it('checklist envia todos os itens exibidos, N/A onde não houve resposta (como o Blade)', () => {
    expect(checklistParaEnvio(['Liga', 'Tela'], { Tela: 'no' })).toEqual({ Liga: 'not_applicable', Tela: 'no' });
  });

  it('padrão é a sequência 1–9 tocada, sem repetir ponto', () => {
    expect(['1', '4', '7', '8', '4'].reduce((p, n) => tocarPonto(p, Number(n)), '')).toBe('1478');
    expect(tocarPonto('12', 0)).toBe('12');
  });

  it('envio leva senha, padrão e checklist só quando existem', () => {
    const r = { ...reparoInicial(2), repair_security_pwd: '1234', repair_security_pattern: '159', checklist: { Liga: 'yes' as const } };
    expect(camposDeReparo(r, ['Liga', 'Tela'])).toMatchObject({
      repair_security_pwd: '1234',
      repair_security_pattern: '159',
      repair_checklist: { Liga: 'yes', Tela: 'not_applicable' },
    });
    expect(camposDeReparo(reparoInicial(2), [])).toEqual({ repair_status_id: 2 });
  });
});
