<template>
 	<layout :title="__('messages.edit_user')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
			        <div class="card">
			        	<div class="card-header">
			        		<h5>{{__('messages.edit_user')}}</h5>
			        	</div>
			        	<form method="PUT" @submit.prevent="submitForm">
				            <div class="card-body">
				            	<div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name">
                                                {{__('messages.name')}}
                                            </label>
                                            <input type="text" :class="['form-control', $page.errors.name ? 'is-invalid': '']" id="name" :placeholder="__('messages.name')" v-model="new_user.name" required>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.name">
                                                <strong>
                                                	{{ $page.errors.name[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">
                                                {{__('messages.email')}}
                                            </label>
                                            <input type="email" :class="['form-control', $page.errors.email ? 'is-invalid': '']" id="email" :placeholder="__('messages.email')" v-model="new_user.email" required>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.email">
                                                <strong>
                                                	{{ $page.errors.email[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="password">
                                                {{__('messages.password')}}
                                            </label>
                                            <input type="password" :class="['form-control', $page.errors.password ? 'is-invalid': '']" id="password" :placeholder="__('messages.password')" v-model="new_user.password">
                                            <small class="form-text text-muted">
                                                {{__('messages.password_help_text')}}
                                            </small>
                                            <span class="invalid-feedback" role="alert" v-if="$page.errors.password">
                                                <strong>
                                                	{{ $page.errors.password[0] }}
                                                </strong>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="role">
                                               {{__('messages.role')}}
                                            </label>
                                            <select :class="['form-control', $page.errors.role ? 'is-invalid': '']" id="role" v-model="new_user.role" required>
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
                                    <div class="col-md-12">
                                        <div class="form-group text-left">
                                            <div class="checkbox checkbox-fill d-inline">
                                                <input type="checkbox" id="checkbox-fill-a1" v-model="new_user.notify_user">
                                                <label for="checkbox-fill-a1" class="cr"> {{ __('messages.send_notification') }}</label>
                                                <tooltip :title="__('messages.send_notification_tooltip')">
                                                    <i class="fas fa-info-circle text-info cursor-pointer"></i>
                                                </tooltip>
                                            </div>
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
    import Tooltip from '@/Shared/Tooltip';
	export default {
		components: {
			Layout,
			Leftnav,
            LoadingButton,
            Tooltip
		},
		props: ['roles', 'user'],
  		data: function () {
  			return {
  				new_user: {
                    name: '',
                    email: '',
                    password: '',
                    role: '',
                    notify_user: false
                },
                submitting: false
  			}
  		},
        created() {
            this.new_user = this.user;
        },
  		methods:{
			submitForm(){
                self = this;
                self.submitting = true;
                self.$inertia.put(self.route_ziggy('user-management.update', [self.user.id]), self.new_user)
                .then(function(response){
                    self.submitting = false;
                    console.log(response);
                });
            },
		}
	}
</script>