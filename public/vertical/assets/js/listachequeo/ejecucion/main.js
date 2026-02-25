// const { defaultsDeep } = require("lodash");

//const { countBy } = require("lodash");

let localizacion = { latitud: 0, longitud: 0, direccion: '' };

let objetoCamara = {
    idPregunta: "",
    idListaChequeoEjecutada: "",
    imagenes: [],
};

var formDataAdjuntos = new FormData()
var objetoAdjuntos = {
    idPregunta: "",
    idListaChequeoEjecutada: "",
    adjuntos: []
}

if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(function (position) {
        localizacion.latitud = position.coords.latitude;
        localizacion.longitud = position.coords.longitude;
        const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${localizacion.latitud},${localizacion.longitud}&key=AIzaSyClW1wVkJdrfHUH_i0hMhDmPfwVq0xTrv8`;

        fetch(url)
            .then(respuesta => {

                return respuesta.json();
            }).then(direccion => {

                localizacion.direccion = direccion.results[1].formatted_address;
            }).catch(error => {
                swal({
                    title: 'gps Bloqueado',
                    text: '¡Tu gps esta bloqueado debes habilitarlo para continuar',
                    type: 'warning',
                    showCancelButton: false,
                    buttonsStyling: false,
                    confirmButtonClass: 'btn btn-success',
                    confirmButtonText: 'Aceptar',
                    cancelButtonClass: 'btn btn-secondary',
                });
                return;
            });
        // console.log("Found ykour location nLat : "+position.coords.latitude+" nLang :"+ position.coords.longitude);
    });
} else {
    console.log("Navegador no permitio obtener la navegación");
}

$(document).ready(function () {
    for (let i = 1; i <= 10; i++) {
        $('#respuestaSelectNumerico').append(`<option value="${i}">${i}</option>`);
    }

    /*
    referencias.forEach(element => {
        $('#respuestaSelectReferencias').append(`<option value="${element}">${element}</option>`);
    });

    productos.forEach(element => {
        $('#respuestaSelectProductos').append(`<option value="${element}">${element}</option>`);
    });
    */

    equipos_max.forEach(element => {
        $('#respuestaSelectEquipos').append(`<option value="${element}">${element}</option>`);
    });

    /*
     lotes.forEach(element => {
         $('#respuestaSelectLotes').append(`<option value="${element}">${element}</option>`);
     });
 
    */


    areas.forEach(element => {
        $('#respuestaSelectAreas').append(`<option value="${element.nombre}">${element.nombre}</option>`);
    });

    // Date Picker
    $('#datepicker-autoclose').datepicker({
        autoclose: true,
        format: 'dd/mm/yyyy',
        language: 'es'
    });

    $(".select2").select2({
        dropdownParent: $('#popUpRespuestaFecha'),
        minimumResultsForSearch: 0
    });

    //Cargar archivos adjuntos
    cargarArchivosAdjuntos()

    IniciarVista();
});

function IniciarVista() {
    let idListaChequeo = $('.datosLista').attr('idListaChequeo');
    let idListaEjecucion = $('.datosLista').attr('idListaChequeoEjecutada');

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/enlistarListaChequeo',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idListaChequeo: idListaChequeo,
            idListaEjecucion: idListaEjecucion
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            console.log(data)
            switch (data.codigoRespuesta) {
                case 202:
                    //CARGAR DE PREGUNTAS 
                    let categorias = '';
                    $.each(data.datos.categoriasPreguntas, function (indexInArray, categoria) {

                        let stringPreguntas = '';
                        $.each(categoria.PREGUNTAS, function (indexInArray, pregunta) {
                            //console.log(pregunta)
                            let arrayRespuestas = pregunta.tiposRespuestas;
                            if (pregunta.permitir_noaplica != 0) {
                                if (arrayRespuestas.length != 0) {
                                    let objeto = { id: 0, valor_personalizado: 'N/A' };
                                    if (arrayRespuestas[0].EXISTE_REGISTRO != 0) {
                                        if (arrayRespuestas[0].NA == 1)
                                            objeto['rta'] = 0;
                                    }

                                    arrayRespuestas.push(objeto);
                                }

                            }

                            stringPreguntas += ComponentePregunta(
                                pregunta.id,
                                pregunta.nombre,
                                arrayRespuestas,
                                pregunta.OpcionesGenerales,
                                parseFloat(pregunta.ponderado).toFixed(0),
                                pregunta.opcionesGeneralesLlenas,
                                pregunta.opcionesGeneralesLlenasFotos,
                                pregunta.opcionesGeneralesLlenasAdjuntos,
                                pregunta.plan_accion_manu,
                                pregunta.opcionesGeneralesLlenasPlanAccionM,
                                pregunta.tipo_respuesta_id,
                                pregunta
                            );
                        });

                        categorias += ComponenteCategoriaCompleta(
                            categoria.CATEGORIA_ID,
                            categoria.NOMBRE_CATEGORIA,
                            stringPreguntas,
                            categoria.PONDERADO,
                            categoria.ETIQUETA
                        );
                    });

                    $('.listaChequeoNombre').html(data.datos.encabezado.NOMBRE_LISTA_CHEQUEO);
                    $('.dev-obs-general').html(data.datos.encabezado.OBSERVACION_GENERAL);

                    // CARGA EVALUADO A
                    $('.evaluandoA').html('');

                    $.each(data.datos.encabezado.SelectLlenado, function (key, value) {
                        $('.evaluandoA')
                            .append($("<option></option>")
                                .attr("value", value.ID)
                                .text(value.NOMBRE));
                    });

                    $(".evaluandoA").select2({ width: "20px" });

                    if (data.datos.encabezado.HABILITA_SELECT == 0)
                        $('.evaluandoA').attr('disabled', 'disabled');
                    else
                        $('.evaluandoA').removeAttr('disabled');

                    if (data.datos.encabezado.HABILITA_FECHA == 0)
                        $('#datepicker-autoclose').attr('disabled', 'disabled');
                    else
                        $('#datepicker-autoclose').removeAttr('disabled');

                    $('.contenedorCategorias').html(categorias);
                    $('[data-toggle="tooltip"]').tooltip();
                    break;

                case 402:
                    Swal.fire({
                        title: 'No puedes continuar con la ejecución',
                        text: "Ya superaste las veces que puedes ejecutar esta lista de cheuqueo",
                        icon: 'danger',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'Aceptar',
                        allowOutsideClick: false
                    }).then((result) => {
                        let url = window.location.origin + '/listachequeo/mislistas';
                        window.location.href = url;
                    });
                    break;
                default:

                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar()
        }
    });
}

function ComponentePregunta(idPregunta, pregunta, respuestas, opcionesRespuesta, ponderado, opcLlenas, opcLlenasFotos, opcLlenasAdjuntos, planAccion, opcionesGeneralesLlenasPlanAccionM, tipoRespuestaBk, objetoPregunta) {
    let string = '';
    console.log(respuestas)
    let stringRespuestas = '';
    let tipoRespuesta = 0;
    if (respuestas.length != 0)
        tipoRespuesta = respuestas[0].TIPO_RESPUESTA;


    $.each(respuestas, function (indexInArray, item) {
        console.log(tipoRespuestaBk);
        switch (tipoRespuestaBk) {
            case 3: //RESPUESTA ABIERTA
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    Responder
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;

            case 5: //RESPUESTA NÚMERICA
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    Agregar número
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;

            case 6: //RESPUESTA IEMPO
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Agregar tiempo
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;

            case 7: //RESPUESTA FECHA
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Agregar Fecha
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;

            case 8: //RESPUESTA SELECTOR NÚMERICO
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Seleccionar
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;

            case 9: //RESPUESTA SELECTOR REFERNCIAS
                if (item.id == 0) //N/A
                {

                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    console.log("rta", item.rta)
                    console.log("id", item.id)
                    console.log("resultado", item.id == item.rta)
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    /*stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Seleccionar
                                                </div>
                                            </div>
                                        </div>`; */


                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" idRespuesta="${item.id}">
                                                   ${item.rta_abierta == null ? 'Sin respuesta' : item.rta_abierta}
                                                </div>
                                            </div>
                                        </div>`
                }
                break;

            case 10: //RESPUESTA SELECTOR PRODUCTOS
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {

                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    /*stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Seleccionar
                                                </div>
                                            </div>
                                        </div>`; */

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" idRespuesta="${item.id}">
                                                   ${item.rta_abierta == null ? 'Sin respuesta' : item.rta_abierta}
                                                </div>
                                            </div>
                                        </div>`
                }
                break;

            case 11: //RESPUESTA SELECTOR EQUIPOS
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {

                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Seleccionar
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;

            case 13: //RESPUESTA SELECTOR LOTE
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   ${item.rta_abierta == null ? 'Sin respuesta' : item.rta_abierta}
                                                </div>
                                            </div>
                                        </div>`;


                }
                break;
            case 14: //RESPUESTA SELECTOR AREA
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                }
                else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Seleccionar
                                                </div>
                                            </div>
                                        </div>`;
                }
                break;
            case 15: //RESPUESTA RANGO
                if (item.id == 0) //N/A
                {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';

                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="0" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                    ${item.valor_personalizado}
                                                </div>
                                            </div>
                                        </div>`;
                } else {
                    let stringClase = '';
                    if (item.id == item.rta)
                        stringClase = stringClase = 'respuestaSeleccion';
                    else
                        stringClase = '';
                    
                    stringRespuestas += `<div class="m-l-5 m-r-5">
                                            <div class="form-group">
                                                <div class="respuesta bg-gray ${stringClase}" valorMin="${objetoPregunta.valor_min}" valorMax="${objetoPregunta.valor_max}" tipoRespuesta="${tipoRespuestaBk}" respuestaAbierta="${item.rta_abierta}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                   Seleccionar
                                                </div>
                                            </div>
                                        </div>`; 
                                      
                }
                break;

            default:
                let stringClase = '';
                if (item.id == item.rta)
                    stringClase = 'respuestaSeleccion';
                else
                    stringClase = '';

                stringRespuestas += `<div class="m-l-5 m-r-5">
                                        <div class="form-group">
                                            <div class="respuesta bg-gray ${stringClase}" tipoRespuesta="${tipoRespuestaBk}" onclick="OnClickRespuesta(this);" idRespuesta="${item.id}">
                                                ${item.valor_personalizado}
                                            </div>
                                        </div>
                                    </div>`;
                break;
        }

    });

    let stringRespuestasOpciones = '';

    $.each(opcionesRespuesta, function (indexInArray, opcionRespuesta) {
        let stringComentario = '';
        let claseSeleccionada = '';
        if (opcLlenas != null && opcLlenas != undefined) {
            if (opcionRespuesta.id == 2) // COMENTARIO
            {
                if (opcLlenas.COMENTARIO == 1) {
                    stringComentario = opcLlenas.TEXTO_COMENTARIO;
                    claseSeleccionada = 'respuestaOpcSeleccion';
                }
            }
        }

        if (opcionRespuesta.id == 1) // FOTO
        {
            if (opcLlenasFotos != 0 && (opcLlenasFotos != null || opcLlenasFotos != undefined)) {
                claseSeleccionada = 'respuestaOpcSeleccion';
                stringComentario = '';
            }
        }

        if (opcionRespuesta.id == 3) // ARCHIVOS ADJUNTOS
        {
            if (opcLlenasAdjuntos != 0 && (opcLlenasAdjuntos != null || opcLlenasAdjuntos != undefined)) {
                claseSeleccionada = 'respuestaOpcSeleccion';
                stringComentario = '';
            }

        }


        stringRespuestasOpciones += `<div class="m-l-10">
                                            <div class="form-group">
                                                <div class="respuestaOpc bg-gray ${claseSeleccionada}" comentarioPregunta="${stringComentario}" onclick="OnClickOpcionRespuesta(this);" idRespuestaOpcion="${opcionRespuesta.id}">
                                                    <i class="${opcionRespuesta.icono}"></i>
                                                </div>
                                            </div>
                                        </div>`
    });

    //VALIDO SI HAY PLAN DE ACCION Y AGREGO EL BOTON A LA PREGUNTA
    if (planAccion.length > 0) {
        //VALIDO SI YA LLENO EL PLAN DE ACCION MANUAL Y ASIGNO LA CLASE PARA DEJAR EL BOTON CHEQUEADO
        let claseSeleccionada = '';
        if (opcionesGeneralesLlenasPlanAccionM) {
            claseSeleccionada = 'respuestaOpcSeleccion';
        }
        stringRespuestasOpciones += `<div class="m-l-10">
                                            <div class="form-group">
                                                <div class="respuestaOpc bg-gray ${claseSeleccionada} btnplanaccion"  onclick="OnClickPlanAccionM(this);" >
                                                    <i class="mdi mdi-file-document"></i>
                                                </div>
                                            </div>
                                        </div>`
    }

    string = `<div class="card card-body">
            <div class="row m-b-10 contenedorPregunta" idPregunta="${idPregunta}" ponderadoPregunta="${ponderado}">
                    <div class="col-lg-12">
                        <div class="form-group">
                            <p>
                            ${pregunta}
                            </p>
                        </div>
                    </div>

                    <div class="col-lg-12">
                            <div class="row contenedorRespuestas" tipoRespuesta="${tipoRespuesta}">
                                ${stringRespuestas}
                            </div>

                            <div class="row contenedorOpcionesRespuestas">
                                ${stringRespuestasOpciones}
                            </div>
                    </div>

                </div>
            </div>
                
                `;

    return string;
}

