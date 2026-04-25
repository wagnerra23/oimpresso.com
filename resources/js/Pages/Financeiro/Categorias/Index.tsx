// @memcofre tela=/financeiro/categorias module=Financeiro

import AppShell from '@/Layouts/AppShell';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Tag, Plus, Pencil, Trash2, Power, PowerOff } from 'lucide-react';
import { toast } from 'sonner';
import { CategoriaSheet } from './components/CategoriaSheet';

interface Categoria {
  id: number;
  nome: string;
  cor: string | null;
  plano_conta_id: number | null;
  tipo: 'receita' | 'despesa' | 'ambos';
  ativo: boolean;
}

interface PlanoConta {
  id: number;
  codigo: string;
  nome: string;
  natureza: string;
}

interface Props {
  categorias: Categoria[];
  planos_conta: PlanoConta[];
}

const TIPO_LABELS: Record<string, { label: string; color: string }> = {
  receita: { label: 'Receita', color: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200' },
  despesa: { label: 'Despesa', color: 'bg-rose-100 text-rose-900 dark:bg-rose-900/30 dark:text-rose-200' },
  ambos: { label: 'Ambos', color: 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-200' },
};

function Index({ categorias, planos_conta }: Props) {
  const [editing, setEditing] = useState<Categoria | null>(null);
  const [creating, setCreating] = useState(false);

  const planoLabel = (id: number | null) => {
    if (!id) return '—';
    const p = planos_conta.find((x) => x.id === id);
    return p ? `${p.codigo} ${p.nome}` : '—';
  };

  const toggleAtivo = (c: Categoria) => {
    router.post(`/financeiro/categorias/${c.id}/toggle`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success(c.ativo ? 'Categoria inativada' : 'Categoria ativada'),
      onError: () => toast.error('Erro ao atualizar status'),
    });
  };

  const excluir = (c: Categoria) => {
    if (!confirm(`Excluir categoria "${c.nome}"? (soft delete)`)) {
      return;
    }
    router.delete(`/financeiro/categorias/${c.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Categoria removida'),
      onError: () => toast.error('Erro ao remover'),
    });
  };

  return (
    <>
      <Head title="Categorias · Financeiro" />

      <div className="p-6 max-w-5xl mx-auto space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
              <Tag className="h-6 w-6" /> Categorias
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Tags livres pra organizar lançamentos e relatórios. Complementam o plano de contas
              (que é fixo/contábil).
            </p>
          </div>
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4 mr-2" /> Nova categoria
          </Button>
        </div>

        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium w-10"></th>
                <th className="px-4 py-2 font-medium">Nome</th>
                <th className="px-4 py-2 font-medium">Tipo</th>
                <th className="px-4 py-2 font-medium">Plano de contas</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {categorias.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                    Nenhuma categoria cadastrada ainda.
                  </td>
                </tr>
              )}
              {categorias.map((c) => {
                const tipo = TIPO_LABELS[c.tipo];
                return (
                  <tr key={c.id} className="border-t hover:bg-muted/30">
                    <td className="px-4 py-3">
                      <span
                        className="inline-block w-5 h-5 rounded border"
                        style={{ backgroundColor: c.cor ?? 'transparent' }}
                        title={c.cor ?? 'Sem cor'}
                      />
                    </td>
                    <td className="px-4 py-3 font-medium">{c.nome}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center text-xs px-2 py-1 rounded ${tipo.color}`}>
                        {tipo.label}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">
                      {planoLabel(c.plano_conta_id)}
                    </td>
                    <td className="px-4 py-3">
                      {c.ativo ? (
                        <span className="text-xs text-emerald-700 dark:text-emerald-300">Ativa</span>
                      ) : (
                        <span className="text-xs text-muted-foreground">Inativa</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-1">
                        <Button size="sm" variant="ghost" onClick={() => setEditing(c)} title="Editar">
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => toggleAtivo(c)}
                          title={c.ativo ? 'Inativar' : 'Ativar'}
                        >
                          {c.ativo ? <PowerOff className="h-4 w-4" /> : <Power className="h-4 w-4" />}
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => excluir(c)}
                          title="Excluir"
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <Card>
          <CardContent className="pt-6 text-sm text-muted-foreground space-y-2">
            <p>
              <strong>Diferença vs plano de contas:</strong> o plano é estrutura contábil fixa
              (ex: 3.1.01 Receita de Vendas). Categorias são tags livres que você cria conforme
              precisar pra relatórios e filtros — pode até vincular opcionalmente a um plano.
            </p>
            <p>
              Categorias inativadas ficam ocultas em selects de novos lançamentos, mas seguem
              vinculadas a registros antigos.
            </p>
          </CardContent>
        </Card>
      </div>

      {(editing || creating) && (
        <CategoriaSheet
          categoria={editing}
          planosConta={planos_conta}
          onClose={() => {
            setEditing(null);
            setCreating(false);
          }}
        />
      )}
    </>
  );
}

Index.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
export default Index;
