<?php

use HivePress\Helpers as hp;
use HivePress\Models;
use HivePress\Forms;
use HivePress\Blocks;

class Cotizador_Eventos {
    private $listing_reservations = array();

    public function __construct() {
        $this->load_listing_reservations();
    }

    public function init() {
        add_shortcode('cotizador_eventos', array($this, 'shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_filter_eventos', array($this, 'ajax_filter_eventos'));
        add_action('wp_ajax_nopriv_filter_eventos', array($this, 'ajax_filter_eventos'));
        add_action('wp_ajax_get_category_filters', array($this, 'get_category_filters'));
        add_action('wp_ajax_nopriv_get_category_filters', array($this, 'get_category_filters'));
        add_action('wp_ajax_open_booking_popup', array($this, 'open_booking_popup'));
        add_action('wp_ajax_nopriv_open_booking_popup', array($this, 'open_booking_popup'));
        add_filter('cotizador_eventos/booking_form_blocks', [$this, 'alter_booking_form_blocks'], 10, 2);
        add_action('cotizador_eventos_booking_make', [$this, 'handle_booking_make']);
        add_action('wp_ajax_booking_make_request', array($this, 'handle_booking_make_request'));
        add_action('wp_ajax_nopriv_booking_make_request', array($this, 'handle_booking_make_request'));
        add_action('wp_ajax_submit_visita_form', array($this, 'handle_visita_form_submission'));
        add_action('wp_ajax_nopriv_submit_visita_form', array($this, 'handle_visita_form_submission'));
        add_action('wp_ajax_get_listing_booking_metadata', array($this, 'get_listing_booking_metadata'));
        add_action('wp_ajax_nopriv_get_listing_booking_metadata', array($this, 'get_listing_booking_metadata'));
        add_action('wp_ajax_get_disabled_dates', array($this, 'get_disabled_dates_ajax'));
        add_action('wp_ajax_nopriv_get_disabled_dates', array($this, 'get_disabled_dates_ajax'));
        add_action('wp_ajax_submit_bloqueo_form', array($this, 'handle_bloqueo_form_submission'));
        add_action('wp_ajax_nopriv_submit_bloqueo_form', array($this, 'handle_bloqueo_form_submission'));

    }


    private function load_listing_reservations() {
        global $wpdb;
        
        $bookings = $wpdb->get_results(
            "SELECT p.post_parent, pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'hp_booking'
			AND p.post_status != 'trash'
            AND pm.meta_key = 'hp_start_time'"
        );
        
        foreach ($bookings as $booking) {
            $listing_id = $booking->post_parent;
            $timestamp = intval($booking->meta_value);
            if ($timestamp > 0) {
                $date = date('Y-m-d', $timestamp);
                if (!isset($this->listing_reservations[$listing_id])) {
                    $this->listing_reservations[$listing_id] = array();
                }
                $this->listing_reservations[$listing_id][] = $date;
            }
        }
    }

   public function enqueue_scripts() {
    // Verificar si estamos en una página que usa el shortcode
    if (!$this->should_load_assets()) {
        return;
    }

    // Estilos
    wp_enqueue_style('cotizador-eventos-style', COTIZADOR_EVENTOS_URL . 'css/style.css');
    wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.9');
    wp_enqueue_style('fancybox', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.css');

    // Scripts
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-slider');
    wp_enqueue_script('jquery-ui-datepicker');
    
    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.9', true);
    wp_enqueue_script('flatpickr-es', 'https://npmcdn.com/flatpickr/dist/l10n/es.js', array('flatpickr'), '4.6.9', true);
    wp_enqueue_script('fancybox', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js', array('jquery'), '4.0', true);
    
    wp_enqueue_script('cotizador-eventos-script', COTIZADOR_EVENTOS_URL . 'js/filter.js', array('jquery', 'jquery-ui-slider'), '1.0', true);
    wp_enqueue_script('cotizador-eventos-custom', COTIZADOR_EVENTOS_URL . 'js/custom-script.js', array('jquery', 'flatpickr', 'fancybox', 'cotizador-eventos-script'), '1.0', true);

    // Localizaciones
    wp_localize_script('cotizador-eventos-script', 'cotizador_eventos_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));

    wp_add_inline_script('flatpickr', 'flatpickr.localize(flatpickr.l10ns.es);');

    wp_localize_script('cotizador-eventos-custom', 'cotizador_eventos_data', array(
        'whatsapp_number' => '+528444550550',
        'user_id' => get_current_user_id(),
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('get_form_nonce')
    ));
}

private function should_load_assets() {
    // Si es la página principal
    if (is_front_page() || is_home()) {
        return true;
    }

    // Si es una página singular, verificar si tiene el shortcode
    if (is_singular()) {
        global $post;
        if (has_shortcode($post->post_content, 'cotizador_eventos')) {
            return true;
        }
    }

    return false;
}

public function shortcode() {
   ob_start();
   include COTIZADOR_EVENTOS_PATH . 'templates/filtro-listados.php';
   return ob_get_clean();
}

public function open_booking_popup() {
    // Verificar nonce y permisos aquí
    $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
    $listing = Models\Listing::query()->get_by_id($listing_id);
    
    if (!$listing) {
        wp_send_json_error('Listing not found');
    }

    // Establecer el contexto para el template
    hivepress()->request->set_context('listing', $listing);

    // Obtener el template
    ob_start();
    include get_stylesheet_directory() . '/hivepress/booking/custom-booking-form.php';
    $form_html = ob_get_clean();

    // Logging del contenido
    error_log('Form HTML length: ' . strlen($form_html));

    // Envolver el formulario en el contenedor del popup
    $output = '<div class="booking-popup">' . $form_html . '</div>';
    
    wp_send_json_success(array('html' => $form_html));
}

    private function get_booking_form_blocks($context) {
        $blocks = [];
    
        // Crear manualmente el bloque del formulario de reserva
        $blocks[] = [
            'name' => 'booking_make_form',
            'type' => 'booking_make_form',
            'context' => $context,
        ]; 
    
        // Aplicar filtros personalizados a los bloques
        $blocks = apply_filters('cotizador_eventos/booking_form_blocks', $blocks, $context);
    
        return $blocks;
    }

    public function alter_booking_form_blocks($blocks, $context) {
        
        
        if (isset($context['listing']) && $context['listing'] instanceof Models\Listing) {
            foreach ($blocks as &$block) {
                if ($block['name'] === 'booking_make_form') {
                    // Asegúrate de que el contexto del listing se pasa al bloque
                    $block['context']['listing'] = $context['listing'];
                    
                    // Aplica tus modificaciones personalizadas aquí
                    $block = $this->alter_booking_make_form($block);
                }
            }
        }
        
    
        return $blocks;
    }

    public function alter_booking_make_form($form, $listing) {
        $original_fields = $form->get_fields();
        $new_fields = [];
    
        // Copiar los campos originales
        foreach ($original_fields as $field_name => $field) {
            $new_fields[$field_name] = $field->get_args();
        }
    
        // Modificar o añadir campos según sea necesario
        $new_fields['listing'] = [
            'type'         => 'number',
            'display_type' => 'hidden',
            'default'      => $listing->get_id(),
            'required'     => true,
            '_order'       => 5,
        ];
    
        $new_fields['_dates'] = [
            'label'      => esc_html__('Fechas', 'hivepress-bookings'),
            'type'       => 'date_range',
            'offset'     => 15,
            'min_length' => 0,
            'required'   => true,
            '_separate'  => true,
            '_order'     => 10,
            'attributes' => [
                'data-component' => 'date-range',
            ],
        ];
    
        if (get_option('hp_booking_enable_quantity', true)) {
            $new_fields['_quantity'] = [
                'label'     => hivepress()->translator->get_string('places'),
                'type'      => 'number',
                'min_value' => max(1, $listing->get_booking_min_quantity()),
                'max_value' => $listing->get_booking_max_quantity(),
                'default'   => max(1, $listing->get_booking_min_quantity()),
                'required'  => true,
                '_separate' => true,
                '_order'    => 30,
            ];
        }
    
        if (get_option('hp_listing_allow_price_extras', true)) {
            $extras = get_post_meta($listing->get_id(), 'hp_price_extras', true);
            if (is_array($extras) && !empty($extras)) {
                $extras_options = [];
                $price_options = $this->get_price_options();
    
                foreach ($extras as $index => $item) {
                    $price_type = isset($item['type']) && isset($price_options[$item['type']]) ? $price_options[$item['type']] : $price_options[''];
                    $extras_options[$index] = sprintf(
                        '%s (%s %s)',
                        $item['name'],
                        hivepress()->woocommerce->format_price($item['price']),
                        $price_type
                    );
                }
    
                $new_fields['_extras'] = [
                    'label'     => esc_html__('Extras', 'hivepress-bookings'),
                    'type'      => 'checkboxes',
                    'options'   => $extras_options,
                    'optional'  => true,
                    '_separate' => true,
                    '_order'    => 100,
                ];
            }
        }
    
        // Crear un nuevo formulario con los campos modificados
        $new_form = new Forms\Booking_Make(
            [
                'model'   => 'booking',
                'listing' => $listing,
                'fields'  => $new_fields,
            ]
        );
    
        return $new_form;
    }
    
    protected function get_price_options() {
        $options = [];
    
        if (get_option('hp_booking_enable_quantity')) {
            $options = [
                ''             => esc_html_x('por cada unidad por día', 'pricing', 'hivepress-bookings'),
                'per_quantity' => esc_html_x('por cada unidad', 'pricing', 'hivepress-bookings'),
                'per_item'     => esc_html_x('por día', 'pricing', 'hivepress-bookings'),
            ];
        } else {
            $options[''] = esc_html_x('por día', 'pricing', 'hivepress-bookings');
        }
    
        $options['per_order'] = esc_html_x('por reserva', 'pricing', 'hivepress-bookings');
    
        return $options;
    }

    private function get_disabled_dates($listing) {
        $disabled_dates = [];
    
        $bookings = Models\Booking::query()->filter([
            'status__in' => ['draft', 'pending', 'publish', 'private'],
            'listing' => $listing->get_id(),
        ])->get();
    
        foreach ($bookings as $booking) {
            $start_time = $booking->get_start_time();
            $end_time = $booking->get_end_time();
            
            $current_date = strtotime(date('Y-m-d', $start_time));
            $end_date = strtotime(date('Y-m-d', $end_time));
    
            while ($current_date <= $end_date) {
                $disabled_dates[] = date('Y-m-d', $current_date);
                $current_date = strtotime('+1 day', $current_date);
            }
        }
    
        $blocked_dates = get_post_meta($listing->get_id(), 'blocked_dates', true);
        if (is_array($blocked_dates)) {
            foreach ($blocked_dates as $blocked_date) {
                $disabled_dates[] = date('Y-m-d', strtotime($blocked_date));
            }
        }
    
         // Asegúrate de que esta función devuelva un array de fechas en formato 'Y-m-d'
         return array_map(function($date) {
            return date('Y-m-d', strtotime($date));
        }, $disabled_dates);
    }

    public function get_category_filters() {
        $categoria = isset($_POST['categoria']) ? sanitize_text_field($_POST['categoria']) : '';
        
        $is_lugares_para_eventos = false;
        if (!empty($categoria)) {
            $term = get_term_by('slug', $categoria, 'hp_listing_category');
            if ($term && ($term->slug === 'lugares-para-eventos' || term_is_ancestor_of(get_term_by('slug', 'lugares-para-eventos', 'hp_listing_category'), $term, 'hp_listing_category'))) {
                $is_lugares_para_eventos = true;
            }
        }

        if ($is_lugares_para_eventos) {
            ob_start();
            include COTIZADOR_EVENTOS_PATH . 'templates/filtros-adicionales.php';
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_success(array('html' => ''));
        }
    }
	
	public function extend_search_to_tags($where, $wp_query) {
    global $wpdb;
    
    if ($search_term = $wp_query->get('post_title_like')) {
        // Escapar el término de búsqueda
        $search_term = $wpdb->esc_like($search_term);
        
        // Construir la consulta para buscar en título y tags
        $where .= $wpdb->prepare(
            " AND (
                {$wpdb->posts}.post_title LIKE %s
                OR {$wpdb->posts}.ID IN (
                    SELECT object_id
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy = 'hp_listing_tags'
                    AND t.name LIKE %s
                )
            )",
            '%' . $search_term . '%',
            '%' . $search_term . '%'
        );
    }
    return $where;
}

    public function ajax_filter_eventos() {
		
		// Obtener favoritos del usuario actual
			$favorite_ids = [];
			if (is_user_logged_in()) {
				$favorites = \HivePress\Models\Favorite::query()
					->filter(['user' => get_current_user_id()])
					->get();

				$favorite_ids = array_map(function($favorite) {
					return $favorite->get_listing__id();
				}, $favorites->serialize());
			}
		
        $categoria = isset($_POST['categoria']) ? sanitize_text_field($_POST['categoria']) : '';
        $ciudad = isset($_POST['ciudad']) ? sanitize_text_field($_POST['ciudad']) : '';
        $fecha = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : '';
        $nombre = isset($_POST['nombre']) ? sanitize_text_field($_POST['nombre']) : '';
        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;

        $args = array(
            'post_type' => 'hp_listing',
            'posts_per_page' => 20,
            'paged' => $paged,
            'post_status' => 'publish',
            'meta_query' => array(),
            'tax_query' => array(),
            'post__not_in' => array()
        );

        if (!empty($categoria)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'hp_listing_category',
                'field' => 'slug',
                'terms' => $categoria
            );
        }

        if (!empty($ciudad)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'hp_listing_ubicacion',
                'field' => 'name',
                'terms' => $ciudad
            );
        }

        if (!empty($nombre)) {
            $args['post_title_like'] = $nombre;
        }

        // Filtrar por fecha
        if (!empty($fecha)) {
            $fecha_seleccionada = date('Y-m-d', strtotime($fecha));
            foreach ($this->listing_reservations as $listing_id => $dates) {
                if (in_array($fecha_seleccionada, $dates)) {
                    $args['post__not_in'][] = $listing_id;
                }
            }
        }

        // Nuevos filtros
        $cantidad_invitados = isset($_POST['cantidad_invitados']) ? intval($_POST['cantidad_invitados']) : '';
        $min_precio = isset($_POST['min_precio']) ? floatval($_POST['min_precio']) : '';
        $max_precio = isset($_POST['max_precio']) ? floatval($_POST['max_precio']) : '';
        $servicios = isset($_POST['servicios']) ? array_map('sanitize_text_field', $_POST['servicios']) : array();

        if (!empty($cantidad_invitados)) {
            $args['meta_query'][] = array(
                'relation' => 'AND',
                array(
                    'key' => 'hp_capacidad_minima',
                    'value' => $cantidad_invitados,
                    'type' => 'NUMERIC',
                    'compare' => '<='
                ),
                array(
                    'key' => 'hp_square_footage',
                    'value' => $cantidad_invitados,
                    'type' => 'NUMERIC',
                    'compare' => '>='
                )
            );
        }

        if (!empty($min_precio)) {
            $args['meta_query'][] = array(
                'key' => 'hp_price',
                'value' => $min_precio,
                'type' => 'NUMERIC',
                'compare' => '>='
            );
        }

        if (!empty($max_precio)) {
            $args['meta_query'][] = array(
                'key' => 'hp_price',
                'value' => $max_precio,
                'type' => 'NUMERIC',
                'compare' => '<='
            );
        }

        if (!empty($servicios)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'hp_listing_incluye_servicio_luga',
                'field' => 'slug',
                'terms' => $servicios,
                'operator' => 'AND'
            );
        }

         // Ordenar por posts destacados primero
    $args['orderby'] = array(
        'meta_value_num' => 'DESC',
        'date' => 'DESC'
    );

    // Modificar la consulta principal para incluir posts con y sin hp_featured
    add_filter('posts_clauses', array($this, 'modify_featured_query'), 10, 2);

    // Añade este filtro justo después de definir los argumentos de WP_Query
add_filter('posts_where', array($this, 'extend_search_to_tags'), 10, 2);

        $query = new WP_Query($args);
		
		// Después de $query = new WP_Query($args);
remove_filter('posts_where', array($this, 'extend_search_to_tags'), 10);

        // Remueve el filtro después de la consulta
remove_filter('posts_where', 'title_filter', 10);

        // Remover el filtro después de la consulta
    remove_filter('posts_clauses', array($this, 'modify_featured_query'), 10);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $listing_id = get_the_ID();

                $precio = get_post_meta($listing_id, 'hp_price', true);
                $max_quantity = get_post_meta($listing_id, 'hp_square_footage', true);
                $min_quantity = get_post_meta($listing_id, 'hp_capacidad_minima', true);
                
                // Obtener todas las ciudades asociadas
                $ciudad_terms = get_the_terms($listing_id, 'hp_listing_ubicacion');
                $ciudades = array();
                if ($ciudad_terms && !is_wp_error($ciudad_terms)) {
                    foreach ($ciudad_terms as $term) {
                        if ($term->parent != 0) {
                            $ciudades[] = $term->name;
                        } elseif (empty($ciudades)) {
                            $ciudades[] = $term->name;
                        }
                    }
                }
                $ciudad = implode(', ', $ciudades);

                if (empty($ciudad)) {
                    $categoria_terms = get_the_terms($listing_id, 'hp_listing_category');
                    if ($categoria_terms && !is_wp_error($categoria_terms)) {
                        $ciudad = $categoria_terms[0]->name;
                    }
                }

                $template_args = [
					'listing_id' => $listing_id,
					'precio' => $precio,
					'favorite_ids' => $favorite_ids
				];
				include COTIZADOR_EVENTOS_PATH . 'templates/listing-item.php';
            }

