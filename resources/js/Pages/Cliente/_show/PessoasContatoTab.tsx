// Onda Final.C — Tab Pessoas de contato.
// Usuários CRM associados ao contact (Modules/Crm contact_login feature).
// Paridade com Blade crm::contact_login.index.

import { UserPlus, Users } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export interface ContactPerson {
  id: number;
  username: string;
  email: string | null;
  full_name: string;
  department: string | null;
  designation: string | null;
}

export interface PessoasContatoTabProps {
  contactId: number;
  contact_persons?: ContactPerson[];
}

export default function PessoasContatoTab({ contactId, contact_persons }: PessoasContatoTabProps) {
  if (!contact_persons) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="persons-tab-skeleton">
        Carregando pessoas de contato…
      </div>
    );
  }

  if (contact_persons.length === 0) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground flex flex-col items-center gap-3" data-testid="persons-tab-empty">
        <Users size={24} className="text-muted-foreground/50" />
        <div>Nenhuma pessoa de contato registrada.</div>
        <Button variant="outline" size="sm" asChild>
          <a href={`/crm/contact-login/create?contact_id=${contactId}`}>
            <UserPlus className="mr-1.5 h-3.5 w-3.5" />
            Adicionar pessoa
          </a>
        </Button>
      </div>
    );
  }

  return (
    <div className="overflow-hidden" data-testid="persons-tab-root">
      <div className="flex items-center justify-between border-b border-border px-4 py-2">
        <span className="text-xs text-muted-foreground">{contact_persons.length} pessoa{contact_persons.length === 1 ? '' : 's'} de contato</span>
        <Button variant="outline" size="sm" asChild>
          <a href={`/crm/contact-login/create?contact_id=${contactId}`}>
            <UserPlus className="mr-1.5 h-3.5 w-3.5" />
            Adicionar
          </a>
        </Button>
      </div>
      <table className="w-full text-sm">
        <thead className="bg-muted/50">
          <tr className="border-b border-border">
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Nome</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Usuário</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">E-mail</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Departamento</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Cargo</th>
          </tr>
        </thead>
        <tbody>
          {contact_persons.map((p) => (
            <tr key={p.id} className="border-b border-border hover:bg-muted/40">
              <td className="px-4 py-3 text-xs font-medium text-foreground">{p.full_name || '—'}</td>
              <td className="px-4 py-3 text-xs text-foreground">{p.username}</td>
              <td className="px-4 py-3 text-xs text-muted-foreground">{p.email ?? '—'}</td>
              <td className="px-4 py-3 text-xs text-muted-foreground">{p.department ?? '—'}</td>
              <td className="px-4 py-3 text-xs text-muted-foreground">{p.designation ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
