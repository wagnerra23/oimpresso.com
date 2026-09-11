/**
 * `ForjaRunbook` — o painel "Trilhas de papel" da /forja/trabalho.
 *
 * Cita: UC-TRAB-18 (Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.casos.md).
 *
 * Por que este teste é de RENDER, e por que ele olha a DERIVAÇÃO e não o texto:
 * o risco desta tela não é ela desenhar errado — é ela ENSINAR errado. O painel é
 * onboarding: quem chega novo (humano ou agente) lê aqui quem faz o quê. Um texto
 * congelado no arquivo fica certo hoje e mente no dia em que um papel entra, sai
 * ou muda de fase — e mente com cara de canon, que é o pior modo de errar.
 *
 * Então os dois casos centrais abaixo mudam `PAPEIS` em tempo de teste e exigem que
 * a tela acompanhe. Um painel com a lista escrita à mão passa no "renderiza" e
 * REPROVA aqui, que é exatamente o ponto.
 *
 * Guarda anti-falso-verde: o primeiro caso falha se `PAPEIS` vier vazio — sem ela,
 * "renderizou tantos quanto a fonte" ficaria verde comparando zero com zero.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

import ForjaRunbook from '../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/ForjaRunbook';
import { FASE_HUE, PAPEIS } from '../../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/trabalhoTokens';

describe('UC-TRAB-18 · ForjaRunbook — o painel deriva da fonte viva, não de texto escrito', () => {
  it('lista exatamente os papéis de PAPEIS (não uma cópia congelada)', () => {
    const nomes = Object.keys(PAPEIS);
    // Anti-falso-verde: se a fonte esvaziar, o caso abaixo compararia 0 com 0.
    expect(nomes.length).toBeGreaterThan(0);

    render(<ForjaRunbook onClose={vi.fn()} />);

    const itens = screen.getByTestId('forja-runbook-papeis').querySelectorAll('li');
    expect(itens).toHaveLength(nomes.length);
    // O título conta pela fonte — o protótipo escreve "6 papéis" literal, e o
    // main tem 7. Número à mão apodrece no primeiro papel novo.
    expect(screen.getByText(`${nomes.length} papéis`)).toBeTruthy();
  });

  it('acompanha um papel NOVO sem editar o componente', () => {
    const papel = 'ZZ';
    expect(PAPEIS[papel]).toBeUndefined(); // o teste não pode colidir com a fonte real

    const antes = Object.keys(PAPEIS).length;
    PAPEIS[papel] = { nome: 'Papel de teste', agente: true, cor: 'oklch(0.5 0.1 200)', desc: 'F9 — inventado pelo teste' };

    try {
      render(<ForjaRunbook onClose={vi.fn()} />);
      const itens = screen.getByTestId('forja-runbook-papeis').querySelectorAll('li');
      expect(itens).toHaveLength(antes + 1);
      expect(screen.getByText('Papel de teste')).toBeTruthy();
    } finally {
      delete PAPEIS[papel];
    }
  });

  it('o dono de cada fase sai INVERTIDO de PAPEIS, não de um mapa escrito à mão', () => {
    // `CC` declara `desc: 'F1 — protótipo visual'`, logo F1 é dele. Se alguém
    // reescrever o desc apontando pra outra fase, o badge tem que migrar junto.
    const cc = PAPEIS['CC'];
    expect(cc).toBeDefined();
    const original = cc!.desc;
    const outraFase = 'F3.5';
    expect(FASE_HUE[outraFase]).toBeDefined();

    cc!.desc = `${outraFase} — realocado pelo teste`;
    try {
      render(<ForjaRunbook onClose={vi.fn()} />);
      const fases = screen.getByTestId('forja-runbook-fases');
      // A fase pra onde o CC foi realocado agora exibe o badge dele.
      const li = Array.from(fases.querySelectorAll('li')).find(
        (n) => n.querySelector('.fj-rb-fase')?.textContent === outraFase,
      );
      expect(li).toBeDefined();
      expect(li!.textContent).toContain('[CC]');
    } finally {
      cc!.desc = original;
    }
  });

  it('NÃO ensina o loop v1 — a fonte daquele texto está marcada superada', () => {
    // PROTOCOL.md marca §1 e §3 como 🪦 superado (ADR 0282: v2 tem 2 papéis, e
    // os gates humanos viraram checks de CI). O protótipo ainda desenha o texto
    // antigo; copiá-lo faria a tela ensinar um processo que não existe mais.
    render(<ForjaRunbook onClose={vi.fn()} />);
    const txt = screen.getByTestId('forja-runbook').textContent ?? '';

    expect(txt).not.toContain('/design-override');
    expect(txt).not.toContain('/screenshot-override');
    expect(txt).not.toContain('/a11y-override');
    expect(txt).not.toContain('Aprovação visual síncrona');
    // E a ausência é DECLARADA, não silenciosa — senão a próxima sessão lê o
    // painel curto como bug e "completa" com o texto que este teste barra.
    expect(txt).toContain('superada');
  });

  it('é um diálogo de verdade e fecha no Esc (o protótipo mede 🔴 nos dois)', () => {
    const onClose = vi.fn();
    render(<ForjaRunbook onClose={onClose} />);

    const dialog = screen.getByTestId('forja-runbook');
    expect(dialog.getAttribute('role')).toBe('dialog');
    expect(dialog.getAttribute('aria-modal')).toBe('true');
    expect(dialog.getAttribute('aria-label')).toBe('Trilhas de papel');

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
