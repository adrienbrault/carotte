import 'bootstrap/dist/css/bootstrap.min.css';
import * as bootstrap from 'bootstrap';

import './bootstrap.js';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

window.addEventListener("turbo:before-stream-render", () => {
    requestAnimationFrame(() => {
        window.scrollTo({
            left: 0,
            top: document.body.scrollHeight,
            behavior: 'instant'
        });
    })
})