<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Services;

use App\Contracts\Arquivos\AnexosDoApp;
use App\Support\AssinaturaPng;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\ComunicacaoVisual\Entities\Instalacao;
use Modules\ComunicacaoVisual\Entities\InstalacaoComprovante;

/**
 * Comprovante da instalação de campo — US-COMVIS-007 · UC-PCV-25 (1a: gravar; a tela e o protocolo PDF vêm depois).
 *
 * A instalação só conclui com foto antes, foto depois e a assinatura do cliente. Foto pode ser refeita
 * enquanto a instalação está aberta (vale a mais nova); a assinatura é colhida uma vez e não se troca.
 * Concluída ou cancelada, não recebe mais nada.
 *
 * Os arquivos vão para o módulo Arquivos pelo contrato do núcleo (`AnexosDoApp`), anexados à linha do
 * comprovante. Sem o módulo carregado o contrato devolve null e o envio é recusado — o comprovante nunca
 * fica sem o arquivo.
 *
 * Tier 0 (ADR 0093): toda consulta filtra o business_id recebido; instalação de outra empresa = não encontrada.
 * Violação de regra → \DomainException (a mensagem vai para a tela); instalação alheia → ModelNotFoundException.
 */
class ComprovanteInstalacao
{
    public const FOTOS = ['foto_antes', 'foto_depois'];

    public const EXTENSOES_FOTO = ['jpg', 'jpeg', 'png', 'webp', 'heic'];

    public function __construct(private readonly AnexosDoApp $anexos) {}

    /**
     * @return array{foto_antes: bool, foto_depois: bool, assinatura: array{assinado_por: ?string, em: string}|null,
     *               status: string, pode_concluir: bool}
     */
    public function estado(int $businessId, int $instalacaoId): array
    {
        $instalacao = $this->instalacao($businessId, $instalacaoId);
        $tem = $this->presentes($businessId, $instalacao->id);
        $ass = $tem['assinatura'] ?? null;

        return [
            'foto_antes' => isset($tem['foto_antes']),
            'foto_depois' => isset($tem['foto_depois']),
            'assinatura' => $ass === null ? null : ['assinado_por' => $ass->assinado_por, 'em' => (string) $ass->created_at],
            'status' => $instalacao->status,
            'pode_concluir' => $this->aberta($instalacao) && count($tem) === 3,
        ];
    }

    public function foto(int $businessId, int $instalacaoId, string $tipo, UploadedFile $arquivo, ?int $userId): void
    {
        if (! in_array($tipo, self::FOTOS, true)) {
            throw new \DomainException('Tipo de foto inválido: use foto_antes ou foto_depois.');
        }
        if (! in_array(strtolower($arquivo->getClientOriginalExtension()), self::EXTENSOES_FOTO, true)) {
            throw new \DomainException('A foto precisa ser uma imagem ('.implode(', ', self::EXTENSOES_FOTO).').');
        }
        $this->gravar($businessId, $instalacaoId, ['tipo' => $tipo, 'user_id' => $userId], $arquivo);
    }

    public function assinatura(int $businessId, int $instalacaoId, string $dataUrl, string $assinadoPor, bool $consentimento, ?int $userId): void
    {
        $nome = trim($assinadoPor);
        if ($nome === '') {
            throw new \DomainException('Informe o nome de quem assinou.');
        }
        if (! $consentimento) {
            throw new \DomainException('Sem o aceite do cliente para guardar a assinatura, ela não é gravada (LGPD).');
        }
        $png = AssinaturaPng::decodificar($dataUrl);
        if ($png === null) {
            throw new \DomainException('Assinatura inválida: envie o desenho em PNG de até 200 KB.');
        }
        $caminho = tempnam(sys_get_temp_dir(), 'cvass');
        try {
            file_put_contents($caminho, $png);
            $arquivo = new UploadedFile($caminho, 'assinatura-instalacao-'.$instalacaoId.'.png', 'image/png', null, true);
            $this->gravar($businessId, $instalacaoId, ['tipo' => 'assinatura', 'assinado_por' => mb_substr($nome, 0, 120),
                'consentimento_em' => now(), 'user_id' => $userId], $arquivo);
        } finally {
            @unlink($caminho);
        }
    }

