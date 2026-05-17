<?php

namespace Modules\RecurringBilling\Database\Seeders;

use App\Contact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\RecurringBilling\Models\ChargeAttempt;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionNote;

/**
 * Demo seeder pra Page Cobrança Recorrente — biz=1 ONLY (ADR 0101).
 * NUNCA biz=4 (ROTA LIVRE prod do cliente Larissa — vestuário).
 *
 * Espelha mock do prototipo Cowork (recurring-data.jsx) — 5 planos +
 * 18 subscriptions cobrindo todos os 5 estados visuais (em_dia/retentando/
 * falhou/pausada/cancelada).
 *
 * Idempotente: re-rodar não duplica. Usa `firstOrCreate` em Plans (por slug)
 * e Contacts (por mobile) — depois liga Subscriptions ao par contact_id + plan_id.
 *
 * Uso:
 *   php artisan db:seed --class="Modules\\RecurringBilling\\Database\\Seeders\\RecurringBillingDemoSeeder"
 */
class RecurringBillingDemoSeeder extends Seeder
{
    private const BUSINESS_ID = 1;

    public function run(): void
    {
        DB::transaction(function () {
            $plans = $this->seedPlans();
            $contacts = $this->seedContacts();
            $this->seedSubscriptions($plans, $contacts);
        });

        $this->command?->info(sprintf(
            'RecurringBillingDemoSeeder OK biz=%d (%d planos · %d subs)',
            self::BUSINESS_ID,
            Plan::query()->where('business_id', self::BUSINESS_ID)->count(),
            Subscription::query()->where('business_id', self::BUSINESS_ID)->count()
        ));
    }

    /** @return array<string, Plan> */
    private function seedPlans(): array
    {
        $defs = [
            ['slug' => 'cardapios-mensais',  'name' => 'Cardápios Mensais',       'valor' => 480.0,  'ciclo' => 'monthly',   'descricao_curta' => 'Cardápio A4 4x0 · 50un · entrega 3d',          'fiscal_type' => 'nfe',  'fiscal_cfop' => '5102'],
            ['slug' => 'banner-promo',        'name' => 'Banner Promo · 4 trocas', 'valor' => 1890.0, 'ciclo' => 'monthly',   'descricao_curta' => '4 banners 3x2m lona 380g + troca semanal',     'fiscal_type' => 'nfe',  'fiscal_cfop' => '5102'],
            ['slug' => 'wind-banner',         'name' => 'Wind Banner Multi-loja',  'valor' => 2800.0, 'ciclo' => 'monthly',   'descricao_curta' => '5 wind banners + base + bolsa transporte',    'fiscal_type' => 'nfe',  'fiscal_cfop' => '5102'],
            ['slug' => 'fachada-faixa',       'name' => 'Fachada + Faixa',         'valor' => 1620.0, 'ciclo' => 'monthly',   'descricao_curta' => 'Fachada ACM + faixa promocional 30 dias',     'fiscal_type' => 'nfse', 'fiscal_servico' => '01.07'],
            ['slug' => 'rotulos-perolados',   'name' => 'Rótulos Perolados',       'valor' => 1640.0, 'ciclo' => 'quarterly', 'descricao_curta' => '2000un rótulo perolado · lote trimestral',    'fiscal_type' => 'nfe',  'fiscal_cfop' => '5102'],
        ];

        $out = [];
        foreach ($defs as $def) {
            $out[$def['slug']] = Plan::query()->firstOrCreate(
                ['business_id' => self::BUSINESS_ID, 'slug' => $def['slug']],
                array_merge($def, ['business_id' => self::BUSINESS_ID, 'ativo' => true])
            );
        }

        return $out;
    }

