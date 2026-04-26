import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';

interface CmsPage {
  id: number;
  title: string;
  content?: string | null;
  meta_description?: string | null;
  feature_image_url?: string | null;
  updated_at?: string | null;
}

interface SitePageProps {
  page: CmsPage;
}

function SitePage({ page }: SitePageProps) {
  return (
    <>
      <Head title={page?.title ?? 'Página'}>
        {page?.meta_description ? (
          <meta name="description" content={page.meta_description} />
        ) : null}
      </Head>

      <article className="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        {page?.feature_image_url && (
          <img
            src={page.feature_image_url}
            alt=""
            className="mb-8 w-full rounded-lg object-cover"
          />
        )}
        <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
          {page?.title}
        </h1>
        <div
          className="prose prose-slate dark:prose-invert mt-8 max-w-none"
          dangerouslySetInnerHTML={{ __html: page?.content ?? '' }}
        />
      </article>
    </>
  );
}

SitePage.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;

export default SitePage;
