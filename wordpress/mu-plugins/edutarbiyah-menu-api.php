<?php
/**
 * Plugin Name: EduTarbiyah Hierarchical Menu REST API
 * Plugin URI: https://edutarbiyah.com
 * Description: Exposes a clean, hierarchical JSON navigation tree endpoint at /wp-json/edutarbiyah/v1/menu/primary-menu for headless Astro frontend.
 * Version: 1.0.0
 * Author: EduTarbiyah Engineering
 * License: GPL-2.0+
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('edutarbiyah/v1', '/menu/primary-menu', [
        'methods'             => 'GET',
        'callback'            => 'edutarbiyah_get_primary_menu_tree',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * REST callback to return hierarchical navigation tree.
 */
function edutarbiyah_get_primary_menu_tree(WP_REST_Request $request) {
    // 1. Locate primary menu
    $locations = get_nav_menu_locations();
    $menu_id = null;

    $candidate_slugs = ['primary', 'primary-menu', 'main', 'main-menu', 'header-menu', 'top-menu'];
    foreach ($candidate_slugs as $slug) {
        if (!empty($locations[$slug])) {
            $menu_id = $locations[$slug];
            break;
        }
    }

    // Fallback: take the first registered menu if no specific location matches
    if (!$menu_id) {
        $registered_menus = wp_get_nav_menus();
        if (!empty($registered_menus)) {
            $menu_id = $registered_menus[0]->term_id;
        }
    }

    if (!$menu_id) {
        return rest_ensure_response([
            'success' => false,
            'message' => 'No navigation menu found in WordPress.',
            'items'   => []
        ]);
    }

    $menu_items = wp_get_nav_menu_items($menu_id);
    if (empty($menu_items)) {
        return rest_ensure_response([
            'success' => true,
            'menu_id' => $menu_id,
            'items'   => []
        ]);
    }

    // Build hierarchical tree
    $items_by_id = [];
    $tree = [];

    foreach ($menu_items as $item) {
        // Strip backend domain to keep internal relative paths clean
        $site_url = home_url();
        $clean_url = str_replace($site_url, '', $item->url);
        if (empty($clean_url) || $clean_url === '') {
            $clean_url = '/';
        }

        $items_by_id[$item->ID] = [
            'id'          => (int) $item->ID,
            'title'       => html_entity_decode($item->title, ENT_QUOTES, 'UTF-8'),
            'url'         => $clean_url,
            'raw_url'     => $item->url,
            'target'      => $item->target ? $item->target : '_self',
            'type'        => $item->object,
            'object_id'   => (int) $item->object_id,
            'order'       => (int) $item->menu_order,
            'parent_id'   => (int) $item->menu_item_parent,
            'children'    => [],
        ];
    }

    foreach ($items_by_id as $id => &$node) {
        if ($node['parent_id'] && isset($items_by_id[$node['parent_id']])) {
            $items_by_id[$node['parent_id']]['children'][] = &$node;
        } else {
            $tree[] = &$node;
        }
    }

    $response = new WP_REST_Response([
        'success' => true,
        'menu_id' => (int) $menu_id,
        'count'   => count($tree),
        'items'   => $tree,
    ], 200);

    // Add CORS headers so Astro build and preview can access smoothly
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept');

    return $response;
}
