jQuery(document).ready(function($) {



    function initFlatpickr() {
        $('[data-component="date-range"]').each(function() {
            var $container = $(this);
            var $hiddenInputs = $container.find('input[type="hidden"][name="_dates[]"]');
            var $visibleInput = $container.find('input[type="text"]');
            
            if ($visibleInput.hasClass('flatpickr-input')) {
                return;
            }
    
            var listingId = $container.closest('form').find('input[name="listing"]').val();
    
            $.ajax({
                url: cotizador_eventos_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_listing_booking_metadata',
                    listing_id: listingId
                },
                success: function(response) {
                    console.log('Respuesta de get_listing_booking_metadata:', response);
                    if (response.success) {
                        initFlatpickrWithMetadata($container, $visibleInput, $hiddenInputs, response.data);
                    } else {
                        console.error('Error al obtener metadatos del listing:', response.data);
                        initFlatpickrWithMetadata($container, $visibleInput, $hiddenInputs, {});
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error en la llamada AJAX para obtener metadatos:', textStatus, errorThrown);
                    initFlatpickrWithMetadata($container, $visibleInput, $hiddenInputs, {});
                }
            });
        });
    }
    
    function initFlatpickrWithMetadata($container, $visibleInput, $hiddenInputs, metadata) {
        var minLength = parseInt(metadata.min_length) || 1;
        var maxLength = parseInt(metadata.max_length) || 365;
        var bookingWindow = parseInt(metadata.booking_window) || 365;
        var bookingOffset = parseInt(metadata.booking_offset) || 1;
        var disabledDates = metadata.disabled_dates || [];
    
        var today = new Date();
        var minDate = new Date(today.getTime() + (bookingOffset * 24 * 60 * 60 * 1000));
        var maxDate = new Date(today.getTime() + (bookingWindow * 24 * 60 * 60 * 1000));
    
        var flatpickrInstance = $visibleInput.flatpickr({
            mode: minLength === maxLength && minLength === 1 ? "single" : "range",
            dateFormat: "Y-m-d",
            altFormat: "j \\de F \\de Y",
            altInput: true,
            minDate: minDate,
            maxDate: maxDate,
            disable: disabledDates,
            locale: {
                ...flatpickr.l10ns.es,
                firstDayOfWeek: 0
            },
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 1) {
                    if (minLength === maxLength && minLength === 1) {
                        $hiddenInputs.eq(0).val(instance.formatDate(selectedDates[0], "Y-m-d"));
                        $hiddenInputs.eq(1).val(instance.formatDate(selectedDates[0], "Y-m-d"));
                    } else {
                        var startDate = new Date(selectedDates[0]);
                        var endDate = new Date(startDate);
                        endDate.setDate(endDate.getDate() + maxLength - 1);
    
                        var newDisabledDates = [...disabledDates];
                        var currentDate = new Date(minDate);
    
                        while (currentDate <= maxDate) {
                            if (currentDate < startDate || currentDate > endDate) {
                                newDisabledDates.push(currentDate.toISOString().split('T')[0]);
                            }
                            currentDate.setDate(currentDate.getDate() + 1);
                        }
    
                        instance.set('disable', newDisabledDates);
                        instance.set('minDate', startDate);
                        instance.set('maxDate', endDate);
                    }
                } else if (selectedDates.length === 2) {
                    var diffTime = Math.abs(selectedDates[1] - selectedDates[0]);
                    var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    if (diffDays >= minLength && diffDays <= maxLength) {
                        $hiddenInputs.eq(0).val(instance.formatDate(selectedDates[0], "Y-m-d"));
                        $hiddenInputs.eq(1).val(instance.formatDate(selectedDates[1], "Y-m-d"));
                    } else {
                        instance.clear();
                    }
                    
                    instance.set('disable', disabledDates);
                    instance.set('minDate', minDate);
                    instance.set('maxDate', maxDate);
                } else {
                    $hiddenInputs.val('');
                    instance.set('disable', disabledDates);
                    instance.set('minDate', minDate);
                    instance.set('maxDate', maxDate);
                }
            },
            onMonthChange: function(selectedDates, dateStr, instance) {
                instance.redraw();
            },
            onYearChange: function(selectedDates, dateStr, instance) {
                instance.redraw();
            },
            onOpen: function(selectedDates, dateStr, instance) {
                instance.redraw();
            },
            allowInput: true,
            clickOpens: true,
        });
    
        $visibleInput.on('focus', function() {
            if (!flatpickrInstance.isOpen) {
                flatpickrInstance.open();
            }
        });
    }

    function updatePrecioTotal($form) {
        var basePrice = parseFloat($form.data('price')) || 0;
        var taxRate = parseFloat($form.data('tax-rate')) || 0;
        var quantity = parseInt($form.find('input[name="_quantity"]').val()) || 1;
        var $datesInputs = $form.find('input[name="_dates[]"]');
        var startDate = $datesInputs.eq(0).val();
        var endDate = $datesInputs.eq(1).val();
        var days = 1;
    
        if (startDate && endDate) {
            var start = new Date(startDate);
            var end = new Date(endDate);
            days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        }
    
        var totalPrice = basePrice * quantity * days;
    
        var extrasData = $form.data('extras');
        var extras = typeof extrasData === 'string' ? JSON.parse(extrasData) : extrasData;
    
        $form.find('input[name="_extras[]"]:checked').each(function() {
            var extraId = $(this).val();
            if (extras && extras[extraId]) {
                var extraPrice = parseFloat(extras[extraId].price);
                var extraType = extras[extraId].type;
    
                switch (extraType) {
                    case 'per_order':
                        totalPrice += extraPrice;
                        break;
                    case null:
                    case '':
                        totalPrice += extraPrice * quantity * days;
                        break;
                    case 'per_quantity':
                        totalPrice += extraPrice * quantity;
                        break;
                    case 'per_item':
                        totalPrice += extraPrice * days;
                        break;
                }
            }
        });
    
        // Aplicar impuestos
        totalPrice *= (1 + taxRate / 100);
    
        $('#precio-total').text(formatPrice(totalPrice));
    }
    
    function formatPrice(price) {
        return price.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
    }

    function showLoginForm() {
    const formContainer = document.getElementById('jet-register-form-popup');
    
    if (!formContainer) {
        console.error('Formulario no encontrado');
        return;
    }

    const parentElement = formContainer.parentNode;
    const nextElement = formContainer.nextElementSibling;

    $.fancybox.open({
        src: formContainer,
        type: 'html',
        opts: {
            baseClass: 'form-fancybox',
            touch: false,
            autoFocus: false,
            animationEffect: 'fade',
            afterShow: function(instance, current) {
                formContainer.style.display = 'block';
                
                // Agregar este manejador para el botón Sign In
                formContainer.querySelector('.elementor-button[href*="elementor-action"]').addEventListener('click', function(e) {
                    e.preventDefault();
                    $.fancybox.close();
                    const href = this.getAttribute('href');
                    setTimeout(() => {
                        window.location.href = href;
                    }, 300);
                });
            },
            afterClose: function() {
                formContainer.style.display = 'none';
                if (nextElement) {
                    parentElement.insertBefore(formContainer, nextElement);
                } else {
                    parentElement.appendChild(formContainer);
                }
            }
        }
    });
}

    
    // Modificar los event listeners de los botones
    function handleButtonClick(e, originalCallback) {
        e.preventDefault();
        e.stopPropagation();
    
        // Verificar si el usuario está logueado
        if (!cotizador_eventos_data.user_id || cotizador_eventos_data.user_id === '0') {
            showLoginForm();
        } else {
            originalCallback(e);
        }
    }


    // Manejador para el botón "Reservar"
    // Manejador para el botón "Reservar"