function ComponenteCategoriaCompleta(idCategoria, nombreCategoria, preguntas, ponderado, etiqueta) {
    let string = '';

    let spanSticker = '';

    if (etiqueta != '')
        spanSticker = `<span class="mdi mdi-sticker" style="font-size: 25px;margin-left: 25px;color:white;" data-toggle="tooltip" data-placement="top" title="${etiqueta}"></span>`;

    string += `<div class="m-b-10 contenedorUnaCategoria" idCategoria="${idCategoria}" ponderadoCategoria="${ponderado}">
                    <div class="p-3 categoriaContenedor" id="headingOne" data-toggle="collapse" onclick="OnClickCategoria(this);">
                        <h6 class="m-0">
                            <a class="text-white">
                                    ${nombreCategoria}
                            </a>
                        </h6>
                        ${spanSticker}
                    </div>

                    <div class="collapse m-t-10 show">
                        <div class="card">
                            <div class="card-body m-t-10 contenedosPreguntas">
                                ${preguntas}
                            </div>
                        </div>
                        
                    </div>
                </div>`;

    return string;

}

function OnClickCategoria(control) {
    let padre = $(control).parent();
    if ($(padre).find('.collapse ').hasClass('show'))
        $(padre).find('.collapse ').collapse('hide');
    else
        $(padre).find('.collapse ').collapse('show');
}

