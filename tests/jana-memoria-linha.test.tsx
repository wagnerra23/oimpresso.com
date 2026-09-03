// Memória da Jana — a LINHA do fato (`/ia/memoria`).
//
// Fonte: âncora `prototipo-ui/cowork/jana-merge.jsx` §`JmMemoria` + CSS `.jm-fato`
// (`jana-merge.css`), e o `Memoria.charter.md`. NÃO derivado do `.tsx` — derivar do
// código é tautológico e passaria verde com o comportamento errado (§5 2026-06-05).
//
// Por que jsdom e não Pest: os 8 UCs de servidor desta tela (motivo obrigatório,
// trilha, PII, Tier 0) moram em `MemoriaEdicaoMotivoTest`/`MemoriaPermissaoTest` e
// nenhum deles toca a linha renderizada. O que estes dois casos travam é client-side —
// Pest de Controller não morde ordem de DOM nem rótulo de botão.
//
// MORDIDA PROVADA (mutação, antes do merge — ver o PR):
//   trocar os dois botões de texto por `<Pencil/>`/`<Trash2/>`  → UC-MEM-09 falha (2)
//   mover a meta pra ANTES do `<p>` do fato                     → UC-MEM-10 falha (1)
//
// ⚠️ Por que NÃO há caso de LARGURA aqui, embora a onda 4 a tenha mudado: largura é
// propriedade COMPUTADA, e jsdom não computa Tailwind. Assertar a ausência da classe
// `max-w-4xl` mediria o que eu escrevi, não o que o browser resolveu (§5 2026-07-16).
// Quem mede isso é o `visual-regression` (esta tela está no `visreg-screens.json`) e a
// sonda do `design-diff.mjs` — registrados no `Memoria-visual-comparison.md`.

import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, within } from '@testing-library/react';

vi.mock('@inertiajs/react', () => {
  const useForm = (inicial: Record<string, string>) => {
    const [data, set] = React.useState(inicial);
    return {
      data,
      setData: (k: string, v: string) => set((d) => ({ ...d, [k]: v })),
      patch: vi.fn(),
      processing: false,
      reset: () => set(inicial),
      errors: {} as Record<string, string>,
      clearErrors: vi.fn(),
    };
  };
  return {
    useForm,
    router: { visit: vi.fn(), delete: vi.fn(), reload: vi.fn() },
    usePage: () => ({ props: {} }),
    Link: ({ children }: { children?: React.ReactNode }) => <a>{children}</a>,
    Head: () => null,
  };
});

import Memoria from '@/Pages/Jana/Memoria';

const FATO = {
  id: 7,
  business_id: 1,
  user_id: 1,
  fato: 'O caixa é fechado toda sexta antes das 18h.',
  metadata: { categoria: 'preferencia', relevancia: 6, origem: 'chat' },
  valid_from: '2026-03-12T09:14:00-03:00',
  valid_until: null,
  score: null,
};

const props = {
  memorias: [FATO],
  businessId: 1,
  userId: 1,
  janaContext: { businessId: 1, businessName: 'Empresa Teste', userName: 'Titular' },
};

beforeEach(() => { render(<Memoria {...props} />); });
afterEach(cleanup);

describe('UC-MEM-09 · as ações da linha do fato se anunciam por TEXTO', () => {
  // Âncora: `.jm-fato-acts` são `<button className="jm-btn ghost">Editar</button>` e
  // `<button className="jm-btn ghost danger">Apagar</button>` — rótulo visível.
  // Charter: a tela existe pro titular EXERCER o Art. 18; ação destrutiva que só se
  // identifica no hover não se anuncia a quem vai clicá-la.
  //
  // O assert é de `textContent`, não de nome acessível, e é isso que faz o caso morder:
  // um botão-ícone com `title="Esquecer"` TEM nome acessível e passaria por `getByRole`
  // sem ter rótulo nenhum na tela.
  it('o "Editar" é texto visível, não ícone mudo', () => {
    const b = screen.getByRole('button', { name: /^editar$/i });
    expect(b.textContent?.trim()).toBe('Editar');
  });

  it('o "Apagar" é texto visível, não ícone mudo', () => {
    const b = screen.getByRole('button', { name: /^apagar$/i });
    expect(b.textContent?.trim()).toBe('Apagar');
  });
});

describe('UC-MEM-10 · a linha apresenta o FATO antes da meta que o qualifica', () => {
  // Âncora: `.jm-fato-bd` é `<p>{f.fato}</p>` e SÓ DEPOIS `.jm-fato-meta`. A produção
  // trazia invertido (pill/relevância/origem/data no topo, texto embaixo) — a linha
  // abria pelo rótulo em vez de pelo que a Jana aprendeu.
  // A linha inteira, escopada pelo texto do fato. Necessário porque "Preferência"
  // aparece DUAS vezes na tela: como pill da linha e como chip do filtro (os chips são
  // derivados do dado). Buscar no documento inteiro casaria as duas.
  const linhaDoFato = () =>
    screen.getByText(FATO.fato).closest('[data-slot="box"]') as HTMLElement;

  it('o texto do fato vem antes da categoria no DOM', () => {
    const texto = screen.getByText(FATO.fato);
    const pill = within(linhaDoFato()).getByText('Preferência');
    // DOCUMENT_POSITION_FOLLOWING = 4 → a pill vem DEPOIS do texto.
    expect(texto.compareDocumentPosition(pill) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('a meta traz origem, data e relevância na mesma linha da categoria', () => {
    const pill = within(linhaDoFato()).getByText('Preferência');
    const meta = pill.parentElement as HTMLElement;
    expect(within(meta).getByText(/origem: chat/i)).toBeTruthy();
    expect(within(meta).getByText(/^desde /i)).toBeTruthy();
    expect(within(meta).getByText(/relevância 6\/10/i)).toBeTruthy();
  });
});
