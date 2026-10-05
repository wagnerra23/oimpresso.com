// Drawer "Novo negócio" — thread Superadmin 02, PR-2. Substitui a Blade `business.create`.
//
// Decisão [W] 2026-10-05: LAYOUT do protótipo (`NegocioForm` em superadmin-page.jsx — seções
// Identificação · Dono da conta · Assinatura inicial), REGRAS de hoje (o `store()` e o
// `StoreBusinessRequest` não mudaram de contrato). Por isso o dono entra com usuário e senha
// definidos aqui, e não por convite: convite por e-mail, dias de teste e CNPJ são do protótipo e
// não existem no backend — ficam registrados como gap no recibo `_saida-02`.

import { useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Casca } from './Gaveta';

export interface FormNovo {
  moedas: { id: number; nome: string }[];
  moeda_padrao: number | null;
  fusos: string[];
  fuso_padrao: string;
  pacotes: { id: number; nome: string; usuarios: number; locais: number }[];
  gateways: { id: string; nome: string }[];
}

const UFS = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];

const emailOk = (v: string) => /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v);

function Campo({ id, rotulo, erro, ajuda, children }: { id: string; rotulo: string; erro?: string; ajuda?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id} className="text-xs">
        {rotulo}
      </Label>
      {children}
      {erro ? <span className="text-[11px] text-destructive">{erro}</span> : ajuda ? <span className="text-[11px] text-muted-foreground">{ajuda}</span> : null}
    </div>
  );
}

