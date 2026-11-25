// Bloco do formulário de produto (Novo e Editar) na forma do Widget do protótipo
// (prototipo-ui/cowork/Wagner/produto-blade.jsx `Widget`): título em maiúsculas, nota em mono ao
// lado, linha embaixo do título. As classes .pb-widget vêm do bundle do Produto, que a lista já usa;
// o que o cadastro tem de diferente da lista (sem sombra) está em produto-cadastro.css.
import type { ReactNode } from 'react';

interface Props {
  titulo: ReactNode;
  /** A segunda parte do título, em mono (ex.: "validade, série, prateleira, campos personalizados"). */
  nota?: ReactNode;
  children: ReactNode;
  'data-testid'?: string;
}

export function SecaoCadastro({ titulo, nota, children, 'data-testid': testId }: Props) {
  return (
    <section className="pb-widget" data-testid={testId}>
      <h3>
        <span>{titulo}</span>
        {nota && <span className="n">{nota}</span>}
      </h3>
      <div className="pb-widget-b">{children}</div>
    </section>
  );
}
