<template>
 	<layout :title="__('messages.view_ticket')">
    	<template v-slot:leftnav>
            <CustomerLeftnav v-if="_.includes(['customer'], user.role)">
            </CustomerLeftnav>
            <AdminLeftnav v-if="_.includes(['admin', 'support_agent'], user.role)">
            </AdminLeftnav>
        </template>
        <div class="row">
	        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
	        	<support-timing :classes="'btn-sm btn-danger float-right'"></support-timing>
	    		<inertia-link :href="getRedirectBackUrl($page.auth.user.role)" class="btn btn-primary btn-sm float-right float-right" :data="getTicketFilterParams(user.role)">
					<i class="fas fa-chevron-left"></i>
					{{__('messages.go_back')}}
				</inertia-link>
	    	</div>
	    </div>
        <div class="row">
        	<div class="col-sm-12 col-md-8 col-lg-8 col-xl-8">
        		<div class="row">
        			<div class="col-md-12" v-if="_.includes(['customer'], $page.auth.user.role) && !_.includes(['closed'], ticket.status)">
        				<div class="alert alert-danger" role="alert">
                            <h3 class="alert-heading" v-html="__('messages.ticket_queue_no_is', {queue_num: ticket.queue_num})"></h3>
                        </div>
        			</div>
        			<div class="col-md-12">
		        		<div class="card">
		        			<div class="bg-c-blue p-4" id="ticketInfoHeading">
								<h5 class="mb-0 text-white">

									{{ticket.subject}}
									<br>
									<small>
									
										<span v-if="ticket.is_public">
											<i class="fas fa-unlock"></i>
											{{__('messages.public_ticket')}}
										</span>
										<span v-else>
											<i class="fas fa-lock"></i>
											{{__('messages.private_ticket')}}
										</span>
										{{ticket.ticket_ref}}
									</small>
								</h5>
							</div>
							<div class="card-body topic-name bg-light">
							    <div class="row align-items-center">
							        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 text-dark ticket-body-content">
							            <div v-html="ticket.message"></div>
										<div v-if="ticket.other_info">
											<hr>
										    {{ticket.other_info}}
										</div>
							        </div>
							    </div>
							</div>
							<div class=" card-body">
								<alert :content="error_message" type="danger"></alert>
								<div class="row">
									<div class="col-md-12" v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
										<div class="form-group">
											<label for="tag-input">
												<i class="fas fa-tags"></i>
												{{__('messages.labels')}}
											</label>
											<textarea class="form-control tags-look"
											v-model="ticket.labels" id="tag-input"
											rows="3" v-on:change="onTagifyChange"></textarea>
										</div>
									</div>
									<div class="col-md-12">
										<div class="form-group">
											<label class="form-label">
												<i class="fas fa-reply"></i>
												{{__('messages.reply')}}*
											</label>
											<p v-if="!_.isNull(ticket.license) && isSupportExpired(ticket.license.support_expires_on)" class="text-danger">
												<b>
													{{__('messages.support_expired_plz_renew')}}
												</b>
											</p>
											<p v-if="_.includes(['closed'], ticket.status) && _.includes(['customer'], user.role)" class="text-danger" v-html="__('messages.ticket_closed_post_a_reply_to_open_help')">
											</p>
											<textarea v-model="comment" class="form-control" required rows="3" id="comment_editor"></textarea>
										</div>
										<div class="checkbox checkbox-fill d-inline" v-if="_.includes(['admin', 'support_agent'], user.role)">
											<input class="form-check-input" type="checkbox" id="send_notification_to_customer" v-model="send_mail_notif_to_customer" value="1">
											<label class="cr" for="send_notification_to_customer">
												{{__('messages.send_notification_to_customer')}}
											</label>
										</div>
										<div class="checkbox checkbox-fill d-inline" v-if="_.includes(['admin', 'support_agent'], user.role)">
											<input class="form-check-input" type="checkbox" id="send_notification_to_other_agents" v-model="send_mail_notif_to_other_agents" value="1">
											<label class="cr" for="send_notification_to_other_agents">
												{{__('messages.send_notification_to_other_agents')}}
											</label>
										</div>
										<small class="form-text text-muted" v-if="!_.isEmpty(canned_responses)">
											<template v-for="(message, name) in canned_responses">
												<button type="button" class="btn btn-square btn-outline-success shadow-3 btn-sm mr-2 mt-2" @click="appendCannedResponse(message)">
													{{name}}
												</button>
											</template>
										</small>
									</div>
									<div class="col-md-12" v-if="_.includes(['customer'], user.role)">
										<div class="form-group">
											<label for="status">{{__('messages.status')}}*</label>
											<select id="status" class="form-control" required v-model="ticket_status">
												<option value="">{{__('messages.plz_select_the_status')}}</option>
												<option value="open">{{__('messages.open')}}</option>
												<option value="closed">{{__('messages.close')}}</option>
											</select>
										</div>
									</div>
									<div class="col-md-12">
										<button type="button" class="btn btn-primary btn-sm mr-2 mt-2" @click="saveComment('redirect_to_comment')" :disabled="submitting">
											<spinner :spin="submitting"></spinner>
											{{__('messages.reply')}}
										</button>
										<button type="button" class="btn btn-primary btn-sm mr-2 mt-2" @click="saveComment('redirect_back')" v-if="_.includes(['admin', 'support_agent'], user.role)" :disabled="submitting">
											<spinner :spin="submitting"></spinner>
											{{__('messages.reply_go_back_to_list')}}
										</button>
										<template v-if="_.includes(['admin', 'support_agent'], user.role)" v-for="(status, key) in statuses">
											<button type="button" class="btn btn-primary btn-sm mr-2 mt-2" @click="saveComment('status_changed', key)" :disabled="submitting">
												<spinner :spin="submitting"></spinner>
												{{__('messages.reply_and_mark_as', {
													status: status
												})}}
											</button>
										</template>
									</div>
								</div>
								<div class="row mt-5 comment-body">
									<div class="col-md-12">
										<ul class="media-list p-0">
											<template v-if="comments.length" v-for="ticketcomment in comments">
												<hr :class="'comment_line_'+ticketcomment.id">
												<li class="media mb-3" :class="'comment_line_'+ticketcomment.id">
													<div class="media-left mr-3">
														<a href="#!">
															<img class="media-object img-radius comment-img" :src="'https://ui-avatars.com/api/'+ticketcomment.commenter">
														</a>
													</div>
													<div class="media-body" style="overflow-x: auto;">
														<div :id="'comment_div_'+ticketcomment.id">
															<div class="row mb-2">
																<div class="col-md-8">
																	<h6 class="media-heading txt-primary" v-html="__('messages.user_commented_at', {
																		user: ticketcomment.commenter,
																		date: $commonFunction.timeFromNow(ticketcomment.created_at)
																	})">
																	</h6>	
																</div>
																<div class="col-md-4 float-right" v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
		                                                            <span>
		                                                            	<a href="#!" class="m-r-10 text-secondary" @click="removeComment(ticketcomment.id)">
		                                                            		<i class="far fa-trash-alt"></i>
		                                                            		{{__('messages.delete')}}
		                                                            	</a>
		                                                            </span>
		                                                            <span>
		                                                            	<a href="#!" class="text-secondary" @click="editComment(ticketcomment.id, ticketcomment.comment)">
		                                                            		<i class="far fa-edit"></i>
		                                                            		{{__('messages.edit')}}
		                                                            	</a>
		                                                            </span>
																</div>
															</div>
															<div class="text-dark" :id="'display_comment_'+ticketcomment.id" v-html="ticketcomment.comment"></div>
	                                                    </div>
	                                                    <div :id="'update_comment_div_'+ticketcomment.id" style="display:none;">
	                                                    	<textarea class="form-control" required rows="2" :id="'comment_'+ticketcomment.id"></textarea>
	                                                    	<div class="m-t-10 m-b-25">
	                                                            <span>
	                                                            	<a href="#!" class="m-r-10 text-secondary"
	                                                            		@click="dontUpdateComment(ticketcomment.id)">
	                                                            		{{__('messages.cancel')}}
	                                                            	</a>
	                                                            </span>
	                                                            <span>
	                                                            	<a href="#!" class="m-r-10 text-secondary"
	                                                            		@click="updateComment(ticketcomment.id)">
	                                                            		{{__('messages.update')}}
	                                                            	</a>
	                                                            </span>
	                                                        </div>
	                                                    </div>
													</div>
												</li>
											</template>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
        	</div>
        	<div class="col-sm-12 col-md-4 col-lg-4 col-xl-4 task-detail-right">
				<div class="accordion" id="ticketInfo">
					<div class="card">
						<div class="card-header" id="ticketInfoHeading">
							<h5 class="mb-0">
								<a href="#!" data-toggle="collapse" data-target="#ticket" aria-expanded="true" aria-controls="ticket">
									{{__('messages.ticket_information')}}
								</a>
							</h5>
						</div>
						<div id="ticket" class="card-block task-details collapse show" aria-labelledby="ticketInfoHeading" data-parent="#ticketInfo">
							<table class="table">
								<tbody>
									<tr v-if="$page.is_public_ticket_enabled && _.includes(['admin', 'support_agent'], $page.auth.user.role)">
										<td colspan="2">
											<div class="form-group">
												<div class="switch d-inline m-r-10">
													<input type="checkbox" id="is_public" v-model="edit_ticket.is_public"
													@change="updateTicket">
													<label for="is_public" class="cr">
													</label>
												</div>
												<label for="is_public" class="cr">
													<span>
														{{
															__('messages.is_public_ticket')
														}}
													</span>
												</label>
											</div>
											<div class="form-group" v-show="edit_ticket.is_public">
												<div class="switch d-inline m-r-10">
													<input type="checkbox" id="allow_comment" v-model="edit_ticket.is_commentable"
													@change="updateTicket">
													<label for="allow_comment" class="cr">
													</label>
												</div>
												<label for="allow_comment" class="cr">
													<span>
														{{
															__('messages.allow_comment')
														}}
													</span>
												</label>
											</div>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<code>{{ticket.ticket_ref}}</code>
											<span :title="__('messages.status')" class="badge text-white" :class="badgeForTicketStatus(ticket.status)">
												{{__('messages.'+ticket.status)}}
											</span>
											<span :title="__('messages.priority')" class="badge" :class="badgeForTicketPriority(ticket.priority)">
												{{__('messages.'+ticket.priority)}}
											</span>
										</td>
									</tr>
									<tr class="bg-light">
										<td>
											<b>{{__('messages.customer')}}</b>
										</td>
										<td class="word-break">
											<span v-if="!_.isEmpty(ticket.user)">
												{{ticket.user.name}}
											</span>
										</td>
									</tr>
									<tr>
										<td>
											<b>{{__('messages.product')}}</b>
										</td>
										<td class="word-break">
											<span v-if="ticket.product">
												{{ticket.product.name}}
											</span>
										</td>
									</tr>
									<tr class="bg-light"
										v-if="!_.isEmpty(ticket.product_department) && !_.isEmpty(ticket.product_department.department)">
										<td>
											<b>{{__('messages.department')}}</b>
										</td>
										<td class="word-break">
											{{ticket.product_department.department.name}}
										</td>
									</tr>
									<tr class="bg-light" v-if="!_.isNull(ticket.license)">
										<td>
											<b>{{__('messages.source')}}</b>
										</td>
										<td class="word-break">
											<span v-if="!_.isEmpty(ticket.license.source)">
												{{ticket.license.source.name}}
											</span>
										</td>
									</tr>
									<tr v-if="!_.isEmpty(ticket.license)">
										<td>
											<b>{{__('messages.license_key')}}</b>
										</td>
										<td class="word-break">
											<span v-show="ticket.license.product_license_key != 'INVALID_LICENSE'">
												{{ticket.license.product_license_key}}
											</span>
										</td>
									</tr>
									<tr v-if="!_.isEmpty(ticket.license) && !_.isEmpty(ticket.license.additional_info)" class="word-break">
										<td>
											<b>{{__('messages.license_type')}}</b>
											<br>
											<b>{{__('messages.buyer')}}</b>
										</td>
										<td>
											<template
												v-if="!_.isNull(ticket.license.additional_info.license_type)">
												{{ticket.license.additional_info.license_type}} <br>
											</template>
											<template
												v-if="!_.isNull(ticket.license.additional_info.buyer)">
												{{ticket.license.additional_info.buyer}}
											</template>
										</td>
									</tr>
									<tr class="bg-light" v-if="!_.isNull(ticket.license)">
										<td>
											<b>{{__('messages.purchased_on')}}</b>
										</td>
										<td>
											{{$commonFunction.formatDate(ticket.license.purchased_on)}}
										</td>
									</tr>
									<tr v-if="!_.isNull(ticket.license)" :class="[isSupportExpired(ticket.license.support_expires_on) ? 'bg-danger text-white' : '']">
										<td>
											<b>{{__('messages.support_expires_on')}}</b>
										</td>
										<td>
											{{$commonFunction.formatDate(ticket.license.support_expires_on)}}
										</td>
									</tr>
									<tr class="bg-light" v-if="!_.isNull(ticket.license)">
										<td>
											<b>{{__('messages.license_expires_on')}}</b>
										</td>
										<td>
											{{$commonFunction.formatDate(ticket.license.expires_on)}}
										</td>
									</tr>
									<tr>
										<td>
											<b>{{__('messages.added_at')}}</b>
										</td>
										<td>
											{{$commonFunction.timeFromNow(ticket.created_at)}}
										</td>
									</tr>
									<tr v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
										<td>
											<b>{{__('messages.labels')}}</b>
										</td>
										<td class="word-break">
											<span id="ticket_label">
												{{_.join(ticket.labels, ' , ')}}
											</span>
										</td>
									</tr>
									<!-- custom fields -->
									<template
										v-if="!_.isEmpty($page.custom_fields)">
										<template
											v-for="(field, key) in $page.custom_fields">
											<tr
												v-if="_.includes(['customer'], field.filled_by) && !_.isEmpty(ticket[key])">
												<td>
													<strong>
														{{field.label}}
													</strong>
												</td>
												<td class="word-break">
													<a 
														v-if="_.includes(['email'], field.type)"
														:href="`mailto:${ticket[key]}`" target="_blank">
														{{ticket[key]}}
													</a>
													<a 
														v-if="_.includes(['url'], field.type)"
														:href="ticket[key]" target="_blank">
														{{ticket[key]}}
													</a>
													<span
														v-if="_.includes(['datetime-local'], field.type)">
														{{$commonFunction.formatDateTime(ticket[key])}}
													</span>
													<p class="mb-1 ws-break-spaces"
														v-if="!_.includes(['email', 'url', 'datetime-local'], field.type)"
														v-html="ticket[key]">
													</p>
												</td>
											</tr>
										</template>
									</template>
									<!-- /custom fields -->
									<tr v-if="_.includes(['admin', 'support_agent'], user.role)">
										<td colspan="2">
											<div class="form-group">
												<label for="support_agents">
													{{__('messages.support_agents')}}
												</label>
												<select class="form-control" id="support_agents" multiple>
													<option v-for="(support_agent, id) in support_agents" :value="id" v-text="support_agent"></option>
												</select>
											</div>
										</td>
									</tr>
									<!-- custom fields -->
									<template
										v-if="_.includes(['admin', 'support_agent'], user.role) && !_.isEmpty(custom_fields)">
										<tr
											v-for="(custom_field, key) in custom_fields">
											<td colspan="2"
												v-if="!_.includes(['textarea'], custom_field.type)">
												<div class="form-group">
													<label :for="custom_field.name">
														{{custom_field.label}}
													</label>
													<input :type="custom_field.type" class="form-control" 
														:id="custom_field.name" 
														:placeholder="custom_field.label" 
														:required="custom_field.is_required"
														v-model="custom_field.value"
														@change="updateCustomFields"
													>
												</div>
											</td>
											<td colspan="2"
												v-if="_.includes(['textarea'], custom_field.type)">
												<div class="form-group">
													<label :for="custom_field.name">
														{{custom_field.label}}
													</label>
													<textarea class="form-control" 
														:id="custom_field.name" rows="3" 
														:placeholder="custom_field.label"
														:required="custom_field.is_required"
														v-model="custom_field.value"
														@change="updateCustomFields">
													</textarea>
												</div>
											</td>
										</tr>
									</template>
									<!-- /custom fields -->
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="accordion" id="ticketInfoAccordion" v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
					<!-- product license -->
				    <div class="card">
				        <div class="card-header" id="headingOne">
				            <h5 class="mb-0">
				                <a href="#!" class="collapsed" data-toggle="collapse" data-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne" @click="getUserPurchaseLists">
				                    {{__('messages.purchases_license')}}
				                </a>
				            </h5>
				        </div>
				        <div id="collapseOne" class="card-body collapse" aria-labelledby="headingOne" data-parent="#ticketInfoAccordion">
				            <template v-for="purchase in purchases" v-if="purchases.length > 0">
					            <div class="row">
					            	<div class="col-md-6">
					            		<b>{{__('messages.license')}}</b>
					            	</div>
					            	<div class="col-md-6">
					            		{{purchase.product_license_key}}
					            	</div>
					            </div>
					            <div class="row">
					            	<div class="col-md-6">
					            		<b>{{__('messages.product')}}</b>
					            	</div>
					            	<div class="col-md-6">
					            		{{purchase.product}}
					            	</div>
					            </div>
					            <div class="row"
					            	v-if="purchase.purchased_on">
						            <div class="col-md-6">
					            		<b>{{__('messages.purchased_on')}}</b>
					            	</div>
					            	<div class="col-md-6">
					            		{{$commonFunction.formatDate(purchase.purchased_on)}}
					            	</div>
					            </div>
					            <div class="row"
					            	v-if="purchase.support_expires_on">
						            <div class="col-md-6">
					            		<b>{{__('messages.support_expires_on')}}</b>
					            	</div>
					            	<div class="col-md-6">
					            		{{$commonFunction.formatDate(purchase.support_expires_on)}}
					            	</div>
					            </div>
					            <div class="row"
					            	v-if="purchase.expires_on">
						            <div class="col-md-6">
					            		<b>{{__('messages.license_expires_on')}}</b>
					            	</div>
					            	<div class="col-md-6">
					            		{{$commonFunction.formatDate(purchase.expires_on)}}
					            	</div>
					            </div>
								<div class="row" v-if="!_.isNull(purchase.additional_info) && !_.isNull(purchase.additional_info.license_type)">
									<div class="col-md-6" >
										<b>{{__('messages.license_type')}} </b>
									</div>
									<div class="col-md-6">
										{{ purchase.additional_info.license_type }}
									</div>
								</div>
								<div class="row" v-if="!_.isNull(purchase.additional_info) && !_.isNull(purchase.additional_info.buyer)">
									<div class="col-md-6">
										<b>{{__('messages.buyer')}} </b>
									</div>
									<div class="col-md-6">
										{{purchase.additional_info.buyer}}
									</div>
								</div>
					            <hr>
					        </template>
					        <div class="row no-gutters" v-if="purchases.length <= 0">
				            	<div class="col-md-12">
				            		<div class="alert alert-warning" role="alert">
									  {{__('messages.no_purchases_found')}}
									</div>
				            	</div>
				            </div>
				        </div>
				    </div>
				    <!-- ticket info -->
				    <div class="card">
				        <div class="card-header" id="headingTwo">
				            <h5 class="mb-0">
				                <a href="#!" class="collapsed" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo" @click="getCustomerTickets">
				                    {{__('messages.tickets')}}
				                </a>
				            </h5>
				        </div>
				        <div id="collapseTwo" class="card-body collapse" aria-labelledby="headingTwo" data-parent="#ticketInfoAccordion">
				            <template v-show="customer_tickets.length" v-for="customer_ticket in customer_tickets">
				            	<inertia-link :href="route_ziggy('customer.view-ticket', [customer_ticket.id])">
						            <div class="row no-gutters cursor-pointer">
						            	<div class="col-md-12">
						            		<div class="m--font-bolder d-flex align-items-center">
												<div class="no-wrap d-inline-block max-width-250" :title="customer_ticket.subject">
													{{customer_ticket.subject}}
												</div>
												<span class="text-muted ml-2" :title="__('messages.ref_num')">
													{{customer_ticket.ticket_ref}}
												</span>
											</div>
											<div class="align-items-baseline">
												<span :title="__('messages.status')">
													<span class="badge"
														:class="badgeForTicketStatus(customer_ticket.status)">
														{{
															__('messages.'+customer_ticket.status)
														}}
													</span>
												</span>
												<span class="ml-1" :title="__('messages.priority')">
													<span class="badge"
														:class="badgeForTicketPriority(customer_ticket.priority)">
														{{
															__('messages.'+customer_ticket.priority)
														}}
													</span>
												</span>
												<span :title="__('messages.added_on')">
													<span class="badge badge-light">
														{{$commonFunction.timeFromNow(customer_ticket.created_at)}}
													</span>
												</span>
											</div>
						            	</div>
						            </div>
					        	</inertia-link>
					            <hr>
					        </template>
					        <div class="alert alert-warning" role="alert" v-show="customer_tickets.length <= 0">
							  {{__('messages.no_more_tickets_found')}}
							</div>
				        </div>
				    </div>
				    <!-- notes -->
				    <div class="card">
					    <div class="card-header" id="headingThree">
					        <h5 class="mb-0">
					            <a href="#!" class="collapsed" data-toggle="collapse" data-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree"
					            	@click="getNotesForCustomer">
					                {{__('messages.notes')}}
					            </a>
					        </h5>
					    </div>
					    <div id="collapseThree" class="card-body collapse" aria-labelledby="headingThree" data-parent="#ticketInfoAccordion">
					       <div class="row mb-3" v-if="ticket_notes.length > 0">
								<div class="col-md-12">
									<div class="list-group">
										<template v-for="note in ticket_notes">
									  		<div class="list-group-item list-group-item-action cursor-pointer " :class="'note_line_'+note.id">
											    <div class="d-flex w-100 justify-content-between">
											    	<small class="text-muted">
											    		<i class="far fa-clock"></i>
											    		{{$commonFunction.timeFromNow(note.created_at)}}
											    	</small>
											    	<small v-if="note.added_by.id === $page.auth.user.id" class="text-muted" @click="deleteTicketNote(note.id)">
											    		<i class="far fa-trash-alt text-danger"></i>
											    	</small>
											    </div>
											    <p class="mb-1 mt-1 ws-pre-wrap" v-html="note.note"></p>
											    <small class="text-muted" v-if="!_.isEmpty(note.added_by)">
											    	<i class="fas fa-pen"></i> {{note.added_by.name}}
											    </small>
											</div>
								  		</template>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-12">
						        	<div class="form-group">
						                <label for="note">{{__('messages.note')}}*</label>
						                <textarea class="form-control" id="note" v-model="ticket_note" rows="2"></textarea>
						            </div>
						        </div>
						        <div class="col-md-12" v-show="!_.isEmpty(ticket_note)">
						        	<button class="btn btn-primary btn-sm btn-block" @click="storeTicketNote">
						        		{{__('messages.add')}}
						        	</button>
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
	import CustomerLeftnav from '@/Pages/Customer/Leftnav';
	import AdminLeftnav from '@/Pages/Elements/Leftnav';
	import Spinner from '@/Shared/Spinner';
	import SupportTiming from '@/Shared/SupportTiming';
	export default {
		components: {
			Layout,
			CustomerLeftnav,
			AdminLeftnav,
			Spinner,
			SupportTiming
		},
		props: ['ticket', 'user', 'statuses',
			'canned_responses', 'mail_notif_to_customer',
			'labels', 'support_agents', 'ticket_agents',
			'default_reply', 'mail_notif_to_other_agents'
		],
  		data: function () {
  			return {
  				comment: '',
  				error_message: null,
  				comments: [],
  				submitting: false,
  				edit_ticket: {},
  				ticket_status: '',
  				customer_tickets:[],
  				purchases:[],
  				send_mail_notif_to_customer: false,
  				ticket_labels: null,
  				ticket_note: '',
  				ticket_notes:[],
				send_mail_notif_to_other_agents: false,
				custom_fields:[],
  			}
  		},
  		mounted() {
  			const self = this;
  			$(function () {
  				var tagify_el = new Tagify(document.querySelector('textarea#tag-input'), {
  					whitelist: self.labels,
					maxTags: 100,
					dropdown: {
					    maxItems: 100,           // <- mixumum allowed rendered suggestions
					    classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
					    enabled: 0,             // <- show suggestions on focus
					    closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
					}
				});
				
				$(".ticket-body-content a[href^=http]").each(function(){
					$(this).attr({
					   target: "_blank",
					   title: this.href,
				       rel: 'noopener'
					});
				});

				if (_.includes(['admin', 'support_agent'], self.user.role)) {
					$('#support_agents').select2({
						placeholder: self.__('messages.plz_select_support_agents'),
						allowClear: true
					})
			    	.val(self.ticket_agents).trigger('change')
			    	.on("change", function (e) {
			    		self.updateSupportAgents(self.ticket.id, $("#support_agents").val());
			    	});
			    }
  			});
			self.getCustomFields();
  		},
  		created: function () {
  			const self = this;
		    $(function() {
		    	//if editor exist destory & re-initialize it
		    	if (!_.isNull(tinymce.get('comment_editor'))) {
  					tinymce.remove("textarea#comment_editor");
				}
				//initialize editor
				tinymce.init({
				    selector: 'textarea#comment_editor',
				    auto_focus: 'comment_editor',
				    relative_urls : false
				});
			    self.setSignature(self.default_reply);
		    });
		    self.edit_ticket = self.ticket;
		    self.getCommentsForTicket();
		    self.send_mail_notif_to_customer = self.mail_notif_to_customer;
			self.send_mail_notif_to_other_agents = self.mail_notif_to_other_agents;
		},
  		methods:{
			getCustomFields() {
				const self = this;
				if(!_.isEmpty(self.$page.custom_fields)) {
					for (const key in self.$page.custom_fields) {
						if (
							self.$page.custom_fields.hasOwnProperty(key) &&
							_.includes(['support_agent'], self.$page.custom_fields[key]['filled_by']) &&
							!_.isEmpty(self.$page.custom_fields[key]['label'])
						) {
							let product_id = Number(self.ticket.product_id);
							let department_id = !_.isEmpty(self.ticket.product_department) ? Number(self.ticket.product_department.department_id) : null;
							let field = this.$page.custom_fields[key];
							if(
								(
									!_.isEmpty(field.products) &&
									_.includes(field.products, product_id)
								) ||
								(
									!_.isEmpty(field.departments) &&
									!_.isNull(department_id) &&
									_.includes(field.departments, department_id)
								)
							) {
								field['name'] = key;
								field['value'] = self.ticket[key] || '';
								self.custom_fields.push(field);
							}
						}
					}
				}
			},
  			getRedirectBackUrl(role) {
  				if (_.includes(['admin', 'support_agent'], role)) {
  					return this.route_ziggy('tickets.index');
  				} else if (_.includes(['customer'], role)) {
  					return this.route_ziggy('customer.tickets.index');	
  				}
  			},
  			saveComment(submit_action, status = null) {
  				const self = this;
  				self.comment = tinymce.get("comment_editor").getContent();
  				if(self.comment.length == 0){
					self.error_message = self.__('messages.comment_is_required');
					return false;
				} else if (self.user.role == 'customer' && _.isEmpty(self.ticket_status)) {
					self.error_message = self.__('messages.status_is_required');
					return false;
				} else {
					self.error_message = null;
					let data = {'comment' : self.comment};
            			data.ticket_id = self.ticket.id;
            			data.status = _.isEmpty(status) ? self.ticket_status : status;
            			data.submit_action = submit_action;
            			data.filetr_params = self.getTicketFilterParams(self.user.role);
            			data.send_mail_notif_to_customer = self.send_mail_notif_to_customer;
						data.send_mail_notif_to_other_agents = self.send_mail_notif_to_other_agents;
            		if (_.includes(['status_changed', 'redirect_back'], submit_action)) {
            			self.submitting = true;
            			self.$inertia.post(this.route_ziggy('customer.ticket-comments.store'), data)
			                .then(function(response){
			                	self.submitting = false;
			                });
            		} else if ('redirect_to_comment' == submit_action) {
            			self.submitting = true;
						axios.post(self.route_ziggy('customer.ticket-comments.store').url(), data)
						.then(function (response) {
							if (response.data.success) {
							    toastr.success(response.data.msg);
							} else {
							    toastr.error(response.data.msg);
							}
							self.submitting = false;
							self.getCommentsForTicket();
							self.setSignature(self.default_reply);
							self.comment = '';
							self.ticket_status = '';
						})
						.catch(function (error) {
							console.log(error);
						})
						.then(function () {
							// always executed
						});
            		}
				}
  			},
  			getCommentsForTicket() {
  				const self = this;
	            axios
	                .get(self.route_ziggy('customer.ticket-comments.index').url(), {
	                    params: { ticket_id: self.ticket.id },
	                })
	                .then(function(response) {
	                    self.comments = response.data;
	                })
	                .catch(function(error) {
	                    console.log(error);
	                }).then(function () {
	                	//convert all anchor tag target to new window
						$(".comment-body a[href^=http]").each(function(){
							$(this).attr({
							   target: "_blank",
							   title: this.href,
						       rel: 'noopener'
							});
						});
					});
  			},
  			appendCannedResponse(message) {
  				const self = this;
  				tinymce.get("comment_editor").insertContent(message);
  			},
  			updateTicket() {
  				const self = this;
  				var data = {
  					is_public : self.edit_ticket.is_public,
  					is_commentable : self.edit_ticket.is_commentable,
  					ticket_id : self.ticket.id,
  					ticket_labels: self.ticket_labels
  				}
  				axios.post(self.route_ziggy('upate-ticket').url(), data)
					.then(function (response) {
						self.edit_ticket.is_public = response.data.is_public;
						self.edit_ticket.is_commentable = response.data.is_commentable;
					})
					.catch(function (error) {
						console.log(error);
					})
					.then(function () {
						// always executed
					});
  			},
  			editComment(comment_id, comment) {
  				//initialize editor
				tinymce.init({
				    selector: 'textarea#comment_'+comment_id,
				    auto_focus: 'comment_editor'
				});

				tinymce.get('comment_'+comment_id).setContent(comment);

  				$("#comment_div_"+comment_id).hide();
  				$("#update_comment_div_"+comment_id).fadeIn();
  			},
  			updateComment(comment_id) {
  				const self = this;
				var comment = tinymce.get('comment_'+comment_id).getContent();
				if(comment.length == 0){
					toastr.warning(self.__('messages.comment_is_required'));
					return false;
				} else {
					axios.put(self.route_ziggy('customer.ticket-comments.update', {'ticket_comment':comment_id}).url(),
						{
					    comment: comment,
						ticket_id : self.ticket.id
					  })
					.then(function (response) {
						if (response.data.success) {
							$("#update_comment_div_"+comment_id).hide();
							$("#comment_div_"+comment_id).fadeIn();
						    toastr.success(response.data.msg);
						    $("div#display_comment_"+comment_id).html(comment);
						} else {
						    toastr.error(response.data.msg);
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
  			dontUpdateComment(comment_id) {
  				$("#comment_div_"+comment_id).fadeIn();
  				$("#update_comment_div_"+comment_id).hide();
  			},
  			removeComment(comment_id) {
  				const self = this;
  				if (confirm(self.__('messages.are_you_sure'))) {
  					axios.delete(self.route_ziggy('customer.ticket-comments.destroy', {'ticket_comment':comment_id, 'ticket_id' : self.ticket.id}).url())
					.then(function (response) {
						if (response.data.success) {
						    toastr.success(response.data.msg);
						    $(".comment_line_"+comment_id).remove();
						} else {
						    toastr.error(response.data.msg);
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
  			getTicketFilterParams(role) {
  				
  				if (_.includes(['admin', 'support_agent'], role) && !_.isNull(sessionStorage.getItem('ticketFilterParams'))) {
  					var ticketFilterParams =  JSON.parse(sessionStorage.getItem('ticketFilterParams'));

  					return ticketFilterParams;
  				}

  				return null;
  			},
  			getCustomerTickets() {
  				const self = this;
  				axios.get(self.route_ziggy('customer.customer-tickets', {
  					'customer_id':self.ticket.user_id,
  					'ticket_id': self.ticket.id
  				}).url())
				.then(function (response) {
					if (response.data.success) {
						self.customer_tickets = response.data.tickets;
					} else {
					    toastr.error(response.data.msg);
					}
				})
				.catch(function (error) {
					console.log(error);
				})
				.then(function () {
					// always executed
				});
  			},
  			getUserPurchaseLists() {
  				const self = this;
  				axios.get(self.route_ziggy('customer.customer-purchases', {
  					'customer_id':self.ticket.user_id
  				}).url())
				.then(function (response) {
					if (response.data.success) {
						self.purchases = response.data.purchases;
					} else {
					    toastr.error(response.data.msg);
					}
				})
				.catch(function (error) {
					console.log(error);
				})
				.then(function () {
					// always executed
				});
  			},
  			isSupportExpired(support_expiry_date) {
            	var is_expired = moment().isAfter(support_expiry_date);
            	return is_expired;
            },
            onTagifyChange(e) {
            	this.ticket_labels = e.target.value;
            	if (!_.isEmpty(this.ticket_labels)) {
            		let labels = JSON.parse(this.ticket_labels);
	            	let result = labels.map(label => label.value);
	            	document.getElementById("ticket_label").innerHTML = result.join(', ');
            	} else {
            		document.getElementById("ticket_label").innerHTML = '';
            	}
            	this.updateTicket();
            },
            updateSupportAgents(ticket_id, agents) {
            	const self = this;
            	axios.post(self.route_ziggy('upate.ticket.agents').url(), {
            		'ticket_id': ticket_id,
            		'agent_ids': agents
            	})
				.then(function (response) {
					//
				})
				.catch(function (error) {
					console.log(error);
				})
				.then(function () {
					// always executed
				});
            },
            setSignature(replyTemplate) {
            	const self = this;
            	if (_.includes(['admin', 'support_agent'], self.$page.auth.user.role) && !_.isEmpty(replyTemplate)) {
			    	tinymce.get("comment_editor").setContent(replyTemplate);
			    } else {
			    	tinymce.get("comment_editor").setContent('');
			    }
            },
            getNotesForCustomer() {
  				const self = this;
	            axios
	                .get(self.route_ziggy('customer.ticket.notes', {'id':self.ticket.user_id, 'ticket_id': self.ticket.id}).url())
	                .then(function(response) {
	                    self.ticket_notes = response.data.notes;
	                })
	                .catch(function(error) {
	                    console.log(error);
	                })
  			},
            storeTicketNote() {
            	const self = this;
            	axios.post(self.route_ziggy('store-ticket-note').url(), {
            		'ticket_id': self.ticket.id,
            		'note': self.ticket_note,
            		'customer_id': self.ticket.user_id
            	})
				.then(function (response) {
					if (response.data.success) {
						self.ticket_notes.push(response.data.note);
						self.ticket_note = '';
					}
				})
				.catch(function (error) {
					console.log(error);
				})
				.then(function () {
					// always executed
				});
            },
            deleteTicketNote(id) {
            	const self = this;
  				if (confirm(self.__('messages.are_you_sure'))) {
  					axios.delete(self.route_ziggy('delete-ticket-note', {'id':id}).url())
					.then(function (response) {
						if (response.data.success) {
						    toastr.success(response.data.msg);
						    $(".note_line_"+id).remove();
						} else {
						    toastr.error(response.data.msg);
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
			updateCustomFields() {
				const self = this;
				let custom_field_data = {};
				let ticket_id = self.ticket.id;
				for (const field of self.custom_fields) {
					custom_field_data[field.name] = field.value;
				}
				axios.put(self.route_ziggy('ticket.customFields.update', {'id':ticket_id}).url(), custom_field_data)
				.then(function (response) {
					if (response.data.success) {
						toastr.success(response.data.msg);
					} else {
						toastr.error(response.data.msg);
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
<style scoped>
	table td, .table th {
	    white-space: inherit;
		padding-bottom: 1rem;
		padding-top: 1rem;
	}
	.word-break {
		word-break: break-all !important;
	}
</style>