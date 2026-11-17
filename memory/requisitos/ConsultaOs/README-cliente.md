---
id: requisitos-consulta-os-readme-cliente
---

# README — Como o cliente externo usa o portal ConsultaOs

> **Audiência:** vendedores oimpresso, operadores que entregam OS a clientes finais.
> Criado na Wave 18 RETRY (2026-05-16); reescrito em 2026-11-17 para a fonte real (Repair). A versão anterior descrevia o mock e dizia que o nome do cliente aparecia no resultado, o que nunca valeu para a fonte real.

## Em 1 frase

Cliente externo (sem login) consulta a OS dele pelo link público, sem ver preço, custo, dados dele próprio nem dados de outras empresas (multi-tenant Tier 0).

## Fluxo do cliente

1. **A empresa entrega o número da OS** ao cliente (ex.: "Sua OS é JS2026/0001 — acompanhe em https://oimpresso.com/consulta-os"). Links antigos de `/repair-status` caem no mesmo portal.
2. **O cliente abre o portal** — tela Inertia/React (`/consulta-os`), sem login.
3. **Escolhe como buscar e digita o número:** nº da OS, nº da venda ou celular (celular só se a empresa ligou essa opção no Repair). Aceita letras, dígitos e `/ . -`, até 20 caracteres; o nº de série é opcional e só estreita a busca.
4. **O portal mostra, por OS encontrada:**
   - nº da OS, marca, aparelho, modelo e nº de série
   - status atual (o nome que a empresa cadastrou no Repair, com a cor)
   - previsão de entrega
   - histórico (data, ação, quem fez, nota)
5. **Quando a empresa marca um status para avisar** (pronto para retirada, aguardando peça), o cliente recebe WhatsApp e o portal já mostra o status novo.

## O que o cliente NUNCA vê (privacidade)

- **O nome, CPF/CNPJ, endereço e e-mail dele mesmo** — o payload não traz dado do cliente (UC-COS-04)
- `business_id` — vazaria identidade interna da empresa atendente (ADR 0093)
- Custo estimado, senha/padrão do aparelho, defeitos, checklist e notas internas da OS
- Valores financeiros da venda

## Defesas ativas

- **Throttle** `throttle:30,1` (30 requisições/min por IP) nas rotas do portal e no `/repair-status`
- **Validação** — tipo de busca em lista fechada; número só com letras, dígitos e `/ . -`, até 20 caracteres
- **Não encontrado** responde só "não encontrado", sem sugestão nem dica de número válido
- **Log de auditoria** de cada busca com o número redigido (`PiiRedactor`), IP truncado /24 (IPv4) ou /48 (IPv6) e User-Agent cortado

## Status atual

- **Fonte real desde 2026-10-02:** as folhas de OS do `Modules/Repair` (US-CONSULTA-001).
- **Pendente, decisão [W]:** identificar a empresa do cliente (hoje um nº válido é procurado em todas as empresas, e OS de empresas diferentes com o mesmo nº saem juntas — UC-COS-09) e captcha.

## Spans OTel

- `consultaos.busca_publica` e `consultaos.repository.lookup` — atributo `tipo` (sem `business_id` de propósito — rota pública)

## Referências

- SPEC: `memory/requisitos/ConsultaOs/SPEC.md` (US-CONSULTA-001)
- ADR 0093 multi-tenant Tier 0
- ADR 0155 module-grade v3 (D5 customer journey)
- `Modules/Repair/Http/Controllers/CustomerRepairStatusController.php` — redirect do `/repair-status`
- Contrato vivo: `resources/js/Pages/ConsultaOs/Index.casos.md`
