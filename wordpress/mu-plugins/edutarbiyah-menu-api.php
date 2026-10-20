<?php
/**
 * Plugin Name: EduTarbiyah Hierarchical Menu REST API
 * Plugin URI: https://edutarbiyah.com
 * Description: Exposes clean, hierarchical JSON navigation endpoints at /wp-json/edutarbiyah/v1/menu/primary-menu and /wp-json/edutarbiyah/v1/menus for headless Astro frontend.
 * Version: 1.1.0
 * Author: EduTarbiyah Engineering
 * License: GPL-2.0+
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    // Primary menu route (auto-resolving or via ?menu=slug_or_id)
    register_rest_route('edutarbiyah/v1', '/menu/primary-menu', [
        'methods'             => 'GET',
        'callback'            => 'edutarbiyah_get_primary_menu_tree',
        'permission_callback' => '__return_true',
    ]);

    // List all menus and their locations
    register_rest_route('edutarbiyah/v1', '/menus', [
        'methods'             => 'GET',
        'callback'            => 'edutarbiyah_get_all_menus',
        'permission_callback' => '__return_true',
    ]);

    // Query specific menu by ID or slug
    register_rest_route('edutarbiyah/v1', '/menu/(?P<id_or_slug>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => 'edutarbiyah_get_menu_by_param',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * REST callback: List all menus in WordPress.
 */
function edutarbiyah_get_all_menus(WP_REST_Request $request) {
    $registered_menus = wp_get_nav_menus();
    $locations = get_nav_menu_locations();
    
    $result = [];
    foreach ($registered_menus as $menu) {
        $assigned_locations = [];
        foreach ($locations as $loc => $m_id) {
            if ($m_id == $menu->term_id) {
                $assigned_locations[] = $loc;
            }
        }

        $items = wp_get_nav_menu_items($menu->term_id);
        $result[] = [
            'id'        => (int) $menu->term_id,
            'name'      => $menu->name,
            'slug'      => $menu->slug,
            'count'     => is_array($items) ? count($items) : 0,
            'locations' => $assigned_locations,
        ];
    }

    $response = new WP_REST_Response([
        'success'   => true,
        'count'     => count($result),
        'locations' => $locations,
        'menus'     => $result,
    ], 200);

    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept');

    return $response;
}

/**
 * REST callback: Query specific menu by ID or slug.
 */
function edutarbiyah_get_menu_by_param(WP_REST_Request $request) {
    $param = $request->get_param('id_or_slug');
    return edutarbiyah_build_menu_response($param);
}

/**
 * REST callback: Primary menu with intelligent auto-discovery.
 */
function edutarbiyah_get_primary_menu_tree(WP_REST_Request $request) {
    $requested_menu = $request->get_param('menu');
    if (!empty($requested_menu)) {
        return edutarbiyah_build_menu_response($requested_menu);
    }

    $locations = get_nav_menu_locations();
    $menu_id = null;

    // 1. Check known theme location slugs
    $candidate_slugs = ['primary', 'primary-menu', 'main', 'main-menu', 'header', 'header-menu', 'top', 'top-menu', 'menu-1'];
    foreach ($candidate_slugs as $slug) {
        if (!empty($locations[$slug])) {
            $candidate_id = $locations[$slug];
            $items = wp_get_nav_menu_items($candidate_id);
            if (!empty($items)) {
                $menu_id = $candidate_id;
                break;
            }
        }
    }

    // 2. If primary location menu was empty or unset, find the first menu that actually HAS items
    if (!$menu_id) {
        $registered_menus = wp_get_nav_menus();
        if (!empty($registered_menus)) {
            // First check if any registered menu has count > 0
            foreach ($registered_menus as $menu) {
                $items = wp_get_nav_menu_items($menu->term_id);
                if (!empty($items)) {
                    $menu_id = $menu->term_id;
                    break;
                }
            }

            // Fallback to first registered menu even if empty
            if (!$menu_id && isset($registered_menus[0])) {
                $menu_id = $registered_menus[0]->term_id;
            }
        }
    }

    return edutarbiyah_build_menu_response($menu_id);
}

/**
 * Helper to build the hierarchical JSON response for a given menu ID or slug.
 */
function edutarbiyah_build_menu_response($menu_identifier) {
    if (!$menu_identifier) {
        return rest_ensure_response([
            'success' => false,
            'message' => 'No navigation menu found in WordPress.',
            'items'   => []
        ]);
    }

    $menu = wp_get_nav_menu_object($menu_identifier);
    if (!$menu) {
        return rest_ensure_response([
            'success' => false,
            'message' => "Menu '{$menu_identifier}' not found in WordPress.",
            'items'   => []
        ]);
    }

    $menu_items = wp_get_nav_menu_items($menu->term_id);
    if (empty($menu_items)) {
        return rest_ensure_response([
            'success'   => true,
            'menu_id'   => (int) $menu->term_id,
            'menu_name' => $menu->name,
            'items'     => []
        ]);
    }

    // Build hierarchical tree
    $items_by_id = [];
    $tree = [];
    $site_url = home_url();

    foreach ($menu_items as $item) {
        // Strip backend domain to keep internal relative paths clean
        $clean_url = str_replace($site_url, '', $item->url);
        $target = $item->target ? $item->target : '_self';

        if (empty($clean_url) || $clean_url === '') {
            $clean_url = '/';
        }

        // External URLs get target _blank
        if (strpos($clean_url, 'http://') === 0 || strpos($clean_url, 'https://') === 0) {
            $target = '_blank';
        }

        // If internal route (not anchor, not file extension), ensure trailing slash for Astro
        if (strpos($clean_url, '/') === 0 && $clean_url !== '/' && strpos($clean_url, '#') === false && strpos($clean_url, '?') === false) {
            if (!preg_match('/\.(png|jpe?g|gif|webp|svg|pdf|docx?|xlsx?)$/i', $clean_url)) {
                $clean_url = rtrim($clean_url, '/') . '/';
            }
        }

        $items_by_id[$item->ID] = [
            'id'          => (int) $item->ID,
            'title'       => html_entity_decode($item->title, ENT_QUOTES, 'UTF-8'),
            'url'         => $clean_url,
            'raw_url'     => $item->url,
            'target'      => $target,
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
        'success'   => true,
        'menu_id'   => (int) $menu->term_id,
        'menu_name' => $menu->name,
        'count'     => count($tree),
        'items'     => $tree,
    ], 200);

    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Accept');

    return $response;
}
