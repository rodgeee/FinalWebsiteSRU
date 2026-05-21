<?php

/**
 * Public shoe cleaning services page content.
 *
 * @return array<string, mixed>
 */
return [
    'hero' => [
        'eyebrow' => "Shoes R' Us · Care Lab",
        'title' => 'Professional shoe cleaning, built for sneakerheads',
        'lead' => 'From everyday refresh to deep restoration—we hand-clean your pairs with color-safe products, careful drying, and the same obsession you have for fresh lace-ups.',
        'image' => 'nike display.png',
        'image_alt' => 'Sneaker on display',
    ],
    'stats' => [
        ['value' => '500+', 'label' => 'Pairs refreshed'],
        ['value' => '3–5', 'label' => 'Days standard turnaround'],
        ['value' => '100%', 'label' => 'Hand-cleaned & inspected'],
    ],
    'packages' => [
        [
            'name' => 'Essential Clean',
            'price' => '₱299',
            'tagline' => 'Quick refresh for daily rotation',
            'featured' => false,
            'includes' => [
                'Upper wipe & light foam clean',
                'Outsole scrub & lace wash',
                'Deodorizing finish',
            ],
        ],
        [
            'name' => 'Deep Clean',
            'price' => '₱599',
            'tagline' => 'Our most popular full reset',
            'featured' => true,
            'includes' => [
                'Full hand wash inside & out',
                'Midsole brightening (material-safe)',
                'Lace replace or deep clean',
                'Protective quick-dry finish',
            ],
        ],
        [
            'name' => 'Premium Restore',
            'price' => '₱999',
            'tagline' => 'For grails & delicate materials',
            'featured' => false,
            'includes' => [
                'Everything in Deep Clean',
                'Suede / nubuck brush & spot care',
                'Minor scuff softening',
                'Premium water & stain repellent',
            ],
        ],
    ],
    'process' => [
        ['step' => '01', 'title' => 'Drop off or book', 'text' => 'Visit the store or message us with your pair, size, and material notes.'],
        ['step' => '02', 'title' => 'Inspect & tag', 'text' => 'We photograph condition, confirm package tier, and set your pickup date.'],
        ['step' => '03', 'title' => 'Clean & dry', 'text' => 'Hand-washed with sneaker-safe solutions—never tossed in a harsh machine cycle.'],
        ['step' => '04', 'title' => 'QC & pickup', 'text' => 'Final inspection, re-laced, bagged, and ready when we text you.'],
    ],
    'care_notes' => [
        [
            'title' => 'Color-safe formulas',
            'text' => 'Solutions chosen by upper material—mesh, leather, knit, suede, and rubber each get a tailored approach.',
        ],
        [
            'title' => 'No harsh shortcuts',
            'text' => 'We skip industrial washers and bleach that fade panels, crack midsoles, or loosen glue.',
        ],
        [
            'title' => 'Rush available',
            'text' => 'Need them sooner? 24-hour rush service is available for an additional fee—ask in store.',
        ],
    ],
    'faq' => [
        [
            'q' => 'How long does cleaning take?',
            'a' => 'Standard turnaround is 3–5 business days depending on queue and material. Rush service may be available for select pairs.',
        ],
        [
            'q' => 'Can you clean suede, knit, and leather?',
            'a' => 'Yes. Tell us the materials when you drop off—we adjust brushes, solutions, and drying so each upper stays true to color.',
        ],
        [
            'q' => 'Do you remove yellowing from midsoles?',
            'a' => 'Light brightening is included in Deep Clean and Premium Restore where safe. Heavy oxidation may need extra assessment.',
        ],
        [
            'q' => 'What should I bring?',
            'a' => 'Bring both shoes, original laces if you have them, and any special instructions. Insoles can be cleaned on request.',
        ],
    ],
];
