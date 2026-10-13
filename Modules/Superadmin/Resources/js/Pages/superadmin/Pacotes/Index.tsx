// @memcofre
//   tela: /superadmin/packages
//   module: Superadmin
//   stories: SA-O4c (Blade/AdminLTE → Inertia)
//   permissao: superadmin
//
// Grade comercial da plataforma. Responde: "o que estamos vendendo?".
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/superadmin-page.jsx → ViewPacotes() (L1176)
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-pacotes.md
//
// Cards e não tabela, como o F1 desenha: um pacote tem 4 limites, 3 flags de visibilidade, uma
// lista de módulos e uma contagem de assinantes. Em tabela isso vira 12 colunas, e o Blade
// resolvia escondendo metade.
//
// A regra que esta tela não pode errar: `0` em qualquer limite significa SEM TETO, não "zero
// permitido" (é o COMMENT da coluna no UltimatePOS). Por isso o backend manda NÚMERO e quem
// escreve "ilimitado" é aqui — a decisão precisa do valor e do vocabulário PT-BR juntos.
//
// Criar e editar pacote (thread Superadmin 03) é drawer da grade, ESTADO da tela:
// `?pacote=novo` ou `?pacote=<id>`. Ele escreve `price` — a regra mestre de valor está no
// `PacoteForm` (envio com ponto e 2 casas) e no controller (`num_uf` nos dois caminhos).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useRef, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural } from '../_components/assinatura';
import { PacoteForm, PacoteFormEsqueleto, PacoteNaoEncontrado, type FormPacote } from './_components/PacoteForm';

interface Pacote {
  id: number;
  nome: string;
  descricao: string;
  preco: number;
  intervalo: string;
  intervalo_count: number;
  trial_dias: number;
  /** Os 4 limites vêm CRUS. `0` = ilimitado — ver `limite()` abaixo. */
  locais: number;
  usuarios: number;
  produtos: number;
  faturas: number;
  ativo: boolean;
  privado: boolean;
  avulso: boolean;
  modulos: string[];
  assinantes: number;
}

interface Props {
  pacotes?: Pacote[];
  /** `'novo'`, o id do pacote em edição, ou null com o drawer fechado. */
  editando?: 'novo' | number | null;
  formPacote?: FormPacote | null;
}

const ROTA = '/superadmin/packages';

// Formatador de moeda: o VALOR vem do payload, sempre. Não existe literal monetário neste
// arquivo — Tier 0 (memory/proibicoes.md).
const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Plural do intervalo por MAPA explícito, nunca concatenando "s".
 *
 * "mês" + "s" dá "mêss"; "mêses" (o erro comum) não existe em português. O F1 §2 cobra isso
 * nominalmente, e um mapa de 4 entradas é mais barato que a regra geral.
 */
const PLURAL_INTERVALO: Record<string, string> = {
  months: 'meses',
  years: 'anos',
  days: 'dias',
};

const INTERVALO_SINGULAR: Record<string, string> = {
  months: 'mês',
  years: 'ano',
  days: 'dia',
};

function ciclo(n: number, intervalo: string): string {
  if (n === 1) return INTERVALO_SINGULAR[intervalo] ?? intervalo;
  return `${n} ${PLURAL_INTERVALO[intervalo] ?? intervalo}`;
}

/**
 * `0` = ILIMITADO. É a convenção do UltimatePOS, escrita no COMMENT da própria coluna
 * (`location_count … '0 = infinite option.'`).
 *
 * Desenhar `0 locais` seria dizer o oposto do que o dado significa — e é exatamente a leitura
 * que qualquer pessoa faria sem esta função. Ela existe pra que a regra tenha um lugar só.
 */
function limite(n: number, sing: string, plur: string, ilimitado: string): string {
  return n === 0 ? ilimitado : plural(n, sing, plur);
}

