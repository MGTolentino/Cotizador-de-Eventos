<?php
/**
 * Plugin Name: Cotizador Eventos
 * Description: Plugin para filtrar y mostrar listados de eventos con sus reservas asociadas.
 * Version: 2.3.2
 * Author: Miguel Tolentino
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Register extension directory.
add_filter(
    'hivepress/v1/extensions',
    function( $extensions ) {
        $extensions[] = __DIR__;
        return $extensions;
    }
);

// Include necessary HivePress helpers and classes
use HivePress\Helpers as hp;
use HivePress\Models;
use HivePress\Forms;
use HivePress\Blocks;

// Initialize the plugin after HivePress is loaded
add_action('plugins_loaded', function() {
    if (function_exists('hivepress') && class_exists('HivePress\Models\Listing')) {
        require_once __DIR__ . '/includes/class-cotizador-eventos.php';
        $cotizador_eventos = new Cotizador_Eventos();
        $cotizador_eventos->init();
    }
}, 20);

// Definir constantes
define('COTIZADOR_EVENTOS_PATH', plugin_dir_path(__FILE__));
define('COTIZADOR_EVENTOS_URL', plugin_dir_url(__FILE__));

// Incluir archivos necesarios
require_once COTIZADOR_EVENTOS_PATH . 'includes/class-cotizador-eventos.php';
require_once COTIZADOR_EVENTOS_PATH . 'includes/class-cotizador-eventos-widget.php';
require_once COTIZADOR_EVENTOS_PATH . 'includes/functions.php';


// Inicializar el plugin
function cotizador_eventos_init() {
    $cotizador_eventos = new Cotizador_Eventos();
    $cotizador_eventos->init();
    

}

// Registrar el widget
function cotizador_eventos_register_widget() {
    register_widget('Cotizador_Eventos_Widget');
}
add_action('widgets_init', 'cotizador_eventos_register_widget');

add_action('init', function() {
    add_rewrite_rule('^make-booking/?$', 'index.php?booking_make=1', 'top');
});

add_filter('query_vars', function($query_vars) {
    $query_vars[] = 'booking_make';
    return $query_vars;
});

add_action('template_redirect', function() {
    if (get_query_var('booking_make')) {
        // Aquí manejaremos la lógica de la página de reserva
        do_action('cotizador_eventos_booking_make');
        exit;
    }
});

function clean_old_auto_draft_bookings() {
    $old_auto_drafts = get_posts(array(
        'post_type' => 'hp_booking',
        'post_status' => 'auto-draft',
        'date_query' => array(
            'before' => '24 hours ago'
        ),
        'posts_per_page' => -1
    ));

    foreach ($old_auto_drafts as $draft) {
        wp_delete_post($draft->ID, true);
    }
}
add_action('wp_scheduled_delete', 'clean_old_auto_draft_bookings');

add_action('template_redirect', function() {
    if (is_page('make-booking') && isset($_GET['booking_id'])) {
        $booking_id = absint($_GET['booking_id']);
        $booking = Models\Booking::query()->get_by_id($booking_id);
        
        if (!$booking || $booking->get_status() !== 'auto-draft' || $booking->get_user__id() !== get_current_user_id()) {
            wp_die(hivepress()->translator->get_string('no_listings_found'));
        }

        $listing = $booking->get_listing();
        if (!$listing || $listing->get_status() !== 'publish' || !hivepress()->booking->is_booking_enabled($listing)) {
            wp_die(hivepress()->translator->get_string('no_listings_found'));
        }

        // Establecer el contexto para la página de detalles
        hivepress()->request->set_context('booking', $booking);
        hivepress()->request->set_context('listing', $listing);
    }
});

add_filter(
    'hivepress/v1/components',
    function($components) {
        return array_merge(
            $components,
            ['favorites_handler' => 'HivePress\Components\Favorites_Handler']
        );
    }
);
