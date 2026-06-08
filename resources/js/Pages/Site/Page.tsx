import type { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';

interface CmsPage {
  id: number;
  title: string;
  content?: string | null; // já sanitizado server-side (SiteContentService::sanitizeHtml)
  meta_description?: string | null;
  feature_image_url?: string | null;
  updated_at?: string | null;
}

interface SitePageProps {
  page: CmsPage | null;
}

function SitePage({ page }: SitePageProps) {
  // Fallback defensivo — controller já faz abort(404), mas a UI não pode quebrar
  // se a prop vier nula.
  if (!page) {
    return (
      <>
        <Head title="Página não encontrada" />
        <div className="mx-auto max-w-3xl px-4 py-24 text-center">
          <h1 className="text-2xl font-bold tracking-tight text-foreground">Página não encontrada</h1>
          <p className="mt-2 text-muted-foreground">
            O conteúdo que você procura não existe ou foi removido.
          </p>
          <Link
            href="/"
            className="mt-6 inline-block rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
          >
            Voltar ao início
          </Link>
        </div>
      </>
    );
  }

  const hasContent = !!page.content && page.content.trim().length > 0;

  return (
    <>
      <Head title={page.title ?? 'Página'}>
        {page.meta_description ? (
          <meta name="description" content={page.meta_description} />
        ) : null}
      </Head>

      <article className="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        {page.feature_image_url && (
          <img
            src={page.feature_image_url}
            alt={page.title}
            loading="lazy"
            width={768}
            height={384}
            className="mb-8 aspect-[2/1] w-full rounded-lg border border-border object-cover"
          />
        )}
        <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
          {page.title}
        </h1>
        {hasContent ? (
          /* HTML sanitizado server-side via SiteContentService::sanitizeHtml (HTMLPurifier). */
          <div
            className="prose prose-slate dark:prose-invert mt-8 max-w-none"
            dangerouslySetInnerHTML={{ __html: page.content ?? '' }}
          />
        ) : (
          <p className="mt-8 text-muted-foreground">Esta página ainda não tem conteúdo.</p>
        )}
      </article>
    </>
  );
}

SitePage.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;

export default SitePage;
