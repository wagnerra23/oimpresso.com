# Protótipo — Emissão de NFS-e (`Nfse/Emitir`)

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1), não importada do Cowork. A tela não tem fonte visual em nenhum dos dois donos do inventário.

## 1 · Status — PROPOSTA de forma, não lei

⚠️ Mesma regra do [`nfe-tributacao`](nfe-tributacao.md) §1: **`Nfse/Emitir.tsx` já existe em produção** (401 linhas, `US-NFSE-009`, permissão `nfse.emit`) e declara `related_prototype: n/a (herda PT-02 Formulário)`, que o `ancora.mjs` reconhece como declaração legítima.

**Não promover a `related_prototype` sem decisão [W]** — promover tornaria este desenho soberano sobre uma tela viva pela cadeia FORMA ([UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)). O charter não foi tocado.

## 2 · Ausência confirmada — e uma divergência a devolver ao design

**Nos dois donos** (medido 2026-09-09): o `Nfse/Emitir` não tem `.jsx` no espelho nem no Cowork vivo. A única emissão NFS-e desenhada é o **modal dentro da venda** (`vendas-flow.jsx:573`); o botão "Emitir" do cockpit fiscal **só navega** (`fiscal-page.jsx:96`), e a tela irmã declara isso como Non-Goal.

⚠️ **O playbook do design está medindo errado o repo.** `cowork-inbox/fiscal/playbook/00-INDICE.md` (rev.2, **2026-09-08**) afirma, em dois lugares:

> *"Capacidade que não existe: … emissão NFS-e (`Modules/NFSe` testado, **sem tela**)"*
> *"Fila 🔴 preservada: … NFS-e emissão"*

**A tela existe.** Recibos: `Modules/NFSe/Http/Controllers/NfseController.php:104` faz `Inertia::render('Nfse/Emitir', …)`; o `.tsx` tem 401 linhas com `@memcofre tela: /nfse/emitir · stories: US-NFSE-009 · permissao: nfse.emit`. O que **de fato** não existe é o `casos.md` da tela — ela é a única das 5 deste ciclo sem contrato executável.

Isso é achado para o design reconciliar (precedência *teste verde > casos > charter > SPEC*), não conserto silencioso daqui.

## 3 · O que foi desenhado

PT-02 Formulário, **dois modos** — porque o Controller tem os dois:

- **vinculada a uma venda** (`?transaction_id=`): card "Venda de origem" com nota, competência, cliente e total; tomador e competência pré-preenchidos a partir da `Transaction`;
- **avulsa**: mesmos campos, sem o card de origem.

Faixa fixa de **ambiente + certificado** no topo: `cert_valido: false` desabilita o botão de emitir e mostra o motivo; `ambiente != producao` avisa que a nota não tem validade fiscal.

## 4 · Âncora do domínio — nada inventado

| o que | de onde |
|---|---|
| `competencia` (AAAA-MM) · `tomador_nome` ≤150 · `tomador_cnpj`/`cpf` opcionais · `tomador_email` · `descricao` ≤2000 · `lc116_codigo` ≤5 · `valor_servicos` ≥ 0,01 · `aliquota_iss` 0..1 · `iss_retido` bool · `transaction_id` | `Modules/NFSe/Http/Requests/StoreNfseRequest::rules()` |
| props `config{lc116_codigo_default, aliquota_iss, ambiente, cert_valido, cert_expira}` e `venda{…}` | `NfseController::emitir` (o `Inertia::render`) |
| emissão **assíncrona** ("acompanhe o status na listagem") | `NfseController::store` → `despacharEmissaoAsync` |
| permissão `nfse.emit` | `StoreNfseRequest::authorize` |

**Alíquota é decimal no banco e percentual na tela** (0.05 ⇄ 5,00%), como no `nfe-tributacao`.

⚠️ **Sobre o bloco de cálculo:** a prévia `ISS = valor × alíquota` é **display**, e está rotulada como prévia — *"o valor oficial do ISS é o que a prefeitura apura no retorno do RPS"*. Este protótipo **não toca cálculo em produção**; se algum dia virar código, a REGRA MESTRE de valor (dupla prova + antes→depois + aprovação [W]) se aplica ao PR de código, não a este.

## 5 · Conformidade e runtime

- Tokens **medidos no DS**, não copiados de outro protótipo — ver o achado em [`nfe-tributacao/SOURCE.md` §5](nfe-tributacao.md) (8 dos 14 do `prototipos/perfil/` não existem e resolvem vazio).
- Prefixo `.ne-`; zero cor crua (`conformance-gate` é LEI).
- `<label htmlFor>` em todo campo; botão desabilitado carrega o motivo em texto, não só no `disabled`.

Validação de runtime na seção correspondente do PR — protótipo que não renderiza não é fonte de design (§5 2026-08-28).

## Refs
- [ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 · [ADR 0299](../../../../../memory/decisions/0299-figma-nao-e-fonte-de-design.md) · [ADR UI-0013](../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
- [INVENTÁRIO 2026-09-09](../../../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) §5.3
