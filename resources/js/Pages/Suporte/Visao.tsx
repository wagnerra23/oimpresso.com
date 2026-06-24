// Modo Suporte fase A (ADR 0306) — visão read-only de uma empresa-cliente: resumo + usuários,
// com "Acessar como" (login-as guardado) por usuário. A faixa "Voltar para X" vem do AppShellV2
// (switched_from, de graça). Read-only com business_id explícito (SPEC §Desenho seguro); a única
// escrita é a porta acessarComo, atrás da trava Tier 0 canImpersonate no servidor. Tokens +
// primitivos (ADR 0253) iguais aos da Suporte/Empresas. Cópia do mockup aprovado por Wagner 2026-06-24.

import { Inline, Stack, Grid } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Building2, Lock, LogIn, ShieldHalf } from 'lucide-react';
import { useState } from 'react';

interface Empresa {
  id: number;
  name: string;
}

interface Contagens {
  usuarios: number;
  contatos: number;
  produtos: number;
  vendas: number;
  compras: number;
}

interface Usuario {
  id: number;
  username: string;
  nome: string;
  papel: string;
  email: string;
  pode_acessar_como: boolean;
}

interface Props {
  empresa: Empresa;
  contagens: Contagens;
  usuarios: Usuario[];
}

const CARDS: { key: keyof Contagens; label: string }[] = [
  { key: 'usuarios', label: 'Usuários' },
  { key: 'contatos', label: 'Contatos' },
  { key: 'produtos', label: 'Produtos' },
  { key: 'vendas', label: 'Vendas' },
  { key: 'compras', label: 'Compras' },
];

