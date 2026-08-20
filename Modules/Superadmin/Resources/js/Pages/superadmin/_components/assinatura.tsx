// Peças que as telas de Superadmin compartilham.
//
// Nasceu na SA-O4a por necessidade, não por gosto: a tela de Assinaturas precisava do MESMO
// mapa rótulo→tom que a de Negócios já tinha, e uma segunda cópia é como as duas divergem no
// primeiro status novo — §5 proibicoes 2026-08-02 ("o fix pousou na cópia que o consumidor não
// usa"). É o par, no front, do `Modules\Superadmin\Support\RotuloAssinatura` no back.

/**
 * Rótulo PT-BR de assinatura → tom do Badge.
 *
 * A entrada é o RÓTULO (o que o back já traduziu), nunca o enum: se um dia alguém passar
 * `approved` aqui, cai no `outline` e o erro fica visível em vez de escolher uma cor por sorte.
 *
 * `Bloqueada` compartilha o tom de `Vencida` porque as duas significam "o acesso parou"; elas
 * seguem SEPARADAS no texto, que é onde a diferença importa (inadimplência × fim de vigência).
 */
export function tomDaAssinatura(rotulo: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (rotulo === 'Ativa') return 'default';
  if (rotulo === 'Vencida' || rotulo === 'Bloqueada') return 'destructive';
  if (rotulo === 'Pendente') return 'secondary';
  return 'outline';
}

/** Plural PT-BR explícito — o F1 §2 cobra "1 local / 2 locais", nunca concatenar "s". */
export const plural = (n: number, sing: string, plur: string) => `${n} ${n === 1 ? sing : plur}`;

/**
 * Combo de filtro. `<select>` nativo de propósito: é a peça que o F1 chama de `cli-fdrop-*` e
 * que o resto do app já usa em filtro de lista — trocar por Radix aqui criaria dois padrões.
 *
 * ⚠️ Nenhuma `<option>` pode nascer de dado sem `.filter(Boolean)` antes — valor vazio em
 * `SelectItem` derruba o Radix, e a lápide de 2026-06-29 é sobre exatamente isso. Aqui o
 * `value=""` é a opção FIXA "todos", escrita à mão, não vinda de lista.
 */
export function Select({
  valor,
  opcoes,
  onChange,
  rotulo,
}: {
  valor: string;
  opcoes: { v: string; label: string }[];
  onChange: (v: string) => void;
  rotulo: string;
}) {
  return (
    <select
      aria-label={rotulo}
      value={valor}
      onChange={(e) => onChange(e.target.value)}
      className="h-9 rounded-md border bg-background px-3 text-xs text-foreground"
    >
      {opcoes.map((o) => (
        <option key={o.v} value={o.v}>
          {o.label}
        </option>
      ))}
    </select>
  );
}
