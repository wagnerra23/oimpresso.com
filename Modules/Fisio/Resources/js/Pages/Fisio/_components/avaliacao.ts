// Monta o corpo da avaliação a partir do formulário em seções (protótipo Avaliacao.dc.html).
//
// O prontuário guarda a avaliação nos 6 campos mínimos da Res. COFFITO 414/2012, validados
// pelo RegistrosController (UC-FPS-03). O formulário em seções só ORGANIZA o que o profissional
// escreve: cada seção vira texto dentro de um desses campos, sem chave nova no `body` — assim o
// prontuário na tela, o PDF e o laudo continuam lendo os mesmos campos.
//
// Regra que protege a Res. 414: se o texto principal de um campo estiver vazio, o campo vai
// vazio, mesmo com os complementos preenchidos. O servidor então recusa (422), como recusaria
// no formulário simples.

export const SINAIS_ALERTA = [
  'Febre ou perda de peso sem explicação',
  'Histórico de câncer',
  'Trauma importante recente',
  'Dor noturna que não alivia',
  'Dormência na região genital',
  'Alteração para urinar ou evacuar',
  'Fraqueza progressiva nas pernas',
] as const;

export interface LinhaExame { teste: string; resultado: string; obs: string }

export interface FormAvaliacao {
  queixa: string;
  historia: string;
  dorAgora: string;
  piorDor: string;
  pioraMelhora: string;
  tratamentos: string;
  medicamentos: string;
  atividade: string;
  trabalho: string;
  sono: string;
  antecedentes: string;
  exame: LinhaExame[];
  sinais: boolean[];
  diagnostico: string;
  prognostico: string;
  objetivo: string;
  sessoes: string;
  frequencia: string;
  plano: string;
}

export const formVazio = (): FormAvaliacao => ({
  queixa: '', historia: '', dorAgora: '', piorDor: '', pioraMelhora: '', tratamentos: '', medicamentos: '',
  atividade: '', trabalho: '', sono: '', antecedentes: '', exame: [{ teste: '', resultado: '', obs: '' }],
  sinais: SINAIS_ALERTA.map(() => false), diagnostico: '', prognostico: '', objetivo: '', sessoes: '', frequencia: '', plano: '',
});

const t = (v: string) => v.trim();

/** Texto principal seguido dos complementos preenchidos, um por linha. Principal vazio = campo vazio. */
function comComplementos(principal: string, complementos: [string, string][]): string {
  if (!t(principal)) return '';
  const extras = complementos.filter(([, v]) => t(v)).map(([rotulo, v]) => `${rotulo}: ${t(v)}`);
  return [t(principal), ...extras].join('\n');
}

export function sinaisMarcados(f: FormAvaliacao): string[] {
  return SINAIS_ALERTA.filter((_, i) => f.sinais[i]);
}

export function montarCorpoAvaliacao(f: FormAvaliacao): Record<string, string> {
  const exame = f.exame
    .filter((l) => t(l.teste))
    .map((l) => `${t(l.teste)}: ${t(l.resultado) || '—'}${t(l.obs) ? ` (${t(l.obs)})` : ''}`);
  const marcados = sinaisMarcados(f);
  const triagem = marcados.length
    ? `Triagem de sinais de alerta: SIM para ${marcados.join('; ')}`
    : 'Triagem de sinais de alerta: nenhum sinal marcado';

  return {
    queixa_principal: t(f.queixa),
    historia_doenca: comComplementos(f.historia, [
      ['Dor agora (0–10)', f.dorAgora], ['Pior dor na semana (0–10)', f.piorDor], ['O que piora / o que melhora', f.pioraMelhora],
      ['Tratamentos anteriores', f.tratamentos], ['Medicamentos em uso', f.medicamentos], ['Atividade física', f.atividade],
      ['Trabalho', f.trabalho], ['Sono', f.sono], ['Antecedentes pessoais e familiares', f.antecedentes],
    ]),
    exame_fisico: exame.length ? [...exame, triagem].join('\n') : '',
    diagnostico_cinesiologico: t(f.diagnostico),
    objetivos: t(f.objetivo),
    plano_tratamento: comComplementos(f.plano, [
      ['Prognóstico', f.prognostico], ['Nº provável de sessões', f.sessoes], ['Frequência', f.frequencia],
    ]),
  };
}
