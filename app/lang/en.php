<?php
/**
 * Veloura Tec — English strings.
 *
 * To add a language later: copy this file to app/lang/<code>.php, translate
 * the values, set 'default_locale' (and 'direction' for RTL) in config.php.
 * Keys must stay identical across files.
 */

declare(strict_types=1);

if (!defined('VELOURA')) {
    http_response_code(403);
    exit('Forbidden');
}

return [
    // Navigation
    'nav.home'      => 'Home',
    'nav.shop'      => 'Shop',
    'nav.solutions' => 'Professional Solutions',
    'nav.about'     => 'About Us',
    'nav.contact'   => 'Contact',
    'nav.faq'       => 'FAQ',
    'nav.menu'      => 'Menu',
    'nav.skip'      => 'Skip to content',
    'nav.close'     => 'Close menu',

    // Calls to action
    'cta.explore'      => 'Explore Equipment',
    'cta.request_quote'=> 'Request a Quote',
    'cta.add_inquiry'  => 'Add to Inquiry',
    'cta.in_inquiry'   => 'In your inquiry',
    'cta.send_whatsapp'=> 'Send Inquiry via WhatsApp',
    'cta.continue'     => 'Continue browsing',
    'cta.view_details' => 'View Details',
    'cta.inquiry_cart' => 'Inquiry Cart',

    // Generic
    'common.loading'   => 'Loading…',
    'common.search'    => 'Search',
    'common.category'  => 'Category',
    'common.featured'  => 'Featured',
    'common.price_on_request' => 'Price on request',
    'common.empty'     => 'Nothing to show here yet.',
    'common.results'   => 'results',
    'common.remove'    => 'Remove',
    'common.quantity'  => 'Quantity',

    // Inquiry cart
    'inquiry.title'        => 'Inquiry Cart',
    'inquiry.empty'        => 'Your inquiry list is empty.',
    'inquiry.empty_hint'   => 'Browse the catalog and add the equipment you would like a quote for.',
    'inquiry.your_details' => 'Your details',
    'inquiry.full_name'    => 'Full name',
    'inquiry.country'      => 'Country',
    'inquiry.city'         => 'City',
    'inquiry.email'        => 'Email address',
    'inquiry.whatsapp'     => 'WhatsApp number',
    'inquiry.company'      => 'Company / clinic name',
    'inquiry.notes'        => 'Additional notes',
    'inquiry.optional'     => 'optional',
    'inquiry.success'      => 'Your inquiry has been received',

    // Footer
    'footer.rights'    => 'All rights reserved.',
    'footer.company'   => 'Company',
    'footer.catalog'   => 'Catalog',
    'footer.legal'     => 'Legal',
    'footer.contact'   => 'Contact',

    // Authentication
    'auth.title'               => 'Admin Sign In',
    'auth.email'               => 'Email address',
    'auth.password'            => 'Password',
    'auth.submit'              => 'Sign In',
    'auth.logout'              => 'Sign Out',
    'auth.invalid_credentials' => 'Incorrect email or password.',
    'auth.locked'              => 'Too many failed attempts. Please try again later.',
    'auth.required'            => 'Please enter both your email and password.',
    'auth.signed_out'          => 'You have been signed out.',

    // Admin
    'admin.dashboard'  => 'Dashboard',
    'admin.categories' => 'Categories',
    'admin.products'   => 'Products',
    'admin.media'      => 'Media',
    'admin.inquiries'  => 'Inquiries',
    'admin.hero'       => 'Hero Slider',
    'admin.settings'   => 'Settings',
];
