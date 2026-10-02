<?php
/**
 * Desinstalación do plugin Dicionario RAG
 *
 * Borra da base de datos os transients da caché de consultas,
 * do límite de peticións e do authToken.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_asg_drag_') . '%',
        $wpdb->esc_like('_transient_timeout_asg_drag_') . '%'
    )
);
