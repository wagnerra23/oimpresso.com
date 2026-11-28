// @memcofre
//   tela: /taxonomies?type=source|life_stage|followup_category|hrm_department|hrm_designation
//   module: Configuracoes
//   stories: listas dos módulos (TaxonomyController@index · Blade → Inertia, Onda 1)
//   permissao: o tipo só abre aqui quando o módulo dono o declara para o usuário (getModuleData addTaxonomies)
//
// Fontes, Estágios de vida e Categorias de acompanhamento (CRM); Departamentos e Cargos (RH).
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-taxonomias.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Taxonomias()
//                   prototipo-ui/cowork/Wagner/hrm-page.jsx → Taxonomia()
//
// Atrás da flag useV2Taxonomias (default ligado, decisão [F] 2026-11-27). Adicionar, renomear e excluir usam os
// endpoints JSON que a Blade já usava (store/update/destroy). `?classico=1` devolve a Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import EmptyState from '@/Components/shared/EmptyState';
import { Skeleton } from '@/Components/ui/skeleton';
import { Inline, Stack } from '@/Components/layout';

interface Item { id: number; nome: string; codigo: string | null; descricao: string | null; em_uso: number }
interface Aba { tipo: string; titulo: string }
interface Props {
  tipo: string;
  titulo: string;
  subtitulo: string;
  rotulo: string;
  com_codigo: boolean;
  rotulo_codigo: string;
  abas: Aba[];
  itens?: Item[];
}
interface Resposta { success: boolean; msg?: string }

async function enviar(url: string, metodo: 'POST' | 'PUT' | 'DELETE', corpo?: Record<string, string>): Promise<Resposta> {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  const r = await fetch(url, {
    method: metodo,
    headers: {
      Accept: 'application/json', 'Content-Type': 'application/json',
      'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest',
    },
    body: corpo ? JSON.stringify(corpo) : undefined,
  });
  if (!r.ok) return { success: false, msg: 'Não foi possível salvar agora. Tente de novo.' };
  return (await r.json()) as Resposta;
}

