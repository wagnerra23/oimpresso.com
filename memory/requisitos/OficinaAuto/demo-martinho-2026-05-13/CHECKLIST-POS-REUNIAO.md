# Checklist pós-reunião Martinho Caçambas — 2026-05-14

> **Reunião:** 2026-05-13 10h (Wagner + Martinho)
> **Resultado:** Martinho topou testar oimpresso novo · "promessa de migrar tudo"
> **Status hoje (14/maio):** Wave A autorizada · dry-runs em curso
> **Documentos relacionados:** [discovery-martinho.md](discovery-martinho.md) · [demo-script.md](demo-script.md) · [charter-1pager.md](charter-1pager.md) · [plano-paralelizacao.md](plano-paralelizacao.md) · [../SPEC.md](../SPEC.md) · [../ROADMAP.md](../ROADMAP.md) · [../MATRIZ-ROI.md](../MATRIZ-ROI.md)

---

## P0 — Decisões fechadas

| # | Decisão | Resposta Wagner |
|---|---|---|
| 1 | Opção comercial | **Paridade preço Delphi** — não altera preço; ganha via upsell de módulos novos (WhatsApp Inbox R$ 200-300/m, etc) |
| 2 | Escopo Fase 1 | **Cadastro cliente + Financeiro completo (AR+AP) + Produtos + Compra + Manifestação destinatário + Estoque + OS** |
| 3 | Prazo cutover | **Canary 7d com dados reais, tela por tela** — apresenta, filha+Dani validam+criticam, ajusta, segue |
| 4 | Champion interno | **Filha do Martinho** (operação/comercial) + **Dani (financeiro)** — querem usar |
| 5 | Rename `vehicles` → `oa_vehicles` | **Deixar como está** — já migrado, evita churn |

## Pricing baseline (ref [proposal pricing-recalibracao R$ 830-850](../../decisions/proposals/pricing-recalibracao-ticket-real-830-850.md))

- Baseline Office Impresso: **R$ 830/m** (paridade) — escopo Delphi atual replicado em oimpresso
- Upsells (cobra extra):
  - WhatsApp Inbox multi-atendente: R$ 200-300/m
  - Jana IA ilimitada (brief diário + cobrança automática): a definir
  - NFSe automática a partir de boleto pago: a definir
  - PWA mecânico campo (Fase 4): a definir
- Sem subir preço baseline (Wagner: "muito difícil")

## Persona champion (análoga ROTA LIVRE biz=4)

- 2 mulheres operando empresa familiar (filha + Dani)
- Não-técnicas provavelmente
- Filha = visão operação/comercial (vê vendedores + caçambas no campo)
- Dani = visão financeiro (sente inadimplência 76.7% na pele) — **persona alvo das cleanup tools US-005**
- 20 funcionários totais · 4 vendedores ativos (Rodrigo/Eduardo/Ruan + 1 do form)
- 4 mecânicos (Leonardo/Leoni/Arthur/Ramon — Google Form Checklist Mecânica 8 páginas)

## Perfil financeiro Martinho ([snapshot 2026-05-11](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md))

| Métrica | Valor | Implicação |
|---|---|---|
| Receita 12m | R$ 6.28M | Empresa GRANDE — R$ 632k/mês médio |
| Despesa 12m | R$ 4.96M | Margem operacional ~21% |
| **Inadimplência vencida** | **R$ 4.82M (76.7%)** | **FÓSSIL desde 2015-2017 — cleanup PRÉ-REQ Dani** |
| A pagar vencidas | R$ 3.36M | Ele também deve há anos |
| Lançamentos 12m | 4.656 | Volume diário alto |
| Ticket médio | R$ 1.349 (mediana R$ 738) | Caçamba típica obra |
| Top 10 clientes | só 15% receita | Base pulverizada |

---

## Plano Wave A (importer multi-área Martinho) — sub-decomposição

### Conectividade verificada 2026-05-14
- ✅ Ping `192.168.0.55` (servidor-crm LAN): 1-2ms
- ✅ Porta Firebird 3050: aberta (Test-NetConnection OK)
- ✅ DSN alias `MartinhoServidor` → `192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB`
- ✅ Wagner local + servidor-crm ligado

### Scripts canônicos já existentes em [`scripts/legacy-migration/`](../../../../scripts/legacy-migration/)

| Script | Cobre | CLI |
|---|---|---|
| `import-vehicles.py` ✅ done | 91 caçambas Martinho (2026-05-13 13:31 BRT) | `--alias MartinhoServidor --target-business 164` |
| `import-contacts-from-venda.py` | Clientes (extraídos inline de VENDA — CRM órfão Martinho v1404) | `--alias MartinhoServidor --target-business 164 [--target dry-run\|local\|prod --confirm]` |
| `import-financeiro.py` | FIN_RECEBER + FIN_PAGAR (write-off auto detect — cleanup embutido) | idem |
| `import-empresas.py` | EMPRESA (entidade própria — usar se Martinho tiver) | idem |
| `import-contas-bancarias.py` | CONTAS bancárias (com `--reset-placeholders --only-ativo`) | idem |
| `import-vendas.py` | VENDA → transactions (resolve contact_id via CNPJ lookup) | idem |
| `probe-vendas-schema.py` | Schema probe Firebird (pré-importer) | idem |
| `migrar-tudo.py` | Orquestrador SSH tunnel Hostinger + dry-run/local/prod | `--target dry-run` |

### Sub-waves com FK dependencies

