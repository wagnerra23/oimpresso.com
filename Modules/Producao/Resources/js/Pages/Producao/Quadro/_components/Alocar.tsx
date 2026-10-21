// Alocador (UC-FLX-10 · US-PCP-029): troca o responsável e o início previsto da etapa atual do
// cartão. Grava pelo `PUT /producao/etapas/{id}/alocacao`; o servidor mantém a duração da etapa,
// confere se a pessoa pode atuar no posto e registra o antes e o depois no histórico da OS.
// Só etapa pendente se realoca (a que já começou o servidor recusa — aqui o botão nem aparece).
import { useLayoutEffect, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { json } from '../_lib/json';
import type { Cartao, Pessoas } from '../_lib/tipos';

export default function Alocar({ cartao, pessoas, onClose, onSalvo }: { cartao: Cartao; pessoas: Pessoas; onClose: () => void; onSalvo: (msg: string) => void }) {
  const e = cartao.etapa;
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  const [userId, setUserId] = useState(e.user_id ? String(e.user_id) : '');
  const [inicio, setInicio] = useState(e.inicio_previsto);
  const [erro, setErro] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);

  // O posto com operadores cadastrados só aceita um deles (US-PCP-030); sem cadastro, qualquer um com acesso.
  const restritos = e.posto_id !== null ? (pessoas.por_posto[e.posto_id] ?? []) : [];
  const opcoes = restritos.length ? pessoas.pessoas.filter((p) => restritos.includes(p.id)) : pessoas.pessoas;
  const mudouQuem = userId !== '' && Number(userId) !== e.user_id;
  const mudouQuando = inicio !== '' && inicio !== e.inicio_previsto;

  const salvar = async () => {
    setSalvando(true); setErro(null);
    try {
      await json('PUT', `/producao/etapas/${e.id}/alocacao`, {
        ...(mudouQuem ? { user_id: Number(userId) } : {}),
        ...(mudouQuando ? { inicio_previsto: inicio.replace('T', ' ') + ':00' } : {}),
      });
      onSalvo(`${cartao.numero}: ${e.nome ?? 'etapa'} realocada. Fica no histórico da OS com o seu nome.`);
    } catch (err) {
      setErro((err as Error).message);
    } finally {
      setSalvando(false);
    }
  };

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" container={cockpit} className="w-full overflow-y-auto sm:max-w-md" data-contract="producao-alocar">
        <SheetHeader>
          <SheetTitle>Alocar {e.nome ?? 'etapa'} · {cartao.numero}</SheetTitle>
          <SheetDescription>{e.posto ?? 'Sem posto'}. Mudar o início mantém a duração prevista da etapa.</SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-3 px-4">
          <div><Label>Responsável</Label>
            <Select value={userId} onValueChange={setUserId}>
              <SelectTrigger aria-label="Responsável"><SelectValue placeholder="Escolha quem faz" /></SelectTrigger>
              <SelectContent>{opcoes.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.nome}</SelectItem>)}</SelectContent>
            </Select>
            {restritos.length > 0 && <p className="pcv-help">Só os operadores cadastrados neste posto.</p>}</div>
          <div><Label htmlFor="alocar-inicio">Início previsto</Label>
            <Input id="alocar-inicio" type="datetime-local" value={inicio} onChange={(ev) => setInicio(ev.target.value)} /></div>
          {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
        </div>
        <SheetFooter>
          <Button onClick={salvar} disabled={salvando || (!mudouQuem && !mudouQuando)}>Salvar alocação</Button>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