export default function Visao({ empresa, contagens, usuarios }: Props) {
  const [q, setQ] = useState('');
  const [entrando, setEntrando] = useState<number | null>(null);

  const termo = q.trim().toLowerCase();
  const filtrados = termo
    ? usuarios.filter(
        (u) =>
          u.username.toLowerCase().includes(termo) ||
          u.nome.toLowerCase().includes(termo) ||
          u.email.toLowerCase().includes(termo),
      )
    : usuarios;

  const acessarComo = (u: Usuario) => {
    if (!u.pode_acessar_como || entrando !== null) {
      return;
    }
    if (
      !window.confirm(
        `Acessar como ${u.username}?\n\nVocê vai operar COMO este usuário em ${empresa.name} até clicar em "Voltar para mim". A ação é registrada (auditoria).`,
      )
    ) {
      return;
    }
    setEntrando(u.id);
    router.post(
      `/suporte/empresas/${empresa.id}/acessar-como/${u.id}`,
      {},
      { onFinish: () => setEntrando(null) },
    );
  };

  return (
    <AppShellV2 title="Suporte">
      <Head title={`Suporte · ${empresa.name}`} />

      <Stack gap={5} className="mx-auto max-w-5xl p-6">
        <Inline
          asChild
          gap={1}
          className="w-fit text-sm text-[color:var(--text-mute)] hover:text-[color:var(--text)]"
        >
          <Link href="/suporte/empresas">
            <ArrowLeft size={15} aria-hidden="true" /> Suporte · empresas
          </Link>
        </Inline>

        <Inline
          gap={3}
          align="start"
          className="rounded-lg border border-[color:var(--border)] bg-[color:var(--panel-2)] px-4 py-3"
        >
          <ShieldHalf size={18} aria-hidden="true" className="mt-0.5 shrink-0 text-[color:var(--warn)]" />
          <p className="text-sm leading-snug text-[color:var(--text)]">
            <span className="font-medium">Modo Suporte.</span> A empresa operadora não aparece.{' '}
            <span className="font-medium">“Acessar como”</span> loga você como aquele usuário — você
            atua no lugar dele até clicar em “Voltar para mim”. Cada acesso é auditado.
          </p>
        </Inline>

        <Inline gap={3} align="center">
          <Inline
            justify="center"
            align="center"
            className="h-10 w-10 shrink-0 rounded-lg bg-[color:var(--panel-2)]"
          >
            <Building2 size={20} aria-hidden="true" className="text-[color:var(--accent)]" />
          </Inline>
          <div className="min-w-0">
            <Inline gap={2} align="center">
              <h1 className="truncate text-lg font-semibold text-[color:var(--text)]">
                {empresa.name}
              </h1>
              <span className="text-xs tabular-nums text-[color:var(--text-mute)]">#{empresa.id}</span>
            </Inline>
            <p className="text-sm text-[color:var(--text-mute)]">
              Empresa-cliente · acesso de suporte auditado
            </p>
          </div>
        </Inline>

        <Grid min="sm" gap={3}>
          {CARDS.map((c) => (
            <div key={c.key} className="rounded-lg bg-[color:var(--panel-2)] px-4 py-3">
              <div className="text-sm text-[color:var(--text-mute)]">{c.label}</div>
              <div className="mt-0.5 text-2xl font-semibold tabular-nums text-[color:var(--text)]">
                {contagens[c.key].toLocaleString('pt-BR')}
              </div>
            </div>
          ))}
        </Grid>

        <Inline justify="between" align="center" gap={4} wrap>
          <h2 className="text-base font-semibold text-[color:var(--text)]">Todos os usuários</h2>
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar usuário…"
            aria-label="Buscar usuário"
            className="w-full sm:w-64"
          />
        </Inline>

        <div className="overflow-hidden rounded-lg border border-[color:var(--border)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[color:var(--border)] text-left text-xs uppercase tracking-wide text-[color:var(--text-mute)]">
                <th className="px-4 py-3 font-semibold">Username</th>
                <th className="px-4 py-3 font-semibold">Nome</th>
                <th className="px-4 py-3 font-semibold">Papel</th>
                <th className="px-4 py-3 font-semibold">Email</th>
                <th className="px-4 py-3 text-right font-semibold">Ação</th>
              </tr>
            </thead>
            <tbody>
              {filtrados.map((u) => (
                <tr
                  key={u.id}
                  className="border-b border-[color:var(--border)] last:border-0 hover:bg-[color:var(--panel-2)]"
                >
                  <td className="px-4 py-3 text-[color:var(--text)]">{u.username}</td>
                  <td className="px-4 py-3 text-[color:var(--text-mute)]">{u.nome || '—'}</td>
                  <td className="px-4 py-3 text-[color:var(--text-mute)]">{u.papel || '—'}</td>
                  <td className="px-4 py-3 text-[color:var(--text-mute)]">{u.email}</td>
                  <td className="px-4 py-3 text-right">
                    {u.pode_acessar_como ? (
                      <Button
                        size="sm"
                        disabled={entrando !== null}
                        onClick={() => acessarComo(u)}
                      >
                        <LogIn size={14} aria-hidden="true" />
                        {entrando === u.id ? 'Entrando…' : 'Acessar como'}
                      </Button>
                    ) : (
                      <Inline
                        asChild
                        gap={1}
                        justify="end"
                        className="text-xs text-[color:var(--text-mute)]"
                      >
                        <span title="Operador, superadmin ou usuário inativo — fora do alcance do Modo Suporte">
                          <Lock size={13} aria-hidden="true" /> indisponível
                        </span>
                      </Inline>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {filtrados.length === 0 && (
            <div className="py-12 text-center text-sm text-[color:var(--text-mute)]">
              {usuarios.length === 0 ? 'Nenhum usuário nesta empresa.' : `Nada para “${q}”.`}
            </div>
          )}
        </div>

        <Inline asChild gap={1} align="center" className="text-xs text-[color:var(--text-mute)]">
          <p>
            <Lock size={12} aria-hidden="true" /> Cada “Acessar como” grava em support_access_logs
            (append-only): quem · qual usuário · qual empresa · quando.
          </p>
        </Inline>
      </Stack>
    </AppShellV2>
  );
}
