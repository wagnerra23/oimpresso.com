# oimpresso · NfeBrasil — NFe automática a partir de boleto pago

## Problema
NFe sai com atraso porque ninguém clica. Cliente final pediu nota, equipe esqueceu, contador só processa quinta. Resultado: cliente reclamando, contador atrasado, e a venda virando dor.

## Solução
Quando o **boleto é pago no Asaas** (ou Inter, C6), o oimpresso:
1. Reconcilia o pagamento com a venda original (`transaction_payment` retro-vínculo)
2. Dispara `Listener` que chama `NfeService` → SEFAZ
3. Recebe autorização (cstat 100) e dispara evento `NFCeAutorizada`
4. Manda e-mail pro cliente final com DANFE PDF + XML anexados
5. Atualiza status na UI por polling (badge muda, ninguém precisa F5)

**Sem clique humano.** Sem digitação. Sem "esqueci".

## Diferenciais únicos
- **Pipeline ponta-a-ponta** (Listener → Job → Service → SEFAZ → evento → e-mail) já entregue (US-NFE-002)
- **11 templates de UF L1** (10 estados + 1 MEI) — não precisa configurar regime/CFOP/CSOSN do zero
- **TransactionBuilder** padroniza dados antes de enviar (evita rejeição SEFAZ por payload sujo)
- **Status visível pro dono:** `/nfe-brasil/transactions/{tx}/status` retorna em tempo real

## 3 features-killer
1. **Auto-emissão por flag** (`NFEBRASIL_AUTO_EMISSION_NFCE=true`) — desligada por padrão, liga quando estiver confiante
2. **Smoke fiscal homologação SC** documentado (runbook biz=1) — testa em ambiente seguro antes de produção
3. **Fallback humano:** se SEFAZ rejeitar (cstat ≠ 100), task aparece pro operador com motivo legível, não com código de erro críptico

## Pricing tier proposto
- **Starter:** NFe avulsa manual (botão "emitir")
- **Pro:** auto-emissão por boleto pago + e-mail DANFE (limite 500 NFes/mês)
- **Enterprise:** ilimitado + multi-business + contingência SVC

`[draft — Wagner valida]`

## CTA
"Quantas NFes/mês sua gráfica emite? E quantas saem **com atraso de mais de 24h**? Devolvo cálculo de quanto isso te custa em multa fiscal + cliente bravo."

---

**Refs internas:** US-NFE-002, `Modules/NfeBrasil/`, runbook smoke SEFAZ biz=1.