export default function TaxonomiasIndex({ tipo, titulo, subtitulo, rotulo, com_codigo, rotulo_codigo, abas, itens }: Props) {
  const [novo, setNovo] = useState({ nome: '', codigo: '' });
  const [edit, setEdit] = useState<{ id: number; nome: string; codigo: string } | null>(null);
  const [aviso, setAviso] = useState<{ texto: string; ok: boolean } | null>(null);
  const [ocupado, setOcupado] = useState(false);

  const recarregar = () => router.reload({ only: ['itens'] });
  const depois = (r: Resposta, okTexto: string) => {
    setAviso({ texto: r.success ? okTexto : (r.msg || 'Não foi possível salvar.'), ok: r.success });
    if (r.success) recarregar();
    return r.success;
  };

  const adicionar = async (e: FormEvent) => {
    e.preventDefault();
    const nome = novo.nome.trim();
    if (!nome) { setAviso({ texto: 'Dê um nome antes de adicionar.', ok: false }); return; }
    setOcupado(true);
    const r = await enviar('/taxonomies', 'POST', { name: nome, short_code: novo.codigo.trim(), category_type: tipo, description: '' });
    setOcupado(false);
    if (depois(r, `“${nome}” adicionado.`)) setNovo({ nome: '', codigo: '' });
  };

  const salvar = async (e: FormEvent, item: Item) => {
    e.preventDefault();
    if (!edit) return;
    const nome = edit.nome.trim();
    if (!nome) { setAviso({ texto: 'O nome não pode ficar vazio.', ok: false }); return; }
    setOcupado(true);
    const r = await enviar(`/taxonomies/${item.id}`, 'PUT', { name: nome, short_code: edit.codigo.trim(), description: item.descricao ?? '' });
    setOcupado(false);
    if (depois(r, `“${nome}” salvo.`)) setEdit(null);
  };

  const excluir = async (item: Item) => {
    if (!window.confirm(`Excluir “${item.nome}”?`)) return;
    setOcupado(true);
    const r = await enviar(`/taxonomies/${item.id}`, 'DELETE');
    setOcupado(false);
    depois(r, `“${item.nome}” excluído.`);
  };

  return (
    <Stack gap={4}>
      <PageHeader title={titulo} subtitle={subtitulo || undefined} />

      {abas.length > 1 ? (
        <Inline asChild wrap gap={2}>
        <nav aria-label="Listas do módulo">
          {abas.map((a) => (
            <Button key={a.tipo} asChild size="sm" variant={a.tipo === tipo ? 'secondary' : 'outline'}>
              <a href={`/taxonomies?type=${a.tipo}`} aria-current={a.tipo === tipo ? 'page' : undefined}>{a.titulo}</a>
            </Button>
          ))}
        </nav>
        </Inline>
      ) : null}

      <Card data-contract="taxonomias">
        <Stack asChild gap={3}>
        <CardContent className="p-4">
          <Inline asChild wrap gap={2}>
          <form onSubmit={adicionar}>
            <Input className="max-w-xs" value={novo.nome} onChange={(e) => setNovo({ ...novo, nome: e.target.value })}
              placeholder={`Nome do novo item de ${rotulo.toLowerCase()}`} aria-label={`Nome do novo item de ${rotulo.toLowerCase()}`} />
            {com_codigo ? (
              <Input className="max-w-[160px]" value={novo.codigo} onChange={(e) => setNovo({ ...novo, codigo: e.target.value })}
                placeholder={rotulo_codigo} aria-label={rotulo_codigo} />
            ) : null}
            <Button type="submit" size="sm" disabled={ocupado}>Adicionar</Button>
            {itens ? <span className="ml-auto text-xs text-muted-foreground">{itens.length} item(ns)</span> : null}
          </form>
          </Inline>

          {aviso ? (
            <p role="status" className={aviso.ok ? 'text-sm text-muted-foreground' : 'text-sm text-destructive'}>{aviso.texto}</p>
          ) : null}

          <Deferred data="itens" fallback={<Skeleton className="h-40 w-full" />}>
          {itens && itens.length ? (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-xs text-muted-foreground">
                    <th scope="col" className="py-2 pr-3 font-medium">{rotulo}</th>
                    {com_codigo ? <th scope="col" className="py-2 pr-3 font-medium">{rotulo_codigo}</th> : null}
                    <th scope="col" className="py-2 pr-3 font-medium">Descrição</th>
                    <th scope="col" className="py-2 pr-3 text-right font-medium">Em uso</th>
                    <th scope="col" className="py-2"><span className="sr-only">Ações</span></th>
                  </tr>
                </thead>
                <tbody>
                  {itens.map((item) => (
                    <tr key={item.id} className="border-b last:border-0">
                      <td className="py-2 pr-3">
                        {edit?.id === item.id ? (
                          <Inline asChild wrap gap={2}>
                          <form onSubmit={(e) => salvar(e, item)}>
                            <Input autoFocus className="max-w-xs" value={edit.nome} onChange={(e) => setEdit({ ...edit, nome: e.target.value })} aria-label={`Novo nome de ${item.nome}`} />
                            {com_codigo ? (
                              <Input className="max-w-[160px]" value={edit.codigo} onChange={(e) => setEdit({ ...edit, codigo: e.target.value })} aria-label={rotulo_codigo} />
                            ) : null}
                            <Button type="submit" size="sm" disabled={ocupado}>Salvar</Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => setEdit(null)}>Cancelar</Button>
                          </form>
                          </Inline>
                        ) : item.nome}
                      </td>
                      {com_codigo ? <td className="py-2 pr-3">{item.codigo || '—'}</td> : null}
                      <td className="py-2 pr-3 text-muted-foreground">{item.descricao || '—'}</td>
                      <td className="py-2 pr-3 text-right tabular-nums">{item.em_uso}</td>
                      <td className="py-2 text-right">
                        <span className="inline-flex gap-1">
                          <Button size="sm" variant="ghost" disabled={ocupado} aria-label={`Editar ${item.nome}`}
                            onClick={() => setEdit({ id: item.id, nome: item.nome, codigo: item.codigo ?? '' })}>Editar</Button>
                          <Button size="sm" variant="ghost" disabled={ocupado || item.em_uso > 0} aria-label={`Excluir ${item.nome}`}
                            title={item.em_uso > 0 ? `${item.em_uso} registro(s) usam este item — troque antes de excluir` : undefined}
                            onClick={() => excluir(item)}>Excluir</Button>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <EmptyState title="Nenhum item cadastrado" description={`Adicione o primeiro item de ${rotulo.toLowerCase()} pelo campo acima.`} />
          )}
          </Deferred>

          <p className="text-xs text-muted-foreground">Item em uso não se exclui — troque nos registros antes.</p>
        </CardContent>
        </Stack>
      </Card>
    </Stack>
  );
}

TaxonomiasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
