/**
 * `shared/KpiCard` — a variante `filter` é ADITIVA, e é isso que estes casos provam.
 *
 * O componente é consumido por 40 arquivos (`git grep -l 'shared/KpiCard' origin/main`, medido
 * 2026-09-13), quase todos fora do módulo que pediu a variante. A guarda não é cerimônia: a lei
 * da mudança é "quem não pedir `filter` não muda de byte", e isso precisa de um caso que caia
 * se alguém puser `variant` em `defaultVariants` ou mexer no corpo default "de passagem".
 *
 * ⚠️ Sobre o que a guarda NÃO é: ela não compara contra um HTML congelado de `origin/main`.
 * Um baseline assim seria copiado da implementação atual — teste derivado do código, que o
 * §5 do projeto cataloga como tautológico (2026-06-05). O que se asserta aqui é CONTRATO
 * observável — o que os 40 consumidores de fato dependem: o eixo da caixa, o degrau da ramp
 * no valor, e a ausência de qualquer marca da variante.
 *
 * ⚠️ Os valores de fixture NÃO levam `R$` — não é descuido, e restaurar o prefixo faz o
 * `brl-scan` acender (medido 2026-09-13: 3 linhas acusadas). O scanner varre `.tsx` também, e
 * nenhum assert daqui depende do conteúdo do `value` — o que se mede é classe e estrutura.
 * A saída de allowlist que o próprio scanner oferece foi recusada de propósito: lista de
 * exceção que só cresce vira allowlist (§5 2026-08-02), e aqui não havia o que excetuar.
 */
import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

// Icon determinístico: o que importa aqui é ONDE ele cai e com que placa, não o glifo.
vi.mock('@/Components/Icon', () => ({
  Icon: ({ name, size }: { name: string; size?: number }) => (
    <i data-testid="icon" data-icon={name} data-size={size} />
  ),
}));

import KpiCard from '@/Components/shared/KpiCard';

const card = (el: HTMLElement) => el.querySelector('[data-slot="kpi-card"]') as HTMLElement;

// Classes que SÓ a variante filter introduz. Se alguma vazar pro default, o default mudou.
const MARCAS_DO_FILTER = ['flex-row', 'rounded-lg', 'p-3'];

describe('KpiCard — guarda do default (os 40 consumidores)', () => {
  it('sem `variant`, não carimba data-variant nem nenhuma marca do filter', () => {
    const { container } = render(
      <KpiCard label="Backups guardados" value={5} description="de 5 · a retenção apaga o resto" />,
    );
    const el = card(container);

    expect(el.getAttribute('data-variant')).toBeNull();
    for (const marca of MARCAS_DO_FILTER) {
      expect(
        el.className.split(/\s+/),
        `a classe "${marca}" é da variante filter e não pode aparecer no default`,
      ).not.toContain(marca);
    }
    // O eixo da caixa default: coluna, radius grande, padding 4.
    expect(el.className.split(/\s+/)).toContain('flex-col');
    expect(el.className.split(/\s+/)).toContain('rounded-xl');
    expect(el.className.split(/\s+/)).toContain('p-4');
  });

  it('sem `variant`, o valor fica no degrau --fs-7 da ramp (não no --fs-6 do filter)', () => {
    const { container } = render(<KpiCard label="Espaço ocupado" value="256 MB" />);
    expect(container.innerHTML).toContain('var(--fs-7)');
    expect(container.innerHTML).not.toContain('var(--fs-6)');
  });

  it.each(['default', 'success', 'warning', 'danger', 'info'] as const)(
    'tone=%s sobrevive sem `variant` — o eixo semântico não foi remapeado',
    (tone) => {
      const { container } = render(<KpiCard label="Saldo previsto" value="0,00" tone={tone} />);
      const el = card(container);
      expect(el.getAttribute('data-tone')).toBe(tone);
      expect(el.getAttribute('data-variant')).toBeNull();
      expect(el.className.split(/\s+/)).toContain('flex-col');
    },
  );

  it('passar `variant={undefined}` explicitamente é idêntico a não passar', () => {
    const props = { label: 'Último backup', value: '19/08', description: 'há 2 dias' } as const;
    const a = render(<KpiCard {...props} />).container.innerHTML;
    const b = render(<KpiCard {...props} variant={undefined} />).container.innerHTML;
    expect(b).toBe(a);
  });

  it('consumidor real (Financeiro/Unificado): onClick+tone já existiam e seguem intactos', () => {
    // Uso literal de `Pages/Financeiro/Unificado/Index.tsx:1031` — clicável ANTES desta variante.
    const { container } = render(
      <KpiCard
        icon="wallet"
        tone="success"
        label="Saldo previsto"
        value="1.234,00"
        description="Final do período"
        onClick={() => {}}
      />,
    );
    const el = card(container);
    expect(el.tagName).toBe('BUTTON');
    expect(el.getAttribute('data-variant')).toBeNull();
    expect(el.className.split(/\s+/)).toContain('flex-col');
  });
});

