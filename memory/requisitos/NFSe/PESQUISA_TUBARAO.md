---
name: Pesquisa fiscal NFSe Tubarão-SC
description: Resultado da US-NFSE-001 — decisão SN-NFSe federal vs ABRASF municipal pra Tubarão, library PHP recomendada, cód LC 116, alíquota ISS, e próximos passos.
type: requisitos
module: NFSe
status: aprovado
authority: ADR ARQ-0001
date: 2026-04-30
---

# Pesquisa fiscal NFSe Tubarão-SC — US-NFSE-001

> **Status:** ✅ concluída 2026-04-30
> **Owner:** Eliana[E] (executada por Claude solo, validar com Wagner+contador antes de US-NFSE-002)
> **Output:** decisão arquitetural confirmada — **SN-NFSe federal**

---

## TL;DR — decisões consolidadas

| Pergunta | Resposta |
|---|---|
| Tubarão usa SN-NFSe federal ou ABRASF municipal? | ✅ **SN-NFSe federal** desde 01/01/2026 (LC 214/2025) |
| Sistema antigo (Prefeitura Moderna) ainda válido? | ❌ Descontinuado — emissão exclusiva via Portal Nacional |
| Provider terceiro obrigatório (Focus/NFE.io/PlugNotas)? | ❌ NÃO — webservice direto `nfse.gov.br/EmissorNacional` (custo zero) |
| Library PHP recomendada | `nfse-nacional/nfse-php` (Packagist, moderna, services Contribuinte+Município) |
| Auth no SN-NFSe | Cert A1 (.pfx) **OU** OAuth/JWT GOV.BR |
| Cód LC 116/2003 pra ERP/SaaS gráfico | **1.05** (licenciamento) + **1.07** (suporte/onboarding) |
| Alíquota ISS Tubarão pra informática | 🟡 pendente — Eliana consulta `tubarao.sc.gov.br/pagina-20663/` ou `fazenda@tubarao.sc.gov.br` |

**Impacto na SPEC:** cancela bifurcação SN-NFSe vs ABRASF. Caminho único, simples, sem custo per-emissão. Library candidata trocada de `rafwell/laravel-focusnfe` pra `nfse-nacional/nfse-php`.

---

## 1. Status Tubarão-SC pós-LC 214/2025

**Fonte primária — Prefeitura de Tubarão:**
> "Todas as empresas estabelecidas no município de Tubarão deverão emitir e/ou cancelar a NFS-e exclusivamente através do Portal Nacional, sendo a obrigatoriedade efetiva a partir de 01/01/2026, conforme determinado pela Lei Complementar nº 214, de 16 de janeiro de 2025."

**Confirmações cruzadas:**
- Decreto municipal **8.916/2025** formaliza regulação local
- Bauhaus Sistemas importa NFSe do SN-NFSe pro sistema TERRA (continuidade fiscal municipal) — **não é nossa responsabilidade**
- Portal único: `https://www.nfse.gov.br/EmissorNacional`
- Documentação técnica: `https://www.gov.br/nfse`
- Dúvidas operacionais: `fazenda@tubarao.sc.gov.br`

**Penalidade não-adesão:** município que não migrou perde repasses voluntários federais. Tubarão migrou — sem rota legacy.

---

## 2. Library PHP/Laravel — comparativo

| Library | Auth | Maturidade | Recomendação |
|---|---|---|---|
| **`nfse-nacional/nfse-php`** | A1 + GOV.BR | ✅ moderna, ergonomic | ⭐ **escolhida** |
| `Rainzart/nfse-nacional` | A1 | usa NFePHP (battle-tested) | fallback se a primeira tiver gap |
| `TiagoSilvaPereira/laravel-nfse` | A1 | exemplo educacional | só referência |
| `lucas-simoes/php-nfse` | varia | multi-prefeitura legacy | ❌ não cobre SN-NFSe |
| `rafwell/laravel-focusnfe` | API key Focus | provider terceiro | ❌ **rejeitada** — paga e desnecessária |

