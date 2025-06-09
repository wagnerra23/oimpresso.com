<template>
	<layout :title="__('messages.sources')">
        <template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
		<div class="page-wrapper">
			<div class="row">
				<div class="col-md-12">
                    <div class="page-header-title">
                        <h3 class="m-b-10">
                            {{__('messages.sources')}}
                        </h3>
                    </div>
					<ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">

                        <li class="nav-item">
                            <a class="nav-link show active" data-toggle="pill" href="#pills-envato" role="tab" aria-controls="pills-woolicensing" aria-selected="true">
                                {{__('messages.envato')}}
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link show" data-toggle="pill" href="#pills-woolicensing" role="tab" aria-controls="pills-woolicensing" aria-selected="false">
                                {{__('messages.Woocommerce_licensing')}}
                            </a>
                        </li>

                        <li class="nav-item" v-if="sources['woocommerce']">
                            <a class="nav-link show" data-toggle="pill" href="#pills-woo" role="tab" aria-controls="pills-woo" aria-selected="false">
                                {{__('messages.woocommerce')}}
                            </a>
                        </li>
                        
                    </ul>

                    <div class="tab-content" id="pills-tabContent">
                        <div class="tab-pane fade" id="pills-woo" role="tabpanel" aria-labelledby="woocommerce" v-if="sources['woocommerce']">
                        	<form v-on:submit.prevent="submitForm">
                                <div class="form-group">
                                    <label>
                                        {{__('messages.name')}}*
                                    </label>
                                    <input type="text" class="form-control" id="name" required="required" 
                                    v-model="sources['woocommerce']['name']"
                                    :placeholder="__('messages.name')" 
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="woo_url">
                                        {{__('messages.website_url')}}*
                                    </label>
                                    <input type="url" class="form-control" id="woo_url" required="required" 
                                    v-model="sources['woocommerce']['web_url']"
                                    :placeholder="__('messages.website_url')" 
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="woo_consumer_key">
                                        {{__('messages.consumer_key')}}
                                    </label>
                                    <input type="text" class="form-control" id="woo_consumer_key" 
                                    v-model="sources['woocommerce']['woo_consumer_key']"
                                    :placeholder="__('messages.consumer_key')">
                                </div>

                                <div class="form-group">
                                    <label for="woo_consumer_secret">
                                        {{__('messages.consumer_secret')}}
                                    </label>
                                    <input type="text" class="form-control" 
                                    	id="woo_consumer_secret"
                                    	v-model="sources['woocommerce']['woo_consumer_secret']"
                                        :placeholder="__('messages.consumer_secret')" 
                                    >
                                </div>

                                <div class="form-group form-check">
                                    <input type="checkbox" class="form-check-input" id="enable_woo" 
                                    v-model="sources['woocommerce']['is_enabled']">
                                    <label class="form-check-label" for="exampleCheck1">
                                        {{__('messages.is_enable')}}
                                    </label>
                                </div>
                                <button type="submit" v-on:click="submit_type = 'woocommerce'" class="btn btn-primary" :disabled="submitting">
                                    <spinner :spin="submitting"></spinner>
                                    {{__('messages.save')}}
                                </button>
                    		</form>
                        </div>

                        <div class="tab-pane fade" id="pills-woolicensing" role="tabpanel" aria-labelledby="woolicensing">
                            <p class="text-muted">
                                Using <a href="https://woosoftwarelicense.com/" target="_blank">https://woosoftwarelicense.com/</a> plugin
                            </p>
                            <form v-on:submit.prevent="submitForm">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>
                                                {{__('messages.name')}}*
                                            </label>
                                            <input type="text" class="form-control" id="name" required="required" 
                                            v-model="sources['woolicensing']['name']"
                                            :placeholder="__('messages.name')" 
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="woo_url">
                                                {{__('messages.website_url')}}*
                                            </label>
                                            <input type="url" class="form-control" id="woo_url" required="required" 
                                            v-model="sources['woolicensing']['web_url']"
                                            :placeholder="__('messages.website_url')" 
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="checkbox checkbox-fill d-inline">
                                            <input type="checkbox" class="form-check-input" id="enable_woo" 
                                            v-model="sources['woolicensing']['is_enabled']">
                                            <label class="cr" for="enable_woo">
                                                {{__('messages.is_enable')}}
                                            </label>
                                        </div>
                                    </div>
                            </div>
                            <button type="submit" v-on:click="submit_type = 'woolicensing'" class="btn btn-primary mt-2" :disabled="submitting">
                                <spinner :spin="submitting"></spinner>
                                {{__('messages.save')}}
                            </button>
                            </form>
                        </div>


                        <div class="tab-pane fade show active" id="pills-envato" role="tabpanel" aria-labelledby="envato">
                            <form v-on:submit.prevent="submitForm">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>
                                                {{__('messages.name')}}*
                                            </label>
                                            <input type="text" class="form-control" id="name" required="required" 
                                            v-model="sources['envato']['name']"
                                            :placeholder="__('messages.name')" 
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>
                                                {{__('messages.envato_token')}}*
                                            </label>
                                            <input type="text" class="form-control" id="name" required="required" 
                                            v-model="sources['envato']['envato_token']"
                                            :placeholder="__('messages.envato_token')" 
                                            >
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="checkbox checkbox-fill d-inline">
                                            <input type="checkbox" class="form-check-input" id="enable_envato" 
                                            v-model="sources['envato']['is_enabled']">
                                            <label class="cr" for="enable_envato">
                                                {{__('messages.is_enable')}}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" v-on:click="submit_type = 'envato'" class="btn btn-primary mt-2" :disabled="submitting">
                                    <spinner :spin="submitting"></spinner>
                                    {{__('messages.save')}}
                                </button>
                            </form>
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
    import Spinner from '@/Shared/Spinner';
	export default {
		components: {
			Layout,
            Leftnav,
            Spinner
		},
		props: {
    		sources: Object,
    		source_types: Object
  		},
  		data: function () {
		    return {
                submit_type: null,
                submitting: false
		    }
  		},
		mounted: function () {
            this.$nextTick(function () {
                console.log("hhere");
            })
		},
		methods:{
			submitForm(){
                this.submitting = true;
                this.$inertia.put(
                        this.route_ziggy('sources.update', this.sources[this.submit_type].id), 
                        this.sources[this.submit_type]
                    )
                .then(() => this.submitting = false)
            }
		}
	}
</script>