<div class="evento-item" style="cursor: pointer !important;" onclick="handleItemClick(event, '<?php echo esc_js(get_permalink($listing_id)); ?>')">
<div class="evento-thumbnail">
        <?php
        $imagenes = get_attached_media('image', $listing_id);
        $num_imagenes = count($imagenes);
        
        if ($num_imagenes > 1) {
            echo '<div class="galeria-slider">';
            foreach ($imagenes as $imagen) {
                $imagen_url = wp_get_attachment_image_url($imagen->ID, 'large');
                if ($imagen_url) {
                    echo '<img src="' . esc_url($imagen_url) . '" alt="Imagen del evento" style="display:none;">';
                }
            }
            echo '</div>';
            echo '<button class="prev-slide" onclick="event.stopPropagation(); console.log(\'Clic en anterior\');">&#10094;</button>';
            echo '<button class="next-slide" onclick="event.stopPropagation(); console.log(\'Clic en siguiente\');">&#10095;</button>';
        } elseif ($num_imagenes == 1) {
            $imagen = reset($imagenes);
            echo wp_get_attachment_image($imagen->ID, 'large');
        } else {
            echo get_the_post_thumbnail($listing_id, 'large');
        }
	
if (function_exists('hivepress')) {
    $is_favorite = in_array($listing_id, $favorite_ids);
    $listing = \HivePress\Models\Listing::query()->get_by_id($listing_id);
    if ($listing) {
        $favorite_toggle = new \HivePress\Blocks\Favorite_Toggle([
            'context' => ['listing' => $listing]
        ]);
        if ($is_favorite) {
            echo '<div class="hp-link hp-state-active" data-component="toggle" data-url="' . hivepress()->router->get_url('listing_favorite_action', ['listing_id' => $listing_id]) . '">';
        } else {
            echo '<div class="hp-link" data-component="toggle" data-url="' . hivepress()->router->get_url('listing_favorite_action', ['listing_id' => $listing_id]) . '">';
        }
        echo '<i class="hp-icon fas fa-heart"></i>';
        echo '<span>' . ($is_favorite ? 'Remove from Favorites' : 'Add to Favorites') . '</span>';
        echo '</div>';
    }
}


        ?>
				
    </div>
    <div class="evento-info">
        <h2>
            <?php echo esc_html(get_the_title()); ?>
            <?php
            $is_moderated = get_post_meta($listing_id, 'hp_booking_moderated', true);
            if ($is_moderated != '1') {
                echo '<span class="re-icon">RE</span>';
            }
            ?>
        </h2>
        <div class="evento-descripcion">
            <?php
            $content = get_the_content();
            $trimmed_content = wp_trim_words($content, 20, '...');
            echo '<p>' . $trimmed_content . '</p>';
            ?>
        </div>
        <?php
        $categories = get_the_terms($listing_id, 'hp_listing_category');
        $is_lugar_evento = false;
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                if ($category->slug === 'lugares-para-eventos' || term_is_ancestor_of(get_term_by('slug', 'lugares-para-eventos', 'hp_listing_category'), $category, 'hp_listing_category')) {
                    $is_lugar_evento = true;
                    break;
                }
            }
        }
        if ($is_lugar_evento) {
            $capacidad_minima = get_post_meta($listing_id, 'hp_capacidad_minima', true);
            $capacidad_maxima = get_post_meta($listing_id, 'hp_square_footage', true);
            $caracteristicas = get_the_terms($listing_id, 'hp_listing_incluye_servicio_luga');
            ?>
            <div class="listing-grid evento-detalles">
                <div class="grid-item"></div>
                <div class="grid-item">
    <span class="icon-capacidad">👥</span> 
    <?php 
    if (!empty($capacidad_minima)) {
        echo esc_html($capacidad_minima) . ' - ';
    } else {
        echo '- ';
    }
    echo esc_html($capacidad_maxima); 
    ?>
</div>
<div class="grid-item">
    <?php if ($caracteristicas && !is_wp_error($caracteristicas) && count($caracteristicas) > 0) : ?>
        <span class="tooltip">Características (+<?php echo count($caracteristicas); ?>)
            <span class="tooltiptext">
                <ul>
                    <?php foreach ($caracteristicas as $caracteristica) : ?>
                        <li><?php echo esc_html($caracteristica->name); ?></li>
                    <?php endforeach; ?>
                </ul>
            </span>
        </span>
    <?php else : ?>
        Características
    <?php endif; ?>
</div>
            </div>
        <?php } ?>
        <div class="listing-grid">
            <div class="grid-item">
                <?php
                if ($categories && !is_wp_error($categories)) {
                    echo esc_html($categories[0]->name);
                }
                ?>
            </div>
            <div class="grid-item">
    <?php
    $ciudad_terms = get_the_terms($listing_id, 'hp_listing_ubicacion');
    if ($ciudad_terms && !is_wp_error($ciudad_terms)) {
        $ciudades_hijas = array();
        $ciudad_padre = '';
        foreach ($ciudad_terms as $term) {
            if ($term->parent != 0) {
                $ciudades_hijas[] = $term->name;
            } else {
                $ciudad_padre = $term->name;
            }
        }
        if (!empty($ciudades_hijas)) {
            echo '<span class="tooltip">';
            echo esc_html($ciudades_hijas[0]);
            $extra_cities = count($ciudades_hijas) - 1;
            if ($extra_cities > 0) {
                echo " (+{$extra_cities})";
                echo '<span class="tooltiptext"><ul>';
                for ($i = 1; $i < count($ciudades_hijas); $i++) {
                    echo '<li>' . esc_html($ciudades_hijas[$i]) . '</li>';
                }
                echo '</ul></span>';
            }
            echo '</span>';
        } else {
            echo esc_html($ciudad_padre);
        }
    }
    ?>
</div>
            <div class="grid-item">
                <div class="rating">
                    ★★★★★
                </div>
            </div>
        </div>
    </div>
    <div class="evento-acciones">
        <div class="evento-precio">
            <div class="precio-principal"><span class="moneda">MXN $</span> <?php echo number_format($precio, 0); ?></div>
        </div>
        <div class="evento-botones">
            <button type="button" class="boton-reservar" data-listing-id="<?php echo esc_attr($listing_id); ?>">Book Now</button>                           
            <a href="#" class="boton-bloquear" data-listing-id="<?php echo esc_attr($listing_id); ?>" data-listing-url="<?php echo esc_url(get_permalink($listing_id)); ?>">Block Date</a>           
            <a href="#" class="boton-visita" data-listing-id="<?php echo esc_attr($listing_id); ?>" data-listing-url="<?php echo esc_url(get_permalink($listing_id)); ?>">Request a Visit</a>            
            <a href="#" class="boton-contactar" data-listing-id="<?php echo esc_attr($listing_id); ?>" data-listing-url="<?php echo esc_url(get_permalink($listing_id)); ?>">Contact</a>
				 <?php 
    // Usar el hook de event-quote-cart
    if (function_exists('eq_can_view_quote_button') && eq_can_view_quote_button()) {
        do_action('eq_cart_add_quote_button', $listing_id);
    }
    ?>
        </div>
    </div>
</div>