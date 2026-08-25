---
de: "[CC] Cowork (F1)"
para: "[CL] Claude Code (F3)"
data: 2026-08-17
tela: /ia (Painel da Jana)
charter: resources/js/Pages/Jana/Index.charter.md (v9 · lido no main 8b2ca41f neste turno)
mapa: memory/requisitos/Jana/Index-visual-comparison.md §Resumo, ordem 1 (+ ordem 6 de carona)
ancora: prototipo-ui/cowork/jana-merge.jsx §JmAcaoModal
tier: A (mexe em Model novo + business_id · multi-tenant Tier 0)
---

# Ação HITL no Painel da Jana — prévia + aprovação registrada (ordem 1)

Fecha a ordem 1 do mapa: **hoje todo CTA da seção "Ações que … sugere" é decorativo**
(`JanaCockpit.tsx` §render de `acoes` — `title={`${a.cta.label} (HITL — em breve V2)`}`, zero
`onClick`). Sem isto, acrescentar linha nova na seção (ex: "Limpeza >365d", cujo dado já existe em
`ageingBuckets['>365d']`) só multiplica botão morto.

## Escopo — e o que fica FORA de propósito

**PR-A (este):** prévia gerada no SERVIDOR + modal de confirmação + aprovação registrada e auditada.
**PR-B (não este):** o disparo de fato (WhatsApp/e-mail) e a tela de fila `/ia/acoes`.

Por isso **o rótulo do CTA muda de `Disparar` para `Revisar`** neste PR. Manter "Disparar" abrindo
um modal que não dispara seria trocar um botão morto por um botão que mente — a mesma classe que o
§Anti-hooks do charter já barra ("prometer no botão o que a rota não entrega"). O modal declara em
letra o que aconteceu: aprovação registrada, nada sai até o envio ser ligado.

⛔ **Não copiar da âncora:** as 4 prévias do `JmAcaoModal` são texto fixo do Martinho (`biz=164`) e
os `Analise*Service` que o protótipo cita **não existem no repo**. A prévia deste PR nasce de
`insightsAggregates`/`SellsCockpitAggregator` (o mesmo dado que já pinta a linha da ação) — nunca de
string hardcoded no frontend, que é o que faria o drawer irmão (`JanaDrillDrawer`) mentir.

---

## 1 · Migration — `jana_acao_aprovacoes`

`Modules/Jana/Database/Migrations/2026_08_17_000000_create_jana_acao_aprovacoes_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jana_acao_aprovacoes', function (Blueprint $table) {
            $table->id();
            // Tier 0 (ADR 0093): NOT NULL. Ação sugerida é sempre de um business.
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id');
            // Chave da regra que gerou a ação (JanaCockpit §acoes): regua-whatsapp,
            // negociar-top, investigar-ticket, pix-adocao, preventivo-pendentes.
            $table->string('acao_key', 64);
            $table->string('status', 16)->default('aprovada'); // aprovada|recusada|executada
            // Prévia EXIBIDA no momento do OK — o recibo do que a pessoa aprovou.
            // Sem isto, mudar o gerador de prévia reescreveria o passado.
            $table->text('previa');
            // Números que sustentavam a ação no clique (contagem, valor, top devedor).
            $table->json('contexto')->nullable();
            $table->timestamp('aprovada_em')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'acao_key', 'created_at'], 'jana_acao_aprov_biz_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jana_acao_aprovacoes');
    }
};
```

## 2 · Entity — `AcaoAprovacao`

`Modules/Jana/Entities/AcaoAprovacao.php`

```php
<?php

namespace Modules\Jana\Entities;

use App\Scopes\ScopeByBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger append-only das ações da Jana aprovadas por gente (HITL).
 *
 * Append-only de propósito: o que muda é `status`, nunca `previa`/`contexto` —
 * eles são o recibo do que foi mostrado no momento do OK.
 */
class AcaoAprovacao extends Model
{
    protected $table = 'jana_acao_aprovacoes';

    protected $fillable = ['business_id', 'user_id', 'acao_key', 'status', 'previa', 'contexto', 'aprovada_em'];

    protected $casts = [
        'contexto'    => 'array',
        'aprovada_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ScopeByBusiness);
    }
}
```

