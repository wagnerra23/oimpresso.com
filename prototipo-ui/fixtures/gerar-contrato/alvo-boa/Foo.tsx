// fixture hermético — alvo COM as 2 âncoras (par BOM do --check)
export default function Foo() {
  return (
    <div>
      <section data-contract="parte-a">Parte A conteúdo</section>
      <section data-contract="parte-b">Parte B conteúdo</section>
    </div>
  );
}
