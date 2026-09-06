---
id: requisitos-paymentgateway-settings-paymentgateways-index-gap
tela: Settings/PaymentGateways/Index (/settings/payment-gateways)
prototipo: prototipo-ui/cowork/pg-payment-gateways-page.jsx
tela_viva: Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx
gerado_em: 2026-09-06
---

<!-- O basename deste arquivo precisa ser slug("Settings/PaymentGateways/Index") + "-gap.md":
     e assim que prototipo-ui/gerar-contrato.mjs::escolherGap resolve <Mod/Tela> -> gap/map.
     Com outro nome, `node prototipo-ui/consumir-map.mjs Settings/PaymentGateways/Index` sai
     rc=1 "map nao encontrado" mesmo com o arquivo no disco, e a Fase 4 fica inalcancavel pela
     forma canonica. Medido em 2026-09-06, antes e depois do rename. -->

# GAP-SPEC — Settings/PaymentGateways/Index

> ⚠️ **Tier 0 — esta tela decide para onde o dinheiro vai.** Ativar, desativar, trocar credencial
> ou apontar conta destino muda o fluxo financeiro real. Toda ação marcada **Decidir.** abaixo,
> quando construída, exige a regra-mestre do projeto **antes do merge**: provar o efeito por dois
> caminhos independentes e apresentar a tabela antes→depois dos registros afetados, com
> aprovação [W] explícita. Nenhum valor monetário aparece neste documento.
>
> **A capacidade mora em quatro arquivos, não só no `Index.tsx`.** Antes de chamar algo de
> ausente, medi também `_components/DrawerGateway.tsx` (729 ln), `_components/SheetNovoGateway.tsx`
> (591 ln), `_components/ConfirmToggleModal.tsx` e `_lib/gateway-shared.ts`.
>
> **Frescor da fonte:** `pg-payment-gateways-page.jsx` foi verificado em 2026-08-27 e não entrou
> na rodada de 2026-09-06 (que mediu 7 de 258). A comparação fala do espelho, não do Cowork de hoje.
>
> **Non-Goals do charter, que NÃO viram gap:** editar credencial sem reentrada de senha mTLS,
> baixar o certificado pela interface, renovação **automática** de mTLS, migração PesaPal→Asaas
> **automática**, rotação de assinatura de webhook sem tocar o painel do gateway, estatística
> histórica **acima de 7 dias**, ativação em massa entre negócios, e layout responsivo para celular.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | `Index.tsx:119-132` tem o mesmo título e o mesmo par de botões do protótipo (`pg-payment-gateways-page.jsx:101-109`) — "Testar todos" e "Novo gateway" — e acrescenta um terceiro, "Voltar", que leva à Cobrança. O "Testar todos" do vivo dispara um POST real para `/settings/payment-gateways/health-check` e recarrega só `gateways`; no protótipo o botão é inerte. | Nada — vivo à frente. |
| Cartões de indicador | `Index.tsx:134-160` traz os três cartões do protótipo (`pg-payment-gateways-page.jsx:112-118`) com os mesmos rótulos, os mesmos subtítulos e o mesmo tom condicional no health check, e o terceiro segue clicável para a Cobrança. O vivo os envolve em `Inertia::defer` com esqueleto de carregamento, que o protótipo não tem. | Nada — vivo à frente. |
| Aviso de credencial com atenção | `Index.tsx:162-171` mostra o mesmo aviso âmbar do protótipo (`pg-payment-gateways-page.jsx:121-131`) quando algum gateway está marcado com ressalva. Falta o botão que o protótipo põe dentro do aviso: "Renovar mTLS" (`:128`). | **Decidir.** Região do mockup: `pg-payment-gateways-page.jsx:128`; ponto no vivo: `Index.tsx:166-170`. O charter proíbe a renovação **automática** e prevê exatamente este caminho manual, então o botão não contradiz Non-Goal — ele é o que o Non-Goal pressupõe. Toca credencial de gateway ativo: se construído, exige a dupla confirmação do efeito e a tabela antes→depois dos gateways afetados, com aprovação [W]. Construir ou rejeitar por escrito. |
| Tabela de gateways | `Index.tsx:174-258` tem as sete colunas do protótipo (`pg-payment-gateways-page.jsx:134-201`), a linha inteira abre o drawer, e o interruptor "Ativo" passa pelo `ConfirmToggleModal` antes de mudar estado. O cabeçalho da coluna de saúde é "Health" no vivo e "Health · latência" no protótipo, mas a latência **é renderizada** na célula (`Index.tsx:241`) — copy encurtada, não capacidade ausente. | Nada — paridade. |
| Drivers disponíveis | `Index.tsx:261-294` repete a faixa do protótipo (`pg-payment-gateways-page.jsx:202-239`): os drivers ainda não configurados viram cartões que abrem o assistente de novo gateway. | Nada — paridade. |
| Drawer do gateway | `_components/DrawerGateway.tsx` tem as mesmas quatro abas canônicas do protótipo (`pg-payment-gateways-page.jsx:360-588`): Identificação, Credenciais, Webhook e Health. Duas coisas do protótipo não existem lá: o bloco de migração do PesaPal (`:496-501`, com o texto de recomendação e o botão "Iniciar migração") e o gráfico de barras dos últimos sete dias na aba Health (`:565-573`). | **Decidir.** Dois itens distintos. (a) Migração PesaPal: região `pg-payment-gateways-page.jsx:496-501`; ponto no vivo dentro de `DrawerGateway.tsx`, na aba Identificação. O charter proíbe a migração **automática** e prevê o link manual, então o botão é o caminho previsto — mas ele troca o gateway que recebe o dinheiro das assinaturas, logo exige dupla confirmação do efeito e tabela antes→depois das assinaturas afetadas, com aprovação [W]. (b) Gráfico de sete dias: região `:565-573`; é **leitura pura**, não toca valor, e cabe no charter (que só proíbe histórico acima de sete dias) — a pré-condição é persistir o resultado dos health checks, porque as barras do protótipo são constantes. Construir ou rejeitar por escrito, item a item. |
| Assistente de novo gateway | `_components/SheetNovoGateway.tsx:29` declara os mesmos três passos do protótipo (`pg-payment-gateways-page.jsx:669-826`): Driver, Credenciais e Vínculo, nessa ordem. | Nada — paridade. |