**Pacote escolhido:** [`nfse-nacional/nfse-php`](https://github.com/nfse-nacional/nfse-php)
- Services: `ContribuinteService` (emissor) + `MunicipioService` (consulta)
- Config: env (`testing|production`), cert path, senha
- Compatível com SN-NFSe RTC v2.00 (NT 004/SE-CGNFSe 10/12/2025)

---

## 3. Cód serviço LC 116/2003 pra oimpresso

oimpresso (gráfica/comunicação visual) **NÃO emite NFSe pra serviços de software como atividade-fim**. Mas o **ERP que vendemos pra clientes** usa esses códigos:

| Cód | Descrição | Quando usar |
|---|---|---|
| **1.05** | Licenciamento ou cessão de direito de uso de programas de computação | Mensalidade SaaS ERP recorrente (uso do software) |
| **1.07** | Suporte técnico em informática (instalação, configuração, manutenção) | Setup, onboarding, customização, treinamento, hotfix |
| 1.04 | Elaboração de programas de computação | ❌ não se aplica (não vendemos dev sob medida) |
| 1.03 | Processamento, armazenamento ou hospedagem de dados | 🟡 cobertura parcial pra hospedagem; consultar contador |

**Decisão pra emissão piloto (US-NFSE-013):**
- Mensalidade ERP → `1.05`
- Setup/onboarding inicial → `1.07`
- Validar com contador oimpresso antes de produção

> Nota: se oimpresso emitir NFSe **de gráfica** (impressão/plotagem/fachada), os códigos são outros (item 17 ou 13 LC 116). Esta tabela é só pra a vertical ERP. Se Wagner quiser emitir NFSe de gráfica primeiro, abrir nova issue de pesquisa.

---

## 4. Alíquota ISS Tubarão-SC

**🟡 Pendente confirmação humana.** Faixa típica nacional pra serviços de informática: **2% a 5%**. São Paulo aplica 2.9% pra esses códigos.

**Como confirmar:**
1. Acessar `https://tubarao.sc.gov.br/pagina-20663/` (ISS Online prefeitura)
2. Email `fazenda@tubarao.sc.gov.br` solicitando tabela alíquotas pra cód 1.05 e 1.07
3. Contador da oimpresso provavelmente já tem essa info

**Owner:** Eliana[E] — adicionar resultado neste arquivo (seção a baixo) antes de US-NFSE-003.

### 4.1. Alíquotas confirmadas (preencher)

| Cód LC 116 | Descrição | Alíquota Tubarão | Fonte | Data |
|---|---|---|---|---|
| 1.05 | Licenciamento software | _pendente_ | _pendente_ | _pendente_ |
| 1.07 | Suporte informática | _pendente_ | _pendente_ | _pendente_ |

---

## 5. Auth flow no SN-NFSe — escolha

SN-NFSe aceita 2 métodos de autenticação:

| Método | Prós | Contras | Recomendação |
|---|---|---|---|
| **Cert A1 (.pfx)** | Padrão fiscal, mesmo cert que NF-e (futuro) | Renovação anual, custo R$ 200-400/ano | ⭐ **escolhido** — alinha com NfeBrasil futuro |
| OAuth/JWT GOV.BR | Sem cert, sem custo | Atrelado a CPF (não CNPJ via cert), ergonomia ruim em servidor | ❌ não recomendado pra emissão automática |

Cert A1 da oimpresso já é bloqueante #1 da SPEC (Wagner+contador). Sem mudança aqui.

---

## 6. Impacto nas próximas tasks (US-NFSE-002 → 014)

| Task | Mudança |
|---|---|
| US-NFSE-002 (composer) | `composer require nfse-nacional/nfse-php` (em vez de Focus) |
| US-NFSE-002 (.env) | `NFSE_AMBIENTE=homologacao\|producao`, `NFSE_CERT_PATH`, `NFSE_CERT_SENHA` (sem `NFSE_PROVIDER_TOKEN`) |
| US-NFSE-003 (migrations) | Tabela `nfse_provider_configs` simplifica — só 1 provider possível (SN-NFSe). Manter por extensibilidade futura (outros municípios podem ter ABRASF). |
| US-NFSE-004 (adapter) | `NfseProvider` interface mantida; `SnNfseAdapter` único concreto inicial. Adapter pattern preserva flexibilidade pra ABRASF de municípios não-aderidos no futuro. |
| US-NFSE-007 (bridge UPOS) | Sem mudança — recurring nativo UPOS gera invoice → cria NFSe rascunho local → emite via SN. |
| US-NFSE-012 (deploy sandbox) | Endpoint sandbox: `https://sefin.producaorestrita.nfse.gov.br` (homologação). Verificar URL atual em `nfse.gov.br/biblioteca`. |
| US-NFSE-013 (deploy real) | Endpoint produção: `https://sefin.nfse.gov.br`. |

**Diferença crítica pra SPEC:** custo per-emissão = **zero** (governo federal). Antes estimávamos R$ 0.50-1.50/emissão com Focus. Economia: 100%.

---

## 7. Próxima task = US-NFSE-002

**Pré-requisitos resolvidos por esta pesquisa:**
- ✅ Library escolhida (`nfse-nacional/nfse-php`)
- ✅ Method auth definido (cert A1)
- ✅ Endpoints sandbox/prod identificados
- ✅ Cód LC 116 mapeado (1.05 + 1.07)

**Pré-requisitos ainda humanos (Wagner/Eliana):**
- 🔴 Cert A1 (.pfx) oimpresso emitido pelo contador
- 🔴 CNPJ/IE/IM oimpresso registrados em Tubarão
- 🔴 Regime tributário definido
- 🔴 Alíquota ISS Tubarão confirmada (seção 4.1 deste doc)
- 🔴 Validação contador: 1.05/1.07 são os códigos certos pra ERP-SaaS oimpresso?

**Quando os 5 acima estiverem ✅, pular pra US-NFSE-002.**

---

## 8. Refs

### Fontes primárias
- [Prefeitura de Tubarão — Emissor Nacional NFS-e a partir de 01/01/2026](https://tubarao.sc.gov.br/emissor-nacional-da-nota-fiscal-de-servicos-eletronica-nfs-e-passa-a-ser-adotado-em-tubarao-a-partir-do-dia-1o-de-janeiro/)
- [Portal Nacional NFSe (SN-NFSe)](https://www.gov.br/nfse)
- [Emissor Nacional](https://www.nfse.gov.br/EmissorNacional)
- [LC 214/2025 (NFSe Nacional)](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm)
- [LC 116/2003 (lista de serviços)](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm)
- [NT 004/SE-CGNFSe — Layout RTC v2.00 (10/12/2025)](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc-producao-restrita-piloto/nt-004-se-cgnfse-novo-layout-rtc-v2-00-20251210.pdf)
- [Decreto Tubarão 8.916/2025 — regulação NFSe local](https://tubarao.sc.gov.br/pagina-43697/)
- [ISS Online Tubarão](https://tubarao.sc.gov.br/pagina-20663/)

### Libraries PHP
- [`nfse-nacional/nfse-php` (Packagist)](https://packagist.org/packages/nfse-nacional/nfse-php)
- [`nfse-nacional/nfse-php` (GitHub)](https://github.com/nfse-nacional/nfse-php)
- [`Rainzart/nfse-nacional` (GitHub)](https://github.com/Rainzart/nfse-nacional)
- [`TiagoSilvaPereira/laravel-nfse` (GitHub exemplo)](https://github.com/TiagoSilvaPereira/laravel-nfse)
- [NFePHP organização](https://github.com/nfephp-org)

### Análises terceiros
- [Nota Gateway — Tubarão SC adota Emissor Nacional 2026](https://notagateway.com.br/blog/tubarao-sc-adota-emissor-nacional-da-nfs-e-a-partir-de-2026/)
- [TOTVS — CNM nota técnica adesão até 01/01/2026](https://www.totvs.com/blog/fiscal-clientes/nfs-e-padrao-nacional-cnm-publica-nota-tecnica-sobre-a-adesao-ate-1o-de-janeiro-de-2026/)
- [Simtax — Como se preparar pra LC 214/2025](https://simtax.com.br/reforma/nfs-e-padrao-nacional-2026-como-se-preparar-para-a-lc-214-2025/)
- [Ministério da Fazenda — NFSe obrigatória janeiro 2026](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2025/agosto/a-partir-de-janeiro-de-2026-a-nota-fiscal-de-servico-eletronica-nfs-e-sera-obrigatoria-a-fim-de-simplificar-cotidiano-das-empresas)

### Internas
- [SPEC NFSe](SPEC.md)
- [ADR ARQ-0001 — Cliente oimpresso, módulo standalone](adr/arq/0001-cliente-oimpresso-modulo-standalone.md)
- [RUNBOOK NFSe](RUNBOOK.md)
- [README NFSe](README.md)
- [ADR-0002 RecurringBilling — NFSe submódulo vs NfeBrasil](../RecurringBilling/adr/arq/0002-nfse-submodulo-vs-nfebrasil.md) (parcialmente superseded)
