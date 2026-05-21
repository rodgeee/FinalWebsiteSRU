import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './styles/landingpage.css';
import './styles/dashboard.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

document.addEventListener('DOMContentLoaded', () => {
    const railTrack = document.querySelector('[data-rail-track]');
    if (!railTrack) {
        return;
    }

    const railBody = railTrack.closest('[data-rail-body]');
    const prevBtn = document.querySelector('[data-rail-nav="prev"]');
    const nextBtn = document.querySelector('[data-rail-nav="next"]');

    const scrollAmount = () => {
        const containerWidth = railBody?.clientWidth ?? railTrack.clientWidth;
        return containerWidth * 0.9;
    };

    prevBtn?.addEventListener('click', () => {
        railTrack.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
    });

    nextBtn?.addEventListener('click', () => {
        railTrack.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
    });
});

function loadBrandsPageAssets() {
    if (document.querySelector('[data-lp-brands]')) {
        import('./brands.js');
    }
}

function loadServicesPageAssets() {
    if (document.querySelector('#services-main')) {
        import('./styles/services-page.css');
    }
}

function initPublicNavFixes() {
    document.querySelectorAll('a[href$="#services"]').forEach((link) => {
        if (link.dataset.servicesNavFixed === '1') {
            return;
        }
        link.dataset.servicesNavFixed = '1';
        link.setAttribute('href', '/services');
        link.setAttribute('data-turbo', 'false');
        link.setAttribute('data-turbo-frame', '_top');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadBrandsPageAssets();
    loadServicesPageAssets();
    initPublicNavFixes();
});
document.addEventListener('turbo:load', () => {
    loadBrandsPageAssets();
    loadServicesPageAssets();
    initPublicNavFixes();
});

if (document.readyState !== 'loading') {
    loadBrandsPageAssets();
    loadServicesPageAssets();
    initPublicNavFixes();
}
