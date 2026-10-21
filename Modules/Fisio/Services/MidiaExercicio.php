<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Fisio\Entities\Exercise;

/**
 * Foto ou vídeo curto do exercício PRÓPRIO da clínica (UC-FBI-05..07).
 *
 * Armazenamento: o backbone `Modules/Arquivos` (ADR 0123 / ADR 0214), o mesmo que as fotos do
 * laudo da OficinaAuto usam — nenhuma dependência nova e nenhum disco novo. O
 * `ArquivosService::attach` grava sob `biz-{business_id}/AAAA/MM/<md5>.<ext>`, lê a empresa da
 * sessão (nunca do corpo) e registra a trilha em `arquivos_audit_log`. O vínculo é polimórfico:
 * `arquivable` = o exercício. A tela nunca recebe o caminho de disco: a mídia é servida pela rota
 * `fisio.biblioteca.exercicios.midia.show`, que confere a empresa.
 *
 * LGPD (ADR 0437 §4 e §7; HANDOFF-F1 "não guarde vídeo, foto ou áudio do paciente"): a mídia é
 * do EXERCÍCIO, nunca do paciente. Não há vínculo com paciente em lugar nenhum, e o envio exige a
 * declaração de que a mídia não mostra paciente (validada no servidor).
 *
 * Uma mídia por exercício: anexar de novo troca a anterior. Remover é a exclusão reversível do
 * backbone (`softDelete`); a purga física é da retenção do Arquivos, que só apaga o arquivo quando
 * nenhuma outra linha aponta para ele (`ArquivosService::blobCompartilhado`).
 */
final class MidiaExercicio
{
    /** Tipos aceitos → o que a tela mostra. Lista fechada: quem decide é o servidor. */
    public const TIPOS = [
        'image/jpeg' => 'foto',
        'image/png' => 'foto',
        'image/webp' => 'foto',
        'video/mp4' => 'video',
        // O detector de conteúdo devolve `application/mp4` para parte dos MP4 (e o Laravel, para
        // arquivo sem conteúdo inspecionável, chuta pelo nome). É o mesmo contêiner: aceito.
        'application/mp4' => 'video',
    ];

    /** Content-Type entregue ao navegador por tipo — o player precisa de `video/mp4`. */
    public static function contentType(string $mimeGravado): string
    {
        return self::tipo($mimeGravado) === 'video' ? 'video/mp4' : $mimeGravado;
    }

    public function __construct(private ArquivosService $arquivos) {}

    public static function fotoMaxKb(): int
    {
        return (int) config('fisio.midia.foto_max_kb', 10240);
    }

    public static function videoMaxKb(): int
    {
        return (int) config('fisio.midia.video_max_kb', 51200);
    }

    public static function tipo(string $mime): ?string
    {
        return self::TIPOS[$mime] ?? null;
    }

    /** @return array<string, mixed> */
    public static function regras(): array
    {
        return [
            'midia' => [
                'bail',
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,mp4',
                'mimetypes:'.implode(',', array_keys(self::TIPOS)),
                // Teto por TIPO: foto e vídeo têm limites diferentes.
                function (string $atributo, mixed $arquivo, \Closure $falhar): void {
                    if (! $arquivo instanceof UploadedFile) {
                        return;
                    }
                    $kb = (int) ceil(((int) $arquivo->getSize()) / 1024);
                    $tipo = self::tipo((string) $arquivo->getMimeType());
                    if ($tipo === 'foto' && $kb > self::fotoMaxKb()) {
                        $falhar('A foto passa de '.self::mb(self::fotoMaxKb()).' MB. Envie uma foto menor.');
                    } elseif ($tipo === 'video' && $kb > self::videoMaxKb()) {
                        $falhar('O vídeo passa de '.self::mb(self::videoMaxKb()).' MB. Envie um vídeo mais curto.');
                    }
                },
            ],
            'declaro_sem_paciente' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'midia.required' => 'Escolha uma foto ou um vídeo.',
            'midia.file' => 'O envio falhou. Tente de novo.',
            'midia.uploaded' => 'O envio falhou. O arquivo pode ser grande demais.',
            'midia.mimes' => 'Use foto JPG, PNG ou WebP, ou vídeo MP4.',
            'midia.mimetypes' => 'Use foto JPG, PNG ou WebP, ou vídeo MP4.',
            'declaro_sem_paciente.accepted' => 'Confirme que a foto ou o vídeo não mostra paciente.',
            'declaro_sem_paciente.required' => 'Confirme que a foto ou o vídeo não mostra paciente.',
        ];
    }

    /**
     * Anexa a mídia ao exercício próprio e tira a anterior. Anexa ANTES de tirar: se o envio
     * falhar, a mídia antiga continua.
     */
    public function anexar(Exercise $exercicio, UploadedFile $arquivo): Arquivo
    {
        $novo = $this->arquivos->attach($exercicio, $arquivo, ['context' => 'fisio-exercicio-midia']);

        foreach ($this->vinculos($exercicio)->where('id', '!=', $novo->id)->get() as $antigo) {
            /** @var Arquivo $antigo */
            $this->arquivos->softDelete($antigo, ['motivo' => 'trocada por nova mídia do exercício']);
        }

        return $novo;
    }

    /** Tira a mídia do exercício. Devolve quantas saíram (0 quando não havia). */
    public function remover(Exercise $exercicio): int
    {
        $n = 0;
        foreach ($this->vinculos($exercicio)->get() as $arquivo) {
            /** @var Arquivo $arquivo */
            $this->arquivos->softDelete($arquivo, ['motivo' => 'mídia removida do exercício']);
            $n++;
        }

        return $n;
    }

    /**
     * A mídia vigente do exercício, para servir. Segunda camada além do escopo do `Arquivo` (que
     * já filtra pela sessão): o `business_id` explícito e o tipo da lista fechada.
     */
    public function vigente(Exercise $exercicio): ?Arquivo
    {
        $arquivo = $this->vinculos($exercicio)->orderByDesc('id')->first();
        if (! $arquivo instanceof Arquivo || self::tipo((string) $arquivo->mime_type) === null || ! $arquivo->temConteudo()) {
            return null;
        }

        return $arquivo;
    }

    /**
     * O que a grade e o formulário recebem: só o tipo e a rota que serve com checagem de empresa.
     * Sem nome de arquivo, caminho de disco nem hash. Exercício global não tem mídia de clínica.
     *
     * @return array{tipo: string, url: string}|null
     */
    public static function resumo(Exercise $exercicio): ?array
    {
        if ($exercicio->ehGlobal()) {
            return null;
        }
        $arquivo = $exercicio->relationLoaded('arquivos')
            ? $exercicio->getRelation('arquivos')->sortByDesc('id')->first()
            : $exercicio->arquivos()->orderByDesc('id')->first();
        if (! $arquivo instanceof Arquivo) {
            return null;
        }
        $tipo = self::tipo((string) $arquivo->mime_type);
        if ($tipo === null) {
            return null;
        }

        return ['tipo' => $tipo, 'url' => "/fisio/biblioteca/exercicios/{$exercicio->id}/midia?v={$arquivo->id}"];
    }

    /** @return Builder<Arquivo> */
    private function vinculos(Exercise $exercicio): Builder
    {
        return Arquivo::query()
            ->where('arquivable_type', Exercise::class)
            ->where('arquivable_id', $exercicio->id)
            ->where('business_id', (int) session('user.business_id'));
    }

    private static function mb(int $kb): string
    {
        return (string) (int) round($kb / 1024);
    }
}