var objetoEnviarGlobal = null;
var controlGlobal = null;
function OnClickRespuesta(control) {
    if ($(control).hasClass('respuestaSeleccion') && ($(control).attr('tipoRespuesta') != 3 && $(control).attr('tipoRespuesta') != 5 && $(control).attr('tipoRespuesta') != 6 && $(control).attr('tipoRespuesta') != 7 && $(control).attr('tipoRespuesta') != 8 && $(control).attr('tipoRespuesta') != 9 && $(control).attr('tipoRespuesta') != 10  && $(control).attr('tipoRespuesta') != 11 && $(control).attr('tipoRespuesta') != 13 && $(control).attr('tipoRespuesta') != 14 && $(control).attr('tipoRespuesta') != 15))
        return;

    let contenedorPregunta = $(control).parents().eq(4);
    let contenedorCategoria = $(control).parents().eq(9);
    let idPregunta = $(contenedorPregunta).attr('idPregunta');
    let ponderadoPregunta = $(contenedorPregunta).attr('ponderadopregunta');
    let idCategoria = $(contenedorCategoria).attr('idcategoria');
    let ponderadoCategoria = $(contenedorCategoria).attr('ponderadocategoria');
    let idRespuesta = $(control).attr('idrespuesta');
    let idListaChequeoEjec = $('.datosLista').attr('idListaChequeoEjecutada');
    let tipoRespuesta = $(control).attr('tipoRespuesta');
    let respuestaAbierta = $(control).attr('respuestaAbierta');

    let objetoEnviar =
    {
        _token: $('meta[name="csrf-token"]').attr('content'),
        idPregunta: idPregunta,
        ponderadoPregunta: ponderadoPregunta,
        idCategoria: idCategoria,
        ponderadoCategoria: ponderadoCategoria,
        idRespuesta: idRespuesta,
        idListaChequeoEjec: idListaChequeoEjec,
        tipoRespuesta: tipoRespuesta,
        respuestaAbierta: ''
    };

    $('#respuestaFechaText').show();
    $('#respuestaSelectReferencias').next('.select2-container').hide();
    $('#respuestaSelectProductos').next('.select2-container').hide();
    $('#respuestaSelectEquipos').next('.select2-container').hide();
    $('#respuestaSelectNumerico').next('.select2-container').hide();
    $('#respuestaSelectLotes').next('.select2-container').hide();
    $('#respuestaSelectAreas').next('.select2-container').hide();
    $('#respuestaSelectMotivos').next('.select2-container').hide();
     $('#respuestaNum').hide();

    $('#popUpRespuestaFecha .modal-title').text('Guardar respuesta');
    $('#popUpRespuestaFecha .cancelarPopUpRespuestaFecha').text('Cancelar');

    if (tipoRespuesta == 3) //RESPUESTA ABIERTA
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        if (respuestaAbierta != 'null')
            $('#respuestaAbiertaText').val(respuestaAbierta);
        else
            $('#respuestaAbiertaText').val('');

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuesta').hide();
        } else {
            $('.guardarRespuesta').show();
        }

        $('#popUpRespuestaAbierta').modal('show');
    }
    else if (tipoRespuesta == 5) //RESPUESTA NÚMERICA
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        if (respuestaAbierta != 'null')
            $('#respuestaNumericaText').val(respuestaAbierta);
        else
            $('#respuestaNumericaText').val('');

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaNumerica').hide();
        } else {
            $('.guardarRespuestaNumerica').show();
        }

        $('#popUpRespuestaNumerica').modal('show');
    }
    else if (tipoRespuesta == 6) //RESPUESTA TIEMPO
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        if (respuestaAbierta != 'null')
            $('#respuestaTiempoText').val(respuestaAbierta);
        else
            $('#respuestaTiempoText').val('');

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaTiempo').hide();
        } else {
            $('.guardarRespuestaTiempo').show();
        }

        $('#popUpRespuestaTiempo').modal('show');
    }
    else if (tipoRespuesta == 8) //RESPUESTA SELECTOR NUMERICO
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectReferencias').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').hide();
        $('#respuestaSelectNumerico').next('.select2-container').show();
        $('#respuestaSelectLotes').next('.select2-container').hide();
        $('#respuestaSelectAreas').next('.select2-container').hide();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
         $('#respuestaNum').hide();

        $('#respuestaSelectNumerico').val(respuestaAbierta).trigger('change');


        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaFecha').hide();
        } else {
            $('.guardarRespuestaFecha').show();
        }

        $('#popUpRespuestaFecha').modal('show');
    } else if (tipoRespuesta == 9) //RESPUESTA SELECTOR REFERENCIAS
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectNumerico').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').hide();
        $('#respuestaSelectReferencias').next('.select2-container').show();
        $('#respuestaSelectLotes').next('.select2-container').hide();
        $('#respuestaSelectAreas').next('.select2-container').hide();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
         $('#respuestaNum').hide();

        const texto = respuestaAbierta.split('-')[0].trim();
        let resultado = referencias.find(ref => ref.split('-')[0].trim() === texto)
        $('#respuestaSelectReferencias').val(resultado).prop('disabled', true).trigger('change');
        $('#popUpRespuestaFecha .modal-title').text('Ver respuesta');
        $('#popUpRespuestaFecha .cancelarPopUpRespuestaFecha').text('Cerrar');

        $('.guardarRespuestaFecha').hide();


        $('#popUpRespuestaFecha').modal('show');
    } else if (tipoRespuesta == 10) //RESPUESTA SELECTOR PRODUCTOS
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectNumerico').next('.select2-container').hide();
        $('#respuestaSelectReferencias').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').show();
        $('#respuestaSelectLotes').next('.select2-container').hide();
        $('#respuestaSelectAreas').next('.select2-container').hide();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
         $('#respuestaNum').hide();

        const texto = respuestaAbierta.split('-')[1].trim();
        let resultado = productos.find(ref => ref.split('-')[1].trim() === texto)
        $('#respuestaSelectProductos').val(resultado).prop('disabled', true).trigger('change');

        $('#popUpRespuestaFecha .modal-title').text('Ver respuesta');
        $('#popUpRespuestaFecha .cancelarPopUpRespuestaFecha').text('Cerrar');
        $('.guardarRespuestaFecha').hide();


        $('#popUpRespuestaFecha').modal('show');
    } else if (tipoRespuesta == 11) //RESPUESTA SELECTOR EQUIPOS§
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectNumerico').next('.select2-container').hide();
        $('#respuestaSelectReferencias').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').hide();
        $('#respuestaSelectLotes').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').show();
        $('#respuestaSelectAreas').next('.select2-container').hide();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
         $('#respuestaNum').hide();

        $('#respuestaSelectEquipos').val(respuestaAbierta).trigger('change');

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaFecha').hide();
        } else {
            $('.guardarRespuestaFecha').show();
        }

        $('#popUpRespuestaFecha').modal('show');
    }
    else if (tipoRespuesta == 13) //RESPUESTA SELECTOR LOTES
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectNumerico').next('.select2-container').hide();
        $('#respuestaSelectReferencias').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').hide();
        $('#respuestaSelectLotes').next('.select2-container').show();
        $('#respuestaSelectAreas').next('.select2-container').hide();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
         $('#respuestaNum').hide();

        $('#respuestaSelectLotes').val(respuestaAbierta).trigger('change');

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaFecha').hide();
        } else {
            $('.guardarRespuestaFecha').show();
        }

        $('#popUpRespuestaFecha').modal('show');
    } else if (tipoRespuesta == 14) //RESPUESTA SELECTOR AREAS
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectNumerico').next('.select2-container').hide();
        $('#respuestaSelectReferencias').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').hide();
        $('#respuestaSelectLotes').next('.select2-container').hide();
        $('#respuestaSelectAreas').next('.select2-container').show();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
        $('#respuestaNum').hide();

        $('#respuestaSelectAreas').val(respuestaAbierta).trigger('change');

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaFecha').hide();
        } else {
            $('.guardarRespuestaFecha').show();
        }

        $('#popUpRespuestaFecha').modal('show');
    } else if (tipoRespuesta == 15) //RESPUESTA SELECTOR MOTIVOS
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        $('#respuestaFechaText').hide();
        $('#respuestaSelectNumerico').next('.select2-container').hide();
        $('#respuestaSelectReferencias').next('.select2-container').hide();
        $('#respuestaSelectProductos').next('.select2-container').hide();
        $('#respuestaSelectEquipos').next('.select2-container').hide();
        $('#respuestaSelectLotes').next('.select2-container').hide();
        $('#respuestaSelectAreas').next('.select2-container').hide();
        $('#respuestaSelectMotivos').next('.select2-container').hide();
        $('#respuestaNum').show();

        if (respuestaAbierta != 'null')
            $('#respuestaNum').val(respuestaAbierta);
        else
            $('#respuestaNum').val('');  
                

        if ($(control).hasClass('respuestaSeleccionNoPermitido')) {
            $('.guardarRespuestaFecha').hide();
        } else {
            $('.guardarRespuestaFecha').show();
        }

        $('#popUpRespuestaFecha').modal('show');
    }
    else if (tipoRespuesta == 7) //RESPUESTA FECHA
    {
        objetoEnviarGlobal = objetoEnviar;
        controlGlobal = control;

        if (respuestaAbierta != 'null')
            $('#respuestaFechaText').val(respuestaAbierta);
        else
            $('#respuestaFechaText').val(new Date().toLocaleDateString("en-CA"));

        $('#popUpRespuestaFecha').modal('show');
    } else {
        $.ajax({
            type: 'POST',
            url: '/listachequeo/ejecucion/agregarRespuestaListaChequeo',
            data: objetoEnviar,
            cache: false,
            dataType: 'json',
            beforeSend: function () {
                // CargandoMostrar();
            },
            success: function (data) {
                // CargandoNoMostrar();
                switch (data.codigoRespuesta) {
                    case 206:

                        let padre = $(control).parents().eq(2);
                        let listaRespuestas = $(padre).find('.respuesta');
                        $.each(listaRespuestas, function (indexInArray, itemRespuesta) {
                            $(itemRespuesta).removeClass('respuestaSeleccion');
                            $(itemRespuesta).attr('respuestaAbierta', 'null');
                        });

                        $(control).addClass('respuestaSeleccion');
                        toastr.success(data.mensaje);
                        break;

                    case 406:
                        toastr.error(data.mensaje);

                        break;

                    default:
                        break;
                }

            },
            error: function (data) {
                // CargandoNoMostrar()
            }
        });
    }


}

function OnClickGuardarRespuestaAbierta() {
    if ($('#respuestaAbiertaText').val() == '') {
        toastr.warning('Debes escribir tu respuesta');
        $('#respuestaAbiertaText').focus();
        return;
    }

    objetoEnviarGlobal.respuestaAbierta = $('#respuestaAbiertaText').val();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/agregarRespuestaListaChequeo',
        data: objetoEnviarGlobal,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            // CargandoMostrar();
        },
        success: function (data) {
            // CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:

                    let padre = $(controlGlobal).parents().eq(2);
                    let listaRespuestas = $(padre).find('.respuesta');
                    $.each(listaRespuestas, function (indexInArray, itemRespuesta) {
                        $(itemRespuesta).removeClass('respuestaSeleccion');
                    });

                    $(controlGlobal).addClass('respuestaSeleccion');
                    $(controlGlobal).attr('respuestaAbierta', $('#respuestaAbiertaText').val());
                    $('#popUpRespuestaAbierta').modal('hide');
                    toastr.success(data.mensaje);
                    break;

                case 406:
                    toastr.error(data.mensaje);

                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            // CargandoNoMostrar()
        }
    });
}

