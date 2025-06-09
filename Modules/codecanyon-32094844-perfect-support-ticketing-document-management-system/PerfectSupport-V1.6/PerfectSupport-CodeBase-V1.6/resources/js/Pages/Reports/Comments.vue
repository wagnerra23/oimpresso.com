<template>
    <layout :title="__('messages.comments')">
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
               {{__('messages.comments')}}
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
                                        {{__('messages.replied_at')}}
                                    </label>
                                    
                                    <div class="input-group mb-2">
                                        <input type="text" id="date_range_picker" class="form-control" name="daterange" readonly />
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
                        </div>
                    </div>
                </div>
            </div>
           <div class="row">
               <div class="col-md-12">
                   <div class="card code-table">
                       <div class="card-header">
                           <h5>
                               {{__('messages.all_comments')}}
                           </h5>
                       </div>
                       <div class="card-body">
                           <div class="row">
                                <div class="col-sm">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.ticket_ref')}}
                                    </span>
                                </div>
                                <div class="col-sm-5">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.subject')}}
                                    </span>
                                </div>
                                <div class="col-sm">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.replied_at')}}
                                    </span>
                                </div>
                                <div class="col-sm">
                                    <span class="hljs-strong text-dark">
                                        {{__('messages.replied_by')}}
                                    </span>
                                </div>
                                <div class="col-sm">
                                    <i class="fas fa-ellipsis-v text-dark"></i>
                                </div>
                           </div>
                           <hr>
                           <template v-if="comments.data.length" v-for="comment in comments.data">
                               <div class="row">
                                    <div class="col-sm cursor-pointer"
                                        @click="viewTicket(comment.ticket_id)">
                                        <span class="text-muted ml-2" :title="__('messages.ref_num')">
                                            {{comment.ticket_ref}}
                                        </span>
                                    </div>
                                    <div class="col-sm-5">
                                        {{comment.subject}}
                                    </div>
                                    <div class="col-sm">
                                        {{$commonFunction.formatDateTime(comment.last_updated_at)}}
                                    </div>
                                    <div class="col-sm">
                                        <span v-if="comment.replied_by">
                                            {{comment.replied_by}}
                                        </span>
                                    </div>
                                    <div class="col-sm">
                                        <i class="far fa-eye cursor-pointer fa-lg"
                                            @click="viewComment(comment.id)"></i>
                                    </div>
                               </div>
                               <hr>
                           </template>
                           <div class="row" v-if="comments.data.length === 0">
                               <div class="col-sm">
                                   <div class="alert alert-info" role="alert">
                                       <h4 class="text-muted">
                                           {{__('messages.no_data_found')}}
                                       </h4>
                                   </div>
                               </div>
                           </div>
                           <Pagination :url-props="form" :pagination="comments" :url="route_ziggy('reports.comments')">
                           </Pagination>
                       </div>
                   </div>
               </div>
           </div>
           <show-comment-modal :comment="comment"></show-comment-modal>
       </div>
     </layout>
</template>

<script>
   import Layout from '@/Shared/Layout';
   import Leftnav from '@/Pages/Elements/Leftnav';
   import Pagination from '@/Shared/Pagination';
   import SearchFilter from '@/Shared/SearchFilter';
   import ShowCommentModal from "./ShowCommentModal.vue"
   export default {
       components: {
           Layout,
           Leftnav,
           Pagination,
           SearchFilter,
           ShowCommentModal
       },
       props: {
           comments: Object,
           filters: Object,
           filterProducts: Object,
           agents: Object
         },
         data: function () {
           return {
               form: {
                   start_date: this.filters.start_date,
                   end_date: this.filters.end_date,
                   product: this.filters.product,
                   support_agent: this.filters.support_agent
               },
               announcements:[],
               comment: ''
           }
         },
         watch: {
           form: {
               handler: _.throttle(function() {
                   let query = _.pickBy(this.form);
                   //store filter params in session storage
                   let filterParams = _.pickBy(this.form);
                   sessionStorage.setItem('commentsFilterParams', JSON.stringify(filterParams));
                   this.$inertia.replace(this.route_ziggy('reports.comments', query))
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
                   }
               }).on('apply.daterangepicker', function(ev, picker) {
                    self.form.start_date = picker.startDate.format('YYYY-MM-DD');
                    self.form.end_date = picker.endDate.format('YYYY-MM-DD');
               }).on('hide.daterangepicker', function(ev, picker) {
                    self.form.start_date = picker.startDate.format('YYYY-MM-DD');
                    self.form.end_date = picker.endDate.format('YYYY-MM-DD');
               });

               //if start & end date exist set it
               if (!_. isEmpty(self.filters.start_date) && !_. isEmpty(self.filters.end_date)) {
                   $("#date_range_picker").data('daterangepicker').setStartDate(moment(self.filters.start_date));
                   $("#date_range_picker").data('daterangepicker').setEndDate(moment(self.filters.end_date));
               }
           });
       },
       created() {
           this.getAnnouncements();
       },
         methods:{
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
           viewComment(id) {
                const self = this;
                axios.get(this.route_ziggy('customer.ticket-comments.show', {ticket_comment:id}).url())
                .then(function (response) {
                    if (response.data.success) {
                        self.comment = response.data.comment;
                        $('#comment_modal').modal('show');
                    }
                })
                .catch(function (error) {
                    console.log(error);
                });
           }
       }
   }
</script>