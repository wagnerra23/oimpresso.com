<template>
    <layout :title="__('messages.completed_tickets')">
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
           <h3 class="m-b-10">
               {{__('messages.completed_tickets')}}
           </h3>
       </div>
       <div class="page-wrapper">
            <div class="accordion" id="searchAccordion">
                <div class="card">
                    <div class="card-header" id="filterHeading">
                        <h5 class="mb-0">
                            <a href="#!" data-toggle="collapse" data-target="#filter" aria-expanded="false" aria-controls="filter" class="collapsed">
                                {{__('messages.filters')}}
                            </a>
                        </h5>
                    </div>
                    <div id="filter" class="card-body collapse" aria-labelledby="filterHeading" data-parent="#searchAccordion">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="date_range_picker">
                                        {{__('messages.last_update_date_range')}}
                                    </label>
                                    
                                    <div class="input-group mb-2">
                                        <input type="text" id="date_range_picker" class="form-control" name="daterange" readonly />
                                        <div class="input-group-prepend">
                                                <div class="input-group-text" :title="__('messages.clear')">
                                                    <i class="fas fa-times cursor-pointer text-danger" @click="resetDateRange"></i>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filter_product">
                                        {{__('messages.product')}}
                                    </label>
                                    <select id="filter_product" class="form-control" name="product" v-model="form.product">
                                        <option value="">
                                            {{__('messages.all')}}
                                        </option>
                                        <option v-for="(product, key) in filterProducts" :value="key" v-text="product"></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="last_replied_by">
                                        {{__('messages.last_replied_by')}}
                                    </label>
                                    <select class="form-control" id="last_replied_by"
                                        v-model="form.last_replied_by">
                                        <option value="">
                                            {{__('messages.all')}}
                                        </option>
                                        <option value="customer">
                                            {{__('messages.customer')}}
                                        </option>
                                        <option value="support_agent">
                                            {{__('messages.support_agent')}}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="filterLabels.length > 0">
                                <div class="form-group">
                                    <label for="filter_label">
                                        {{__('messages.label')}}
                                    </label>
                                    <select class="form-control" id="filter_label"
                                        v-model="form.label">
                                        <option value="">
                                            {{__('messages.all')}}
                                        </option>
                                        <option v-for="filterLabel in filterLabels" :value="filterLabel" v-text="filterLabel"></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4"
                                v-if="_.includes(['admin'], $page.auth.user.role)">
                                <div class="form-group">
                                    <label for="support_agent">
                                        {{__('messages.support_agent')}}
                                    </label>
                                    <select class="form-control" id="support_agent"
                                        v-model="form.support_agent">
                                        <option value="">
                                            {{__('messages.all')}}
                                        </option>
                                        <option v-for="(agent, id) in agents" :value="id" v-text="agent"></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="!_.isEmpty(productDepartments)">
                                <div class="form-group">
                                    <label for="department_label">
                                        {{__('messages.department')}}
                                    </label>
                                    <select class="form-control p_department_select2" id="department_label"
                                        v-model="form.p_department">
                                        <option value="">
                                            {{__('messages.all')}}
                                        </option>
                                        <option value="none">
                                            {{__('messages.none')}}
                                        </option>
                                        <option v-for="(department, id) in productDepartments" :value="id" v-text="department"></option>
                                    </select>
                                </div>
                            </div>
                            <!-- closed by -->
                            <div class="col-md-4"
                                v-if="_.includes(['admin'], $page.auth.user.role)">
                                <div class="form-group">
                                    <label for="closed_by">
                                        {{__('messages.closed_by')}}
                                    </label>
                                    <select class="form-control" id="closed_by"
                                        v-model="form.closed_by">
                                        <option value="">
                                            {{__('messages.all')}}
                                        </option>
                                        <option v-for="(agent, id) in agents" :value="id" v-text="agent"></option>
                                    </select>
                                    <small class="form-text text-muted"
                                    v-html="__('messages.please_select_closed_status')"></small>
                                </div>
                            </div>
                            <!-- /closed by -->
                            <!-- closed on -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="closed_on_daterange">
                                        {{__('messages.closed_on')}}
                                    </label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="closed_on_daterange" class="form-control"
                                            name="closed_on_daterange" readonly />
                                        <div class="input-group-prepend">
                                                <div class="input-group-text" :title="__('messages.clear')">
                                                    <i class="fas fa-times cursor-pointer text-danger" @click="resetClosedOnDateRange"></i>
                                                </div>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted"
                                        v-html="__('messages.please_select_closed_status')"></small>
                                </div>
                            </div>
                            <!-- /closed on -->
                        </div>
                    </div>
                </div>
            </div>
           <div class="row">
               <div class="col-md-12">
                   <div class="card code-table">
                       <div class="card-header">
                           <h5>
                               {{__('messages.all_tickets')}}
                           </h5>
                           <a v-if="_.includes(['admin'], $page.auth.user.role)"
                               target="_blank"
                               :href="route_ziggy('export.tickets', _.pickBy(form))" class="btn float-right btn-sm btn-outline-primary">
                               <i class="far fa-file-excel"></i>
                               {{__('messages.export_to_excel')}}
                           </a>
                       </div>
                       <div class="card-body">
                           <div class="row">
                               <div class="col-sm-3">
                                   <span class="hljs-strong text-dark">
                                       {{__('messages.subject')}}
                                   </span>
                               </div>
                               <div class="col-sm-2">
                                   <span class="hljs-strong text-dark">
                                       {{__('messages.product')}}
                                   </span>
                               </div>
                               <div class="col-sm">
                                   <span class="hljs-strong text-dark"
                                        v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_1']) && !_.isEmpty($page.custom_fields['custom_field_1']['label'])">
                                       {{ $page.custom_fields['custom_field_1']['label'] }}
                                   </span>
                                   <span class="hljs-strong text-dark"
                                        v-else>
                                       {{__('messages.custom_field_1')}}
                                   </span>
                               </div>
                               <div class="col-sm">
                                    <span class="hljs-strong text-dark"
                                        v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_2']) && !_.isEmpty($page.custom_fields['custom_field_2']['label'])">
                                       {{ $page.custom_fields['custom_field_2']['label'] }}
                                   </span>
                                   <span class="hljs-strong text-dark"
                                        v-else>
                                       {{__('messages.custom_field_2')}}
                                   </span>
                               </div>
                               <div class="col-sm-2" v-if="_.includes(['admin'], $page.auth.user.role)">
                                   <span class="hljs-strong text-dark">
                                       {{__('messages.support_agents')}}
                                   </span>
                               </div>
                               <div class="col-sm">
                                   <span class="hljs-strong text-dark">
                                       {{__('messages.closed_on')}}
                                   </span>
                               </div>
                               <div class="col-sm">
                                   <span class="hljs-strong text-dark">
                                       {{__('messages.closed_by')}}
                                   </span>
                               </div>
                               <div class="col-sm">
                                   <i class="fas fa-ellipsis-v text-dark"></i>
                               </div>
                           </div>
                           <hr>
                           <template v-if="tickets.data.length" v-for="ticket in tickets.data">
                               <div class="row">
                                   <div class="col-sm-3 cursor-pointer" @click="viewTicket(ticket.id)">
                                       <div class="m--font-bolder d-flex align-items-center">
                                           <div class="no-wrap d-inline-block max-width-250" :title="ticket.subject">
                                               {{ticket.subject}}
                                           </div>
                                           <span class="text-muted ml-2" :title="__('messages.ref_num')">
                                               {{ticket.ticket_ref}}
                                           </span>
                                       </div>
                                       <div class="align-items-baseline">
                                           <span v-if="ticket.is_public" :title="__('messages.public_ticket')" class="mr-1">
                                               <i class="fas fa-unlock text-success fa-lg"></i>
                                           </span>
                                           <span :title="__('messages.status')">
                                               <span class="badge"
                                                   :class="badgeForTicketStatus(ticket.status)">
                                                   {{
                                                       __('messages.'+ticket.status)
                                                   }}
                                               </span>
                                           </span>
                                           <span class="ml-1" :title="__('messages.priority')">
                                               <span class="badge"
                                                   :class="badgeForTicketPriority(ticket.priority)">
                                                   {{
                                                       __('messages.'+ticket.priority)
                                                   }}
                                               </span>
                                           </span>
                                           <span class="ml-1" :title="__('messages.customer')">
                                               <span class="badge badge-success" v-if="ticket.user && ticket.user.name">
                                                   {{ticket.user.name}}
                                               </span>
                                           </span>
                                           <span class="badge badge-dark" :title="__('messages.comments')">
                                               <i class="far fa-comments"></i>
                                               {{ticket.comments_count}}
                                           </span>
                                           <span v-if="ticket.labels.length > 0"
                                               class="ml-1 badge badge-secondary"
                                               v-for="label in ticket.labels" :title="__('messages.label')">
                                               {{label}}
                                           </span>
                                           <span class="ml-2"
                                               v-if="!_.isEmpty(ticket.product_department) && !_.isEmpty(ticket.product_department.department)"
                                               :title="__('messages.department')">
                                               <span class="badge badge-secondary">
                                                   <i class="far fa-building"></i>
                                                   {{ticket.product_department.department.name}}
                                               </span>
                                           </span>
                                           <display-custom-fields
                                               :ticket="ticket">
                                           </display-custom-fields>
                                       </div>
                                   </div>
                                   <div class="col-sm-2 cursor-pointer" @click="viewTicket(ticket.id)">
                                       <span v-if="ticket.product && ticket.product.name" v-text="ticket.product.name">
                                       </span>
                                   </div>
                                   <div class="col-sm cursor-pointer">
                                        <a v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_1']) && _.includes(['url'], $page.custom_fields['custom_field_1']['type'])"
                                            :href="ticket.custom_field_1"
                                            target="_blank">
                                            <small>
                                                {{ticket.custom_field_1}}
                                            </small>
                                        </a>
                                        <a v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_1']) && _.includes(['email'], $page.custom_fields['custom_field_1']['type'])"
                                            :href="`mailto:${ticket.custom_field_1}`"
                                            target="_blank">
                                            <small>
                                                {{ticket.custom_field_1}}
                                            </small>
                                        </a>
                                        <span
                                            v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_1']) && !_.includes(['email', 'url'], $page.custom_fields['custom_field_1']['type'])">
                                            {{ticket.custom_field_1}}
                                        </span>
                                   </div>
                                   <div class="col-sm cursor-pointer">
                                        <a v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_2']) && _.includes(['url'], $page.custom_fields['custom_field_2']['type'])"
                                            :href="ticket.custom_field_2"
                                            target="_blank">
                                            <small>
                                                {{ticket.custom_field_2}}
                                            </small>
                                        </a>
                                        <a v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_2']) && _.includes(['email'], $page.custom_fields['custom_field_2']['type'])"
                                            :href="`mailto:${ticket.custom_field_2}`"
                                            target="_blank">
                                            <small>
                                                {{ticket.custom_field_2}}
                                            </small>
                                        </a>
                                        <span
                                            v-if="!_.isEmpty($page.custom_fields) && !_.isUndefined($page.custom_fields['custom_field_2']) && !_.includes(['email', 'url'], $page.custom_fields['custom_field_2']['type'])">
                                            {{ticket.custom_field_2}}
                                        </span>
                                   </div>
                                   <div class="col-sm-2 cursor-pointer" @click="viewTicket(ticket.id)" v-if="_.includes(['admin'], $page.auth.user.role)">
                                       {{listSupportAgents(ticket.support_agents)}}
                                   </div>
                                   <div class="col-sm cursor-pointer" @click="viewTicket(ticket.id)">
                                       <span v-if="_.includes(['closed'], ticket.status) && ticket.closed_on">
                                           {{$commonFunction.formatDateTime(ticket.closed_on)}}
                                       </span>
                                   </div>
                                   <div class="col-sm cursor-pointer" @click="viewTicket(ticket.id)">
                                       <span v-if="_.includes(['closed'], ticket.status) && ticket.closed_by && ticket.closed_by.name">
                                           {{ticket.closed_by.name}}
                                       </span>
                                   </div>
                                   <div class="col-sm">
                                       <inertia-link :href="route_ziggy('customer.view-ticket', [ticket.id])" class="cursor-pointer">
                                           <i class="far fa-eye"></i>
                                       </inertia-link>
                                   </div>
                               </div>
                               <hr>
                           </template>
                           <div class="row" v-if="tickets.data.length === 0">
                               <div class="col-sm">
                                   <div class="alert alert-info" role="alert">
                                       <h4 class="text-muted">
                                           {{__('messages.no_data_found')}}
                                       </h4>
                                   </div>
                               </div>
                           </div>
                           <Pagination :url-props="form" :pagination="tickets" :url="route_ziggy('reports.completed.tickets')">
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
   import Leftnav from '@/Pages/Elements/Leftnav';
   import Pagination from '@/Shared/Pagination';
   import LoadingButton from '@/Shared/LoadingButton';
   import SearchFilter from '@/Shared/SearchFilter';
   import Tooltip from '@/Shared/Tooltip';
    import DisplayCustomFields from '../Ticket/Shared/CustomFieldsView.vue';
   export default {
       components: {
           Layout,
           Leftnav,
           Pagination,
           LoadingButton,
           SearchFilter,
           Tooltip,
           DisplayCustomFields
       },
       props: {
           tickets: Object,
           filters: Object,
           filterProducts: Object,
           filterLabels: Array,
           productDepartments: [Object, Array],
           agents: Object
         },
         data: function () {
           return {
               support_agents: [],
               ticket_ids: [],
               statuses:[],
               priorities:[],
               submitting: false,
               form: {
                   status: ['closed'],
                   start_date: this.filters.start_date,
                   end_date: this.filters.end_date,
                   product: this.filters.product,
                   last_replied_by: this.filters.last_replied_by,
                   label: this.filters.label,
                   p_department: this.filters.p_department,
                   support_agent: this.filters.support_agent,
                   closed_by: this.filters.closed_by,
                   closed_on_start_date: this.filters.closed_on_start_date,
                   closed_on_end_date: this.filters.closed_on_end_date,
               },
               announcements:[],
               labels:[],
               product_departments: []
           }
         },
         watch: {
           form: {
               handler: _.throttle(function() {
                   let query = _.pickBy(this.form);
                   //store filter params in session storage
                   let filterParams = _.pickBy(this.form);
                   sessionStorage.setItem('closedTicketFilterParams', JSON.stringify(filterParams));
                   this.$inertia.replace(this.route_ziggy('reports.completed.tickets', query))
               }, 200),
               deep: true,
           },
       },
       mounted() {
           const self = this;

           //get & set scrollYPosition
           if (!_.isNull(sessionStorage.getItem('ScrollYPosition'))) {
               var ScrollYPosition = JSON.parse(sessionStorage.getItem('ScrollYPosition'));
               document.documentElement.scrollTop = document.body.scrollTop = ScrollYPosition;
           }

           $(function () {
               //initialize date range picker
               $('#date_range_picker').daterangepicker({
                   ranges: {
                      'Today': [moment(), moment()],
                      'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                      'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                      'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                      'This Month': [moment().startOf('month'), moment().endOf('month')],
                      'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                   },
                   locale: {
                       cancelLabel: self.__('messages.clear')
                   }
               }).on('apply.daterangepicker', function(ev, picker) {

                     self.form.start_date = picker.startDate.format('YYYY-MM-DD');
                   self.form.end_date = picker.endDate.format('YYYY-MM-DD');

               }).on('hide.daterangepicker', function(ev, picker) {

                     self.form.start_date = picker.startDate.format('YYYY-MM-DD');
                   self.form.end_date = picker.endDate.format('YYYY-MM-DD');
                   
               }).on('cancel.daterangepicker', function(ev, picker) {
                   $(this).val('');
                   self.form.start_date = null;
                   self.form.end_date = null;
               });

               //if start & end date exist set it
               if (!_. isEmpty(self.filters.start_date) && !_. isEmpty(self.filters.end_date)) {
                   $("#date_range_picker").data('daterangepicker').setStartDate(moment(self.filters.start_date));
                   $("#date_range_picker").data('daterangepicker').setEndDate(moment(self.filters.end_date));
               } else {
                   $("#date_range_picker").val('');
               }
               
               $('.p_department_select2').select2()
                   .val(self.form.p_department).trigger('change')
                   .on("change", function (e) {
                       self.form.p_department = $(".p_department_select2").val();
                   });
               
               //initialize closed on date range picker
               $('#closed_on_daterange').daterangepicker({
                   ranges: {
                       'Today': [moment(), moment()],
                       'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                       'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                       'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                       'This Month': [moment().startOf('month'), moment().endOf('month')],
                       'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                   },
                   locale: {
                       cancelLabel: self.__('messages.clear')
                   }
               }).on('apply.daterangepicker', function(ev, picker) {
                   self.form.closed_on_start_date = picker.startDate.format('YYYY-MM-DD');
                   self.form.closed_on_end_date = picker.endDate.format('YYYY-MM-DD');
               }).on('hide.daterangepicker', function(ev, picker) {
                   self.form.closed_on_start_date = picker.startDate.format('YYYY-MM-DD');
                   self.form.closed_on_end_date = picker.endDate.format('YYYY-MM-DD');
               }).on('cancel.daterangepicker', function(ev, picker) {
                   $(this).val('');
                   self.form.closed_on_start_date = null;
                   self.form.closed_on_end_date = null;
               });

               //if start & end date exist set it
               if (!_. isEmpty(self.filters.closed_on_start_date) && !_. isEmpty(self.filters.closed_on_end_date)) {
                   $("#closed_on_daterange").data('daterangepicker').setStartDate(moment(self.filters.closed_on_start_date));
                   $("#closed_on_daterange").data('daterangepicker').setEndDate(moment(self.filters.closed_on_end_date));
               } else {
                   $("#closed_on_daterange").val('');
               }
           });
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
           viewTicket(id) {

               //get & store ScrollYPosition
               var ScrollYPosition = window.pageYOffset || document.documentElement.scrollTop;
               sessionStorage.setItem('ScrollYPosition', JSON.stringify(ScrollYPosition));

               this.$inertia.visit(this.route_ziggy('customer.view-ticket', [id]))
               .then(function(response){
                   console.log(response);
               });
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
           },
           resetDateRange() {
               const self = this;
               $("#date_range_picker").val('');
               self.form.start_date = null;
               self.form.end_date = null;
           },
           resetClosedOnDateRange() {
               const self = this;
               $("#closed_on_daterange").val('');
               self.form.closed_on_start_date = null;
               self.form.closed_on_end_date = null;
           }
       }
   }
</script>