var controlPlanAccionM;
//FUNCION PARA PLAN DE ACCION MANUAL
function OnClickPlanAccionM(control) {
    let div = $(control).parent().parent().parent().parent().parent()
    let idPregunta = $(div).attr('idpregunta')
    let idListaChequeo = $('.datosLista').attr('idListaChequeo');
    let evaluadoId = $('.evaluandoA').val();
    let contenedorRespuestas = $(control).parents().eq(3).find('.contenedorRespuestas')

    if ($(contenedorRespuestas).find('.respuestaSeleccion').length == 0) {
        toastr.warning('Debes seleccionar una respuesta antes');
        return;
    }
    controlPlanAccionM = control;
    //CONSULTO LOS DATOS PARA CARGAR LOS INPUTS
    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/opciones_plan_accion_manual',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            idpregunta: idPregunta,
            idListaChequeo: idListaChequeo,
            evaluadoId: evaluadoId
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (res) {
            let html = ''
            $.each(res.datos, function (index, valor) {
                let etiqueta = 'input'
                let obligatorio = ''
                let tipo_input = 'text'
                //VALIDO EL TIPO DE INPUT
                if (valor.opcion_id == 4) {
                    tipo_input = 'date'
                } else if (valor.opcion_id == 7) {
                    tipo_input = 'number'
                } else if (valor.opcion_id == 9) {
                    etiqueta = 'textarea'
                }

                let caracter = "";
                if (valor.obligatorio == 1) {
                    obligatorio = 'required="required"';
                    caracter = `<span style="color:red;font-weight: bold;">*</span>`
                }

                if (valor.opcion_id == 8) //SI ES RESPONSABLE DEBE SER UN SELECT
                {

                    let responsableOpciones = '<option value="0">Selecciona el responsable</option>';
                    $.each(res.Responsables, function (indexInArray, responsable) {
                        responsableOpciones += `<option value="${responsable.id}">${responsable.nombre_completo} (${responsable.CARGO})</option>`;
                    });

                    html += `
                    <div class="form-group form-inputs" >
                        <label for="exampleFormControlInput1">${valor.nom_opcion} ${caracter}</label>
                        <select idopc="${valor.opcion_id}" class="form-control select2 border-rigth-0 selectResponsablePopUp" esObligatorio="${valor.obligatorio}">
                            ${responsableOpciones}
                        </select>
                    </div>`
                }
                else if (valor.opcion_id == 5) // SI ES ¿QUIEN LO HARÁ DEBE SER UN SELECT
                {
                    let quienHaraOpciones = '<option value="0">Selecciona a quién</option>';
                    $.each(res.Responsables, function (indexInArray, responsable) {
                        quienHaraOpciones += `<option value="${responsable.id}">${responsable.nombre_completo} (${responsable.CARGO})</option>`;
                    });

                    html += `
                    <div class="form-group form-inputs" >
                        <label for="exampleFormControlInput1">${valor.nom_opcion} ${caracter}</label>
                        <select idopc="${valor.opcion_id}" class="form-control select2 border-rigth-0 selectQuienLoHaraPopUp" esObligatorio="${valor.obligatorio}">
                            ${quienHaraOpciones}
                        </select>
                    </div>`
                }
                else {
                    html += `
                    <div class="form-group form-inputs" >
                        <label for="exampleFormControlInput1">${valor.nom_opcion} ${caracter}</label>
                        <${etiqueta} type="${tipo_input}" class="form-control input-plan-accionm" idopc="${valor.opcion_id}"  ${obligatorio} />
                    </div>
                `
                }


            })
            //SI EL BOTON SE ENCUENTRA ACTIVO ENTONCES CONSULTO LA DATA QUE SE ENCUENTRA ALMACENADA EN LA BD PARA LUEGO PINTARLA
            if ($(control).hasClass('respuestaOpcSeleccion')) {
                $.ajax({
                    type: 'POST',
                    url: '/listachequeo/ejecucion/plan_accion_manual/datos',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        idpregunta: idPregunta
                    },
                    cache: false,
                    dataType: 'json',
                    beforeSend: function () {
                        CargandoMostrar();
                    },
                    success: function (res) {
                        let inputs = $('.form-plan-accion').find('.input-plan-accionm')
                        let selects = $('.form-plan-accion').find('.selectResponsablePopUp');
                        let selectsQuien = $('.form-plan-accion').find('.selectQuienLoHaraPopUp');

                        $.each(res.data, function (i, el) {
                            $.each(inputs, function (index, elem) {
                                let idopc = parseInt($(elem).attr('idopc'))

                                if (idopc == el.plan_accio_man_opc_id) {
                                    $(elem).val(el.respuesta)
                                }
                            })

                            if ($(selects).length != 0) // SI TIENE SELECTS
                            {
                                let controlSelect = $('.form-plan-accion').find('.selectResponsablePopUp')[0];
                                let idOpc = $(controlSelect).attr('idopc');

                                if (idOpc == el.plan_accio_man_opc_id)
                                    $(controlSelect).val(el.respuesta).change();
                            }

                            if ($(selectsQuien).length != 0) // SI TIENE SELECTS (QUIEN LO HARA)
                            {
                                let controlSelectQuienLoHara = $('.form-plan-accion').find('.selectQuienLoHaraPopUp')[0];
                                let idOpc = $(controlSelectQuienLoHara).attr('idopc');

                                if (idOpc == el.plan_accio_man_opc_id)
                                    $(controlSelectQuienLoHara).val(el.respuesta).change();
                            }
                        })

                    },
                    error: function (error) {
                        toastr.error('Error al obtener la informacion de la lista de chequeo')
                    }
                })
            }

            $('.cuerpo-pa-m').append(html)
            $(".selectResponsablePopUp").select2({ dropdownParent: $('#modal-plan-manual'), });
            $(".selectQuienLoHaraPopUp").select2({ dropdownParent: $('#modal-plan-manual'), });
            CargandoNoMostrar()
            $('.guardar-plan-accion-manual').attr("idpregunta", idPregunta)
            $('#modal-plan-manual').modal('show')
        },
        error: function (err) {
            toastr.warning('Error al obtener la data para plan de accion manual')
        }
    })


}

$('#modal-plan-manual').on('hidden.bs.modal', function (e) {
    $('.cuerpo-pa-m').html('')
})

$('.guardar-plan-accion-manual').on('click', function () {
    let inputs = $('.form-plan-accion').find('.input-plan-accionm')
    let idpregunta = $('.guardar-plan-accion-manual').attr('idpregunta')
    let idListaChequeo = $('.datosLista').attr('idlistachequeo')
    let data = {
        idpregunta,
        idListaChequeo

    }

    let algunCampoRequerido = 0;
    $.each(inputs, function (index, elem) {
        let idopc = $(elem).attr('idopc')
        let requerido = $(elem).attr('required');

        if (requerido == 'required' && $(elem).val() == '')
            algunCampoRequerido = 1;

        if (data.hasOwnProperty(idopc) == false && idopc != undefined) {
            data[idopc] = $(elem).val()
        }

    })

    if ($('.form-plan-accion').find('.selectResponsablePopUp').length != 0) {
        let controlSelect = $('.form-plan-accion').find('.selectResponsablePopUp')[0];
        let idOpc = $(controlSelect).attr('idopc');
        let esObligatorio = $(controlSelect).attr('esObligatorio');
        if (esObligatorio == 1) {
            if ($(controlSelect).val() == 0) {
                toastr.warning('Debes ingresar los campos que son requeridos (*)')
                return;
            }
        }

        if (data.hasOwnProperty(idOpc) == false && idOpc != undefined)
            data[idOpc] = $(controlSelect).val();
    }

    if ($('.form-plan-accion').find('.selectQuienLoHaraPopUp').length != 0) {
        let controlSelectQuienHara = $('.form-plan-accion').find('.selectQuienLoHaraPopUp')[0];
        let idOpcQuien = $(controlSelectQuienHara).attr('idopc');
        let esObligatorioQuien = $(controlSelectQuienHara).attr('esObligatorio');
        if (esObligatorioQuien == 1) {
            if ($(controlSelectQuienHara).val() == 0) {
                toastr.warning('Debes ingresar los campos que son requeridos (*)')
                return;
            }
        }

        if (data.hasOwnProperty(idOpcQuien) == false && idOpcQuien != undefined)
            data[idOpcQuien] = $(controlSelectQuienHara).val();
    }

    if (algunCampoRequerido != 0) {
        toastr.warning('Debes ingresar los campos que son requeridos (*)')
        return;
    }

    //FUNCION PARA GUARDAR LOS DATOS
    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/guardar_plan_accion_manual',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: data,
        dataType: 'json',
        success: function (resp) {
            $('#modal-plan-manual').modal('hide')
            $(controlPlanAccionM).addClass('respuestaOpcSeleccion')
            toastr.success('Se ha guardado el plan de accíon')
        },
        error: function (error) {
            toastr.warning('Error al obtener la data para plan de accion manual')
        }
    })
})


function OnClickOpcionRespuesta(control) {
    let idOpcion = $(control).attr('idrespuestaopcion');
    switch (idOpcion) {
        case "1": //FOTOS
            OnClickPopUpCamara(control);
            break;

        case "2": //COMENTARIO
            OnClickPopUpComentario(control);
            break;

        case "3": //ADJUNTO
            OnClickPopUpArchivosAdjuntos(control)
            break;

        case "4": //PLAN ACCIÓN
            onClickPopUpPlanAccionManual(conrol)
            break;

        default:
            break;
    }
}

