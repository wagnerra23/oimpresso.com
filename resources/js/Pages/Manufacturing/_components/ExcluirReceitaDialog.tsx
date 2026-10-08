// Fabricação — confirmação de "Excluir receita" (US-MANU-006 etapa 3, handoff §5 regra 6).
//
// Copy VERBATIM do protótipo (`prototipo-ui/cowork/Wagner/manufacturing-page.jsx`, modal "Excluir
// receita" `width={400}`): diz o que se perde (a ficha + N ingredientes) e que as ordens já lançadas
// continuam com o custo registrado — verdade medida: o detalhe da ordem lê `purchase_lines`, não a
// receita (`ProductionService::detalheOrdem`).
//
// Grava pelo `DELETE /manufacturing/recipe/{id}` de sempre (`RecipeController::destroy`, que responde
// JSON `{success, msg}` e só acha receita da empresa da sessão — UC-RECIPE-19). Quem decide se o
// botão aparece é a tela: o servidor exige `manufacturing.add_recipe`.
import { useState } from 'react';
import { AlertTriangle } from 'lucide-react';
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
import { Inline } from '@/Components/layout/inline';

export interface ReceitaAExcluir {
  id: number;
  nome: string;
  nIngredientes: number;
}

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

export default function ExcluirReceitaDialog({
  receita,
  onFechar,
  onExcluida,
}: {
  receita: ReceitaAExcluir | null;
  onFechar: () => void;
  onExcluida: () => void;
}) {
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  async function excluir() {
    if (!receita || enviando) return;
    setEnviando(true);
    setErro(null);
    try {
      const r = await fetch(`/manufacturing/recipe/${receita.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
      });
      const corpo = r.ok ? ((await r.json()) as { success?: number | boolean; msg?: string }) : null;
      if (corpo?.success) {
        onExcluida();
        return;
      }
      setErro(corpo?.msg ?? `Não foi possível excluir a receita (HTTP ${r.status}).`);
    } catch {
      setErro('Não foi possível excluir a receita. Verifique a conexão e tente de novo.');
    } finally {
      setEnviando(false);
    }
  }

  return (
    <AlertDialog
      open={receita !== null}
      onOpenChange={(aberto) => {
        if (!aberto && !enviando) {
          setErro(null);
          onFechar();
        }
      }}
    >
      <AlertDialogContent data-contract="confirm-excluir-receita" className="sm:max-w-[400px]">
        <AlertDialogHeader>
          <AlertDialogTitle>Excluir receita</AlertDialogTitle>
          <AlertDialogDescription asChild>
            <div className="space-y-2 text-sm">
              <p>
                Excluir <b>{receita?.nome}</b> apaga a ficha técnica e os {receita?.nIngredientes} ingredientes. Ordens
                de produção já lançadas continuam com o custo registrado.
              </p>
              <p>Não dá pra desfazer.</p>
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        {erro && (
          <Inline asChild gap={1} align="center">
            <p role="alert" className="text-[12px] font-medium text-[var(--color-destructive-fg)]">
              <AlertTriangle className="h-3.5 w-3.5" />
              {erro}
            </p>
          </Inline>
        )}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={enviando}>Cancelar</AlertDialogCancel>
          <Button type="button" variant="destructive" disabled={enviando} onClick={excluir}>
            {enviando ? 'Excluindo…' : 'Excluir receita'}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
