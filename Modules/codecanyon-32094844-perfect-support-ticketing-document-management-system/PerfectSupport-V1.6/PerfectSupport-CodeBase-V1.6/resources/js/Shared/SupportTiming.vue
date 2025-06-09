<template>
	<div v-if="$page.is_enabled_support_timing">
		<p class="text-danger d-flex justify-content-end mb-0" v-if="getSupportMessageForTheDay()">
			{{getSupportMessageForTheDay()}}
		</p>
		<button type="button" class="btn" :class="classes" data-toggle="popover"  data-container="body" data-content="" data-html="true" id="support_time_popover" data-trigger="focus">
	        <span v-html="getCurrentDayAndTime()"></span>
	    </button>
	    <div id="support-popover-content" class="hide">
	        <div class="row">
	            <div class="col-md-4">
	                <b>
	                    {{__('messages.day')}}
	                </b>
	            </div>
	            <div class="col-md-3">
	                <b>
	                    {{__('messages.start_time')}}
	                </b>
	            </div>
	            <div class="col-md-3">
	                <b>
	                    {{__('messages.end_time')}}
	                </b>
	            </div>
	        </div>
	        <template v-for="(timing, key) in $page.support_timing">
	            <hr class="mb-0 mt-0"
	            	v-show="!_.isUndefined(timing.show_day) && timing.show_day">
	            <div class="row" :key="key"
	            	:title="!_.isEmpty(timing.message) ? timing.message : ''"
	            	v-show="!_.isUndefined(timing.show_day) && timing.show_day">
	                <div class="col-md-4">
	                    {{__('messages.'+key)}}
	                </div>
	                <template v-if="timing.is_closed">
	                    <div class="col-md-6">
	                        <span class="badge badge-danger">
	                            {{__('messages.closed')}}
	                        </span>
	                    </div>
	                </template>
	                <template v-else>
	                    <div class="col-md-3" v-show="!_.isNull(timing.start)">
	                        {{getTimezoneFormattedTime(timing.start)}}
	                    </div>
	                    <div class="col-md-3" v-show="!_.isNull(timing.end)">
	                        {{getTimezoneFormattedTime(timing.end)}}
	                    </div>
	                </template>
	                <div class="col-md-2" v-if="timing.message">
	                    <i class="fas fa-envelope-open-text cursor-pointer fa-lg text-info mt-2"
	                        data-toggle="tooltip" :title="timing.message"></i>
	                </div>
	            </div>
	        </template>
	    </div>
	</div>
</template>
<script>
	export default {
		props: {
    		classes: String,
  		},
		data: function(){
            return {
                CURRENT_DAY: null,
                APP_TIMEZONE : APP.TIMEZONE
            }
        },
        mounted() {
            let day = new Date()
                        .toLocaleString("en-IN", {timeZone: this.APP_TIMEZONE, weekday: "long" });
            this.CURRENT_DAY = day.toLowerCase();
            $("#support_time_popover").popover({
                title: '<h5>'+this.__('messages.support_timing')+'</h5>',
                container: 'body',
                html: true,
                content: $('#support-popover-content'),
            }).on('show.bs.popover', function() {
                $('#support-popover-content').addClass('show')
            }).on('hide.bs.popover', function() {
                $('#support-popover-content').addClass('hide')
            });
        },
        methods:{
            getCurrentDayAndTime() {
            	var time = '';
                let support = this.$page.support_timing[this.CURRENT_DAY];
                if(!_.isEmpty(support) && support.is_closed) {
                    time = `(${this.__('messages.closed')})`;
                } else if(
                		!_.isEmpty(support) && !_.isNull(support.start) && !_.isNull(support.end)
                	) {
                	time = `(${this.getTimezoneFormattedTime(support.start) +' - '+ this.getTimezoneFormattedTime(support.end)})`;
                }
                return this.__('messages.'+this.CURRENT_DAY)+`${time}`;
            },
            getSupportMessageForTheDay() {
            	let support = this.$page.support_timing[this.CURRENT_DAY];
            	if(!_.isEmpty(support) && !_.isEmpty(support.message)) {
            		return support.message;
            	}
            },
            getTimezoneFormattedTime(time) {
            	const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            	return  moment(time, "HH:mm").tz(timezone).format("hh:mm A");
            }
        }
	}
</script>
<style scoped>
    .popover, .popover-body {
        width: 100% !important;
        max-width: 100% !important;
    }
    .hide{
        display:none !important;
    }
    .show{
        display:block !important;
    }
</style>