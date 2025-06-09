<template>
	<layout :title="__('messages.view_ticket')">
    	<template v-slot:leftnav>
            <CustomerLeftnav v-if="_.includes(['customer'], $page.auth.user.role)">
            </CustomerLeftnav>
            <AdminLeftnav v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
            </AdminLeftnav>
        </template>
        <div class="row">
	        <div class="col-sm-6 col-md-6 col-lg-6 col-xl-6">
	    		<inertia-link :href="route_ziggy('customer.public-tickets')" class="btn btn-primary btn-sm" :data="getPublicTicketFilterParams()">
					<i class="fas fa-chevron-left"></i>
					{{__('messages.go_back')}}
				</inertia-link>
	    	</div>
	    	<div class="col-sm-6 col-md-6 col-lg-6 col-xl-6">
        		<support-timing :classes="'btn-sm btn-danger float-right'"></support-timing>
        	</div>
	    </div>
	    <div class="row">
	    	<div class="col-sm-12 col-md-12 col-lg-12 col-xl-12" v-if="_.includes(['customer'], $page.auth.user.role) && !_.includes(['closed'], ticket.status) && ticket.user_id === $page.auth.user.id">
				<div class="alert alert-danger" role="alert">
                    <h3 class="alert-heading" v-html="__('messages.ticket_queue_no_is', {queue_num: ticket.queue_num})"></h3>
                </div>
			</div>
	    	<div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
	    		<div class="card">
        			<div class="bg-c-blue p-4" id="ticketInfoHeading">
						<h5 class="mb-0 text-white">
							{{ticket.subject}}
							<br>
							<small>
								<i class="fas fa-unlock"></i>
								{{__('messages.public_ticket')}}
							</small>
						</h5>
					</div>
					<div class="card-body topic-name bg-light">
					    <div class="row align-items-center">
					        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 text-dark ticket-body-content">
					            <div v-html="ticket.message"></div>
					            <hr>
								<p v-if="ticket.other_info">
								    {{ticket.other_info}}
								</p>
					        </div>
					    </div>
					</div>
					<div class=" card-body">
						<alert :content="error_message" type="danger"></alert>
						<div class="row mb-5" v-if="isEnabledComment(ticket, $page.auth.user)">
							<div class="col-md-12">
								<div class="form-group">
									<label class="form-label">
										<i class="fas fa-reply"></i>
										{{__('messages.reply')}}*
									</label>
									<p v-if="_.includes(['closed'], ticket.status)" class="text-danger" v-html="__('messages.ticket_closed_post_a_reply_to_open_help')">
									</p>
									<textarea class="form-control" id="comment"></textarea>
									<button type="button" class="btn btn-primary btn-sm mr-2 mt-2" @click="saveComment('redirect_to_comment')" :disabled="submitting">
										<spinner :spin="submitting"></spinner>
										{{__('messages.reply')}}
									</button>
								</div>
							</div>
						</div>
						<div class="row comment-body" v-if="comments.length">
							<div class="col-md-12">
								<h4>
									<i class="far fa-comments"></i>
									{{__('messages.comments')}}
								</h4>
							</div>
							<div class="col-md-12">
								<ul class="media-list p-0">
									<template v-for="ticketcomment in comments">
										<hr>
										<li class="media mb-3">
											<div class="media-left mr-3">
												<a href="#!">
													<img class="media-object img-radius comment-img" :src="'https://ui-avatars.com/api/'+ticketcomment.commenter">
												</a>
											</div>
											<div class="media-body" style="overflow-x: auto;">
												<div :id="'comment_div_'+ticketcomment.id">
													<div class="row mb-2">
														<div class="col-md-9">
															<h6 v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role) || $page.auth.user.id == ticketcomment.user_id" class="media-heading txt-primary" v-html="__('messages.user_commented_at', {
																user: ticketcomment.commenter,
																date: $commonFunction.timeFromNow(ticketcomment.created_at)
															})">
															</h6>
															<h6 v-else class="media-heading txt-primary" v-html="__('messages.comment_added_at',
																	{
																		date: $commonFunction.timeFromNow(ticketcomment.created_at)
																	})">
															</h6>
														</div>
														<div class="col-md-3 float-right" v-if="_.includes(['admin', 'support_agent'], $page.auth.user.role)">
		                                                    <span>
		                                                    	<a href="#!" class="m-r-10 text-secondary" @click="removeComment(ticketcomment.id)">
		                                                    		<i class="far fa-trash-alt"></i>
		                                                    		{{__('messages.delete')}}
		                                                    	</a>
		                                                    </span>
		                                                    <span>
		                                                    	<a href="#!" class="m-r-10 text-secondary" @click="editComment(ticketcomment.id, ticketcomment.comment)">
		                                                    		<i class="far fa-edit"></i>
		                                                    		{{__('messages.edit')}}
		                                                    	</a>
		                                                    </span>
														</div>
													</div>
													<div class="text-dark" v-html="ticketcomment.comment"></div>
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
			AdminLeftnav,
			CustomerLeftnav,
			Spinner,
			SupportTiming
		},
		props: ['ticket', 'default_reply'],
		data: function () {
  			return {
  				comments: [],
  				submitting: false,
  				error_message: ''
  			}
  		},
		created() {
			const self = this;
			$(function() {
		    	//if editor exist destory & re-initialize it
		    	if (!_.isNull(tinymce.get('comment'))) {
  					tinymce.remove("textarea#comment");
				}
				//initialize editor
				tinymce.init({
				    selector: 'textarea#comment',
				    auto_focus: 'comment'
				});
			    self.setSignature(self.default_reply);
				$(".ticket-body-content a[href^=http]").each(function(){
					$(this).attr({
					   target: "_blank",
					   title: this.href,
				       rel: 'noopener'
					});
				});
		    });
			self.getCommentsForTicket();
		},
		methods:{ 
			saveComment(submit_action) {
  				const self = this;
  				var comment = tinymce.get("comment").getContent();
  				if(_.isNull(comment) || comment.length == 0){
					self.error_message = self.__('messages.comment_is_required');
					return false;
				} else {
					self.error_message = null;
					let data = {'comment' : comment};
            			data.ticket_id = self.ticket.id;
            			data.submit_action = submit_action;

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
					})
					.catch(function (error) {
						console.log(error);
					})
					.then(function () {
						// always executed
					});
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
			isEnabledComment(ticket, currentUser) {
				if (_.includes(['admin', 'support_agent'], currentUser.role) || ticket.user_id == currentUser.id) {
					return true;
				} else if (_.includes(['customer'], currentUser.role) && ticket.is_commentable) {
					return true;
				}

				return false;
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
						    self.getCommentsForTicket();
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
						    self.getCommentsForTicket();
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
  			getPublicTicketFilterParams() {
  				if (!_.isNull(sessionStorage.getItem('pTticketFilterParams'))) {
  					var ticketFilterParams =  JSON.parse(sessionStorage.getItem('pTticketFilterParams'));
  					return ticketFilterParams;
  				}
  				return null;
  			},
  			setSignature(replyTemplate) {
  				const self = this;
  				if (_.includes(['admin', 'support_agent'], self.$page.auth.user.role) && !_.isEmpty(replyTemplate)) {
			    	tinymce.get("comment").setContent(replyTemplate);
			    } else {
			    	tinymce.get("comment").setContent('');
			    }
  			}
		}
	}
</script>