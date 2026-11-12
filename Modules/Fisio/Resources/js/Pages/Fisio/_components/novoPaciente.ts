// O que falta para cadastrar o paciente novo (Pacientes/Index · UC-FPI-21). Mesmas regras do
// PacientesController@store: contato existente escolhido, ou nome e celular do contato novo.
// A tela mostra esta frase em vez de só deixar o botão cinza (achado do smoke de 2026-11-11).

export function faltaParaCadastrar(origem: 'existente' | 'novo', contatoId: number | null, nome: string, telefone: string): string | null {
  if (origem === 'existente') {
    return contatoId === null ? 'Escolha o contato para continuar.' : null;
  }
  const faltam = [nome.trim() === '' ? 'o nome' : null, telefone.trim() === '' ? 'o celular' : null].filter((x): x is string => x !== null);

  return faltam.length === 0 ? null : `Falta ${faltam.join(' e ')} do contato.`;
}
