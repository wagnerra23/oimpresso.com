// Ficha da associação em drawer (US-ASSOC-002 · UC-ASSOC-02..06). Cadastrar e editar os dados,
// os usuários representantes (só a diretoria da rede troca), a mudança de diretoria (D7) e os
// documentos. Documento não se apaga: "Tirar da lista" pede o motivo.
import { useState, type FormEvent, type ReactNode } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { DocumentInput } from '@/Components/ui/document-input';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { SITUACOES, TIPOS_DOCUMENTO, VARIANTE_PENDENCIA, dataBR, rotuloPendencia, type Ficha } from './tipos';

interface Props {
  ficha: Ficha | null; // null = associação nova
  podeEditar: boolean;
  podeRede: boolean;
  onClose: () => void;
  onCriada?: () => void;
}

// Depois de gravar, o servidor volta para /associacoes/lista?ver={id}; recarrega só a ficha e a lista,
// para o drawer continuar aberto com o dado novo.
const OPCOES = { preserveScroll: true, preserveState: true, only: ['ficha', 'associacoes', 'flash'] };

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="mx-4 flex flex-col gap-3 rounded-lg border border-border p-4">
      <h3 className="text-xs font-semibold uppercase tracking-[0.04em] text-muted-foreground">{titulo}</h3>
      {children}
    </section>
  );
}

function Erro({ msg }: { msg?: string }) {
  return msg ? <p className="text-xs text-destructive">{msg}</p> : null;
}

function Dados({ ficha, podeEditar, podeRede, onCriada }: Props) {
  const form = useForm({
    nome: ficha?.nome ?? '',
    sigla: ficha?.sigla ?? '',
    cidade: ficha?.cidade ?? '',
    regiao: ficha?.regiao ?? '',
    cnpj: ficha?.cnpj ?? '',
    situacao: ficha?.situacao ?? 'em_adesao',
    representantes: ficha?.representantes ?? [],
  });
  const travado = !podeEditar;
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => (podeRede ? d : { ...d, representantes: undefined }));
    if (ficha) form.put(`/associacoes/${ficha.id}`, OPCOES);
    else form.post('/associacoes/lista', { ...OPCOES, onSuccess: onCriada });
  };
  const alternar = (id: number, marcado: boolean) =>
    form.setData('representantes', marcado ? [...form.data.representantes, id] : form.data.representantes.filter((r) => r !== id));

  return (
    <form onSubmit={enviar} className="flex flex-col gap-4">
      <Secao titulo="Dados da associação">
        <div className="flex flex-col gap-1">
          <Label htmlFor="assoc-nome">Nome</Label>
          <Input id="assoc-nome" value={form.data.nome} disabled={travado} onChange={(e) => form.setData('nome', e.target.value)} />
          <Erro msg={form.errors.nome} />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div className="flex flex-col gap-1">
            <Label htmlFor="assoc-sigla">Sigla</Label>
            <Input id="assoc-sigla" value={form.data.sigla} disabled={travado} onChange={(e) => form.setData('sigla', e.target.value)} />
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="assoc-cnpj">CNPJ</Label>
            <DocumentInput id="assoc-cnpj" tipo="cnpj" value={form.data.cnpj} disabled={travado}
              onValueChange={({ masked }) => form.setData('cnpj', masked)} />
            <Erro msg={form.errors.cnpj} />
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="assoc-cidade">Cidade</Label>
            <Input id="assoc-cidade" value={form.data.cidade} disabled={travado} onChange={(e) => form.setData('cidade', e.target.value)} />
            <Erro msg={form.errors.cidade} />
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="assoc-regiao">Região</Label>
            <Input id="assoc-regiao" value={form.data.regiao} disabled={travado} onChange={(e) => form.setData('regiao', e.target.value)} />
          </div>
        </div>
        <div className="flex flex-col gap-1">
          <span id="assoc-situacao" className="text-sm font-medium">Situação</span>
          <Segmented aria-labelledby="assoc-situacao" value={form.data.situacao}
            onValueChange={(v) => v && !travado && form.setData('situacao', v)}
            options={Object.entries(SITUACOES).map(([value, label]) => ({ value, label, disabled: travado }))} />
          <Erro msg={form.errors.situacao} />
        </div>
      </Secao>
      {podeRede && ficha && (
        <Secao titulo="Representantes no sistema">
          <p className="text-xs text-muted-foreground">Quem marcar aqui entra no sistema e vê só esta associação.</p>
          <div className="flex max-h-48 flex-col gap-2 overflow-y-auto">
            {ficha.usuarios.map((u) => (
              <label key={u.id} className="flex items-center gap-2 text-sm">
                <Checkbox checked={form.data.representantes.includes(u.id)} onCheckedChange={(c) => alternar(u.id, c === true)} />
                {u.nome}
              </label>
            ))}
          </div>
          <Erro msg={form.errors.representantes} />
        </Secao>
      )}
      {podeEditar && (
        <div className="mx-4 flex justify-end">
          <Button type="submit" disabled={form.processing}>{ficha ? 'Salvar' : 'Cadastrar associação'}</Button>
        </div>
      )}
    </form>
  );
}

