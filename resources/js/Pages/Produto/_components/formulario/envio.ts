// O `submit_type` dos botões do rodapé do formulário de produto (AcoesDoCadastro.tsx), o mesmo dos
// botões do Blade (create.blade.php / edit.blade.php). Fica fora do .tsx para o componente exportar só componente.

/** Os valores de `submit_type` que o ProductController@store e @update reconhecem. */
// `update_n_edit_opening_stock` é o do edit.blade.php, mandado pelo Editar (UC-PEDIT-35).
export type TipoDeEnvio =
  | 'submit_n_add_selling_prices'
  | 'save_n_add_another'
  | 'submit_n_add_opening_stock'
  | 'update_n_edit_opening_stock';

const TIPOS: readonly string[] = [
  'submit_n_add_selling_prices',
  'save_n_add_another',
  'submit_n_add_opening_stock',
  'update_n_edit_opening_stock',
];

/** O `submit_type` do botão que enviou o formulário (vazio = só salvar e voltar para a lista). */
export function tipoDeEnvio(evento: { nativeEvent: Event }): TipoDeEnvio | undefined {
  const botao = (evento.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null | undefined;
  const valor = botao?.value;
  return valor !== undefined && TIPOS.includes(valor) ? (valor as TipoDeEnvio) : undefined;
}
