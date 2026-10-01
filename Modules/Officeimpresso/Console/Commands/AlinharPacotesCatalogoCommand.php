<?php

namespace Modules\Officeimpresso\Console\Commands;

use Illuminate\Console\Command;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;

/**
 * Inclui `productcatalogue_module` em todo pacote e inscrição que já tem
 * `officeimpresso_module` — pré-requisito para o QR do catálogo do Officeimpresso
 * redirecionar pro ProductCatalogue.
 *
 * ── POR QUE ─────────────────────────────────────────────────────────────────
 * Decisão [W] 2026-10-01 (D3 do playbook Officeimpresso, 2ª rodada, "pode ajustar
 * primeiro"): o catálogo tem dono, o `Modules/ProductCatalogue`. O gerador de QR
 * daqui (`/officeimpresso/catalogue-qr`) aceita `officeimpresso_module`; o de lá
 * (`/product-catalogue/catalogue-qr`) exige `productcatalogue_module`. Redirecionar
 * antes de alinhar os pacotes faria quem só tem o primeiro tomar 403. Ordem: este
 * comando aplicado em prod → só então o redirect.
 *
 * ── POR QUE NÃO É O HARDCODE PROIBIDO ───────────────────────────────────────
 * Nenhum business_id aparece aqui. O comando grava o MESMO dado que a tela
 * `/superadmin/packages/{id}/edit` grava (checkbox `productcatalogue_module` +
 * "Atualizar inscrições existentes"), selecionado por CONTEÚDO do pacote, não por
 * tenant. A UI segue sendo a fonte: o superadmin pode desmarcar depois e este
 * comando não volta atrás sozinho. É o caminho que a REGRA PRIMÁRIA prescreve para
 * escrita em banco ("comando artisan idempotente"), no lugar de SQL na mão.
 *
 * ── DUAS SUPERFÍCIES, porque o gate lê só UMA delas ───────────────────────────
 * `ModuleUtil::hasThePermissionInSubscription` lê `subscriptions.package_details`,
 * nunca o pacote. Mas ajustar só a inscrição deixa a próxima renovação (que copia
 * do pacote) sem a chave. Então:
 *   1. pacote  — `packages.custom_permissions` (o que renovações herdam);
 *   2. inscrição vigente ou futura (`end_date >= hoje`, mesmo recorte do
 *      "Atualizar inscrições existentes") — `package_details` (o que o gate lê).
 * A inscrição é avaliada pelo PRÓPRIO conteúdo, não pelo pacote: assinatura que
 * ganhou `officeimpresso_module` à mão, num pacote que não o tem, também entra.
 *
 * Só ACRESCENTA a chave. Não reconstrói `package_details` a partir do pacote (o que
 * a UI faz), porque isso apagaria chaves que vivem só na inscrição.
 *
 * Idempotente: segunda execução = "nada a fazer". Sem `--dry-run` grava.
 *
 * @see app/Utils/ModuleUtil.php::hasThePermissionInSubscription (quem lê)
 * @see Modules/Superadmin/Http/Controllers/PackagesController.php::update (o que a UI grava)
 * @see Modules/VozDoCliente/Console/Commands/HabilitarVozDoClienteCommand.php (molde)
 * @see memory/reference/feedback-habilitar-modulo-por-business.md
 */
class AlinharPacotesCatalogoCommand extends Command
{
    protected $signature = 'officeimpresso:alinhar-pacotes-catalogo
                            {--dry-run : Lista o que mudaria e NÃO grava}';

    protected $description = 'Inclui productcatalogue_module nos pacotes e inscrições que têm officeimpresso_module (pré-requisito do redirect do QR).';

    public const CHAVE_ORIGEM = 'officeimpresso_module';

    public const CHAVE_DESTINO = 'productcatalogue_module';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $pacotes = Package::query()
            ->get()
            ->filter(fn (Package $p) => $this->precisa((array) ($p->custom_permissions ?? [])));

        $inscricoes = Subscription::query()
            ->whereDate('end_date', '>=', \Carbon::today()->toDateString())
            ->get()
            ->filter(fn (Subscription $s) => $this->precisa($this->detalhes($s)));

        $this->info('Pacotes com '.self::CHAVE_ORIGEM.' e sem '.self::CHAVE_DESTINO.': '.$pacotes->count());
        if ($pacotes->isNotEmpty()) {
            $this->table(['pacote', 'nome', self::CHAVE_DESTINO.' antes'], $pacotes->map(fn (Package $p) => [
                $p->id,
                $p->name,
                $this->valor((array) ($p->custom_permissions ?? [])),
            ])->values()->all());
        }

        $this->info('Inscrições vigentes/futuras com '.self::CHAVE_ORIGEM.' e sem '.self::CHAVE_DESTINO.': '.$inscricoes->count());
        if ($inscricoes->isNotEmpty()) {
            $this->table(['inscrição', 'business', 'pacote', 'status', 'fim', self::CHAVE_DESTINO.' antes'], $inscricoes->map(fn (Subscription $s) => [
                $s->id,
                $s->business_id,
                $s->package_id,
                $s->status,
                optional($s->end_date)->toDateString(),
                $this->valor($this->detalhes($s)),
            ])->values()->all());
        }

        if ($pacotes->isEmpty() && $inscricoes->isEmpty()) {
            $this->info('Nada a fazer — todo pacote/inscrição com '.self::CHAVE_ORIGEM.' já tem '.self::CHAVE_DESTINO.'.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('--dry-run: NADA foi gravado.');

            return self::SUCCESS;
        }

        foreach ($pacotes as $pacote) {
            $permissoes = (array) ($pacote->custom_permissions ?? []);
            $permissoes[self::CHAVE_DESTINO] = 1;
            $pacote->custom_permissions = $permissoes;
            $pacote->save();
        }

        foreach ($inscricoes as $inscricao) {
            $detalhes = $this->detalhes($inscricao);
            $detalhes[self::CHAVE_DESTINO] = 1;
            $inscricao->package_details = $detalhes;
            $inscricao->save();
        }

        $this->info("✓ {$pacotes->count()} pacote(s) e {$inscricoes->count()} inscrição(ões) atualizados.");

        return self::SUCCESS;
    }

    /** Tem a chave de origem ligada e a de destino desligada ou ausente. */
    private function precisa(array $chaves): bool
    {
        return ! empty($chaves[self::CHAVE_ORIGEM]) && empty($chaves[self::CHAVE_DESTINO]);
    }

    /** `package_details` normalizado — a coluna já foi gravada como string JSON em dado legado. */
    private function detalhes(Subscription $inscricao): array
    {
        $detalhes = $inscricao->package_details;
        if (is_string($detalhes)) {
            $detalhes = json_decode($detalhes, true);
        }

        return is_array($detalhes) ? $detalhes : [];
    }

    /** Valor atual da chave de destino, para o relatório distinguir ausente de "0". */
    private function valor(array $chaves): string
    {
        return array_key_exists(self::CHAVE_DESTINO, $chaves)
            ? var_export($chaves[self::CHAVE_DESTINO], true)
            : 'ausente';
    }
}
