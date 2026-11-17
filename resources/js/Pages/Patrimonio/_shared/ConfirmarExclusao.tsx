// Confirmação de exclusão do Patrimônio — `AlertDialog` do DS no lugar do `window.confirm`.
//
// Desenho: `ExcluirModal` do protótipo (`prototipo-ui/cowork/Wagner/patrimonio-forms.jsx:275`) —
// título com o código, uma linha dizendo o que sai e o que fica, "Cancelar" + botão de perigo.
//
// POR QUE `fetch` E NÃO `router.delete`: os `destroy` do módulo só respondem dentro de
// `if (request()->ajax())` e devolvem `{success, msg}` em JSON (`AssetController::destroy`).
// O Inertia manda `X-Requested-With` em toda visita, então o `router.delete` caía nesse ramo e
// recebia JSON — resposta não-Inertia, que o Inertia mostra num diálogo de resposta crua e
// sem atualizar a lista. É o mesmo contrato que as telas irmãs já tratam assim
// (`Pages/Configuracoes/Impostos/Index.tsx:59`, `enviar()`).
//
// FALHA VISÍVEL: `success: false`, HTTP fora de 2xx ou rede caída deixam o diálogo ABERTO com a
// mensagem em `role="alert"` e o botão vira "Tentar de novo". Nada some calado.

import { useState } from 'react';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { excluirRegistro } from './excluir';

interface Props {
  /** O que vai ser excluído; `null` fecha o diálogo. */
  alvo: { titulo: string; descricao: string; url: string } | null;
  onFechar: () => void;
  /** Chamado só quando o servidor confirmou (`success: true`). */
  onExcluido: (msg?: string) => void;
}

export default function ConfirmarExclusao({ alvo, onFechar, onExcluido }: Props) {
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const fechar = () => {
    if (enviando) return;
    setErro(null);
    onFechar();
  };

  const confirmar = async () => {
    if (!alvo) return;
    setEnviando(true);
    setErro(null);
    const r = await excluirRegistro(alvo.url);
    setEnviando(false);
    if (r.success) {
      onExcluido(r.msg);
      return;
    }
    setErro(r.msg || 'O servidor não concluiu a exclusão.');
  };

  return (
    <AlertDialog open={alvo !== null} onOpenChange={(aberto) => !aberto && fechar()}>
      <AlertDialogContent data-contract="confirmar-exclusao">
        <AlertDialogHeader>
          <AlertDialogTitle>{alvo?.titulo}</AlertDialogTitle>
          <AlertDialogDescription>{alvo?.descricao}</AlertDialogDescription>
        </AlertDialogHeader>
        {erro ? (
          <p role="alert" data-contract="exclusao-erro" className="text-sm text-destructive">
            Não consegui excluir. {erro}
          </p>
        ) : null}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={enviando}>Cancelar</AlertDialogCancel>
          <Button variant="destructive" onClick={confirmar} disabled={enviando}>
            {enviando ? 'Excluindo…' : erro ? 'Tentar de novo' : 'Excluir'}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