let controlTemporal = 0;
function OnClickPopUpComentario(control) {
    let contenedorPregunta = $(control).parents().eq(4)
    let contenedorRespuestas = $(control).parents().eq(3).find('.contenedorRespuestas')

    if ($(contenedorRespuestas).find('.respuestaSeleccion').length == 0) {
        toastr.warning('Debes seleccionar una respuesta antes');
        return;
    }

    let idPregunta = $(contenedorPregunta).attr('idPregunta');

    controlTemporal = control;
    $('#popUpComentario').attr('idPregunta', idPregunta);
    $('#comentarioText').val($(control).attr('comentarioPregunta'));
    $('#popUpComentario').modal('show');
}

function OnClickPopUpComentarioCerrar() {
    $('#comentarioText').val('');
    $('#popUpComentario').modal('hide');
}

function OnClickPopUpRespuestaCerrar() {
    $('#respuestaAbiertaText').val('');
    $('#popUpRespuestaAbierta').modal('hide');
}

// FUNCION PARA ADJUNTAR ARCHIVOS
function OnClickPopUpArchivosAdjuntos(control) {
    let contenedorPregunta = $(control).parents().eq(4)
    let contenedorRespuestas = $(control).parents().eq(3).find('.contenedorRespuestas')
    let idPregunta = $(contenedorPregunta).attr('idPregunta')
    let idListaChequeoEjec = $('.datosLista').attr('idListaChequeoEjecutada')
    limpiarVariablesAdjuntos()
    if ($(contenedorRespuestas).find('.respuestaSeleccion').length == 0) {
        toastr.warning('Debes seleccionar una respuesta antes');
        return;
    }
    $('#popUpAdjuntos').attr('idPregunta', idPregunta)
    //Agrego el id de la lista de chequeo ejecutada y el id de la pregunta al objeto en donde voy a guardar los adjuntos
    objetoAdjuntos.idListaChequeoEjecutada = idListaChequeoEjec
    objetoAdjuntos.idPregunta = idPregunta
    controlTemporal = control;
    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/traerAdjuntosAuditoria',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idPregunta: idPregunta,
            idListaChequeoEjec: idListaChequeoEjec
        },
        cache: false,
        dataType: 'json',
        success: function (data) {
            CargandoNoMostrar()
            if (data.adjuntos.length != 0) {
                data.adjuntos.forEach((elem, index) => {
                    pintarNombreAdjuntos(elem.nombre, null, true, elem.id)
                })
            }
        },
        error: function (e) {

        }
    })
    $('#popUpAdjuntos').modal('show');
}

function cargarArchivosAdjuntos() {
    if ($('input[type="file"]')[0]) {
        var fileInput = document.querySelector('label[for="et_pb_contact_brand_file_request_0"]')
        fileInput.ondragover = function () {
            this.className = "et_pb_contact_form_label changed";
            return false;
        }
        fileInput.ondragleave = function () {
            this.className = "et_pb_contact_form_label";
            return false;
        }
        //Funcion cuando el usuario arrastra y suelta en el elemento
        fileInput.ondrop = function (e) {
            e.preventDefault()
            var fileNames = e.dataTransfer.files;
            let sizeFiles = tamanoArchivos(fileNames)
            let cantArchivosListados = $('.file_names').length
            let cantArchivosCargados = fileNames.length
            if (cantArchivosCargados <= 5 && sizeFiles <= 5100000) {
                let sizeAdjuntosBD = tamanoAdjuntosBD(objetoAdjuntos.adjuntos) //Valido el tamaño de los archivos cargados en BD
                if (cantArchivosListados < 5 && sizeAdjuntosBD <= 5100000) {//valido que los datos en la lista sean menor a 5
                    for (var x = 0; x < fileNames.length; x++) {
                        $ = jQuery.noConflict()
                        formDataAdjuntos.append("adjuntos[]", fileNames[x])
                        objetoAdjuntos.adjuntos.push({ name: fileNames[x].name, size: fileNames[x].size })
                        pintarNombreAdjuntos(fileNames[x].name, x, true)
                    }
                } else {
                    toastr.warning('Deben ser maximo 5 archivos y un total de 5MB.');
                }
            } else {
                toastr.warning('Deben ser maximo 5 archivos y un total de 5MB.');
            }

            formDataAdjuntos.append("idPregunta", objetoAdjuntos.idPregunta)
            formDataAdjuntos.append("idListaChequeoEjec", objetoAdjuntos.idListaChequeoEjecutada)
        }
        //Funcion cuando el usuario da clic en el boton Browser para cargar los archivos
        $('#et_pb_contact_brand_file_request_0').change(function () {
            let fileNames = $('#et_pb_contact_brand_file_request_0')[0].files
            let sizeFiles = tamanoArchivos(fileNames)
            let cantArchivosListados = $('.file_names').length
            let cantArchivosCargados = fileNames.length
            if (cantArchivosCargados <= 5 && sizeFiles <= 5100000) {
                let sizeAdjuntosBD = tamanoAdjuntosBD(objetoAdjuntos.adjuntos)
                if (cantArchivosListados < 5 && sizeAdjuntosBD <= 5100000) {//valido que los datos en la lista sean menor a 5
                    for (var x = 0; x < fileNames.length; x++) {
                        $ = jQuery.noConflict()
                        formDataAdjuntos.append("adjuntos[]", fileNames[x])
                        objetoAdjuntos.adjuntos.push({ name: fileNames[x].name, size: fileNames[x].size })
                        pintarNombreAdjuntos(fileNames[x].name, x, true)
                    }
                } else {
                    toastr.warning('Deben ser maximo 5 archivos y un total de 5MB.');
                }

            } else {
                toastr.warning('Deben ser maximo 5 archivos y un total de 5MB.');
            }
            formDataAdjuntos.append("idPregunta", objetoAdjuntos.idPregunta)
            formDataAdjuntos.append("idListaChequeoEjec", objetoAdjuntos.idListaChequeoEjecutada)
        })
    }
}

function tamanoAdjuntosBD(data) {
    let size = 0
    data.forEach(el => {
        size += el.size
    })
    return size
}

function pintarNombreAdjuntos(name, index, opcEliminar = false, idFile = '') {
    let spanEliminar = ``
    if (opcEliminar == true) {
        spanEliminar = `<span class="mdi mdi-close btnEliminar" index="${index}" file="${idFile}" OnClick="EliminarArchivo(this)" ></span>`
    }
    $('.contenedorNombres').append(`<div class="file_names" ><span class="mdi mdi-file-outline iconFile"></span>${name} ${spanEliminar}</div>`)
}

function tamanoArchivos(data) {
    let tamanoTotal = 0
    for (let i = 0; i < data.length; i++) {
        tamanoTotal += data[i].size
    }

    return tamanoTotal
}

function limpiarVariablesAdjuntos() {
    objetoAdjuntos.adjuntos = []
    $('.contenedorNombres').html('')
    formDataAdjuntos.delete('adjuntos[]')
}

function OnClickGuardarAdjuntos() {

    let filesData = formDataAdjuntos

    let archivosAgregados = $('.file_names').length
    if (archivosAgregados == 0 || objetoAdjuntos.adjuntos.length == 0) {
        toastr.warning('Debe seleccionar al menos 1 archivo.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/guardarAdjuntos',
        enctype: "multipart/form-data",
        processData: false,
        contentType: false,
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: filesData,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (res) {
            CargandoNoMostrar()
            toastr.success(res.msg)
            $(controlTemporal).addClass('respuestaOpcSeleccion')
            CerrarPopUpAdjuntarArchivos()


        },
        error: function (data) {
            CargandoNoMostrar()
        }
    });

}

function CerrarPopUpAdjuntarArchivos() {
    objetoAdjuntos.adjuntos = []
    $('.contenedorNombres').html('')
    formDataAdjuntos.delete('adjuntos[]')
    $('#popUpAdjuntos').modal('hide');
}

function EliminarArchivo(control) {
    let idFile = $(control).attr('file')
    let nombreFile = $(control).parent().text()
    let index = $(control).attr('index')
    let etiquetaPadre = $(control).parent()
    if (idFile == '' && index != 'null') {
        let values = formDataAdjuntos.getAll("adjuntos[]")
        //OBTENGO EL ID DEL ELEMENTO QUE VOY A ELIMINAR
        values.forEach((el, i) => {
            if (nombreFile == el.name)
                index = i
        })
        values.splice(index, 1)
        formDataAdjuntos.delete('adjuntos[]')
        $.each(values, function (i, v) {
            formDataAdjuntos.append("adjuntos[]", v)
        })
        etiquetaPadre.remove()
        let fileControl = document.getElementById('et_pb_contact_brand_file_request_0');
        fileControl.value = null;
        objetoAdjuntos.adjuntos = objetoAdjuntos.adjuntos.filter(function (elemento) { return elemento.name == nombreFile; });

    } else {//Si el archivo ya esta en el servidor, prodecedo a eliminarlo
        $.ajax({
            type: 'POST',
            url: '/listachequeo/ejecucion/elimnarArchivoAdjunto',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                idFile: idFile
            },
            cache: false,
            dataType: 'json',
            success: function (data) {
                toastr.success(data.msg)
            },
            error: function (e) {
                toastr.error('Error al eliminar el archivo en el servidor.')
            }
        })
        etiquetaPadre.remove()
    }
}
// FIN FUNCION PARA ADJUNTAR ARCHIVOS

