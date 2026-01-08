<?php
/**
 * Plugin Name: Interslide Codex
 * Description: Manage custom post types and taxonomies with quick add, rename, and delete tools.
 * Version: 1.1.0
 * Author: Interslide
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'INTERSLIDE_CODEX_CAPABILITY' ) ) {
    define( 'INTERSLIDE_CODEX_CAPABILITY', 'manage_options' );
}

const INTERSLIDE_CODEX_OPTION_POST_TYPES = 'interslide_codex_post_types';
const INTERSLIDE_CODEX_OPTION_TAXONOMIES = 'interslide_codex_taxonomies';

add_action( 'init', 'interslide_codex_register_dynamic_types', 0 );
add_action( 'admin_menu', 'interslide_codex_register_menu' );
add_action( 'admin_post_interslide_codex_add_post_type', 'interslide_codex_handle_add_post_type' );
add_action( 'admin_post_interslide_codex_update_post_type', 'interslide_codex_handle_update_post_type' );
add_action( 'admin_post_interslide_codex_delete_post_type', 'interslide_codex_handle_delete_post_type' );
add_action( 'admin_post_interslide_codex_add_taxonomy', 'interslide_codex_handle_add_taxonomy' );
add_action( 'admin_post_interslide_codex_update_taxonomy', 'interslide_codex_handle_update_taxonomy' );
add_action( 'admin_post_interslide_codex_delete_taxonomy', 'interslide_codex_handle_delete_taxonomy' );

function interslide_codex_register_dynamic_types() {
    $post_types = interslide_codex_get_post_types();
    foreach ( $post_types as $slug => $data ) {
        $labels = array(
            'name'          => $data['plural'],
            'singular_name' => $data['singular'],
            'add_new_item'  => sprintf( __( 'Add New %s', 'interslide-codex' ), $data['singular'] ),
            'edit_item'     => sprintf( __( 'Edit %s', 'interslide-codex' ), $data['singular'] ),
            'new_item'      => sprintf( __( 'New %s', 'interslide-codex' ), $data['singular'] ),
            'view_item'     => sprintf( __( 'View %s', 'interslide-codex' ), $data['singular'] ),
            'search_items'  => sprintf( __( 'Search %s', 'interslide-codex' ), $data['plural'] ),
        );

        register_post_type(
            $slug,
            array(
                'labels'       => $labels,
                'public'       => (bool) $data['public'],
                'hierarchical' => (bool) $data['hierarchical'],
                'supports'     => $data['supports'],
                'show_in_rest' => true,
                'rewrite'      => array( 'slug' => $slug ),
            )
        );
    }

    $taxonomies = interslide_codex_get_taxonomies();
    foreach ( $taxonomies as $slug => $data ) {
        $labels = array(
            'name'          => $data['plural'],
            'singular_name' => $data['singular'],
            'search_items'  => sprintf( __( 'Search %s', 'interslide-codex' ), $data['plural'] ),
            'all_items'     => sprintf( __( 'All %s', 'interslide-codex' ), $data['plural'] ),
            'edit_item'     => sprintf( __( 'Edit %s', 'interslide-codex' ), $data['singular'] ),
            'update_item'   => sprintf( __( 'Update %s', 'interslide-codex' ), $data['singular'] ),
            'add_new_item'  => sprintf( __( 'Add New %s', 'interslide-codex' ), $data['singular'] ),
            'new_item_name' => sprintf( __( 'New %s Name', 'interslide-codex' ), $data['singular'] ),
        );

        register_taxonomy(
            $slug,
            $data['object_type'],
            array(
                'labels'       => $labels,
                'public'       => (bool) $data['public'],
                'hierarchical' => (bool) $data['hierarchical'],
                'show_in_rest' => true,
                'rewrite'      => array( 'slug' => $slug ),
            )
        );
    }
}

function interslide_codex_register_menu() {
    add_menu_page(
        'Interslide Codex',
        'Interslide Codex',
        INTERSLIDE_CODEX_CAPABILITY,
        'interslide-codex',
        'interslide_codex_render_page',
        'dashicons-category',
        80
    );
}

function interslide_codex_render_page() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'interslide-codex' ) );
    }

    $notice = isset( $_GET['interslide_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['interslide_notice'] ) ) : '';
    $status = isset( $_GET['interslide_status'] ) ? sanitize_text_field( wp_unslash( $_GET['interslide_status'] ) ) : '';

    if ( $notice ) {
        $class = ( 'success' === $status ) ? 'notice notice-success' : 'notice notice-error';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $notice ) . '</p></div>';
    }

    $post_types     = interslide_codex_get_post_types();
    $taxonomies     = interslide_codex_get_taxonomies();
    $all_post_types = get_post_types( array(), 'objects' );
    $support_items  = interslide_codex_support_options();

    if ( ! is_array( $all_post_types ) ) {
        $all_post_types = array();
    }

    ksort( $all_post_types );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Interslide Codex', 'interslide-codex' ) . '</h1>';
    echo '<p>' . esc_html__( 'Create, rename, or delete custom post types and taxonomies. Built-in and third-party items are listed for reference but can only be edited if created here.', 'interslide-codex' ) . '</p>';
    echo '<p><strong>' . esc_html__( 'Field help:', 'interslide-codex' ) . '</strong> ' . esc_html__( 'Slug is the unique identifier used in URLs and code (lowercase, no spaces). Singular is the label for one item (e.g., "Project"). Plural is the label for multiple items (e.g., "Projects").', 'interslide-codex' ) . '</p>';

    echo '<h2>' . esc_html__( 'Post Types', 'interslide-codex' ) . '</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Slug', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Singular', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Plural', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Supports', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Visibility', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'interslide-codex' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if ( empty( $all_post_types ) ) {
        echo '<tr><td colspan="6">' . esc_html__( 'No post types found.', 'interslide-codex' ) . '</td></tr>';
    } else {
        foreach ( $all_post_types as $slug => $post_type_obj ) {
            $managed = isset( $post_types[ $slug ] );
            $data    = $managed ? $post_types[ $slug ] : array(
                'singular' => $post_type_obj->labels->singular_name,
                'plural'   => $post_type_obj->labels->name,
                'supports' => array(),
                'public'   => ! empty( $post_type_obj->public ),
            );
            echo '<tr>';
            echo '<td>' . esc_html( $slug ) . '</td>';
            echo '<td>' . esc_html( $data['singular'] ) . '</td>';
            echo '<td>' . esc_html( $data['plural'] ) . '</td>';
            $supports = $managed ? $data['supports'] : get_all_post_type_supports( $slug );
            $supports = is_array( $supports ) ? array_keys( array_filter( $supports ) ) : array();
            echo '<td>' . esc_html( $supports ? implode( ', ', $supports ) : __( 'None', 'interslide-codex' ) ) . '</td>';
            echo '<td>' . esc_html( $data['public'] ? __( 'Public', 'interslide-codex' ) : __( 'Private', 'interslide-codex' ) ) . '</td>';
            echo '<td>';

            if ( $managed ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:8px;">';
                wp_nonce_field( 'interslide_codex_update_post_type' );
                echo '<input type="hidden" name="action" value="interslide_codex_update_post_type" />';
                echo '<input type="hidden" name="original_slug" value="' . esc_attr( $slug ) . '" />';
                echo '<input type="text" name="slug" value="' . esc_attr( $slug ) . '" placeholder="slug" /> ';
                echo '<input type="text" name="singular" value="' . esc_attr( $data['singular'] ) . '" placeholder="singular" /> ';
                echo '<input type="text" name="plural" value="' . esc_attr( $data['plural'] ) . '" placeholder="plural" /> ';

                foreach ( $support_items as $support_key => $support_label ) {
                    $checked = in_array( $support_key, $data['supports'], true ) ? 'checked' : '';
                    echo '<label style="margin-right:8px;"><input type="checkbox" name="supports[]" value="' . esc_attr( $support_key ) . '" ' . $checked . ' />' . esc_html( $support_label ) . '</label>';
                }

                $public_checked       = $data['public'] ? 'checked' : '';
                $hierarchical_checked = $data['hierarchical'] ? 'checked' : '';
                echo '<label style="margin-right:8px;"><input type="checkbox" name="public" value="1" ' . $public_checked . ' />' . esc_html__( 'Public', 'interslide-codex' ) . '</label>';
                echo '<label style="margin-right:8px;"><input type="checkbox" name="hierarchical" value="1" ' . $hierarchical_checked . ' />' . esc_html__( 'Hierarchical', 'interslide-codex' ) . '</label>';
                echo '<button class="button" type="submit">' . esc_html__( 'Save', 'interslide-codex' ) . '</button>';
                echo '</form>';

                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'interslide_codex_delete_post_type' );
                echo '<input type="hidden" name="action" value="interslide_codex_delete_post_type" />';
                echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
                echo '<button class="button button-link-delete" type="submit" onclick="return confirm(\'' . esc_js( __( 'Delete this custom post type?', 'interslide-codex' ) ) . '\')">' . esc_html__( 'Delete', 'interslide-codex' ) . '</button>';
                echo '</form>';
            } else {
                echo esc_html__( 'Managed elsewhere', 'interslide-codex' );
            }

            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';

    echo '<h3>' . esc_html__( 'Add a Custom Post Type', 'interslide-codex' ) . '</h3>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'interslide_codex_add_post_type' );
    echo '<input type="hidden" name="action" value="interslide_codex_add_post_type" />';
    echo '<input type="text" name="slug" placeholder="slug" required /> ';
    echo '<input type="text" name="singular" placeholder="singular" required /> ';
    echo '<input type="text" name="plural" placeholder="plural" required /> ';
    foreach ( $support_items as $support_key => $support_label ) {
        echo '<label style="margin-right:8px;"><input type="checkbox" name="supports[]" value="' . esc_attr( $support_key ) . '" />' . esc_html( $support_label ) . '</label>';
    }
    echo '<label style="margin-right:8px;"><input type="checkbox" name="public" value="1" checked />' . esc_html__( 'Public', 'interslide-codex' ) . '</label>';
    echo '<label style="margin-right:8px;"><input type="checkbox" name="hierarchical" value="1" />' . esc_html__( 'Hierarchical', 'interslide-codex' ) . '</label>';
    echo '<button class="button button-primary" type="submit">' . esc_html__( 'Add Post Type', 'interslide-codex' ) . '</button>';
    echo '</form>';

    echo '<hr />';

    echo '<h2>' . esc_html__( 'Custom Taxonomies (created by this plugin)', 'interslide-codex' ) . '</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Slug', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Singular', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Plural', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Applies to', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Visibility', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'interslide-codex' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if ( empty( $taxonomies ) ) {
        echo '<tr><td colspan="6">' . esc_html__( 'No custom taxonomies created yet.', 'interslide-codex' ) . '</td></tr>';
    } else {
        foreach ( $taxonomies as $slug => $data ) {
            echo '<tr>';
            echo '<td>' . esc_html( $slug ) . '</td>';
            echo '<td>' . esc_html( $data['singular'] ) . '</td>';
            echo '<td>' . esc_html( $data['plural'] ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', $data['object_type'] ) ) . '</td>';
            echo '<td>' . esc_html( $data['public'] ? __( 'Public', 'interslide-codex' ) : __( 'Private', 'interslide-codex' ) ) . '</td>';
            echo '<td>';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:8px;">';
            wp_nonce_field( 'interslide_codex_update_taxonomy' );
            echo '<input type="hidden" name="action" value="interslide_codex_update_taxonomy" />';
            echo '<input type="hidden" name="original_slug" value="' . esc_attr( $slug ) . '" />';
            echo '<input type="text" name="slug" value="' . esc_attr( $slug ) . '" placeholder="slug" /> ';
            echo '<input type="text" name="singular" value="' . esc_attr( $data['singular'] ) . '" placeholder="singular" /> ';
            echo '<input type="text" name="plural" value="' . esc_attr( $data['plural'] ) . '" placeholder="plural" /> ';

            foreach ( $all_post_types as $post_type_key => $post_type_obj ) {
                $checked = in_array( $post_type_key, $data['object_type'], true ) ? 'checked' : '';
                echo '<label style="margin-right:8px;"><input type="checkbox" name="object_type[]" value="' . esc_attr( $post_type_key ) . '" ' . $checked . ' />' . esc_html( $post_type_obj->labels->name ) . '</label>';
            }

            $public_checked       = $data['public'] ? 'checked' : '';
            $hierarchical_checked = $data['hierarchical'] ? 'checked' : '';
            echo '<label style="margin-right:8px;"><input type="checkbox" name="public" value="1" ' . $public_checked . ' />' . esc_html__( 'Public', 'interslide-codex' ) . '</label>';
            echo '<label style="margin-right:8px;"><input type="checkbox" name="hierarchical" value="1" ' . $hierarchical_checked . ' />' . esc_html__( 'Hierarchical', 'interslide-codex' ) . '</label>';
            echo '<button class="button" type="submit">' . esc_html__( 'Save', 'interslide-codex' ) . '</button>';
            echo '</form>';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'interslide_codex_delete_taxonomy' );
            echo '<input type="hidden" name="action" value="interslide_codex_delete_taxonomy" />';
            echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
            echo '<button class="button button-link-delete" type="submit" onclick="return confirm(\'' . esc_js( __( 'Delete this custom taxonomy?', 'interslide-codex' ) ) . '\')">' . esc_html__( 'Delete', 'interslide-codex' ) . '</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';

    echo '<h3>' . esc_html__( 'Add a Custom Taxonomy', 'interslide-codex' ) . '</h3>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'interslide_codex_add_taxonomy' );
    echo '<input type="hidden" name="action" value="interslide_codex_add_taxonomy" />';
    echo '<input type="text" name="slug" placeholder="slug" required /> ';
    echo '<input type="text" name="singular" placeholder="singular" required /> ';
    echo '<input type="text" name="plural" placeholder="plural" required /> ';
    echo '<label style="margin-right:8px;">' . esc_html__( 'Applies to:', 'interslide-codex' ) . '</label>';
    foreach ( $all_post_types as $post_type_key => $post_type_obj ) {
        echo '<label style="margin-right:8px;"><input type="checkbox" name="object_type[]" value="' . esc_attr( $post_type_key ) . '" />' . esc_html( $post_type_obj->labels->name ) . '</label>';
    }
    echo '<label style="margin-right:8px;"><input type="checkbox" name="public" value="1" checked />' . esc_html__( 'Public', 'interslide-codex' ) . '</label>';
    echo '<label style="margin-right:8px;"><input type="checkbox" name="hierarchical" value="1" />' . esc_html__( 'Hierarchical', 'interslide-codex' ) . '</label>';
    echo '<button class="button button-primary" type="submit">' . esc_html__( 'Add Taxonomy', 'interslide-codex' ) . '</button>';
    echo '</form>';

    echo '</div>';
}

function interslide_codex_handle_add_post_type() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_add_post_type' );

    $slug       = interslide_codex_clean_slug( $_POST, 'slug' );
    $singular   = interslide_codex_clean_text( $_POST, 'singular' );
    $plural     = interslide_codex_clean_text( $_POST, 'plural' );
    $supports   = interslide_codex_clean_supports( $_POST );
    $public     = isset( $_POST['public'] );
    $hierarchy  = isset( $_POST['hierarchical'] );

    if ( ! $slug || ! $singular || ! $plural ) {
        interslide_codex_redirect_with_notice( 'Please fill in all required fields.', 'error' );
    }

    if ( post_type_exists( $slug ) ) {
        interslide_codex_redirect_with_notice( 'This post type slug is already registered.', 'error' );
    }

    $post_types            = interslide_codex_get_post_types();
    $post_types[ $slug ]   = array(
        'singular'     => $singular,
        'plural'       => $plural,
        'supports'     => $supports,
        'public'       => $public,
        'hierarchical' => $hierarchy,
    );

    interslide_codex_save_post_types( $post_types );
    interslide_codex_redirect_with_notice( 'Custom post type created.', 'success' );
}

function interslide_codex_handle_update_post_type() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_update_post_type' );

    $original = interslide_codex_clean_slug( $_POST, 'original_slug' );
    $slug     = interslide_codex_clean_slug( $_POST, 'slug' );
    $singular = interslide_codex_clean_text( $_POST, 'singular' );
    $plural   = interslide_codex_clean_text( $_POST, 'plural' );
    $supports = interslide_codex_clean_supports( $_POST );
    $public   = isset( $_POST['public'] );
    $hierarchy = isset( $_POST['hierarchical'] );

    $post_types = interslide_codex_get_post_types();

    if ( ! $original || ! isset( $post_types[ $original ] ) ) {
        interslide_codex_redirect_with_notice( 'Post type not found.', 'error' );
    }

    if ( ! $slug || ! $singular || ! $plural ) {
        interslide_codex_redirect_with_notice( 'Please fill in all required fields.', 'error' );
    }

    if ( $slug !== $original && post_type_exists( $slug ) ) {
        interslide_codex_redirect_with_notice( 'This post type slug is already registered.', 'error' );
    }

    $post_types[ $original ] = array(
        'singular'     => $singular,
        'plural'       => $plural,
        'supports'     => $supports,
        'public'       => $public,
        'hierarchical' => $hierarchy,
    );

    if ( $slug !== $original ) {
        $post_types[ $slug ] = $post_types[ $original ];
        unset( $post_types[ $original ] );
    }

    interslide_codex_save_post_types( $post_types );
    interslide_codex_redirect_with_notice( 'Custom post type updated.', 'success' );
}

function interslide_codex_handle_delete_post_type() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_delete_post_type' );

    $slug       = interslide_codex_clean_slug( $_POST, 'slug' );
    $post_types = interslide_codex_get_post_types();

    if ( ! $slug || ! isset( $post_types[ $slug ] ) ) {
        interslide_codex_redirect_with_notice( 'Post type not found.', 'error' );
    }

    unset( $post_types[ $slug ] );
    interslide_codex_save_post_types( $post_types );
    interslide_codex_redirect_with_notice( 'Custom post type deleted.', 'success' );
}

function interslide_codex_handle_add_taxonomy() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_add_taxonomy' );

    $slug         = interslide_codex_clean_slug( $_POST, 'slug' );
    $singular     = interslide_codex_clean_text( $_POST, 'singular' );
    $plural       = interslide_codex_clean_text( $_POST, 'plural' );
    $object_types = interslide_codex_clean_object_types( $_POST );
    $public       = isset( $_POST['public'] );
    $hierarchy    = isset( $_POST['hierarchical'] );

    if ( ! $slug || ! $singular || ! $plural ) {
        interslide_codex_redirect_with_notice( 'Please fill in all required fields.', 'error' );
    }

    if ( empty( $object_types ) ) {
        interslide_codex_redirect_with_notice( 'Select at least one post type for this taxonomy.', 'error' );
    }

    if ( taxonomy_exists( $slug ) ) {
        interslide_codex_redirect_with_notice( 'This taxonomy slug is already registered.', 'error' );
    }

    $taxonomies            = interslide_codex_get_taxonomies();
    $taxonomies[ $slug ]   = array(
        'singular'     => $singular,
        'plural'       => $plural,
        'object_type'  => $object_types,
        'public'       => $public,
        'hierarchical' => $hierarchy,
    );

    interslide_codex_save_taxonomies( $taxonomies );
    interslide_codex_redirect_with_notice( 'Custom taxonomy created.', 'success' );
}

function interslide_codex_handle_update_taxonomy() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_update_taxonomy' );

    $original     = interslide_codex_clean_slug( $_POST, 'original_slug' );
    $slug         = interslide_codex_clean_slug( $_POST, 'slug' );
    $singular     = interslide_codex_clean_text( $_POST, 'singular' );
    $plural       = interslide_codex_clean_text( $_POST, 'plural' );
    $object_types = interslide_codex_clean_object_types( $_POST );
    $public       = isset( $_POST['public'] );
    $hierarchy    = isset( $_POST['hierarchical'] );

    $taxonomies = interslide_codex_get_taxonomies();

    if ( ! $original || ! isset( $taxonomies[ $original ] ) ) {
        interslide_codex_redirect_with_notice( 'Taxonomy not found.', 'error' );
    }

    if ( ! $slug || ! $singular || ! $plural ) {
        interslide_codex_redirect_with_notice( 'Please fill in all required fields.', 'error' );
    }

    if ( empty( $object_types ) ) {
        interslide_codex_redirect_with_notice( 'Select at least one post type for this taxonomy.', 'error' );
    }

    if ( $slug !== $original && taxonomy_exists( $slug ) ) {
        interslide_codex_redirect_with_notice( 'This taxonomy slug is already registered.', 'error' );
    }

    $taxonomies[ $original ] = array(
        'singular'     => $singular,
        'plural'       => $plural,
        'object_type'  => $object_types,
        'public'       => $public,
        'hierarchical' => $hierarchy,
    );

    if ( $slug !== $original ) {
        $taxonomies[ $slug ] = $taxonomies[ $original ];
        unset( $taxonomies[ $original ] );
    }

    interslide_codex_save_taxonomies( $taxonomies );
    interslide_codex_redirect_with_notice( 'Custom taxonomy updated.', 'success' );
}

function interslide_codex_handle_delete_taxonomy() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_delete_taxonomy' );

    $slug       = interslide_codex_clean_slug( $_POST, 'slug' );
    $taxonomies = interslide_codex_get_taxonomies();

    if ( ! $slug || ! isset( $taxonomies[ $slug ] ) ) {
        interslide_codex_redirect_with_notice( 'Taxonomy not found.', 'error' );
    }

    unset( $taxonomies[ $slug ] );
    interslide_codex_save_taxonomies( $taxonomies );
    interslide_codex_redirect_with_notice( 'Custom taxonomy deleted.', 'success' );
}

function interslide_codex_get_post_types() {
    $post_types = get_option( INTERSLIDE_CODEX_OPTION_POST_TYPES, array() );
    if ( ! is_array( $post_types ) ) {
        return array();
    }

    foreach ( $post_types as $slug => $data ) {
        $post_types[ $slug ] = array(
            'singular'     => isset( $data['singular'] ) ? $data['singular'] : $slug,
            'plural'       => isset( $data['plural'] ) ? $data['plural'] : $slug,
            'supports'     => isset( $data['supports'] ) ? (array) $data['supports'] : array( 'title', 'editor' ),
            'public'       => ! empty( $data['public'] ),
            'hierarchical' => ! empty( $data['hierarchical'] ),
        );
    }

    return $post_types;
}

function interslide_codex_save_post_types( $post_types ) {
    update_option( INTERSLIDE_CODEX_OPTION_POST_TYPES, $post_types );
}

function interslide_codex_get_taxonomies() {
    $taxonomies = get_option( INTERSLIDE_CODEX_OPTION_TAXONOMIES, array() );
    if ( ! is_array( $taxonomies ) ) {
        return array();
    }

    foreach ( $taxonomies as $slug => $data ) {
        $taxonomies[ $slug ] = array(
            'singular'     => isset( $data['singular'] ) ? $data['singular'] : $slug,
            'plural'       => isset( $data['plural'] ) ? $data['plural'] : $slug,
            'object_type'  => isset( $data['object_type'] ) ? array_values( (array) $data['object_type'] ) : array( 'post' ),
            'public'       => ! empty( $data['public'] ),
            'hierarchical' => ! empty( $data['hierarchical'] ),
        );
    }

    return $taxonomies;
}

function interslide_codex_save_taxonomies( $taxonomies ) {
    update_option( INTERSLIDE_CODEX_OPTION_TAXONOMIES, $taxonomies );
}

function interslide_codex_support_options() {
    return array(
        'title'   => __( 'Title', 'interslide-codex' ),
        'editor'  => __( 'Editor', 'interslide-codex' ),
        'excerpt' => __( 'Excerpt', 'interslide-codex' ),
        'thumbnail' => __( 'Featured Image', 'interslide-codex' ),
    );
}

function interslide_codex_clean_slug( $source, $key ) {
    if ( empty( $source[ $key ] ) ) {
        return '';
    }

    return sanitize_key( wp_unslash( $source[ $key ] ) );
}

function interslide_codex_clean_text( $source, $key ) {
    if ( empty( $source[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field( wp_unslash( $source[ $key ] ) );
}

function interslide_codex_clean_supports( $source ) {
    $supports = array();
    if ( ! empty( $source['supports'] ) && is_array( $source['supports'] ) ) {
        foreach ( $source['supports'] as $support ) {
            $support = sanitize_key( wp_unslash( $support ) );
            if ( $support ) {
                $supports[] = $support;
            }
        }
    }

    if ( empty( $supports ) ) {
        $supports = array( 'title', 'editor' );
    }

    return array_values( array_unique( $supports ) );
}

function interslide_codex_clean_object_types( $source ) {
    $object_types = array();
    if ( ! empty( $source['object_type'] ) && is_array( $source['object_type'] ) ) {
        foreach ( $source['object_type'] as $object_type ) {
            $object_type = sanitize_key( wp_unslash( $object_type ) );
            if ( $object_type ) {
                $object_types[] = $object_type;
            }
        }
    }

    return array_values( array_unique( $object_types ) );
}

function interslide_codex_redirect_with_notice( $message, $status ) {
    $url = add_query_arg(
        array(
            'page'              => 'interslide-codex',
            'interslide_notice' => rawurlencode( $message ),
            'interslide_status' => $status,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $url );
    exit;
}
