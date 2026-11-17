---
title: "RUNBOOK — Fiscal/Sped (sub-página 7)"
owner: W
status: ativo
last_validated: "2026-11-06"
---

# RUNBOOK — Fiscal/Sped (sub-página 7)

> **Tela:** `/fiscal/sped` · **Permissão:** `fiscal.sped.export` · **PRs origem:** #3 (placeholder) + #1259 Wave 8 (gerador MVP) + #1261 Wave 9 (Bloco E + H)

## 1. Objetivo

Gera **arquivo TXT EFD-ICMS/IPI** (perfil A) para importação no PVA-EFD.

> **Atualizado em 2026-11-06.** O texto original (v3.0) dizia *"23 registros canônicos, Blocos 0 + C + E + H + 9"*.
> Desde a US-FISCAL-028 fatia 1 (2026-10-27) o arquivo tem **os 10 blocos obrigatórios** na ordem do Guia Prático
> v3.2.3 (`0, B, C, D, E, G, H, K, 1, 9` — `EfdIcmsIpiLeiaute::ORDEM_DOS_BLOCOS`); B, D, G, K e 1 saem sem dados.
> O `COD_VER` do registro 0000 vem do ano (`EfdIcmsIpiLeiaute::codVer`, NT 2025.001; 2027 recusa). Contrato:
> `UC-FSPED-11..14` em [`Sped.casos.md`](../../../resources/js/Pages/Fiscal/Sped.casos.md). A §3.1 abaixo é o
> retrato da v3.0.
>
> **Trava:** o download segue atrás de `fiscal.sped_simples_only_lock` (padrão `true`, `config/fiscal.php`) →
> **503** explicativo para quem não é superadmin. O superadmin pode **reativar a trava só na sessão dele**
> (`POST /fiscal/sped/trava`, Onda 10); a flag global é decisão [W] e nenhuma tela a troca. Erro na geração
> devolve mensagem genérica e vai para `report($e)` (#10462, 2026-11-05).

EFD-Contribuições (PIS/COFINS arquivo separado) fica em backlog PR #10.

## 2. Estrutura

```
FxShell route="sped"
└── Body
    ├── Banner verde "✅ Gerador EFD-ICMS/IPI MVP disponível"
    ├── Tabela períodos (5 últimos meses)
    │   colunas: Competência · Status · Notas auth · Valor auth · Prazo · Export
    │   Botão Download habilitado quando notasAutorizadas > 0
    │   Link `<a download>` → /fiscal/sped/icms-ipi/{ano}/{mes}
    └── Empty "Livros fiscais" (apuração ICMS/ISS, conciliação — "em desenvolvimento"; o Bloco E já sai no arquivo desde a US-FISCAL-017)
```

## 3. Gerador `SpedIcmsIpiGeneratorService`

**Service:** `Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php`

**Método público:**
```php
public function gerar(int $businessId, int $ano, int $mes): string
```

Retorna o TXT completo (pipe-delimited `|REG|c1|c2|...|`, terminator `|\r\n`).

**OTel span:** `fiscal.sped.gerar`

### 3.1. 23 registros canônicos implementados

| Bloco | Registros | Conteúdo |
|---|---|---|
| 0 (abertura + cadastros) | `0000, 0001, 0005, 0150, 0190, 0200, 0990` | Layout v3.1.1 (COD_VER=018) + dest + unidades + itens |
| C (notas saídas) | `C001, C100, C170, C190, C990` | NfeEmissao status='autorizada' do período |
| **E (apuração ICMS — PR #9)** | **`E001, E100, E110, E116, E990`** | Apuração consolidada — débitos via array_sum(C190.vl_icms) |
| **H (inventário esqueleto — PR #9)** | **`H001, H990`** | IND_MOV=1 sempre (dados reais exigem integração Stock — backlog) |
| 9 (encerramento + contadores) | `9001, 9900, 9990, 9999` | Contadores automáticos via snapshot `$this->contadores` |

### 3.2. Lógica E110 (apuração consolidada)

```php
VL_TOT_DEBITOS         = array_sum(array_column($totalizadores, 'vl_icms'))
VL_TOT_CREDITOS        = 0  // placeholder — sem entradas DF-e (backlog)
VL_SLD_CREDOR_ANT      = 0  // placeholder — exige histórico ICMS mês anterior
VL_SLD_APURADO         = débitos - créditos
VL_ICMS_RECOLHER       = saldo if > 0 else 0
VL_SLD_CREDOR_TRANSPORTAR = |saldo| if < 0 else 0
```

**E116** emitido CONDICIONALMENTE (`if ($vlTotalDebitos > 0)`) — anti-zero-line.

### 3.3. Validações

- `ano` ∈ [2020, ano atual] — anti-historical / anti-future
- `mes` ∈ [1, 12]
- Cross-tenant guard explícito: `session.user.business_id` deve bater com `$businessId` param

## 4. Endpoint download

`GET /fiscal/sped/icms-ipi/{ano}/{mes}` (perm `fiscal.sped.export`, throttle 3/min anti-abuse)

```http
HTTP/2 200
Content-Type: text/plain; charset=UTF-8
Content-Disposition: attachment; filename="EFD-ICMS-IPI-2026-05.txt"
Content-Length: ...
X-Sped-Layout-Version: 020
X-Robots-Tag: noindex
```

`X-Sped-Layout-Version` é o `COD_VER` do ano do período (`EfdIcmsIpiLeiaute::codVer`), o mesmo do registro `0000`: `019` em 2025, `020` em 2026.

Controller `SpedController::gerar` thin delegate — Service faz o trabalho.

## 5. Multi-tenant (ADR 0093)

- `HasBusinessScope` automático em `NfeEmissao` (Models lidos no Bloco C)
- Cross-tenant guard explícito no Service (`validar()` valida session vs param)
- Pest test `Modules/Fiscal/Tests/Feature/SpedIcmsIpiGeneratorServiceTest::it gerar lança RuntimeException cross-tenant`

## 6. Permissão

`fiscal.sped.export` — pré-existente PR #1.

## 7. Riscos

- **R1**: Validação contra PVA-EFD CONFAZ ainda **não foi feita em prod real** — smoke biz=1 pós-merge via Pest browser MCP deve validar
- **R2**: Saldo credor anterior em E110 é placeholder 0 — empresas com histórico ICMS credor terão arquivo NÃO REFLETINDO real
- **R3**: Bloco H sempre IND_MOV=1 (sem dados) — declaração janeiro 31/12 vai PRECISAR dados reais inventário (backlog PR #10 integração Stock)
- **R4**: Ajustes E110 (estornos crédito/débito, isenções, etc) todos placeholder 0 — empresas com escrituração complexa terão arquivo INCOMPLETO pra apuração real
- **R5**: Entradas (NF-e contra CNPJ via DF-e manifestada) não geradas — Bloco C só tem saídas (`NfeEmissao` autorizadas). Empresas que aproveitam crédito de entrada precisam adicionar manualmente OU aguardar PR #10

## 8. Smoke biz=1

```bash
# Verifica acesso à tela
curl -sv "https://oimpresso.com/fiscal/sped" -H "Cookie: ..." | grep '^< HTTP'
# Esperado: < HTTP/2 200

# Download SPED do mês corrente
curl -sv "https://oimpresso.com/fiscal/sped/icms-ipi/2026/5" -H "Cookie: ..." -o /tmp/sped-test.txt
head -1 /tmp/sped-test.txt
# Esperado: |0000|018|0|01052026|31052026|...|

tail -1 /tmp/sped-test.txt
# Esperado: |9999|N|\r\n  onde N = total de linhas

# Conta registros
grep -c '^|' /tmp/sped-test.txt
# Esperado: ≥ 23 (16 fixos + N notas × 3 [C100+C170+C190 part] + N destinatários + N itens)
```

## 9. Próximo PR (#10)

- **EFD-Contribuições** (arquivo separado SPED CONFAZ ADE 20/2012):
  - Bloco 0: 0000 + 0001 + 0100 + 0110 + 0140 + 0150 + 0190 + 0200 + 0990
  - Bloco C: C001 + C100 + C170 + C181 (PIS) + C185 (COFINS) + C190 + C990
  - Bloco M: M001 + M200 (apuração PIS) + M600 (apuração COFINS) + M990
  - Bloco 9 encerramento
- **Saldo credor anterior real em E110** — exige consulta do `VL_SLD_APURADO` do mês anterior persistido
- **Bloco H com dados reais** — declaração 31/12 com `Modules/ProductCatalogue/Stock`
- **Entradas DF-e manifestada (Bloco C inputs)** — reconciliação cadastro fornecedor via `Modules/Crm`
- **Ajustes E110 detalhados** — escrituração por CST individual (estornos, isenções, outras obrigações)

## 10. Histórico

- **v1.0** (PR #3 #1189) — Placeholder panorama 5 últimos meses, botão Download disabled.
- **v2.0** (PR #8 #1259) — Gerador MVP saídas: 16 registros canônicos (Blocos 0 + C + 9). Botão Download habilitado.
- **v3.0** (PR #9 #1261) — Bloco E (apuração ICMS) + Bloco H (esqueleto). Total: 23 registros canon — estrutura completa pra PVA-EFD CONFAZ.
