<template>
 	<layout :title="__('messages.canned_response')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-header-title">
			<h3 class="m-b-10">
				{{__('messages.canned_response')}}
			</h3>
		</div>
        <div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
			        <div class="card">
			        	<div class="card-header">
			        		<div class="row">
				            	<blockquote class="blockquote">
									<footer class="blockquote-footer">
										<cite>
											{{__('messages.available_tags')}}
										</cite>
									</footer>
									<p class="mb-0">
										<code>{{tags.toString()}}</code>
									</p>
									<small class="form-text" v-html="__('messages.canned_response_tags_help_text')">
									</small>
								</blockquote>
							</div>
			        	</div>
			        	<form v-on:submit.prevent="submitForm">
				            <div class="card-body">
								<div class="row">
									<div class="col-md-4">
										<label for="name">
											{{__('messages.name')}}*
										</label>
									</div>
									<div class="col-md-5">
										<label for="message">
											{{__('messages.message')}}*
										</label>
									</div>
									<div class="col-md-1">
										<label>
											{{__('messages.only_for_admin')}}
										</label> 
									</div>
									<div class="col-md-2">
										<label>{{__('messages.action')}}</label>
										<i class="fas fa-plus-circle cursor-pointer text-info fa-lg ml-2" @click="addObjectToResponse"></i>
									</div>
								</div>
				            	<template v-for="(response, index) in responses">
				            		<input type="hidden" id="response_id" v-model="response.id" v-if="response.id">
					            	<div class="row">
						                <div class="col-md-4">
						                	<div class="form-group">
						                		<input class="form-control form-control-lg" type="text" :placeholder="__('messages.name')" id="name" v-model="response.name" required>
						                	</div>
										</div>
										<div class="col-md-5">
						                	<div class="form-group">
						                		<textarea class="form-control" id="message" rows="3" :placeholder="__('messages.message')" v-model="response.message" required></textarea>
						                	</div>
										</div>
										<div class="col-md-1">
											<div class="form-group">
												<input type="checkbox" class="form-check-input ml-4" value="1" v-model="response.only_for_admin">
											</div>
										</div>
										<div class="col-md-2">
											<div class="form-group">
												<i class="fas fa-trash-alt fa-lg text-danger cursor-pointer" @click="removeObjectFromResponse(index)"></i>
											</div>
										</div>
									</div>
								</template>
								<loading-button :loading="submitting" class="btn btn-success float-right" type="submit">
                                    {{__('messages.save')}}
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
		props: ['canned_responses', 'tags'],
  		data: function () {
  			return {
  				responses:[
  					{
  						'name' : '',
  						'message' : '',
  						'only_for_admin': 0
  					}
  				],
  				submitting: false
  			}
  		},
  		created() {
  			const self = this;
  			if (self.canned_responses.length) {
  				self.responses = self.canned_responses;
  			}
  		},
  		methods:{
  			addObjectToResponse() {
  				const self = this;
  				self.responses.unshift({
					'name' : '',
					'message' : '',
					'only_for_admin': 0
				});
  			},
  			removeObjectFromResponse(index) {
  				const self = this;
  				if (index != 0) {
  					self.responses.splice(index, 1);
  				}
  			},
			submitForm(){
				const self = this;
				self.submitting = true;
				self.$inertia.post(this.route_ziggy('canned-responses.store'), {'response': self.responses})
                .then(function(response){
                	self.submitting = false;
                    console.log(response);
                });
            }
		}
	}
</script>