    /** @return array<string, Contact> */
    private function seedContacts(): array
    {
        // 18 contatos PT-BR neutros (NÃO usa ROTA LIVRE biz=4 / Larissa).
        $defs = [
            ['key' => 'padaria-estrela',     'name' => 'Padaria Estrela',         'mobile' => '+5519998761234', 'tax_number' => '12.345.678/0001-90'],
            ['key' => 'acme',                'name' => 'Acme Comércio Ltda',      'mobile' => '+5519987772200', 'tax_number' => '45.678.901/0001-23'],
            ['key' => 'horizonte',           'name' => 'Imobiliária Horizonte',   'mobile' => '+5519991104400', 'tax_number' => '78.901.234/0001-56'],
            ['key' => 'mercado-uniao',       'name' => 'Mercado União',           'mobile' => '+5519992205500', 'tax_number' => '23.456.789/0001-12'],
            ['key' => 'lupulada',            'name' => 'Cervejaria Lupulada',     'mobile' => '+5519993306600', 'tax_number' => '56.789.012/0001-34'],
            ['key' => 'sabor-vo',            'name' => 'Restaurante Sabor da Vó', 'mobile' => '+5519994407700', 'tax_number' => '89.012.345/0001-67'],
            ['key' => 'vida-plena',          'name' => 'Clínica Vida Plena',      'mobile' => '+5519995508800', 'tax_number' => '34.567.890/0001-89'],
            ['key' => 'auto-posto',          'name' => 'Auto Posto Caminho',      'mobile' => '+5519996609900', 'tax_number' => '67.890.123/0001-01'],
            ['key' => 'caopanhia',           'name' => 'Pet Shop Cãopanhia',      'mobile' => '+5519997710100', 'tax_number' => '12.987.654/0001-45'],
            ['key' => 'escola-caminhos',     'name' => 'Escola Caminhos',         'mobile' => '+5519998802200', 'tax_number' => '45.321.876/0001-12'],
            ['key' => 'sol-nascente',        'name' => 'Padaria Sol Nascente',    'mobile' => '+5519990013300', 'tax_number' => '99.111.222/0001-33'],
            ['key' => 'bellamoda',           'name' => 'Loja BellaModa',          'mobile' => '+5519991124400', 'tax_number' => '55.666.777/0001-44'],
            ['key' => 'foto-cia',            'name' => 'Estúdio Foto&Cia',        'mobile' => '+5519992235500', 'tax_number' => '33.444.555/0001-66'],
            ['key' => 'konichiwa',           'name' => 'Sushi Konichiwa',         'mobile' => '+5519993346600', 'tax_number' => '22.333.444/0001-77'],
            ['key' => 'vivenda',             'name' => 'Loja Vivenda',            'mobile' => '+5519994457700', 'tax_number' => '77.888.999/0001-22'],
            ['key' => 'forno-lenha',         'name' => 'Pizzaria Forno Lenha',    'mobile' => '+5519995568800', 'tax_number' => '11.222.333/0001-88'],
            ['key' => 'yoga-equilibrio',     'name' => 'Studio Yoga Equilíbrio',  'mobile' => '+5519996679900', 'tax_number' => '44.555.666/0001-99'],
            ['key' => 'grao-arte',           'name' => 'Cafeteria Grão & Arte',   'mobile' => '+5519997781100', 'tax_number' => '66.777.888/0001-55'],
        ];

        $out = [];
        foreach ($defs as $def) {
            $out[$def['key']] = Contact::firstOrCreate(
                ['business_id' => self::BUSINESS_ID, 'mobile' => $def['mobile']],
                [
                    'name'         => $def['name'],
                    'tax_number'   => $def['tax_number'],
                    'contact_type' => 'customer',
                    'type'         => 'customer',
                    'created_by'   => 1,
                ]
            );
        }

        return $out;
    }

