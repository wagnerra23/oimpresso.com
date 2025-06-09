export default {
    install(Vue, options) {
        Vue.prototype.$commonFunction = {
            formatDate: function(date) {
                if (!_.isNull(date)) {
                    return moment(date).format("DD/MM/YYYY");
                }
            },
            timeFromNow: function(dateTimeString) {
                if (!_.isNull(dateTimeString)) {
                    return moment(dateTimeString).fromNow();
                }
            },
            timeFromNowForUnixTimeStamp: function(dateTimeString) {
                if (!_.isNull(dateTimeString)) {
                    return moment.unix(dateTimeString).fromNow();
                }
            },
            isDemo: function(dateTimeString) {
                return (APP.APP_ENV == 'demo');
            },
            formatDateTime: function(dateTimeString) {
                if(dateTimeString && !_.isNull(dateTimeString)) {
                    return moment(dateTimeString).format("DD/MM/YYYY h:mm:ss A");
                }
            }
        };
    },
};