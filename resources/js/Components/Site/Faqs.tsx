import { motion, useReducedMotion } from 'framer-motion';

interface Faq {
  question?: string;
  answer?: string;
}

interface FaqsProps {
  faqs?: Faq[] | string | null;
}

const FALLBACK_FAQS: Faq[] = [
  {
    question: 'O oimpresso atende quais segmentos?',
    answer:
      'Hoje rodam comunicação visual, gráficas, varejo, serviços e operações multi-loja. O núcleo é multi-tenant e os módulos verticais (Vestuário, ComVis, OficinaAuto) ligam recursos específicos por CNAE.',
  },
  {
    question: 'Preciso instalar algo no computador?',
    answer:
      'Não. Tudo roda na nuvem com SSL. Você abre no navegador (PC, notebook ou celular) — funciona desde o primeiro clique.',
  },
  {
    question: 'Emite NF-e, NFC-e e NFS-e?',
    answer:
      'Sim. Emissão fiscal homologada pra todo o Brasil (SEFAZ-SP validado). NF-e e NFC-e com certificado A1, NFS-e Padrão Nacional + city-specific. CT-e e MDF-e inclusos.',
  },
  {
    question: 'Tem suporte humano em português?',
    answer:
      'Sim. Atendimento WhatsApp e e-mail em português, durante horário comercial. Documentação e tutoriais 100% em PT-BR.',
  },
];

function normalizeFaqs(input: FaqsProps['faqs']): Faq[] {
  if (!input) return FALLBACK_FAQS;

  let parsed: unknown = input;
  if (typeof input === 'string') {
    try {
      parsed = JSON.parse(input);
    } catch {
      return FALLBACK_FAQS;
    }
  }

  if (!Array.isArray(parsed) || parsed.length === 0) return FALLBACK_FAQS;

  const valid = parsed
    .filter((item): item is Faq => typeof item === 'object' && item !== null)
    .filter((item) => (item.question ?? '').toString().trim() !== '');

  return valid.length > 0 ? valid : FALLBACK_FAQS;
}

export default function Faqs({ faqs }: FaqsProps) {
  const reduceMotion = useReducedMotion();
  const list = normalizeFaqs(faqs);

  return (
    <section id="faqs" className="py-20 sm:py-24">
      <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <span className="text-xs font-semibold uppercase tracking-wider text-primary">
            Perguntas frequentes
          </span>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Tudo o que você precisa saber antes de começar
          </h2>
        </div>

        <div className="mt-12 grid gap-4 sm:grid-cols-2">
          {list.map((faq, idx) => (
            <motion.div
              key={`${faq.question}-${idx}`}
              initial={{ opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, amount: 0.2 }}
              transition={{
                duration: reduceMotion ? 0 : 0.4,
                delay: reduceMotion ? 0 : idx * 0.05,
              }}
              className="rounded-xl border border-border bg-card p-6"
            >
              <h3 className="text-base font-semibold text-foreground">{faq.question}</h3>
              {faq.answer && (
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{faq.answer}</p>
              )}
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