    public function concluir(int $businessId, int $instalacaoId): void
    {
        DB::transaction(function () use ($businessId, $instalacaoId) {
            $instalacao = $this->instalacao($businessId, $instalacaoId, true);
            $this->exigirAberta($instalacao);
            $falta = array_diff(InstalacaoComprovante::TIPOS, array_keys($this->presentes($businessId, $instalacao->id)));
            if ($falta !== []) {
                $nomes = ['foto_antes' => 'foto antes', 'foto_depois' => 'foto depois', 'assinatura' => 'assinatura do cliente'];
                throw new \DomainException('Para concluir falta: '.implode(', ', array_map(fn ($t) => $nomes[$t], $falta)).'.');
            }
            $instalacao->forceFill(['status' => 'concluida', 'data_realizada' => now()])->save();
        });
    }

    /** @param array<string, mixed> $campos */
    private function gravar(int $businessId, int $instalacaoId, array $campos, UploadedFile $arquivo): void
    {
        DB::transaction(function () use ($businessId, $instalacaoId, $campos, $arquivo) {
            $instalacao = $this->instalacao($businessId, $instalacaoId, true);
            $this->exigirAberta($instalacao);
            if ($campos['tipo'] === 'assinatura' && isset($this->presentes($businessId, $instalacao->id)['assinatura'])) {
                throw new \DomainException('A assinatura desta instalação já foi colhida e não pode ser trocada.');
            }
            $linha = InstalacaoComprovante::query()->create($campos + ['business_id' => $businessId, 'instalacao_id' => $instalacao->id]);
            if ($this->anexos->anexar($businessId, InstalacaoComprovante::class, $linha->id, $arquivo) === null) {
                throw new \DomainException('O módulo Arquivos não está ativo nesta empresa: o comprovante não foi gravado.');
            }
        });
    }

    /**
     * Comprovantes com arquivo ativo, o mais novo de cada tipo.
     *
     * @return array<string, object>
     */
    private function presentes(int $businessId, int $instalacaoId): array
    {
        $linhas = DB::table('cv_instalacao_comprovantes as c')
            ->join('arquivos as f', function ($j) {
                $j->on('f.arquivable_id', '=', 'c.id')->where('f.arquivable_type', '=', InstalacaoComprovante::class);
            })
            ->where('c.business_id', $businessId)->where('f.business_id', $businessId)
            ->where('c.instalacao_id', $instalacaoId)->whereNull('f.deleted_at')
            ->orderBy('c.id')->get(['c.id', 'c.tipo', 'c.assinado_por', 'c.created_at']);

        $por = [];
        foreach ($linhas as $l) {
            $por[$l->tipo] = $l;
        }

        return $por;
    }

    private function instalacao(int $businessId, int $id, bool $travar = false): Instalacao
    {
        $q = Instalacao::query()->where('cv_instalacoes.business_id', $businessId)->whereKey($id);

        return ($travar ? $q->lockForUpdate() : $q)->first() ?? throw (new ModelNotFoundException)->setModel(Instalacao::class, [$id]);
    }

    private function aberta(Instalacao $instalacao): bool
    {
        return ! in_array($instalacao->status, ['concluida', 'cancelada'], true);
    }

    private function exigirAberta(Instalacao $instalacao): void
    {
        if (! $this->aberta($instalacao)) {
            $nome = ['concluida' => 'concluída', 'cancelada' => 'cancelada'][$instalacao->status] ?? $instalacao->status;
            throw new \DomainException('A instalação está '.$nome.' e não recebe mais comprovante.');
        }
    }
}
