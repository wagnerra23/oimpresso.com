# RUNBOOK — Fiscal/Sped (sub-página 7)

> **Tela:** `/fiscal/sped` · **Permissão:** `fiscal.sped.export` · **PRs origem:** #3 (placeholder) + #1259 Wave 8 (gerador MVP) + #1261 Wave 9 (Bloco E + H)

## 1. Objetivo

Gera **arquivo TXT EFD-ICMS/IPI** (CONFAZ Guia Prático v3.1.1 perfil A) pronto pra importação no PVA-EFD. Cobre 23 registros canônicos (Blocos 0 + C + E + H + 9) — estrutura completa pra validação SPED Fiscal.

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
    └── Empty Livros (apuração ICMS/ISS — placeholder Bloco E real em PR #10)
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
X-Sped-Layout-Version: 018
X-Robots-Tag: noindex
```

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