## 3 · Service — `AcaoHitlService` (prévia no servidor + registro)

`Modules/Jana/Services/AcaoHitlService.php`

```php
<?php

namespace Modules\Jana\Services;

use App\Helpers\OtelHelper;
use App\Services\Sells\SellsCockpitAggregator;
use Illuminate\Support\Carbon;
use Modules\Jana\Entities\AcaoAprovacao;

/**
 * Prévia e aprovação das ações sugeridas no Painel (/ia) — o HITL da ordem 1
 * do `Index-visual-comparison.md`.
 *
 * A prévia é gerada AQUI, e não no frontend, pela mesma razão do farol
 * (`ApuracaoService::farol`) e da fonte do drill: texto que afirma número é
 * veredito, e veredito nasce no servidor. O front só exibe o que recebe.
 */
class AcaoHitlService
{
    /** As 5 regras do `JanaCockpit` §acoes — chave => rótulo do CTA neste PR. */
    public const ACOES = [
        'regua-whatsapp'       => 'Revisar régua',
        'negociar-top'         => 'Revisar proposta',
        'investigar-ticket'    => 'Revisar recorte',
        'pix-adocao'           => 'Revisar leitura',
        'preventivo-pendentes' => 'Revisar lembrete',
    ];

    public function __construct(private SellsCockpitAggregator $aggregator) {}

    public function existe(string $acaoKey): bool
    {
        return array_key_exists($acaoKey, self::ACOES);
    }

    /**
     * @return array{previa:string, contexto:array, alcance:?int}
     */
    public function previa(string $acaoKey, int $businessId): array
    {
        return OtelHelper::spanBiz('jana.acao.previa', function () use ($acaoKey, $businessId) {
            $agg = $this->aggregator->buildInsightsAggregates($businessId);

            $brl = fn (float $v) => 'R$ ' . number_format($v, 2, ',', '.');
            $topDevedor = $agg['topDevedor']['name'] ?? null;

            [$previa, $contexto, $alcance] = match ($acaoKey) {
                'regua-whatsapp' => [
                    "Mensagem de cobrança para {$agg['overdueCount']} venda(s) vencida(s), "
                        . "somando {$brl($agg['overdueValue'])}. Uma mensagem por cliente, com o "
                        . 'valor e o vencimento de cada título — nada agregado, nada genérico.',
                    ['overdueCount' => $agg['overdueCount'], 'overdueValue' => $agg['overdueValue']],
                    $agg['overdueCount'],
                ],
                'negociar-top' => [
                    'Proposta de negociação para ' . ($topDevedor ?? 'o maior devedor')
                        . ' — ' . $brl((float) ($agg['topDevedor']['total'] ?? 0))
                        . '. Contato direto, uma pessoa só: não entra na régua automática.',
                    ['topDevedor' => $agg['topDevedor']],
                    1,
                ],
                'investigar-ticket' => [
                    'Recorte do ticket médio (' . $brl($agg['ticketMedio']) . ') por produto e por '
                        . 'vendedor na janela de 30 dias, pra achar o mix que puxou pra baixo. '
                        . 'Nenhuma mensagem sai: é leitura.',
                    ['ticketMedio' => $agg['ticketMedio']],
                    null,
                ],
                'pix-adocao' => [
                    'Leitura da adoção de PIX de hoje contra o faturado, com a quebra por forma de '
                        . 'pagamento dos últimos 30 dias. Nenhuma mensagem sai: é leitura.',
                    ['methodsAgg' => $agg['methodsAgg']],
                    null,
                ],
                'preventivo-pendentes' => [
                    'Lembrete amigável para os títulos que ainda NÃO venceram — antes da régua. '
                        . 'Um por cliente, citando a data de vencimento.',
                    ['totalAReceber' => $agg['totalAReceber']],
                    null,
                ],
            };

            return ['previa' => $previa, 'contexto' => $contexto, 'alcance' => $alcance];
        });
    }

    public function aprovar(string $acaoKey, int $businessId, int $userId): AcaoAprovacao
    {
        return OtelHelper::spanBiz('jana.acao.aprovar', function () use ($acaoKey, $businessId, $userId) {
            $p = $this->previa($acaoKey, $businessId);

            // `previa` é gravada, não recebida do cliente: recibo do que o servidor
            // mostrou. Aceitar o texto do request deixaria o front reescrever o passado.
            return AcaoAprovacao::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'acao_key'    => $acaoKey,
                'status'      => 'aprovada',
                'previa'      => $p['previa'],
                'contexto'    => $p['contexto'],
                'aprovada_em' => Carbon::now(),
            ]);
        });
    }
}
```