function OnClickGuardarComentario() {
    let idPregunta = $('#popUpComentario').attr('idPregunta');
    let idListaChequeoEjec = $('.datosLista').attr('idListaChequeoEjecutada');
    let comentario = $('#comentarioText').val();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/agregarComentarioPregunta',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idPregunta: idPregunta,
            idListaChequeoEjec: idListaChequeoEjec,
            comentario: comentario
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:
                    toastr.success(data.mensaje);
                    OnClickPopUpComentarioCerrar();
                    $(controlTemporal).attr('comentarioPregunta', data.datos);
                    $(controlTemporal).addClass('respuestaOpcSeleccion');
                    break;

                case 400:
                    toastr.error(data.mensajes);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar()
        }
    });
}

let controlTemporalCamara = 0;
function OnClickPopUpCamara(control) {
    let contenedorRespuestas = $(control).parents().eq(3).find('.contenedorRespuestas');
    if ($(contenedorRespuestas).find('.respuestaSeleccion').length == 0) {
        toastr.warning('Debes seleccionar una respuesta antes');
        return;
    }

    let contenedorPregunta = $(control).parents().eq(4);
    let idPregunta = $(contenedorPregunta).attr('idPregunta');
    let idListaChequeoEjec = $('.datosLista').attr('idListaChequeoEjecutada');

    controlTemporalCamara = control;
    $('#popUpCamara').attr('idPregunta', idPregunta);
    ConfiguracionCamara();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/traerImagenesAuditoria',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idPregunta: idPregunta,
            idListaChequeoEjec: idListaChequeoEjec
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 202:
                    if (data.datos.length != 0) {
                        objetoCamara.imagenes = [];
                        $.each(data.datos, function (index, responseImg) {
                            let responseID = Math.random().toString(36).slice(2);
                            let imgResp = responseImg;

                            objetoCamara.imagenes.push({
                                ["id_" + responseID]: {
                                    img: imgResp,
                                },
                            });
                            let imagenResp = `<img src="${imgResp}" alt="" onclick="OnClickFotoTomada(this);" key-busqueda="${responseID}" class="cambiar-imagen scale">`;
                            $(".contenedorImgs").append(imagenResp);
                            $(".camara-footer")
                                .children()
                                .children()
                                .eq(0)
                                .children()
                                .eq(0)
                                .removeClass("nueva-foto btn-default");
                            $(".camara-footer")
                                .children()
                                .children()
                                .eq(0)
                                .children()
                                .eq(0)
                                .addClass("foto btn-primary");
                            $(".contenedorImgs").show();
                        });
                    } else {
                        objetoCamara.imagenes = [];
                        $(".contenedorImgs").empty();
                        $(".camara-footer")
                            .children()
                            .children()
                            .eq(0)
                            .children()
                            .eq(0)
                            .removeClass("nueva-foto btn-default");
                        $(".camara-footer")
                            .children()
                            .children()
                            .eq(0)
                            .children()
                            .eq(0)
                            .addClass("foto btn-primary");
                    }

                    $('#popUpCamara').modal('show');
                    break;

                case 404:
                    toastr.error(data.mensajes);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar()
        }
    });
}

let cxt;
function ConfiguracionCamara() {
    $(".modal-content-camera").modal("show");
    $("#video").css("position", "absolute");
    $("#video").css("opacity", "1");
    $("#video").css("z-index", "999");
    $("#canvas").css("position", "relative");
    $("#canvas").css("opacity", "0");

    $(".guardar").removeClass(".foto");

    $(".modal-content-camera")
        .find(".camara-footer")
        .children()
        .children()
        .children()
        .eq(0)
        .remove();

    $(".modal-content-camera")
        .find(".camara-footer")
        .children()
        .children()
        .eq(0).prepend(`
         <a href="#" class="btn btn-primary foto" style="position:relative;">
        Tomar foto
        </a>`);

    let canvas = document.getElementById("canvas");

    cxt = canvas.getContext("2d");

    let video = document.getElementById("video");
    video.setAttribute("autoplay", "");
    video.setAttribute("muted", "");
    video.setAttribute("playsinline", "");
    var front = false;

    var constraints = {
        audio: false,
        video: {
            facingMode: front ? "user" : "environment",
        },
    };

    navigator.mediaDevices
        .getUserMedia(constraints)
        .then(function success(stream) {
            video.srcObject = stream;
        });


}
// Evento click para capturar una foto.
cont = 0;
let cantidadFotosPermitidas = 5;
$(document).on("click", ".foto", function (event) {
    event.preventDefault();
    if ((objetoCamara.imagenes.length >= cantidadFotosPermitidas) && $(".contenedorImgs").find('.scaleAnimate').length == 0) {
        toastr.info('No puedes tomar más de 5 fotografias');
        return;
    }

    if (cont == 1) {
        return false;
    }
    let btnCamera = $(this);

    let attrKey = btnCamera.attr("key-busqueda");
    if (typeof attrKey !== typeof undefined && attrKey !== false) {
        cxt.clearRect(0, 0, canvas.width, canvas.height);
        cxt.drawImage(video, 0, 0, 400, 338);
        let cambioImg = canvas.toDataURL("image/jpeg");
        $.each(objetoCamara.imagenes, function (index, value) {
            $.each(Object.keys(value), function (indexInArray, valueOfElement) {
                if (valueOfElement == attrKey) {
                    objetoCamara.imagenes[index][attrKey].img = cambioImg;

                    $.each(
                        $(btnCamera).parents().eq(1).children().eq(1).children(),
                        function (indexImg, imgValue) {
                            if ("id_" + $(imgValue).attr("key-busqueda") == attrKey) {
                                $(imgValue).removeAttr("src", "");
                                $(imgValue).prop("src", cambioImg);

                                btnCamera.removeClass("foto btn-primary");
                                btnCamera.removeAttr("key-busqueda");
                                btnCamera.addClass("nueva-foto btn-default");
                                btnCamera.html('Nueva foto');
                                // $(imgValue).attr('src',cambioImg);
                            }
                        }
                    );
                    cont = 1;
                }
            });
        });

        LimpiarAnimacionEnImagenesScale();
        $(".guardar").show();
    }
    else {
        let id = Math.random().toString(36).slice(2);

        $("#enviar").show();
        $(".guardar").show();

        cxt.clearRect(0, 0, canvas.width, canvas.height);
        cxt.drawImage(video, 0, 0, 400, 338);

        $("#video").css("position", "absolute");
        $("#video").css("opacity", "0");
        $("#canvas").css("position", "relative");
        $("#canvas").css("opacity", "1");

        let img = canvas.toDataURL("image/jpeg");
        cont = 1;
        if (objetoCamara.imagenes.length <= cantidadFotosPermitidas) {
            objetoCamara.idPregunta = $('#popUpCamara').attr('idPregunta');
            objetoCamara.idListaChequeoEjecutada = $('.datosLista').attr('idListaChequeoEjecutada');

            objetoCamara.imagenes.push({
                ["id_" + id]: {
                    img,
                },
            });

            let imagen = `<img src="${img}" alt="" key-busqueda="${id}" onclick="OnClickFotoTomada(this);" class="cambiar-imagen scale">`;
            $(".contenedorImgs").append(imagen);
            btnCamera.removeClass("foto btn-primary");
            btnCamera.addClass("nueva-foto btn-default");

            var timer;
            clearTimeout(timer);
            timer = setTimeout(function () { btnCamera.trigger('click'); }, 80);

            $(".contenedorImgs").show();

            ReiniciarVideoCamara();
        }
    }

    event.stopPropagation();
});

function LimpiarAnimacionEnImagenesScale() {
    $('.contenedorImgs').parent().find('.cambiar-imagen').removeClass('scaleAnimate').addClass('scale');
    // $(imagen).removeClass().addClass('slideInUp animated');
}

