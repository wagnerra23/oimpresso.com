/**
 * shared/Toolbar — atomo do DS (thread 03 do playbook ds-atomos).
 *
 * Prova as 7 checagens do bloco D do playbook, com uma fronteira declarada:
 * o vitest roda jsdom com `css: false` (vitest.config.ts), entao NAO existe
 * layout computado aqui — `getComputedStyle` nao resolveria `gap-2` em 8px.
 * Por isso os numeros do bloco C (1215x71px, gap 8, pad 9x12) sao provados no
 * RUNTIME pelo PROTOCOLO-COMPARACAO-RUNTIME (D2), nunca por este arquivo.
 * O que este arquivo prova e o que jsdom sabe responder: contrato de API,
 * arvore de zonas, semantica ARIA e — a checagem que mais importa — o spacer
 * unico (D-6), que e comportamento puro e independe de CSS.
 */
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import axe from 'axe-core';

import Toolbar, { ToolbarSpacer } from '@/Components/shared/Toolbar';
import PageFilters, { FilterChip } from '@/Components/shared/PageFilters';

afterEach(cleanup);

const barra = () => screen.getByRole('toolbar');
const spacers = (el: HTMLElement) => el.querySelectorAll('[data-slot="toolbar-spacer"]');

describe('Toolbar — contrato de 3 zonas (D-1)', () => {
  it('renderiza role=toolbar com nome acessivel e as tres zonas', () => {
    render(
      <Toolbar
        label="Acoes da lista"
        left={<button type="button">Filtrar</button>}
        center={<span>centro</span>}
        right={<button type="button">Exportar</button>}
      />,
    );

    const el = barra();
    expect(el.getAttribute('aria-label')).toBe('Acoes da lista');
    expect(screen.getByRole('button', { name: 'Filtrar' })).toBeTruthy();
    expect(screen.getByText('centro')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Exportar' })).toBeTruthy();
  });

  it('aceita children soltos (o uso real do Ponto poe tudo em children)', () => {
    render(
      <Toolbar label="Filtros da competencia">
        <select aria-label="Mes de referencia" />
        <input aria-label="Buscar" />
      </Toolbar>,
    );

    expect(screen.getByLabelText('Mes de referencia')).toBeTruthy();
    expect(screen.getByLabelText('Buscar')).toBeTruthy();
  });
});

describe('Toolbar — spacer unico (D-6)', () => {
  it('emite UM spacer quando nao ha center (e o que cola o right na borda)', () => {
    render(<Toolbar label="Barra" right={<button type="button">Exportar</button>} />);
    expect(spacers(barra())).toHaveLength(1);
  });

  it('NAO soma o proprio spacer quando o consumidor ja passa um', () => {
    render(
      <Toolbar label="Barra">
        <button type="button">Novo</button>
        <ToolbarSpacer />
        <button type="button">Exportar</button>
      </Toolbar>,
    );
    // Dois spacers dividiriam a folga e o ultimo filho nao colaria na borda.
    expect(spacers(barra())).toHaveLength(1);
  });

  it('NAO emite spacer quando ha center (a zona central ja ocupa a folga)', () => {
    render(<Toolbar label="Barra" center={<span>centro</span>} />);
    expect(spacers(barra())).toHaveLength(0);
  });

  it('o spacer nao entra na arvore de acessibilidade nem altera a faixa', () => {
    render(<Toolbar label="Barra" />);
    const sp = spacers(barra())[0];
    expect(sp.getAttribute('aria-hidden')).toBe('true');
    expect(sp.textContent).toBe('');
  });
});

describe('Toolbar — bordered / dense / tone (D-3)', () => {
  it('bordered default desenha a borda de baixo (uso em cabecalho de painel)', () => {
    render(<Toolbar label="Barra" />);
    expect(barra().className).toContain('border-b');
    expect(barra().className).not.toContain('border-b-0');
  });

  it('bordered=false nao desenha borda — evita a borda dupla dentro de moldura do pai', () => {
    render(<Toolbar label="Barra" bordered={false} />);
    expect(barra().className).toContain('border-b-0');
  });

  it('dense troca o padding da faixa, e o default mantem 9px 12px', () => {
    const { rerender } = render(<Toolbar label="Barra" />);
    expect(barra().className).toContain('py-[9px]');

    rerender(<Toolbar label="Barra" dense />);
    expect(barra().className).toContain('py-1.5');
    expect(barra().className).not.toContain('py-[9px]');
  });

  it('tone escolhe a superficie por token do tema, sem CSS proprio', () => {
    const { rerender } = render(<Toolbar label="Barra" />);
    expect(barra().className).toContain('bg-card');

    rerender(<Toolbar label="Barra" tone="muted" />);
    expect(barra().className).toContain('bg-muted');

    rerender(<Toolbar label="Barra" tone="transparent" />);
    expect(barra().className).toContain('bg-transparent');
  });

  it('wrap fica ligado: o reflow abaixo de ~900px e comportamento correto, nao defeito (D-5)', () => {
    render(<Toolbar label="Barra" />);
    expect(barra().className).toContain('flex-wrap');
  });
});

describe('Toolbar — a11y runtime (axe em jsdom: ARIA/estrutura, nao contraste)', () => {
  it('nao tem violacao serious/critical', async () => {
    const { container } = render(
      <Toolbar label="Acoes da lista" left={<button type="button">Filtrar</button>} right={<button type="button">Exportar</button>} />,
    );
    const r = await axe.run(container, { resultTypes: ['violations'] });
    const graves = r.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(graves.map((v) => v.id)).toEqual([]);
  });
});

describe('guarda: PageFilters segue intacto (D-4)', () => {
  it('continua exportando default + FilterChip e renderizando os proprios chips', () => {
    expect(typeof PageFilters).toBe('function');
    expect(typeof FilterChip).toBe('function');

    render(
      <PageFilters activeChips={[{ label: 'Mes: Abril/2026', onRemove: () => {} }]}>
        <input aria-label="campo" />
      </PageFilters>,
    );

    expect(screen.getByText('Mes: Abril/2026')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Remover filtro: Mes: Abril/2026' })).toBeTruthy();
    // O PageFilters NAO e uma toolbar — se algum dia virar, esta thread foi violada.
    expect(screen.queryByRole('toolbar')).toBeNull();
  });
});
