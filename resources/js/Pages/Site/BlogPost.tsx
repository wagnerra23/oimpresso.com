import type { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';

interface BlogPostData {
  id: number;
  title: string;
  content?: string | null;
  meta_description?: string | null;
  feature_image_url?: string | null;
  tags?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface SiteBlogPostProps {
  post: BlogPostData;
}

function SiteBlogPost({ post }: SiteBlogPostProps) {
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

        {post?.feature_image_url && (
          <img
            src={post.feature_image_url}
            alt=""
            className="mt-6 w-full rounded-lg object-cover"
          />
        )}

        <h1 className="mt-6 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
          {post?.title}
        </h1>

        <div
          className="prose prose-slate dark:prose-invert mt-8 max-w-none"
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
