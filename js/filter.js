// Variables globales
let isMobile = window.innerWidth <= 767;
let updatePagination;

// Variables para Event Quote Manager integration
let lastFilterDate = localStorage.getItem('eq_selected_date') || null;

// Función para inicializar los sliders de galería
function initGallerySliders() {
    jQuery('.galeria-slider').each(function() {
        var $slider = jQuery(this);
        var $slides = $slider.find('img');
        var currentIndex = 0;

        function showSlide(index) {
            $slides.hide();
            $slides.eq(index).show();
        }

        function nextSlide(e) {
            e.stopPropagation();
            e.preventDefault();
            currentIndex = (currentIndex + 1) % $slides.length;
            showSlide(currentIndex);
        }

        function prevSlide(e) {
            e.stopPropagation();
            e.preventDefault();
            currentIndex = (currentIndex - 1 + $slides.length) % $slides.length;
            showSlide(currentIndex);
        }

        var $nextButton = $slider.closest('.evento-thumbnail').find('.next-slide');
        var $prevButton = $slider.closest('.evento-thumbnail').find('.prev-slide');

        $nextButton.on('click', nextSlide);
        $prevButton.on('click', prevSlide);

        showSlide(0);
    });
}

// Función principal para cargar eventos
function loadEventos(paged = 1) {
    var formData = jQuery('#filtro-eventos-form').serialize();
    formData += '&action=filter_eventos&paged=' + paged;
    formData += '&nombre=' + jQuery('#nombre').val();
    
    jQuery('.filtros-laterales :input').each(function() {
        if (jQuery(this).attr('type') === 'checkbox') {
            if (jQuery(this).is(':checked')) {
                formData += '&' + jQuery(this).attr('name') + '=' + jQuery(this).val();
            }
        } else {
            formData += '&' + jQuery(this).attr('name') + '=' + jQuery(this).val();
        }
    });
    
    jQuery.ajax({
        url: cotizador_eventos_ajax.ajax_url,
        type: 'POST',
        data: formData,
        beforeSend: function() {
            jQuery('#resultados-eventos').html('<p>Cargando...</p>');
        },
        success: function(response) {
            jQuery('#resultados-eventos').html(response.data.html);
            updatePagination(response.data.pagination.currentPage, response.data.pagination.totalPages);
            initGallerySliders();
        }
    });
}

// Función para actualizar la paginación
updatePagination = function(currentPage, totalPages) {
    var paginationHtml = '';
    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, startPage + 5);

    if (currentPage > 1) {
        paginationHtml += '<a href="#" class="page-number" data-page="' + (currentPage - 1) + '">Anterior</a>';
    }

    for (var i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            paginationHtml += '<span class="page-number current">' + i + '</span>';
        } else {
            paginationHtml += '<a href="#" class="page-number" data-page="' + i + '">' + i + '</a>';
        }
    }

    if (currentPage < totalPages) {
        paginationHtml += '<a href="#" class="page-number" data-page="' + (currentPage + 1) + '">Siguiente</a>';
    }

    jQuery('.pagination').html(paginationHtml);
};

// Función para inicializar sliders
function initSliders() {
    jQuery("#slider-precio").slider({
        range: true,
        min: 0,
        max: 100000,
        values: [0, 100000],
        slide: function(event, ui) {
            jQuery("#min-precio").val(ui.values[0]);
            jQuery("#max-precio").val(ui.values[1]);
        },
        stop: function(event, ui) {
            loadEventos();
        }
    });

    jQuery("#min-precio, #max-precio").on("change", function() {
        jQuery("#slider-precio").slider("values", [jQuery("#min-precio").val(), jQuery("#max-precio").val()]);
        loadEventos();
    });
}

// Función para toggle de visibilidad de filtros en móvil
function toggleFilterVisibility() {
    if (isMobile) {
        jQuery('.filtros-movil').removeClass('active');
        jQuery('#filtro-toggle').show();
    } else {
        jQuery('.filtros-movil').removeClass('active');
        jQuery('#filtro-toggle').hide();
    }
}

