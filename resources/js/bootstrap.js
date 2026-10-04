// Configuración inicial de JavaScript que se carga antes que el resto (desde app.js).

// Axios es la librería que usamos para hacer peticiones HTTP al servidor desde
// el navegador. La guardamos en window para tenerla disponible en cualquier script.
import axios from 'axios';
window.axios = axios;

// Esta cabecera le indica a Laravel que la petición se hizo por JavaScript (AJAX)
// y no navegando normalmente, así puede responder con JSON cuando corresponde.
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
