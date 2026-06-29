import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
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

function formatDate(iso?: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function SiteBlogs({ posts = [] }: SiteBlogsProps) {
  const [q, setQ] = useState('');

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (!term) return posts;
    return posts.filter(
      (p) =>
        p.title.toLowerCase().includes(term) ||
        (p.meta_description ?? '').toLowerCase().includes(term),
    );
  }, [posts, q]);

  return (
    <>
      <Head title="Blog" />

      <section className="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <header className="mb-8">
          <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Blog</h1>
          <p className="mt-3 text-base text-muted-foreground">
            Conteúdo prático sobre gestão, comunicação visual, fiscal e operação.
          </p>
        </header>

        {posts.length > 0 && (
          <div className="mb-8">
            <input
              type="search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar artigos…"
              aria-label="Buscar artigos"
              className="w-full max-w-sm rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          </div>
        )}

        {posts.length === 0 ? (
          <p className="text-sm text-muted-foreground">Em breve: nossos primeiros posts.</p>
        ) : filtered.length === 0 ? (
          <p className="text-sm text-muted-foreground">Nenhum artigo encontrado para “{q}”.</p>
        ) : (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {filtered.map((post) => {
              const slug = post.slug ?? String(post.id);
              const href = `/c/blog/${slug}-${post.id}`;
              const dateLabel = formatDate(post.created_at);
              return (
                <article
                  key={post.id}
                  className="group rounded-lg border border-border bg-card p-5 transition-all hover:border-primary/40 hover:shadow-lg"
                >
                  {post.feature_image_url && (
                    <img
                      src={post.feature_image_url}
                      alt={post.title}
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
                  <div className="mt-4 flex items-center justify-between">
                    <Link href={href} className="text-sm font-medium text-primary hover:underline">
                      Ler mais →
                    </Link>
                    {dateLabel && <span className="text-xs text-muted-foreground">{dateLabel}</span>}
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
