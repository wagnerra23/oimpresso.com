---
sessao: "_saida-07d"
thread: "07 · Acompanhamentos — PR-c3 (rodapé por status/tipo e drawer de detalhe)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main + PR-c1 (#8734) + PR-c2 (#8735)
complementa: "_saida-03.md (pendente 2) e _saida-07.md (PR-c)"
---
# _saida-07d · Rodapé e drawer de detalhe

## Entregue (PR-c3)
- **Rodapé** (`data-contract="crm-rodape"`): além de `Total:`, as contagens por status e por tipo do
  protótipo (`crm-blade.jsx` → TelaAcompanhamentos, `Rodape`). Vêm da prop adiada `contagem`, calculada
  sobre a **mesma consulta filtrada** da lista (`business_id`, filtros, "só os meus", busca) — não sobre
  a página de 25, que é só um recorte.
- **Drawer de detalhe** (`_components/DrawerAcompanhamento.tsx`, `Sheet` PT-02): clicar na linha abre
  "Informações de acompanhamento", "Descrição" e **Registros** (o histórico que a Blade mostrava no modal
  de log). Rodapé com **Log de acompanhamento** (abre o modal de registro do PR-b2) e **Marcar concluído**
  (o mesmo `PUT /crm/follow-ups/{id}` do modal de edição, com `status=completed`). Só na aba avulsa,
  como a Blade.
- `ScheduleLogController@index`: com `lista=1`, devolve os registros em lista; sem ele, o HTML de sempre.
  O acompanhamento continua buscado no negócio da sessão.
- O menu "Ação" da linha para a propagação, para não abrir o drawer junto.
- UC-CRMACO-20 `[T0]` e teste em `CrmAcompanhamentosContratoTest.php` (lane `verticais-pest.yml`).

## Diferença declarada
O protótipo tem "Prazo" (badge de SLA) no drawer; não há dado de prazo no acompanhamento, então ficou
fora.

## Pendente
- Notificação no antecipado (`_saida-07c`).
- Smoke em produção depois do merge da pilha (c1 → c2 → c3).

## Placar
`07`: c1 + c2 + c3 cobrem o que o `_saida-07` deixou pendente; o restante é o smoke. O `00-INDICE.md` não
foi editado.
