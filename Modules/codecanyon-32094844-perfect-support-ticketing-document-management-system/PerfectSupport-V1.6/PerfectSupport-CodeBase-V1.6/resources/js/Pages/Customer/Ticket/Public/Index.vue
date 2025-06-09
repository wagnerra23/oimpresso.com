<template>
 	<layout :title="__('messages.public_tickets')">
    	<template v-slot:leftnav>
            <CustomerLeftnav v-if="_.includes(['customer'], $page.auth.user.role)">
            </CustomerLeftnav>
            <AdminLeftnav v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
            </AdminLeftnav>
        </template>
        <div class="page-header-title">
		
			<div class="row">
				<div class="col-md-4">
					<h3 class="m-b-10">
						{{__('messages.tickets')}}
					</h3>
				</div>
				<div class="col-md-8">
					<support-timing :classes="'btn-sm btn-danger float-right'"></support-timing>
				</div>
			</div>
		</div>
        <div class="page-wrapper">
        	<div class="row">
        		<div class="col-md-12">
	        		<search-filter v-model="form.search" :form="form">
	        		</search-filter>
	        	</div>
        	</div>
			<div class="row">
				<div class="col-md-12">
			        <div class="card code-table">
			        	<div class="card-header">
			        		<h5>
			        			{{__('messages.public_tickets')}}
			        		</h5>
			        	</div>
			            <div class="card-body">
			                <div class="table-responsive mb-4">
			                    <table class="table table-striped">
			                    	<thead>
				                        <tr>
				                        	<th>
				                            	{{__('messages.action')}}
				                            </th>
				                        	<th>
				                        		{{__('messages.subject')}}
				                        	</th>
				                        	<th>
				                        		{{__('messages.product')}}
				                        	</th>
				                        	<th>
				                        		{{__('messages.support_agents')}}
				                        	</th>
				                        	<th>
				                        		{{__('messages.last_updated_at')}}
				                        	</th>
				                        	<th>
				                        		{{__('messages.added_at')}}
				                        	</th>
				                        </tr>
				                    </thead>
				                    <tbody>
				                        <tr v-for="ticket in tickets.data" class="cursor-pointer" @click="viewTicket(ticket.id)">
				                        	<td>
				                        		<a class="btn btn-icon btn-rounded btn-info" @click="viewTicket(ticket.id)">
				                            		<i class="far fa-eye text-white"></i>
				                            	</a>
				                            </td>
				                        	<td>
				                        		<div>
													<div class="m--font-bolder d-flex align-items-center">
														<div class="no-wrap d-inline-block max-width-250" :title="ticket.subject">
															{{ticket.subject}}
														</div>
													</div>
													<div class="d-flex align-items-baseline">
														<span :title="__('messages.status')">
															<span class="badge no-wrap d-block"
																:class="badgeForTicketStatus(ticket.status)">
																{{
																	__('messages.'+ticket.status)
																}}
															</span>
														</span>
														<span class="ml-2"
															v-if="!_.isEmpty(ticket.product_department) && !_.isEmpty(ticket.product_department.department)"
															:title="__('messages.department')">
															<span class="badge badge-secondary">
																<i class="far fa-building"></i>
																{{ticket.product_department.department.name}}
															</span>
														</span>
													</div>
												</div>
				                        	</td>
	                                        <td>
				                            	<span v-if="ticket.product" v-text="ticket.product.name">
				                            	</span>
				                            </td>
				                            <td>
	                                            {{listSupportAgents(ticket.support_agents)}}
	                                        </td>
				                        	<td>
				                        		{{$commonFunction.timeFromNow(ticket.updated_at)}}
				                        	</td>
				                        	<td>
				                        		{{$commonFunction.timeFromNow(ticket.created_at)}}
				                        	</td>
				                        </tr>
				                        <tr v-if="tickets.data.length === 0">
										    <td colspan="8">
										      	<div class="alert alert-info" role="alert">
													<h4 class="text-muted">
														{{__('messages.no_data_found')}}
													</h4>
												</div>
										    </td>
										</tr>
				                    </tbody>
			                    </table>
			                </div>
			                <Pagination :pagination="tickets" :url="route_ziggy('customer.public-tickets')">
			                </Pagination>
			            </div>
			        </div>
			    </div>
		    </div>
		</div>
  	</layout>
</template>

<script>
	import Layout from '@/Shared/Layout';
	import CustomerLeftnav from '@/Pages/Customer/Leftnav';
	import AdminLeftnav from '@/Pages/Elements/Leftnav';
	import Pagination from '@/Shared/Pagination';
	import SearchFilter from '@/Shared/SearchFilter';
	import SupportTiming from '@/Shared/SupportTiming';
	export default {
		components: {
			Layout,
			AdminLeftnav,
			CustomerLeftnav,
			Pagination,
			SearchFilter,
			SupportTiming
		},
		props: {
    		tickets: Object,
    		filters: Object,
  		},
  		data: function () {
		    return {
                form: {
			        search: this.filters.search,
			    }
		    }
  		},
  		watch: {
		    form: {
				handler: _.throttle(function() {
					let query = _.pickBy(this.form)
					sessionStorage.setItem('pTticketFilterParams', JSON.stringify(query));
					this.$inertia.replace(this.route_ziggy('customer.public-tickets', query))
				}, 200),
				deep: true,
		    },
		},
		mounted: function () {
		  	this.$nextTick(function () {
		    	console.log("hhere");
		  	});

			//get & set scrollYPosition
			if (!_.isNull(sessionStorage.getItem('PtScrollYPosition'))) {
				var ScrollYPosition = JSON.parse(sessionStorage.getItem('PtScrollYPosition'));
				document.documentElement.scrollTop = document.body.scrollTop = ScrollYPosition;
			}
		},
		methods:{
			listSupportAgents(supportAgents) {
                var temp = [];
                _(supportAgents).forEach(
                    function(supportAgent) {
                        temp.push(supportAgent.name);
                    }
                )

                return temp.join(', ');
            },
            viewTicket(id) {
            	//get & store ScrollYPosition
            	var ScrollYPosition = window.pageYOffset || document.documentElement.scrollTop;
            	sessionStorage.setItem('PtScrollYPosition', JSON.stringify(ScrollYPosition));
            	this.$inertia.visit(this.route_ziggy('customer.view-public-ticket', [id]))
                .then(function(response){
                    console.log(response);
                });
            }
		}
	}
</script>