```
A1: import-contacts-from-venda.py (dedupe CNPJ · sem FK deps · simples)
    │
    ├─ A2: import-empresas.py (entidades próprias — se Martinho tiver)
    │
    └─ A3: import-financeiro.py (FK contacts cliente · write-off detection embutida)
        ├─ STATUS write-off candidate: TIPO='A RECEBER' AND VENCTO < NOW-365d
        │  AND DATAPAGTO IS NULL AND BOLETO_NOSSO_NR IS NULL AND JUROS=0
        │  → metadata.is_write_off_candidate=true (Dani filtra)

A4: import-vendas.py (filtrar últimos 12m via SQL · NÃO importar 44k completos)

A5: Produtos + Estoque (verificar se há importer ou usar lib mysql_writer direto)

A6: service_orders FSM populated com status 8 distinct legacy

A8: Audit Delphi via skill officeimpresso-source-analysis (paralelo · paridade rigorosa Sells/Create)
```

### Execução iterativa hoje 14/maio

1. ✅ Conectividade Firebird OK
2. 🟡 Dry-run `import-contacts-from-venda.py --target dry-run` — em curso
3. ⏳ Dry-run `import-financeiro.py --target dry-run`
4. ⏳ Dry-run `import-vendas.py --target dry-run --since 12months`
5. ⏳ Wagner valida relatório consolidado
6. ⏳ Wagner aprova → `--target prod --confirm`

---

## P1 — Decisões pendentes (próximos 3-5 dias)

| # | Item | Default sugerido se não decidir |
|---|---|---|
| 5 | Contrato escrito + DPA LGPD (Wagner operador, Martinho controlador) | Modelo padrão oimpresso (Wagner gera com Eliana[E] advogada) |
| 6 | Backup banco Delphi pré-migração (onde guarda · retenção) | Hostinger backup diário 30d + cópia local Wagner ZIP 90d |
| 7 | Certificado digital A1 NFe Martinho (PFX exportável) | Aguardar Fase 2 V2 NFSe LIVE (não bloqueia Wave A) |
| 8 | CNAE 4581-4/00 locação caçambas — município emite NFSe? | Investigar município Martinho · driver NFSe — Fase 2 |
| 9 | Conta Inter PJ — dele ou Wagner? | Sugiro: conta Martinho próprio (multi-tenant Inter PJ) — destrava futuro |
| 10 | Número WhatsApp Business — dele ou Wagner? | Sugiro: número dele (pairing Baileys CT 100) — Fase 1 |
| 11 | Mapeamento permissions Spatie 20 funcionários | Admin (Martinho+filha+Dani) · Vendedor (4) · Mecânico (4) · Outros (11) |
| 12 | Treinamento (vídeo · sessão remota · material) | Sessão remota síncrona com filha+Dani primeiro (1h) · vídeo Loom secundário |
| 13 | Janela cutover final | Madrugada final de semana · Wagner monitora |
| 14 | Suporte canal | WhatsApp Wagner direto durante canary 7d · escalar pra Felipe[F] semana 2 |

---

## P2 — Pode esperar Fase 2-3

15. Histórico NFes legacy (XMLs) — importa ou só Delphi consulta-arquivo?
16. Google Form "Checklist Mecânica" 8 páginas — Fase 4 PWA mecânico
17. Sidebar customizada por business (esconder 90% dos módulos não-usados)
18. Cap diário OpenAI Jana IA pro Martinho
19. Charter `live` da Producao Kanban

## P3 — Migration Factory como produto

20. Documentar como serviço pago pós-Martinho ($/sizing?)
21. Ordem fila OfficeImpresso: Vargas próximo (mesmo módulo) > ComVis (módulo diferente)
22. Treinar Felipe[F] pra ele ser ponto focal de 1+ cliente (Wagner não é gargalo)

---

## Riscos catalogados

| Risco | Mitigação |
|---|---|
| 76.7% inadimplência fóssil 2015-2017 | `import-financeiro.py` flag automático `is_write_off_candidate` · Dani filtra no UI |
| Servidor-crm desligado durante import | Confirmado ligado hoje · agendar madrugada se necessário |
| 44k vendas estourar Hostinger | Filtrar últimos 12m no SQL · histórico fica Delphi consulta-arquivo |
| biz=164 é PROD — Pest pode contaminar | Toda Pest roda biz=1 vs biz=99 (ADR 0101) · NUNCA biz=164 |
| "Promessa migrar tudo" é gancho perigoso | Escopo cravado nesta tabela P0 #2 · resto via ADR feature-wish |
| Tela Sells/Create velocidade (pain #1 Martinho) | Profiling ANTES do canary tela #1 · target ≤ tempo Delphi |
| Champion duplo se uma viaja | Filha + Dani redundância · cobertura mútua |
| Sessão paralela outra Claude tocando módulo | `whats-active` antes de cada wave · áreas isoladas |

---

## Próximos passos imediatos (sessão 2026-05-14)

- [x] Wagner confirma decisões P0
- [x] Conectividade Firebird Martinho verificada
- [ ] Dry-run `import-contacts-from-venda.py` — em curso
- [ ] Dry-run `import-financeiro.py`
- [ ] Dry-run `import-vendas.py` (últimos 12m)
- [ ] Wagner valida relatório consolidado
- [ ] Wagner aprova prod `--confirm` (criar ADR 0144 ativação se sim)

---

**Criado:** 2026-05-14 (manhã pós-reunião animado)
**Autor:** Wagner + Claude
**Lifecycle:** vivo · atualizar conforme decisões/dry-runs avançam
