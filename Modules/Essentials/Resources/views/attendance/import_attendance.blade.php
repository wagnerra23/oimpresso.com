<div class="row">
    <div class="col-sm-12">
        {!! Form::open(['url' => action([\Modules\Essentials\Http\Controllers\AttendanceController::class, 'importAttendance']), 'method' => 'post', 'enctype' => 'multipart/form-data' ]) !!}
            <div class="row">
                <div class="col-sm-6">
                <div class="col-sm-8">
                    <div class="form-group">
                        {!! Form::label('name', __( 'product.file_to_import' ) . ':') !!}
                        {!! Form::file('attendance', ['accept'=> '.xls', 'required' => 'required']); !!}
                      </div>
                </div>
                <div class="col-sm-4">
                <br>
                    <button type="submit" class="btn btn-primary">@lang('messages.submit')</button>
                </div>
                </div>
            </div>

        {!! Form::close() !!}
        <br><br>
        <div class="row">
            <div class="col-sm-4">
                <a href="{{ asset('modules/essentials/files/import_attendance_template.xls') }}" class="btn btn-success" download><i class="fa fa-download"></i> @lang('lang_v1.download_template_file')</a>
            </div>
        </div>

        {{-- Relatório da importação (HRM-O6 / PR-6, achado A7).
             Prefere o do request atual (fila `sync`, relatório pronto antes do redirect);
             na ausência dele mostra o último do negócio, que é como o resultado chega
             quando o Job roda depois do redirect (fila `database`). --}}
        @php($relatorio_import = session('import_presenca_relatorio') ?: ($import_presenca_relatorio ?? null))
        @if (!empty($relatorio_import))
            <div class="row">
                <div class="col-md-12">
                    <h4>
                        @lang('essentials::lang.import_relatorio_titulo')
                        @if (!empty($relatorio_import['arquivo']))
                            <small>{{ $relatorio_import['arquivo'] }} &middot; {{ $relatorio_import['em'] ?? '' }}</small>
                        @endif
                    </h4>
                    @if (($relatorio_import['estado'] ?? null) === 'erro')
                        {{-- Falha do próprio import: não há contagem nem lista de recusas
                             pra mostrar, e afirmar "nenhuma linha recusada" aqui mentiria. --}}
                        <p class="text-danger">{{ $relatorio_import['mensagem'] ?? '' }}</p>
                    @else
                        <p class="text-muted">
                            @lang('essentials::lang.import_concluido', ['inseridas' => $relatorio_import['inseridas'] ?? 0])
                        </p>
                        @if (!empty($relatorio_import['recusadas']))
                            <table class="table table-condensed table-bordered" width="100%">
                                <tr>
                                    <th width="10%">@lang('essentials::lang.import_relatorio_linha')</th>
                                    <th>@lang('essentials::lang.import_relatorio_motivo')</th>
                                </tr>
                                @foreach ($relatorio_import['recusadas'] as $recusada)
                                    <tr>
                                        <td>{{ $recusada['linha'] }}</td>
                                        <td>{{ $recusada['motivo'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @else
                            <p class="text-muted">@lang('essentials::lang.import_relatorio_sem_recusas')</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-md-12">
                <table class="table" width="100%">
                    <tr>
                        <th>@lang('lang_v1.col_no')</th>
                        <th>@lang('lang_v1.col_name')</th>
                        <th>@lang('lang_v1.instruction')</th>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td>@lang('business.email') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>{!! __('essentials::lang.email_ins') !!}</td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>@lang('essentials::lang.clock_in_time') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                        <td>{!! __('essentials::lang.clock_in_time_ins') !!} ({{\Carbon::now()->toDateTimeString()}})</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>@lang('essentials::lang.clock_out_time') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>{!! __('essentials::lang.clock_out_time_ins') !!} ({{\Carbon::now()->toDateTimeString()}})</td>
                    </tr>
                    {{-- A coluna 4 do template `import_attendance_template.xls` é o Turno, e
                         esta tabela não a listava: quem seguia as instruções montava o arquivo
                         deslocado a partir daqui. Agora que a linha com turno inexistente é
                         RECUSADA com motivo, a instrução precisa dizer que a coluna existe. --}}
                    <tr>
                        <td>4</td>
                        <td>@lang('essentials::lang.shift') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>@lang('essentials::lang.clock_in_note') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>@lang('essentials::lang.clock_out_note') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <td>7</td>
                        <td>@lang('essentials::lang.ip_address') <small class="text-muted">(@lang('lang_v1.optional'))</small></td>
                        <td>&nbsp;</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>