function PacotesIndex({ pacotes, editando, formPacote }: Props) {
  const abrir = (alvo: 'novo' | number) =>
    router.get(ROTA, { pacote: alvo }, { only: ['editando', 'formPacote'], preserveState: true, preserveScroll: true, replace: true });
  const fechar = () =>
    router.get(ROTA, {}, { only: ['editando', 'formPacote'], preserveState: true, preserveScroll: true, replace: true });

  // Os atalhos leem a versão mais recente de abrir/fechar sem re-registrar o listener.
  const acoes = useRef({ abrir, fechar });
  acoes.current = { abrir, fechar };

  // `n` abre "Novo pacote" — o atalho do protótipo.
  useEffect(() => {
    const h = (e: KeyboardEvent) => {
      const alvo = (e.target as HTMLElement)?.tagName;
      if (alvo === 'INPUT' || alvo === 'TEXTAREA' || alvo === 'SELECT' || e.metaKey || e.ctrlKey) return;
      if (e.key === 'n') {
        e.preventDefault();
        acoes.current.abrir('novo');
      }
    };
    document.addEventListener('keydown', h);
    return () => document.removeEventListener('keydown', h);
  }, []);

  // `esc` fecha o drawer. Um Select aberto dentro dele fecha primeiro (o Radix marca o evento).
  useEffect(() => {
    if (editando === null || editando === undefined) return;
    const h = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !e.defaultPrevented) acoes.current.fechar();
    };
    document.addEventListener('keydown', h);
    return () => document.removeEventListener('keydown', h);
  }, [editando]);

  return (
    <div className="pb-8">
      <PageHeader
        title="Pacotes de assinatura"
        subtitle="A grade comercial da plataforma"
        actions={
          <Button size="sm" className="h-8 text-xs" onClick={() => abrir('novo')} data-contract="superadmin.pacotes.novo-botao">
            Novo pacote <kbd className="ml-1 rounded border px-1 text-[10px] opacity-70">n</kbd>
          </Button>
        }
      />

      <div className="px-6 pt-4" data-contract="superadmin.pacotes.grid">
        <Deferred data="pacotes" fallback={<GridEsqueleto />}>
          <Grid pacotes={pacotes} onEditar={(id) => abrir(id)} />
        </Deferred>
      </div>

      {editando !== null && editando !== undefined ? (
        <Deferred data="formPacote" fallback={<PacoteFormEsqueleto onFechar={fechar} />}>
          {formPacote ? (
            <PacoteForm key={String(editando)} dados={formPacote} onFechar={fechar} />
          ) : (
            <PacoteNaoEncontrado onFechar={fechar} />
          )}
        </Deferred>
      ) : null}
    </div>
  );
}

/* O <Deferred> segura o filho até a prop chegar; ele NÃO injeta — cada bloco recebe o valor. */

function GridEsqueleto() {
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      {[0, 1, 2, 3, 4, 5].map((i) => (
        <Skeleton key={i} className="h-72 w-full" />
      ))}
    </div>
  );
}

function Grid({ pacotes, onEditar }: { pacotes?: Pacote[]; onEditar: (id: number) => void }) {
  const lista = pacotes ?? [];

  if (lista.length === 0) {
    return (
      <Card>
        <CardContent className="p-0">
          <EmptyState
            title="Nenhum pacote cadastrado"
            description="Um pacote define preço, limites e módulos liberados. Enquanto não houver nenhum, nenhum negócio consegue assinar."
          />
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      {lista.map((p) => (
        <CartaoPacote key={p.id} pacote={p} onEditar={onEditar} />
      ))}
    </div>
  );
}

function CartaoPacote({ pacote: p, onEditar }: { pacote: Pacote; onEditar: (id: number) => void }) {
  return (
    <Card className={p.ativo ? undefined : 'opacity-60'}>
      <CardContent className="flex h-full flex-col gap-3 p-4">
        <header className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h3 className="truncate font-medium">{p.nome}</h3>
            <div className="flex flex-wrap gap-1 pt-1">
              {p.privado && <Badge variant="outline">privado</Badge>}
              {p.avulso && <Badge variant="outline">avulso</Badge>}
              <Badge variant={p.ativo ? 'default' : 'secondary'}>{p.ativo ? 'ativo' : 'inativo'}</Badge>
            </div>
          </div>
          <Button variant="outline" size="sm" className="h-7 shrink-0 text-[11px]" onClick={() => onEditar(p.id)} aria-label={`Editar o pacote ${p.nome}`}>
            Editar
          </Button>
        </header>

        <div className="flex items-baseline gap-1.5">
          <span className="text-2xl font-semibold tabular-nums">
            {p.preco === 0 ? 'Grátis' : moeda.format(p.preco)}
          </span>
          <span className="text-xs text-muted-foreground">
            {p.preco === 0 ? 'por' : '/'} {ciclo(p.intervalo_count, p.intervalo)}
          </span>
        </div>

        {/*
          "0 = ilimitado" — a linha que a tela não pode errar. Ver `limite()` e o charter
          §Anti-hooks: desenhar `0 locais` inverte o sentido do dado.
        */}
        <ul className="flex flex-col gap-0.5 text-xs text-muted-foreground">
          <li>{limite(p.locais, 'local', 'locais', 'locais ilimitados')}</li>
          <li>{limite(p.usuarios, 'usuário', 'usuários', 'usuários ilimitados')}</li>
          <li>{limite(p.produtos, 'produto', 'produtos', 'produtos ilimitados')}</li>
          <li>{limite(p.faturas, 'fatura', 'faturas', 'faturas ilimitadas')}</li>
          {p.trial_dias > 0 && <li>{plural(p.trial_dias, 'dia de teste', 'dias de teste')}</li>}
        </ul>

        {/* Pacote sem módulo liberado: a seção SOME, não fica um bloco vazio. */}
        {p.modulos.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {p.modulos.map((m) => (
              <Badge key={m} variant="secondary" className="font-normal">
                {m}
              </Badge>
            ))}
          </div>
        )}

        <footer className="mt-auto flex flex-col gap-1 border-t pt-3">
          {p.descricao && <p className="text-[11px] text-muted-foreground">{p.descricao}</p>}
          <span className="text-[11px] font-medium tabular-nums">
            {plural(p.assinantes, 'assinante', 'assinantes')}
          </span>
        </footer>
      </CardContent>
    </Card>
  );
}

PacotesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PacotesIndex;
