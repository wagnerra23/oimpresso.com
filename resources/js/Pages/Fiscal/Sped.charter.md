---
id: resources-js-pages-fiscal-sped-charter
page: /fiscal/sped
component: resources/js/Pages/Fiscal/Sped.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
page_id: fiscal-sped
url: /fiscal/sped
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho]
prototypes:
  - "prototipo-ui/.../fiscal-data.jsx SPED_PERIODOS/LIVROS"
---

# Charter — `Fiscal/Sped`

> ⚠️ **Reconciliado em 2026-07-27** — este charter descrevia só o PR #3 (placeholder) e por isso
> **contradizia o código em produção**: declarava Non-Goal "❌ Gerador SPED real" e anti-hook
> "🚫 NÃO emitir SPED real" quando `SpedIcmsIpiGeneratorService` + a rota de download já tinham sido
> entregues em **US-FISCAL-016 (PR #8)** / **US-FISCAL-017 (PR #9)** e integrados ao MotorTributario
> em **US-FISCAL-020**. Obedecer o charter velho significaria remover código correto. Precedência
> aplicada: *teste verde > casos > charter > SPEC* (proibicoes.md §REGRA DE PRECEDÊNCIA) — o charter
> era o perdedor e foi corrigido no mesmo PR. **Nenhum Non-Goal novo foi inventado aqui**: só saíram
> os dois que o código já refutava; os demais seguem como [W] os aprovou.

## Mission

Dar à contadora (Eliana) o **panorama das últimas competências** — notas autorizadas, valor e status de
apuração — e o **download do arquivo EFD-ICMS/IPI** (layout CONFAZ Guia Prático v3.1.1, perfil A) da
competência escolhida, sem sair do cockpit Fiscal.

## Goals

**Panorama (US-FISCAL-010 · PR #3)**
1. 5 últimas competências → contagem `NfeEmissao` autorizadas + valor
2. Status estimado (mês atual = aberto · M-1 = pronto · M-2+ = entregue) — visual apenas
3. Permissão `fiscal.sped.export` no acesso à tela

**Gerador (US-FISCAL-016/017 · PR #8+#9, expandido por US-FISCAL-020)**
4. `GET /fiscal/sped/icms-ipi/{ano}/{mes}` devolve o TXT com os 23 registros canônicos dos Blocos
   0+C+E+H+9, `Content-Disposition: attachment`
5. Validação de competência (ano ≥ 2020, não-futuro, mês 1–12) antes de qualquer query
6. Guard cross-tenant explícito no Service (ADR 0093) além do global scope
7. Tributo por item resolvido pelo `MotorTributarioService` quando configurado; fallback Simples
   Nacional (CSOSN 102) quando o motor não tem regra
8. Download atrás da feature-flag `fiscal.sped_simples_only_lock` enquanto o fallback depender de
   hardcodes (audit sênior 2026-05-25 R1) — superadmin bypassa

## Non-Goals (Wagner aprova explicitamente)

- ❌ EFD-Contribuições (PIS/COFINS — arquivo separado) — PR dedicado
- ❌ Livros fiscais (Apuração ICMS/ISS, Conciliação SEFAZ × ERP) — backlog
- ❌ Workflow de validação contador → entrega SEFIN — backlog
- ❌ Bloco H com inventário real (hoje é esqueleto `IND_MOV=1`) — exige integração Stock/ProductCatalogue
- ❌ Entradas (NF-e contra o CNPJ via DF-e manifestada) — só saídas por ora
- ❌ Saldo credor anterior real no E110

## Anti-hooks

- 🚫 **NÃO liberar a flag `sped_simples_only_lock` sem antes eliminar os hardcodes de fallback** — o TXT
  vai pro Fisco; CFOP/CST errado em venda interestadual expõe a multa (audit R1). Default é `true`
  (fail-secure) e a decisão de desligar é do [W].
- 🚫 **NÃO usar CFOP fixo** — 5xxx é operação interna e 6xxx interestadual (Convênio S/Nº de 1970); o
  CFOP sai da UF origem × destino. O hardcode 5102 já gerou SPED inválido uma vez.
- 🚫 **NÃO gerar sem o guard cross-tenant** — `RuntimeException` antes de qualquer query (ADR 0093).
- 🚫 NÃO sugerir prazo de entrega via cron auto (legal/contador decide; o prazo da EFD é fixado por UF —
  o painel usa dia 15 só como heurística visual).
- 🚫 **NÃO declarar o gerador "validado" sem smoke no PVA-EFD** — os testes de bloco atuais são
  source-grep (ver `Sped.casos.md` §Backlog), não provam o conteúdo do arquivo.