    /**
     * @param  array<string, Plan>     $plans
     * @param  array<string, Contact>  $contacts
     */
    private function seedSubscriptions(array $plans, array $contacts): void
    {
        $today = Carbon::now()->toDateString();
        $tomorrow = Carbon::now()->copy()->addDay()->toDateString();
        $week = Carbon::now()->copy()->addDays(7)->toDateString();
        $month = Carbon::now()->copy()->addDays(20)->toDateString();
        $startOfMonth = Carbon::now()->copy()->startOfMonth()->toDateString();
        $lastMonth = Carbon::now()->copy()->subMonth()->toDateString();

        // 18 subs cobrindo os 5 buckets visuais (Cowork mock).
        $defs = [
            // EM DIA — 8 subs
            ['c' => 'padaria-estrela', 'p' => 'cardapios-mensais', 'status' => 'active', 'method' => 'pix',    'since' => '2025-02-05', 'next' => $month,    'paid' => 14, 'rev' => 6720],
            ['c' => 'acme',            'p' => 'banner-promo',      'status' => 'active', 'method' => 'boleto', 'since' => '2025-08-10', 'next' => $month,    'paid' => 9,  'rev' => 17010],
            ['c' => 'horizonte',       'p' => 'wind-banner',       'status' => 'active', 'method' => 'pix',    'since' => '2024-09-20', 'next' => $month,    'paid' => 20, 'rev' => 55400],
            ['c' => 'mercado-uniao',   'p' => 'fachada-faixa',     'status' => 'active', 'method' => 'boleto', 'since' => '2025-11-24', 'next' => $month,    'paid' => 6,  'rev' => 9720],
            ['c' => 'lupulada',        'p' => 'rotulos-perolados', 'status' => 'active', 'method' => 'pix',    'since' => '2024-11-30', 'next' => $month,    'paid' => 5,  'rev' => 8200],
            ['c' => 'sabor-vo',        'p' => 'cardapios-mensais', 'status' => 'active', 'method' => 'pix',    'since' => '2025-04-12', 'next' => $month,    'paid' => 10, 'rev' => 4800],
            ['c' => 'vida-plena',      'p' => 'banner-promo',      'status' => 'active', 'method' => 'boleto', 'since' => '2024-06-08', 'next' => $month,    'paid' => 24, 'rev' => 45360],
            ['c' => 'auto-posto',      'p' => 'wind-banner',       'status' => 'active', 'method' => 'boleto', 'since' => '2025-06-15', 'next' => $month,    'paid' => 11, 'rev' => 30800],

            // RETENTANDO 2x — 1 sub, com 2 charge_attempts falhos
            ['c' => 'caopanhia',       'p' => 'cardapios-mensais', 'status' => 'past_due', 'method' => 'boleto', 'since' => '2025-05-22', 'next' => $today,    'paid' => 11, 'rev' => 5280, 'attempts' => 2],
            // RETENTANDO 1x — 1 sub
            ['c' => 'escola-caminhos', 'p' => 'fachada-faixa',     'status' => 'past_due', 'method' => 'card',   'since' => '2025-09-03', 'next' => $tomorrow, 'paid' => 7,  'rev' => 11340, 'attempts' => 1],

            // FALHOU 3x — 2 subs (requer ação Eliana)
            ['c' => 'sol-nascente',    'p' => 'cardapios-mensais', 'status' => 'past_due', 'method' => 'boleto', 'since' => '2024-12-10', 'next' => $today,    'paid' => 14, 'rev' => 6720, 'failed' => 3, 'attempts' => 3],
            ['c' => 'bellamoda',       'p' => 'banner-promo',      'status' => 'past_due', 'method' => 'boleto', 'since' => '2025-01-18', 'next' => $today,    'paid' => 13, 'rev' => 24570, 'failed' => 3, 'attempts' => 3],

            // PAUSADA — 1 sub
            ['c' => 'foto-cia',        'p' => 'cardapios-mensais', 'status' => 'paused',   'method' => 'pix',    'since' => '2025-03-14', 'next' => '2026-07-01', 'paid' => 11, 'rev' => 5280, 'paused_until' => '2026-07-01'],

            // CANCELADAS — 4 subs com churn_reason
            ['c' => 'konichiwa',       'p' => 'banner-promo',      'status' => 'canceled', 'method' => 'boleto', 'since' => '2024-10-05', 'next' => $today,    'paid' => 18, 'rev' => 34020, 'churn' => 'preço',           'canceled_at' => $startOfMonth.' 14:00:00'],
            ['c' => 'vivenda',         'p' => 'fachada-faixa',     'status' => 'canceled', 'method' => 'boleto', 'since' => '2025-02-28', 'next' => $today,    'paid' => 13, 'rev' => 21060, 'churn' => 'loja fechou',     'canceled_at' => $lastMonth.' 10:00:00'],
            ['c' => 'forno-lenha',     'p' => 'cardapios-mensais', 'status' => 'canceled', 'method' => 'pix',    'since' => '2024-08-15', 'next' => $today,    'paid' => 18, 'rev' => 8640,  'churn' => 'inadimplência',   'canceled_at' => '2026-03-15 11:00:00'],
            ['c' => 'yoga-equilibrio', 'p' => 'cardapios-mensais', 'status' => 'canceled', 'method' => 'card',   'since' => '2025-07-08', 'next' => $today,    'paid' => 9,  'rev' => 4320,  'churn' => 'trocou fornecedor', 'canceled_at' => $startOfMonth.' 16:00:00'],

            // NOVA ativada esta semana
            ['c' => 'grao-arte',       'p' => 'cardapios-mensais', 'status' => 'active',   'method' => 'pix',    'since' => $today,         'next' => $week,     'paid' => 0,  'rev' => 0],
        ];

        foreach ($defs as $i => $def) {
            $contact = $contacts[$def['c']];
            $plan = $plans[$def['p']];

            $existing = Subscription::query()
                ->where('business_id', self::BUSINESS_ID)
                ->where('contact_id', $contact->id)
                ->where('plan_id', $plan->id)
                ->first();
            if ($existing) {
                continue;
            }

            $sub = Subscription::create([
                'business_id'           => self::BUSINESS_ID,
                'plan_id'               => $plan->id,
                'contact_id'            => $contact->id,
                'status'                => $def['status'],
                'start_date'            => $def['since'],
                'next_due_date'         => $def['next'],
                'billing_anchor_date'   => $def['since'],
                'canceled_at'           => $def['canceled_at'] ?? null,
                'paused_at'             => $def['status'] === 'paused' ? Carbon::now() : null,
                'paused_until'          => $def['paused_until'] ?? null,
                'churn_reason'          => $def['churn'] ?? null,
                'payment_method'        => $def['method'],
                'total_paid_cached'     => $def['paid'],
                'failed_count_cached'   => $def['failed'] ?? 0,
                'total_revenue_cached'  => $def['rev'],
                'contact_phone_cached'  => $contact->mobile,
                'metadata'              => ['valor' => (float) $plan->valor, 'ciclo' => $plan->ciclo],
            ]);

            // Cria invoice + charge attempts pra past_due (retentando/falhou)
            if (! empty($def['attempts'])) {
                $invoice = Invoice::create([
                    'business_id'      => self::BUSINESS_ID,
                    'subscription_id'  => $sub->id,
                    'contact_id'       => $contact->id,
                    'numero_documento' => 'INV-DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'valor'            => $plan->valor,
                    'status'           => 'overdue',
                    'vencimento'       => $def['next'],
                    'gateway'          => 'inter',
                ]);

                for ($n = 1; $n <= $def['attempts']; $n++) {
                    ChargeAttempt::create([
                        'business_id' => self::BUSINESS_ID,
                        'invoice_id'  => $invoice->id,
                        'gateway'     => 'inter',
                        'attempt_n'   => $n,
                        'status'      => 'soft_decline',
                        'error_code'  => 'BOLETO_EXPIRED',
                    ]);
                }
            }

            // Nota pinada pros 3 primeiros (demo do card amarelo no drawer)
            if (in_array($def['c'], ['padaria-estrela', 'acme', 'horizonte'], true)) {
                SubscriptionNote::create([
                    'business_id'     => self::BUSINESS_ID,
                    'subscription_id' => $sub->id,
                    'user_id'         => 1,
                    'body'            => match ($def['c']) {
                        'padaria-estrela' => 'Cliente prefere boleto pré-agendado',
                        'acme'            => '4 trocas/mês — cuidado com SLA arte',
                        'horizonte'       => '5 lojas · cada uma com KV diferente',
                    },
                    'is_pinned'       => true,
                ]);
            }
        }
    }
}
