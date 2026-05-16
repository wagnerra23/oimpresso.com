import { motion, useReducedMotion } from 'framer-motion';

interface Testimonial {
  id?: number | string;
  title?: string;
  content?: string;
  feature_image_url?: string | null;
}

interface TestimonialsProps {
  testimonials?: Testimonial[] | null;
}

const FALLBACK_TESTIMONIALS: Testimonial[] = [
  {
    title: 'ROTA LIVRE',
    content:
      'Antes a gente fechava o mês com 3 planilhas e 2 sistemas. Hoje é tudo no oimpresso — venda, NF-e, financeiro e estoque em um lugar só.',
  },
  {
    title: 'Operação multi-loja',
    content:
      'Controlar estoque entre filiais era pesadelo. Transferência rápida e relatório de giro me mostraram onde tava o problema de ruptura.',
  },
  {
    title: 'Comunicação visual',
    content:
      'Cálculo por m² automático economiza meia hora por orçamento. Adeus calculadora — bati o competidor em proposta tempo de resposta.',
  },
];

function stripHtml(html?: string | null): string {
  if (!html) return '';
  return html.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
}

function normalizeTestimonials(input: TestimonialsProps['testimonials']): Testimonial[] {
  if (!input || !Array.isArray(input) || input.length === 0) return FALLBACK_TESTIMONIALS;

  const valid = input.filter((item) => (item?.title ?? '').toString().trim() !== '');
  return valid.length > 0 ? valid : FALLBACK_TESTIMONIALS;
}

function avatarFor(testimonial: Testimonial): string {
  if (testimonial.feature_image_url) return testimonial.feature_image_url;
  const name = encodeURIComponent(testimonial.title ?? 'Cliente');
  return `https://ui-avatars.com/api/?name=${name}&background=random&bold=true&size=128`;
}

export default function Testimonials({ testimonials }: TestimonialsProps) {
  const reduceMotion = useReducedMotion();
  const list = normalizeTestimonials(testimonials);

  return (
    <section className="border-t border-border bg-muted/10 py-20 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <span className="text-xs font-semibold uppercase tracking-wider text-primary">
            Quem usa fala
          </span>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Empresas brasileiras que confiam no oimpresso
          </h2>
        </div>

        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {list.map((testimonial, idx) => (
            <motion.figure
              key={`${testimonial.id ?? testimonial.title}-${idx}`}
              initial={{ opacity: 0, y: 16 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, amount: 0.2 }}
              transition={{
                duration: reduceMotion ? 0 : 0.45,
                delay: reduceMotion ? 0 : idx * 0.08,
              }}
              className="flex flex-col rounded-xl border border-border bg-card p-6"
            >
              <blockquote className="flex-1 text-sm leading-relaxed text-foreground">
                <span className="text-2xl leading-none text-primary" aria-hidden>
                  &ldquo;
                </span>
                <span className="ml-1">{stripHtml(testimonial.content)}</span>
              </blockquote>
              <figcaption className="mt-5 flex items-center gap-3 border-t border-border pt-4">
                <img
                  src={avatarFor(testimonial)}
                  alt=""
                  loading="lazy"
                  className="h-10 w-10 rounded-full object-cover"
                />
                <span className="text-sm font-semibold text-foreground">{testimonial.title}</span>
              </figcaption>
            </motion.figure>
          ))}
        </div>
      </div>
    </section>
  );
}
