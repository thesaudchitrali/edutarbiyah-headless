<?php
function hello_elementor_child_enqueue_styles() {
    wp_enqueue_style(
        'hello-elementor-child-style',
        get_stylesheet_uri(),
        array( 'hello-elementor-theme-style' ),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles', 20 );

/**
 * Enqueue EduTarbiyah Unified Design System for Tutor LMS & WooCommerce
 * Matches the Astro static website: Playfair Display + Plus Jakarta Sans,
 * Forest Green (#064E3B), Deep Teal (#0F766E), Amber (#F59E0B), Warm Paper (#FFFDF9).
 */
function edutarbiyah_unified_enqueue_styles() {
    // Google Fonts matching main website
    wp_enqueue_style(
        'edutarbiyah-unified-fonts',
        'https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap',
        array(),
        null
    );

    // Unified Design System Stylesheet (Priority 99 loads after Tutor LMS & WooCommerce)
    wp_enqueue_style(
        'edutarbiyah-unified-design',
        get_stylesheet_directory_uri() . '/css/edutarbiyah-unified.css',
        array( 'hello-elementor-child-style' ),
        '1.2.0'
    );
}
add_action( 'wp_enqueue_scripts', 'edutarbiyah_unified_enqueue_styles', 99 );

/**
 * Direct wp_head injection ensuring Google Fonts and Unified Critical CSS
 * apply instantly without waiting for or being stripped by LiteSpeed UCSS optimization.
 */
function edutarbiyah_unified_head_styles() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">' . "\n";

    $css_path = get_stylesheet_directory() . '/css/edutarbiyah-unified.css';
    if ( file_exists( $css_path ) ) {
        echo '<style id="edutarbiyah-unified-css">' . file_get_contents( $css_path ) . '</style>' . "\n";
    }
}
add_action( 'wp_head', 'edutarbiyah_unified_head_styles', 999 );

add_filter('woocommerce_checkout_fields', 'custom_minimal_checkout', 9999);

function custom_minimal_checkout($fields) {

    // Remove unnecessary billing fields
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_email']);
    unset($fields['order']['order_comments']);

    // Keep only these required
    $fields['billing']['billing_first_name']['required'] = true;
    $fields['billing']['billing_last_name']['required'] = true;
    $fields['billing']['billing_phone']['required'] = true;
    $fields['billing']['billing_address_1']['required'] = true;

    // Remove validation requirement completely
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_state']);

    return $fields;
}
// Buy Now button under Add to Cart
add_action('woocommerce_after_add_to_cart_button', 'custom_buy_now_button');

function custom_buy_now_button() {
    global $product;

    if (!$product || !$product->is_purchasable()) {
        return;
    }

    echo '<input type="hidden" name="buy_now" id="buy_now_field" value="0">';

    echo '<button type="submit"
        name="add-to-cart"
        value="' . esc_attr($product->get_id()) . '"
        class="single_add_to_cart_button button alt buy-now-button"
        onclick="document.getElementById(\'buy_now_field\').value=\'1\';"
        style="width:100%;">
        Buy Now
    </button>';
}

// Force redirect to checkout after Buy Now
add_action('template_redirect', 'custom_buy_now_force_checkout');

function custom_buy_now_force_checkout() {
    if (
        isset($_REQUEST['buy_now']) &&
        $_REQUEST['buy_now'] == '1' &&
        !is_checkout()
    ) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}


function enqueue_child_theme_scripts() {
    // Enqueue the custom JS file
    wp_enqueue_script(
        'child-custom-js', // Unique handle name for the script
        get_stylesheet_directory_uri() . '/js/custom-scripts.js', // URL path to your file
        array('jquery'), // Dependencies (e.g., load jQuery before this script)
        '1.0.0', // Script version number
        true // Load script in the footer (true) instead of the header (false)
    );
}
add_action('wp_enqueue_scripts', 'enqueue_child_theme_scripts');




/**
 * ---------------------------------------------------------
 * EduTarbiyah custom WooCommerce Shop Archive
 * ---------------------------------------------------------
 * Everything below is scoped to the WooCommerce catalog so
 * existing Elementor pages remain unaffected.
 */

function edutarbiyah_shop_enqueue_assets() {
    if ( ! function_exists( 'is_shop' ) ) {
        return;
    }

    if ( is_shop() || is_product_taxonomy() ) {
        wp_enqueue_style(
            'edutarbiyah-public-sans',
            'https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap',
            array(),
            null
        );

        wp_enqueue_style(
            'edutarbiyah-shop',
            get_stylesheet_directory_uri() . '/css/shop.css',
            array( 'hello-elementor-child-style', 'edutarbiyah-public-sans' ),
            '2.0.0'
        );

        wp_enqueue_script(
            'edutarbiyah-shop',
            get_stylesheet_directory_uri() . '/js/shop.js',
            array(),
            '2.0.0',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'edutarbiyah_shop_enqueue_assets', 30 );

/**
 * Apply EduTarbiyah audience / format query filters.
 * These work when WooCommerce product attributes with the
 * slugs "audience" and "format" exist.
 */
function edutarbiyah_shop_catalog_filters( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! function_exists( 'is_shop' ) ) {
        return;
    }

    if ( ! ( is_shop() || is_product_taxonomy() ) ) {
        return;
    }

    $tax_query = (array) $query->get( 'tax_query' );

    if ( ! empty( $_GET['et_audience'] ) && taxonomy_exists( 'pa_audience' ) ) {
        $tax_query[] = array(
            'taxonomy' => 'pa_audience',
            'field'    => 'slug',
            'terms'    => sanitize_title( wp_unslash( $_GET['et_audience'] ) ),
        );
    }

    if ( ! empty( $_GET['et_format'] ) && taxonomy_exists( 'pa_format' ) ) {
        $tax_query[] = array(
            'taxonomy' => 'pa_format',
            'field'    => 'slug',
            'terms'    => sanitize_title( wp_unslash( $_GET['et_format'] ) ),
        );
    }

    if ( count( $tax_query ) > 0 ) {
        $query->set( 'tax_query', $tax_query );
    }
}
add_action( 'pre_get_posts', 'edutarbiyah_shop_catalog_filters', 20 );

/**
 * Utility for keeping selected shop query parameters when
 * building category/audience links.
 */
function edutarbiyah_shop_url( $args = array(), $remove = array() ) {
    $base = function_exists( 'wc_get_page_permalink' )
        ? wc_get_page_permalink( 'shop' )
        : home_url( '/shop/' );

    $allowed = array( 'et_audience', 'et_format', 'orderby', 'min_price', 'max_price' );
    $current = array();

    foreach ( $allowed as $key ) {
        if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
            $current[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
        }
    }

    foreach ( $remove as $key ) {
        unset( $current[ $key ] );
    }

    $current = array_merge( $current, $args );

    return add_query_arg( $current, $base );
}


/**
 * ---------------------------------------------------------
 * EduTarbiyah custom WooCommerce Single Product
 * ---------------------------------------------------------
 */
function edutarbiyah_single_product_support() {
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'edutarbiyah_single_product_support', 30 );

function edutarbiyah_single_product_assets() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return;
    }

    wp_enqueue_style(
        'edutarbiyah-product-public-sans',
        'https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap',
        array(),
        null
    );

    wp_enqueue_style(
        'edutarbiyah-single-product',
        get_stylesheet_directory_uri() . '/css/single-product.css',
        array( 'hello-elementor-child-style', 'edutarbiyah-product-public-sans' ),
        '2.4.0'
    );

    wp_enqueue_script(
        'edutarbiyah-single-product',
        get_stylesheet_directory_uri() . '/js/single-product.js',
        array(),
        '2.4.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'edutarbiyah_single_product_assets', 30 );

function edutarbiyah_get_product_meta( $product_id, $key, $fallback = '' ) {
    $value = get_post_meta( $product_id, $key, true );
    if ( is_string( $value ) ) {
        $value = trim( $value );
    }
    return '' !== $value ? $value : $fallback;
}


/**
 * ---------------------------------------------------------
 * EduTarbiyah Product Details metabox
 * ---------------------------------------------------------
 */

function edutarbiyah_add_product_details_metabox() {
    add_meta_box(
        'edutarbiyah-product-details',
        __( 'EduTarbiyah Product Details', 'hello-elementor-child' ),
        'edutarbiyah_render_product_details_metabox',
        'product',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'edutarbiyah_add_product_details_metabox' );

function edutarbiyah_render_product_details_metabox( $post ) {
    wp_nonce_field( 'edutarbiyah_save_product_details', 'edutarbiyah_product_details_nonce' );

    $fields = array(
        '_et_author' => array(
            'label'       => __( 'Author', 'hello-elementor-child' ),
            'type'        => 'text',
            'placeholder' => __( 'e.g. Dr. Muhammad Younus Khalid', 'hello-elementor-child' ),
            'help'        => __( 'Shown below the product title on the custom single-product page.', 'hello-elementor-child' ),
        ),
        '_et_subtitle' => array(
            'label'       => __( 'Subtitle', 'hello-elementor-child' ),
            'type'        => 'text',
            'placeholder' => __( 'e.g. A Practical Guide for Parents and Educators', 'hello-elementor-child' ),
            'help'        => __( 'Optional supporting line below the main product title.', 'hello-elementor-child' ),
        ),
        '_et_isbn' => array(
            'label'       => __( 'ISBN', 'hello-elementor-child' ),
            'type'        => 'text',
            'placeholder' => __( 'e.g. 978-969-000-000-0', 'hello-elementor-child' ),
            'help'        => __( 'Optional ISBN or publication identifier.', 'hello-elementor-child' ),
        ),
        '_et_preview_url' => array(
            'label'       => __( 'Preview PDF / Sample URL', 'hello-elementor-child' ),
            'type'        => 'url',
            'placeholder' => __( 'https://example.com/sample.pdf', 'hello-elementor-child' ),
            'help'        => __( 'If filled, a “Look Inside / Preview Sample” button appears on the product page.', 'hello-elementor-child' ),
        ),
        '_et_bulk_quote_url' => array(
            'label'       => __( 'Bulk Quote URL', 'hello-elementor-child' ),
            'type'        => 'url',
            'placeholder' => __( 'https://edutarbiyah.com/contact/', 'hello-elementor-child' ),
            'help'        => __( 'Optional link for institutional or bulk-order enquiries. Falls back to the Contact page if empty.', 'hello-elementor-child' ),
        ),
    );

    echo '<div class="et-product-admin-fields">';

    foreach ( $fields as $key => $field ) {
        $value = get_post_meta( $post->ID, $key, true );

        echo '<p style="margin:0 0 18px;">';
        echo '<label for="' . esc_attr( $key ) . '" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html( $field['label'] ) . '</label>';
        echo '<input type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" style="width:100%;max-width:760px;padding:8px 10px;" />';
        echo '<span style="display:block;color:#646970;font-size:12px;margin-top:5px;">' . esc_html( $field['help'] ) . '</span>';
        echo '</p>';
    }

    echo '</div>';
}

function edutarbiyah_save_product_details_metabox( $post_id ) {
    if (
        ! isset( $_POST['edutarbiyah_product_details_nonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['edutarbiyah_product_details_nonce'] ) ),
            'edutarbiyah_save_product_details'
        )
    ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( 'product' !== get_post_type( $post_id ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $text_fields = array(
        '_et_author',
        '_et_subtitle',
        '_et_isbn',
    );

    foreach ( $text_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
            if ( '' !== $value ) {
                update_post_meta( $post_id, $field, $value );
            } else {
                delete_post_meta( $post_id, $field );
            }
        }
    }

    $url_fields = array(
        '_et_preview_url',
        '_et_bulk_quote_url',
    );

    foreach ( $url_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = esc_url_raw( wp_unslash( $_POST[ $field ] ) );
            if ( $value ) {
                update_post_meta( $post_id, $field, $value );
            } else {
                delete_post_meta( $post_id, $field );
            }
        }
    }
}
add_action( 'save_post_product', 'edutarbiyah_save_product_details_metabox' );


/**
 * ---------------------------------------------------------
 * Quantity +/- buttons for WooCommerce product quantity input
 * ---------------------------------------------------------
 */

function edutarbiyah_quantity_minus_button() {
    if ( ! is_product() ) {
        return;
    }

    echo '<button type="button" class="et-qty-btn et-qty-minus" aria-label="' .
        esc_attr__( 'Decrease quantity', 'hello-elementor-child' ) .
        '">−</button>';
}
add_action( 'woocommerce_before_quantity_input_field', 'edutarbiyah_quantity_minus_button' );

function edutarbiyah_quantity_plus_button() {
    if ( ! is_product() ) {
        return;
    }

    echo '<button type="button" class="et-qty-btn et-qty-plus" aria-label="' .
        esc_attr__( 'Increase quantity', 'hello-elementor-child' ) .
        '">+</button>';
}
add_action( 'woocommerce_after_quantity_input_field', 'edutarbiyah_quantity_plus_button' );
