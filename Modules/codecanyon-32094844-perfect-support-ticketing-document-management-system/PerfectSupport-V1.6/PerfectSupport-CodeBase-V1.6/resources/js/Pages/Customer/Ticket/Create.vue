<template>
 	<layout :title="__('messages.new_ticket')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-wrapper">
	        <div class="page-header-title">
				<div class="row">
					<div class="col-md-4">
						<h3 class="m-b-10">
							{{__('messages.new_ticket')}}
						</h3>
					</div>
					<div class="col-md-8 float-right">
						<support-timing :classes="'btn-sm btn-danger float-right'"></support-timing>
					</div>
				</div>
			</div>
        	<div class="row">
		        <div class="col-md-12">        	
			        <form @submit.prevent="submitForm">        	
				        <div class="card">
				    		<div class="card-header">
				        		<h5>
				        			{{__('messages.create_ticket')}}
				        		</h5>
				    		</div>
				    		<div class="card-block">
								<div class="col-md-12 mb-5" v-if="hasSupportExpired">
									<div class="alert alert-danger alert-dismissible fade show" role="alert">
										<h3 class="text-danger">
											{{isSupportExpiredMsg}}
										</h3>
									</div>
								</div>
				    			<form-wizard title="" subtitle="" stepSize="sm" 
				    				@on-complete="submitForm" :finish-button-text="__('messages.submit')"
				    				ref="ticketForm" color="#3498db">
									<tab-content :title="__('messages.instruction')">
										<div v-html="instruction" v-if="instruction"></div>
									</tab-content>
									<tab-content :title="__('messages.product')" 
										:before-change="validateStepProduct">
										<div class="card bg-light">
											<div class="card-body">
												<alert :content="error_message" type="danger">
												</alert>
												<b>
													{{__('messages.select_the_product')}}
												</b>
												<div class="row mt-3 mb-3">
													<template  v-for="product in products">
														<div class="col-md-5 mb-2 offset-md-1">
															<div class="form-check">
																<input class="form-check-input" type="radio"
																	:value="product.id" :id="product.id" 
																	v-model="ticket_data.product_id"
																	@change="openLicenseModel(product.id)">
																<label class="form-check-label" :for="product.id">
																	{{product.name}}
																</label>
															</div>
														</div>
													</template>
												</div>
												<input type="hidden" class="form-control" v-model="ticket_data.license_id" required>
												<transition
												    name="custom-classes-transition"
												    enter-active-class="animate__animated animate__fadeInDown"
												    leave-active-class="animate__animated animate__fadeOut">
													<div class="row mt-2 mb-1"
														v-show="!_.isEmpty(departments)">
														<div class="col-md-12">
															<b class="mt-5">
																{{__('messages.department')}} *
															</b>
														</div>
														<template  v-for="(department, id) in departments">
															<div class="col-md-5 mb-2 offset-md-1">
																<div class="form-check">
																	<input class="form-check-input" type="radio"
																		:value="id" :id="department" 
																		v-model="ticket_data.product_department_id"
																		@change="getDepartmentInfo">
																	<label class="form-check-label" :for="department">
																		{{department}}
																	</label>
																</div>
															</div>
														</template>
													</div>
												</transition>
												<transition
												    name="custom-classes-transition"
												    enter-active-class="animate__animated animate__fadeInDown"
												    leave-active-class="animate__animated animate__fadeOut">
													<div class="row mb-3 mt-2"
														v-if="department_info">
														<div class="col-md-12" v-html="department_info"></div>
													</div>
												</transition>
												<transition
												    name="custom-classes-transition"
												    enter-active-class="animate__animated animate__fadeInDown"
												    leave-active-class="animate__animated animate__fadeOut">
													<div class="row mt-2"
														v-if="department_tickets.length > 0">
														<div class="col-md-12">
															<h4 class="mb-0">
																{{__('messages.related_tickets')}}
															</h4>
															<p class="mt-0">
																{{__('messages.check_tickets_you_might_get_solution')}}
															</p>
														</div>
														<div class="col-md-12">
															<ul class="list-group">
									                        	<template v-for="ticket in department_tickets">
																  	<li class="list-group-item">
																  		<a :href="route_ziggy('customer.view-public-ticket', {id : ticket.id})" target="_blank" rel="noopener">
																  			<i class="fas fa-ticket-alt" :title="__('messages.view_ticket')"></i>
																  			{{ticket.subject}}
																  		</a>
																  	</li>
																</template>
															</ul>
														</div>
													</div>
												</transition>
											</div>
										</div>
									</tab-content>
									<tab-content :title="__('messages.details')" 
										:before-change="validateStepDetails">
										<alert :content="error_message" 
											type="danger"></alert>
										<div class="form-group">
				                            <label for="subject">
				                            	{{__('messages.subject')}}*
				                           	</label>
				                            <input type="text" class="form-control" 
				                            	id="subject" :placeholder="__('messages.subject')" 
				                            	v-model="ticket_data.subject" required
				                            	@change="getTicketSuggestions"
				                            >
				                            <div v-if="!_.isEmpty($page.gcse_html)" v-html="$page.gcse_html"></div>
				                        </div>
				                        <div class="card text-white bg-success " v-if="!_.isEmpty(ticket_suggestions)">
				                        	<div class="card-header">
			                        			{{
		                        				__('messages.suggestions')
		                        				}}
				                        	</div>
				                        	<div class="card-body">
						                        <ul class="list-group">
						                        	<template v-for="suggestion in ticket_suggestions">
													  	<li class="list-group-item">
													  		<a :href="suggestion.url" target="_blank" rel="noopener">
													  			<i class="fas fa-ticket-alt" :title="__('messages.view_ticket')"
													  				v-show="suggestion.type == 'ticket'"></i>
													  			<i class="fas fa-book-open" :title="__('messages.view_doc')"
													  				v-show="suggestion.type == 'doc'"></i>
													  			{{suggestion.title}}
													  		</a>
													  	</li>
													</template>
												</ul>
											</div>
										</div>
				                        <div class="form-group">
					                        <label for="message">
					                        	{{__('messages.your_message')}}*
					                        </label>
					                        <textarea class="form-control" id="message" rows="5"></textarea>
					                    </div>
					                    <div class="form-group">
					                    	<label for="priority">
				                            	{{__('messages.priority')}}*
				                           	</label>
					                    	<select class="form-control" v-model="ticket_data.priority" id="priority">
					                    		<option value="">
					                    			{{__('messages.plz_select')}}
					                    		</option>
					                    		<option v-for="(priority, index) in priorities" :value="index">
					                    			{{priority}}
					                    		</option>
					                    	</select>
					                    </div>
										<div class="form-group" v-if="is_public_ticket_enabled">
											<div class="switch d-inline m-r-10">
												<input type="checkbox" id="is_public" v-model="ticket_data.is_public">
												<label for="is_public" class="cr">
												</label>
											</div>
											<label for="is_public" class="cr">
												{{__('messages.is_public_ticket')}}
											</label>

											<small class="form-text text-danger"><b>{{__('messages.public_ticket_help')}}</b></small>
										</div>
									</tab-content>

									<tab-content :title="__('messages.extra_information')">
										<alert :content="error_message" 
											type="danger"></alert>
										<div class="form-group">
					                        <label for="other_info">
					                        	{{__('messages.other_information')}}
					                        </label>
					                        <textarea class="form-control" id="other_info" rows="3" v-model="ticket_data.other_info" :placeholder="__('messages.other_information')"></textarea>
					                    </div>
										<!-- custom fields -->
										<template
											v-if="!_.isEmpty(custom_fields)">
											<template
												v-for="(custom_field, key) in custom_fields">
												<template
													v-if="isCustomFieldDisplayable(custom_field)">
													<template
														v-if="!_.includes(['textarea'], custom_field.type)">
														<div class="form-group">
															<label :for="custom_field.name">
																{{custom_field.label}}
																<span
																	v-if="custom_field.is_required">
																	*
																</span>
															</label>
															<input :type="custom_field.type" class="form-control" 
																:id="custom_field.name" 
																:placeholder="custom_field.label" 
																:required="custom_field.is_required"
																v-model="custom_field.value"
															>
														</div>
													</template>
													<template
														v-if="_.includes(['textarea'], custom_field.type)">
														<div class="form-group">
															<label :for="custom_field.name">
																{{custom_field.label}}
																<span
																	v-if="custom_field.is_required">
																	*
																</span>
															</label>
															<textarea class="form-control" 
																:id="custom_field.name" rows="3" 
																:placeholder="custom_field.label"
																:required="custom_field.is_required"
																v-model="custom_field.value">
															</textarea>
														</div>
													</template>
												</template>
											</template>
										</template>
										<!-- /custom fields -->
									</tab-content>
									<template slot="prev">
										<button type="button" class="btn btn-primary">
											{{__('messages.back')}}
										</button>
									</template>

									<template slot="next">
										<button type="button" class="btn btn-primary">
											{{__('messages.next')}}
										</button>
									</template>
									
									<template slot="finish" >
										<loading-button :loading="submitting" class="btn btn-primary" type="submit">
			                                {{__('messages.submit')}}
			                            </loading-button>
									</template>
								</form-wizard>
				    		</div>
				    	</div>
			    	</form>
		    	</div>
		    	<div class="col-md-12">
		    		<!-- License Modal -->
		    		<div class="modal fade" id="addLicense" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
						<form v-on:submit.prevent="validateLicense">
							<div class="modal-dialog modal-lg">
								<div class="modal-content">
									<div class="modal-header">
										<h5 class="modal-title" id="exampleModalLabel"
											v-html="__('messages.license_for_product',{
												product:product.name
											})">
										</h5>
										<button type="button" class="close" data-dismiss="modal" aria-label="Close">
											<span aria-hidden="true">&times;</span>
										</button>
									</div>
									<div class="modal-body">
										<alert :content="license_error_message" type="danger"></alert>
										<div class="card bg-light" v-if="user_licenses.length">
											<h5 class="card-header">
												{{__('messages.choose_license')}}
											</h5>
											<div class="card-body">
												<div class="row">
													<div class="col-md-6">
														<b>
															{{__('messages.license_key')}}
														</b>
													</div>
													<div class="col-md-6">
														<b>
															{{__('messages.support_expires_on')}}
														</b>
													</div>
												</div>
												<div class="row" v-for="user_license in user_licenses">
													<div class="col-md-6">
														<div class="form-check"
															v-show="user_license.product_license_key != 'INVALID_LICENSE'">
															<input class="form-check-input" type="radio" :id="'user_'+user_license.id" :value="user_license.id" name="license_key" v-model="choosen_license"
															@change="setProductLicense(user_license.id)"
															:disabled="isSupportExpired(user_license.support_expires_on)">
															<label class="form-check-label" :for="'user_'+user_license.id">
																{{user_license.product_license_key}}
															</label>
														</div>
														<div v-show="user_license.product_license_key == 'INVALID_LICENSE'">
															<div class="input-group">
																<input type="text" class="form-control" id="license_key" :placeholder="__('messages.license_key')" v-model="user_license.new_product_license_key">
																<div class="input-group-append">
																    <span class="input-group-text cursor-pointer text-success" @click="refreshLicenses(user_license)">
																    	{{__('messages.submit')}}
																    </span>
																</div>
															</div>
															<small class="form-text text-danger">
																{{__('messages.please_provide_license_key_for_security_check')}}
															</small>
														</div>
													</div>
													<div class="col-md-6">
														{{$commonFunction.formatDate(user_license.support_expires_on)}}
														<span v-if="isSupportExpired(user_license.support_expires_on)" class="badge badge-danger">
															{{__('messages.support_expired_plz_renew')}}
														</span>
														<span v-if="isSupportExpired(user_license.support_expires_on)"
															data-toggle="tooltip" :title="__('messages.refresh')"
															@click="refreshLicenses(user_license)">
															<i class="fas fa-sync-alt cursor-pointer ml-2 text-info"></i>
														</span>
													</div>
												</div>
											</div>
										</div>
										<input type="hidden" v-model="license.product_id" id="product_id">
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="license_key">
														{{__('messages.license_key')}}
													</label>
													<input type="text" class="form-control" id="license_key" :placeholder="__('messages.license_key')" v-model="license.license_key" required>
												</div>
											</div>
											<div class="col-md-6">
												<div class="form-group">
													<label for="source">
														{{__('messages.source')}}
													</label>
													<select class="form-control" id="source" required v-model="license.source_id">
														<option v-for="source in sources" :value="source.id"> {{source.name}} </option>
													</select>
												</div>
											</div>
										</div>
										<loading-button :loading="submitting" class="btn btn-primary btn-sm float-right" type="submit">
		                                    {{__('messages.add')}}
		                                </loading-button>
									</div>
									<div class="modal-footer">
										<button type="button" class="btn btn-secondary" data-dismiss="modal">
											{{__('messages.close')}}
										</button>
										<button type="button" class="btn btn-success" @click="checkIfLicenseSelected">
											{{__('messages.chosen_license_key')}}
										</button>
									</div>
								</div>
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
	import Leftnav from '@/Pages/Customer/Leftnav';
	import LoadingButton from '@/Shared/LoadingButton';
	import SupportTiming from '@/Shared/SupportTiming';
	export default {
		components: {
			Layout,
			Leftnav,
			LoadingButton,
			SupportTiming
		},
		props: {
    		products: Array,
    		user: Object,
    		instruction: String,
    		is_public_ticket_enabled: String,
    		default_ticket_type: String,
    		priorities: Object,
    		source_count_by_product: [Array, Object]
  		},
  		data: function(){
  			return {
  				ticket_data: {},
  				step: null,
  				error_message: null,
  				sources: [],
  				product: [],
  				license: [],
  				user_licenses:[],
  				choosen_license: null,
  				license_error_message: null,
  				submitting: false,
  				ticket_suggestions:[],
				hasSupportExpired: false,
				isSupportExpiredMsg:'',
				departments: [],
				department_info: '',
            	department_tickets: [],
				custom_fields:[],
				department_id: null
  			}
  		},
		beforeMount(){
            this.resetTicket();
        },
        created() {
        	$(function() {
		    	//if editor exist destory & re-initialize it
		    	if (!_.isNull(tinymce.get('message'))) {
  					tinymce.remove("textarea#message");
				}
				//initialize editor
				tinymce.init({
				    selector: 'textarea#message',
				});
		    });
        },
		watch : {
            '$page.errors': function (errors) {
				if(!_.isEmpty(this.$page.flash.error)) {
					this.hasSupportExpired = true;
					this.isSupportExpiredMsg = this.$page.flash.error;
				} else {
					tinymce.get("message").setContent('');
                    this.resetTicket();
				}
            }
        },
		methods:{
			resetTicket(){
                this.ticket_data = {
                	'product_id': null,
                	'subject': null,
                	'other_info': null,
                	'license_id': null,
                	'is_public': 0,
                	'priority': '',
                	'product_department_id': null
                };

				//get custom fields to render on screen
				if(!_.isEmpty(this.$page.custom_fields)) {
					for (const key in this.$page.custom_fields) {
						if (
							this.$page.custom_fields.hasOwnProperty(key) &&
							_.includes(['customer'], this.$page.custom_fields[key]['filled_by']) &&
							!_.isEmpty(this.$page.custom_fields[key]['label'])
						) {
							let field = this.$page.custom_fields[key];
							field['name'] = key;
							field['value'] = '';
							this.custom_fields.push(field);
						}
					}
				}
            },
			validateStepProduct(){
				if(_.isNull(this.ticket_data.product_id)){
					this.error_message = this.__('messages.input_error');
					return false;
				} else if (!_.isUndefined(this.source_count_by_product[this.ticket_data.product_id])
						&& _.isNull(this.ticket_data.license_id)
					) {
					this.error_message = this.__('messages.choose_product_license_key');
					$('#addLicense').modal('show');
					return false;
				} else if (!_.isEmpty(this.departments) && _.isEmpty(this.ticket_data.product_department_id)) {
					this.error_message = this.__('messages.department_required');
					return false;
				} else {
					if (this.is_public_ticket_enabled) {
						this.ticket_data.is_public = this.default_ticket_type == 'public'?1:0;
					}
					this.error_message = null;
					return true;
				}
			},
			validateStepDetails(){
				if(_.isNull(this.ticket_data.subject) || tinymce.get("message").getContent().length <= 0 || _.isEmpty(this.ticket_data.priority)){
					this.error_message = this.__('messages.input_error');
					return false;
				} else{
					this.error_message = null;
					return true;
				}
			},
			submitForm(){
				self = this;
				if(!_.isEmpty(self.custom_fields)) {

					self.error_message = null;

					for (const field of self.custom_fields) {
						if(document.querySelector(`#${field.name}`)) {
							let value = document.querySelector(`#${field.name}`).value.trim();
							//if value length is 0 then set value to empty string
							if(value.length == 0) {
								document.querySelector(`#${field.name}`).value = '';
							}

							//add required validation
							if(field.is_required && value.length == 0) {
								self.error_message = self.__('messages.input_field_required', {
									attribute: field.label
								});
								return false;
							}

							//validate email
							if(
								_.includes(['email'], field.type) && 
								(value.length > 0) && 
								!/^\w+([\+.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(value)
							) {
								self.error_message = self.__('validation.email', {
									attribute: field.label
								});
								return false;
							}
							
							//validate url
							if(
								_.includes(['url'], field.type) && 
								(value.length > 0) && 
								value.match(/(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g) == null
							) {
								self.error_message = self.__('messages.enter_valid_url', {
									attribute: field.label
								});
								return false;
							}
						}
					}
				}
				
				let ticket_data = _.pick(self.ticket_data, ['product_id', 'subject', 'other_info',
									'license_id', 'is_public', 'priority', 'product_department_id']);
				ticket_data.message = tinymce.get("message").getContent();
				
				//get custom fields value to store in db
				if(!_.isEmpty(self.custom_fields)) {
					for (const field of self.custom_fields) {
						ticket_data[field.name] = field.value;
					}
				}
				self.submitting = true;
				self.$inertia.post(self.route_ziggy('customer.tickets.store'),ticket_data)
                .then(function(){
                	self.submitting = false;
                });
            },
            openLicenseModel(product_id) {
            	const self = this;
            	self.error_message = null;
            	self.license = [];
            	self.license.license_key = null;
				self.license.source_id = null;
            	self.user_licenses = [];
        		self.submitting = false;
        		self.ticket_data.license_id = null;
        		self.choosen_license = null;
        		self.getProductDepartments(product_id);
            	if (!_.isUndefined(self.source_count_by_product[product_id])) {
	    			axios.get(this.route_ziggy('customer.licenses.create').url(), {
					    params: {
					      product_id: product_id
					    }
					})
					.then(function (response) {
						if (response.data.success) {
							self.product = response.data.product;
							self.sources = response.data.product.sources;
							self.license.product_id = self.product.id;
							if (!_.isEmpty(response.data.licenses)) {
								self.user_licenses = response.data.licenses;
							}

							self.license.source_id = self.sources[0].id;
						}
					    $('#addLicense').modal('show');
					})
					.catch(function (error) {
					    console.log(error);
					})
					.then(function () {
					    // always executed
					});
				}
            },
            validateLicense() {
            	const self = this;
            	let data = _.pick(self.license, ['license_key', 'product_id', 'source_id']);
            	data.user_id = self.user.id;
            	self.submitting = true;
            	axios.post(self.route_ziggy('customer.licenses.store').url(), data)
				.then(function (response) {
					if(response.data.success == true){
						self.user_licenses.push(response.data.license);
					} else {
						alert(response.data.msg);
					}
				})
				.catch(function (error) {
				    console.log(error);
				})
				.then(function () {
				    self.submitting = false;
				    self.license.license_key = null;
					self.license.source_id = null;
				});
            },
            setProductLicense(license_id) {
            	const self = this;
            	self.ticket_data.license_id = license_id;
            },
            checkIfLicenseSelected() {
            	const self = this;
            	if(_.isNull(self.choosen_license)){
					this.license_error_message = this.__('messages.choose_product_license_key');
					return false;
				} else{
					this.license_error_message = null;
					self.ticket_data.license_id = self.choosen_license;
					$('#addLicense').modal('hide');
				}
            },
            isSupportExpired(support_expiry_date) {
            	var is_expired = moment().isAfter(support_expiry_date);
            	return is_expired;
            },
            getTicketSuggestions() {
            	const self = this;
            	
            	if($('#gs_tti50').length > 0) {
			        $('#gs_tti50').find('input').val($('#subject').val());
					$('.gsc-search-button').find('button').trigger("click");
		    	}

            	if (self.is_public_ticket_enabled) {
					axios.get(this.route_ziggy('customer.tickets-suggestion').url(), {
					    params: {
					      search_params: this.ticket_data.subject
					    }
					})
					.then(function (response) {
						if (response.data.success) {
							self.ticket_suggestions = response.data.suggestions;
						}
					})
					.catch(function (error) {
					    console.log(error);
					})
					.then(function () {
					    // always executed
					});
				}
            },
            refreshLicenses(license) {
            	const self = this;
            	var license_key = (license.product_license_key != 'INVALID_LICENSE') ? license.product_license_key : license.new_product_license_key;

            	if (_.isUndefined(license_key) || license_key.length == 0) {
            		self.license_error_message = self.__('messages.please_enter_license_key');
					return false;
            	} else {
            		self.license_error_message = null;
            	}

            	axios.post(self.route_ziggy('customer.update-license-expiry').url(), {
        			product_id: license.product_id,
        			source_id: license.source_id,
        			license_key: license_key,
        			license_id: license.id,
            	})
        		.then(function (response) {
        			if (response.data.success) {
						license.support_expires_on = response.data.license.support_expires_on;
						license.product_license_key = response.data.license.product_license_key;
						self.license_error_message = null;
					} else {
						self.license_error_message = response.data.msg;
					}
        		}).catch(function (error) {
        			console.error(error);
        		}).then(function () {
        			//always executed
        		})
            },
            getProductDepartments(product_id){
            	const self = this;
            	self.departments = [];
            	self.ticket_data.product_department_id = '';
            	self.department_info = '';
            	self.department_tickets = [];
				self.department_id = null;
            	axios.get(self.route_ziggy('customer.product.departments', {'product_id':product_id}).url())
				.then(function (response) {
					if (response.data.success) {
						self.departments = response.data.departments;
					}
				})
				.catch(function (error) {
				    console.log(error);
				})
				.then(function () {
				    // always executed
				});
            },
            getDepartmentInfo(){
            	const self = this;
            	self.department_info = '';
            	self.department_tickets = [];
				self.department_id = null;
            	axios.get(self.route_ziggy('customer.department.info', {'department_id':self.ticket_data.product_department_id}).url())
				.then(function (response) {
					if (response.data.success) {
						self.department_info = response.data.information;
            			self.department_tickets = response.data.tickets;
						self.department_id = response.data.department_id
					}
				})
				.catch(function (error) {
				    console.log(error);
				})
				.then(function () {
				    // always executed
				});
            },
			isCustomFieldDisplayable(field) {
				const self = this;
				let product_id = Number(self.ticket_data.product_id);
				let department_id = Number(self.department_id);
				if(
					field.label && 
					(
						(
							!_.isEmpty(field.products) &&
							_.includes(field.products, product_id)
						) ||
						(
							!_.isEmpty(field.departments) &&
							_.includes(field.departments, department_id)
						)
					)
				) {
					return true;
				}
				return false;
			}
		}
	}
</script>