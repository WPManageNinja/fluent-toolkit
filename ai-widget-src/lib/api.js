export const api = {
    async request(endpoint, data, method = 'POST', options = {}) {
        // Retrieve nonce if available (standard WP way)
        const nonce = window.fluent_ai_vars?.nonce || '';
        const apiBase = window.fluent_ai_vars?.api_base;

        if (!apiBase) {
            throw new Error('Fluent AI API base URL is not configured.');
        }

        let url = `${apiBase}/${endpoint}`;

        if (method === 'GET' && data) {
            const queryParams = new URLSearchParams(data).toString();
            url += `?${queryParams}`;
        }

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: method === 'POST' ? JSON.stringify(data) : null,
            signal: options.signal
        });

        const body = await response.text();
        let result = {};

        if (body) {
            try {
                result = JSON.parse(body);
            } catch (error) {
                result = { message: body };
            }
        }

        if (!response.ok) {
            const requestError = new Error(result.message || 'API Request Failed');
            requestError.status = response.status;
            requestError.data = result;
            throw requestError;
        }

        return result;
    }
};
