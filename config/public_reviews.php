<?php

/**
 * Public reviews shown on /reviews.
 *
 * Edit this file anytime to change the content (no template edits needed).
 *
 * @return array{
 *   title?: string,
 *   subtitle?: string,
 *   view_more_label?: string,
 *   items: array<int, array{
 *     name: string,
 *     initials?: string,
 *     rating?: float,
 *     text: string,
 *     link_label?: string
 *   }>
 * }
 */
return [
    'title' => 'What people Thinks About Us',
    'view_more_label' => 'View More',
    'items' => [
        [
            'name' => 'KIAN LUZANO',
            'initials' => 'KL',
            'rating' => 4.9,
            'text' => "They’ve got a really solid collection of trendy sneakers and limited drops. I just wish more sizes were available for popular releases. Still a great platform overall.",
            'link_label' => 'Explore More',
        ],
        [
            'name' => 'JAY LAWRENCE',
            'initials' => 'JL',
            'rating' => 4.7,
            'text' => "I love how easy it is to browse through sneakers on this platform. The layout is super clean and modern, and the checkout process is fast. Definitely feels like a premium store experience.",
            'link_label' => 'Explore More',
        ],
        [
            'name' => 'RHAM NIÑO',
            'initials' => 'RN',
            'rating' => 5.0,
            'text' => "The design reminds me of high-end sneaker boutiques. Minimalist but stylish. It really highlights the shoes without cluttering the page.",
            'link_label' => 'Explore More',
        ],
        [
            'name' => 'RAINIER KINTANILLA',
            'initials' => 'RK',
            'rating' => 4.0,
            'text' => "Everything is intuitive and easy to navigate. It would be even better if there was a wishlist or save-for-later option.",
            'link_label' => 'Explore More',
        ],
        [
            'name' => 'ABENIS AMANTILLO',
            'initials' => 'AA',
            'rating' => 5.0,
            'text' => "No unnecessary clutter, just straight-up good design and functionality. Makes finding and buying shoes super easy.",
            'link_label' => 'Explore More',
        ],
        [
            'name' => 'NIÑO SAMSON',
            'initials' => 'NS',
            'rating' => 4.5,
            'text' => "I like how I can sort by brand, size, and price. Makes it easy to find what I want without scrolling forever.",
            'link_label' => 'Explore More',
        ],
    ],
];

