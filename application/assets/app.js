import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import autoColors from 'chartjs-plugin-autocolors';

// register globally for all charts
document.addEventListener('chartjs:init', function (event) {
    const Chart = event.detail.Chart;
    Chart.register(autoColors);
});
