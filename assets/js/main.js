/**
 * Archivo principal de JavaScript
 * Funciones comunes para todo el sistema
 */

$(document).ready(function() {
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
    
    // Confirmar eliminación
    $(document).on('click', '.btn-delete', function(e) {
        if (!confirm('¿Está seguro de que desea eliminar este registro?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Formatear campos de entrada
    $('input[type="text"]').on('blur', function() {
        if ($(this).hasClass('uppercase')) {
            $(this).val($(this).val().toUpperCase());
        }
    });
    
    // Validar número de inventario (formato: 3 o 7 + 8 dígitos)
    $('#inventario').on('blur', function() {
        var valor = $(this).val();
        var regex = /^[3|7][0-9]{8}$/;
        if (!regex.test(valor) && valor != '') {
            $(this).addClass('is-invalid');
            $(this).after('<div class="invalid-feedback">Formato inválido. Debe comenzar con 3 o 7 y tener 9 dígitos.</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Activar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Activar popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

/**
 * Función para enviar formularios por AJAX
 */
function submitFormAjax(formId, url, successCallback, errorCallback) {
    $('#' + formId).on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        // Deshabilitar botón y mostrar loading
        submitBtn.prop('disabled', true);
        submitBtn.html('<span class="spinner-border spinner-border-sm"></span> Procesando...');
        
        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function(xhr, status, error) {
                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                } else {
                    alert('Error: ' + error);
                }
            },
            complete: function() {
                // Restaurar botón
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });
}

/**
 * Función para cargar datos de un equipo por inventario
 */
function cargarEquipo(inventario, tipo) {
    $.ajax({
        type: 'POST',
        url: '../../api/equipo.php',
        data: { inventario: inventario, tipo: tipo },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Llenar formulario con los datos
                $.each(data.data, function(key, value) {
                    $('#' + key).val(value);
                });
            }
        },
        error: function() {
            alert('Error al cargar los datos');
        }
    });
}

/**
 * Función para generar reporte
 */
function generarReporte(tipo, filtro, criterio) {
    window.location.href = '../reportes/descargar.php?tipo=' + tipo + 
                          '&filtro=' + filtro + 
                          '&criterio=' + encodeURIComponent(criterio);
}

// Exportar funciones para uso global
window.submitFormAjax = submitFormAjax;
window.cargarEquipo = cargarEquipo;
window.generarReporte = generarReporte;