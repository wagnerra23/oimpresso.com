import type { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';

interface BlogPost {
  id: number;
  title: string;
  slug?: string;
  meta_description?: string | null;
  feature_image_url?: string | null;
  created_at?: string | null;
}

interface SiteBlogsProps {
  posts?: BlogPost[];
}

function SiteBlogs({ posts = [] }: SiteBlogsProps) {
  return (
    <>
      <Head title="Blog" />

      <section className="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <header className="mb-10">
          <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Blog</h1>
          <p className="mt-3 text-base text-muted-foreground">
            Conteúdo prático sobre gestão, comunicação visual, fiscal e operação.
          </p>
        </header>

        {posts.length === 0 ? (
          <p className="text-sm text-muted-foreground">Em breve: nossos primeiros posts.</p>
        ) : (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {posts.map((post) => {
              const slug = post.slug ?? String(post.id);
              const href = `/c/blog/${slug}-${post.id}`;
              return (
                <article
                  key={post.id}
                  className="group rounded-xl border border-border bg-card p-5 transition-all hover:border-primary/40 hover:shadow-lg"
                >
                  {post.feature_image_url && (
                    <img
                      src={post.feature_image_url}
                      alt=""
                      loading="lazy"
                      className="mb-4 h-40 w-full rounded-md object-cover"
                    />
                  )}
                  <h2 className="text-lg font-semibold text-foreground group-hover:text-primary">
                    <Link href={href}>{post.title}</Link>
                  </h2>
                  {post.meta_description && (
                    <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">
                      {post.meta_description}
                    </p>
                  )}
                  <div className="mt-4">
                    <Link
                      href={href}
                      className="text-sm font-medium text-primary hover:underline"
                    >
                      Ler mais →
                    </Link>
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </section>
    </>
  );
}

SiteBlogs.layout = (page: ReactNode) => <SiteLayout title="Blog">{page}</SiteLayout>;

export default SiteBlogs;
