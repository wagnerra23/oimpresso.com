// Seções do drawer de negócio que vieram da página /superadmin/business/{id} (Blade), aposentada
// na thread Superadmin 02 — decisão [W] 2026-10-05: tudo que a show mostrava entra no drawer.
// Dados do cadastro, locais e usuários (com definir senha e entrar como). O backend é o
// `detalheDoNegocio()` do BusinessController; a ação de senha reusa `POST /superadmin/update-password`.

import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';

export interface Cadastro {
  moeda: string | null;
  imposto_1: string | null;
  imposto_2: string | null;
  fuso: string | null;
  criado_por: string | null;
  logo: string | null;
}

export interface Local {
  id: number;
  nome: string;
  codigo: string | null;
  referencia: string | null;
  cidade: string | null;
  cep: string | null;
  uf: string | null;
  pais: string | null;
}

export interface UsuarioDoNegocio {
  id: number;
  username: string | null;
  nome: string | null;
  email: string | null;
  papel: string | null;
}

export interface Usuarios {
  pode_agir: boolean;
  lista: UsuarioDoNegocio[];
}

export function Linha({ rotulo, valor }: { rotulo: string; valor: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-xs text-muted-foreground">{rotulo}</span>
      <span className="text-right text-xs font-medium">{valor ?? '—'}</span>
    </div>
  );
}

export function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="border-b px-5 py-4">
      <h3 className="mb-2.5 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">{titulo}</h3>
      {children}
    </section>
  );
}

export function SecaoCadastro({ cadastro }: { cadastro: Cadastro }) {
  return (
    <Secao titulo="Dados do cadastro">
      <div className="flex flex-col gap-2">
        <Linha rotulo="Moeda" valor={cadastro.moeda} />
        <Linha rotulo="Imposto 1" valor={cadastro.imposto_1} />
        <Linha rotulo="Imposto 2" valor={cadastro.imposto_2} />
        <Linha rotulo="Fuso horário" valor={cadastro.fuso} />
        <Linha rotulo="Cadastrado por" valor={cadastro.criado_por} />
      </div>
      {cadastro.logo && (
        <img src={cadastro.logo} alt="Logo do negócio" className="mt-3 max-h-16 max-w-[160px] rounded border object-contain" />
      )}
    </Secao>
  );
}

export function SecaoLocais({ locais }: { locais: Local[] }) {
  return (
    <Secao titulo={locais.length === 1 ? 'Local' : `Locais (${locais.length})`}>
      {locais.length === 0 ? (
        <p className="text-xs text-muted-foreground">Nenhum local cadastrado.</p>
      ) : (
        <ul className="flex flex-col gap-2.5">
          {locais.map((l) => (
            <li key={l.id} className="flex flex-col">
              <span className="text-xs font-medium">
                {l.nome}
                {l.codigo && <span className="ml-1.5 text-[11px] font-normal tabular-nums text-muted-foreground">{l.codigo}</span>}
              </span>
              <span className="text-[11px] text-muted-foreground">
                {[l.referencia, l.cidade, l.uf, l.cep, l.pais].filter(Boolean).join(' · ') || 'sem endereço'}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Secao>
  );
}

export function SecaoUsuarios({ usuarios }: { usuarios: Usuarios }) {
  const [alvo, setAlvo] = useState<UsuarioDoNegocio | null>(null);

  return (
    <Secao titulo={`Usuários (${usuarios.lista.length})`}>
      {usuarios.lista.length === 0 ? (
        <p className="text-xs text-muted-foreground">Nenhum usuário além de você.</p>
      ) : (
        <ul className="flex flex-col gap-3" data-contract="superadmin.negocios.drawer.usuarios">
          {usuarios.lista.map((u) => (
            <li key={u.id} className="flex items-start justify-between gap-3">
              <div className="flex min-w-0 flex-col">
                <span className="truncate text-xs font-medium">{u.nome ?? u.username ?? `usuário #${u.id}`}</span>
                <span className="truncate text-[11px] text-muted-foreground">
                  {[u.username, u.email, u.papel].filter(Boolean).join(' · ')}
                </span>
              </div>
              {usuarios.pode_agir && (
                <div className="flex shrink-0 gap-1.5">
                  <Button variant="outline" size="sm" className="h-7 text-[11px]" onClick={() => setAlvo(u)}>
                    Definir senha
                  </Button>
                  {u.username && (
                    <Button asChild variant="ghost" size="sm" className="h-7 text-[11px]">
                      <a href={`/sign-in-as-user/${u.id}?save_current=true`}>Entrar como</a>
                    </Button>
                  )}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
      <DefinirSenha usuario={alvo} onFechar={() => setAlvo(null)} />
    </Secao>
  );
}

function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function DefinirSenha({ usuario, onFechar }: { usuario: UsuarioDoNegocio | null; onFechar: () => void }) {
  const [senha, setSenha] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const fechar = () => {
    setSenha('');
    setErro(null);
    onFechar();
  };

  const salvar = async () => {
    if (!usuario) return;
    if (senha.length < 8) {
      setErro('A senha precisa ter ao menos 8 caracteres.');
      return;
    }
    setEnviando(true);
    try {
      const res = await fetch('/superadmin/update-password', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify({ user_id: usuario.id, password: senha }),
      });
      const corpo = await res.json().catch(() => null);
      if (res.status === 422) {
        setErro(corpo?.errors?.password?.[0] ?? corpo?.message ?? 'Senha recusada.');
        return;
      }
      if (!res.ok || !corpo?.success) {
        toast.error(corpo?.msg ?? 'Não foi possível definir a senha.');
        return;
      }
      toast.success(corpo.msg ?? 'Senha definida.');
      fechar();
    } catch {
      toast.error('Não foi possível definir a senha.');
    } finally {
      setEnviando(false);
    }
  };

  const nome = usuario ? (usuario.nome ?? usuario.username ?? `usuário #${usuario.id}`) : '';

  return (
    <Dialog open={usuario !== null} onOpenChange={(aberto) => !aberto && fechar()}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>Definir senha</DialogTitle>
          <DialogDescription>
            Nova senha de {nome}. A senha atual deixa de valer; se o e-mail estiver configurado, o usuário recebe a nova por e-mail.
          </DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="nova-senha">Nova senha</Label>
          <Input
            id="nova-senha"
            type="password"
            autoComplete="new-password"
            value={senha}
            onChange={(e) => {
              setSenha(e.target.value);
              setErro(null);
            }}
            onKeyDown={(e) => e.key === 'Enter' && salvar()}
            aria-invalid={erro !== null}
          />
          {erro && <span className="text-[11px] text-destructive">{erro}</span>}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={fechar} disabled={enviando}>
            Cancelar
          </Button>
          <Button onClick={salvar} disabled={enviando || senha.length === 0}>
            {enviando ? 'Salvando…' : 'Definir senha'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