## 4 · Controller

`Modules/Jana/Http/Controllers/AcoesController.php`

```php
<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Jana\Services\AcaoHitlService;

class AcoesController extends Controller
{
    public function previa(Request $request, string $acao, AcaoHitlService $hitl)
    {
        abort_unless($hitl->existe($acao), 404);
        $businessId = (int) $request->session()->get('user.business_id');

        return response()->json($hitl->previa($acao, $businessId));
    }

    public function aprovar(Request $request, string $acao, AcaoHitlService $hitl)
    {
        abort_unless($hitl->existe($acao), 404);
        $businessId = (int) $request->session()->get('user.business_id');

        $hitl->aprovar($acao, $businessId, (int) auth()->id());

        // `back()` e não `redirect('/ia')`: a Page trata como visita Inertia parcial
        // e o flash vira toast (sonner) sem recarregar o Painel inteiro.
        return back()->with('sucesso', 'Aprovação registrada — nada sai antes do envio ser ligado.');
    }
}
```

`Modules/Jana/Http/routes.php` — dentro do grupo de prefixo `ia` (o mesmo de
`/alertas/config`, hoje nas linhas ~144-146):

```php
Route::get('/acoes/{acao}/previa',  'AcoesController@previa')->name('jana.acoes.previa');
Route::post('/acoes/{acao}/aprovar', 'AcoesController@aprovar')->name('jana.acoes.aprovar');
```

## 5 · Frontend — `JanaAcaoModal.tsx`

`resources/js/Pages/Jana/_components/JanaAcaoModal.tsx`