function OnClickFotoTomada(control) {
    let imagen = $(control);

    LimpiarAnimacionEnImagenesScale();

    $(imagen).removeClass('scale').addClass('scaleAnimate');

    let keyBusqueda = `id_${imagen.attr("key-busqueda")}`;

    $(".guardar").show();
    $.each(objetoCamara.imagenes, function (index, value) {
        $.each(Object.keys(value), function (indexInArray, valueOfElement) {
            if (valueOfElement == keyBusqueda) {
                //    let attrCambioFoto = $(imagen).parents().eq(2).children().eq(2).children().children().eq(0);
                let attrCambioFoto = $(imagen)
                    .parents()
                    .eq(1)
                    .children()
                    .eq(0)
                    .children()
                    .eq(0);

                attrCambioFoto.attr("key-busqueda", keyBusqueda);
                attrCambioFoto.removeClass("foto btn-primary");
                attrCambioFoto.addClass("nueva-foto btn-default");
                attrCambioFoto.html("Remplazar");

                let cambio = objetoCamara.imagenes[index][keyBusqueda].img;
                var myImage = new Image();
                myImage.src = cambio;
                cxt.drawImage(myImage, 0, 0, 400, 338);
                // cxt.drawImage(cambio, 0, 0);

                $("#video").css("position", "absolute");
                $("#video").css("opacity", "0");
                $("#canvas").css("position", "relative");
                $("#canvas").css("opacity", "1");
                $(".guardar").show();
            }
        });
    });
}

$(document).on("click", ".nueva-foto", function (e) {
    e.preventDefault();

    let btnCamera = $(this);
    cont = 0;
    $("#video").css("position", "absolute");
    $("#video").css("opacity", "1");
    $("#canvas").css("position", "relative");
    $("#canvas").css("opacity", "0");
    btnCamera.removeClass("nueva-foto btn-default");
    btnCamera.addClass("foto btn-primary");
    btnCamera.html("Tomar foto");

    e.stopPropagation();
});

$(".guardar").click(function (event) {
    let idPregunta = $('#popUpCamara').attr('idPregunta');
    let idListaChequeoEjec = $('.datosLista').attr('idListaChequeoEjecutada');
    let dataImagen = objetoCamara;
    event.preventDefault();
    if (objetoCamara.imagenes.length == 0) {
        toastr.warning('Debes tomar almenos una foto');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/guardarFotosTomadas',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idPregunta: idPregunta,
            idListaChequeoEjec: idListaChequeoEjec,
            dataImagen,
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:
                    toastr.success(data.mensaje);
                    $(controlTemporalCamara).addClass('respuestaOpcSeleccion');
                    CerrarPopUpCamaraLimpiar();
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar()
        }
    });


});

function CerrarPopUpCamaraLimpiar() {
    objetoCamara.imagenes = [];
    $(".contenedorImgs").empty();
    cont = 0;
    ReiniciarVideoCamara();
    $('#popUpCamara').modal('hide');
}

function ReiniciarVideoCamara() {
    $("#video").css("position", "absolute");
    $("#video").css("opacity", "1");
    $("#canvas").css("position", "relative");
    $("#canvas").css("opacity", "0");
}

function OnClickFinalizarListaChequeo() {
    //DEBE VALIDARSE QUE TODAS LAS PREGUNTAS FUERON CONTESTADAS
    let cantidadTotal = $('.contenedorCategorias').find('.contenedorRespuestas').length;
    let cantidadContestadas = $('.contenedorCategorias').find('.respuestaSeleccion').length;

    let velocidad = 500;    

    if (cantidadContestadas == 0) {
        $("html, body").animate({
            scrollTop: 0
        }, velocidad);
    }

    let scroll = false;
    $.each($('.contenedorCategorias').find('.contenedorRespuestas'), function (indexInArray, item) {
       
        let respondio = $(item).find('.respuesta').hasClass('respuestaSeleccion');
        if (!respondio && !scroll) {
            //console.log($(item));
            // if($(item).tiporespuesta == 2) //SI / NO
            // {
            if (!$(item).parents().eq(6).find('.collapse').hasClass('show')) // collapse DE LA CATEGORIA
                $(item).parents().eq(6).find('.categoriaContenedor').trigger('click');

            let posicion = $(item).parent().parent().parent().position();
            let posicionDos = $(item).parents().eq(6).position();

            $("html, body").animate({
                scrollTop: (posicion.top + parseFloat(posicionDos.top))
            }, velocidad);
            scroll = true;
            // }
        }
    });

    if (scroll) {
        toastr.info('Debes completar todas las preguntas');
        return;
    }

    respuestasRango = [];

    $.each($('.contenedorCategorias').find('.respuestaSeleccion'), function (i, item) {

        const respuesta = $(item);

        const valormin = respuesta.attr('valormin');
        const valormax = respuesta.attr('valormax');
        const respuestaAbierta = respuesta.attr('respuestaabierta');
        const tiporespuesta = respuesta.attr('tiporespuesta');        
        const idPregunta = respuesta.parents().eq(4).attr('idPregunta');
        
        if (tiporespuesta == 15) {
            if (Number(respuestaAbierta) < valormin || Number(respuestaAbierta) > valormax) {
                respuestasRango.push(idPregunta);
            }
        }
    });

    console.log('respuestas fuera de rango:', respuestasRango);


    let evaluadoId = $('.evaluandoA').val();
    let latitud = localizacion.latitud;
    let longitud = localizacion.longitud;
    let direccion = localizacion.direccion;
    let estado = 2; //TERMINADA
    let fechaRealizacion = $('#datepicker-autoclose').val();
    let idListaChequeoEjec = $('.datosLista').attr('idListaChequeoEjecutada');
    // let fechaFormateada = moment(fechaRealizacion,'YYYY-DD-MM').format('YYYY/DD/MM');
    let obsgeneral = $('.dev-obs-general').val();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/finalizarListaChequeo',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            evaluadoId: evaluadoId,
            latitud: latitud,
            direccion: direccion,
            longitud: longitud,
            estado: estado,
            fechaRealizacion: fechaRealizacion,
            idListaChequeoEjec: idListaChequeoEjec,
            obsgeneral: obsgeneral,
            respuestasRango: respuestasRango
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:
                    swal.fire({
                        title: 'Ejecutar otra lista de chequeo',
                        text: '¿Quieres realizar otra lista de chequeo?',
                        type: 'info',
                        showCancelButton: true,
                        buttonsStyling: false,
                        allowOutsideClick: false,
                        confirmButtonClass: 'btn btn-success',
                        confirmButtonText: 'Si',
                        cancelButtonClass: 'btn btn-secondary ml-3',
                        cancelButtonText: 'No',
                    }).then(function (response) {
                        if (response.dismiss == undefined) {
                            let idListaChequeo = $('.datosLista').attr('idListaChequeo');
                            $.ajax({
                                type: "POST",
                                url: "/listachequeo/mislistas/comenzarEjecucionListaChequeo",
                                data: {
                                    _token: $('meta[name="csrf-token"]').attr("content"),
                                    idListaChequeo: idListaChequeo,
                                },
                                cache: false,
                                dataType: "json",
                                beforeSend: function () {
                                    CargandoMostrar();
                                },
                                success: function (data) {
                                    CargandoNoMostrar();
                                    switch (data.codigoRespuesta) {
                                        case 200:
                                            let url =
                                                window.location.origin +
                                                "/listachequeo/ejecucion/" +
                                                idListaChequeo +
                                                "/" +
                                                data.datos;
                                            window.location.href = url;
                                            break;

                                        case 402:
                                            Swal.fire({
                                                title: "No puedes continuar con la ejecución",
                                                text:
                                                    "Ya superaste las veces que puedes ejecutar esta lista de chequeo",
                                                icon: "danger",
                                                confirmButtonColor: "#3085d6",
                                                confirmButtonText: "Aceptar",
                                                allowOutsideClick: false,
                                            }).then((result) => { });
                                            break;

                                        case 406:
                                            if (data.datos != 1) {
                                                // DIFERENTE ADMINISTRADOR
                                                Swal.fire({
                                                    title: "No puedes continuar",
                                                    text: data.mensaje,
                                                    type: "warning",
                                                    confirmButtonClass: "btn btn-success",
                                                    confirmButtonText: "Aceptar",
                                                }).then(function () { });
                                            } else {
                                                Swal.fire({
                                                    title: "No puedes continuar",
                                                    text: data.mensaje,
                                                    type: "warning",
                                                    confirmButtonClass: "btn btn-success",
                                                    confirmButtonText: "Cambiar plan",
                                                    cancelButtonClass: "btn btn-secondary",
                                                    cancelButtonText: "Cerrar",
                                                    showCancelButton: true,
                                                }).then(function (response) {
                                                    if (response.dismiss == undefined) {
                                                        let idPlan = $.trim(
                                                            $("#popUpSuscripcion").attr("planActual")
                                                        );

                                                        switch (idPlan) {
                                                            case "1":
                                                                let control = $(
                                                                    ".contenedoresPlanes"
                                                                ).find(
                                                                    'div[idTarjeta="' + idPlan + '"]'
                                                                );
                                                                $(control)
                                                                    .find(".btn-audeed")
                                                                    .addClass("hidden");
                                                                break;

                                                            case "2":
                                                            case "3":
                                                            case "4":
                                                                for (
                                                                    let index = idPlan;
                                                                    index > 0;
                                                                    index--
                                                                ) {
                                                                    let control = $(
                                                                        ".contenedoresPlanes"
                                                                    ).find(
                                                                        'div[idTarjeta="' + index + '"]'
                                                                    );
                                                                    $(control)
                                                                        .find(".btn-audeed")
                                                                        .addClass("hidden");
                                                                }

                                                                break;

                                                            default:
                                                                break;
                                                        }

                                                        $(".iconoCerrarPopUpPlanes").removeClass(
                                                            "hidden"
                                                        );
                                                        $("#popUpSuscripcion").modal("show");
                                                    }
                                                });
                                            }
                                            break;

                                        default:
                                            break;
                                    }
                                },
                                error: function (data) {
                                    CargandoNoMostrar();
                                },
                            });
                        }
                        else {
                            let url = window.location.origin + '/listachequeo/ejecutadas';
                            window.location.href = url;
                        }
                    });
                    break;

                case 400:
                    toastr.error(data.mensajes);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar()
        }
    });
}

