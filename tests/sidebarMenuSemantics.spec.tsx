// Semântica de MENU da sidebar — o seletor de empresa e o menu do usuário
// anunciam que abrem um menu, e o estado aberto/fechado é VERDADEIRO.
//
// ── Por que este arquivo existe ──────────────────────────────────────────────
//
// A thread 01 do playbook SINCRONIZAR Sidebar deu ao protótipo Cowork
// (`prototipo-ui/cowork/Wagner/sidebar.jsx`) a semântica de menu nos três papéis abaixo.
// Medido no vivo logado em 2026-09-11 (dark, 2560px), o shell do ERP não tinha
// NENHUM deles — os seis atributos voltavam `null`, e o dropdown de empresa
// renderizava `<div>` com `onClick`, que leitor de tela não alcança e teclado
// não aciona. Por ADR UI-0029 isso é DEFEITO, não pauta: o protótipo manda na
// forma, e a comparação é por PAPEL, nunca por classe CSS.
//
// ── O que o teste trava, e por que NÃO basta checar presença ─────────────────
//
// `aria-expanded` é o caso de escola do presence-gate (LC-11): um atributo
// escrito à mão como `aria-expanded="false"` fixo passa em qualquer assert de
// presença e MENTE para a tecnologia assistiva em 100% das aberturas. Um
// `getAttribute` isolado não distingue "reflete o estado" de "constante que
// ninguém atualiza".
//
// Por isso todo assert aqui é RELACIONAL — mede a transição, não o valor:
//
//     fechado --click--> aberto --click--> fechado
//        false            true             false
//
// Atributo estático reprova na segunda linha. É a mesma razão de o teste
// assertar `tagName === 'BUTTON'` nos itens do dropdown: `role="menuitemradio"`
// num `<div>` é ARIA que promete foco e ativação por teclado sem entregar
// nenhum dos dois (LC-15 no eixo a11y) — o role só é verdadeiro sobre um
// controle real.
//
// ── O que este arquivo NÃO é ────────────────────────────────────────────────
//
// Não é prova de LAYOUT. `vitest.config.ts` roda com `css: false`, então nada
// aqui mede cascata. O risco de cascata desta mudança (trocar a tag muda quem
// vence: o UA declara font-size/width/background em `<button>`, e declaração do
// UA vence HERANÇA) está tratado no reset `button:where(...)` do `cockpit.css`,
// com a especificidade calculada no comentário daquela regra. Layout se mede no
// DOM renderizado, não aqui.

import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { CompanyPicker, SidebarFooter } from '@/Components/cockpit/Sidebar';
import type { BusinessOpt } from '@/Components/cockpit/shared';

afterEach(cleanup);

const EMPRESAS: BusinessOpt[] = [
  { id: 1, nome: 'Oimpresso Matriz', iniciais: 'OM', ativa: true },
  { id: 2, nome: 'Filial Centro', iniciais: 'FC', ativa: false },
  { id: 3, nome: 'Filial Sul', iniciais: 'FS', ativa: false },
];

function renderFooter() {
  return render(
    <SidebarFooter
      nome="Wagner Rocha Araujo"
      email="wagner@oimpresso.com.br"
      cargo="Administrador"
      iniciais="WR"
      superadminItems={[]}
      userMenuItems={[]}
    />,
  );
}

