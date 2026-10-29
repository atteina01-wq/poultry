class ApiClient {
    constructor(baseURL) {
        this.baseURL = baseURL;
    }

    getAuthHeaders() {
        const token = localStorage.getItem('jwt_token');
        return {
            'Content-Type': 'application/json',
            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
        };
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const headers = this.getAuthHeaders();

        try {
            const response = await fetch(url, { ...options, headers });
            const data = await response.json();

            if (!response.ok) {
                if (response.status === 401) {
                    // Token expired or invalid
                    localStorage.removeItem('jwt_token');
                    localStorage.removeItem('user_data');
                    window.dispatchEvent(new Event('auth_failed'));
                }
                throw new Error(data.error || 'API Request Failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    async post(endpoint, body) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    }

    async login(username, password) {
        const res = await this.post('/auth/login.php', { username, password });
        if (res.success && res.token) {
            localStorage.setItem('jwt_token', res.token);
            localStorage.setItem('user_data', JSON.stringify(res.user));
            return res.user;
        }
        throw new Error('Login failed');
    }

    logout() {
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('user_data');
        window.dispatchEvent(new Event('auth_failed'));
    }

    isLoggedIn() {
        return !!localStorage.getItem('jwt_token');
    }
}

// Initialize global API client
const api = new ApiClient('/poultry/api/v1');