function OnClickGuardarRespuestaNumerica() {
    if ($('#respuestaNumericaText').val() == '') {
        toastr.warning('Debes escribir tu respuesta');
        $('#respuestaNumericaText').focus();
        return;
    }

    objetoEnviarGlobal.respuestaAbierta = $('#respuestaNumericaText').val();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/agregarRespuestaListaChequeo',
        data: objetoEnviarGlobal,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            // CargandoMostrar();
        },
        success: function (data) {
            // CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:

                    let padre = $(controlGlobal).parents().eq(2);
                    let listaRespuestas = $(padre).find('.respuesta');
                    $.each(listaRespuestas, function (indexInArray, itemRespuesta) {
                        $(itemRespuesta).removeClass('respuestaSeleccion');
                    });

                    $(controlGlobal).addClass('respuestaSeleccion');
                    $(controlGlobal).attr('respuestaAbierta', $('#respuestaNumericaText').val());
                    $('#popUpRespuestaNumerica').modal('hide');
                    toastr.success(data.mensaje);
                    break;

                case 406:
                    toastr.error(data.mensaje);

                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            // CargandoNoMostrar()
        }
    });
}

function OnClickPopUpRespuestaCerrarNumerica() {
    $('#respuestaNumericaText').val('');
    $('#popUpRespuestaNumerica').modal('hide');
}

function OnClickGuardarRespuestaTiempo() {
    if ($('#respuestaTiempoText').val() == '') {
        toastr.warning('Debes seleccionar el tiempo');
        $('#respuestaTiempoText').focus();
        return;
    }

    objetoEnviarGlobal.respuestaAbierta = $('#respuestaTiempoText').val();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/agregarRespuestaListaChequeo',
        data: objetoEnviarGlobal,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            // CargandoMostrar();
        },
        success: function (data) {
            // CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:

                    let padre = $(controlGlobal).parents().eq(2);
                    let listaRespuestas = $(padre).find('.respuesta');
                    $.each(listaRespuestas, function (indexInArray, itemRespuesta) {
                        $(itemRespuesta).removeClass('respuestaSeleccion');
                    });

                    $(controlGlobal).addClass('respuestaSeleccion');
                    $(controlGlobal).attr('respuestaAbierta', $('#respuestaTiempoText').val());
                    $('#popUpRespuestaTiempo').modal('hide');
                    toastr.success(data.mensaje);
                    
                    break;

                case 406:
                    toastr.error(data.mensaje);

                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            // CargandoNoMostrar()
        }
    });
}

function OnClickPopUpRespuestaCerrarTiempo() {
    $('#respuestaTiempoText').val('');
    $('#popUpRespuestaTiempo').modal('hide');
}

function OnClickGuardarRespuestaFecha() {
    if (objetoEnviarGlobal.tipoRespuesta == 8) {
        if ($('#respuestaSelectNumerico').val() == '') {
            toastr.warning('Debes seleccionar una opción');
            $('#respuestaSelectNumerico').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaSelectNumerico').val();

    } else if (objetoEnviarGlobal.tipoRespuesta == 9) {
        if ($('#respuestaSelectReferencias').val() == '') {
            toastr.warning('Debes seleccionar una opción');
            $('#respuestaSelectReferencias').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaSelectReferencias').val();

    }
    else if (objetoEnviarGlobal.tipoRespuesta == 10) {
        if ($('#respuestaSelectProductos').val() == '') {
            toastr.warning('Debes seleccionar una opción');
            $('#respuestaSelectProductos').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaSelectProductos').val();

    }
    else if (objetoEnviarGlobal.tipoRespuesta == 11) {
        if ($('#respuestaSelectEquipos').val() == '') {
            toastr.warning('Debes seleccionar una opción');
            $('#respuestaSelectEquipos').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaSelectEquipos').val();
    }
    else if (objetoEnviarGlobal.tipoRespuesta == 13) {
        if ($('#respuestaSelectLotes').val() == '') {
            toastr.warning('Debes seleccionar una opción');
            $('#respuestaSelectLotes').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaSelectLotes').val();
    } else if (objetoEnviarGlobal.tipoRespuesta == 14) {
        if ($('#respuestaSelectAreas').val() == '') {
            toastr.warning('Debes seleccionar una opción');
            $('#respuestaSelectAreas').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaSelectAreas').val();
    } else if (objetoEnviarGlobal.tipoRespuesta == 15) {
        if ($('#respuestaNum').val() == '') {
            toastr.warning('Debes ingresar un valor');
            $('#respuestaNum').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaNum').val();
    }
    else {
        if ($('#respuestaFechaText').val() == '') {
            toastr.warning('Debes seleccionar la fecha');
            $('#respuestaFechaText').focus();
            return;
        }

        objetoEnviarGlobal.respuestaAbierta = $('#respuestaFechaText').val();
    }

    //objetoEnviarGlobal.respuestaAbierta = $('#respuestaFechaText').val();

    $.ajax({
        type: 'POST',
        url: '/listachequeo/ejecucion/agregarRespuestaListaChequeo',
        data: objetoEnviarGlobal,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            // CargandoMostrar();
        },
        success: function (data) {
            // CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 206:

                    let padre = $(controlGlobal).parents().eq(2);
                    let listaRespuestas = $(padre).find('.respuesta');
                    $.each(listaRespuestas, function (indexInArray, itemRespuesta) {
                        $(itemRespuesta).removeClass('respuestaSeleccion');
                    });

                    $(controlGlobal).addClass('respuestaSeleccion');

                    if (objetoEnviarGlobal.tipoRespuesta == 8) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaSelectNumerico').val());
                    } else if (objetoEnviarGlobal.tipoRespuesta == 9) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaSelectReferencias').val());
                    } else if (objetoEnviarGlobal.tipoRespuesta == 10) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaSelectProductos').val());
                    } else if (objetoEnviarGlobal.tipoRespuesta == 11) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaSelectEquipos').val());
                    } else if (objetoEnviarGlobal.tipoRespuesta == 13) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaSelectLotes').val());
                        window.location.reload()
                    } else if (objetoEnviarGlobal.tipoRespuesta == 14) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaSelectAreas').val());

                        let area = areas.find(element => ($('#respuestaSelectAreas').val() == element.nombre));
                        let motivos = [];

                        if (area && area.motivos) {
                            motivos = area.motivos;
                        }

                        motivos.forEach(element => {
                            $('#respuestaSelectMotivos').append(`<option value="${element.nombre}">${element.nombre}</option>`);
                        });

                    } else if (objetoEnviarGlobal.tipoRespuesta == 15) {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaNum').val());
                    }
                    else {
                        $(controlGlobal).attr('respuestaAbierta', $('#respuestaFechaText').val());
                    }

                    $('#popUpRespuestaFecha').modal('hide');
                    toastr.success(data.mensaje);

                    break;

                case 406:
                    toastr.error(data.mensaje);

                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            // CargandoNoMostrar()
        }
    });
}

function OnClickPopUpRespuestaCerrarFecha() {
    $('#respuestaFechaText').val('');
    $('#popUpRespuestaFecha').modal('hide');
}

$('.cancelarPopUp').on('click', OnClickPopUpComentarioCerrar);
$('.guardarComentario').on('click', OnClickGuardarComentario);
$('.TerminarListaChequeo').on('click', OnClickFinalizarListaChequeo);
$('.cerrarPopUpCamara').on('click', CerrarPopUpCamaraLimpiar);
$('.guardarAdjuntos').on('click', OnClickGuardarAdjuntos);
$('.cancelarPopUpRespuesta').on('click', OnClickPopUpRespuestaCerrar);
$('.guardarRespuesta').on('click', OnClickGuardarRespuestaAbierta);
$('.guardarRespuestaNumerica').on('click', OnClickGuardarRespuestaNumerica);
$('.cancelarPopUpRespuestaNumerica').on('click', OnClickPopUpRespuestaCerrarNumerica);
$('.guardarRespuestaTiempo').on('click', OnClickGuardarRespuestaTiempo);
$('.cancelarPopUpRespuestaTiempo').on('click', OnClickPopUpRespuestaCerrarTiempo);
$('.guardarRespuestaFecha').on('click', OnClickGuardarRespuestaFecha);
$('.cancelarPopUpRespuestaFecha').on('click', OnClickPopUpRespuestaCerrarFecha);
