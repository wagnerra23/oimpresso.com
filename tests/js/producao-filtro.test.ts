// UC-PCV-06 · o filtro da Produção é uma regra só (Quadro e Linha do tempo) e mora na URL.
// Fonte: prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md — "Dado o filtro
// 'Travadas por arte' + responsável 'Marcos' · Quando troco do Quadro para a Linha · Então o filtro
// continua e os blocos visíveis são os das mesmas OS · E 'Limpar' volta tudo".
import { describe, expect, it } from 'vitest';
import { LIMPO, filtrado, passa, paraUrl, type Filtravel, type Filtro } from '../../Modules/Producao/Resources/js/Pages/Producao/_lib/filtro';

const os = (numero: string, arte: string | null, resp: string | null, setor: string | null, atraso: string | null = null): Filtravel =>
  ({ numero, cliente: 'Padaria', titulo: 'Fachada ' + numero, atraso, arte, etapa: { responsavel: resp, setor } });

const lista = [
  os('OS-1', 'aguardando', 'Marcos', 'Impressão'),
  os('OS-2', 'ajuste', 'Ana', 'Impressão'),       // arte travada, outro responsável
  os('OS-3', null, 'Marcos', 'Acabamento'),       // do Marcos, sem arte travada
  os('OS-4', 'aguardando', 'Marcos', null, 'atrasada'),
];

describe('UC-PCV-06 · filtro da Produção', () => {
  it('"Travadas por arte" + responsável Marcos deixa só as OS que têm as duas coisas', () => {
    const f: Filtro = { ...LIMPO, so: 'arte', resp: 'Marcos' };
    expect(lista.filter((c) => passa(c, f)).map((c) => c.numero)).toEqual(['OS-1', 'OS-4']);
  });

  it('o mesmo filtro, lido de volta da URL por outra vista, dá as mesmas OS', () => {
    const f: Filtro = { ...LIMPO, so: 'arte', resp: 'Marcos' };
    const u = new URLSearchParams(paraUrl(f));
    const lido: Filtro = { q: u.get('q') ?? '', so: (u.get('so') as Filtro['so']) ?? 'todas', resp: u.get('resp') ?? '', setor: u.get('setor') ?? '' };
    expect(lido).toEqual(f);
    expect(lista.filter((c) => passa(c, lido))).toEqual(lista.filter((c) => passa(c, f)));
  });

  it('"Limpar" volta tudo e some da URL', () => {
    expect(filtrado(LIMPO)).toBe(false);
    expect(paraUrl(LIMPO)).toBe('');
    expect(lista.filter((c) => passa(c, LIMPO))).toHaveLength(4);
  });

  it('setor vazio cai em "Sem setor", e busca olha número, cliente e serviço', () => {
    expect(lista.filter((c) => passa(c, { ...LIMPO, setor: 'Sem setor' })).map((c) => c.numero)).toEqual(['OS-4']);
    expect(lista.filter((c) => passa(c, { ...LIMPO, q: 'fachada os-3' })).map((c) => c.numero)).toEqual(['OS-3']);
    // Controle negativo: responsável que ninguém tem não deixa nada passar.
    expect(lista.filter((c) => passa(c, { ...LIMPO, resp: 'Fulano' }))).toHaveLength(0);
  });
});
