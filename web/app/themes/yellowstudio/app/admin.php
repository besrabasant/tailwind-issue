<?php

namespace App;

/**
 * Theme customizer
 */
add_action('customize_register', function (\WP_Customize_Manager $wp_customize) {
    // Add postMessage support
	$wp_customize->get_setting('blogname')->transport = 'postMessage';
	$wp_customize->selective_refresh->add_partial('blogname', [
		'selector' => '.brand',
		'render_callback' => function () {
			bloginfo('name');
		}
	]);
});

/**
 * Customizer JS
 */
add_action('customize_preview_init', function () {
	wp_enqueue_script('sage/manifest.js', asset_path('js/manifest.js'), null, null, true);
	wp_enqueue_script('sage/customizer.js', asset_path('js/customizer.js'), ['customize-preview'], null, true);
});