            // Paginación
            $total_pages = $query->max_num_pages;
            if ($total_pages > 1) {
                echo '<div class="pagination">';
                for ($i = 1; $i <= min(6, $total_pages); $i++) {
                    echo '<a href="#" class="page-number' . ($i == $paged ? ' current' : '') . '" data-page="' . $i . '">' . $i . '</a>';
                }
                if ($total_pages > 6) {
                    echo '<span class="ellipsis">...</span>';
                    echo '<a href="#" class="page-number" data-page="' . $total_pages . '">' . $total_pages . '</a>';
                    echo '<a href="#" class="next-page" data-page="' . min($paged + 1, $total_pages) . '">Siguiente</a>';
                }
                echo '</div>';
            }
        } else {
            echo '<p>No se encontraron listados que coincidan con los criterios de búsqueda.</p>';
        }

        wp_reset_postdata();

        $pagination_data = array(
            'currentPage' => $paged,
            'totalPages' => $query->max_num_pages
        );
        echo '<script>updatePagination(' . $pagination_data['currentPage'] . ', ' . $pagination_data['totalPages'] . ');</script>';

        $pagination_data = array(
            'currentPage' => $paged,
            'totalPages' => $query->max_num_pages
        );
        echo '<script>
            if (typeof updatePagination === "function") {
                updatePagination(' . $pagination_data['currentPage'] . ', ' . $pagination_data['totalPages'] . ');
            } else {
                console.error("updatePagination function is not defined");
            }
        </script>';

        $response = array(
            'html' => ob_get_clean(), // Asumiendo que has iniciado un buffer de salida al principio de la función
            'pagination' => $pagination_data
        );

        wp_send_json_success($response);

        die();
    }

    public function modify_featured_query($clauses, $wp_query) {
        global $wpdb;
        
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} as featured_meta ON ({$wpdb->posts}.ID = featured_meta.post_id AND featured_meta.meta_key = 'hp_featured')";
        $clauses['orderby'] = "CASE WHEN featured_meta.meta_value = '1' THEN 0 ELSE 1 END ASC, " . $clauses['orderby'];
        
        return $clauses;
    }

    public function handle_booking_make() {
        // Verificar si el usuario está logueado
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/make-booking/')));
            exit;
        }
    
        // Obtener los datos del formulario
        $listing_id = isset($_POST['listing']) ? absint($_POST['listing']) : 0;
        $start_date = isset($_POST['_dates'][0]) ? sanitize_text_field($_POST['_dates'][0]) : '';
        $end_date = isset($_POST['_dates'][1]) ? sanitize_text_field($_POST['_dates'][1]) : '';
        $quantity = isset($_POST['_quantity']) ? absint($_POST['_quantity']) : 1;
        $extras = isset($_POST['_extras']) ? array_map('absint', $_POST['_extras']) : [];
    
        // Validar los datos
        if (!$listing_id || !$start_date || !$end_date) {
            wp_die(esc_html__('Información de reserva incompleta', 'cotizador-eventos'));
        }
    
        // Obtener el listing
        $listing = get_post($listing_id);
        if (!$listing || $listing->post_type !== 'hp_listing' || $listing->post_status !== 'publish') {
            wp_die(hivepress()->translator->get_string('no_listings_found'));
        }
    
        // Crear la reserva
        $booking_data = array(
            'post_title'  => 'Reserva para ' . $listing->post_title,
            'post_status' => 'draft',
            'post_type'   => 'hp_booking',
            'post_author' => get_current_user_id(),
            'meta_input'  => array(
                'hp_listing'   => $listing_id,
                'hp_start_time' => strtotime($start_date),
                'hp_end_time'   => strtotime($end_date),
                'hp_quantity'   => $quantity,
                'hp_price_extras' => $extras,
            ),
        );
    
        $booking_id = wp_insert_post($booking_data);
    
        if (is_wp_error($booking_id)) {
            wp_die(esc_html__('No se pudo crear la reserva', 'cotizador-eventos'));
        }
    
        // Redirigir a la página de detalles de la reserva
        wp_redirect(home_url('/booking/' . $booking_id . '/'));
        exit;
    }

    public function handle_booking_make_request() {
    
        $listing_id = isset($_POST['listing']) ? absint($_POST['listing']) : 0;
        $start_date = isset($_POST['_dates'][0]) ? sanitize_text_field($_POST['_dates'][0]) : '';
        $end_date = isset($_POST['_dates'][1]) ? sanitize_text_field($_POST['_dates'][1]) : '';
        $quantity = isset($_POST['_quantity']) ? absint($_POST['_quantity']) : 1;
        $extras = isset($_POST['_extras']) ? array_map('absint', $_POST['_extras']) : [];
    
        
    
        if (!$listing_id || !$start_date || !$end_date) {
            wp_send_json_error(['message' => 'Información de reserva incompleta']);
            return;
        }
    
        $listing = Models\Listing::query()->get_by_id($listing_id);
        if (!$listing || $listing->get_status() !== 'publish' || !hivepress()->booking->is_booking_enabled($listing)) {
            wp_send_json_error(['message' => hivepress()->translator->get_string('no_listings_found')]);
            return;
        }
    
        // Buscar una reserva existente o crear una nueva
        $booking = Models\Booking::query()->filter(
            [
                'status'  => 'auto-draft',
                'drafted' => true,
                'user'    => get_current_user_id(),
            ]
        )->get_first();
    
        if (!$booking) {
            $booking = new Models\Booking();
        }
    
        $booking->fill(
            [
                'listing'      => $listing_id,
                'start_time'   => strtotime($start_date),
                'end_time'     => strtotime($end_date),
                'quantity'     => $quantity,
                'price_extras' => $extras,
                'status'       => 'auto-draft',
                'drafted'      => true,
                'user'         => get_current_user_id(),
            ]
        );
    
        // Guardar la reserva
        if ($booking->save(['listing', 'start_time', 'end_time', 'quantity', 'price_extras', 'status', 'drafted', 'user'])) {
            
            // Limpiar cualquier caché relacionada con esta reserva
            wp_cache_delete($booking->get_id(), 'post_meta');
            
            // Construir la URL de redirección
            $redirect_url = home_url('/make-booking/details/');
            $redirect_url = add_query_arg('booking_id', $booking->get_id(), $redirect_url);
            
            
            wp_send_json_success(['redirect_url' => $redirect_url]);
        } else {
            error_log("Failed to save booking");
            wp_send_json_error(['message' => esc_html__('The booking can\'t be made', 'hivepress-bookings')]);
        }
    }
    
    public function handle_visita_form_submission() {
        if (!isset($_POST['form_data'])) {
            wp_send_json_error('No form data received');
        }
    
        parse_str($_POST['form_data'], $form_data);
    
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
    
        // Preparar el contenido del correo
        $to = 'mgarcia08@hotmail.com';
        $subject1 = 'Nueva solicitud de visita';
        $message1 = "User ID: {$user_id}\n";
        $message1 .= "Correo Usuario: {$user->user_email}\n";
        $message1 .= "Nombre Usuario: {$user->user_login}\n";
        $message1 .= "Post URL: {$form_data['post_url']}\n";
        $message1 .= "Fecha de Visita: {$form_data['fecha_visita']}\n";
        $message1 .= "Hora de Visita: {$form_data['hora_visita']}\n";
        $message1 .= "Celular: {$form_data['celular']}\n";
        $message1 .= "Cantidad de Invitados: {$form_data['cantidad_invitados']}\n";
        $message1 .= "Fecha de Evento: {$form_data['fecha_evento']}\n";
        $message1 .= "Requerimientos Especiales: {$form_data['requerimientos_especiales']}\n";
    
        $subject2 = 'Notificación de solicitud de visita';
        $message2 = "Hola! User ID: {$user_id}, se ha hecho una solicitud de visita de este usuario para la fecha {$form_data['fecha_visita']} a las {$form_data['hora_visita']}!";
    
        // Enviar correos
        $mail1 = wp_mail($to, $subject1, $message1);
        $mail2 = wp_mail($to, $subject2, $message2);
    
        if ($mail1 && $mail2) {
            wp_send_json_success('Emails sent successfully');
        } else {
            wp_send_json_error('Failed to send emails');
        }
    }

    public function handle_bloqueo_form_submission() {
        if (!isset($_POST['form_data'])) {
            wp_send_json_error('No form data received');
        }
    
        parse_str($_POST['form_data'], $form_data);
    
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
    
        // Preparar el contenido del correo
        $to = 'mgarcia08@hotmail.com';
        $subject = "Solicitud de Bloqueo Usuario {$user->user_login}";
        $message = "URL post: " . (isset($form_data['post_url']) ? $form_data['post_url'] : 'No disponible') . "\n";
        $message .= "User ID: {$user_id}\n";
        $message .= "Nombre Usuario: {$user->user_login}\n";
        $message .= "Correo Usuario: {$user->user_email}\n";
        $message .= "Fecha de interés: {$form_data['fecha_interes']}\n";
        $message .= "Celular: {$form_data['celular']}\n";
        $message .= "Cantidad invitados: {$form_data['cantidad_invitados']}\n";
    
        // Enviar correo
        $mail = wp_mail($to, $subject, $message);
    
        if ($mail) {
            wp_send_json_success('Email sent successfully');
        } else {
            wp_send_json_error('Failed to send email');
        }
    }

    public function get_listing_booking_metadata() {
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        
        if (!$listing_id) {
            wp_send_json_error('Invalid listing ID');
        }
    
        $listing = Models\Listing::query()->get_by_id($listing_id);
    
        if (!$listing) {
            wp_send_json_error('Listing not found');
        }
    
        $min_length = $listing->get_booking_min_length() ?: 1;
        $max_length = $listing->get_booking_max_length() ?: 365;
        $booking_window = $listing->get_booking_window() ?: 365;
        $booking_offset = $listing->get_booking_offset() ?: 0;
    
        $disabled_dates = $this->get_disabled_dates($listing);
    
        wp_send_json_success(array(
            'min_length' => $min_length,
            'max_length' => $max_length,
            'booking_window' => $booking_window,
            'booking_offset' => $booking_offset,
            'disabled_dates' => $disabled_dates
        ));
    }
    
       

    public function get_disabled_dates_ajax() {
        $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
        
        if (!$listing_id) {
            wp_send_json_error('Invalid listing ID');
        }
    
        $listing = Models\Listing::query()->get_by_id($listing_id);
    
        if (!$listing) {
            wp_send_json_error('Listing not found');
        }
    
        $disabled_dates = $this->get_disabled_dates($listing);
    
        // Asegurarse de que las fechas estén en el formato correcto (Y-m-d)
        $formatted_dates = array_map(function($date) {
            return date('Y-m-d', strtotime($date));
        }, $disabled_dates);
    
        wp_send_json_success(array('disabled_dates' => $formatted_dates));
    }

}