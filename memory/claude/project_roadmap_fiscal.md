---
name: Roadmap fiscal — Boleto + NFe + Tributação por prioridade
description: Plano detalhado para refatorar Boleto (apagar custom, usar pacote padrão) + implementar NFe + Tributação em cascata. Adiado em 2026-04-22
type: project
originSessionId: 3f332cf1-9ebd-4bb2-8b41-a6a1fd23c222
---
Wagner confirmou em 2026-04-22 que o módulo `Modules/Boleto/` custom (93 arquivos de autoria própria) **pode ser apagado** — vai usar pacote padrão. Também quer NFe funcionando. Principal diretriz do cliente: **usabilidade do cliente final + fácil configuração + tributação por prioridade**.

Adiado para depois do React piloto PontoWR2. Não executar sem Wagner pedir.

## Pacotes a usar

- **Boleto:** `eduardokum/laravel-boleto` (padrão PHP/Laravel BR; suporta BB, Bradesco, Itaú, Santander, Caixa, Sicoob, Sicredi, BNB, Banrisul, Safra, Inter, BS2; PIX+QR Code; CNAB 240/400)
- **NFe:** `nfephp-org/sped-nfe` (canônica BR; geração/assinatura/transmissão SEFAZ; DANFE; NFC-e)
- **NFSe:** `nfephp-org/sped-nfse-*` (habilitar só quando precisar; cada município tem layout diferente)

## Modelo de dados — Tributação por prioridade

Conceito: sistema resolve o imposto por cascata. Cliente configura UMA VEZ, não preenche em cada venda.

**Cascata:** `Produto.perfil → Categoria.perfil → Marca.perfil → Business.perfil → Sistema fallback (CST 102 Simples)`

**Nova tabela `perfis_tributacao`** (escopada por business_id):
- id, business_id, nome, prioridade
- cst_icms, cst_pis, cst_cofins
- aliq_icms, aliq_pis, aliq_cofins, aliq_icms_st, mva
- cfop_dentro_estado, cfop_fora_estado
- origem, ncm_default, observacoes_nfe

**Colunas novas em tabelas existentes:**
- `products.perfil_tributacao_id`
- `categories.perfil_tributacao_id`
- `brands.perfil_tributacao_id`
- `business.perfil_tributacao_default_id`
- `business.regime_tributario` enum, `business.crt` enum, `business.boleto_configs` JSON, `business.nfe_ambiente` enum, `business.nfe_serie`, `business.nfe_proximo_numero`, `business.certificado_path`, `business.certificado_senha` (encrypted)

**Service:** `TributacaoResolver::resolver(Product, BusinessLocation, Contact): PerfilTributacaoResolvido`

## Telas (todas React/shadcn/Inertia)

1. **Wizard 5 passos** para setup inicial (regime → certificado → ambiente → primeiro perfil → banco)
2. **Config bancário por banco** com "Testar conexão" (gera boleto fake de R$ 0,01)
3. **Lista de perfis de tributação** com prioridade draggable
4. **Produto — campo "Perfil de tributação"** com Combobox + preview do perfil resolvido
5. **Emissão NFe na venda** — Dialog pós-POS: emitir agora / rascunho / só cupom. Emissão assíncrona via Job + polling Inertia
6. **Emissão boleto** — geração em lote para venda a prazo, PDF único + email cliente + QR PIX
7. **Central Fiscal** dashboard — NFes hoje/mês, boletos abertos/vencidos/pagos, alertas (cert vence, SEFAZ offline)
8. **Histórico/consulta** com filtros + ações em lote (baixar remessa, processar retorno)

## Momentos de IA

1. **Classificador NCM** — descrição do produto → sugestão NCM
2. **Tradutor de rejeições SEFAZ** — código → explicação em linguagem humana + ação
3. **Sugeridor de perfil de tributação** no cadastro de produto (baseado em NCM + regime + histórico)

## Fases estimadas (quando reativar)

- **F0:** instalar pacotes + migrations — 1 sessão
- **F1:** TributacaoResolver + testes + seeders (Simples CST 102/103, MEI, Lucro Presumido) — 1 sessão
- **F2:** Wizard setup + CRUD perfis — 2 sessões
- **F3:** Boleto (config + single + remessa CNAB) — 2 sessões
- **F4:** NFe (job emissão + DANFE + consulta status) — 3 sessões
- **F5:** Central Fiscal + histórico + retorno CNAB — 1 sessão
- **F6:** IA NCM + tradutor rejeições + sugeridor perfil — 2 sessões

**Total:** ~12 sessões.
