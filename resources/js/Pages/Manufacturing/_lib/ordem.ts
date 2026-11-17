/**
 * Destino dos botões que criam ordem de produção na Fabricação ("Nova produção" da lista de
 * ordens e "Produzir" da gaveta da receita) e do atalho do menu lateral (`DataController`).
 *
 * Por que ainda é a Blade: a ADR 0448 (D1) tira a criação da ordem da Fabricação, mas a §6 põe a
 * saída da Blade de criar e editar na etapa 7, depois que a ordem para estoque existir na Produção
 * (etapa 6). Até lá a Blade é o único caminho que cria ordem, e a decisão B1 do [W] (2026-11-09)
 * manda consertar valor e estoque nela em vez de esperar. Quando a etapa 6 chegar em produção,
 * troque esta constante e o `href` do `DataController` pelo atalho "Nova ordem para estoque", que
 * abre a Produção (ADR 0448 §5).
 */
export const ROTA_NOVA_ORDEM = '/manufacturing/production/create';