describe('KpiCard — variant="filter" (o tile do protótipo)', () => {
  it('vira linha: flex-row, rounded-lg, p-3, gap-3 — e se declara no DOM', () => {
    const { container } = render(<KpiCard variant="filter" label="Pendentes" value={12} />);
    const el = card(container);
    expect(el.getAttribute('data-variant')).toBe('filter');
    const cls = el.className.split(/\s+/);
    for (const marca of [...MARCAS_DO_FILTER, 'gap-3']) expect(cls).toContain(marca);
    // twMerge tem de ter derrubado os eixos conflitantes do base/size.
    expect(cls).not.toContain('flex-col');
    expect(cls).not.toContain('rounded-xl');
    expect(cls).not.toContain('p-4');
  });

  it('o valor cai um degrau da ramp: --fs-6 (18px), o da âncora', () => {
    const { container } = render(<KpiCard variant="filter" label="Atrasos" value={3} />);
    expect(container.innerHTML).toContain('var(--fs-6)');
    expect(container.innerHTML).not.toContain('var(--fs-7)');
  });

  it('o label NÃO muda sob filter — segue a ADR 0110 (11px/600 uppercase muted)', () => {
    // Este caso existe por causa do `D-KPI-LABEL`: o playbook descrevia o alvo como
    // "13.3px/400 em accent" e nenhuma fonte do repo produz isso (medido 2026-09-13).
    // Se alguém "corrigir" o label pro accent, cai aqui.
    const { getByText } = render(<KpiCard variant="filter" label="Presentes" value={42} />);
    // Localizado pelo TEXTO, não pela classe: um seletor `span.uppercase` sumiria junto com a
    // mutação que este caso existe pra pegar, e o teste cairia por TypeError em vez de por
    // assert de contrato — falha de natureza errada não prova nada (§5 2026-09-05).
    const cls = getByText('Presentes').className.split(/\s+/);
    expect(cls).toContain('text-[11px]');
    expect(cls).toContain('font-semibold');
    expect(cls).toContain('text-muted-foreground');
    // Copy de contrato quebra, não trunca (lição medida em prod 2026-08-24).
    expect(cls).toContain('break-words');
    expect(cls).not.toContain('truncate');
  });

  it.each([
    ['primary', 'bg-primary/15'],
    ['amber', 'bg-warning/15'],
    ['rose', 'bg-destructive/15'],
    ['emerald', 'bg-success/15'],
    ['violet', 'bg-primary/15'],
  ] as const)('filterTone=%s pinta a placa do ícone com token, não cor crua', (filterTone, esperado) => {
    const { container } = render(
      <KpiCard variant="filter" filterTone={filterTone} icon="chart" label="Total" value={1} />,
    );
    const ic = container.querySelector('[data-testid="icon"]');
    expect(ic, 'o ícone sumiu — sem ele não há placa pra medir').not.toBeNull();
    const placa = ic!.parentElement as HTMLElement;
    expect(placa.className.split(/\s+/)).toContain(esperado);
    expect(placa.className.split(/\s+/)).toContain('h-9');
    // Zero cor crua: nenhum oklch()/hex literal no markup da variante.
    expect(container.innerHTML).not.toMatch(/oklch\(|#[0-9a-fA-F]{6}/);
  });

  it('sem filterTone cai em primary — o mesmo default da âncora', () => {
    const { container } = render(<KpiCard variant="filter" icon="chart" label="Total" value={1} />);
    const placa = container.querySelector('[data-testid="icon"]')!.parentElement as HTMLElement;
    expect(placa.className.split(/\s+/)).toContain('bg-primary/15');
  });

  it('clicável: button + type=button + anel de foco', () => {
    const { container } = render(
      <KpiCard variant="filter" label="Atrasados" value={7} onClick={() => {}} />,
    );
    const el = card(container);
    expect(el.tagName).toBe('BUTTON');
    expect(el.getAttribute('type')).toBe('button');
    expect(el.className).toContain('focus-visible:ring-2');
  });

  it('aria-pressed: "true"/"false" quando `selected` é booleano — AUSENTE quando não é passado', () => {
    // ⚠️ GAP DE A11Y HERDADO, pinado aqui de propósito — não é efeito desta variante.
    // `aria-pressed={selected}` com `selected === undefined` faz o React OMITIR o atributo, e
    // um <button> clicável sem `aria-pressed` não se anuncia como toggle pro leitor de tela.
    // Consertar seria `aria-pressed={!!selected}` — uma linha — mas isso passaria a carimbar
    // `aria-pressed="false"` em consumidores que hoje não têm o atributo (medido:
    // `Pages/Financeiro/Unificado/Index.tsx:1031` usa `onClick` SEM `selected`), ou seja,
    // mudaria o markup de quem não pediu nada. É o oposto da lei desta mudança, então fica
    // FORA deste PR e vira decisão de quem tocar o eixo a11y do componente.
    const { container, rerender } = render(
      <KpiCard variant="filter" label="Atrasados" value={7} onClick={() => {}} />,
    );
    expect(card(container).getAttribute('aria-pressed')).toBeNull();

    rerender(
      <KpiCard variant="filter" label="Atrasados" value={7} selected={false} onClick={() => {}} />,
    );
    expect(card(container).getAttribute('aria-pressed')).toBe('false');

    rerender(<KpiCard variant="filter" label="Atrasados" value={7} selected onClick={() => {}} />);
    expect(card(container).getAttribute('aria-pressed')).toBe('true');
  });

  it('o toggle é reversível: o componente não impede desligar o selecionado', () => {
    // Invariante 2 do playbook — a lógica é da tela, mas o componente não pode travar.
    const onClick = vi.fn();
    const { container } = render(
      <KpiCard variant="filter" label="Faltas" value={2} selected onClick={onClick} />,
    );
    card(container).click();
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('sem onClick, o tile de filtro é div — não inventa button sem ação', () => {
    const { container } = render(<KpiCard variant="filter" label="Só leitura" value={9} />);
    expect(card(container).tagName).toBe('DIV');
  });

  it('`description` vira a sub-linha da âncora, e `delta` não é descartado em silêncio', () => {
    const { container } = render(
      <KpiCard
        variant="filter"
        label="Recebido"
        value="900,00"
        description="12 baixas"
        delta={{ value: 3, label: 'vs ontem' }}
      />,
    );
    expect(container.textContent).toContain('12 baixas');
    expect(container.textContent).toContain('vs ontem');
    expect(container.innerHTML).toContain('var(--fs-1)');
  });

  it('a regra do danger (valor carrega o tom) vale sob filter também', () => {
    const { container } = render(
      <KpiCard variant="filter" tone="danger" label="Vencido" value="50,00" />,
    );
    const valor = container.querySelector('.tabular-nums') as HTMLElement;
    expect(valor.className.split(/\s+/)).toContain('text-destructive');
  });
});
