// Abas do módulo Cms — a navegação entre as telas do painel do site.
//
// Por que existe: o item do Cms mora na cascata Superadmin do rodapé, que mostra só o link
// do item (não os `ghosts` que o DataController declara). Sem estas abas, `/cms/site-details`
// só abria digitando a URL. O protótipo põe as sub-telas do CMS como "abas do módulo"
// (prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md, tabela de componentes) e
// deixa a cascata só com o item (prototipo-ui/cowork/Wagner/data.jsx, SUPERADMIN_MENU).
//
// Leads e Módulo, que o protótipo também lista, ficam de fora: Leads não tem tela (decisão
// pendente de [W] sobre `cms_leads`) e Módulo é a instalação, fora do painel de conteúdo.

import { Link } from '@inertiajs/react';

export type CmsTipo = 'page' | 'blog' | 'testimonial';
export type CmsAba = CmsTipo | 'detalhes';

const ROTULO_TIPO: Record<CmsTipo, string> = { page: 'Páginas', blog: 'Blog', testimonial: 'Depoimentos' };

const ABAS: { chave: CmsAba; rotulo: string; href: string }[] = [
  { chave: 'page', rotulo: ROTULO_TIPO.page, href: '/cms/cms-page?type=page' },
  { chave: 'blog', rotulo: ROTULO_TIPO.blog, href: '/cms/cms-page?type=blog' },
  { chave: 'testimonial', rotulo: ROTULO_TIPO.testimonial, href: '/cms/cms-page?type=testimonial' },
  { chave: 'detalhes', rotulo: 'Detalhes do site', href: '/cms/site-details' },
];

export default function CmsAbas({ ativa, contagens }: { ativa: CmsAba; contagens?: Partial<Record<CmsTipo, number>> }) {
  return (
    <nav className="flex gap-1" aria-label="Seções do site" data-contract="cms.content.abas">
      {ABAS.map((a) => {
        const contagem = a.chave !== 'detalhes' ? contagens?.[a.chave] : undefined;
        return (
          <Link
            key={a.chave}
            href={a.href}
            aria-current={a.chave === ativa ? 'page' : undefined}
            className={
              'rounded-md px-3 py-1.5 text-sm ' +
              (a.chave === ativa ? 'bg-muted font-medium' : 'text-muted-foreground hover:bg-muted/60')
            }
          >
            {a.rotulo}
            {contagem !== undefined && <> <span className="tabular-nums text-muted-foreground">{contagem}</span></>}
          </Link>
        );
      })}
    </nav>
  );
}
