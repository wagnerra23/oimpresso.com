// CONN-O7 · quem está usando cada credencial (UC-CONN-21).
// O servidor manda, por client, os acessos abertos (tokens não revogados e não vencidos) de
// usuários do MESMO negócio — top 5 + contagem do resto. Uso por outro negócio nunca chega aqui.
// "Último uso" é a última atividade registrada no token (o mesmo sinal da coluna Tokens 24 h).

import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import EmptyState from '@/Components/shared/EmptyState';

export interface TokenUso { user_name: string; last_used_at: string | null; expires_at: string | null }

const quando = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
};

/** "Ana, Bruno e mais 3" — usado na confirmação de excluir para dizer QUEM perde acesso. */
export function nomesQuePerdem(tokens: TokenUso[], resto: number): string {
  const nomes = Array.from(new Set(tokens.map((t) => t.user_name || 'usuário sem nome')));
  if (nomes.length === 0) return '';
  const mais = resto > 0 ? ` e mais ${resto} acesso${resto > 1 ? 's' : ''}` : '';
  if (nomes.length === 1) return `${nomes[0]}${mais}`;
  return resto > 0 ? `${nomes.join(', ')}${mais}` : `${nomes.slice(0, -1).join(', ')} e ${nomes[nomes.length - 1]}`;
}

export function QuemUsaDrawer({ client, onClose }: {
  client: { name: string; tokens: TokenUso[]; tokens_resto: number } | null; onClose: () => void;
}) {
  return (
    <Sheet open={!!client} onOpenChange={(v) => !v && onClose()}>
      <SheetContent data-contract="quem-usa" className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Quem usa “{client?.name}”</SheetTitle>
          <SheetDescription>Acessos abertos com esta credencial, do uso mais recente ao mais antigo. Excluir o client derruba todos.</SheetDescription>
        </SheetHeader>
        {client && client.tokens.length > 0 ? (
          <div className="px-4">
            <table className="w-full text-sm">
              <thead><tr className="border-b text-left text-xs text-muted-foreground">
                <th className="py-2">Colaborador</th><th>Último uso</th><th>Vence em</th>
              </tr></thead>
              <tbody>
                {client.tokens.map((t, i) => (
                  <tr key={i} className="border-b">
                    <td className="py-2 font-medium">{t.user_name || 'usuário sem nome'}</td>
                    <td className="tabular-nums">{quando(t.last_used_at)}</td>
                    <td className="tabular-nums">{quando(t.expires_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {client.tokens_resto > 0 && (
              <p className="mt-2 text-xs text-muted-foreground">e mais {client.tokens_resto} acesso{client.tokens_resto > 1 ? 's' : ''} aberto{client.tokens_resto > 1 ? 's' : ''}, mais antigos.</p>
            )}
          </div>
        ) : (
          <div className="px-4">
            <EmptyState title="Ninguém com acesso aberto" description="Nenhum colaborador deste negócio tem token válido com esta credencial. Excluir não derruba ninguém." />
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
