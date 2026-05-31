import type { ReactNode } from 'react';
import { useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';

interface BlogPostData {
  id: number;
  title: string;
  content?: string | null; // já sanitizado server-side (SiteContentService::sanitizeHtml)
  meta_description?: string | null;
  feature_image_url?: string | null;
  tags?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface SiteBlogPostProps {
  post: BlogPostData;
}

// Tempo de leitura ~200 palavras/min, a partir do HTML sem tags.
function readingMinutes(html: string): number {
  const text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  const words = text ? text.split(' ').length : 0;
  return Math.max(1, Math.round(words / 200));
}

function formatDate(iso?: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

function SiteBlogPost({ post }: SiteBlogPostProps) {
  const minutes = useMemo(() => readingMinutes(post?.content ?? ''), [post?.content]);
  const dateLabel = formatDate(post?.created_at);

  return (
    <>
      <Head title={post?.title ?? 'Post'}>
        {post?.meta_description ? (
          <meta name="description" content={post.meta_description} />
        ) : null}
      </Head>

      <article className="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <Link
          href="/c/blogs"
          className="text-sm font-medium text-muted-foreground hover:text-foreground"
        >
          ← Voltar pro blog
        </Link>

        <h1 className="mt-6 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
          {post?.title}
        </h1>

        {/* Meta: data · tempo de leitura */}
        <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
          {dateLabel && <span>{dateLabel}</span>}
          {dateLabel && <span aria-hidden>·</span>}
          <span>{minutes} min de leitura</span>
        </div>

        {post?.feature_image_url && (
          <img
            src={post.feature_image_url}
            alt={post.title}
            loading="lazy"
            width={768}
            height={384}
            className="mt-8 aspect-[2/1] w-full rounded-lg border border-border object-cover"
          />
        )}

        <div
          className="prose prose-slate dark:prose-invert mt-8 max-w-none"
          // eslint-disable-next-line react/no-danger -- sanitizado server-side (SiteContentService::sanitizeHtml)
          dangerouslySetInnerHTML={{ __html: post?.content ?? '' }}
        />

        {post?.tags && (
          <div className="mt-10 flex flex-wrap gap-2 text-xs text-muted-foreground">
            {post.tags.split(',').map((tag) => (
              <span
                key={tag}
                className="rounded-full border border-border bg-card px-3 py-1"
              >
                {tag.trim()}
              </span>
            ))}
          </div>
        )}
      </article>
    </>
  );
}

SiteBlogPost.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;

export default SiteBlogPost;
