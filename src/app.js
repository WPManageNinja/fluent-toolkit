import {createApp} from 'vue'
import Dashboard from './components/Dashboard.vue'

import {ElNotification, ElMessageBox, ElLoading} from "element-plus";

require('./style.scss');

const app = createApp(Dashboard);

app.config.globalProperties.$notify = ElNotification;
app.config.globalProperties.$confirm = ElMessageBox.confirm;
app.use(ElLoading);

const request = function (method, action, data = {}) {
    data.query_timestamp = Date.now();
    data.action = action;
    data.__nonce = window.fluentToolkitVars.nonce;

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
                errorMessage = 'Something is wrong!';
            }
            this.$notify({
                type: 'error',
                title: 'Error',
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
