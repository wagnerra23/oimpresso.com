// Origem do paciente no cadastro (UC-FPI-06, UC-FPI-07): escolher um contato da empresa pela
// busca no servidor, ou cadastrar um contato novo (nome + celular). Quem cria o contato e
// recusa celular repetido é o servidor; aqui só a escolha e os campos.
//
// Busca: Command (cmdk) inline com shouldFilter={false} — o motor canônico do papel combobox
// (REGISTRY_DS_COMPONENTES §Combobox), alimentado por GET /fisio/pacientes/contatos?q=.
import { useEffect, useState } from 'react';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PhoneInput } from '@/Components/ui/phone-input';
import { Segmented } from '@/Components/ui/segmented';

export type Origem = 'existente' | 'novo';
export interface ContatoOpcao { id: number; nome: string; telefone: string }
export interface NovoContato { nome: string; telefone: string }

interface Props {
  origem: Origem;
  onOrigem: (o: Origem) => void;
  contatoId: number | null;
  onContato: (c: ContatoOpcao | null) => void;
  novo: NovoContato;
  onNovo: (n: NovoContato) => void;
  erros: Partial<Record<string, string>>;
}

const Erro = ({ msg }: { msg?: string }) => (msg ? <p className="text-xs text-destructive">{msg}</p> : null);

function BuscaContato({ contatoId, onContato }: Pick<Props, 'contatoId' | 'onContato'>) {
  const [termo, setTermo] = useState('');
  const [contatos, setContatos] = useState<ContatoOpcao[]>([]);
  const [carregando, setCarregando] = useState(false);

  useEffect(() => {
    const ctl = new AbortController();
    const t = setTimeout(() => {
      setCarregando(true);
      fetch(`/fisio/pacientes/contatos?q=${encodeURIComponent(termo.trim())}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: ctl.signal,
      })
        .then((r) => (r.ok ? r.json() : { contatos: [] }))
        .then((d) => setContatos(d.contatos ?? []))
        .catch(() => undefined)
        .finally(() => setCarregando(false));
    }, 250);
    return () => { clearTimeout(t); ctl.abort(); };
  }, [termo]);

  return (
    <Command shouldFilter={false} className="rounded-lg border border-border">
      <CommandInput aria-label="Buscar contato" placeholder="Buscar por nome ou celular" value={termo} onValueChange={setTermo} />
      <CommandList>
        <CommandEmpty>{carregando ? 'Buscando…' : 'Nenhum contato disponível. Cadastre um contato novo.'}</CommandEmpty>
        <CommandGroup>
          {contatos.map((c) => (
            <CommandItem
              key={c.id}
              value={String(c.id)}
              onSelect={() => onContato(contatoId === c.id ? null : c)}
              data-checked={contatoId === c.id}
              className="justify-between"
            >
              <span>{c.nome}</span>
              <span className="tabular-nums text-muted-foreground">
                {contatoId === c.id ? 'selecionado · ' : ''}{c.telefone}
              </span>
            </CommandItem>
          ))}
        </CommandGroup>
      </CommandList>
    </Command>
  );
}

export default function OrigemContato({ origem, onOrigem, contatoId, onContato, novo, onNovo, erros }: Props) {
  return (
    <div className="flex flex-col gap-3">
      <Segmented
        value={origem}
        onValueChange={(v) => v && onOrigem(v as Origem)}
        options={[{ value: 'existente', label: 'Contato existente' }, { value: 'novo', label: 'Novo contato' }]}
      />
      {origem === 'existente' ? (
        <div className="flex flex-col gap-1.5">
          <Label>Contato</Label>
          <BuscaContato contatoId={contatoId} onContato={onContato} />
          <Erro msg={erros.contact_id} />
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="fisio-nc-nome">Nome</Label>
            <Input id="fisio-nc-nome" autoComplete="off" value={novo.nome} onChange={(e) => onNovo({ ...novo, nome: e.target.value })} />
            <Erro msg={erros['novo_contato.nome']} />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="fisio-nc-celular">Celular (WhatsApp)</Label>
            <PhoneInput id="fisio-nc-celular" value={novo.telefone} onValueChange={({ digits }) => onNovo({ ...novo, telefone: digits })} />
            <Erro msg={erros['novo_contato.telefone']} />
          </div>
          <p className="text-xs text-muted-foreground sm:col-span-2">
            O contato fica no cadastro de contatos da empresa, como cliente.
          </p>
        </div>
      )}
    </div>
  );
}