function Diretoria({ ficha, podeEditar }: { ficha: Ficha; podeEditar: boolean }) {
  const form = useForm({ mudou_em: '' });
  const p = ficha.pendencia;
  return (
    <Secao titulo="Diretoria">
      <p className="text-sm" data-contract="assoc-pendencia-ficha">
        Ata vigente: {dataBR(ficha.diretoria_atualizada_em)} ·{' '}
        <Badge variant={VARIANTE_PENDENCIA[p.estado]}>{rotuloPendencia(p)}</Badge>
      </p>
      {podeEditar && (
        <form className="flex items-end gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/associacoes/${ficha.id}/diretoria`, { ...OPCOES, onSuccess: () => form.reset() }); }}>
          <div className="flex flex-col gap-1">
            <Label htmlFor="assoc-mudou">A diretoria mudou em</Label>
            <Input id="assoc-mudou" type="date" value={form.data.mudou_em} onChange={(e) => form.setData('mudou_em', e.target.value)} />
            <Erro msg={form.errors.mudou_em} />
          </div>
          <Button type="submit" variant="outline" disabled={form.processing || !form.data.mudou_em}>Registrar mudança</Button>
        </form>
      )}
    </Secao>
  );
}

function Documentos({ ficha, podeEditar }: { ficha: Ficha; podeEditar: boolean }) {
  const form = useForm<{ tipo: string; descricao: string; validade: string; arquivo: File | null }>({ tipo: 'estatuto', descricao: '', validade: '', arquivo: null });
  const [chave, setChave] = useState(0);
  const [tirando, setTirando] = useState<number | null>(null);
  const [motivo, setMotivo] = useState('');
  const anexar = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/associacoes/${ficha.id}/documentos`, { ...OPCOES, forceFormData: true, onSuccess: () => { form.reset(); setChave((k) => k + 1); } });
  };
  const tirar = (id: number) =>
    router.post(`/associacoes/documentos/${id}/tirar`, { motivo }, { ...OPCOES, onSuccess: () => { setTirando(null); setMotivo(''); } });

  return (
    <Secao titulo="Documentos">
      {ficha.documentos.length === 0 && <p className="text-sm text-muted-foreground">Nenhum documento na lista.</p>}
      <ul className="flex flex-col divide-y divide-border">
        {ficha.documentos.map((d) => (
          <li key={d.id} className="flex flex-col gap-1 py-2 text-sm">
            <div className="flex items-center justify-between gap-2">
              <a href={d.url} target="_blank" rel="noopener noreferrer" className="font-medium hover:underline">
                {TIPOS_DOCUMENTO[d.tipo] ?? d.tipo}{d.descricao ? ` · ${d.descricao}` : ''}
              </a>
              {podeEditar && tirando !== d.id && <Button type="button" size="sm" variant="ghost" onClick={() => setTirando(d.id)}>Tirar da lista</Button>}
            </div>
            <span className="text-xs text-muted-foreground">
              Enviado em {dataBR(d.enviado_em)}{d.validade ? ` · validade ${dataBR(d.validade)}` : ''}{d.vencido ? ' · vencido' : ''}
            </span>
            {tirando === d.id && (
              <div className="flex gap-2">
                <Input aria-label="Motivo para tirar da lista" placeholder="Por que sai da lista?" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
                <Button type="button" size="sm" disabled={motivo.trim().length < 3} onClick={() => tirar(d.id)}>Confirmar</Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setTirando(null)}>Cancelar</Button>
              </div>
            )}
          </li>
        ))}
      </ul>
      {podeEditar && (
        <form onSubmit={anexar} className="flex flex-col gap-2 border-t border-border pt-3">
          <span id="doc-tipo" className="text-sm font-medium">Anexar documento</span>
          <Segmented aria-labelledby="doc-tipo" value={form.data.tipo} onValueChange={(v) => v && form.setData('tipo', v)}
            options={Object.entries(TIPOS_DOCUMENTO).map(([value, label]) => ({ value, label }))} />
          <div className="grid grid-cols-2 gap-2">
            <Input aria-label="Descrição" placeholder="Descrição (opcional)" value={form.data.descricao} onChange={(e) => form.setData('descricao', e.target.value)} />
            <Input aria-label="Validade" type="date" value={form.data.validade} onChange={(e) => form.setData('validade', e.target.value)} />
          </div>
          <Input key={chave} aria-label="Arquivo" type="file" accept="application/pdf,image/jpeg,image/png,image/webp"
            onChange={(e) => form.setData('arquivo', e.target.files?.[0] ?? null)} />
          <Erro msg={form.errors.arquivo ?? form.errors.tipo} />
          <div className="flex justify-end">
            <Button type="submit" disabled={form.processing || !form.data.arquivo}>Anexar</Button>
          </div>
        </form>
      )}
    </Secao>
  );
}

export default function FichaAssociacao(props: Props) {
  const { ficha, podeEditar, onClose } = props;
  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl" data-contract="assoc-ficha">
        <SheetHeader>
          <SheetTitle>{ficha ? ficha.nome : 'Nova associação'}</SheetTitle>
          <SheetDescription>
            {ficha ? `${ficha.cidade}${ficha.regiao ? ` · ${ficha.regiao}` : ''}` : 'A associação entra na rede como linha desta empresa.'}
          </SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-4 pb-6">
          <Dados key={ficha?.id ?? 'nova'} {...props} />
          {ficha && <Diretoria ficha={ficha} podeEditar={podeEditar} />}
          {ficha && <Documentos ficha={ficha} podeEditar={podeEditar} />}
        </div>
      </SheetContent>
    </Sheet>
  );
}
