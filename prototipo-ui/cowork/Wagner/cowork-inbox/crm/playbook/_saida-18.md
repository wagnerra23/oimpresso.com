---
sessao: "_saida-18"
thread: "18 · Portal do contato · Painel do contato (DashboardController) → Inertia"
dono: "[CL]"
data: 2026-10-27
tipo: recibo
entregue_em: "PR da branch claude/mig-modulo-crm-37f1bc (aberto em 27/10, não mergeado)"
base_lida: wagnerra23/oimpresso.com@main 7806b8ec7c
---
# _saida-18

## Entregue: Início do portal do contato em React, atrás da flag OFF
`DashboardController@index` responde Inertia `Crm/Portal/Painel/Index` quando a flag `useV2CrmPortalPainel` está ligada para o
negócio. Desligada (default), ou com `?classico=1`, segue a Blade `crm::dashboard.index`. Ligar é o cutover (F5), decisão [W].

A casca do contato (D6) nasce aqui, para as threads 19..25 reusarem: `Modules/Crm/Resources/js/Pages/Crm/Portal/_layout/PortalContatoLayout.tsx`.
O menu dela vem do `Modules/Crm/Services/PortalContatoMenu.php`, que o `ContactSidebarMenu` da Blade também passou a ler — a condição de
cada item (tipo do contato, `enable_order_request`, módulo `booking`) ficou num lugar só.

## Placar
entregue 4 de 5 UCs · ausente UC-CRMPO-17 por falta de fonte no `main`.

| UC | estado |
|---|---|
| UC-CRMPO-01 saldo, e só o meu `[T0]` | teste escrito (contato A × B × A' do negócio 99) |
| UC-CRMPO-02 blocos pelo tipo | teste escrito (customer · supplier · both · abertura) |
| UC-CRMPO-03 casca do contato | teste escrito (`shell.portal` presente, `shell.menu`/`shell.topnavs` ausentes; Blade com flag OFF e `?classico=1`) |
| UC-CRMPO-17 aviso de OS | **ausente.** O `main` não tem regra que diga, para o portal, quando uma OS está "pronta para retirada" ou com "orçamento aguardando". É a mesma derivação que a thread 25 (UC-COS-24) precisa criar. Ficou como `[BACKLOG]` no `Index.casos.md` |
| UC-CRMPO-18 cada um na sua porta `[T0]` | teste escrito (operador → 403 no portal; contato → 403 em `/crm/leads`) |

Pela prova do índice (o teste precisa conter `UC-CRMPO-17`), o placar **não** conta esta thread como feita: `placar.mjs --thread 18`
devolve `em curso`. Isso é correto: escrever o id num teste sem o comportamento seria cobertura de faz-de-conta.

**Errata para o índice (pedido ao Cowork, não editei aqui):** a prova `contem "UC-CRMPO-17"` é satisfeita por qualquer menção ao texto.
Na primeira versão deste PR o teste dizia, em comentário, que o caso 17 ficava de fora — e o placar marcou a thread como **feito**.
Tirei o id do comentário e ele voltou a `em curso`. Sugestão: a prova dos UCs passar a exigir o id dentro de um `it('UC-…`, não em
qualquer lugar do arquivo.

## Testes
`Modules/Crm/Tests/Feature/CrmPortalPainelContratoTest.php`, registrado na lane `verticais-pest.yml` (lista de arquivos e os dois gatilhos,
push e paths-filter de PR). **Não rodei Pest:** o CT 100 está fora (chave do Tailscale expirada) e Pest local é proibido. O veredito
sai do CI deste PR; até lá os UCs ficam 🧪 no casos.md.

## Forma
**Não medida.** `design-diff --compare --check` contra `crm--portal-painel--index` (1280 e 390, claro e escuro) exige a tela rodando
com um contato logado e a flag ligada; não havia ambiente para isso nesta sessão. Fica para a F4.

## Divergências declaradas
- Logo do protótipo (`Logo` do DS) não existe no app: o cabeçalho usa um monograma da loja (`Avatar`).
- Navegação por `<button>` com carga completa, como no protótipo, e não `<Link>`: as outras telas do portal ainda são Blade.
- O atalho "Acompanhar pedidos" sai sem a linha de apoio: o protótipo mostra o último pedido, e o Início não carrega pedidos.
- `tests/Feature/Architecture/AppShellUsageGateTest.php` só procura a Page em `resources/js/Pages/`; Pages de `Modules/` ficam fora dele.
  Não muda nada aqui (o portal não deve usar AppShellV2, pela D6), mas é um ponto cego do gate.

## Pendente
- CI verde do PR (veredito dos 4 UCs).
- F4: smoke com a flag ligada num negócio de teste, e o design-diff acima.
- F5: ligar a flag para as empresas. Decisão [W].
- UC-CRMPO-17: com a thread 25.