function Lista({ id, valor, onChange, opcoes, vazio }: { id: string; valor: string; onChange: (v: string) => void; opcoes: { v: string; label: string }[]; vazio?: string }) {
  return (
    <select id={id} value={valor} onChange={(e) => onChange(e.target.value)} className="h-9 rounded-md border bg-background px-3 text-xs text-foreground">
      {vazio !== undefined && <option value="">{vazio}</option>}
      {opcoes.map((o) => (
        <option key={o.v} value={o.v}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="border-b px-5 py-4">
      <h3 className="mb-3 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">{titulo}</h3>
      <div className="flex flex-col gap-3">{children}</div>
    </section>
  );
}

export function NovoNegocioEsqueleto({ onFechar }: { onFechar: () => void }) {
  return (
    <Casca onFechar={onFechar} titulo="Novo negócio" largo>
      <div className="flex flex-col gap-3 p-5">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    </Casca>
  );
}

export function NovoNegocio({ opcoes, onFechar }: { opcoes: FormNovo; onFechar: () => void }) {
  const form = useForm({
    name: '',
    currency_id: opcoes.moeda_padrao ? String(opcoes.moeda_padrao) : '',
    time_zone: opcoes.fuso_padrao,
    mobile: '',
    city: '',
    state: 'SP',
    zip_code: '',
    country: 'Brasil',
    first_name: '',
    last_name: '',
    email: '',
    username: '',
    password: '',
    package_id: '',
    paid_via: '',
    payment_transaction_id: '',
  });
  const [confirma, setConfirma] = useState('');
  const [tocado, setTocado] = useState<Record<string, boolean>>({});

  const d = form.data;
  const set = (k: keyof typeof d) => (v: string) => {
    form.setData(k, v);
    form.clearErrors(k);
    setTocado((t) => ({ ...t, [k]: true }));
  };

  // Erro local só depois de tocar o campo; erro do servidor sempre (vem do StoreBusinessRequest).
  const local = {
    name: tocado.name && !d.name.trim() ? 'Diga o nome do negócio.' : undefined,
    email: tocado.email && !emailOk(d.email) ? 'E-mail inválido.' : undefined,
    password: tocado.password && d.password.length < 8 ? 'A senha precisa ter ao menos 8 caracteres.' : undefined,
    confirma: confirma && confirma !== d.password ? 'As duas senhas não batem.' : undefined,
    paid_via: d.package_id && !d.paid_via && tocado.package_id ? 'Diga como a assinatura foi paga.' : undefined,
  };
  const erro = (k: string) => (form.errors as Record<string, string | undefined>)[k] ?? (local as Record<string, string | undefined>)[k];

  const podeSalvar =
    d.name.trim() !== '' &&
    d.currency_id !== '' &&
    d.first_name.trim() !== '' &&
    emailOk(d.email) &&
    d.username.trim() !== '' &&
    d.password.length >= 8 &&
    confirma === d.password &&
    d.city.trim() !== '' &&
    d.state !== '' &&
    d.zip_code.trim() !== '' &&
    d.country.trim() !== '' &&
    (!d.package_id || d.paid_via !== '');

  const salvar = () => {
    if (!podeSalvar || form.processing) return;
    form.post('/superadmin/business', { preserveScroll: true });
  };

  const pacote = opcoes.pacotes.find((p) => String(p.id) === d.package_id);

  return (
    <Casca onFechar={onFechar} titulo="Novo negócio" largo>
      <header className="flex items-start justify-between gap-3 border-b px-5 py-4">
        <div className="min-w-0">
          <h2 className="text-base font-semibold">Novo negócio</h2>
          <p className="mt-0.5 text-[11px] text-muted-foreground">O dono entra com o usuário e a senha que você definir aqui.</p>
        </div>
        <Button variant="outline" size="sm" className="h-8 shrink-0 text-xs" onClick={onFechar} title="Fechar (esc)">
          Fechar
        </Button>
      </header>

      <div className="flex-1 overflow-y-auto" data-contract="superadmin.negocios.novo">
        <Secao titulo="Identificação">
          <Campo id="nn-nome" rotulo="Nome do negócio" erro={erro('name')}>
            <Input id="nn-nome" value={d.name} onChange={(e) => set('name')(e.target.value)} />
          </Campo>
          <div className="grid grid-cols-2 gap-3">
            <Campo id="nn-moeda" rotulo="Moeda" erro={erro('currency_id')}>
              <Lista id="nn-moeda" valor={d.currency_id} onChange={set('currency_id')} vazio="Selecione" opcoes={opcoes.moedas.map((m) => ({ v: String(m.id), label: m.nome }))} />
            </Campo>
            <Campo id="nn-fuso" rotulo="Fuso horário" erro={erro('time_zone')}>
              <Lista id="nn-fuso" valor={d.time_zone} onChange={set('time_zone')} opcoes={opcoes.fusos.map((f) => ({ v: f, label: f }))} />
            </Campo>
          </div>
          <Campo id="nn-fone" rotulo="Telefone do negócio" erro={erro('mobile')}>
            <Input id="nn-fone" value={d.mobile} onChange={(e) => set('mobile')(e.target.value)} inputMode="tel" />
          </Campo>
          <div className="grid grid-cols-[1fr_88px] gap-3">
            <Campo id="nn-cidade" rotulo="Cidade" erro={erro('city')}>
              <Input id="nn-cidade" value={d.city} onChange={(e) => set('city')(e.target.value)} />
            </Campo>
            <Campo id="nn-uf" rotulo="UF" erro={erro('state')}>
              <Lista id="nn-uf" valor={d.state} onChange={set('state')} opcoes={UFS.map((u) => ({ v: u, label: u }))} />
            </Campo>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Campo id="nn-cep" rotulo="CEP" erro={erro('zip_code')}>
              <Input id="nn-cep" value={d.zip_code} onChange={(e) => set('zip_code')(e.target.value)} inputMode="numeric" />
            </Campo>
            <Campo id="nn-pais" rotulo="País" erro={erro('country')}>
              <Input id="nn-pais" value={d.country} onChange={(e) => set('country')(e.target.value)} />
            </Campo>
          </div>
        </Secao>

        <Secao titulo="Dono da conta">
          <div className="grid grid-cols-2 gap-3">
            <Campo id="nn-dono" rotulo="Nome do dono" erro={erro('first_name')}>
              <Input id="nn-dono" value={d.first_name} onChange={(e) => set('first_name')(e.target.value)} />
            </Campo>
            <Campo id="nn-sobrenome" rotulo="Sobrenome" erro={erro('last_name')}>
              <Input id="nn-sobrenome" value={d.last_name} onChange={(e) => set('last_name')(e.target.value)} />
            </Campo>
          </div>
          <Campo id="nn-email" rotulo="E-mail" erro={erro('email')}>
            <Input id="nn-email" type="email" value={d.email} onChange={(e) => set('email')(e.target.value)} />
          </Campo>
          <Campo id="nn-usuario" rotulo="Usuário" erro={erro('username')} ajuda="É com ele que o dono entra no sistema.">
            <Input id="nn-usuario" value={d.username} onChange={(e) => set('username')(e.target.value)} autoComplete="off" />
          </Campo>
          <div className="grid grid-cols-2 gap-3">
            <Campo id="nn-senha" rotulo="Senha" erro={erro('password')}>
              <Input id="nn-senha" type="password" value={d.password} onChange={(e) => set('password')(e.target.value)} autoComplete="new-password" />
            </Campo>
            <Campo id="nn-confirma" rotulo="Confirmar senha" erro={local.confirma}>
              <Input id="nn-confirma" type="password" value={confirma} onChange={(e) => setConfirma(e.target.value)} autoComplete="new-password" />
            </Campo>
          </div>
        </Secao>

        <Secao titulo="Assinatura inicial">
          <Campo id="nn-pacote" rotulo="Pacote" erro={erro('package_id')} ajuda="Opcional. Sem pacote, o negócio nasce só com o cadastro.">
            <Lista id="nn-pacote" valor={d.package_id} onChange={set('package_id')} vazio="Sem assinatura" opcoes={opcoes.pacotes.map((p) => ({ v: String(p.id), label: p.nome }))} />
          </Campo>
          {pacote && (
            <p className="rounded border bg-muted/40 px-3 py-2 text-[11px] text-muted-foreground">
              {pacote.usuarios === 0 ? 'Usuários ilimitados' : `${pacote.usuarios} usuários`} ·{' '}
              {pacote.locais === 0 ? 'locais ilimitados' : `${pacote.locais} ${pacote.locais === 1 ? 'local' : 'locais'}`}
            </p>
          )}
          {d.package_id !== '' && (
            <div className="grid grid-cols-2 gap-3">
              <Campo id="nn-pago" rotulo="Pago via" erro={erro('paid_via')}>
                <Lista id="nn-pago" valor={d.paid_via} onChange={set('paid_via')} vazio="Selecione" opcoes={opcoes.gateways.map((g) => ({ v: g.id, label: g.nome }))} />
              </Campo>
              <Campo id="nn-transacao" rotulo="Transação" erro={erro('payment_transaction_id')}>
                <Input id="nn-transacao" value={d.payment_transaction_id} onChange={(e) => set('payment_transaction_id')(e.target.value)} />
              </Campo>
            </div>
          )}
        </Secao>
      </div>

      <footer className="flex justify-end gap-2 border-t px-5 py-3">
        <Button variant="outline" onClick={onFechar} disabled={form.processing}>
          Cancelar
        </Button>
        <Button onClick={salvar} disabled={!podeSalvar || form.processing}>
          {form.processing ? 'Criando…' : 'Criar negócio'}
        </Button>
      </footer>
    </Casca>
  );
}
