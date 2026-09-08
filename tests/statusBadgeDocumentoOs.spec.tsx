// Os kinds `documento` e `os` do StatusBadge — a Situação da Visão geral em PT-BR.
//
// ── O DEFEITO QUE ISTO FECHA (medido 2026-09-03, adversário âncora × produção) ──
// `resources/js/Pages/Home/_components/GradesPainel.tsx` chama `colunaSituacao('documento')`
// em 3 abas (:163, :170, :177) e `colunaSituacao('os')` numa quarta (:184). Nenhum dos dois
// kinds existia em `StatusBadge.tsx`, então o componente caía no fallback — `variant="outline"`
// + `toTitle(valor)` — e a coluna "Situação" de 4 das 8 abas da Visão geral mostrava o valor
// CRU do banco, em inglês e sem cor: **"Ordered", "Received", "Packed", "Shipped"**.
//
// Nenhum gate via: o fallback é um caminho legítimo do componente (existe pra não quebrar
// com valor desconhecido), então nada ficava vermelho. Só olhar a tela mostrava.
//
// ── DE ONDE VÊM AS CHAVES E OS RÓTULOS (não são escolha de quem escreveu) ──
//   chaves de `os`        → `Util::shipping_statuses()` (app/Utils/Util.php:1342)
//   chaves de `documento` → `transactions.status`, como o service devolve cru
//                           (GradesDoPainelService.php:502), filtrado em :415 e :433
//   rótulos               → `lang/pt/lang_v1.php` (received/pending/ordered/partial/final,
//                           packed/shipped/delivered) e `lang/pt/sale.php` (draft)
// Uma fonte só: os mesmos rótulos que os Blades já mostram no `<select>` de expedição.
//
// ── MÉTODO (ADR 0258 — "todo ✅ tem que ter sido visto falhar") ──
// O caso 3 é o CONTROLE NEGATIVO: um kind que não existe TEM que cair no fallback com o
// valor cru. Sem ele, um teste que só afirma "documento vira Solicitado" passaria mesmo
// que o componente traduzisse tudo por acidente — e não provaria que o mapa é o que age.
//
// @see resources/js/Components/shared/StatusBadge.tsx
// @see resources/js/Pages/Home/_components/GradesPainel.tsx
// @see tests/statusBadgeFidelity.spec.tsx (irmão — trava a COR; este trava o RÓTULO)

import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup, screen } from '@testing-library/react';
import StatusBadge from '@/Components/shared/StatusBadge';

afterEach(cleanup);

/** Exatamente os valores que o banco entrega, e o PT-BR que a tela deve mostrar. */
const DOCUMENTO: Array<[string, string]> = [
  ['draft', 'Rascunho'],
  ['pending', 'Pendente'],
  ['ordered', 'Solicitado'],
  ['partial', 'Parcial'],
  ['received', 'Recebido'],
  ['final', 'Final'],
  ['completed', 'Concluído'],
  ['cancelled', 'Cancelado'],
];

/** As 5 de `Util::shipping_statuses()` — nem uma a mais, nem a menos. */
const OS: Array<[string, string]> = [
  ['ordered', 'Solicitado'],
  ['packed', 'Embalado'],
  ['shipped', 'Enviado'],
  ['delivered', 'Entregue'],
  ['cancelled', 'Cancelado'],
];

describe('StatusBadge · kind "documento" (transactions.status)', () => {
  it.each(DOCUMENTO)('traduz %s → %s', (valor, esperado) => {
    render(<StatusBadge kind="documento" value={valor} />);
    expect(screen.getByText(esperado)).toBeTruthy();
  });

  it('NÃO deixa escapar o valor cru em inglês', () => {
    // "Ordered" com O maiúsculo é a assinatura do fallback (`toTitle`), que era o defeito.
    render(<StatusBadge kind="documento" value="ordered" />);
    expect(screen.queryByText('Ordered')).toBeNull();
  });
});

describe('StatusBadge · kind "os" (transactions.shipping_status)', () => {
  it.each(OS)('traduz %s → %s', (valor, esperado) => {
    render(<StatusBadge kind="os" value={valor} />);
    expect(screen.getByText(esperado)).toBeTruthy();
  });

  it('cobre exatamente as 5 chaves de Util::shipping_statuses(), sem inventar', () => {
    // Se alguém adicionar chave nova aqui sem adicionar no PHP (ou vice-versa), este
    // número deixa de bater e a divergência entra em revisão em vez de passar calada.
    expect(OS).toHaveLength(5);
  });
});

describe('CONTROLE NEGATIVO — o mapa é o que age, não um acaso', () => {
  it('kind inexistente AINDA cai no fallback com o valor cru', () => {
    // Prova a sensibilidade do teste: o componente continua tendo o caminho de fallback,
    // e é ele que produzia "Ordered". Se este caso passasse a traduzir, o teste acima
    // estaria verde por outro motivo que não o mapa novo.
    render(<StatusBadge kind="kind-que-nao-existe" value="ordered" />);
    expect(screen.getByText('Ordered')).toBeTruthy();
  });

  it('valor desconhecido DENTRO de um kind conhecido também cai no fallback', () => {
    render(<StatusBadge kind="documento" value="status_inventado" />);
    expect(screen.getByText('Status Inventado')).toBeTruthy();
  });
});
