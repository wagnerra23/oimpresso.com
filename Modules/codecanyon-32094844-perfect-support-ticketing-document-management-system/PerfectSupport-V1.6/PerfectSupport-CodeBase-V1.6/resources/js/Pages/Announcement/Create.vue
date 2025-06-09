<template>
	<layout :title="__('messages.create_announcement')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">					
                    <div class="card">
                        <div class="card-header">
			        		<h5>
                                {{__('messages.create_announcement')}}
                            </h5>
			        	</div>
			        	<form method="post" @submit.prevent="submitAnnouncementForm()">
			        		<div class="card-body">
			        			<div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="role">
                                                {{__('messages.role')}}*
                                            </label>
                                            <select :class="['form-control', $page.errors.role ? 'is-invalid': '']" id="role" v-model="announcement.role" required>
                                                <option v-for="(name, key) in roles" :value="key" v-text="name">
                                                </option>
                                            </select>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.role">
                                                <strong>
                                                    {{ $page.errors.role[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
			        				<div class="col-md-3">
			        					<div class="form-group">
                                            <label for="product_id">
                                                {{__('messages.product')}}
                                            </label>
                                            <select class="form-control" id="product_id" v-model="announcement.product_id">
                                                <option v-for="(product, product_id) in products" :value="product_id" v-text="product">
                                                </option>
                                            </select>
                                        </div>
			        				</div>
			        				<div class="col-md-3">
										<div class="form-group">
											<label for="start_datetime">
												{{__('messages.start_datetime')}}*
											</label>
											<input type="text" id="start_datetime" :class="['form-control', $page.errors.start_datetime ? 'is-invalid': '']" name="start_datetime" readonly required>
											<span class="invalid-feedback" role="alert" v-if="$page.errors.start_datetime">
                                                <strong>
                                                	{{ $page.errors.start_datetime[0] }}
                                                </strong>
                                            </span>
										</div>
									</div>
									<div class="col-md-3">
										<div class="form-group">
											<label for="end_datetime">
												{{__('messages.end_datetime')}}*
											</label>
											<input type="text" id="end_datetime" :class="['form-control', $page.errors.end_datetime ? 'is-invalid': '']" name="end_datetime" readonly required>
											<span class="invalid-feedback" role="alert" v-if="$page.errors.end_datetime">
                                                <strong>
                                                	{{ $page.errors.end_datetime[0] }}
                                                </strong>
                                            </span>
										</div>
									</div>
			        			</div>
			        			<div class="row">
			        				<div class="col-md-12">
			        					<div class="form-group">
			        						<label for="body">
                                                {{__('messages.announcement')}}*
                                            </label>
                                            <textarea :class="['form-control', $page.errors.body ? 'is-invalid': '']" id="body" :placeholder="__('messages.announcement')"></textarea>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.body">
                                                <strong>
                                                	{{ $page.errors.body[0] }}
                                                </strong>
                                            </span>
			        					</div>
			        				</div>
			        			</div>
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
		props:['products', 'roles'],
		data: function () {
  			return {
  				announcement: {
                    body: '',
                    role:'customer',
                    product_id: '',
                    start_datetime: null,
                    end_datetime: null
                },
                submitting: false
  			}
  		},
  		mounted() {
  			const self = this;
  			$(function () {
  				$('#start_datetime').daterangepicker({
			    	singleDatePicker: true,
    				showDropdowns: true,
    				timePicker: true,
                    locale: {
                        cancelLabel: self.__('messages.clear'),
                        format: 'YYYY-MM-DD hh:mm A'
                    }
			    }).on('apply.daterangepicker', function(ev, picker) {
			    	self.announcement.start_datetime = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('hide.daterangepicker', function(ev, picker) {
					self.announcement.start_datetime = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                    self.announcement.start_datetime = null;
                });
				$('#end_datetime').daterangepicker({
			    	singleDatePicker: true,
    				showDropdowns: true,
    				timePicker: true,
                    locale: {
                        cancelLabel: self.__('messages.clear'),
                        format: 'YYYY-MM-DD hh:mm A'
                    }
			    }).on('apply.daterangepicker', function(ev, picker) {
			    	self.announcement.end_datetime = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('hide.daterangepicker', function(ev, picker) {
					self.announcement.end_datetime = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				}).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                    self.announcement.end_datetime = null;
                });

                $("#start_datetime, #end_datetime").val('');
                //if editor exist destory & re-initialize it
                if (!_.isNull(tinymce.get('body'))) {
                    tinymce.remove("textarea#body");
                }
                //initialize editor
                tinymce.init({
                    selector: 'textarea#body',
                    auto_focus: 'body',
                    height: 350,
                    theme: 'silver',
                    plugins: [
                        'paste link autolink hr anchor pagebreak'
                    ],
                    toolbar: 'undo redo | bold italic | link',
                    menubar: ''
                });
  			});
  		},
		methods:{
			submitAnnouncementForm(){
                self = this;
                self.submitting = true;
                self.announcement.body = tinymce.get("body").getContent();
                self.$inertia.post(self.route_ziggy('announcements.store'), self.announcement)
                .then(function(response){
                    self.submitting = false;
                    console.log(response);
                });
            },
		}
	}
</script>