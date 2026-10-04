// Punto de entrada del JavaScript del sistema. Vite toma este archivo, lo
// empaqueta junto con sus dependencias y lo carga en todas las páginas.

// Configuración base compartida (axios para peticiones al servidor).
import './bootstrap';

// Alpine.js es una librería ligera que usamos para la interactividad de las
// vistas Blade (menús desplegables, mostrar u ocultar campos de un formulario,
// ventanas de confirmación) sin necesidad de un framework más pesado.
import Alpine from 'alpinejs';

// Lo dejamos disponible de forma global para poder usarlo desde la consola del
// navegador o desde otros scripts si hiciera falta.
window.Alpine = Alpine;

// Inicia Alpine: busca en la página los atributos x-data, x-show, etc. y los activa.
Alpine.start();
