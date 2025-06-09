module.exports = {
    methods: {
        /**
         * Translate the given key.
         */
        __(key, replace = []) {
            let translation, translationNotFound = true

            try {
                translation = key.split('.').reduce((t, i) => t[i] || null, window._translations[window._locale].php)

                if (translation) {
                    translationNotFound = false
                }
            } catch (e) {
                translation = key
            }

            if (translationNotFound) {
                translation = window._translations[window._locale]['json'][key]
                    ? window._translations[window._locale]['json'][key]
                    : key
            }

            _.forEach(replace, (value, key) => {
                translation = translation.replace(':' + key, value)
            })

            return translation
        },
        badgeForTicketStatus(status) {
            var badge = 'badge-secondary';
            if (status == 'new') {
                badge = 'badge-primary';
            } else if (status == 'waiting') {
                badge = 'badge-warning';
            }  else if (status == 'pending') {
                badge = 'badge-dark';
            }  else if (status == 'closed') {
                badge = 'badge-danger';
            }

            return badge;
        },
        badgeForTicketPriority(priority) {
            if (priority == 'low') {
                return 'badge-secondary';
            } else if (priority == 'medium') {
                return 'badge-info text-white';
            } else if (priority == 'high') {
                return 'badge-warning';
            } else if (priority == 'urgent') {
                return 'badge-danger';
            }
        }
    },
}
