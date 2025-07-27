import axios from 'axios';

const myAxios = axios.create();

myAxios.interceptors.response.use(
    response => {
        console.log(response);
        if (response.data && response.data.error) {

            const enrichedError = new Error();
            enrichedError.message = response.data.error.message;
            enrichedError.results = response.data.error.results;
            enrichedError.domainId = response.data.error?.domainId || "";
            enrichedError.timestamp = response.data.error?.timestamp || "";

            throw enrichedError;
        }

        return response;
    },
    error => {
        return Promise.reject(error);
    }
);

export default myAxios;