// fixture hermético — alvo SEM a âncora parte-b (par RUIM do --check → exit 1)
export default function Foo() {
  return (
    <div>
      <section data-contract="parte-a">Parte A conteúdo</section>
      <section>Parte B SEM âncora data-contract</section>
    </div>
  );
}