describe('seletor de empresa — anuncia menu e reflete o estado', () => {
  it('o botão declara que abre um MENU e se nomeia pela empresa atual', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);

    const botao = screen.getByRole('button', { name: /trocar de empresa/i });

    expect(botao.getAttribute('aria-haspopup')).toBe('menu');
    // O nome acessível carrega a empresa ATIVA — sem ele o botão é só "chevron".
    expect(botao.getAttribute('aria-label')).toBe(
      'Empresa: Oimpresso Matriz. Trocar de empresa',
    );
  });

  it('aria-expanded ALTERNA (false -> true -> false), não é constante', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);

    const botao = screen.getByRole('button', { name: /trocar de empresa/i });

    expect(botao.getAttribute('aria-expanded')).toBe('false');

    fireEvent.click(botao);
    expect(botao.getAttribute('aria-expanded')).toBe('true');

    // A volta é o que um atributo escrito à mão não sobrevive.
    fireEvent.click(botao);
    expect(botao.getAttribute('aria-expanded')).toBe('false');
  });

  it('o dropdown só existe aberto, e aberto ele É um menu', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);

    expect(screen.queryByRole('menu')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: /trocar de empresa/i }));

    expect(screen.getByRole('menu')).not.toBeNull();
  });

  it('cada empresa é um menuitemradio, e só a ativa está aria-checked', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);
    fireEvent.click(screen.getByRole('button', { name: /trocar de empresa/i }));

    const itens = screen.getAllByRole('menuitemradio');

    // Contagem derivada da fixture — não cravada — pra fixture nova não
    // reprovar por estar desatualizada (§5 2026-07-17).
    expect(itens).toHaveLength(EMPRESAS.length);

    const marcados = itens.filter((i) => i.getAttribute('aria-checked') === 'true');
    expect(marcados).toHaveLength(1);
    expect(marcados[0]?.textContent).toContain('Oimpresso Matriz');
  });

  it('o role de menuitem está sobre um CONTROLE real (focável e acionável)', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);
    fireEvent.click(screen.getByRole('button', { name: /trocar de empresa/i }));

    // `<div role="menuitemradio">` passaria em qualquer assert de role e seria
    // inalcançável por teclado — o role tem de morar num <button>.
    for (const item of screen.getAllByRole('menuitemradio')) {
      expect(item.tagName).toBe('BUTTON');
    }
    expect(screen.getByRole('menuitem', { name: /adicionar empresa/i }).tagName).toBe(
      'BUTTON',
    );

    // Focável de verdade: jsdom só move o foco pra quem o aceita.
    const [primeiro] = screen.getAllByRole('menuitemradio');
    expect(primeiro).toBeDefined();
    primeiro?.focus();
    expect(document.activeElement).toBe(primeiro);
  });

  // ── "+ Adicionar empresa": a promessa que o item NÃO pode cumprir ──────
  //
  // O item nasceu `role="menuitem"` e SEM handler (medido no main em 2026-09-13):
  // anunciado como acionável, inerte ao clique — LC-15 no eixo a11y, o mesmo
  // defeito que o assert de `tagName === 'BUTTON'` acima trava por outra porta.
  //
  // O destino EXISTE (`/superadmin/business`, com `create`), mas quem pode abri-lo
  // é decidido pelo middleware `superadmin`, que compara o `username` contra
  // `config('constants.administrator_usernames')` — regra que NÃO chega ao front
  // (`props.auth.can` traz só as 5 chaves do Ponto; `superadminItems` filtra LABELS
  // de outros módulos, cada um com permissão própria). Sem sinal confiável, navegar
  // daria 403 pra maioria — o honesto é declarar-se desabilitado COM o motivo.
  //
  // Os asserts medem COMPORTAMENTO, não presença (LC-11): o 2º caso é o que separa
  // `aria-disabled` (focável, tooltip vivo) de `disabled` nativo (nem um, nem outro).

  it('o rodapé não promete o que não pode cumprir — declara-se desabilitado e diz por quê', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);
    fireEvent.click(screen.getByRole('button', { name: /trocar de empresa/i }));

    const adicionar = screen.getByRole('menuitem', { name: /adicionar empresa/i });

    expect(adicionar.getAttribute('aria-disabled')).toBe('true');

    // "Desabilitado" sem porquê é beco sem saída. Não cravo a FRASE — cravo que ela
    // diz ONDE a ação vive, pra reescrita de copy não reprovar por estar viva.
    expect(adicionar.getAttribute('title') ?? '').toMatch(/superadmin/i);
  });

  it('desabilitado por ARIA, não pelo `disabled` do HTML — segue focável', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);
    fireEvent.click(screen.getByRole('button', { name: /trocar de empresa/i }));

    const adicionar = screen.getByRole('menuitem', { name: /adicionar empresa/i });

    // `disabled` nativo tiraria o item do foco E suprimiria o tooltip do `title`
    // no Chrome/Firefox — matando justamente o motivo. O padrão ARIA manda manter
    // menuitem desabilitado alcançável.
    expect((adicionar as HTMLButtonElement).disabled).toBe(false);

    adicionar.focus();
    expect(document.activeElement).toBe(adicionar);
  });

  it('clicar no item desabilitado é inerte — o menu continua aberto', () => {
    render(<CompanyPicker businesses={EMPRESAS} fallbackNome="Oimpresso" />);
    fireEvent.click(screen.getByRole('button', { name: /trocar de empresa/i }));

    fireEvent.click(screen.getByRole('menuitem', { name: /adicionar empresa/i }));

    // Se um dia o item ganhar destino, ESTE assert cai primeiro — e cair aqui é o
    // sinal de revisar o contrato, nunca de apagar o teste.
    expect(screen.getByRole('menu')).not.toBeNull();
  });
});

describe('menu do usuário (rodapé) — anuncia menu e reflete o estado', () => {
  it('o botão do rodapé declara que abre um MENU', () => {
    renderFooter();

    const botao = screen.getByRole('button', { name: /wagner rocha/i });
    expect(botao.getAttribute('aria-haspopup')).toBe('menu');
  });

  it('aria-expanded ALTERNA (false -> true -> false), não é constante', () => {
    renderFooter();

    const botao = screen.getByRole('button', { name: /wagner rocha/i });

    expect(botao.getAttribute('aria-expanded')).toBe('false');

    fireEvent.click(botao);
    expect(botao.getAttribute('aria-expanded')).toBe('true');

    fireEvent.click(botao);
    expect(botao.getAttribute('aria-expanded')).toBe('false');
  });
});
