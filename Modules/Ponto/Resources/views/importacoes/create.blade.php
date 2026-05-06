@extends('pontowr2::layouts.module')

@section('title', 'Nova importação AFD')

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.importacoes') }}
            <small>Upload de arquivo AFD / AFDT</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-upload"></i>
                        Upload do arquivo
                        <small class="text-muted">— Portaria MTP 671/2021</small>
                    @endslot

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul style="margin-bottom:0;">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('ponto.importacoes.store') }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label for="tipo">Tipo de arquivo <span class="text-red">*</span></label>
                            <select name="tipo" id="tipo" class="form-control" required>
                                <option value="AFD"  @if(old('tipo', 'AFD') === 'AFD')  selected @endif>AFD — Arquivo Fonte de Dados</option>
                                <option value="AFDT" @if(old('tipo') === 'AFDT') selected @endif>AFDT — Arquivo Fonte de Dados Tratados</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="arquivo">Arquivo <span class="text-red">*</span></label>
                            <input type="file" name="arquivo" id="arquivo" class="form-control" required>
                            <small class="text-muted">
                                Formato texto conforme layout Portaria 671/2021. Arquivos duplicados
                                (mesmo hash SHA-256) são rejeitados automaticamente.
                            </small>
                        </div>

                        <div class="callout callout-info">
                            <h4><i class="fa fas fa-info-circle"></i> Processamento assíncrono</h4>
                            <p>
                                O arquivo será enfileirado em <code>ProcessarImportacaoAfdJob</code>
                                e processado em segundo plano. Você pode acompanhar o status
                                na tela de detalhes após o upload.
                            </p>
                        </div>

                        <div class="text-right">
                            <a href="{{ route('ponto.importacoes.index') }}" class="btn btn-default">
                                <i class="fa fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fas fa-upload"></i> Enviar para processamento
                            </button>
                        </div>
                    </form>
                @endcomponent
            </div>
        </div>
    </section>
@endsection