$(document).off('click', '.boton-reservar').on('click', '.boton-reservar', function(e) {
    handleButtonClick(e, function(event) {
        e.preventDefault();
        e.stopPropagation();
        var listingId = $(event.target).data('listing-id');
        
        $.ajax({
            url: cotizador_eventos_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'open_booking_popup',
                listing_id: listingId
            },
            success: function(response) {
                if (response.success && response.data && response.data.html) {
                    $.fancybox.open({
						src: response.data.html,
						type: 'inline',
						opts: {
							baseClass: 'booking-form-popup',
							touch: false,
							autoFocus: false,
							animationEffect: 'fade',
							animationDuration: 300,
							padding: 0,
							closeButton: true,  // Habilitar botón de cerrar
							smallBtn: true,
							btnTpl: {
								smallBtn:
									'<button type="button" data-fancybox-close class="fancybox-button fancybox-close-small" title="{{CLOSE}}">' +
									'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M13 12l5-5-1-1-5 5-5-5-1 1 5 5-5 5 1 1 5-5 5 5 1-1z"/></svg>' +
									'</button>'
							},
							afterShow: function(instance, current) {
								const bookingForm = new BookingForm();
							}
						}
					});
                } else {
                    console.error('Invalid AJAX response:', response);
                    alert('Error al cargar el formulario de reserva');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
                alert('Error al cargar el formulario de reserva');
            }
        });
        
        return false;
    });
});

    // Inicializar Flatpickr cuando se abra cualquier modal
    $(document).on('hp_modal_open', function() {
        initFlatpickr();
    });

    

    $(document).on('submit', '.hp-form--booking-make form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formElement = $form[0];
        var formData;

        try {
            formData = new FormData(formElement);
        } catch (error) {
            console.error('Error al crear FormData:', error);
            alert('Error al procesar el formulario. Por favor, inténtelo de nuevo.');
            return;
        }

        var listingId = $form.find('input[name="listing"]').val();
        if (!listingId) {
            listingId = $form.closest('.hp-form--booking-make').data('listing-id');
            formData.append('listing', listingId);
        }
        
        formData.append('action', 'booking_make_request');

        var startDate = $form.find('input[name="_dates[]"]').eq(0).val();
        var endDate = $form.find('input[name="_dates[]"]').eq(1).val();
        
        formData.append('_dates[]', startDate);
        formData.append('_dates[]', endDate);

        $.ajax({
            url: cotizador_eventos_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                if (response.success) {
                    window.location.href = response.data.redirect_url;
                } else {
                    console.error('Error en la respuesta:', response.data.message);
                    alert('Error al procesar la reserva: ' + (response.data.message || 'Error desconocido'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error AJAX:', textStatus, errorThrown);
                alert('Error al enviar la solicitud de reserva');
            }
        });
    });

    // Función para manejar el clic en el ítem del evento
    window.handleItemClick = function(event, url) {
       if (!event.target.closest('.evento-botones') && !event.target.closest('.hp-link')) {
            window.open(url, '_blank');
        }
    };

    $(document).off('click', '.boton-contactar').on('click', '.boton-contactar', function(e) {
        handleButtonClick(e, function(event) {

        e.preventDefault();
        e.stopPropagation();
        var listingId = $(event.target).data('listing-id');
        var listingUrl = $(event.target).data('listing-url');
        var phoneNumber = cotizador_eventos_data.whatsapp_number.replace(/[+\s]/g, '');
        var message = 'Hola, me interesa reservar un servicio que vi en su página, me puedes ayudar? el servicio que vi es este: ' + listingUrl;
        var whatsappUrl = 'https://wa.me/' + phoneNumber + '?text=' + encodeURIComponent(message);
        window.open(whatsappUrl, '_blank');
        });

    });


    function openVisitaPopup(listingId, postUrl) {
    var userId = cotizador_eventos_data.user_id;
    var formHtml = `
        <form id="visita-form" class="bv-booking-form">
            <div class="bv-booking-header">
                <h2>Request a Visit</h2>
            </div>
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="post_url" value="${postUrl}">
            <div class="bv-fields-container">
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="fecha_visita">Date and Time of Visit</label>
                            <input type="text" id="fecha_visita" name="fecha_visita" required>
                        </div>
                    </div>
                </div>
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="celular">Phone Number</label>
                            <input type="tel" id="celular" name="celular" required>
                        </div>
                    </div>
                </div>
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="cantidad_invitados">Number of Guests</label>
                            <input type="number" id="cantidad_invitados" name="cantidad_invitados" required>
                        </div>
                    </div>
                </div>
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="fecha_evento">Event Date</label>
                            <input type="text" id="fecha_evento" name="fecha_evento" required>
                        </div>
                    </div>
                </div>
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="requerimientos_especiales">Special Requirements</label>
                            <textarea id="requerimientos_especiales" name="requerimientos_especiales" rows="4"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="bv-booking-button">Send Request</button>
            <div id="form-message" class="form-message" style="display:none;"></div>
        </form>
    `;

    $.fancybox.open({
        src: formHtml,
        type: 'html',
        opts: {
            baseClass: 'booking-form-popup',
            touch: false,
            autoFocus: false,
            animationEffect: 'fade',
            animationDuration: 300,
            padding: 0,
            closeButton: true,
            smallBtn: true,
            btnTpl: {
                smallBtn:
                    '<button type="button" data-fancybox-close class="fancybox-button fancybox-close-small" title="{{CLOSE}}">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M13 12l5-5-1-1-5 5-5-5-1 1 5 5-5 5 1 1 5-5 5 5 1-1z"/></svg>' +
                    '</button>'
            },
            afterShow: function(instance, current) {
                flatpickr("#fecha_visita", {
                    dateFormat: "Y-m-d H:i",
                    enableTime: true,
                    minDate: "today",
                    locale: "es",
                    clickOpens: true
                });
                
                flatpickr("#fecha_evento", {
                    dateFormat: "Y-m-d",
                    minDate: "today",
                    locale: "es"
                });
            }
        }
    });
}

    function openBloqueoPopup(listingId, postUrl) {
    var userId = cotizador_eventos_data.user_id;
    var formHtml = `
        <form id="bloqueo-form" class="bv-booking-form">
            <div class="bv-booking-header">
                <h2>Block Date</h2>
            </div>
            <input type="hidden" name="post_url" value="${postUrl}">
            <div class="bv-fields-container">
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="fecha_interes">Date of Interest</label>
                            <input type="text" id="fecha_interes" name="fecha_interes" required>
                        </div>
                    </div>
                </div>
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="celular">Phone Number</label>
                            <input type="tel" id="celular" name="celular" required>
                        </div>
                    </div>
                </div>
                <div class="bv-form-row">
                    <div class="bv-field-block">
                        <div class="bv-field-content">
                            <label for="cantidad_invitados">Number of Guests</label>
                            <input type="number" id="cantidad_invitados" name="cantidad_invitados" required>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="bv-booking-button">Send Request</button>
            <div id="form-message" class="form-message" style="display:none;"></div>
        </form>
    `;

    $.fancybox.open({
        src: formHtml,
        type: 'html',
        opts: {
            baseClass: 'booking-form-popup',
            touch: false,
            autoFocus: false,
            animationEffect: 'fade',
            animationDuration: 300,
            padding: 0,
            closeButton: true,
            smallBtn: true,
            btnTpl: {
                smallBtn:
                    '<button type="button" data-fancybox-close class="fancybox-button fancybox-close-small" title="{{CLOSE}}">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M13 12l5-5-1-1-5 5-5-5-1 1 5 5-5 5 1 1 5-5 5 5 1-1z"/></svg>' +
                    '</button>'
            },
            afterShow: function(instance, current) {
                flatpickr("#fecha_interes", {
                    dateFormat: "Y-m-d H:i",
                    enableTime: true,
                    minDate: "today",
                    locale: "es",
                    clickOpens: true
                });
            }
        }
    });
}
    $(document).off('click', '.boton-visita').on('click', '.boton-visita', function(e) {
        handleButtonClick(e, function(event) {

        e.preventDefault();
        e.stopPropagation();
        var listingId = $(event.target).data('listing-id');
        var postUrl = $(event.target).data('listing-url');
        openVisitaPopup(listingId, postUrl);

     });

    });
    
			$(document).off('click', '.boton-bloquear').on('click', '.boton-bloquear', function(e) {
				handleButtonClick(e, function(event) {

				e.preventDefault();
				e.stopPropagation();
				var listingId = $(event.target).data('listing-id');
				var postUrl = $(event.target).data('listing-url');
				openBloqueoPopup(listingId, postUrl);

			 });
			});
	
	$(document).on('click', '.hp-link', function(e) {
    e.preventDefault();
    e.stopPropagation();
   
    var $toggle = $(this);
    var listingId = $toggle.data('listing-id');
    
    if (!listingId) {
        return;
    }
   
    $.ajax({
        url: cotizador_eventos_ajax.ajax_url,
        method: 'POST',
        data: {
            action: 'toggle_custom_favorite',
            listing_id: listingId
        },
        success: function(response) {
            if (response.success) {
                $toggle.toggleClass('hp-state-active');
                var $text = $toggle.find('span');
                var newText = $toggle.hasClass('hp-state-active') ? 'Remove from Favorites' : 'Add to Favorites';
                $text.text(newText);
            }
        }
    });
});
	
	$(document).on('click', '.hp-link[data-component="toggle"]', function(e) {
   e.preventDefault();
   e.stopPropagation();
   
   var $toggle = $(this);
   var listingId = $toggle.closest('.evento-item').find('.boton-reservar').data('listing-id');
   
   $.ajax({
       url: cotizador_eventos_ajax.ajax_url,
       method: 'POST',
       data: {
           action: 'toggle_custom_favorite',
           listing_id: listingId
       },
       success: function(response) {
           if (response.success) {
               $toggle.toggleClass('hp-state-active');
               var $text = $toggle.find('span');
               var newText = $toggle.hasClass('hp-state-active') ? 'Remove from Favorites' : 'Add to Favorites';
               $text.text(newText);
           }
       }
   });
});
	
});