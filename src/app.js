import {createApp} from 'vue'
import App from './components/App.vue'

import {ElNotification, ElMessageBox, ElLoading} from "element-plus";
import {$t, $_n, $_x} from './i18n.js';

require('./style.scss');

const app = createApp(App);

// Every notification shows at the bottom right, clear of the WP admin bar and
// the app's top bar, unless the caller asks for another position.
const withPosition = options => Object.assign(
    {position: 'bottom-right'},
    typeof options === 'string' ? {message: options} : options
);
const notify = options => ElNotification(withPosition(options));
['success', 'warning', 'info', 'error'].forEach(type => {
    notify[type] = options => ElNotification[type](withPosition(options));
});
notify.closeAll = ElNotification.closeAll;

app.config.globalProperties.$notify = notify;
app.config.globalProperties.$confirm = ElMessageBox.confirm;
app.use(ElLoading);

const request = function (method, action, data = {}) {
    data.query_timestamp = Date.now();
    data.action = action;
    data._ft_nonce = window.fluentToolkitVars.nonce;

    return new Promise((resolve, reject) => {
        window.jQuery.ajax({
            url: window.fluentToolkitVars.ajax_url,
            type: method,
            data: data
        })
            .then(response => resolve(response))
            .fail(errors => reject(errors.responseJSON));
    });
}

function convertToText(obj) {
    const string = [];
    if (typeof (obj) === 'object' && (obj.join === undefined)) {
        for (const prop in obj) {
            string.push(convertToText(obj[prop]));
        }
    } else if (typeof (obj) === 'object' && !(obj.join === undefined)) {
        for (const prop in obj) {
            string.push(convertToText(obj[prop]));
        }
    } else if (typeof (obj) === 'function') {

    } else if (typeof (obj) === 'string') {
        string.push(obj)
    }

    return string.join('<br />')
}

app.config.globalProperties.appVars = window.fluentToolkitVars;
app.config.globalProperties.$t = $t;
app.config.globalProperties.$_n = $_n;
app.config.globalProperties.$_x = $_x;

app.mixin({
    methods: {
        $get(action, data = {}) {
            return request('GET', action, data);
        },
        $post(action, data = {}) {
            return request('POST', action, data);
        },
        $handleError(response) {
            let errorMessage = '';
            if (typeof response === 'string') {
                errorMessage = response;
            } else if (response && response.message) {
                errorMessage = response.message;
            } else {
                errorMessage = convertToText(response);
            }
            if (!errorMessage) {
                errorMessage = $t('That did not work. Try again, or reload the page.');
            }
            this.$notify({
                type: 'error',
                title: $t('Error'),
                message: errorMessage,
                dangerouslyUseHTMLString: true
            });
        },
        convertToText,
    }
});

app.mount(
    '#fluent_app'
);
