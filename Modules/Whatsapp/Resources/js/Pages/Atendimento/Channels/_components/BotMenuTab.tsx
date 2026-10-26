// Aba "Menu do bot" do canal (US-WA-327) — configura o bot de atendimento com menu (US-WA-324):
// saudação, opções numeradas (rótulo → fila → resposta) e se pergunta o nome de quem não é
// cliente cadastrado. O bot só responde com o menu ligado aqui E a flag do sistema ligada.

import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

export interface OpcaoMenu { tecla: string; rotulo: string; fila: string | null; resposta: string | null }
export interface MenuBotPayload {
  menu: { ativo: boolean; saudacao: string; opcoes: OpcaoMenu[]; pergunta_nome: boolean } | null;
  filas: { slug: string; label: string }[];
  ligado_no_sistema: boolean;
}

const SEM_FILA = '__sem_fila';
const MAX_OPCOES = 9;

export default function BotMenuTab({ channelId, dados }: { channelId: number; dados: MenuBotPayload }) {
  const inicial = dados.menu;
  const [ativo, setAtivo] = useState(inicial?.ativo ?? false);
  const [saudacao, setSaudacao] = useState(inicial?.saudacao ?? 'Olá! Como podemos ajudar? Responda com o número da opção.');
  const [perguntaNome, setPerguntaNome] = useState(inicial?.pergunta_nome ?? false);
  const [opcoes, setOpcoes] = useState<OpcaoMenu[]>(inicial?.opcoes?.length ? inicial.opcoes : [{ tecla: '1', rotulo: '', fila: null, resposta: null }]);
  const [erros, setErros] = useState<Record<string, string>>({});
  const [salvando, setSalvando] = useState(false);

  const mudar = (i: number, campo: keyof OpcaoMenu, valor: string | null) =>
    setOpcoes((os) => os.map((o, j) => (j === i ? { ...o, [campo]: valor } : o)));

  const salvar = () => {
    setSalvando(true);
    router.put(`/atendimento/canais/${channelId}/menu-bot`, {
      ativo, saudacao, pergunta_nome: perguntaNome, opcoes,
    }, {
      preserveScroll: true, preserveState: true, only: ['menuBot'],
      onError: (e) => setErros(e),
      onSuccess: () => setErros({}),
      onFinish: () => setSalvando(false),
    });
  };

  return (
    <Card className="flex flex-col gap-4 p-4" data-testid="channel-bot-menu">
      {!dados.ligado_no_sistema && (
        <p className="rounded-md border border-warning/20 bg-warning-soft p-3 text-sm text-warning-fg">
          O bot de menu está desligado no sistema. Você pode deixar o menu pronto; ele só responde depois de ligado.
        </p>
      )}

      <div className="flex items-center justify-between gap-4">
        <div>
          <Label htmlFor="bot-ativo">Responder com o menu neste canal</Label>
          <p className="text-xs text-muted-foreground">O bot fala só em conversa sem atendente e para depois que o cliente escolhe.</p>
        </div>
        <Switch id="bot-ativo" checked={ativo} onCheckedChange={setAtivo} />
      </div>

      <div className="flex flex-col gap-1">
        <Label htmlFor="bot-saudacao">Saudação</Label>
        <Textarea id="bot-saudacao" rows={3} value={saudacao} onChange={(e) => setSaudacao(e.target.value)} />
        {erros.saudacao && <small className="text-xs text-destructive">{erros.saudacao}</small>}
      </div>

      <div className="flex flex-col gap-2">
        <Label>Opções</Label>
        {opcoes.map((o, i) => (
          <div key={i} className="grid gap-2 rounded-md border p-2 sm:grid-cols-[4rem_1fr_12rem_auto]">
            <Input aria-label={`Número da opção ${i + 1}`} value={o.tecla} onChange={(e) => mudar(i, 'tecla', e.target.value)} />
            <Input aria-label={`Rótulo da opção ${i + 1}`} placeholder="Ex.: Financeiro" value={o.rotulo} onChange={(e) => mudar(i, 'rotulo', e.target.value)} />
            <Select value={o.fila ?? SEM_FILA} onValueChange={(v) => mudar(i, 'fila', v === SEM_FILA ? null : v)}>
              <SelectTrigger aria-label={`Fila da opção ${i + 1}`}><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={SEM_FILA}>Sem fila</SelectItem>
                {dados.filas.filter((f) => f.slug).map((f) => (
                  <SelectItem key={f.slug} value={f.slug}>{f.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button variant="ghost" size="icon" aria-label={`Remover opção ${i + 1}`} disabled={opcoes.length === 1}
              onClick={() => setOpcoes((os) => os.filter((_, j) => j !== i))}>
              <Trash2 size={16} aria-hidden />
            </Button>
            <Input className="sm:col-span-4" aria-label={`Resposta da opção ${i + 1}`} placeholder="Resposta ao escolher (opcional)"
              value={o.resposta ?? ''} onChange={(e) => mudar(i, 'resposta', e.target.value || null)} />
          </div>
        ))}
        {Object.entries(erros).filter(([k]) => k.startsWith('opcoes')).map(([k, v]) => (
          <small key={k} className="text-xs text-destructive">{v}</small>
        ))}
        <Button variant="outline" size="sm" className="self-start" disabled={opcoes.length >= MAX_OPCOES}
          onClick={() => setOpcoes((os) => [...os, { tecla: String(os.length + 1), rotulo: '', fila: null, resposta: null }])}>
          <Plus size={14} className="mr-1" aria-hidden /> Adicionar opção
        </Button>
      </div>

      <div className="flex items-center justify-between gap-4">
        <div>
          <Label htmlFor="bot-nome">Perguntar o nome</Label>
          <p className="text-xs text-muted-foreground">Só para quem ainda não é cliente cadastrado.</p>
        </div>
        <Switch id="bot-nome" checked={perguntaNome} onCheckedChange={setPerguntaNome} />
      </div>

      <Button className="self-end" onClick={salvar} disabled={salvando}>{salvando ? 'Salvando…' : 'Salvar menu'}</Button>
    </Card>
  );
}
