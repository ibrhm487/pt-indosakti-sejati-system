import axios from 'axios';
import { route as ziggyRoute } from 'ziggy-js';

const axiosInstance = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

window.axios = axiosInstance;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.route = ziggyRoute;
