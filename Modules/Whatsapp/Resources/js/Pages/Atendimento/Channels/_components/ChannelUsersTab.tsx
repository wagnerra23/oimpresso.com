// US-WA-068 — Tab "Usuários do canal" (ACL atendente↔canal).
//
// Tabela de users com acesso ativo + dialog pra adicionar (seletor) +
// botão remover por linha. Operações via Inertia router (POST/DELETE).
//
// ADR 0135 (omnichannel) · ADR 0093 (multi-tenant Tier 0 — server valida
// cross-tenant; aqui só renderiza o que server entregou).

import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Trash2, UserPlus, Loader2 } from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/Components/ui/dialog';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';

interface AccessRow {
  id: number;
  user_id: number;
  name: string;
  email: string | null;
  granted_at: string | null;
  granted_by_user_id: number;
  granted_by_name: string | null;
}

interface AvailableUser {
  id: number;
  name: string;
  email: string | null;
  username: string | null;
}

interface Props {
  channelId: number;
  users: AccessRow[];
  availableUsers: AvailableUser[];
}

export default function ChannelUsersTab({ channelId, users, availableUsers }: Props) {
  const [showAdd, setShowAdd] = useState(false);
  const [selectedUserId, setSelectedUserId] = useState<string>('');
  const [submitting, setSubmitting] = useState(false);
  const [pendingRevoke, setPendingRevoke] = useState<number | null>(null);

  function submitGrant() {
    if (!selectedUserId || submitting) return;
    setSubmitting(true);
    router.post(
      route('atendimento.channels.users.grant', channelId),
      { user_id: Number(selectedUserId) },
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowAdd(false);
          setSelectedUserId('');
        },
        onFinish: () => setSubmitting(false),
      },
    );
  }

  function doRevoke(userId: number) {
    setPendingRevoke(userId);
    router.delete(
      route('atendimento.channels.users.revoke', { id: channelId, userId }),
      {
        preserveScroll: true,
        onFinish: () => setPendingRevoke(null),
      },
    );
  }

  return (
    <Card className="p-0 overflow-hidden" data-testid="channel-users-tab">
      <div className="flex items-center justify-between p-3 border-b">
        <div>
          <h3 className="font-semibold text-sm">Usuários com acesso</h3>
          <p className="text-xs text-muted-foreground">
            Atendentes que veem a inbox deste canal. Atendente sem grant não enxerga as conversas.
          </p>
        </div>
        <Button
          size="sm"
          onClick={() => setShowAdd(true)}
          disabled={availableUsers.length === 0}
          data-testid="channel-users-add-btn"
        >
          <Plus size={14} className="mr-1.5" aria-hidden />
          Adicionar usuário
        </Button>
      </div>

      {users.length === 0 ? (
        <div className="p-8 text-center text-sm text-muted-foreground">
          Nenhum usuário com acesso. Adicione atendentes pra eles enxergarem a inbox deste canal.
        </div>
      ) : (
        <table className="w-full text-sm" data-testid="channel-users-table">
          <thead className="bg-muted/30 border-b">
            <tr className="text-xs text-muted-foreground">
              <th className="text-left px-3 py-2">Nome</th>
              <th className="text-left px-3 py-2">Email</th>
              <th className="text-left px-3 py-2">Concedido em</th>
              <th className="text-left px-3 py-2">Por</th>
              <th className="text-right px-3 py-2 w-20">Ação</th>
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id} className="border-b last:border-0">
                <td className="px-3 py-2 font-medium">{u.name}</td>
                <td className="px-3 py-2 text-xs text-muted-foreground">{u.email || '—'}</td>
                <td className="px-3 py-2 text-xs">{fmtDate(u.granted_at)}</td>
                <td className="px-3 py-2 text-xs">{u.granted_by_name || '—'}</td>
                <td className="px-3 py-2 text-right">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => doRevoke(u.user_id)}
                    disabled={pendingRevoke === u.user_id}
                    title="Revogar acesso"
                    className="h-7 w-7"
                    data-testid={`channel-users-revoke-${u.user_id}`}
                  >
                    {pendingRevoke === u.user_id ? (
                      <Loader2 size={14} className="animate-spin" aria-hidden />
                    ) : (
                      <Trash2 size={14} className="text-muted-foreground hover:text-destructive" aria-hidden />
                    )}
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {availableUsers.length === 0 && users.length > 0 && (
        <div className="px-3 py-2 border-t bg-muted/20 text-xs text-muted-foreground">
          Todos os usuários elegíveis (com permissão whatsapp.access ou whatsapp.send) já têm acesso.
        </div>
      )}

      {/* Add user dialog */}
      <Dialog open={showAdd} onOpenChange={(o) => { setShowAdd(o); if (!o) setSelectedUserId(''); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Conceder acesso ao canal</DialogTitle>
            <DialogDescription>
              Usuário precisa ter permissão <code className="text-xs">whatsapp.access</code> ou{' '}
              <code className="text-xs">whatsapp.send</code>. Apenas users do mesmo business aparecem.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-2">
            <Label htmlFor="user_id">Usuário</Label>
            <Select value={selectedUserId} onValueChange={setSelectedUserId}>
              <SelectTrigger id="user_id" data-testid="channel-users-select">
                <SelectValue placeholder="Selecione um usuário..." />
              </SelectTrigger>
              <SelectContent>
                {availableUsers.length === 0 ? (
                  <SelectItem value="__none" disabled>
                    Nenhum usuário disponível
                  </SelectItem>
                ) : (
                  availableUsers.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      <span className="flex items-center gap-2">
                        <UserPlus size={12} aria-hidden />
                        {u.name}
                        {u.email && <Badge variant="outline" className="text-[10px]">{u.email}</Badge>}
                      </span>
                    </SelectItem>
                  ))
                )}
              </SelectContent>
            </Select>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowAdd(false)}>Cancelar</Button>
            <Button
              onClick={submitGrant}
              disabled={!selectedUserId || submitting}
              data-testid="channel-users-grant-submit"
            >
              {submitting && <Loader2 size={14} className="mr-1.5 animate-spin" aria-hidden />}
              Conceder acesso
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('pt-BR');
  } catch {
    return iso;
  }
}
