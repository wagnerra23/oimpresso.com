<template>
 	<layout :title="__('messages.tickets')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-header-title">
        	<div class="alert alert-success" role="alert" v-if="announcements.length">
                <h3 class="text-success">
                	<template v-for="(announcement, index) in announcements">
                		<span v-html="announcement.body">
	                	</span>
	                	<hr v-show="index+1 != announcements.length">
                	</template>
                </h3>
            </div>
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
			        <div class="card code-table">
			        	<div class="card-header">
			        		<h5>
			        			{{__('messages.my_tickets')}}
			        		</h5>
			        		<a :href="route_ziggy('customer.tickets.create')" class="btn float-right btn-sm btn-info text-white">
		        				<i class="fas fa-plus"></i>
		        				{{__('messages.new_ticket')}}
		        			</a>
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
				                        <tr v-for="ticket in tickets.data">
				                        	<td>
				                            	<inertia-link :href="route_ziggy('customer.view-ticket', [ticket.id])" class="btn btn-icon btn-rounded btn-info">
				                            		<i class="far fa-eye text-white"></i>
				                            	</inertia-link>
				                            </td>
				                        	<td>
				                        		<div>
													<div class="m--font-bolder d-flex align-items-center">
														<div class="no-wrap d-inline-block max-width-250" :title="ticket.subject">
															{{ticket.subject}}
														</div>
														<span class="text-muted ml-2">
															{{ticket.ticket_ref}}
														</span>
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
														<span class="ml-2" :title="__('messages.priority')">
															<span class="badge"
																:class="badgeForTicketPriority(ticket.priority)">
																{{
																	__('messages.'+ticket.priority)
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
			                <Pagination :pagination="tickets" :url="route_ziggy('customer.tickets.index')"></Pagination>
			            </div>
			        </div>
			    </div>
		    </div>
		</div>
  	</layout>
</template>

<script>
	import Layout from '@/Shared/Layout';
	import Leftnav from '@/Pages/Customer/Leftnav';
	import Pagination from '@/Shared/Pagination';
	import SupportTiming from '@/Shared/SupportTiming';
	export default {
		components: {
			Layout,
			Leftnav,
			Pagination,
			SupportTiming
		},
		props: {
    		tickets: Object,
  		},
  		data() {
  			return {
  				announcements:[]
  			}
  		},
		mounted: function () {
		  this.$nextTick(function () {
		    console.log("hhere");
		  })
		},
		created() {
			this.getAnnouncements();
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
            getAnnouncements() {
            	const self = this;
            	axios.get(this.route_ziggy('announcements-view').url())
				.then(function (response) {
					if (response.data.success) {
						self.announcements = response.data.announcements;
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
</script>