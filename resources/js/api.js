import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

// Attach the Sanctum token from storage on every request.
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

// On 401 drop the stale token so the app re-runs the messenger login.
api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            localStorage.removeItem('token');
        }
        return Promise.reject(err);
    },
);

export default api;
