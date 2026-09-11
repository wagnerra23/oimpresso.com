/**
 * "Débitos conhecidos desta tela" — a tela declara a própria dívida, e a lista é DERIVADA.
 *
 * @covers-us UC-FEVT-08 UC-FDFE-06
 *
 * POR QUE ESTE TESTE É DE RENDER, E MONTA O `FxShell` (e não o componente sozinho): o
 * contrato tem duas metades, e um teste do componente isolado provaria só a primeira. A
 * segunda é o WIRING — o bloco é montado uma vez no shell e resolvido por `route`, de modo
 * que as sete telas o herdam. Remover a linha do `FxShell` deixaria o componente perfeito e
 * a tela muda; aqui isso fica vermelho.
 *
 * A MORDIDA QUE IMPORTA é o caso do bullet TACHADO. Ela é a diferença entre derivar e
 * transcrever, e não é hipotética: o protótipo Cowork tem a lista escrita à mão
 * (`FX_DEBITOS` em `fiscal-data.jsx`) e ela afirma "Gate fiscal.nfe.view sem teste —
 * nenhum teste o exercita", frase que ficou FALSA em 2026-09-01, quando o
 * `Nfe.casos.md:298` foi corrigido e o bullet tachado. Se este projeto tivesse copiado
 * aquela constante, publicaria a mentira em produção. O caso abaixo prova que não copiou.
 *
 * LIMITE HONESTO: o que se mede aqui é o que o DOM contém — títulos, tons, âncoras e
 * ausência. A aparência (a borda tonal à esquerda, o dot do Badge, a leitura em 1280px
 * no tema escuro) é olho humano no smoke (R1), não este arquivo.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => <a href="#">{children}</a>,
  router: { visit: vi.fn() },
}));

import FxShell from '@/Pages/Fiscal/_components/FxShell';
import { DEBITOS_CONHECIDOS } from '@/Pages/Fiscal/_lib/debitos-conhecidos';

const PAGES = resolve(__dirname, '../../resources/js/Pages/Fiscal');

const renderTela = (route: string) =>
  render(
    <FxShell route={route} title="t">
      <div>conteúdo</div>
    </FxShell>
  );

const bloco = () => document.querySelector('[data-contract="debitos-conhecidos"]');
const blocoDecisao = () => document.querySelector('[data-contract="decisao-pendente"]');

describe('UC-FEVT-08 · a tela declara os débitos que os casos.md dela já registram', () => {
  it('UC-FEVT-08 · a tela com débitos desenha um item por bullet [BACKLOG] dela', () => {
    renderTela('fiscal_eventos');

    const secao = bloco();
    expect(secao).not.toBeNull();
    expect(screen.getByText('Débitos conhecidos desta tela')).toBeTruthy();

    // `!d.decisao` porque item de decisão tem bloco próprio (UC-FDFE-06). O Eventos hoje tem
    // zero decisões, então o filtro não muda o número — está aqui para o caso continuar
    // verdadeiro no dia em que um bullet desta tela ganhar `decisão [W]`.
    const esperados = DEBITOS_CONHECIDOS.filter(d => d.tela === 'fiscal_eventos' && !d.decisao);
    expect(esperados.length).toBeGreaterThan(0);
    expect(secao!.querySelectorAll('[data-ancora]')).toHaveLength(esperados.length);
    for (const d of esperados) expect(screen.getByText(d.titulo)).toBeTruthy();
  });

  it('UC-FEVT-08 · tela sem débito não desenha NÓ NENHUM — nem contêiner, nem mensagem vazia', () => {
    renderTela('rota_sem_debito_nenhum');
    expect(bloco()).toBeNull();
  });

  it('UC-FEVT-08 · todo item exibido é um bullet [BACKLOG] VIVO no casos.md que ele cita', async () => {
    renderTela('sped');

    const exibidos = Array.from(bloco()!.querySelectorAll('[data-ancora]')).map(el => ({
      ancora: el.getAttribute('data-ancora')!,
      titulo: el.querySelector('[data-slot="alert-title"]')?.textContent ?? '',
    }));
    expect(exibidos.length).toBeGreaterThan(0);

    // A rastreabilidade é RE-DERIVADA da fonte, não conferida contra o arquivo gerado — senão
    // o teste compararia o dado consigo mesmo. `coletar()` relê os sete `.casos.md` agora; se
    // um bullet foi tachado, editado ou removido e ninguém regerou, o item exibido não vai
    // estar aqui. É o DoD "cada item rastreado à sua âncora" medido, e não uma lista escrita à
    // mão no corpo do PR.
    const { coletar } = await import('../../scripts/governance/fiscal-debitos-derive.mjs');
    const vivos = coletar();

    for (const item of exibidos) {
      expect(readFileSync(resolve(PAGES, item.ancora), 'utf8')).toContain('[BACKLOG');
      expect(
        vivos.some(v => v.titulo === item.titulo && v.ancora === item.ancora),
        `"${item.titulo}" está na tela mas não é mais um bullet [BACKLOG] de ${item.ancora}`
      ).toBe(true);
    }

    // E o inverso, que é o que pega dívida NOVA não publicada: todo bullet vivo da tela
    // aparece nela.
    expect(exibidos).toHaveLength(vivos.filter(v => v.tela === 'sped' && !v.decisao).length);
  });

  it('UC-FEVT-08 · dívida PAGA (bullet tachado) não aparece na tela', () => {
    // `Nfe.casos.md:298` foi tachado em 2026-09-01 — o gate `fiscal.nfe.view` TEM teste.
    // O protótipo, que escreve a lista à mão, segue anunciando o contrário.
    renderTela('nfe');

    const titulos = Array.from(bloco()!.querySelectorAll('[data-ancora]')).map(
      el => el.textContent ?? ''
    );
    expect(titulos.some(t => t.includes('fiscal.nfe.view'))).toBe(false);
    expect(titulos.some(t => t.includes('Gate de permissão'))).toBe(false);
  });

  it('UC-FEVT-08 · o tom vira variante de ESTADO do DS, e o rótulo diz o marcador — não o tom', () => {
    renderTela('sped');

    const itens = Array.from(bloco()!.querySelectorAll('[data-tom]'));
    expect(itens.length).toBeGreaterThan(0);
    for (const el of itens) {
      expect(['danger', 'warning', 'info']).toContain(el.getAttribute('data-tom'));
      expect(el.querySelector('[data-slot="badge"]')?.getAttribute('data-variant')).toBe(
        el.getAttribute('data-tom')
      );
    }

    // `source-grep` e `sem teste` compartilham o tom `warning`; se o rótulo saísse do tom,
    // um item source-grep seria rotulado "sem teste" — e ali o teste EXISTE, ele é que
    // mede o fonte. O SPED tem os dois marcadores, então a distinção é observável aqui.
    const rotulos = itens.map(el => el.querySelector('[data-slot="badge"]')?.textContent);
    expect(rotulos).toContain('source-grep');
    expect(rotulos).toContain('sem teste');
  });
});

describe('UC-FDFE-06 · a tela avisa quando depende de decisão [W]', () => {
  it('UC-FDFE-06 · a tela com item de decisão desenha o bloco, com um item por decisão em aberto', () => {
    renderTela('dfe');

    const secao = blocoDecisao();
    expect(secao).not.toBeNull();
    expect(screen.getByText('Decisão [W] pendente')).toBeTruthy();

    const esperados = DEBITOS_CONHECIDOS.filter(d => d.tela === 'dfe' && d.decisao);
    expect(esperados.length).toBeGreaterThan(0);
    expect(secao!.querySelectorAll('[data-ancora]')).toHaveLength(esperados.length);
    for (const d of esperados) expect(screen.getByText(d.titulo)).toBeTruthy();
  });

  it('UC-FDFE-06 · tela sem decisão em aberto não desenha NÓ NENHUM', () => {
    // O Eventos tem 4 débitos e ZERO decisões — logo desenha o bloco de dívida e NÃO o de
    // decisão. É o par que prova que os dois blocos são independentes, e não um só com
    // título trocado.
    renderTela('fiscal_eventos');
    expect(bloco()).not.toBeNull();
    expect(blocoDecisao()).toBeNull();
  });

  it('UC-FDFE-06 · item de decisão aparece em UM bloco só — nunca nos dois', () => {
    // O Config é a tela mais dura: tem itens dos dois tipos. Se o filtro `!decisao` do bloco
    // de dívida cair, os de decisão aparecem duas vezes na mesma tela, e a repetição lê como
    // duas dívidas onde há uma.
    //
    // ⚠️ OS NÚMEROS SÃO DERIVADOS, NUNCA FIXOS. Até 2026-09-04 este caso assertava `toBe(2)`
    // e `toBe(3)`, e reprovou por GANHO: o `main` declarou 2 débitos novos no `Config.casos.md`
    // e o teste acusou uma regressão que não existia. Número derivado de artefato vivo não se
    // congela em assert — o contrato aqui é a PARTIÇÃO (interseção vazia, união completa), e
    // ela vale para qualquer contagem.
    renderTela('fiscal_config');

    const naDivida = Array.from(bloco()!.querySelectorAll('[data-slot="alert-title"]')).map(
      el => el.textContent
    );
    const naDecisao = Array.from(
      blocoDecisao()!.querySelectorAll('[data-slot="alert-title"]')
    ).map(el => el.textContent);

    const doConfig = DEBITOS_CONHECIDOS.filter(d => d.tela === 'fiscal_config');
    const esperadoDecisao = doConfig.filter(d => d.decisao).length;

    // A tela precisa exercitar os DOIS blocos, senão o caso não prova partição nenhuma.
    expect(esperadoDecisao).toBeGreaterThan(0);
    expect(doConfig.length - esperadoDecisao).toBeGreaterThan(0);

    expect(naDecisao.length).toBe(esperadoDecisao);
    expect(naDivida.length).toBe(doConfig.length - esperadoDecisao);
    expect(naDivida.filter(t => naDecisao.includes(t))).toEqual([]);

    // E nada se perdeu no caminho: os dois blocos somados são todos os itens da tela.
    expect(naDivida.length + naDecisao.length).toBe(doConfig.length);
  });

  it('UC-FDFE-06 · só entra item cujo bullet [BACKLOG] diz `decisão [W]` — nada inventado', async () => {
    renderTela('fiscal_config');

    const { coletar } = await import('../../scripts/governance/fiscal-debitos-derive.mjs');
    const exibidos = Array.from(
      blocoDecisao()!.querySelectorAll('[data-slot="alert-title"]')
    ).map(el => el.textContent);

    for (const titulo of exibidos) {
      const vivo = coletar().find(v => v.titulo === titulo);
      expect(vivo, `"${titulo}" não é mais um bullet vivo`).toBeTruthy();
      expect(vivo!.decisao, `"${titulo}" está no bloco de decisão sem dizer "decisão [W]"`).toBe(
        true
      );
    }
  });
});
