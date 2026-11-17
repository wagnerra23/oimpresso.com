/**
 * Questionários de fibromialgia na ficha (etapa 07, P-03 · UC-FPS-41 em
 * Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md).
 *
 * Âncora: Wolfe et al. 2016 (critérios ACR 2016) e ADR 0437 §5 — o resultado dos critérios aparece
 * como informação para o profissional, nunca como diagnóstico. Os números vêm do servidor
 * (PontuacaoQuestionario); aqui só se confere o que a tela mostra.
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  useForm: () => ({ data: {}, setData: vi.fn(), post: vi.fn(), errors: {}, processing: false, transform: vi.fn(), reset: vi.fn(), clearErrors: vi.fn() }),
}));

import { HistoricoQuestionarios, type Instrumento, type RespostaQuestionario } from '../../Modules/Fisio/Resources/js/Pages/Fisio/_components/Questionarios';

const acr: Instrumento = {
  codigo: 'acr2016', nome: 'Critérios ACR 2016', tipo: 'acr2016', itens: [], faixa_item: [0, 3],
  pontuacao: 'WPI + SSS', faixa_escore: [0, 31], direcao: 'maior = pior', versao: 'acr2016-fsq-brasil-2020',
  texto_pendente: true, referencias: [],
};

const resposta = (detalhe: Record<string, number>): RespostaQuestionario => ({
  id: 1, instrumento: 'acr2016', versao: 'acr2016-fsq-brasil-2020', score: 12, detalhe, answered_at: '2026-11-11T10:00:00-03:00',
});

describe('HistoricoQuestionarios · ACR 2016', () => {
  it('UC-FPS-41 · mostra WPI e SSS e os critérios como informação, com a versão', () => {
    render(<HistoricoQuestionarios instrumentos={[acr]} respostas={[resposta({
      wpi: 7, sss: 5, regioes_com_dor: 5, criterio_wpi_sss: 1, dor_generalizada: 1, sintomas_3_meses: 1, criterios_acr2016_atendidos: 1,
    })]} />);

    expect(screen.getByText('WPI: 7')).toBeTruthy();
    expect(screen.getByText('SSS: 5')).toBeTruthy();
    const criterios = document.querySelector('[data-contract="fisio-acr2016-criterios"]')?.textContent ?? '';
    expect(criterios).toContain('Critérios ACR 2016: sim');
    expect(criterios).toContain('Informação para o profissional, não é diagnóstico.');
    expect(screen.getByText(/acr2016-fsq-brasil-2020/)).toBeTruthy();
    // As marcas 0/1 dos critérios não saem como número solto.
    expect(screen.queryByText(/criterio_wpi_sss/)).toBeNull();
  });

  it('UC-FPS-41 · sem os 3 meses, os critérios aparecem como não atendidos', () => {
    render(<HistoricoQuestionarios instrumentos={[acr]} respostas={[resposta({
      wpi: 7, sss: 5, regioes_com_dor: 5, criterio_wpi_sss: 1, dor_generalizada: 1, sintomas_3_meses: 0, criterios_acr2016_atendidos: 0,
    })]} />);

    const criterios = document.querySelector('[data-contract="fisio-acr2016-criterios"]')?.textContent ?? '';
    expect(criterios).toContain('Critérios ACR 2016: não');
    expect(criterios).toContain('Sintomas há 3 meses ou mais: não');
  });
});
