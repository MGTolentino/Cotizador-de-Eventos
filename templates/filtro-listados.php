<div class="cotizador-eventos-container">
    <div class="filtros-movil">
        <div class="filtro-principal">
            <h3>Cotizador</h3>
            <form id="filtro-eventos-form">
                <div class="filtro-item">
                    <label for="categoria">Categoría:</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas las categorías</option>
                        <?php
                        $categorias = cotizador_eventos_get_categorias();
                        foreach ($categorias as $categoria) {
                            echo '<option value="' . esc_attr($categoria->slug) . '">' . esc_html($categoria->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filtro-item">
                    <label for="ciudad">Ciudad:</label>
                    <select id="ciudad" name="ciudad">
                        <option value="">Todas las ciudades</option>
                        <?php
                        $ciudades = cotizador_eventos_get_ciudades();
                        foreach ($ciudades as $ciudad) {
                            echo '<option value="' . esc_attr($ciudad->name) . '">' . esc_html($ciudad->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filtro-item">
                    <label for="fecha">Fecha del evento:</label>
                    <input type="date" id="fecha" name="fecha">
                </div>
                <div class="filtro-buttons">
                    <button type="submit">Filtrar</button>
                    <button type="button" id="limpiar-filtros">Limpiar filtros</button>
                </div>
            </form>
        </div>
        
        <div class="filtros-laterales">
            <div class="filtro-nombre">
                <h3>Busca por nombre de servicio</h3>
                <input type="text" id="nombre" name="nombre" placeholder="ej. Tercer Octante">
                <button id="buscar-nombre">Buscar</button>
            </div>
            <div id="filtros-adicionales"></div>
        </div>
    </div>
    
    <div class="filtro-eventos-y-resultados">
        <div id="resultados-eventos">
            <!-- Los resultados se cargarán aquí mediante AJAX -->
        </div>
    </div>
</div>
<?php if (wp_is_mobile()): ?>
    <button id="filtro-toggle">🔍</button>
<?php endif; ?>