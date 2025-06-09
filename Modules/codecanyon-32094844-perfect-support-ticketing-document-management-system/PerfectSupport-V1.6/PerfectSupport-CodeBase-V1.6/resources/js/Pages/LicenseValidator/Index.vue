<template>
	<layout :title="__('messages.validate_license')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
                    <div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.validate_license')}}
                        </h3>
                    </div>
                    <div class="card">
                        <div class="card-block">
                            <alert :content="error_message" type="danger"></alert>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="license_key">
                                            {{__('messages.license_key')}}*
                                        </label>
                                        <input type="text" class="form-control" id="license_key" v-model="form.license_key">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product">
                                            {{__('messages.product')}}*
                                        </label>
                                        <select class="form-control" id="product" v-model="form.product_id">
                                            <option value="">
                                                {{__('messages.please_select')}}
                                            </option>
                                            <template v-for="(product, id) in products">
                                                <option :value="id" v-text="product">
                                                </option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="source">
                                            {{__('messages.source')}}*
                                        </label>
                                        <select class="form-control" id="source" v-model="form.source_id">
                                            <option value="">
                                                {{__('messages.please_select')}}
                                            </option>
                                            <template v-for="(source, id) in sources">
                                                <option :value="id" v-text="source">
                                                </option>
                                            </template>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-4">
                                    <button type="button" class="btn btn-primary" @click="validateLicense" :disabled="loading">
                                        <i class="fas fa-spinner fa-pulse fa-spin" v-if="loading"></i>
                                        {{__('messages.validate')}}
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-md-6" v-show="!_.isEmpty(license.purchased_on)">
                                    <b>{{__('messages.purchased_on')}}:</b>
                                    {{$commonFunction.formatDate(license.purchased_on)}}
                                </div>
                                <div class="col-md-6" v-show="!_.isEmpty(license.support_expires_on)">
                                    <b>{{__('messages.support_expires_on')}}:</b>
                                    {{$commonFunction.formatDate(license.support_expires_on)}}
                                </div>
                                <div class="col-md-6" v-show="!_.isEmpty(license.license_expires_on)">
                                    <b>{{__('messages.license_expires_on')}}:</b>
                                    {{$commonFunction.formatDate(license.license_expires_on)}}
                                </div>
                                 <div class="col-md-6" v-if="!_.isEmpty(license.additional_info) && !_.isNull(license.additional_info.license_type)">
                                    <b>{{__('messages.license_type')}}:</b>
                                    {{ license.additional_info.license_type }}
                                </div>
                                <div class="col-md-6" v-if="!_.isEmpty(license.additional_info) && !_.isNull(license.additional_info.buyer)">
                                    <b>{{__('messages.buyer')}}:</b>
                                    {{license.additional_info.buyer}}
                                </div>
                            </div>
                            <div class="row mt-3"
                                v-show="!_.isEmpty(response_error)">
                                <div class="col-md-12">
                                    <div class="alert alert-danger" role="alert">
                                      {{response_error}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
				</div>
			</div>
		</div>
  	</layout>
</template>

<script>
	import Layout from '@/Shared/Layout';
    import Leftnav from '@/Pages/Elements/Leftnav';
	export default {
		components: {
			Layout,
            Leftnav,
		},
        props: ['products', 'sources'],
        data() {
            const self = this;
            return {
                form:{
                    license_key: '',
                    product_id: '',
                    source_id: ''
                },
                error_message: null,
                loading: false,
                license:{
                    license_expires_on: null,
                    purchased_on: null,
                    support_expires_on: null,
                    additional_info: []
                },
                response_error: null
            }
        },
		methods:{
            validateLicense() {
                const self = this;
                if(self.form.license_key.length <= 0){
                    self.error_message = self.__('messages.input_error');
                    return false;
                } else if (self.form.product_id.length <= 0) {
                    self.error_message = self.__('messages.input_error');
                    return false;
                } else if (self.form.source_id.length <= 0) {
                    self.error_message = self.__('messages.input_error');
                    return false;
                } else {
                    self.error_message = null;
                }
                self.license = {license_expires_on: null, purchased_on: null, support_expires_on: null,additional_info: []
                };
                self.response_error = null;
                self.loading = true;
                axios.post(self.route_ziggy('post.validate.license').url(), self.form)
                .then(function (result) {
                    self.loading = false;
                    if (result.data.success) {
                        if(result.data.response.success) {
                            self.license.license_expires_on = result.data.response.expires_on;
                            self.license.purchased_on = result.data.response.purchased_on;
                            self.license.support_expires_on = result.data.response.support_expires_on;
                            self.license.additional_info = result.data.response.additional_info;
                        } else {
                            self.response_error = result.data.response.msg;
                        }
                    } else {
                        self.response_error = result.data.msg;
                    }
                }).catch(function (error) {
                    console.error(error);
                }).then(function () {
                    //always executed
                })
            }
		}
	}
</script>