// @memcofre
//   tela: /superadmin/packages
//   module: Superadmin
//   stories: SA-O4c (Blade/AdminLTE → Inertia)
//   permissao: superadmin
//
// Grade comercial da plataforma. Responde: "o que estamos vendendo?".
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/superadmin-page.jsx → ViewPacotes() (L1176)
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
// Esta onda é LEITURA. O FormDrawer do F1 (UC-SA-010/011) é a SA-O4d: ele escreve `price`, e
// isso exige a REGRA MESTRE de memory/proibicoes.md antes de existir.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural } from '../_components/assinatura';

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
}

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

function PacotesIndex({ pacotes }: Props) {
  return (
    <div className="pb-8">
      <PageHeader title="Pacotes de assinatura" moduleNav description="A grade comercial da plataforma" />

      <div className="px-6 pt-4" data-contract="superadmin.pacotes.grid">
        <Deferred data="pacotes" fallback={<GridEsqueleto />}>
          <Grid pacotes={pacotes} />
        </Deferred>
      </div>
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

function Grid({ pacotes }: { pacotes?: Pacote[] }) {
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
        <CartaoPacote key={p.id} pacote={p} />
      ))}
    </div>
  );
}

function CartaoPacote({ pacote: p }: { pacote: Pacote }) {
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
