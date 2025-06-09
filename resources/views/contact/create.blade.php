<div class="modal-dialog modal-lg" role="document">
  <div class="modal-content">
  @php
    $form_id = 'contact_add_form';
    if(isset($quick_add)){
      $form_id = 'quick_add_contact';
    }

    if(isset($store_action)) {
      $url = $store_action;
      $type = 'lead';
      $customer_groups = [];
    } else {
      $url = action('ContactController@store');
      $type = isset($selected_type) ? $selected_type : '';
      $sources = [];
      $life_stages = [];
      $users = [];
    }
  @endphp
    {!! Form::open(['url' => $url, 'method' => 'post', 'id' => $form_id ]) !!}

    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang('contact.add_contact')</h4>
    </div>

    <div class="modal-body">
        <div class="row">            

            <div class="col-md-4 contact_type_div">
                <div class="form-group">
                    {!! Form::label('type', __('contact.contact_type') . ':*' ) !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-user"></i>
                        </span>
                        {!! Form::select('type', $types, $type , ['class' => 'form-control', 'id' => 'contact_type','placeholder' => __('messages.please_select'), 'required']); !!}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('contact_id', __('lang_v1.contact_id') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-id-badge"></i>
                    </span>
                    {!! Form::text('contact_id', null, ['class' => 'form-control','placeholder' => __('lang_v1.contact_id')]); !!}
                </div>
            </div>
        </div>

        <div class="clearfix"></div>        
	      <div class="col-md-2">
	      <div class="form-group">
	        {!! Form::label('tipo', 'Tipo' . ':') !!}
	        <div class="input-group" style="width: 100%;">

	          {!! Form::select('tipo', ['Jurídica' => 'Jurídica', 'Física' => 'Física', 'Outros' => 'Outros'], '', ['class' => 'form-control']); !!}
	        </div>
	      </div>
	    </div>
	    <div class="col-md-3">
	      <div class="form-group">

	        <label for="cpf_cnpj">CNPJ/CPF:</label>

	        <input class="form-control" required placeholder="CPF/CNPJ" data-mask="00.000.000/0000-00" name="cpf_cnpj" type="text" id="cpf_cnpj">
	      </div>
	    </div>
	    <div class="col-md-3">
	      <div class="form-group">
	        <label for="ie_rg">I.E/RG:</label>
	        <input class="form-control" placeholder="I.E/RG" name="ie_rg" type="number" id="ie_rg">
	      </div>
	    </div>      
        
      <div class="col-md-6">
          <div class="form-group">
              {!! Form::label('name', 'Razão Social/Nome:*') !!}
              {!! Form::text('name', null, ['class' => 'form-control', 'required', 'placeholder' => 'Razão Social/Nome']); !!}
          </div>
      </div>        
    
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('supplier_business_name', __('business.business_name') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-briefcase"></i>
                    </span>
                    {!! Form::text('supplier_business_name', null, ['class' => 'form-control', 'placeholder' => __('business.business_name')]); !!}
                </div>
            </div>
        </div>

        <div class="clearfix"></div>
        
        <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('mobile', __('contact.mobile') . ':*') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-mobile"></i>
                </span>
                {!! Form::text('mobile', null, ['class' => 'form-control', 'required', 'placeholder' => __('contact.mobile')]); !!}
            </div>
        </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('alternate_number', __('contact.alternate_contact_number') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-phone"></i>
                    </span>
                    {!! Form::text('alternate_number', null, ['class' => 'form-control', 'placeholder' => __('contact.alternate_contact_number')]); !!}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('landline', __('contact.landline') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-phone"></i>
                    </span>
                    {!! Form::text('landline', null, ['class' => 'form-control', 'placeholder' => __('contact.landline')]); !!}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('email', __('business.email') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-envelope"></i>
                    </span>
                    {!! Form::email('email', null, ['class' => 'form-control','placeholder' => __('business.email')]); !!}
                </div>
            </div>
        </div>
        <div class="clearfix"></div>      
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('dob', __('lang_v1.dob') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar"></i>
                    </span>
                    
                    {!! Form::text('dob', null, ['class' => 'form-control dob-date-picker','placeholder' => __('lang_v1.dob'), 'readonly']); !!}
                </div>
            </div>
        </div>
  
        <div class="col-md-4 customer_fields">
            <div class="form-group">
              {!! Form::label('customer_group_id', __('lang_v1.customer_group') . ':') !!}
              <div class="input-group">
                  <span class="input-group-addon">
                      <i class="fa fa-users"></i>
                  </span>
                  {!! Form::select('customer_group_id', $customer_groups, '', ['class' => 'form-control']); !!}
              </div>
            </div>
        </div>    
        <div class="col-md-2">
          <div class="form-group">
            {!! Form::label('tipo', 'UF' . ':') !!}
            <div class="input-group" style="width: 100%;">
              <span class="input-group-addon">
                <a onclick="buscaDados()"><i class="fa fa-search"></i></a>
              </span>

              {!! Form::select('uf', $estados, '', ['id' => 'uf2', 'class' => 'form-control select2']); !!}
            
            </div>
          </div>
        </div>       
        <div class="clearfix"></div>                        

        <!-- lead additional field -->
        <div class="col-md-4 lead_additional_div">
          <div class="form-group">
              {!! Form::label('crm_source', __('lang_v1.source') . ':' ) !!}
              <div class="input-group">
                  <span class="input-group-addon">
                      <i class="fas fa fa-search"></i>
                  </span>
                  {!! Form::select('crm_source', $sources, null , ['class' => 'form-control', 'id' => 'crm_source','placeholder' => __('messages.please_select')]); !!}
              </div>
          </div>
        </div>
        
        <div class="col-md-4 lead_additional_div">
          <div class="form-group">
              {!! Form::label('crm_life_stage', __('lang_v1.life_stage') . ':' ) !!}
              <div class="input-group">
                  <span class="input-group-addon">
                      <i class="fas fa fa-life-ring"></i>
                  </span>
                  {!! Form::select('crm_life_stage', $life_stages, null , ['class' => 'form-control', 'id' => 'crm_life_stage','placeholder' => __('messages.please_select')]); !!}
              </div>
          </div>
        </div>
        <div class="col-md-6 lead_additional_div">
          <div class="form-group">
              {!! Form::label('user_id', __('lang_v1.assigned_to') . ':*' ) !!}
              <div class="input-group">
                  <span class="input-group-addon">
                      <i class="fa fa-user"></i>
                  </span>
                  {!! Form::select('user_id[]', $users, null , ['class' => 'form-control select2', 'id' => 'user_id', 'multiple', 'required', 'style' => 'width: 100%;']); !!}
              </div>
          </div>
        </div>
        <div class="clearfix"></div>    
        <div class="col-md-12">
          <button type="button" class="btn btn-primary center-block" id="more_btn">@lang('lang_v1.more_info') <i class="fa fa-chevron-down"></i></button>

          <div id="more_div" class="hide">
          <div class="col-md-12"><hr/></div>      

          <div class="col-md-4 pay_term">
            <div class="form-group">
              <div class="multi-input">
                {!! Form::label('pay_term_number', __('contact.pay_term') . ':') !!} @show_tooltip(__('tooltip.pay_term'))
                <br/>
                {!! Form::number('pay_term_number', null, ['class' => 'form-control width-40 pull-left', 'placeholder' => __('contact.pay_term')]); !!}

                {!! Form::select('pay_term_type', ['months' => __('lang_v1.months'), 'days' => __('lang_v1.days')], '', ['class' => 'form-control width-60 pull-left','placeholder' => __('messages.please_select')]); !!}
              </div>
            </div>
          </div>
          <div class="col-md-4 opening_balance">
            <div class="form-group">
                {!! Form::label('opening_balance', __('lang_v1.opening_balance') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::text('opening_balance', 0, ['class' => 'form-control input_number']); !!}
                </div>
            </div>
          </div>            

          <div class="col-md-4 customer_fields">
            <div class="form-group">
                {!! Form::label('credit_limit', __('lang_v1.credit_limit') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::text('credit_limit', null, ['class' => 'form-control input_number']); !!}
                </div>
                <p class="help-block">@lang('lang_v1.credit_limit_help')</p>
            </div>
          </div>  

          <div class="clearfix"></div>             

          <div class="col-md-2">
            <div class="form-group">
              {!! Form::label('consumidor_final', 'Consumidor final' . ':') !!}
              <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::select('consumidor_final', ['1' => 'Sim', '0' => 'Não'], '', ['id' => 'consumidor_final', 'class' => 'form-control select2', 'required']); !!}
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="form-group">
              {!! Form::label('contribuinte', 'Contribuinte' . ':') !!}
              <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::select('contribuinte', ['1' => 'Sim', '0' => 'Não'], '', ['id' => 'contribuinte', 'class' => 'form-control select2', 'required']); !!}
              </div>
            </div>
          </div>      
          <div class="col-md-3">
            <div class="form-group">
              {!! Form::label('regime', 'Regime' . ':') !!}
              <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::select('regime', ['1' => 'Simples', '0' => 'Normal'], '', ['id' => 'regime', 'class' => 'form-control select2', 'required']); !!}
              </div>
            </div>
          </div>                
        

        <div class="col-md-12"><hr/></div>    
        <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('cep', 'CEP:') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::text('cep', null, ['class' => 'form-control', 'placeholder' => 'Informe o Cep']); !!}
            </div>
           </div>
        </div>        
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('rua','Rua:') !!}
                {!! Form::text('rua', null, ['class' => 'form-control', 'placeholder' => '' ]); !!}
            </div>
        </div>          
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('bairro','Bairro:') !!}
                {!! Form::text('bairro', null, ['class' => 'form-control', 'placeholder' => '' ]); !!}
            </div>
        </div>    
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('numero','Número:') !!}
                {!! Form::text('numero', null, ['class' => 'form-control', 'placeholder' => '' ]); !!}
            </div>
        </div>       

        <div class="clearfix"></div>    

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('address_line_1', __('lang_v1.address_line_1') . ':') !!}
                {!! Form::text('address_line_1', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.address_line_1'), 'rows' => 3]); !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('address_line_2', __('lang_v1.address_line_2') . ':') !!}
                {!! Form::text('address_line_2', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.address_line_2'), 'rows' => 3]); !!}
            </div>
        </div>
      <div class="clearfix"></div>

      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('city_id', 'Cidade:*') !!}
          <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::select('city_id', $cities, '', ['id' => 'cidade', 'class' => 'form-control select2', 'required']); !!}
            </div>
        </div>
      </div>

      <!-- 
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('city', __('business.city') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::text('city', null, ['class' => 'form-control', 'placeholder' => __('business.city')]); !!}
            </div>
        </div>
      </div>  -->

      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('state', __('business.state') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-map-marker"></i>
                </span>
                {!! Form::text('state', null, ['class' => 'form-control', 'placeholder' => __('business.state')]); !!}
            </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('country', __('business.country') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-globe"></i>
                </span>
                {!! Form::text('country', null, ['class' => 'form-control', 'placeholder' => __('business.country')]); !!}
            </div>
        </div>
      </div>

      <div class="clearfix"></div>
      <!--
      <div class="col-md-12"><hr/></div>
      @php
        $custom_labels = json_decode(session('business.custom_labels'), true);
        $contact_custom_field1 = !empty($custom_labels['contact']['custom_field_1']) ? $custom_labels['contact']['custom_field_1'] : __('lang_v1.contact_custom_field1');
        $contact_custom_field2 = !empty($custom_labels['contact']['custom_field_2']) ? $custom_labels['contact']['custom_field_2'] : __('lang_v1.contact_custom_field2');
        $contact_custom_field3 = !empty($custom_labels['contact']['custom_field_3']) ? $custom_labels['contact']['custom_field_3'] : __('lang_v1.contact_custom_field3');
        $contact_custom_field4 = !empty($custom_labels['contact']['custom_field_4']) ? $custom_labels['contact']['custom_field_4'] : __('lang_v1.contact_custom_field4');
        $contact_custom_field5 = !empty($custom_labels['contact']['custom_field_5']) ? $custom_labels['contact']['custom_field_5'] : __('lang_v1.custom_field', ['number' => 5]);
        $contact_custom_field6 = !empty($custom_labels['contact']['custom_field_6']) ? $custom_labels['contact']['custom_field_6'] : __('lang_v1.custom_field', ['number' => 6]);
        $contact_custom_field7 = !empty($custom_labels['contact']['custom_field_7']) ? $custom_labels['contact']['custom_field_7'] : __('lang_v1.custom_field', ['number' => 7]);
        $contact_custom_field8 = !empty($custom_labels['contact']['custom_field_8']) ? $custom_labels['contact']['custom_field_8'] : __('lang_v1.custom_field', ['number' => 8]);
        $contact_custom_field9 = !empty($custom_labels['contact']['custom_field_9']) ? $custom_labels['contact']['custom_field_9'] : __('lang_v1.custom_field', ['number' => 9]);
        $contact_custom_field10 = !empty($custom_labels['contact']['custom_field_10']) ? $custom_labels['contact']['custom_field_10'] : __('lang_v1.custom_field', ['number' => 10]);
      @endphp
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field1', $contact_custom_field1 . ':') !!}
            {!! Form::text('custom_field1', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field1]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field2', $contact_custom_field2 . ':') !!}
            {!! Form::text('custom_field2', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field2]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field3', $contact_custom_field3 . ':') !!}
            {!! Form::text('custom_field3', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field3]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field4', $contact_custom_field4 . ':') !!}
            {!! Form::text('custom_field4', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field4]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field5', $contact_custom_field5 . ':') !!}
            {!! Form::text('custom_field5', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field5]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field6', $contact_custom_field6 . ':') !!}
            {!! Form::text('custom_field6', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field6]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field7', $contact_custom_field7 . ':') !!}
            {!! Form::text('custom_field7', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field7]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field8', $contact_custom_field8 . ':') !!}
            {!! Form::text('custom_field8', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field8]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field9', $contact_custom_field9 . ':') !!}
            {!! Form::text('custom_field9', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field9]); !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('custom_field10', $contact_custom_field10 . ':') !!}
            {!! Form::text('custom_field10', null, ['class' => 'form-control', 
                'placeholder' => $contact_custom_field10]); !!}
        </div>
      </div>
      <div class="col-md-12 shipping_addr_div"><hr></div>
      <div class="col-md-8 col-md-offset-2 shipping_addr_div" >
          <strong>{{__('lang_v1.shipping_address')}}</strong><br>
          {!! Form::text('shipping_address', null, ['class' => 'form-control', 
                'placeholder' => __('lang_v1.search_address'), 'id' => 'shipping_address']); !!}
        <div id="map"></div>
      </div>
      -->      
      {!! Form::hidden('position', null, ['id' => 'position']); !!}
      </div>
    </div>

  
    
    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">@lang( 'messages.save' )</button>
      <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>
</div>    

    {!! Form::close() !!}
  


<script type="text/javascript">
  $('#cpf_cnpj').mask('00.000.000/0000-00')
  $('#cep').mask('00000-000')
  $('#tipo').change((val) => {
    let t = $('#tipo').val()

    if(t == 'Jurídica'){
      $('#cpf_cnpj').mask('00.000.000/0000-00')
    }else
      if(t == 'Física'){
        $('#cpf_cnpj').mask('000.000.000-00');      
      }else{
        $('#cpf_cnpj').mask('999999999999999999');
      }
  })

  function buscaDados(){
    let uf = $('#uf2').val();
    let cnpj = $('#cpf_cnpj').val();

    var path = window.location.protocol + '//' + window.location.host
    $.ajax
    ({
      type: 'GET',
      data: {
        cnpj: cnpj,
        uf: uf
      },
      url: path + '/nfe/consultaCadastro',

      dataType: 'json',
      success: function(e){
        console.log(e)
        if(e.infCons.infCad){
          let info = e.infCons.infCad;
          console.log(info)

          $('#ie_rg').val(info.IE)
          $('#name').val(info.xNome)
          $('#nome_fantasia').val(info.xFant ? info.xFant : info.xNome)

          $('#rua').val(info.ender.xLgr)
          $('#numero').val(info.ender.nro)
          $('#bairro').val(info.ender.xBairro)
          let cep = info.ender.CEP;
          $('#cep').val(cep.substring(0, 5) + '-' + cep.substring(5, 9))

          

          findCidade(info.ender.xMun, (res) => {

            if(res){

              var $option = $("<option selected></option>").val(res.id).text(res.nome + " (" + res.uf + ")");
              $('#cidade').append($option).trigger('change');

            }
          })

        }else{
          swal('Algo deu errado', e.infCons.xMotivo, 'warning')
        }
      },
      error: function(e){
        console.log("err",e.responseText)
        swal('Algo deu errado', e.responseText, 'warning')

      }
    });
  }

  function findCidade(nomeCidade, call){
    var path = window.location.protocol + '//' + window.location.host
    $.get(path + '/nfe/findCidade', {nome: nomeCidade} )
    .done((success) => {
      call(success)
    })
    .fail((err) => {
      call(err)
    })
  }

  function limpa_formulário_cep() {
    // Limpa valores do formulário de cep.
    $("#rua").val("");
    $("#bairro").val("");
    $("#city").val("");
    $("#state").val("");
    $("#city_id").val("");
  }
    
  //Quando o campo cep perde o foco.
  $("#cep").blur(function() {

      //Nova variável "cep" somente com dígitos.
      var cep = $(this).val().replace(/\D/g, '');

      //Verifica se campo cep possui valor informado.
      if (cep != "") {

          //Expressão regular para validar o CEP.
          var validacep = /^[0-9]{8}$/;

          //Valida o formato do CEP.
          if(validacep.test(cep)) {

              //Preenche os campos com "..." enquanto consulta webservice.
              $("#rua").val("...");
              $("#bairro").val("...");
              $("#city").val("...");
              $("#state").val("...");
              $("#city_id").val("...");

              //Consulta o webservice viacep.com.br/
              $.getJSON("https://viacep.com.br/ws/"+ cep +"/json/?callback=?", function(dados) {

                  if (!("erro" in dados)) {
                      //Atualiza os campos com os valores da consulta.
                      $("#rua").val(dados.logradouro);
                      $("#bairro").val(dados.bairro);
                      $("#city").val(dados.localidade);
                      $("#state").val(dados.uf);
                      $("#city_id").val(dados.ibge);
                  } //end if.
                  else {
                      //CEP pesquisado não foi encontrado.
                      limpa_formulário_cep();
                      alert("CEP não encontrado.");
                  }
              });
          } //end if.
          else {
              //cep é inválido.
              limpa_formulário_cep();
              alert("Formato de CEP inválido.");
          }
      } //end if.
      else {
          //cep sem valor, limpa formulário.
          limpa_formulário_cep();
      }
  });

</script>

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->