```tsx
// JanaAcaoModal — confirmação HITL das ações do Painel (/ia).
//
// Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`JmAcaoModal` — âncora de SÍMBOLO
// (`grep -n "JmAcaoModal" prototipo-ui/cowork/jana-merge.jsx`).
//
// DIVERGÊNCIA DELIBERADA: a prévia da âncora é texto FIXO do Martinho (biz=164).
// Aqui ela vem de `GET /ia/acoes/{key}/previa`, gerada por `AcaoHitlService` a
// partir do mesmo agregado que pinta a linha da ação. Prévia inventada no cliente
// seria a mentira com selo de autoridade que o `JanaDrillDrawer` existe pra evitar.
//
// Modal, e não Drawer: é confirmação (PT-04), não detalhe (PT-02).
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Skeleton } from '@/Components/ui/skeleton';

export interface AcaoHitl {
  id: string;
  title: string;
  sub: string;
  ctaLabel: string;
}

type Fase = 'carregando' | 'pronto' | 'enviando' | 'erro';

export default function JanaAcaoModal({ acao, onClose }: { acao: AcaoHitl | null; onClose: () => void }) {
  const [fase, setFase] = useState<Fase>('carregando');
  const [previa, setPrevia] = useState<string | null>(null);
  const [alcance, setAlcance] = useState<number | null>(null);

  useEffect(() => {
    if (!acao) return;
    let vivo = true;
    setFase('carregando');
    setPrevia(null);
    fetch(`/ia/acoes/${acao.id}/previa`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((d: { previa: string; alcance: number | null }) => {
        if (!vivo) return;
        setPrevia(d.previa);
        setAlcance(d.alcance ?? null);
        setFase('pronto');
      })
      .catch(() => vivo && setFase('erro'));
    return () => {
      vivo = false;
    };
  }, [acao]);

  const aprovar = () => {
    if (!acao) return;
    setFase('enviando');
    // `router.post` e não fetch: o flash do `back()` vira toast na Page, e o
    // Inertia já cuida de CSRF/erro sem um segundo caminho de request.
    router.post(
      `/ia/acoes/${acao.id}/aprovar`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: () => setFase('erro'),
      },
    );
  };

  return (
    <Dialog open={!!acao} onOpenChange={(aberto) => !aberto && onClose()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <DialogTitle className="text-base">{acao?.title}</DialogTitle>
          <DialogDescription>{acao?.sub}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-3">
          <section className="rounded-md border border-border bg-muted/40 p-3">
            <h3 className="text-[10.5px] font-semibold uppercase tracking-widest text-muted-foreground">
              O que a Jana preparou
            </h3>
            {fase === 'carregando' ? (
              <Skeleton className="mt-2 h-12 w-full" />
            ) : fase === 'erro' ? (
              <p data-contract="painel-acao-erro" className="mt-2 text-sm text-destructive">
                Não deu pra carregar a prévia. Tente de novo — nada foi aprovado.
              </p>
            ) : (
              <p className="mt-2 text-sm leading-relaxed text-foreground">{previa}</p>
            )}
          </section>

          <ul className="flex flex-col gap-1.5 text-xs leading-relaxed text-muted-foreground">
            <li>
              Você aprova <strong className="font-medium text-foreground">cada</strong> mensagem antes
              do envio — a Jana não dispara sozinha.
            </li>
            <li>
              Escopo <code className="font-mono">business_id</code> da sessão — nada cruza empresa.
            </li>
            {alcance !== null && (
              <li>
                Alcance: <strong className="font-medium text-foreground">{alcance}</strong> destinatário
                {alcance === 1 ? '' : 's'}.
              </li>
            )}
            {/* Literal, porque é o que este PR entrega: registro auditável, não envio. */}
            <li>Aprovar registra sua decisão. O envio entra quando o disparo for ligado.</li>
          </ul>
        </div>

        <DialogFooter className="gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={aprovar} disabled={fase !== 'pronto'}>
            {fase === 'enviando' ? 'Registrando…' : 'Aprovar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

## 6 · Wiring em `JanaCockpit.tsx`

```diff
 import JanaDrillDrawer, { type DrillAnalise } from './JanaDrillDrawer';
+import JanaAcaoModal, { type AcaoHitl } from './JanaAcaoModal';
```

```diff
   const [drill, setDrill] = useState<DrillAnalise | null>(null);
+  // Ação em confirmação. `null` = modal fechado; o objeto carrega só o que o
+  // modal exibe — a prévia é buscada por ele no servidor, não passada daqui.
+  const [acaoHitl, setAcaoHitl] = useState<AcaoHitl | null>(null);
```

Rótulos das 5 regras (`acoes`) — `Disparar` → `Revisar régua`, `Preparar` → `Revisar proposta`,
`Investigar` → `Revisar recorte`, `Detalhe` → `Revisar leitura`, `Lembrar` → `Revisar lembrete`
(espelham `AcaoHitlService::ACOES`; um teste amarra os dois lados, ver §7).

```diff
-                  <Button variant={ctaVariant(a.cta.tone)} size="sm" title={`${a.cta.label} (HITL — em breve V2)`}>
+                  <Button
+                    variant={ctaVariant(a.cta.tone)}
+                    size="sm"
+                    onClick={() => setAcaoHitl({ id: a.id, title: a.title, sub: a.sub, ctaLabel: a.cta.label })}
+                    aria-haspopup="dialog"
+                  >
                     {a.cta.label}
                   </Button>
```

```diff
       <JanaDrillDrawer analise={drill} onClose={() => setDrill(null)} />
