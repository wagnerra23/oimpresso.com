<template>
	<layout :title="__('messages.edit_purchase')">
		<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-wrapper">
        	<div class="row">
        		<div class="col-md-12">
        			<div class="card">
			        	<div class="card-header">
			        		<h5>
                                {{__('messages.edit_purchase')}}               
                            </h5>
			        	</div>
			        	<form method="POST" @submit.prevent="submitForm">
				            <div class="card-body">
				            	<div class="row">
                                    <div class="col-md-6">
                                    	<div class="form-group">
                                            <label for="customer">
                                                {{__('messages.customer')}}*
                                            </label>
                                            <select :class="['form-control', $page.errors.user_id ? 'is-invalid': '']" id="customer" v-model="new_purchase.user_id" required>
                                            	<option value="">
                                            		{{__('messages.please_select')}}
                                            	</option>
                                                <option v-for="(name, key) in customers" :value="key" v-text="name">
                                                </option>
                                            </select>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.user_id">
                                                <strong>
                                                	{{ $page.errors.user_id[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                    	<div class="form-group">
                                            <label for="product">
                                                {{__('messages.product')}}*
                                            </label>
                                            <select :class="['form-control', $page.errors.product_id ? 'is-invalid': '']" id="product" v-model="new_purchase.product_id" required @change="getSources(new_purchase.product_id)">
                                            	<option value="">
                                            		{{__('messages.please_select')}}
                                            	</option>
                                                <option v-for="(name, key) in products" :value="key" v-text="name">
                                                </option>
                                            </select>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.product_id">
                                                <strong>
                                                	{{ $page.errors.product_id[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                	<div class="col-md-6">
                                		<div class="form-group">
                                            <label for="source">
                                                {{__('messages.source')}}*
                                            </label>
                                            <select :class="['form-control', $page.errors.source_id ? 'is-invalid': '']" id="source" v-model="new_purchase.source_id" required>
                                            	<option value="">
                                            		{{__('messages.please_select')}}
                                            	</option>
                                                <option v-for="source in sources" :value="source.id" v-text="source.name">
                                                </option>
                                            </select>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.source_id">
                                                <strong>
                                                	{{ $page.errors.source_id[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                	</div>
                                	<div class="col-md-6">
                                		<div class="form-group">
                                			<label for="license_key">{{__('messages.license_key')}}*</label>
                                			<input type="text" id="license_key" :class="['form-control', $page.errors.license_key ? 'is-invalid': '']" v-model="new_purchase.license_key" required>
                                			<span class="invalid-feedback" role="alert" v-if="$page.errors.license_key">
                                                <strong>
                                                	{{ $page.errors.license_key[0] }}
                                                </strong>
                                            </span>
                                		</div>
                                	</div>
                                </div>
                                <div class="row">
                                	<div class="col-md-4">
										<div class="form-group">
											<label for="purchased_on">
												{{__('messages.purchased_on')}}
											</label>
											<input type="text" id="purchased_on" :class="['form-control', $page.errors.purchased_on ? 'is-invalid': '']" name="purchased_on" readonly>
											<span class="invalid-feedback" role="alert" v-if="$page.errors.purchased_on">
                                                <strong>
                                                	{{ $page.errors.purchased_on[0] }}
                                                </strong>
                                            </span>
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label for="support_expires_on">
												{{__('messages.support_expires_on')}}
											</label>
											<input type="text" id="support_expires_on" :class="['form-control', $page.errors.support_expires_on ? 'is-invalid': '']" name="support_expires_on" readonly>
											<span class="invalid-feedback" role="alert" v-if="$page.errors.support_expires_on">
                                                <strong>
                                                	{{ $page.errors.support_expires_on[0] }}
                                                </strong>
                                            </span>
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group">
											<label for="license_expires_on">
												{{__('messages.license_expires_on')}}
											</label>
											<input type="text" id="license_expires_on" :class="['form-control', $page.errors.expires_on ? 'is-invalid': '']" name="license_expires_on" readonly>
											<span class="invalid-feedback" role="alert" v-if="$page.errors.expires_on">
                                                <strong>
                                                	{{ $page.errors.expires_on[0] }}
                                                </strong>
                                            </span>
										</div>
									</div>
                                </div>
                                <loading-button :loading="submitting" class="btn btn-success float-right" type="submit">
                                   {{__('messages.update')}}
                                </loading-button>
				            </div>
				        </form>
			        </div>
        		</div>
        	</div>
        </div>
	</layout>
</template>
<script>
	import Layout from '@/Shared/Layout';
	import Leftnav from '@/Pages/Elements/Leftnav';
    import LoadingButton from '@/Shared/LoadingButton';
	export default {
		components: {
			Layout,
			Leftnav,
			LoadingButton
		},
		props:['customers', 'products', 'license'],
		data: function () {
			return {
				new_purchase: {
                    user_id: '',
                    product_id: '',
                    license_key: '',
                    source_id: '',
                    purchased_on: null,
                    support_expires_on: null,
                    expires_on: null
                },
                submitting: false,
                sources:[]
			}
		},
		mounted() {
  			const self = this;
  			$(function () {
  				$('#purchased_on').daterangepicker({
			    	singleDatePicker: true,
    				showDropdowns: true,
    				timePicker: true,
                    locale: {
                        cancelLabel: self.__('messages.clear'),
                        format: 'YYYY-MM-DD hh:mm A'
                    }
			    }).on('apply.daterangepicker', function(ev, picker) {
			    	self.new_purchase.purchased_on = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('hide.daterangepicker', function(ev, picker) {
					self.new_purchase.purchased_on = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                    self.new_purchase.purchased_on = null;
                });


                 //set date time
                if (!_. isEmpty(self.license.purchased_on)) {
                    $("#purchased_on").data('daterangepicker').setStartDate(moment(self.license.purchased_on));
                    $("#purchased_on").data('daterangepicker').setEndDate(moment(self.license.purchased_on));
                    self.new_purchase.purchased_on = self.license.purchased_on;
                } else {
                    $('#purchased_on').val('');
                }

				$('#support_expires_on').daterangepicker({
			    	singleDatePicker: true,
    				showDropdowns: true,
    				timePicker: true,
                    locale: {
                        cancelLabel: self.__('messages.clear'),
                        format: 'YYYY-MM-DD hh:mm A'
                    }
			    }).on('apply.daterangepicker', function(ev, picker) {
			    	self.new_purchase.support_expires_on = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('hide.daterangepicker', function(ev, picker) {
					self.new_purchase.support_expires_on = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                    self.new_purchase.support_expires_on = null;
                });

                if (!_. isEmpty(self.license.support_expires_on)) {
                    $("#support_expires_on").data('daterangepicker').setStartDate(moment(self.license.support_expires_on));
                    $("#support_expires_on").data('daterangepicker').setEndDate(moment(self.license.support_expires_on));
                    self.new_purchase.support_expires_on = self.license.support_expires_on;
                } else {
                    $('#support_expires_on').val('');
                }

				$('#license_expires_on').daterangepicker({
			    	singleDatePicker: true,
    				showDropdowns: true,
    				timePicker: true,
                    locale: {
                        cancelLabel: self.__('messages.clear'),
                        format: 'YYYY-MM-DD hh:mm A'
                    }
			    }).on('apply.daterangepicker', function(ev, picker) {
			    	self.new_purchase.expires_on = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('hide.daterangepicker', function(ev, picker) {
					self.new_purchase.expires_on = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                    self.new_purchase.expires_on = null;
                });

                if (!_. isEmpty(self.license.expires_on)) {
                    $("#license_expires_on").data('daterangepicker').setStartDate(moment(self.license.expires_on));
                    $("#license_expires_on").data('daterangepicker').setEndDate(moment(self.license.expires_on));
                    self.new_purchase.expires_on = self.license.expires_on;
                } else {
                    $('#license_expires_on').val('');
                }
  			});
  		},
        created() {
            const self = this;
            self.new_purchase.user_id =  self.license.user_id;
            self.new_purchase.product_id = self.license.product_id;
            self.new_purchase.license_key = self.license.product_license_key;
            self.getSources(self.license.product_id);
            self.new_purchase.source_id = self.license.source_id;
        },
		methods:{
			submitForm(){
                const self = this;
                self.submitting = true;
                self.$inertia.put(self.route_ziggy('update.purchase', [self.license.id]), self.new_purchase)
                .then(function(response){
                    self.submitting = false;
                });
            },
            getSources(product_id) {
            	if (!_.isUndefined(product_id)) {
            		const self = this;
            		axios.get(this.route_ziggy('product.sources', [product_id]).url())
					.then(function (response) {
						if (response.data.success) {
							self.sources = response.data.product.sources;
						}
					})
					.catch(function (error) {
					    console.log(error);
					})
					.then(function () {
					    // always executed
					});
            	}
            }
		}
	}
</script>