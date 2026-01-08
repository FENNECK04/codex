<?php
/**
 * Plugin Name: Interslide Codex
 * Description: Manage WordPress categories and taxonomies with quick rename and delete tools.
 * Version: 1.0.0
 * Author: Interslide
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'INTERSLIDE_CODEX_CAPABILITY' ) ) {
    define( 'INTERSLIDE_CODEX_CAPABILITY', 'manage_categories' );
}

add_action( 'admin_menu', 'interslide_codex_register_menu' );
add_action( 'admin_post_interslide_codex_update_term', 'interslide_codex_handle_update_term' );
add_action( 'admin_post_interslide_codex_delete_term', 'interslide_codex_handle_delete_term' );

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

    $selected_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'category';
    $taxonomies        = get_taxonomies( array( 'public' => true ), 'objects' );

    if ( ! isset( $taxonomies[ $selected_taxonomy ] ) ) {
        $selected_taxonomy = 'category';
    }

    $notice = isset( $_GET['interslide_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['interslide_notice'] ) ) : '';
    $status = isset( $_GET['interslide_status'] ) ? sanitize_text_field( wp_unslash( $_GET['interslide_status'] ) ) : '';

    if ( $notice ) {
        $class = ( 'success' === $status ) ? 'notice notice-success' : 'notice notice-error';
        echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $notice ) . '</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Interslide Codex', 'interslide-codex' ) . '</h1>';
    echo '<p>' . esc_html__( 'Select a taxonomy to rename or delete terms.', 'interslide-codex' ) . '</p>';

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="interslide-codex" />';
    echo '<label for="taxonomy">' . esc_html__( 'Taxonomy:', 'interslide-codex' ) . '</label> ';
    echo '<select id="taxonomy" name="taxonomy" onchange="this.form.submit()">';
    foreach ( $taxonomies as $taxonomy_key => $taxonomy_obj ) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr( $taxonomy_key ),
            selected( $taxonomy_key, $selected_taxonomy, false ),
            esc_html( $taxonomy_obj->labels->name )
        );
    }
    echo '</select>';
    echo '</form>';

    $terms = get_terms(
        array(
            'taxonomy'   => $selected_taxonomy,
            'hide_empty' => false,
        )
    );

    if ( is_wp_error( $terms ) ) {
        echo '<p>' . esc_html__( 'Unable to load terms.', 'interslide-codex' ) . '</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Name', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Slug', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Count', 'interslide-codex' ) . '</th>';
    echo '<th>' . esc_html__( 'Actions', 'interslide-codex' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if ( empty( $terms ) ) {
        echo '<tr><td colspan="4">' . esc_html__( 'No terms found for this taxonomy.', 'interslide-codex' ) . '</td></tr>';
    } else {
        foreach ( $terms as $term ) {
            echo '<tr>';
            echo '<td>' . esc_html( $term->name ) . '</td>';
            echo '<td>' . esc_html( $term->slug ) . '</td>';
            echo '<td>' . esc_html( (string) $term->count ) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block; margin-right:12px;">';
            wp_nonce_field( 'interslide_codex_update_term' );
            echo '<input type="hidden" name="action" value="interslide_codex_update_term" />';
            echo '<input type="hidden" name="taxonomy" value="' . esc_attr( $selected_taxonomy ) . '" />';
            echo '<input type="hidden" name="term_id" value="' . esc_attr( (string) $term->term_id ) . '" />';
            echo '<input type="text" name="term_name" value="' . esc_attr( $term->name ) . '" />';
            echo '<button class="button" type="submit">' . esc_html__( 'Rename', 'interslide-codex' ) . '</button>';
            echo '</form>';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
            wp_nonce_field( 'interslide_codex_delete_term' );
            echo '<input type="hidden" name="action" value="interslide_codex_delete_term" />';
            echo '<input type="hidden" name="taxonomy" value="' . esc_attr( $selected_taxonomy ) . '" />';
            echo '<input type="hidden" name="term_id" value="' . esc_attr( (string) $term->term_id ) . '" />';
            echo '<button class="button button-link-delete" type="submit" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this term?', 'interslide-codex' ) ) . '\')">' . esc_html__( 'Delete', 'interslide-codex' ) . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

function interslide_codex_handle_update_term() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_update_term' );

    $taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
    $term_id   = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
    $term_name = isset( $_POST['term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';

    if ( ! $taxonomy || ! $term_id || '' === $term_name ) {
        interslide_codex_redirect_with_notice( 'Invalid term data provided.', 'error', $taxonomy );
    }

    $result = wp_update_term( $term_id, $taxonomy, array( 'name' => $term_name ) );

    if ( is_wp_error( $result ) ) {
        interslide_codex_redirect_with_notice( $result->get_error_message(), 'error', $taxonomy );
    }

    interslide_codex_redirect_with_notice( 'Term renamed successfully.', 'success', $taxonomy );
}

function interslide_codex_handle_delete_term() {
    if ( ! current_user_can( INTERSLIDE_CODEX_CAPABILITY ) ) {
        wp_die( esc_html__( 'You do not have permission to perform this action.', 'interslide-codex' ) );
    }

    check_admin_referer( 'interslide_codex_delete_term' );

    $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
    $term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

    if ( ! $taxonomy || ! $term_id ) {
        interslide_codex_redirect_with_notice( 'Invalid term data provided.', 'error', $taxonomy );
    }

    $result = wp_delete_term( $term_id, $taxonomy );

    if ( is_wp_error( $result ) ) {
        interslide_codex_redirect_with_notice( $result->get_error_message(), 'error', $taxonomy );
    }

    interslide_codex_redirect_with_notice( 'Term deleted successfully.', 'success', $taxonomy );
}

function interslide_codex_redirect_with_notice( $message, $status, $taxonomy ) {
    $url = add_query_arg(
        array(
            'page'              => 'interslide-codex',
            'taxonomy'          => $taxonomy,
            'interslide_notice' => rawurlencode( $message ),
            'interslide_status' => $status,
        ),
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $url );
    exit;
}
