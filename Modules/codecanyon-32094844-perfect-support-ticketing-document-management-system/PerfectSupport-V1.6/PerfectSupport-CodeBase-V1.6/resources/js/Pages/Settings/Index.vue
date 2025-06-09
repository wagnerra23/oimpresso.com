<template>
 	<layout :title="__('messages.settings')">
    	<template v-slot:leftnav>
            <Leftnav></Leftnav>
        </template>
        <div class="page-wrapper">
        	<div class="page-header-title">
				<h3 class="m-b-10">
					{{__('messages.settings')}}
					<small>
						{{__('messages.edit_settings')}}
					</small>
				</h3>
			</div>
			<form v-on:submit.prevent="submitForm">
				<div class="row">
					<div class="col-md-12 col-sm-12">
						<ul class="nav nav-pills mb-3" id="v-pills-tab" role="tablist" aria-orientation="vertical">
							<li>
								<a class="nav-link text-left active" id="v-pills-ticket-tab" data-toggle="pill" href="#v-pills-ticket" role="tab" aria-controls="v-pills-ticket" aria-selected="true">
									{{__('messages.ticket')}}
								</a>
							</li>
							<li>
								<a class="nav-link text-left" id="v-pills-ticket_reminder-tab" data-toggle="pill" href="#v-pills-ticket_reminder" role="tab" aria-controls="v-pills-ticket_reminder" aria-selected="true">
									{{__('messages.ticket_reminder')}}
								</a>
							</li>
							<li>
								<a class="nav-link text-left" id="v-pills-notification-tab" data-toggle="pill" href="#v-pills-notification" role="tab" aria-controls="v-pills-notification" aria-selected="false">
									{{__('messages.notification')}}
								</a>
							</li>
							<li>
								<a class="nav-link text-left" id="v-pills-custom-fields-tab" data-toggle="pill" href="#v-pills-custom-fields" role="tab" aria-controls="v-pills-custom-fields-template" aria-selected="false">
									{{__('messages.custom_fields')}}
								</a>
							</li>
							<li>
								<a class="nav-link text-left" id="v-pills-support-timing-tab" data-toggle="pill" href="#v-pills-support-timing" role="tab" aria-controls="v-pills-default -support-agent-reply-template" aria-selected="false">
									{{__('messages.support_timing')}}
								</a>
							</li>
							<li>
								<a class="nav-link text-left" id="v-pills-integration-tab" data-toggle="pill" href="#v-pills-integration" role="tab" aria-controls="v-pills-integration" aria-selected="false">
									{{__('messages.integration')}}
								</a>
							</li>							
							<li>
								<a class="nav-link text-left" id="v-pills-miscellaneous-tab" data-toggle="pill" href="#v-pills-miscellaneous" role="tab" aria-controls="v-pills-miscellaneous" aria-selected="false">
									{{__('messages.miscellaneous')}}
								</a>
							</li>
						</ul>
						<div class="tab-content" id="v-pills-tabContent">
							<div class="tab-pane fade show active" id="v-pills-ticket" role="tabpanel" aria-labelledby="v-pills-ticket-tab">
								<div class="row">
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="ticket_prefix">
                                               {{__('messages.ticket_prefix')}}*
                                           </label>
                                           <input class="form-control" type="text" :placeholder="__('messages.ticket_prefix')" id="ticket_prefix" v-model="system.ticket_prefix" required>
                                       </div>
                                   </div>
                                   <div class="col-md-6">
                                       <div class="form-group">
                                           <label for="auto_close_ticket_in_days">
                                               {{__('messages.auto_close_ticket_in_days')}}
                                               <i class="fas fa-info-circle fa-lg text-info cursor-pointer" data-toggle="tooltip"
                                                   :title="__('messages.auto_close_ticket_in_days_tooltip')">
                                               </i>
                                           </label>
                                           <input class="form-control" type="number" :placeholder="__('messages.auto_close_ticket_in_days')" id="auto_close_ticket_in_days" v-model="system.auto_close_ticket_in_days" min="0">
                                       </div>
                                   </div>
                               </div>
                               <h4 class="mt-4 mb-3">
                                   {{
                                       __('messages.public_ticket')
                                   }}
                               </h4>
                               <div class="form-group row">
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="enable_public_ticket" v-model="system.is_public_ticket_enabled" value="1">
                                           <label class="cr" for="enable_public_ticket">
                                               {{__('messages.enable_public_ticket')}}
                                           </label>
                                       </div>
                                   </div>
                                   <div class="col-sm-8">
                                       <label>
                                           {{__('messages.default_ticket_type')}}:
                                       </label>
                                       &nbsp;&nbsp;
                                       <div class="form-group d-inline">
                                           <div class="radio d-inline">
                                               <input type="radio" name="default_ticket_type" id="private" v-model="system.default_ticket_type" value="private">
                                               <label for="private" class="cr">
                                                   {{__('messages.private')}}
                                               </label>
                                           </div>
                                       </div>

                                       <div class="form-group d-inline">
                                           <div class="radio d-inline">
                                               <input type="radio" name="default_ticket_type" id="public" v-model="system.default_ticket_type" value="public">
                                               <label for="public" class="cr">
                                                   {{__('messages.public')}}
                                               </label>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                               <div class="row mt-3">
                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label class="form-label">
                                               {{__('messages.ticket_instruction')}}*
                                           </label>
                                           <textarea  class="form-control" rows="3" id="ticket_instruction"></textarea>
                                       </div>
                                       <alert :content="error_message" type="danger"></alert>
                                   </div>
								   <div class="col-md-12 mt-2">
										<div class="form-group">
											<label for="signature">
												{{ __('messages.support_agent_reply_temp')}}
											</label>
											<textarea  class="form-control" rows="3" id="signature"></textarea>
											<small class="form-text text-muted">
												<p>
													{{settings.signature_tags.help_text}}:
													<code>{{settings.signature_tags.tags.join(', ')}}</code>
												</p>
											</small>
										</div>	
								   </div>
                               </div>
							</div>
							<div class="tab-pane fade" id="v-pills-ticket_reminder" role="tabpanel" aria-labelledby="v-pills-ticket-tab">
								<div class="row mb-2">
									<div class="col-md-12">
										<p class="mb-2 text-primary">
											<i class="fas fa-info-circle"></i>
											{{__('messages.ticket_reminder_help_text')}}
										</p>
									</div>
									<div class="col-md-12">
                                       <div class="form-group row">
                                           <label class="col-sm-4 col-form-label" for="remind_ticket_in_days">
                                               {{__('messages.remind_ticket_in_days')}}
											   <i class="fas fa-info-circle fa-lg text-info cursor-pointer" data-toggle="tooltip"
                                                   :title="__('messages.ticket_reminder_help_text')">
                                               </i>
                                           </label>
										   <div class="col-sm-8">
												<input class="form-control" type="number" :placeholder="__('messages.remind_ticket_in_days')" id="remind_ticket_in_days" v-model="system.remind_ticket_in_days" min="0">
										   </div>
                                       </div>
                                   </div>
								   <div class="col-md-12">
										<h4>
											{{ __('messages.email_template') }}
									</h4>
								   </div>
								   <div class="col-md-12">
                                       <div class="form-group">
                                           <label for="tc_email_subject">
                                               {{__('messages.email_subject')}}
                                           </label>
                                           <input class="form-control" type="text" 
										   	:placeholder="__('messages.email_subject')" id="tc_email_subject" v-model="system.ticket_reminder_mail_template.subject">
                                       </div>
                                   </div>
								   <div class="col-md-12 mt-2">
										<div class="form-group">
											<label for="tc_body_template">
												{{ __('messages.email_body')}}
											</label>
											<textarea  class="form-control" rows="3"
												id="tc_body_template"
												v-model="system.ticket_reminder_mail_template.body"></textarea>
											<small class="form-text text-muted">
												<p>
													{{settings.ticket_reminder_tags.help_text}}:
													<code>{{settings.ticket_reminder_tags.tags.join(', ')}}</code>
												</p>
											</small>
										</div>	
								   </div>
								</div>
							</div>
							<div class="tab-pane fade" id="v-pills-notification" role="tabpanel" aria-labelledby="v-pills-notification-tab">
								<h4 class="mb-3">
                                   {{__('messages.customer_notification')}}
                               </h4>
                               <div class="form-group row">
                                   <div class="col-sm-4">
                                       {{__('messages.new_ticket')}}
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="cust_new_ticket_app_notif" v-model="system.cust_new_ticket_app_notif" value="1">
                                           <label class="cr" for="cust_new_ticket_app_notif">
                                               {{__('messages.app')}}
                                           </label>
                                       </div>
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="cust_new_ticket_mail_notif" v-model="system.cust_new_ticket_mail_notif" value="1">
                                           <label class="cr" for="cust_new_ticket_mail_notif">
                                               {{__('messages.email')}}
                                           </label>
                                       </div>
                                   </div>
                               </div>
                               
                               <div class="form-group row">
                                   <div class="col-sm-4">
                                       {{__('messages.agent_replied_ticket')}}
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="agent_replied_to_ticket_app_notif" v-model="system.agent_replied_to_ticket_app_notif" value="1">
                                           <label class="cr" for="agent_replied_to_ticket_app_notif">
                                               {{__('messages.app')}}
                                           </label>
                                       </div>
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="agent_replied_to_ticket_mail_notif" v-model="system.agent_replied_to_ticket_mail_notif" value="1">
                                           <label class="cr" for="agent_replied_to_ticket_mail_notif">
                                               {{__('messages.email')}}
                                           </label>
                                       </div>
                                   </div>
                               </div>

                               <h4 class="mt-4 mb-3">
                                   {{
                                       __('messages.support_agent_notification')
                                   }}
                               </h4>

                               <div class="form-group row">
                                   <div class="col-sm-4">
                                       {{__('messages.new_ticket')}}
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="agent_assigned_ticket_app_notif" v-model="system.agent_assigned_ticket_app_notif" value="1">
                                           <label class="cr" for="agent_assigned_ticket_app_notif">
                                               {{__('messages.app')}}
                                           </label>
                                       </div>
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="agent_assigned_ticket_mail_notif" v-model="system.agent_assigned_ticket_mail_notif" value="1">
                                           <label class="cr" for="agent_assigned_ticket_mail_notif">
                                               {{__('messages.email')}}
                                           </label>
                                       </div>
                                   </div>
                               </div>
                               <div class="form-group row">
                                   <div class="col-sm-4">
                                       {{__('messages.customer_replied_ticket')}}
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="cust_replied_to_ticket_app_notif" v-model="system.cust_replied_to_ticket_app_notif" value="1">
                                           <label class="cr" for="cust_replied_to_ticket_app_notif">
                                               {{__('messages.app')}}
                                           </label>
                                       </div>
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="cust_replied_to_ticket_mail_notif" v-model="system.cust_replied_to_ticket_mail_notif" value="1">
                                           <label class="cr" for="cust_replied_to_ticket_mail_notif">
                                               {{__('messages.email')}}
                                           </label>
                                       </div>
                                   </div>
                               </div>
                               <!-- Other agents replied to ticket -->
                               <div class="form-group row">
                                   <div class="col-sm-4">
                                       {{__('messages.other_agents_replied_to_ticket')}}
                                       <i class="fas fa-info-circle fa-lg text-info cursor-pointer" data-toggle="tooltip"
                                           :title="__('messages.other_agents_replied_to_ticket_tooltip')">
                                       </i>
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="other_agents_replied_to_ticket_app_notif" v-model="system.other_agents_replied_to_ticket_app_notif" value="1">
                                           <label class="cr" for="other_agents_replied_to_ticket_app_notif">
                                               {{__('messages.app')}}
                                           </label>
                                       </div>
                                   </div>
                                   <div class="col-sm-4">
                                       <div class="checkbox checkbox-fill d-inline">
                                           <input class="form-check-input" type="checkbox" id="other_agents_replied_to_ticket_mail_notif" v-model="system.other_agents_replied_to_ticket_mail_notif" value="1">
                                           <label class="cr" for="other_agents_replied_to_ticket_mail_notif">
                                               {{__('messages.email')}}
                                           </label>
                                       </div>
                                   </div>
                               </div>
                               <!-- /Other agents replied to ticket -->
							</div>
							<div class="tab-pane fade" id="v-pills-integration" role="tabpanel" aria-labelledby="v-pills-integration-tab">
								<h5>
                                   <i class="fas fa-info-circle fa-lg"></i>
                                   Google Custom Search Engine
                                   <small>
                                       <a href="https://programmablesearchengine.google.com/cse/create/new"
                                           target="_blank">
                                           {{__('messages.gcse_get_code_help_text')}}
                                       </a>
                                   </small>
                               </h5>
                               <div class="row mt-3">
                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label class="form-label" for="gcse_html_code">
                                               {{__('messages.gcse_html_code')}} 
                                               <tooltip :title="__('messages.gcse_instruction')">
                                                   <i class="fas fa-info-circle text-info fa-lg"></i>
                                               </tooltip>
                                           </label>
                                           <textarea  class="form-control" v-model="system.gcse_html" rows="2" id="gcse_html_code"></textarea>
                                       </div>
                                   </div>
                               </div>
                               <div class="row mt-3">
                                   <div class="col-md-12">
                                       <div class="form-group">
                                           <label class="form-label" for="gcse_javascript_code">
                                               {{__('messages.gcse_javascript_code')}}
                                               <tooltip :title="__('messages.gcse_instruction')">
                                                   <i class="fas fa-info-circle text-info fa-lg"></i>
                                               </tooltip>
                                           </label>
                                           <textarea  class="form-control" v-model="system.gcse_js" rows="2" id="gcse_javascript_code"></textarea>
                                       </div>
                                   </div>
                               </div>
							</div>
							<div class="tab-pane fade" id="v-pills-miscellaneous" role="tabpanel" aria-labelledby="v-pills-miscellaneous-tab">
								<h4 class="mb-3">
									{{
										__('messages.backup')
									}}
								</h4>
								<div class="row">
									<div class="col-md-12">
										<label>
											{{__('messages.default_backup_on')}}:
										</label>
										&nbsp;&nbsp;
										<div class="form-group d-inline">
											<div class="radio d-inline">
												<input type="radio" name="default_backup" id="local" v-model="system.BACKUP_DISK" value="local">
												<label for="local" class="cr">
													{{__('messages.local')}}
												</label>
											</div>
										</div>

										<div class="form-group d-inline">
											<div class="radio d-inline">
												<input type="radio" name="default_backup" id="dropbox" v-model="system.BACKUP_DISK" value="dropbox">
												<label for="dropbox" class="cr">
													{{__('messages.dropbox')}}
												</label>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12" v-if="system.BACKUP_DISK == 'dropbox'">
										<div class="form-group">
											<label for="dropbox_access_token">
												DROPBOX ACCESS TOKEN
											</label>
											<input class="form-control" type="text" placeholder="DROPBOX ACCESS TOKEN" id="dropbox_access_token" v-model="system.DROPBOX_ACCESS_TOKEN" required>
										</div>
									</div>
								</div>
								<h4 class="mt-4 mb-3">
									{{
										__('messages.landing_page')
									}}
								</h4>
								<div class="row">
									<div class="col-md-12">
										<label>
											{{__('messages.default_landing_page')}}:
										</label>
										&nbsp;&nbsp;
										<div class="form-group d-inline">
											<div class="radio d-inline">
												<input type="radio" name="default_landing" id="login" v-model="system.DEFAULT_LANDING_PAGE" value="login">
												<label for="login" class="cr">
													{{__('messages.login')}}
												</label>
											</div>
										</div>

										<div class="form-group d-inline">
											<div class="radio d-inline">
												<input type="radio" name="default_landing" id="documentation" v-model="system.DEFAULT_LANDING_PAGE" value="documentation">
												<label for="documentation" class="cr">
													{{__('messages.documentation')}}
												</label>
											</div>
										</div>
									</div>
								</div>
								<h4 class="mt-4 mb-3">
									{{
										__('messages.application')
									}}
								</h4>
								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<label for="app_tzone">
												{{__('messages.app_timezone')}}
											</label>
											<select class="form-control" id="app_tzone">
												<option v-for="(timezone, key) in timezone_list" 
													:value="key" v-text="key"></option>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="tab-pane fade" id="v-pills-support-timing" role="tabpanel" aria-labelledby="v-pills-support-timing-tab">
								<div class="row mb-2">
									<p class="mb-2 text-primary">
										<i class="fas fa-info-circle"></i>
										{{__('messages.support_timing_display_help_text')}}
									</p>
									<div class="col-md-12">
										<div class="checkbox checkbox-fill d-inline">
											<input class="form-check-input" type="checkbox" id="enable_support_timing" v-model="system.enable_support_timing" value="1">
											<label class="cr" for="enable_support_timing">
												{{__('messages.enable_support_timing')}}
											</label>
										</div>
									</div>
								</div>
								<div v-show="system.enable_support_timing">
									<div class="row">
										<div class="col-md-2">
											<b>
												{{__('messages.day')}}
											</b>
										</div>
										<div class="col-md-2">
											<b>
												{{__('messages.start_time')}}
											</b>
										</div>
										<div class="col-md-2">
											<b>
												{{__('messages.end_time')}}
											</b>
										</div>
										<div class="col-md-2">
											<b>
												{{__('messages.is_closed')}}
											</b>
										</div>
										<div class="col-md-1">
											<b>
												{{__('messages.show_the_day')}}
											</b>
										</div>
										<div class="col-md-3">
											<b>
												{{__('messages.message')}}
											</b>
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.sunday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="sunday" data-timing="start"
												:value="!_.isEmpty(support.sunday.start) ? support.sunday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="sunday" data-timing="end"
												:value="!_.isEmpty(support.sunday.end) ? support.sunday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.sunday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.sunday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.sunday.message">
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.monday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="monday" data-timing="start"
												:value="!_.isEmpty(support.monday.start) ? support.monday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="monday" data-timing="end"
												:value="!_.isEmpty(support.monday.end) ? support.monday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.monday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.monday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.monday.message">
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.tuesday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="tuesday" data-timing="start"
												:value="!_.isEmpty(support.tuesday.start) ? support.tuesday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="tuesday" data-timing="end"
												:value="!_.isEmpty(support.tuesday.end) ? support.tuesday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.tuesday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.tuesday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.tuesday.message">
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.wednesday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="wednesday" data-timing="start"
												:value="!_.isEmpty(support.wednesday.start) ? support.wednesday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="wednesday" data-timing="end"
												:value="!_.isEmpty(support.wednesday.end) ? support.wednesday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.wednesday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.wednesday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.wednesday.message">
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.thursday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="thursday" data-timing="start"
												:value="!_.isEmpty(support.thursday.start) ? support.thursday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="thursday" data-timing="end"
												:value="!_.isEmpty(support.thursday.end) ? support.thursday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.thursday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.thursday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.thursday.message">
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.friday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="friday" data-timing="start"
												:value="!_.isEmpty(support.friday.start) ? support.friday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="friday" data-timing="end"
												:value="!_.isEmpty(support.friday.end) ? support.friday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.friday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.friday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.friday.message">
										</div>
									</div>
									<div class="row mb-2">
										<div class="col-md-2">
											{{__('messages.saturday')}}
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="saturday" data-timing="start"
												:value="!_.isEmpty(support.saturday.start) ? support.saturday.start : '00:00'">
										</div>
										<div class="col-md-2">
											<input type="text" class="form-control timing" readonly
												data-day="saturday" data-timing="end"
												:value="!_.isEmpty(support.saturday.end) ? support.saturday.end : '00:00'">
										</div>
										<div class="col-md-2">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.saturday.is_closed">
											</div>
										</div>
										<div class="col-md-1">
											<div class="form-check">
												<input class="form-check-input" type="checkbox"
													v-model="support.saturday.show_day">
											</div>
										</div>
										<div class="col-md-3">
											<input type="text" class="form-control"
												v-model="support.saturday.message">
										</div>
									</div>
								</div>
							</div>
							<div class="tab-pane fade" id="v-pills-custom-fields" role="tabpanel" aria-labelledby="v-pills-custom-fields-tab">
								<div class="row">
									<div class="col-md-12">
										<p class="mb-2 text-primary">
                                           <i class="fas fa-info-circle"></i>
                                           {{__('messages.enable_disable_custom_field_help_text')}}
                                       </p> 
									</div>
								</div>
								<div class="row mb-2">
									<div class="col-md-2">
										<strong>
											{{__('messages.label')}}
										</strong>
									</div>
									<div class="col-md-3">
										<strong>
											{{__('messages.custom_field_products')}}
										</strong>
									</div>
									<div class="col-md-2">
										<strong>
											{{__('messages.departments')}}
										</strong>
									</div>
									<div class="col-md-1 text-center">
										<strong>
											{{__('messages.is_required')}}
										</strong>
									</div>
									<div class="col-md-2">
										<strong>
											{{__('messages.field_type')}}
										</strong>
									</div>
									<div class="col-md-2">
										<strong>
											{{ __('messages.filled_by') }}
										</strong>
									</div>
								</div>
								<div class="row mb-2"
									v-for="(custom_field, key) in custom_fields">
									<div class="col-md-2">
										<input type="text" class="form-control"
											:placeholder="__(`messages.${key}`)"
											v-model="custom_field.label">
									</div>
									<div class="col-md-3">
										<v-select class="form-input"
											:name="`product_${key}`" :id="`product_${key}`"
											multiple
											v-model="custom_field.products"
											:reduce="product => product.id"
											:options="products"
											label="name"
											:placeholder="__('messages.please_select')">
										</v-select>
									</div>
									<div class="col-md-2">
										<v-select class="form-input"
											:name="`department_${key}`" :id="`department_${key}`"
											multiple
											v-model="custom_field.departments"
											:reduce="department => department.id"
											:options="departments"
											label="name"
											:placeholder="__('messages.please_select')">
										</v-select>
									</div>
									<div class="col-md-1 text-center">
										<div class="checkbox checkbox-fill d-inline">
											<input class="form-check-input" type="checkbox"
												v-model="custom_field.is_required"
												:id="`custom_field_checkbox_${key}`">
											<label :for="`custom_field_checkbox_${key}`" class="cr">
											</label>
										</div>
									</div>
									<div class="col-md-2">
										<select class="form-control"
											v-model="custom_field.type">
											<option v-for="(name, key) in field_types" 
												:value="key" v-text="name"></option>
										</select>
									</div>
									<div class="col-md-2">
										<div class="form-check">
											<input class="form-check-input" type="radio" :name="key" 
											:id="`customer_${key}`" value="customer" v-model="custom_field.filled_by">
											<label class="form-check-label" :for="`customer_${key}`">
												{{__('messages.customer')}}
												<i data-toggle="tooltip"
													:title="__('messages.filled_by_customer_help_text')"
													class="fas fa-info-circle text-info cursor-pointer">
												</i>
											</label>
										</div>
										<div class="form-check">
											<input class="form-check-input" type="radio" :name="key" 
											:id="`agent_${key}`" value="support_agent" v-model="custom_field.filled_by">
											<label class="form-check-label" :for="`agent_${key}`">
												{{__('messages.support_agent')}}
												<i data-toggle="tooltip"
													:title="__('messages.filled_by_support_agent_help_text')"
													class="fas fa-info-circle text-info cursor-pointer">
												</i>
											</label>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row mt-3">
                   <div class="col-md-12">
                       <loading-button :loading="submitting" class="btn btn-lg btn-success float-right" type="submit">
                           {{__('messages.update')}}
                       </loading-button>
                   </div>
               </div>
			</form>
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
		props: ['settings', 'timezone_list', 'products', 'departments'],
  		data: function () {
			const self = this;
  			return {
  				system:{
  					ticket_prefix: '',
  					cust_new_ticket_app_notif: 0,
  					cust_new_ticket_mail_notif: 0,
  					agent_replied_to_ticket_app_notif: 0,
  					agent_replied_to_ticket_mail_notif: 0,
  					agent_assigned_ticket_app_notif: 0,
  					agent_assigned_ticket_mail_notif: 0,
  					cust_replied_to_ticket_app_notif: 0,
  					cust_replied_to_ticket_mail_notif: 0,
  					is_public_ticket_enabled: 0,
					other_agents_replied_to_ticket_app_notif : 0,
					other_agents_replied_to_ticket_mail_notif : 0,
  					default_ticket_type: 'private',
  					auto_close_ticket_in_days: 0,
  					BACKUP_DISK: 'local',
  					DROPBOX_ACCESS_TOKEN: '',
  					gcse_html: '',
  					gcse_js: '',
  					signature: '',
					DEFAULT_LANDING_PAGE: '',
					APP_TIMEZONE: '',
					enable_support_timing: false,
					remind_ticket_in_days: 0,
					ticket_reminder_mail_template: {
						subject: '',
						body: ''
					}
  				},
  				support:{
  					sunday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					},
  					monday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					},
  					tuesday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					},
  					wednesday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					},
  					thursday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					},
  					friday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					},
  					saturday: {
  						start: null,
  						end: null,
  						is_closed: false,
  						show_day: true,
  						message: null
  					}
				},
				custom_fields: {
					custom_field_1:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_2:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_3:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_4:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_5:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_6:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_7:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_8:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_9:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					},
					custom_field_10:{
						label: '',
						products:[],
						departments: [],
						is_required : false,
						type : 'text',
						filled_by: 'customer'
					}
				},
  				error_message:null,
  				submitting: false,
				field_types:{
					'text' : self.__('messages.text'),
					'textarea' : self.__('messages.textarea'),
					'date' : self.__('messages.date'),
					'datetime-local' : self.__('messages.datetime_local'),
					'email' : self.__('messages.email'),
					'url' : self.__('messages.url'),
					'number' : self.__('messages.number')
				}
  			}
  		},
  		created() {
  			const self = this;
  			$(function() {
		    	//if ticket instruction editor exist destory & re-initialize it
		    	if (!_.isNull(tinymce.get('ticket_instruction'))) {
  					tinymce.remove("textarea#ticket_instruction");
				}
				//initialize editor
				tinymce.init({
				    selector: 'textarea#ticket_instruction',
				});

				tinymce.get("ticket_instruction").setContent(self.settings.ticket_instruction);

				//if signature editor exist destory & re-initialize it
		    	if (!_.isNull(tinymce.get('signature'))) {
  					tinymce.remove("textarea#signature");
				}
				//initialize editor
				tinymce.init({
				    selector: 'textarea#signature',
				    height: 250,
					theme: 'silver',
					plugins: [
					'paste link autolink lists hr anchor pagebreak textcolor image'
					],
					toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify |' +
					' bullist numlist outdent indent | link image | forecolor backcolor textcolor',
					menubar: 'edit insert',
					/* enable title field in the Image dialog*/
					image_title: true,
					/* enable automatic uploads of images represented by blob or data URIs*/
					automatic_uploads: true,
					/*
					URL of our upload handler (for more details check: https://www.tiny.cloud/docs/configure/file-image-upload/#images_upload_url)
					images_upload_url: 'postAcceptor.php',
					here we add custom filepicker only to Image dialog
					*/
					file_picker_types: 'image',
					/* and here's our custom image picker*/
					file_picker_callback: function (cb, value, meta) {
					var input = document.createElement('input');
					input.setAttribute('type', 'file');
					input.setAttribute('accept', 'image/*');

					/*
					  Note: In modern browsers input[type="file"] is functional without
					  even adding it to the DOM, but that might not be the case in some older
					  or quirky browsers like IE, so you might want to add it to the DOM
					  just in case, and visually hide it. And do not forget do remove it
					  once you do not need it anymore.
					*/

					input.onchange = function () {
					  var file = this.files[0];

					  var reader = new FileReader();
					  reader.onload = function () {
					    /*
					      Note: Now we need to register the blob in TinyMCEs image blob
					      registry. In the next release this part hopefully won't be
					      necessary, as we are looking to handle it internally.
					    */
					    var id = 'blobid' + (new Date()).getTime();
					    var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
					    var base64 = reader.result.split(',')[1];
					    var blobInfo = blobCache.create(id, file, base64);
					    blobCache.add(blobInfo);

					    /* call the callback and populate the Title field with the file name */
					    cb(blobInfo.blobUri(), { title: file.name });
					  };
					  reader.readAsDataURL(file);
					};

					input.click();
					},
				});

				if (!_.isEmpty(self.settings.signature)) {
					tinymce.get("signature").setContent(self.settings.signature);
				}

				$('#app_tzone').select2({
						allowClear: true
					})
					.val(self.system.APP_TIMEZONE).trigger('change')
					.on("change", function (e) {
					self.system.APP_TIMEZONE = $("#app_tzone").val();
				});

				$('.timing').daterangepicker({
				    timePicker : true,
		            singleDatePicker:true,
		            timePicker24Hour : true,
		            timePickerIncrement : 1,
		            timePickerSeconds : false,
		            locale : {
		                format : 'HH:mm'
		            }
				}).on('show.daterangepicker', function (ev, picker) {
		            picker.container.find(".calendar-table").hide();
		        }).on('hide.daterangepicker', function(ev, picker){
		        	let day = $(this).data('day');
		        	let timing = $(this).data('timing');
		        	self.support[day][timing] = $(this).val();
		        });

				//initialize editor for ticket reminder template body
				tinymce.init({
				    selector: 'textarea#tc_body_template',
				});
		    });
  			self.setInputDatas();
  		},
  		methods:{
			submitForm(){
				const self = this;
				if(tinymce.get("ticket_instruction").getContent().length <= 0){
					self.error_message = self.__('messages.ticket_instruction_required');
					return false;
				} else {
					self.error_message = null;
				}

				let data = _.pick(self.system, ['ticket_prefix', 'cust_new_ticket_app_notif',
								'cust_new_ticket_mail_notif', 'agent_replied_to_ticket_app_notif',
								'agent_replied_to_ticket_mail_notif', 'agent_assigned_ticket_app_notif',
								'agent_assigned_ticket_mail_notif', 'cust_replied_to_ticket_app_notif',
								'cust_replied_to_ticket_mail_notif', 'is_public_ticket_enabled',
								'default_ticket_type', 'auto_close_ticket_in_days', 'BACKUP_DISK',
								'DROPBOX_ACCESS_TOKEN', 'gcse_html', 'gcse_js', 'DEFAULT_LANDING_PAGE',
								'APP_TIMEZONE', 'enable_support_timing', 'other_agents_replied_to_ticket_app_notif', 'other_agents_replied_to_ticket_mail_notif', 'ticket_reminder_mail_template', 'remind_ticket_in_days']);

				data.ticket_instruction = tinymce.get("ticket_instruction").getContent();
				data.signature = tinymce.get("signature").getContent();
				data.ticket_reminder_mail_template.body = tinymce.get("tc_body_template").getContent();
				data.support_timing = self.support;
				self.submitting = true;
				data.custom_fields = self.custom_fields;
				
				self.$inertia.post(this.route_ziggy('settings.store'), data)
                .then(function(response){
                	self.submitting = false;
                });
            },
            setInputDatas() {
            	const self = this;
            	self.system.ticket_prefix = self.settings.ticket_prefix;
	  			self.system.cust_new_ticket_app_notif = parseInt(self.settings.cust_new_ticket_app_notif);
	  			self.system.cust_new_ticket_mail_notif = parseInt(self.settings.cust_new_ticket_mail_notif);
	  			self.system.agent_replied_to_ticket_app_notif = parseInt(self.settings.agent_replied_to_ticket_app_notif);
	  			self.system.agent_replied_to_ticket_mail_notif = parseInt(self.settings.agent_replied_to_ticket_mail_notif);
	  			self.system.agent_assigned_ticket_app_notif = parseInt(self.settings.agent_assigned_ticket_app_notif);
	  			self.system.agent_assigned_ticket_mail_notif = parseInt(self.settings.agent_assigned_ticket_mail_notif);
	  			self.system.cust_replied_to_ticket_app_notif = parseInt(self.settings.cust_replied_to_ticket_app_notif);
	  			self.system.cust_replied_to_ticket_mail_notif = parseInt(self.settings.cust_replied_to_ticket_mail_notif);
	  			self.system.is_public_ticket_enabled = parseInt(self.settings.is_public_ticket_enabled);
	  			self.system.auto_close_ticket_in_days = parseInt(self.settings.auto_close_ticket_in_days);
	  			self.system.default_ticket_type = self.settings.default_ticket_type;
	  			self.system.BACKUP_DISK = self.settings.BACKUP_DISK;
	  			self.system.DROPBOX_ACCESS_TOKEN = self.settings.DROPBOX_ACCESS_TOKEN;
	  			self.system.gcse_html = self.settings.gcse_html;
	  			self.system.gcse_js = self.settings.gcse_js;
				self.system.DEFAULT_LANDING_PAGE = self.settings.DEFAULT_LANDING_PAGE;
				self.system.APP_TIMEZONE = self.settings.APP_TIMEZONE;
				self.system.enable_support_timing = parseInt(self.settings.enable_support_timing);
				self.system.other_agents_replied_to_ticket_app_notif = parseInt(self.settings?.other_agents_replied_to_ticket_app_notif || 0);
				self.system.other_agents_replied_to_ticket_mail_notif = parseInt(self.settings?.other_agents_replied_to_ticket_mail_notif || 0);
				self.custom_fields = _.isEmpty(self.settings.custom_fields) ? self.custom_fields : self.settings.custom_fields;
				self.system.remind_ticket_in_days = parseInt(self.settings.remind_ticket_in_days);
				self.system.ticket_reminder_mail_template.subject = self.settings.ticket_reminder_mail_template.subject;
				self.system.ticket_reminder_mail_template.body = self.settings.ticket_reminder_mail_template.body;

				//support timing
				if(self.settings.support_timing) {
					for (let day in self.settings.support_timing) {
					  self.support[day]['start'] = self.settings.support_timing[day]['start'];
					  self.support[day]['end'] = self.settings.support_timing[day]['end'];
					  self.support[day]['is_closed'] = self.settings.support_timing[day]['is_closed'];
					  self.support[day]['show_day'] = _.isUndefined(self.settings.support_timing[day]['show_day']) ? true : self.settings.support_timing[day]['show_day'];
					  self.support[day]['message'] = self.settings.support_timing[day]['message'];
					}
				}
            }
		}
	}
</script>