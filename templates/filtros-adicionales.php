<div class="filtros-adicionales">
    <div class="filtro-invitados">
        <h4>Cantidad de Invitados</h4>
        <input type="number" id="cantidad-invitados" name="cantidad_invitados" placeholder="Número de invitados">
    </div>

    <div class="filtro-precio">
        <h4>Rango de Precio</h4>
        <div class="input-range">
            <input type="number" id="min-precio" name="min_precio" placeholder="Mínimo">
            <input type="number" id="max-precio" name="max_precio" placeholder="Máximo">
        </div>
        <div id="slider-precio"></div>
    </div>

    <div class="filtro-servicios">
        <h4>Servicios Incluidos</h4>
        <label><input type="checkbox" name="servicios[]" value="solo-renta"> Sólo Renta</label>
        <label><input type="checkbox" name="servicios[]" value="con-banquete"> Con Banquete</label>
        <label><input type="checkbox" name="servicios[]" value="con-mobiliario"> Con Mobiliario</label>
        <label><input type="checkbox" name="servicios[]" value="con-decoracion"> Con Decoración</label>
        <label><input type="checkbox" name="servicios[]" value="con-musica"> Con Música</label>
        <label><input type="checkbox" name="servicios[]" value="todo-incluido"> Todo Incluido</label>
    </div>
</div>