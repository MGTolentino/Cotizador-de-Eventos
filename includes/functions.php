<?php
function cotizador_eventos_get_categorias() {
    $terms = get_terms(array(
        'taxonomy' => 'hp_listing_category',
        'hide_empty' => false,
    ));
    return $terms;
}

function cotizador_eventos_get_ciudades() {
    $terms = get_terms(array(
        'taxonomy' => 'hp_listing_ubicacion',
        'hide_empty' => false,
    ));
    return $terms;
}