jQuery(document).ready(function($) {
    // Cargar eventos iniciales
    loadEventos();
	
// Event Quote Manager: Initialize date filter with stored value
if (!$('#fecha').val()) {
    if (lastFilterDate) {
        $('#fecha').val(lastFilterDate);
    } else {
        // Asegurarse de que no hay fecha por defecto si no hay una guardada
        $('#fecha').val('');
    }
}

    const isDateFromPanel = localStorage.getItem('eq_date_source') === 'panel';
    const canUseContextPanel = typeof eqContextData !== 'undefined' && eqContextData.canUseContextPanel;
    

 // Si la fecha viene del panel y el usuario tiene acceso, usarla
    if (isDateFromPanel && canUseContextPanel) {
        const panelDate = localStorage.getItem('eq_panel_selected_date');
        if (panelDate) {
            $('#fecha').val(panelDate);
            lastFilterDate = panelDate;
            
            // Log para debug
            console.log('Using date from Context Panel:', panelDate);
        }
    } 
    // Si no viene del panel o el usuario no tiene acceso, usar la guardada normalmente
    else if (lastFilterDate) {
        $('#fecha').val(lastFilterDate);
    }
    
    // Cargar eventos iniciales
    loadEventos();

$('#limpiar-filtros').on('click', function() {
    // NUEVO: Verificar si debemos respetar la fecha del panel
    const isDateFromPanel = localStorage.getItem('eq_date_source') === 'panel';
    const canUseContextPanel = typeof eqContextData !== 'undefined' && eqContextData.canUseContextPanel;
    
    // Solo limpiar fecha si no viene del panel o el usuario no tiene acceso
    if (!isDateFromPanel || !canUseContextPanel) {
        localStorage.removeItem('eq_selected_date');
        localStorage.removeItem('eq_date_source');
        localStorage.removeItem('eq_date_timestamp');
        lastFilterDate = null;
    } else {
        // Si viene del panel, restaurar la fecha del panel
        const panelDate = localStorage.getItem('eq_panel_selected_date');
        if (panelDate) {
            $('#fecha').val(panelDate);
            
            // Notificar al usuario
            const notification = $('<div class="eq-notification info">La fecha del panel de contexto no se puede limpiar directamente</div>');
            $('body').append(notification);
            setTimeout(() => notification.addClass('show'), 100);
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    }
    
    $('#filtro-eventos-form')[0].reset();
    
    // Si hay fecha del panel, restaurarla después de resetear el formulario
    if (isDateFromPanel && canUseContextPanel) {
        const panelDate = localStorage.getItem('eq_panel_selected_date');
        if (panelDate) {
			  // Agregar logs aquí
        console.log('DIAGNÓSTICO - Filter.js: Intentando usar fecha del panel:', panelDate);
        console.log('DIAGNÓSTICO - Filter.js: localStorage completo:', {
            eq_panel_selected_date: localStorage.getItem('eq_panel_selected_date'),
            eq_selected_date: localStorage.getItem('eq_selected_date'),
            eq_date_source: localStorage.getItem('eq_date_source'),
            eq_date_timestamp: localStorage.getItem('eq_date_timestamp')
        });
            $('#fecha').val(panelDate);
			        lastFilterDate = panelDate;
			        console.log('Using date from Context Panel:', panelDate);


        }
    }
    
    $('#nombre').val('');
    $('#filtros-adicionales').empty();
    $("#categoria").val("").trigger('change');
    loadEventos();
});

    // Event listeners
    $('#categoria').on('change', function() {
        var categoria = $(this).val();
        $.ajax({
            url: cotizador_eventos_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_category_filters',
                categoria: categoria
            },
            success: function(response) {
                $('#filtros-adicionales').html(response.data.html);
                initSliders();
                loadEventos();
            }
        });
    });

   $('#filtro-eventos-form').on('submit', function(e) {
    e.preventDefault();
    
    // NUEVO: Verificar si debemos respetar la fecha del panel
    const isDateFromPanel = localStorage.getItem('eq_date_source') === 'panel';
    const canUseContextPanel = typeof eqContextData !== 'undefined' && eqContextData.canUseContextPanel;
    
    // Guardar fecha cuando se aplica el filtro
    const selectedDate = $('#fecha').val();
    
    // Solo actualizar localStorage si no hay fecha del panel o el usuario no tiene acceso
    if (!isDateFromPanel || !canUseContextPanel) {
        if (selectedDate) {
            localStorage.setItem('eq_selected_date', selectedDate);
            lastFilterDate = selectedDate;
            
            // Si no estamos en modo panel, actualizar la fuente de la fecha
            localStorage.setItem('eq_date_source', 'filter');
            localStorage.setItem('eq_date_timestamp', Date.now().toString());
            
            // Disparar evento personalizado
            $(document).trigger('eqFilterDateApplied', [selectedDate]);
        } else {
            localStorage.removeItem('eq_selected_date');
            localStorage.removeItem('eq_date_source');
            localStorage.removeItem('eq_date_timestamp');
            lastFilterDate = null;
        }
    } else if (isDateFromPanel && canUseContextPanel && selectedDate) {
        // Si estamos en modo panel, NO actualizar la fecha del panel
        // Solo actualizar la variable local para el filtro
        lastFilterDate = selectedDate;
        
        // Mostrar notificación
        const notification = $('<div class="eq-notification info">El filtro está usando la fecha del panel de contexto.</div>');
        $('body').append(notification);
        setTimeout(() => notification.addClass('show'), 100);
        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    loadEventos();
    $('.filtros-movil').removeClass('active');
    $('#filtro-toggle').html('🔍');
});

    $('#buscar-nombre').on('click', function(e) {
        e.preventDefault();
        loadEventos();
        $('.filtros-movil').removeClass('active');
        $('#filtro-toggle').html('🔍'); // Cambia el ícono a lupa
    });

    $(document).on('click', '.pagination .page-number', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadEventos(page);
    });

    $('#cantidad-invitados').on('change', function() {
        loadEventos();
    });

    // Inicializar sliders si los filtros adicionales ya están presentes en la carga inicial
    if ($('#filtros-adicionales').children().length > 0) {
        initSliders();
    }

    // Manejar cambios en los filtros adicionales
    $(document).on('change', '#filtros-adicionales input[type="checkbox"], #filtros-adicionales select', function() {
        loadEventos();
    });

    // Manejo del botón de filtro en móvil
    $('#filtro-toggle').on('click', function() {
        $('.filtros-movil').toggleClass('active');
        if ($('.filtros-movil').hasClass('active')) {
            $('.filtros-movil').show();
            $(this).html('✕'); // Cambia a una X cuando está abierto
        } else {
            $('.filtros-movil').hide();
            $(this).html('🔍'); // Vuelve al ícono de lupa cuando está cerrado
        }
    });

    // Inicializar visibilidad de filtros
    toggleFilterVisibility();

    // Manejar cambio de tamaño de ventana
    $(window).resize(function() {
        isMobile = window.innerWidth <= 767;
        toggleFilterVisibility();
    });
	
// Escuchar cambios de fecha desde otros componentes
$(document).on('eqDateChanged', function(e, newDate, options) {
	
	console.log('DIAGNÓSTICO - Filter.js: Evento eqDateChanged recibido:', {
        newDate: newDate,
        options: options,
        currentDate: $('#fecha').val()
    });
    // Si viene del panel y tiene flag de forzar, actualizar sin importar el usuario
    if (options && options.fromPanel && options.force) {
        $('#fecha').val(newDate);
        lastFilterDate = newDate;
        
        console.log('Filter: Date updated from Context Panel with force flag');
    }
    // Si no tiene flag force, solo actualizar si el usuario tiene acceso
    else if (options && options.fromPanel) {
        const canUseContextPanel = typeof eqContextData !== 'undefined' && eqContextData.canUseContextPanel;
        if (canUseContextPanel) {
            $('#fecha').val(newDate);
            lastFilterDate = newDate;
            
            console.log('Filter: Date updated from Context Panel');
        }
    }
});
	
});

// Reinicializar sliders de galería después de cada carga AJAX
jQuery(document).ajaxComplete(function() {
    initGallerySliders();
});

function hideFilterToggleOnDesktop() {
    var filterToggle = document.getElementById('filtro-toggle');
    if (filterToggle) {
        if (window.innerWidth >= 768) {
            filterToggle.style.setProperty('display', 'none', 'important');
        } else {
            filterToggle.style.setProperty('display', 'block', 'important');
        }
    }
}

window.addEventListener('load', hideFilterToggleOnDesktop);
window.addEventListener('resize', hideFilterToggleOnDesktop);