+
+      {/* Confirmação HITL — âncora §JmAcaoModal. */}
+      <JanaAcaoModal acao={acaoHitl} onClose={() => setAcaoHitl(null)} />
```

E o rodapé da seção perde a promessa (a frase "Próximas ondas: ações HITL real …" deixou de ser
verdade pra metade do que ela promete):

```diff
-        Insights baseados em vendas filtradas atual + agregados 30d. Próximas ondas: ações HITL real
-        (régua WhatsApp · investigar anomalias) + agentes Brain B Jana real.
+        Insights baseados em vendas filtradas atual + agregados 30d. A aprovação é registrada aqui;
+        o disparo das mensagens entra num PR próprio.
```

## 7 · Ordem 6 de carona — toast (custo ~6 linhas)

`Index.tsx`, no corpo do componente (o `sonner` já está disponível e sem uso no Painel — R9):

```tsx
import { usePage } from '@inertiajs/react'
import { toast } from 'sonner'
// …
const flash = usePage().props.flash as { sucesso?: string } | undefined
useEffect(() => {
  if (flash?.sucesso) toast.success(flash.sucesso)
}, [flash?.sucesso])
```

O **estado de erro** do R9 (distinguir vazio de erro nas metas) **não** entra: exige o payload
declarar a falha, e é PR de backend próprio. Não force com `try/catch` no front.

## 8 · Guards (Pest) — o que trava a regressão

`Modules/Jana/Tests/Feature/AcaoHitlTest.php`

```php
it('gera prévia só pras 5 ações conhecidas', function () {
    expect(app(AcaoHitlService::class)->existe('regua-whatsapp'))->toBeTrue();
    expect(app(AcaoHitlService::class)->existe('inventada'))->toBeFalse();
});

it('404 em ação desconhecida', function () {
    $this->actingAs($user)->get('/ia/acoes/inventada/previa')->assertNotFound();
});

it('grava a prévia do SERVIDOR, ignorando texto do cliente', function () {
    $this->actingAs($user)->post('/ia/acoes/regua-whatsapp/aprovar', ['previa' => 'texto do cliente']);
    expect(AcaoAprovacao::latest('id')->first()->previa)->not->toContain('texto do cliente');
});

it('isola aprovações por business_id', function () { /* ScopeByBusiness — Tier 0 */ });

it('mantém os rótulos do CTA em paridade com AcaoHitlService::ACOES', function () {
    // Lê os labels do JanaCockpit.tsx e compara com as chaves do service — é o
    // teste que impede o CTA de voltar a prometer "Disparar" sem sender.
});
```

`UC-JPAIN-11` (contrato de tela, `prototipo-ui/contrato/*.contract.json`): a seção de ações
tem **N linhas com `onClick`** e **zero** `title` contendo "em breve" — asserção estrutural, pela
mesma razão que o UC-10 conta `<Switch` em vez de buscar a palavra "Frota".

## 9 · Docs a atualizar NO MESMO PR (regra de precedência)

- `resources/js/Pages/Jana/Index.charter.md` → **v10**: §Goals ganha o HITL; o §Anti-hooks de
  "prometer no botão" ganha o recibo de que o CTA foi RENOMEADO (não silenciado); registrar que
  prévia é server-side pelo mesmo motivo do farol.
- `Index.casos.md` → UC-JPAIN-11.
- `memory/requisitos/Jana/Index-visual-comparison.md` → R7/R8 de ❌ para ✅ **parcial** (modal sim,
  disparo não) e §Resumo ordem 1 vira "PR-B: sender + fila `/ia/acoes`". Re-medir o lado vivo antes
  de escrever cada linha — o próprio documento cataloga a reincidência de derivado citado fora do
  prazo.
- `prototipo-ui/CODE_NOTES.md` → entrada `[CL] → [W]/[CC]`.
- ⚠️ **Espelho:** o `jana-merge.jsx` segue com as 4 prévias fixas e os 6 `Analise*Service`
  fictícios. Não "conserte" o protótipo neste PR — é pedido [CC] separado (o PR 0.5 do
  `cowork-inbox/JANA-MODULO-ONDAS-PR-2026-08-09.md` nunca rodou); apenas **não derive** deles.

## 10 · Gate

`Jana/Index` está no manifesto `tests/Browser/visreg-screens.json` — este PR gera diff de pixel e
precisa de **aprovação [W]** (F1.5). O golden PT-04 continua `draft`.

---

_Handoff [CC] — código pra [CL] executar. **Nada aqui está commitado**: eu não escrevo no git.
Ponte = você colar isto pro Code, ou `cowork-inbox/` → PR._
