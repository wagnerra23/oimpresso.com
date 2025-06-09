<template>
	<div class="accordion" id="searchAccordion">
	    <div class="card">
	        <div class="card-header" id="filterHeading">
	            <h5 class="mb-0">
	            	<a href="#!" data-toggle="collapse" data-target="#filter" aria-expanded="false" aria-controls="filter" class="collapsed">
	            		{{__('messages.search_filter')}}
	            	</a>
	           	</h5>
	        </div>
	        <div id="filter" class="card-body collapse" aria-labelledby="filterHeading" data-parent="#searchAccordion">
	            <div class="row">
	            	<div class="col-md-4">
	            		<label for="search">
            				{{__('messages.search')}}
            			</label>
	            		<div class="input-group mb-3">
	            			<input type="text" id="search" class="form-control" name="search" :placeholder="__('messages.search')" :value="value" @input="$emit('input', $event.target.value)" aria-describedby="adv_search">
	            			<div class="input-group-append" v-if="!_.isUndefined(form.search_fields)">
                                <span class="input-group-text cursor-pointer" id="adv_search" data-toggle="popover" data-placement="bottom" data-container="body" data-content="" data-html="true">
                                	<i class="fas fa-search-plus"></i>
                                </span>
                            </div>
	            		</div>
	            	</div>
	            	<slot />
	            </div>
	        </div>
	    </div>
	    <!-- popover search config -->
		<div id="popover-form" class="hide">
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<div class="checkbox checkbox-fill d-inline">
							<input type="checkbox" id="body" v-model="config.body">
							<label for="body" class="cr">
								{{__('messages.body')}}
							</label>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<div class="checkbox checkbox-fill d-inline">
							<input type="checkbox" id="customer" v-model="config.customer">
							<label for="customer" class="cr">
								{{__('messages.customer')}}
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<div class="checkbox checkbox-fill d-inline">
							<input type="checkbox" id="subject" v-model="config.subject">
							<label for="subject" class="cr">
								{{__('messages.subject')}}
							</label>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<div class="checkbox checkbox-fill d-inline">
							<input type="checkbox" id="comments" v-model="config.comments">
							<label for="comments" class="cr">
								{{__('messages.comments')}}
							</label>
						</div>
					</div>
				</div>
				<div class="col-md-12">
					<div class="form-group">
						<div class="checkbox checkbox-fill d-inline">
							<input type="checkbox" id="ref_num" v-model="config.ref_num">
							<label for="ref_num" class="cr">
								{{__('messages.ticket_ref')}}
							</label>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12 text-center">
					<button type="button" class="btn btn-sm btn-outline-secondary" @click="searchConfigured">
						Ok
					</button>
				</div>
			</div>
		</div>
		<!-- /popover search config -->
	</div>
</template>
<script>
	export default {
		props: {
    		value: String,
    		form: Object
  		},
		data() {
			return{
				config: {
					body: !_.isUndefined(this.form.search_fields) ? this.form.search_fields.body : true,
					comments: !_.isUndefined(this.form.search_fields) ? this.form.search_fields.comments : true,
					customer: !_.isUndefined(this.form.search_fields) ? this.form.search_fields.customer : true,
					ref_num: !_.isUndefined(this.form.search_fields) ? this.form.search_fields.ref_num : true,
					subject: !_.isUndefined(this.form.search_fields) ? this.form.search_fields.subject : true
				}
			}
		},
		methods: {
			searchConfigured() {
				$('#adv_search').popover('hide');
				let query = _.pickBy(this.form);
				query.search_fields = this.config;
				sessionStorage.setItem('ticketFilterParams', JSON.stringify(query));
				this.$inertia.replace(this.route_ziggy('tickets.index', query));
			}
		},
		mounted() {
			$("#adv_search").popover({
				title: '<h5>'+this.__('messages.search_ticket_by')+'</h5>',
				container: 'body',
				placement: 'bottom',
				html: true,
				content: $('#popover-form'),
			}).on('show.bs.popover', function() {
				$('#popover-form').addClass('show')
			}).on('hide.bs.popover', function() {
				$('#popover-form').addClass('hide')
			});
		}
	}
</script>
<style scoped>
	.popover, .popover-body {
		width: 1000px;
		max-width: 100%;
	}
	.hide{
		display:none !important;
	}
	.show{
		display:block !important;
	}
</style>