<div class="modal-dialog" role="document">
  	<div class="modal-content">

    {!! Form::open(['url' => action('ConvenioController@store'), 'method' => 'post', 'id' => 'convenio_form' ]) !!}
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">Adicionar</h4>
    </div>

    <div class="modal-body">
      	<div class="form-group">
        	{!! Form::label('convenio_descricao', 'Descrição:*') !!}
          	{!! Form::text('convenio_descricao', null, ['class' => 'form-control', 'required', 'placeholder' => 'Campo livre para descrição do convênio']); !!}
        </div>
        <div class="form-group">
        	{!! Form::label('convenio_numero', 'Convênio:*') !!}
          	{!! Form::text('convenio_numero', null, ['class' => 'form-control', 'required', 'placeholder' => 'Campo livre para descrição do convênio']); !!}
        </div>
        <div class="form-group">
        	{!! Form::label('convenio_carteira', 'Carteira*:') !!}
          	{!! Form::text('convenio_carteira', null, ['class' => 'form-control', 'required', 'placeholder' => 'Campo livre para descrição do convênio']); !!}
        </div>
      {{-- <div class="form-group">
        	{!! Form::label('account_id', 'Conta:') !!}
          	{!! Form::select('account_id', $account->pluck('name', 'id'), null, ['class' => 'form-control', 'placeholder' => __( 'messages.please_select' )]); !!}
      </div> --}}
    </div>

    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">@lang( 'messages.save' )</button>
      <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->