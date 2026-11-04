// UC-FAV-01 e UC-FAV-02 (Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md).
// O formulário em seções da avaliação compõe os 6 campos da Res. COFFITO 414/2012 que o
// RegistrosController valida; principal vazio deixa o campo vazio para o servidor recusar.
import { describe, expect, it } from 'vitest';
import { formVazio, montarCorpoAvaliacao, sinaisMarcados, SINAIS_ALERTA } from '../../Modules/Fisio/Resources/js/Pages/Fisio/_components/avaliacao';

const preenchido = () => ({
  ...formVazio(),
  queixa: 'Dor na lombar ao ficar sentada',
  historia: 'Início há 3 meses, sem trauma.',
  dorAgora: '6',
  medicamentos: 'Anti-inflamatório por 5 dias',
  exame: [{ teste: 'Flexão lombar (dedo-chão)', resultado: '18 cm', obs: 'dor no fim' }, { teste: '', resultado: 'ignorado', obs: '' }],
  diagnostico: 'Dor lombar inespecífica crônica',
  prognostico: 'Favorável',
  objetivo: 'Ficar sentada 2 h sem dor',
  sessoes: '8',
  plano: 'Programa domiciliar + 2 sessões por semana',
});

describe('montarCorpoAvaliacao', () => {
  it('UC-FAV-01 · compõe exatamente os 6 campos mínimos, com os complementos em linhas', () => {
    const corpo = montarCorpoAvaliacao(preenchido());

    expect(Object.keys(corpo).sort()).toEqual(['diagnostico_cinesiologico', 'exame_fisico', 'historia_doenca', 'objetivos', 'plano_tratamento', 'queixa_principal']);
    expect(corpo.historia_doenca).toBe('Início há 3 meses, sem trauma.\nDor agora (0–10): 6\nMedicamentos em uso: Anti-inflamatório por 5 dias');
    expect(corpo.exame_fisico).toBe('Flexão lombar (dedo-chão): 18 cm (dor no fim)\nTriagem de sinais de alerta: nenhum sinal marcado');
    expect(corpo.plano_tratamento).toBe('Programa domiciliar + 2 sessões por semana\nPrognóstico: Favorável\nNº provável de sessões: 8');
  });

  it('UC-FAV-01 · principal vazio deixa o campo vazio mesmo com complementos (o servidor recusa)', () => {
    const corpo = montarCorpoAvaliacao({ ...preenchido(), historia: '  ', plano: '', exame: [{ teste: '', resultado: '10', obs: 'x' }] });

    expect(corpo.historia_doenca).toBe('');
    expect(corpo.plano_tratamento).toBe('');
    expect(corpo.exame_fisico).toBe('');
  });

  it('UC-FAV-02 · sinal de alerta marcado entra no exame como texto, sem bloquear nada', () => {
    const f = preenchido();
    f.sinais = SINAIS_ALERTA.map((_, i) => i === 1);

    expect(sinaisMarcados(f)).toEqual(['Histórico de câncer']);
    expect(montarCorpoAvaliacao(f).exame_fisico).toContain('Triagem de sinais de alerta: SIM para Histórico de câncer');
  });
});
