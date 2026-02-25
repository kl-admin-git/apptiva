let arrayChanges = [];
var totalPonderadoPreguntas = 0
let dataPlanAccionManual = []
arrayChanges['Empresa'] = [{ id: 1, valor: 'Asociada al evaluador' }, { id: 2, valor: 'Selecciona de una lista' }];
arrayChanges['Establecimiento'] = [{ id: 1, valor: 'Asociada al evaluador' }, { id: 2, valor: 'Selecciona de una lista' }];
arrayChanges['Usuario'] = [{ id: 1, valor: 'Evaluador' }, { id: 2, valor: 'Selecciona de una lista' }];
arrayChanges['Áreas'] = [{ id: 2, valor: 'Selecciona de una lista' }];
arrayChanges['Equipos'] = [{ id: 2, valor: 'Selecciona de una lista' }];

let steps =
{
    idListaChequeo: $('.datosListas').attr('idListaChequeo'),
    stepsCarga:
    {
        stepUno: { pregunta: '', ponderado: 100, categorias: [], orden: [] },
        stepDos: [],
        stepTres: {},
        stepCuatro: []
    },
    stepsEnviar:
    {
        stepUno: { pregunta: '', ponderado: 100, preguntaEnCategoria: false, categoriaId: 0, orden: [], permiteNoAplica: false },
        stepDos: { idRespuesta: 0 },
        stepTres: { personalizadas: [] },
        stepCuatro: { opcionesRespuesta: [], aplicaPlanAccion: false, idRespuesta: 0, planDeAccion: '' }
    }
};

$(document).ready(function () {
    let formulario = $('#formularioEditarListaChequeo').parsley();
    formulario.options.requiredMessage = "Este campo es requerido";
    let formularioCategoria = $('#formularioCrearCategoria').parsley();
    formularioCategoria.options.requiredMessage = "Este campo es requerido";

    var formStep = $("#fomularioSteps").show();

    formStep.steps({
        headerTag: "h3",
        bodyTag: "fieldset",
        transitionEffect: "slide",
        labels:
        {
            finish: "Terminar",
            previous: "Atrás",
            next: "Continuar",
        },
        onStepChanging: function (event, currentIndex, newIndex) {
            $('#fomularioSteps .steps li:eq(1)').removeClass().addClass('disabled');
            $('#fomularioSteps .steps li:eq(2)').removeClass().addClass('disabled');
            $('#fomularioSteps .steps li:eq(3)').removeClass().addClass('disabled');

            // Forbid next action on "Warning" step if the user is to young
            if (currentIndex < newIndex) //AVANZA
            {
                switch (currentIndex) {
                    case 0: // VALIDACION PRIMER STEP
                        if ($('#preguntaTextArea').val() == '') {
                            toastr.warning('Debes escribir tu pregunta');
                            return false;
                        }

                        if ($('.ponderadoPopUpPregunta').val() == '') {
                            toastr.warning('Debes colocar un ponderado');
                            return false;
                        }

                        steps.stepsEnviar.stepUno.pregunta = $('#preguntaTextArea').val();
                        steps.stepsEnviar.stepUno.ponderado = $('.ponderadoPopUpPregunta').val();
                        if ($('.categoríaPopUpStep').val() == 0) {
                            steps.stepsEnviar.stepUno.preguntaEnCategoria = false;
                            steps.stepsEnviar.stepUno.orden = [];
                        }
                        else {
                            steps.stepsEnviar.stepUno.preguntaEnCategoria = true;
                            steps.stepsEnviar.stepUno.categoriaId = $('.categoríaPopUpStep').val();
                        }


                        return true;
                        break;

                    case 1: // VALIDACION SEGUNDO STEP
                        let usados = $('.tipoRespuestaField').find('.seleccionadoRespuestaStep');
                        if (usados.length == 0) {
                            toastr.warning('Debes seleccionar un tipo de respuesta');
                            return false;
                        }

                        let idRespuesta = $(usados[0]).attr('idtiporespuesta');

                        steps.stepsEnviar.stepDos.idRespuesta = idRespuesta;
                        let editando = $('#crearPreguntaPopUp').attr('editando');
                        if (editando == 1 || editando == 2) // EDITANDO
                        {
                            let idPregunta = $('#crearPreguntaPopUp').attr('idPregunta');

                            $.ajax({
                                type: 'POST',
                                url: '/listachequeo/mislistas/validarTiposDeRespuestaModoEdicion',
                                data:
                                {
                                    _token: $('meta[name="csrf-token"]').attr('content'),
                                    idRespuesta: idRespuesta,
                                    idPregunta: idPregunta
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
                                            $('.textoEncabezadoConfiguracionRespuesta').attr('idRespuestaPonPredeterminada',data.datos.tipoRespuesta);
                                            let configuracionRta = ComponentePersonalizacion(data.datos.respuestas,data.datos.tipoRespuesta);
                                            $('.contenedorPersonalizacionTipoRespuesta').html(configuracionRta);
                                            $('.textoEncabezadoConfiguracionRespuesta').html(data.datos.descripcion);
                                            return true;
                                            break;

                                        case 400:

                                            break;

                                        default:
                                            break;
                                    }

                                },
                                error: function (data) {
                                    CargandoNoMostrar();
                                }
                            });
                        } else {
                            $.ajax({
                                type: 'POST',
                                url: '/listachequeo/mislistas/validarTiposDeRespuesta',
                                data:
                                {
                                    _token: $('meta[name="csrf-token"]').attr('content'),
                                    idRespuesta: idRespuesta
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
                                            $('.textoEncabezadoConfiguracionRespuesta').attr('idRespuestaPonPredeterminada',data.datos.tipoRespuesta);
                                            let configuracionRta = ComponentePersonalizacion(data.datos.respuestas,data.datos.tipoRespuesta);
                                            $('.contenedorPersonalizacionTipoRespuesta').html(configuracionRta);
                                            $('.textoEncabezadoConfiguracionRespuesta').html(data.datos.descripcion);
                                            return true;
                                            break;

                                        case 400:

                                            break;

                                        default:
                                            break;
                                    }

                                },
                                error: function (data) {
                                    CargandoNoMostrar();
                                }
                            });
                        }




                        break;

                    case 2: // VALIDACION TERCERO STEP
                        let idRespuestaPonPredeterminada = $('.textoEncabezadoConfiguracionRespuesta').attr('idRespuestaPonPredeterminada');
                    
                        let hijosContenedor = $('.contenedorPersonalizacionTipoRespuesta').find('.form-group');

                        // CARGA CONTESTAR
                        $('.contestarPopUpStep').html('');

                        $('.contestarPopUpStep')
                            .append($("<option></option>")
                                .attr("value", 0)
                                .text('En caso de no contestar'));

                        if(idRespuestaPonPredeterminada == 4) // SI ES MULTIPLE
                        {
                            let hijosContenedorMultiple = $('.cuerpoConfiguracionRespuesas').find(".row");
                            steps.stepsEnviar.stepTres.personalizadas = [];
                            let vaciosTexto = false;
                            let vaciosPonderado = false;
                            let repetidosBool = false;
                            let valorPorcentajesTotal = 0;

                            $.each(hijosContenedorMultiple, function (indexInArray, itemHijo) 
                            {
                                let valorPersonalizado = $(itemHijo).find('.valorPersonalizado').val();
                                let valorPersonalizadoPonderado = $(itemHijo).find('.valorPersonalizadoPonderado').val();

                                if(valorPersonalizado == "")
                                    vaciosTexto = true;

                                if(valorPersonalizadoPonderado == "")
                                    vaciosPonderado = true;
                                else
                                    valorPorcentajesTotal = parseFloat(valorPorcentajesTotal) + parseFloat(valorPersonalizadoPonderado);
                                
                                let repetidos = steps.stepsEnviar.stepTres.personalizadas.filter(ob => ob.valorPersonalizado.toUpperCase() == valorPersonalizado.toUpperCase());

                                if(repetidos.length != 0)
                                    repetidosBool = true;

                                steps.stepsEnviar.stepTres.personalizadas.push(
                                {
                                    idPredeterminado: idRespuestaPonPredeterminada,
                                    valorPersonalizado: valorPersonalizado,
                                    valorPredeterminado: "",
                                    valorPersonalizadoPonderado: valorPersonalizadoPonderado
                                });

                                $('.contestarPopUpStep')
                                    .append($("<option></option>")
                                        .attr("value", valorPersonalizado)
                                        .text(valorPersonalizado));
                            });
                            
                            if(vaciosTexto)
                            {
                                toastr.warning("No debe haber campos vacíos en los campos de valores personalizados");
                                return false;
                            }

                            if(vaciosPonderado)
                            {
                                toastr.warning("No debe haber campos vacíos en los campos de los ponderados (%)");
                                return false;
                            }

                            // if(valorPorcentajesTotal > 100)
                            // {
                            //     toastr.warning("La suma de tus ponderados supera el 100%");
                            //     return false;
                            // }

                            if(repetidosBool)
                            {
                                toastr.warning("Tienes 2 respuestas que tienen el mismo nombre");
                                return false;
                            }
                        } else if(idRespuestaPonPredeterminada == 15){
                            let hijosContenedorMultiple = $('.cuerpoConfiguracionRespuesas').find(".row");

                            let vaciosTexto = false;

                            $.each(hijosContenedorMultiple, function (indexInArray, itemHijo) {
                                let valorPersonalizadoMin = $(itemHijo).find('.valorPersonalizadoMin').val();
                                let valorPersonalizadoMax = $(itemHijo).find('.valorPersonalizadoMax').val();

                                if (valorPersonalizadoMin == "" || valorPersonalizadoMax == "") {
                                    vaciosTexto = true;
                                }else{                                    
                                    steps.stepsEnviar.stepTres.personalizadas.push(
                                        {
                                            idPredeterminado: 15,
                                            valorPersonalizado: "NÚMERICO",
                                            valorPredeterminado: "NÚMERICO",
                                            valorMin: valorPersonalizadoMin,
                                            valorMax: valorPersonalizadoMax
                                        });
                                }
                            });


                            if (vaciosTexto) {
                                toastr.warning("No debe haber campos vacíos en los campos minimo y maximo");
                                return false;
                            }
                        }
                        else
                        {
                            steps.stepsEnviar.stepTres.personalizadas = [];

                            $.each(hijosContenedor, function (indexInArray, itemHijo) {
                                let idRespuestaPredeterminada = $(itemHijo).find('label').attr('idrespuespredeterminda');
                                let valorRespuestaPredeterminada = $.trim($(itemHijo).find('label').html());
                                let valorPersonalizado = $(itemHijo).find('.valorPersonalizado').val();

                                steps.stepsEnviar.stepTres.personalizadas.push(
                                    {
                                        idPredeterminado: idRespuestaPredeterminada,
                                        valorPersonalizado: valorPersonalizado,
                                        valorPredeterminado: valorRespuestaPredeterminada
                                    });

                                $('.contestarPopUpStep')
                                    .append($("<option></option>")
                                        .attr("value", idRespuestaPredeterminada)
                                        .text((valorPersonalizado == '' ? valorRespuestaPredeterminada : valorPersonalizado)));
                            });
                        }

                        steps.stepsEnviar.stepUno.permiteNoAplica = $('#checkBoxPermitirNA').is(':checked');

                        $(".contestarPopUpStep").select2({ dropdownParent: $('#crearPreguntaPopUp') });
                        if (edicionIdPlanAccion != 0) 
                        {
                            if(idRespuestaPonPredeterminada == 4) // SI ES MULTIPLE
                                $(".contestarPopUpStep").val(edicionValorPlanAccion).change();
                            else
                                $(".contestarPopUpStep").val(edicionIdPlanAccion).change();
                        }

                        return true;
                        break;

                    default:
                        break;
                }

                return true;
            }

            // Allways allow previous action even if the current form is not valid!
            if (currentIndex > newIndex) // RETROCEDE
            {
                return true;
            }


            // Forbid next action on "Warning" step if the user is to young
            // if (newIndex === 3 && Number($("#age-2").val()) < 18)
            // {
            //     return false;
            // }
            // Needed in some cases if the user went back (clean up)
            // if (currentIndex < newIndex)
            // {
            //     To remove error styles
            //     form.find(".body:eq(" + newIndex + ") label.error").remove();
            //     form.find(".body:eq(" + newIndex + ") .error").removeClass("error");
            // }
            // form.validate().settings.ignore = ":disabled,:hidden";
            // return form.valid();
        },
        onStepChanged: function (event, currentIndex, priorIndex) {

            // // Used to skip the "Warning" step if the user is old enough.
            // if (currentIndex === 2 && Number($("#age-2").val()) >= 18)
            // {
            //     form.steps("next");
            // }
            // // Used to skip the "Warning" step if the user is old enough and wants to the previous step.
            // if (currentIndex === 2 && priorIndex === 3)
            // {
            //     form.steps("previous");
            // }
        },
        onFinishing: function (event, currentIndex) {
            let acciones = $('.contenedorOpcRespuestaStepFinal').find('.cuerpoRespuestaStep');

            steps.stepsEnviar.stepCuatro.opcionesRespuesta = [];

            let validarPlanAccion = false;
            $.each(acciones, function (indexInArray, elemento) {
                if ($(elemento).hasClass('seleccionadoRespuestaStep')) {
                    let idopcionrespuesta = $(elemento).attr('idopcionrespuesta');

                    steps.stepsEnviar.stepCuatro.opcionesRespuesta.push(
                        {
                            idopcionrespuesta: idopcionrespuesta
                        });

                    if (idopcionrespuesta == 4)
                        validarPlanAccion = true;
                }

            });

            if (validarPlanAccion) {
                //Filtro si es plan de accion manual o automatico
                let planAccionManual = $('.planAccionManual').hasClass('show')
                let planAccionAutomatico = $('.planAccionAutomatico').hasClass('show')
                steps.stepsEnviar.stepCuatro.aplicaPlanAccion = true;

                if (planAccionAutomatico) {
                    if ($('.contestarPopUpStep').val() == 0 || $('#planDeAccionArea').val() == '') {
                        toastr.warning('Debes seleccionar y llenar el plan de acción');
                        return false;
                    }
                    steps.stepsEnviar.stepCuatro.tipoPlanAccion = 'automatico'
                    if ($('.contestarPopUpStep').val() != 0) {

                        steps.stepsEnviar.stepCuatro.idRespuesta = $('.contestarPopUpStep').val();
                        steps.stepsEnviar.stepCuatro.planDeAccion = $('#planDeAccionArea').val();
                    } else {
                        steps.stepsEnviar.stepCuatro.aplicaPlanAccion = false;
                        steps.stepsEnviar.stepCuatro.idRespuesta = 0;
                        steps.stepsEnviar.stepCuatro.planDeAccion = '';
                    }

                } else if (planAccionManual) {
                    // console.log(dataPlanAccionManual)
                    let checks = $('.tbody-detalle-pm').find(".checkHabilitar");
                    let cantidadChecks = 0;
                    $.each(checks, function (indexInArray, check) 
                    { 
                      let isChecked = $(check).is(':checked');
                      if(isChecked)
                        cantidadChecks = 1;
                    });

                    if(cantidadChecks == 0)
                    {
                        toastr.warning('Debes escoger almenos una opción')
                        return;
                    }
                    let checksPlanAccionManual = $('.checkBoxPlanAccion')
                    steps.stepsEnviar.stepCuatro.tipoPlanAccion = 'manual'
                    steps.stepsEnviar.stepCuatro.planAccionData = dataPlanAccionManual
                }

            } else {
                steps.stepsEnviar.stepCuatro.aplicaPlanAccion = false;
                steps.stepsEnviar.stepCuatro.idRespuesta = 0;
                steps.stepsEnviar.stepCuatro.planDeAccion = '';
            }            

            let modo = $('#crearPreguntaPopUp').attr('editando');
            if (modo != 1) {
                $.ajax({
                    type: 'POST',
                    url: '/listachequeo/mislistas/crearPregunta',
                    data:
                    {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        objetoEnviar: JSON.stringify(steps)
                    },
                    cache: false,
                    dataType: 'json',
                    beforeSend: function () {
                        CargandoMostrar();
                    },
                    success: function (data) {
                        CargandoNoMostrar();
                        switch (data.codigoRespuesta) {
                            case 200:
                                toastr.success(data.mensaje);
                                TraerCategoriasYPreguntas();
                                OnClickCancelarPregunta();
                                break;

                            case 400:

                                break;

                            default:
                                break;
                        }

                    },
                    error: function (data) {
                        CargandoNoMostrar();
                    }
                });
            } else {
                let idPregunta = $('#crearPreguntaPopUp').attr('idPregunta');
                $.ajax({
                    type: 'POST',
                    url: '/listachequeo/mislistas/actualizarPregunta',
                    data:
                    {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        objetoEnviar: JSON.stringify(steps),
                        idPregunta: idPregunta
                    },
                    cache: false,
                    dataType: 'json',
                    beforeSend: function () {
                        CargandoMostrar();
                    },
                    success: function (data) {
                        CargandoNoMostrar();
                        switch (data.codigoRespuesta) {
                            case 201:
                                toastr.success(data.mensaje);
                                TraerCategoriasYPreguntas();
                                OnClickCancelarPregunta();
                                break;

                            case 402:
                            case 406:
                                toastr.error(data.mensaje);
                                break;

                            default:
                                break;
                        }

                    },
                    error: function (data) {
                        CargandoNoMostrar();
                    }
                });
            }



        },
        onFinished: function (event, currentIndex) {
            console.log('finished');
            alert("Submitted!");
        }
    });

    // Select2
    $(".select2").select2({});
    $(".selectPopUp").select2({ dropdownParent: $('#crearCategoriaPopUp') });
    $(".selectStepPopUp").select2({ dropdownParent: $('#crearPreguntaPopUp') });
    //  $(".selectLinkPopUp").select2({dropdownParent: $('#linkPopUp')});

    IniciarVista();
});

function IniciarVista() {

    $('.empresaSelect').val($('.datosListas').attr('idEntidad')).change();
    if ($('.datosListas').attr('idEvaluado') != 0)
        $('.empresaEvaluadaSelect').val($('.datosListas').attr('idEvaluado')).change();

    TraerCategoriasYPreguntas();
    // let pregunta = ComponentePregunta();
    // pregunta += ComponentePregunta();
    // pregunta += ComponentePregunta();
    // pregunta += ComponentePregunta();
    // let categorias = ComponenteCategoriaYCuerpo(pregunta);
    // let contenidoTotal = ComponenteCategoriaConCuerpoPreguntas(categorias);
    // contenidoTotal += ComponenteCategoriaConCuerpoPreguntas(pregunta);
    // $('.ContenedorCategoriaYPreguntas').html(contenidoTotal);
}

function TraerCategoriasYPreguntas() {
    let lista_chequeo_id = $('.datosListas').attr('idListaChequeo');
    totalPonderadoPreguntas = 0 //Reseteo la variable
    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/traerCategoriasYPreguntas',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            lista_chequeo_id: lista_chequeo_id
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
                    let categorias = '';
                    $.each(data.datos.arrayCategoriasPreguntas, function (indexInArray, itemCategoria) {
                        let preguntas = '';
                        let totalPonderadoCategoria = 0
                        $.each(itemCategoria.PREGUNTAS, function (indexInArray, pregunta) {
                            preguntas += ComponentePregunta(pregunta.id, pregunta.nombre, pregunta.ponderado, pregunta.NOMBRE_CATEGORIA, pregunta.ICONO_TIPO_RESPUESTA, pregunta.OpcionesGenerales);
                            totalPonderadoCategoria += parseFloat(pregunta.ponderado) //Sumo el ponderado de cada pregunta de acuerdo a cada categoria
                            totalPonderadoPreguntas += parseFloat(pregunta.ponderado)
                        });
                   
                        categorias += ComponenteCategoriaYCuerpo(preguntas,itemCategoria.CATEGORIA_ID,itemCategoria.PONDERADO,itemCategoria.NOMBRE_CATEGORIA,itemCategoria.ORDEN_LISTA,itemCategoria.IDETIQUETA,itemCategoria.ETIQUETANOMBRE, totalPonderadoCategoria,itemCategoria.SUMA_PONDERADO );
                    });


                    // CARGA ORDEN
                    $('.ordenCategorias').html('');

                    $('.ordenCategorias')
                        .append($("<option></option>")
                            .attr("value", 'final')
                            .text('Al final de las categorías'));

                    $.each(data.datos.arrayListadoNuevoPopUp, function (key, orden) {
                        $('.ordenCategorias')
                            .append($("<option></option>")
                                .attr("value", orden.orden_lista)
                                .text(('Después de la categoría "' + orden.nombre + '"')));
                    });

                    $('.ordenCategorias')
                        .append($("<option></option>")
                            .attr("value", 'principio')
                            .text('Al inicio de las categorías'));

                    $(".ordenCategorias").select2({ dropdownParent: $('#crearCategoriaPopUp') });

                    $('.ContenedorCategoriaYPreguntas').html(categorias);

                    $('[data-toggle="tooltip"]').tooltip();
                    ValidarSiTieneDatos($('.ContenedorCategoriaYPreguntas'));
                    break;

                case 400:

                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });
}

function OnClickEditar() {
    let padre = $(this).parent();
    let nombreLista = $.trim($(padre).find('.nombreLista').html());
    $('.nombreMiListaPopUp').val(nombreLista);
    $('#editarNombreLista').modal('show');
}

function OnClickCancelarEdicionListaChequeo() {
    $('#editarNombreLista').modal('hide');
}

function OnClickActualizarNombreLista() {
    let idListaChequeoId = $('.datosListas').attr('idListaChequeo');
    var form = $('#formularioEditarListaChequeo');
    form.parsley().validate();

    if (form.parsley().isValid()) {
        $.ajax({
            type: 'POST',
            url: '/listachequeo/mislistas/actualizarListaChequeo',
            data:
            {
                _token: $('meta[name="csrf-token"]').attr('content'),
                idListaChequeoId: idListaChequeoId,
                nombreLista: $('.nombreMiListaPopUp').val()
            },
            cache: false,
            dataType: 'json',
            beforeSend: function () {
                CargandoMostrar();
            },
            success: function (data) {
                CargandoNoMostrar();
                switch (data.codigoRespuesta) {
                    case 201:
                        $('.nombreLista').html($('.nombreMiListaPopUp').val());
                        OnClickCancelarEdicionListaChequeo();
                        break;

                    default:
                        break;
                }

            },
            error: function (data) {
                CargandoNoMostrar();
            }
        });
    }


}

function OnChangeEvaluado() {
    let nombreEvaluado = $.trim($(this).find(':selected')[0].text);
    $('.tituloDinamicoSelect').html(nombreEvaluado.toUpperCase() + " EVALUADO(A)");

    // CARGA EVALUADA
    $('.empresaEvaluadaSelect').html('');

    $.each(arrayChanges[nombreEvaluado], function (key, value) {
        $('.empresaEvaluadaSelect')
            .append($("<option></option>")
                .attr("value", value.id)
                .text(value.valor));
    });

    $(".empresaEvaluadaSelect").select2({});
}

function OnClickCollapseCategorias(control) {
    let padre = $(control).parent();
    let controlContenido = $(padre).find('.contenidoCategoria');
    $(controlContenido).collapse('toggle');
}

function OnClickAgregarCategoria() {
    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/traerEtiquetas',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
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
                    // CARGA ETIQUETAS
                    $('.etiquetaSelector').html('');
                    $('.etiquetaSelector')
                        .append($("<option></option>")
                            .attr("value", 0)
                            .text('Seleccione una etiqueta'));

                    $.each(data.datos, function (key, value) {
                        $('.etiquetaSelector')
                            .append($("<option></option>")
                                .attr("value", value.id)
                                .text(value.nombre));
                    });


                    $(".etiquetaSelector").select2({});

                    $('.guardarPopUp').attr('accion', 0);
                    $('.guardarPopUp').html('Guardar');
                    $('.tituloCategoriaPopUp').html('AGREGAR CATEGORÍA');
                    // $('.ponderadoPopUp').val(CalcularPonderadoCategorias());
                    $('.ponderadoPopUp').val(0);
                    $('#crearCategoriaPopUp').modal('show');
                    break;

                case 402:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });

}

function OnClickAgregarPregunta(control) {
    $('.contenidoPlanDeAccion').collapse('hide');
    let accion = $(this).attr('accion'); // 0 PARA CREAR PREGUNTA POR FUERA Y 1 PARA CREAR PREGUNTA EN CATEGORIA
    let idCategoria = 0;
    if (accion == undefined) {

        if (accion == undefined) {
            idCategoria = $(control).attr('idCategoria');
            accion = $(control).attr('accion');
            if (accion == 1) {
                accion = $(control).attr('accion');
                idCategoria = $(control).attr('idCategoria');
                $('#crearPreguntaPopUp').attr('editando', 0);
                $('#crearPreguntaPopUp').find('.tituloPreguntaPopUp').html("AGREGAR PREGUNTA");

            }
        }

    } else {
        $('#crearPreguntaPopUp').attr('editando', 0);
        $('#crearPreguntaPopUp').find('.tituloPreguntaPopUp').html("AGREGAR PREGUNTA");
    }

    let objetoEnviar =
    {
        _token: $('meta[name="csrf-token"]').attr('content'),
        idListaChequeo: $('.datosListas').attr('idListaChequeo')
    };

    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/consultarInformacionStep',
        data: objetoEnviar,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 202:
                    let controlPadre = $(control).parents().eq(4).find('.contenidoCategoria');
                    steps.stepsCarga.stepUno.pregunta = '';
                    steps.stepsCarga.stepUno.categorias = data.datos.categorias;
                    steps.stepsCarga.stepUno.ponderado = parseFloat(CalcularPonderadoPreguntas($(controlPadre)));
                    steps.stepsCarga.stepUno.orden = data.datos.orden.arrayCategoriasPreguntas;

                    steps.stepsCarga.stepDos = [];
                    steps.stepsCarga.stepDos.push(data.datos.tipoRespuesta);

                    steps.stepsCarga.stepCuatro = [];
                    steps.stepsCarga.stepCuatro = data.datos.opcionesRespuesta;

                    CargarPopUpStepUno();
                    CargarPopUpStepDos();
                    CargarPopUpStepCuatro();
                    $('#crearPreguntaPopUp').attr('accionPregunta', accion);
                    if (accion == 1)
                        $('.categoríaPopUpStep').val(idCategoria).change();


                    $('#crearPreguntaPopUp').modal('show');
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });

}

function CargarPopUpStepUno() {
    $('#preguntaTextArea').val(steps.stepsCarga.stepUno.pregunta);
    $('.ponderadoPopUpPregunta').val(steps.stepsCarga.stepUno.ponderado);

    // CARGA CATEGORIAS
    $('.categoríaPopUpStep').html('');

    // $('.categoríaPopUpStep')
    // .append($("<option></option>")
    // .attr("value",0)
    // .text('N/A'));

    $.each(steps.stepsCarga.stepUno.categorias, function (key, value) {
        $('.categoríaPopUpStep')
            .append($("<option></option>")
                .attr("value", value.id)
                .text(value.nombre));
    });

    $(".categoríaPopUpStep").select2({ dropdownParent: $('#crearPreguntaPopUp') });

    // CARGA ORDEN
    $('.ordenPopUpStep').html('');

    $('.ordenPopUpStep')
        .append($("<option></option>")
            .attr("value", '0')
            .text('Seleccione una pregunta'));

    $.each(steps.stepsCarga.stepUno.orden, function (key, categoria) {
        // if(categoria.PREGUNTAS.length != 0)
        // {
        $('.ordenPopUpStep')
            .append($("<optgroup>")
                .attr("label", categoria.NOMBRE_CATEGORIA));

        $.each(categoria.PREGUNTAS, function (indexInArray, pregunta) {
            $('.ordenPopUpStep')
                .append($("<option></option>")
                    .attr("value", pregunta.id)
                    .text(pregunta.nombre));
        });

        $('.ordenPopUpStep')
            .append($("</optgroup>"));
        // }

    });

    $(".ordenPopUpStep").select2({ dropdownParent: $('#crearPreguntaPopUp') });
}

function CargarPopUpStepDos() {
    let stringCategorias = '';
    $.each(steps.stepsCarga.stepDos, function (indexInArray, items) {
        Object.entries(items).forEach((key, value) => {
            let name_section = key[0];
            let values_section = key[1];
            stringCategorias += ComponenteIconos(name_section, values_section);
        });
    });

    $('.tipoRespuestaField').html(`<div class="d-flex flex-wrap col-lg-12">${stringCategorias}</div>`);
}

function CargarPopUpStepCuatro() {
    let stringOpcRespuesta = '';
    $.each(steps.stepsCarga.stepCuatro, function (indexInArray, items) {
        stringOpcRespuesta += ComponenteOpcionesDeRespuesta(items.id, items.icono, items.nombre);

    });

    $('.contenedorOpcionesRta').html(stringOpcRespuesta);
}

function ComponenteIconos(nombreCategoriaTipoRespuesta, tipoRespuestas) {
    let string = '';

    let stringRespuestas = ''
    let tipoRespuestaVariable = '';
    $.each(tipoRespuestas, function (indexInArray, tipoRespuesta) {
        if (tipoRespuesta.ICONO == '') {
            tipoRespuestaVariable = `<i class="iconosRespuestasSteps" style="font-size:20px!important;">Sí No</i>`;
        }
        else {
            tipoRespuestaVariable = `
            <i class="${tipoRespuesta.ICONO} iconosRespuestasSteps"></i>
            <p class="card-title font-10 mt-0 ">${tipoRespuesta.NOMBRE_TIPO_RESPUESTA}</p>`;
        }

        stringRespuestas += `<div class="col-lg-3 mt-2  ">
                                <div class=" cuerpoRespuestaStep" style="align-items: center;" idTipoRespuesta="${tipoRespuesta.ID_TIPO_RESPUESTA}" onclick="OnClickTipoRespuesta(this);">
                                    ${tipoRespuestaVariable}
                                </div>
                            </div> `;
    });

    string += `<div class="row col-lg-6">
                    <div class="col-lg-12">
                        <label class="col-lg-12 text-center">${nombreCategoriaTipoRespuesta}</label>
                        <div class="form-group row d-flex justify-content-center">
                            ${stringRespuestas}
                        </div>
                    </div>
                </div>`;

    return string;
}

function ComponenteOpcionesDeRespuesta(idOpcionRespuesta, icono, nombre) {
    let string = '';
    string = `<div class="col-lg-3">
                <div class=" cuerpoRespuestaStep" idOpcionRespuesta="${idOpcionRespuesta}" OnClick="OnClickTipoRespuestaStepCuatro(this);">
                    <i class="${icono} iconosRespuestasSteps"></i>
                    <p class="card-title font-10 mt-0 ">${nombre}</p>
                </div>
              </div>`;

    return string;
}

function ComponentePersonalizacion(respuestas, tipoRespuesta = 0) {
    let string = '';

    if (tipoRespuesta == 4) //TIPO RESPUESTA MULTIPLE (M)
    {
        let stringRespuestas = "";
        let contador = 0;
        $.each(respuestas, function (indexInArray, rta) {
            if (contador == 0) {
                stringRespuestas += ComponenteRespuestaMultiple(rta, 1, true); //IRREMOVIBLE ITEM
                contador = contador + 1;
            }
            else
                stringRespuestas += ComponenteRespuestaMultiple(rta, 2, true); //NORMAL ITEM
        });

        string += `<div class="form-group">
                        <div class="cuerpoConfiguracionRespuesas">
                            ${stringRespuestas}
                        </div>

                        <div class="col-lg-12 text-center">
                            <span class="btn btn-primary agregarMasPreguntas mt-2" onclick="OnClickAgregarRespuesta(this);">Agregar</span>
                        </div>
                    </div>`;
    }
    if (tipoRespuesta == 15) //TIPO RANGO
    {
        let stringRespuestas = "";
        let contador = 0;
        
        stringRespuestas =`<div class="row mt-1">
                                <label class="col-lg-5 col-form-label" style="text-align:end;">Valor Minimo</label>
                                <div class="col-lg-3">
                                    <input type="number" class="form-control valorPersonalizadoMax" value=""/>
                                </div>
                                <label class="col-lg-5 col-form-label" style="text-align:end;">Valor Maximo</label>
                                <div class="col-lg-3">
                                    <input type="number" class="form-control valorPersonalizadoMin"/>
                                </div>
                            </div>`;

        string += `<div class="form-group">
                        <div class="cuerpoConfiguracionRespuesas">
                            ${stringRespuestas}
                        </div>                        
                    </div>`;
    }
    else {
        $.each(respuestas, function (indexInArray, rta) {
            string += ValidarTipoDeString(rta);
        });
    }


    return string;

}

const ValidarTipoDeString = (rta) => 
{
    let string = '';
    switch (rta.tipo_respuesta_id) 
    {
        case 3: //RESPUESTA LIBRE
            string = `<div class="form-group row">
                            <label class="col-lg-5 col-form-label" idRespuesPredeterminda="${rta.id}" style="text-align:end;">${rta.valor_original.toUpperCase()}</label>
                            <div class="col-lg-3">
                                <input type="text" class="form-control valorPersonalizado" value="" disabled placeholder=""/>
                            </div>
                        </div>`;
        break;

        case 5: //CAMPO NÚMERICO
            string = `<div class="form-group row">
                            <label class="col-lg-5 col-form-label" idRespuesPredeterminda="${rta.id}" style="text-align:end;">${rta.valor_original.toUpperCase()}</label>
                            <div class="col-lg-3">
                                <input type="text" class="form-control valorPersonalizado" value="" disabled placeholder=""/>
                            </div>
                        </div>`;
        break;

        default: // SI Y NO (DEFAULT)
            string = `<div class="form-group row">
                            <label class="col-lg-5 col-form-label" idRespuesPredeterminda="${rta.id}" style="text-align:end;">${rta.valor_original.toUpperCase()}</label>
                            <div class="col-lg-3">
                                <input type="text" class="form-control valorPersonalizado" value="${((rta.VALOR_PERSONALIZADO != undefined && rta.VALOR_PERSONALIZADO != '') ? rta.VALOR_PERSONALIZADO : rta.valor_original)}" required placeholder="personalizado"/>
                            </div>
                        </div>`;
        break;
    }

    return string;
}

const ComponenteRespuestaMultiple = (rta, opDefault=1,editando=false) =>
{
    let string = "";
    if(editando)
    {
        if(opDefault == 1) //VALOR IRREMOVIBLE
        {
            string = `<div class="row">
                            <label class="col-lg-5 col-form-label" idRespuesPredeterminda="${rta.id}" style="text-align:end;">${rta.valor_original.toUpperCase()}</label>
                            <div class="col-lg-3">
                                <input type="text" class="form-control valorPersonalizado" value="${((rta.VALOR_PERSONALIZADO != undefined && rta.VALOR_PERSONALIZADO != '') ? rta.VALOR_PERSONALIZADO : rta.valor_original)}" required placeholder="personalizado"/>
                            </div>
                            <div class="col-lg-2">
                                <input type="text" class="form-control valorPersonalizado valorPersonalizadoPonderado" oninput="OnInputNumberLimit(this);" value="${ parseFloat(((rta.PONDERADO == undefined || rta.PONDERADO == "") ? rta.ponderado : rta.PONDERADO)).toFixed(0) }" placeholder="%"/>
                            </div>
                        </div>`;
        }
        else
        {
            string = `<div class="row mt-1">
                                <label class="col-lg-5 col-form-label" style="text-align:end;"><i class="mdi mdi-close-circle" style="font-size: 23px;color: red;" onclick="OnClickEliminarRespuestaConfiguracion(this);"></i></label>
                                <div class="col-lg-3">
                                    <input type="text" class="form-control valorPersonalizado" value="${((rta.VALOR_PERSONALIZADO != undefined && rta.VALOR_PERSONALIZADO != '') ? rta.VALOR_PERSONALIZADO : rta.valor_original)}" placeholder="personalizado"/>
                                </div>
                                <div class="col-lg-2">
                                    <input type="text" class="form-control valorPersonalizadoPonderado" oninput="OnInputNumberLimit(this);" value="${ parseFloat(rta.PONDERADO).toFixed(0) }" placeholder="%"/>
                                </div>
                        </div>`;
        }
    }else
    {
        if(opDefault == 1) //VALOR IRREMOVIBLE
        {
            string = `<div class="row">
                            <label class="col-lg-5 col-form-label" style="text-align:end;">Opción 1</label>
                            <div class="col-lg-3">
                                <input type="text" class="form-control valorPersonalizado" value="Opción 1" placeholder="personalizado"/>
                            </div>
                            <div class="col-lg-2">
                                <input type="text" class="form-control valorPersonalizado valorPersonalizadoPonderado" oninput="OnInputNumberLimit(this);" value="100" placeholder="%"/>
                            </div>
                        </div>`;
        }
        else
        {
            string = `<div class="row mt-1">
                                <label class="col-lg-5 col-form-label" style="text-align:end;"><i class="mdi mdi-close-circle" style="font-size: 23px;color: red;" onclick="OnClickEliminarRespuestaConfiguracion(this);"></i></label>
                                <div class="col-lg-3">
                                    <input type="text" class="form-control valorPersonalizado" value="" placeholder="personalizado"/>
                                </div>
                                <div class="col-lg-2">
                                    <input type="text" class="form-control valorPersonalizadoPonderado" oninput="OnInputNumberLimit(this);" value="100" placeholder="%"/>
                                </div>
                        </div>`;
        }
    }

    return string;
}

function OnInputNumberLimit(control) 
{ 
    control.value = control.value.replace(/[^0-9.]/g,'');

    if (control.value < 0) control.value = 0;
    if (control.value > 100) control.value = 100;
}

function OnClickAgregarRespuesta(control) 
{
    if($('.cuerpoConfiguracionRespuesas').children().length >= 5)
    {
        toastr.warning("No puedes agregar más opciones de respuesta");
        return;
    }

    $('.cuerpoConfiguracionRespuesas').append(ComponenteRespuestaMultiple("",2,false));
}

function OnClickEliminarRespuestaConfiguracion(control) 
{
    $(control).parent().parent().remove();
}

function LimpiarPopUpCategorias() {
    $('.nombreCategoriaPopUp').val('');
    $('.ponderadoPopUp').val('');
    $('.ordenCategorias').val('final').change();
}

function OnClickCancelarAgregarCategoria() {
    LimpiarPopUpCategorias();
    $('#crearCategoriaPopUp').modal('hide');
}

function CalcularPonderadoCategorias() {
    let total = 100;
    let ponderado = 100;
    let cantidadCategoriasCargadas = $('.ContenedorCategoriaYPreguntas').children().length;
    if (cantidadCategoriasCargadas == 0)
        ponderado = 100;
    else {
        ponderado = (total / (cantidadCategoriasCargadas + 1));
    }

    ponderado = ponderado.toFixed(0);

    return ponderado;
}

function CalcularPonderadoPreguntas(control)
{
    // let total = 100;
    // let ponderado = 100;
    // let cantidadCategoriasCargadas = $(control).children().length;
    // if(cantidadCategoriasCargadas == 0)
    //     ponderado = 100;
    // else
    // {
    //     ponderado = (total/(cantidadCategoriasCargadas + 1));
    // }

    let preguntas = $('.ContenedorCategoriaYPreguntas').find('.contenedorPreguntas').length;
    let ponderado = 100 / (preguntas + 1);

    ponderado = ponderado.toFixed(2);

    return ponderado;
}

function OnClickGuardarPopUp() {
    var form = $('#formularioCrearCategoria');
    form.parsley().validate();

    if (form.parsley().isValid()) {
        let objetoEnviar =
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            nombre: $('.nombreCategoriaPopUp').val(),
            ponderado: $('.ponderadoPopUp').val(),
            orden_categoria: 0,
            orden_lista: $('.ordenCategorias ').val(),
            lista_chequeo_id: $('.datosListas').attr('idListaChequeo'),
            idEtiqueta: ($('.etiquetaSelector').val() == 0 ? null : $('.etiquetaSelector').val())
        };

        if ($('.guardarPopUp').attr('accion') == 0) {
            $.ajax({
                type: 'POST',
                url: '/listachequeo/mislistas/crearCategoria',
                data: objetoEnviar,
                cache: false,
                dataType: 'json',
                beforeSend: function () {
                    CargandoMostrar();
                },
                success: function (data) {
                    CargandoNoMostrar();
                    switch (data.codigoRespuesta) {
                        case 200:
                            toastr.success(data.mensaje);
                            TraerCategoriasYPreguntas();
                            OnClickCancelarAgregarCategoria();
                            break;

                        case 402:
                            toastr.error(data.mensaje);
                            break;

                        default:
                            break;
                    }

                },
                error: function (data) {
                    CargandoNoMostrar();
                }
            });
        }
        else if ($('.guardarPopUp').attr('accion') == 1) {
            let idCategoria = $(this).attr('idCategoria');
            objetoEnviar['idCategoria'] = idCategoria;

            $.ajax({
                type: 'POST',
                url: '/listachequeo/mislistas/editaCategoria',
                data: objetoEnviar,
                cache: false,
                dataType: 'json',
                beforeSend: function () {
                    CargandoMostrar();
                },
                success: function (data) {
                    CargandoNoMostrar();
                    switch (data.codigoRespuesta) {
                        case 201:
                            toastr.success(data.mensaje);
                            // let control = $('.contenedorTodaLaCategoria').find('div[idcategoria="'+idCategoria+'"]');
                            // $(control).find('.categoriaControl').html(objetoEnviar.nombre);
                            // $(control).find('.ponderadoControl').html(objetoEnviar.ponderado+"%");
                            TraerCategoriasYPreguntas();
                            OnClickCancelarAgregarCategoria();
                            break;

                        case 406:
                            toastr.error(data.mensaje);
                            break;

                        default:
                            break;
                    }

                },
                error: function (data) {
                    CargandoNoMostrar();
                }
            });
        }


    }
}

function OnEliminarCategoriaBadge(control) {
    let idCategoria = $(control).attr('idCategoria');

    Swal.fire({
        title: '¿Estás seguro?',
        text: "No se podrán revertir los cambios de la eliminación",
        type: 'warning',
        showCancelButton: true,
        confirmButtonClass: 'btn btn-success',
        cancelButtonClass: 'btn btn-danger m-l-10',
        confirmButtonText: 'Si, eliminarlo!',
        cancelButtonText: 'Cancelar'
    }).then(function (resultado) {
        if (resultado.dismiss == 'cancel')
            return;

        $.ajax({
            type: 'POST',
            url: '/listachequeo/mislistas/eliminarCategoria',
            data:
            {
                _token: $('meta[name="csrf-token"]').attr('content'),
                idCategoria: idCategoria
            },
            cache: false,
            dataType: 'json',
            beforeSend: function () {
                CargandoMostrar();
            },
            success: function (data) {
                CargandoNoMostrar();
                switch (data.codigoRespuesta) {
                    case 203:
                        toastr.success(data.mensaje);
                        TraerCategoriasYPreguntas();
                        break;

                    case 406:
                        toastr.error(data.mensaje);
                        break;

                    default:
                        break;
                }

            },
            error: function (data) {
                CargandoNoMostrar();
            }
        });

    });


}

function OnEditarCategoriaBadge(control) {
    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/traerEtiquetas',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
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
                    // CARGA ETIQUETAS
                    $('.etiquetaSelector').html('');
                    $('.etiquetaSelector')
                        .append($("<option></option>")
                            .attr("value", 0)
                            .text('Seleccione una etiqueta'));

                    $.each(data.datos, function (key, value) {
                        $('.etiquetaSelector')
                            .append($("<option></option>")
                                .attr("value", value.id)
                                .text(value.nombre));
                    });


                    $(".etiquetaSelector").select2({});

                    let idCategoria = $(control).attr('idCategoria');
                    let idEtiqueta = $(control).attr('idEtiqueta');
                    let nombre = $(control).attr('nombre');
                    let ponderado = $(control).attr('ponderado');
                    let idOrdenLista = $(control).attr('idOrdenLista');

                    $('.contenidoPlanDeAccion').collapse('hide');
                    $('.guardarPopUp').attr('accion', 1);
                    $('.guardarPopUp').attr('idCategoria', idCategoria);
                    $('.nombreCategoriaPopUp').val(nombre);
                    $('.ponderadoPopUp').val(ponderado);
                    $('.ordenCategorias').val(idOrdenLista).change();
                    $('.guardarPopUp').html('Actualizar');
                    $('.tituloCategoriaPopUp').html('ACTUALIZAR CATEGORÍA');

                    if (idEtiqueta != "null")
                        $(".etiquetaSelector").val(idEtiqueta).change();
                    else
                        $(".etiquetaSelector").val(0).change();

                    $('#crearCategoriaPopUp').modal('show');
                    break;

                case 402:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });

}

function LimpiarStep() {
    $('#crearPreguntaPopUp').modal('hide');
    $('#fomularioSteps').find('input[type="text"]').val('');
    $('#fomularioSteps').find('textarea').val('');
    $("#fomularioSteps").find("select").val($('select option:first').val()).change();
    $('.contestarPopUpStep').val(0).change();
    $('#fomularioSteps').find('.seleccionadoRespuestaStep').removeClass('seleccionadoRespuestaStep');
    $('#fomularioSteps .steps li:eq(1)').removeClass().addClass('disabled');
    $('#fomularioSteps .steps li:eq(2)').removeClass().addClass('disabled');
    $('#fomularioSteps .steps li:eq(3)').removeClass().addClass('disabled');
    $('#fomularioSteps .steps li:first a').trigger('click');
    if (!$('#checkBoxPermitirNA').is(':checked'))
        $('#checkBoxPermitirNA').trigger('click');
    $('.contenidoPlanDeAccion').collapse('hide');
    edicionIdPlanAccion = 0;
    edicionValorPlanAccion = "";
}


function OnClickTipoRespuesta(control) {
    let usados = $('.tipoRespuestaField').find('.seleccionadoRespuestaStep');

    $.each(usados, function (indexInArray, item) {
        $(item).removeClass('seleccionadoRespuestaStep');
    });

    $(control).addClass('seleccionadoRespuestaStep');
}

function limpiarPlanAccionManualTabla(){
    let checkbox = $('.checkBoxPlanAccion')
    $.each(checkbox, function(index, el){
        $(el).prop('checked', false)
    })
}

$('#crearPreguntaPopUp').on('hidden.bs.modal', function (e) {
    limpiarPlanAccionManualTabla()
    $('.contenidoPlanDeAccion').collapse('hide')
    $('.planAccionAutomatico').collapse('hide')
    $('.planAccionManual').collapse('hide')
})

function pintarOpcionesRespuestas(control, tipo_pa = null) {
    console.log(tipo_pa)
    if ($(control).hasClass('seleccionadoRespuestaStep')) {

        $(control).removeClass('seleccionadoRespuestaStep');
        let idOpcRespuesta = $(control).attr('idopcionrespuesta');

        if (idOpcRespuesta == 4) // ID DE PLAN DE ACCIÓN
        {
            $('.contenidoPlanDeAccion').collapse('hide');
            if(tipo_pa == 1){
                $('.planAccionAutomatico').collapse('hide')
            }else if(tipo_pa == 2){
                $('.planAccionManual').collapse('hide')
            }
        }
    }
    else {
        let idOpcRespuesta = $(control).attr('idopcionrespuesta');
        if (idOpcRespuesta == 4) // ID DE PLAN DE ACCIÓN
        {
            $('.contenidoPlanDeAccion').collapse('show');
            if(tipo_pa == 1){
                $('.planAccionAutomatico').collapse('show')
            }else if(tipo_pa == 2){
                $('.planAccionManual').collapse('show')
            }
        }

        $(control).addClass('seleccionadoRespuestaStep');
    }
}

function OnClickTipoRespuestaStepCuatro(control) {

    if ($(control).hasClass('seleccionadoRespuestaStep')) {
        $(control).removeClass('seleccionadoRespuestaStep');

        let idOpcRespuesta = $(control).attr('idopcionrespuesta');
        if (idOpcRespuesta == 4) // ID DE PLAN DE ACCIÓN
        {
            $('.contenidoPlanDeAccion').collapse('hide');
            //Valido los tipos de accion y su estado 
            if ($('.planAccionAutomatico').hasClass('show')) {
                $('.planAccionManual').collapse('hide')
            } else if ($('.planAccionManual').hasClass('show')) {
                $('.planAccionAutomatico').collapse('hide')
            }

        }
    }
    else {
        let idOpcRespuesta = $(control).attr('idopcionrespuesta');
        if (idOpcRespuesta == 4) // ID DE PLAN DE ACCIÓN
        {
            Swal.fire({
                title: '<strong>Tipo de plan de accíon</strong>',
                icon: 'info',
                html:
                    'Por favor seleccione un tipo de plan de accíon.',
                showCloseButton: true,
                showCancelButton: true, //Activar para ve ropcion de PLAN ACCION MANUAL
                focusConfirm: false,
                confirmButtonText: 'Automatico',
                confirmButtonAriaLabel: 'Thumbs up, great!',
                confirmButtonColor: '#67a8e4',
                cancelButtonText: 'Manual',
                denyButtonColor: '#4ac18e',
                cancelButtonAriaLabel: 'Thumbs down'
            }).then((result) => {
                if (result.value) {
                    //MUestro el contenido para plan de accion AUTOMATICO
                    if(steps.stepsEnviar.stepDos.idRespuesta != 3)
                    {
                        $('.planAccionManual').collapse('hide')
                        $('.contenidoPlanDeAccion').collapse('show')
                        $('.planAccionAutomatico').collapse('show')
                        console.log('Automatico')    
                    }
                    else
                    {
                        $(control).removeClass('seleccionadoRespuestaStep');
                        toastr.info('Con el tipo de respuesta seleccionada, esta opción no aplica');
                        return;
                    }
                    
                } else if (result.dismiss == "cancel") {
                    //MUestro el contenido para plan de accion MANUAL
                    $('.planAccionAutomatico').collapse('hide')
                    $('.contenidoPlanDeAccion').collapse('show')
                    $('.planAccionManual').collapse('show')
                }
            })

        }

        $(control).addClass('seleccionadoRespuestaStep');
    }

}

function OnClickCancelarPregunta() {
    LimpiarStep();
}

function OnEliminarPreguntaBadge(control) {
    let idPregunta = $(control).attr('idPregunta');

    Swal.fire({
        title: '¿Estás seguro?',
        text: "No se podrán revertir los cambios de la eliminación",
        type: 'warning',
        showCancelButton: true,
        confirmButtonClass: 'btn btn-success',
        cancelButtonClass: 'btn btn-danger m-l-10',
        confirmButtonText: 'Si, eliminarlo!',
        cancelButtonText: 'Cancelar'
    }).then(function (resultado) {
        if (resultado.dismiss == 'cancel')
            return;

        $.ajax({
            type: 'POST',
            url: '/listachequeo/mislistas/eliminarPregunta',
            data:
            {
                _token: $('meta[name="csrf-token"]').attr('content'),
                idPregunta: idPregunta
            },
            cache: false,
            dataType: 'json',
            beforeSend: function () {
                CargandoMostrar();
            },
            success: function (data) {
                CargandoNoMostrar();
                switch (data.codigoRespuesta) {
                    case 203:
                        toastr.success(data.mensaje);
                        TraerCategoriasYPreguntas();
                        break;

                    case 406:
                        toastr.error(data.mensaje);
                        break;

                    default:
                        break;
                }

            },
            error: function (data) {
                CargandoNoMostrar();
            }
        });

    });


}

let edicionIdPlanAccion = 0;
let edicionValorPlanAccion = "";
function OnClickEditaPregunta(control) {
    let idPregunta = $(control).attr('idPregunta');
    let accionBoton = $(control).attr('accionEdicion');

    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/editarPregunta',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idPregunta: idPregunta,
            idListaChequeo: $('.datosListas').attr('idListaChequeo')
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
                    // CARGA NORMAL
                    steps.stepsCarga.stepUno.pregunta = '';
                    steps.stepsCarga.stepUno.categorias = data.datos.categorias;
                    steps.stepsCarga.stepUno.ponderado = 100;
                    steps.stepsCarga.stepUno.orden = data.datos.orden.arrayCategoriasPreguntas;

                    steps.stepsCarga.stepDos = [];
                    steps.stepsCarga.stepDos.push(data.datos.tipoRespuesta);

                    steps.stepsCarga.stepCuatro = [];
                    steps.stepsCarga.stepCuatro = data.datos.opcionesRespuesta;

                    CargarPopUpStepUno();
                    CargarPopUpStepDos();
                    CargarPopUpStepCuatro();

                    $('#preguntaTextArea').val(data.datos.preguntaDetalle.nombre);
                    $('.ponderadoPopUpPregunta ').val(parseFloat(data.datos.preguntaDetalle.ponderado).toFixed(2));
                    $('.categoríaPopUpStep').val(data.datos.preguntaDetalle.categoria_id).change();

                    if (data.datos.preguntaDetalle.permitir_noaplica) {
                        if (!$('#checkBoxPermitirNA').is(':checked'))
                            $('#checkBoxPermitirNA').trigger('click');

                    }
                    else {
                        if ($('#checkBoxPermitirNA').is(':checked'))
                            $('#checkBoxPermitirNA').trigger('click');
                    }

                    let control = $('.tipoRespuestaField').find('div[idtiporespuesta="' + data.datos.preguntaDetalle.tipo_respuesta_id + '"]');
                    $(control).addClass('seleccionadoRespuestaStep');

                    $.each(data.datos.preguntaDetalle.OpcionesGenerales, function (indexInArray, itemGeneral) {
                        let controlLocal = $('.contenedorOpcionesRta').find('div[idopcionrespuesta="' + itemGeneral.id + '"]');
                        let tipo_pa = itemGeneral.TIPO_PLAN_ACCION //tipo de plan de accion (1) Automatico (2) Manual
                        pintarOpcionesRespuestas(controlLocal, tipo_pa)
                        if (itemGeneral.id == 4 && tipo_pa == 1) // PLAN DE ACCIÓN 
                        {
                            $('#planDeAccionArea').val(itemGeneral.PLAN_ACCION);
                            edicionIdPlanAccion = itemGeneral.RESPUESTA_ID;
                            edicionValorPlanAccion = itemGeneral.RESPUESTA_VALOR;
                            
                        }else if(itemGeneral.id == 4 && tipo_pa == 2){
                            //PLAN ACCION MANUAL (PINTAR)
                            limpiarPlanAccionManualTabla()
                            let checkbox = $('.checkBoxPlanAccion')
                            $.each(data.datos.preguntaDetalle.OpcionesPlanAccionManual, function(index, item){
                                $.each(checkbox, function(i, el){
                                    if(parseInt(item.OPCIONES) == parseInt($(el).val())){
                                        //Valido el check requerido
                                        if($(el).hasClass('requerido')){
                                            $(el).prop('disabled', false)
                                            if(item.REQUERIDO == 1){
                                                // $(el).prop('checked', true)
                                                $(el).trigger('click')
                                                //$(el).prop('disabled', false)
                                            }
                                                
                                        }else{
                                            // $(el).prop('checked', true)
                                            $(el).trigger('click')
                                        }
                                        
                                    }
                                })
                            })
                        }
                    });


                    $('#crearPreguntaPopUp').attr('idPregunta', idPregunta);
                    if (accionBoton == 1) // EDICIÓN
                    {
                        $('#crearPreguntaPopUp').attr('editando', '1');
                        $('#crearPreguntaPopUp').find('.tituloPreguntaPopUp').html("ACTUALIZACIÓN DE PREGUNTA");
                    }
                    else if (accionBoton == 2) // COPIADO
                    {
                        $('#crearPreguntaPopUp').attr('editando', '2');
                        $('#crearPreguntaPopUp').find('.tituloPreguntaPopUp').html("COPIA DE PREGUNTA");
                    }

                    $('#crearPreguntaPopUp').modal('show');

                    // TraerCategoriasYPreguntas();
                    break;

                case 404:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });

}

function ComponenteCategoriaConCuerpoPreguntas(categorias) {
    let string = '';

    string = `<div class="contenedorTodaLaCategoria">
                ${categorias}
              </div>`;

    return string;
}

function pintarPieBar(ponderado, nombreCategoria,porcentajeCategoria,SumaPonderadosPreguntas){
    // ponderado = ponderado;
    let classCss = 0;
    let calculo = ((parseFloat(ponderado) / parseFloat(porcentajeCategoria)) * 100).toFixed(0);
    let supera = (parseFloat(SumaPonderadosPreguntas) - parseFloat(porcentajeCategoria));
    calculo = parseFloat(calculo);
    if(calculo < 5 && calculo != 0){
        classCss = '5'
    }else if(calculo >= 10 && calculo <= 20){
        classCss = '10'
    }else if(calculo >= 20 && calculo <= 30){
        classCss = '20'
    }else if(calculo >= 30 && calculo <= 40){
        classCss = '30'
    }else if(calculo >= 40 && calculo <= 50){
        classCss = '40'
    }else if(calculo >= 50 && calculo <= 60){
        classCss = '50'
    }else if(calculo >= 70 && calculo <= 80){
        classCss = '70'
    }else if(calculo >= 80 && calculo <= 90){
        classCss = '80'
    }else if(calculo >= 90 && calculo <= 98){
        classCss = '90'
    }else if(calculo >= 99 && calculo <= 100){
        classCss = '100'
    }else if(calculo > 100){
        // Swal.fire(
        //     'Cuidado!',
        //     'Tiene categorias que superan el 100% de sus ponderados. Por favor validar.',
        //     'warning'
        // );
    }

    if(calculo > 100 && tiposPonderado == 0)
    {
        // Swal.fire(
        //     'Cuidado!',
        //     `La sumatoría de los ponderados de las preguntas(${SumaPonderadosPreguntas}%) superó en ${supera}% el ponderado de la categoría (${porcentajeCategoria}%)`,
        //     'warning'
        // );
        classCss = '100 supero';
    }

    // if(tiposPonderado == 1)
    //     calculo = '100';
 
    return classCss;
}

function ComponenteCategoriaYCuerpo(preguntas,idCategoria,porcentajeCategoria,nombreCategoria,ordenLista,idEtiqueta,nombreEtiqueta, totalPonderadoCategoria,SumaPonderadosPreguntas)
{
    let cssClass = pintarPieBar(totalPonderadoCategoria, nombreCategoria, porcentajeCategoria,SumaPonderadosPreguntas)
    let string = '';
    let spanSticker = '';


    if (idEtiqueta != null)
        spanSticker = `<span class="mdi mdi-sticker" style="font-size: 25px;margin-left: 25px;color:white;" data-toggle="tooltip" data-placement="top" title="${nombreEtiqueta}"></span>`;

    let pieBar = `<div class="set-size charts-container">
                    <div class="pie-wrapper progress-${cssClass}" data-toggle="tooltip" data-placement="top" title="Categoria:${parseFloat(porcentajeCategoria).toFixed(2)}%  Preguntas:${parseFloat(totalPonderadoCategoria).toFixed(2)}%">
                        <span class="label">${parseFloat(porcentajeCategoria)}<span class="smaller">%</span></span>
                        <div class="pie">
                        <div class="left-side half-circle"></div>
                        <div class="right-side half-circle"></div>
                        </div>
                    </div>
                    ${spanSticker}
                </div>`

    string = `
            <div class="contenedorTodaLaCategoria" idCategoria="${idCategoria}" idEtiqueta="${idEtiqueta}">
                    <div class="card-header contenedorTextoCollapse" onclick="OnClickCollapseCategorias(this);">
                            <h6 class="m-0">
                                <div class="contenedorOpciones">
                                    ${pieBar}
                                    <label class="categoriaControl">${nombreCategoria}</label>
                                    <div class="contenedorBotonesAcciones">
                                        <span class="badge badge-success badge-custom-botones" onclick="event.stopPropagation(); OnClickAgregarPregunta(this);" idCategoria="${idCategoria}" idCategoria="${idCategoria}" accion="1" accionEdicion="0">Agregar pregunta</span>
                                        <span class="badge badge-warning badge-custom-botones" onclick="event.stopPropagation(); OnEditarCategoriaBadge(this);" idCategoria="${idCategoria}" nombre="${nombreCategoria}" idOrdenLista="${ordenLista}" idEtiqueta="${idEtiqueta}" ponderado="${porcentajeCategoria}">Editar categoría</span>
                                        <span class="badge badge-danger badge-custom-botones" onclick="event.stopPropagation(); OnEliminarCategoriaBadge(this);" idCategoria="${idCategoria}" >Eliminar categoría</span>
                                    </div>
                                </div>
                            </h6>
                    </div>

                    <div class="collapse show contenidoCategoria" >
                        ${preguntas}
                    </div>
            </div>
        `;

    return string;

}

function ComponentePregunta(idPregunta, pregunta, ponderado, nombreTipoRespuesta, iconoTipoRespuesta, OpcionesGenerales) {
    let stringPregunta = '';
    let stringOpcion = '';
    let stringTipoRespuesta = '';
    if (iconoTipoRespuesta == '')
        stringTipoRespuesta = `<span>Si No</span>`;
    else
        stringTipoRespuesta = `<span><i class="${iconoTipoRespuesta} iconosOpc"></i></span>`;

    $.each(OpcionesGenerales, function (indexInArray, opcionGeneral) {
        stringOpcion += `<div class="contenedorOpc">
                            <span class="row  justify-content-center">${opcionGeneral.nombre}</span>
                            <span><i class="${opcionGeneral.icono} iconosOpc"></i></span>
                         </div>`;
    });

    stringPregunta = `<div class="col-lg-12 m-t-20 contenedorPreguntas" idPregunta="${idPregunta}">
                            <div class="col-lg-1 contenedorPorcentajePregunta">
                                <label>${parseFloat(ponderado).toFixed(2)}%</label>
                            </div>
                            <div class="col-lg-9" style="padding:0px;">
                                <div class="contenedorPregunta">
                                    <label for="">${pregunta}</label>
                                </div>

                                <div class="contenedorOpcionesRespuesta">
                                        <div class="col-lg-4 text-center contenedoreTipoRespuestaLabels">
                                            <span style="font-weight: bold" class="row  justify-content-center">Tipo de respuesta</span>
                                            <span class="row  justify-content-center">${nombreTipoRespuesta}</span>
                                            ${stringTipoRespuesta}
                                        </div>

                                        <div class="col-lg-8 contenedorOpcionesDeRespuesta">
                                            <span style="font-weight: bold" class="row  justify-content-center">Opciones de respuesta</span>
                                            <div class="contenedorBotonesIconosOpcRespuesta">
                                                ${stringOpcion}  
                                            </div>

                                        </div>
                                </div>

                            </div>

                            <div class="col-lg-2 contenedorTresPreguntas" style="padding: 0px;">
                                <div class=" contenedorBotonesAcciones">
                                    <span class="badge badge-warning badge-custom-botones" onclick="OnClickEditaPregunta(this);" accionEdicion="1" idPregunta="${idPregunta}">Editar pregunta</span>
                                    <span class="badge badge-danger badge-custom-botones" onclick="event.stopPropagation(); OnEliminarPreguntaBadge(this);" idPregunta="${idPregunta}">Eliminar pregunta</span>
                                    <span class="badge badge-primary badge-custom-botones" onclick="OnClickEditaPregunta(this);" accionEdicion="2" idPregunta="${idPregunta}">Copiar pregunta</span>

                                </div>
                            </div>
                        </div>`;

    return stringPregunta;
}

function OnClickTerminarListaDeChequeo() {
    let fecha = $('.fecha').val();
    let entidad_evaluada_opcion = $('.empresaEvaluadaSelect ').val();
    let asociado = $('.empresaSelect ').val();
    let cantCategorias = $('.ContenedorCategoriaYPreguntas').children().length
    if (cantCategorias == 0) {
        toastr.error('Debes agregar almenos una categoría');
        return;
    }

    let encontroInconsistencias = 0;
    let inconsistenciasSinPreguntas = 0;
    let control = '';
    let controlSinPreguntas = '';
    // let posicionDos = $(item).parents().eq(6).position();
    $.each($('.ContenedorCategoriaYPreguntas').children(), function (indexCategoria, itemCategoria) 
    { 
         if(encontroInconsistencias == 0)
         {
            if($(itemCategoria).find('.supero').length != 0)
            {
               control = $(itemCategoria).find('.categoriaControl');
               encontroInconsistencias = 1;
            }
         }

         if(inconsistenciasSinPreguntas == 0)
         {
            if($(itemCategoria).find('.contenedorPreguntas').length == 0)
            {
                controlSinPreguntas = $(itemCategoria).find('.categoriaControl');
                inconsistenciasSinPreguntas = 1;
            }
         }
         
    });
    
    if(encontroInconsistencias != 0)
    {
            Swal.fire(
                'Error',
                `Debes revisar la categoría: ${$(control).html()} al parecer las preguntas están superando el valor de la categoría`,
                'error'
            );
            return;
    }

    if(inconsistenciasSinPreguntas != 0)
    {
            Swal.fire(
                'Error',
                `Debes revisar la categoría: ${$(controlSinPreguntas).html()} no tienes preguntas en esta categoría`,
                'error'
            );
            return;
    }
    
    // if((totalPonderadoPreguntas/cantCategorias).toFixed(1) != 100){
    //     Swal.fire(
    //         'Error',
    //         'No se puede finalizar la lista de chequeo debido a que el ponderado total de las categorias es menor o mayor al 100%',
    //         'error'
    //     );
    //     return 0
    // }
 

    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/guardarEncabezadoListaChequeo',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            fecha: fecha,
            asociado: asociado,
            idListaChequeo: $('.datosListas').attr('idListaChequeo'),
            entidad_evaluada_opcion: entidad_evaluada_opcion
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
                    $('.linkSelect').val(data.datos.frecuencia_ejecucion).change();
                    $('.cantidadFrecuencia').val((data.datos.cant_ejecucion == undefined ? '' : data.datos.cant_ejecucion));
                    $('#linkPopUp').modal('show');
                    break;

                case 406:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });

}

function OnChangeLinkTiempo() {
    if ($(this).val() != 0) {
        $('.cantidadFrecuencia').removeAttr('disabled');
    } else {
        $('.cantidadFrecuencia').attr('disabled', 'disabled');
        $('.cantidadFrecuencia').val('');
    }
}

function Copiar() {
    /* Get the text field */
    var copyText = document.getElementById("link");

    /* Select the text field */
    copyText.select();
    copyText.setSelectionRange(0, 99999); /*For mobile devices*/

    /* Copy the text inside the text field */
    document.execCommand("copy");

    /* Alert the copied text */
    toastr.success('Tu link ha sido copiado')
}

function OnClickBotonCopiar() {
    Copiar();
}

function ConfimarBoton() {
    let frecuencia = $('.linkSelect').val();
    let cantidad = $('.cantidadFrecuencia').val();
    let favorito = $('.startRange').attr('idfavorito');
    if (cantidad == '' || cantidad == undefined)
        cantidad = 0;

    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/actualizarConfiguracion',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            frecuencia: frecuencia,
            cantidad: cantidad,
            idListaChequeo: $('.datosListas').attr('idListaChequeo'),
            favorito: favorito
        },
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 201:
                    let url = window.location.origin + '/listachequeo/mislistas';
                    window.location.href = url;
                    break;

                case 403:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });

}

function OnClickFavoritoCrearLista(control) {
    if ($(control).hasClass('mdi-star')) {
        $(control).removeClass('mdi-star').addClass('mdi-star-outline');
        $(control).attr('idFavorito', 0);
    }
    else {
        $(control).removeClass('mdi-star-outline').addClass('mdi-star');
        $(control).attr('idFavorito', 1);
    }

}

// FUNCIONES ETIQUETAS

// EVENTO PARA EVITAR QUE EL USUARIO SE SALGA DE LA NUEVA AUDITORIA SIN HABER DADO CLIC EN FINALIZAR


function OnClickCancelarCreacionEtiqueta() {
    LimpiarCajasEtiqueta();
    $('#creacionEtiqueta').modal('hide');
}

function OnClickAgregarEtiqueta() {
    if ($('#nombreEtiqueta').val() == '') {
        toastr.warning('Debes colocar el nombre de la etiqueta');
        $('#nombreEtiqueta').focus();
        return;
    }

    let objetoEnviar =
    {
        _token: $('meta[name="csrf-token"]').attr('content'),
        nombreEtiqueta: $('#nombreEtiqueta').val(),
        descripcion: $('#descripcion').val()
    }

    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/crearEtiqueta',
        data: objetoEnviar,
        cache: false,
        dataType: 'json',
        beforeSend: function () {
            CargandoMostrar();
        },
        success: function (data) {
            CargandoNoMostrar();
            switch (data.codigoRespuesta) {
                case 200:
                    toastr.success(data.mensaje);
                    LimpiarCajasEtiqueta();
                    let stringTabla = '';

                    // // CARGA ETIQUETA
                    $('.etiquetaSelector').html('');
                    $('.etiquetaSelector')
                        .append($("<option></option>")
                            .attr("value", 0)
                            .text('Seleccione una etiqueta'));

                    $.each(data.datos.etiquetas, function (indexInArray, item) {

                        stringTabla += `<tr idEtiqueta="${item.id}">
                                            <td>${item.nombre}</td>
                                            <td>${(item.descripcion == undefined ? '' : item.descripcion)}</td>
                                            <td><button type="button" class="btn btn-danger waves-effect m-l-5" idEtiqueta="${item.id}" onclick="OnClickEliminarEtiqueta(this);" >Eliminar</button></td>
                                        </tr>`;

                        $('.etiquetaSelector')
                            .append($("<option></option>")
                                .attr("value", item.id)
                                .text(item.nombre));
                    });

                    $(".etiquetaSelector ").select2({});
                    $('.conenedorTablaEtiquetas tbody').html(stringTabla);
                    break;

                case 402:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });


}

function OnClickTraerEtiquetas() {
    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/traerEtiquetas',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
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
                    let stringTabla = '';
                    $.each(data.datos, function (indexInArray, item) {

                        stringTabla += `<tr idEtiqueta="${item.id}">
                                            <td>${item.nombre}</td>
                                            <td>${(item.descripcion == undefined ? '' : item.descripcion)}</td>
                                            <td><button type="button" class="btn btn-danger waves-effect m-l-5" idEtiqueta="${item.id}" onclick="OnClickEliminarEtiqueta(this);" >Eliminar</button></td>
                                        </tr>`;
                    });

                    $('.conenedorTablaEtiquetas tbody').html(stringTabla);
                    $('#creacionEtiqueta').modal('show');
                    break;

                case 402:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });
}

function OnClickEliminarEtiqueta(control) {
    let idEtiqueta = $(control).attr('idEtiqueta');
    $.ajax({
        type: 'POST',
        url: '/listachequeo/mislistas/eliminarEtiqueta',
        data:
        {
            _token: $('meta[name="csrf-token"]').attr('content'),
            idEtiqueta: idEtiqueta
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
                    let stringTabla = '';

                    // CARGA ETIQUETA
                    $('.etiquetaSelector').html('');
                    $('.etiquetaSelector')
                        .append($("<option></option>")
                            .attr("value", 0)
                            .text('Seleccione una etiqueta'));
                    $.each(data.datos, function (indexInArray, item) {

                        stringTabla += `<tr idEtiqueta="${item.id}">
                                            <td>${item.nombre}</td>
                                            <td>${(item.descripcion == undefined ? '' : item.descripcion)}</td>
                                            <td><button type="button" class="btn btn-danger waves-effect m-l-5" idEtiqueta="${item.id}" onclick="OnClickEliminarEtiqueta(this);" >Eliminar</button></td>
                                        </tr>`;


                        $('.etiquetaSelector')
                            .append($("<option></option>")
                                .attr("value", item.id)
                                .text(item.nombre));
                    });

                    $(".etiquetaSelector").select2({});
                    $('.conenedorTablaEtiquetas tbody').html(stringTabla);
                    break;

                case 406:
                    toastr.error(data.mensaje);
                    break;

                default:
                    break;
            }

        },
        error: function (data) {
            CargandoNoMostrar();
        }
    });
}

function LimpiarCajasEtiqueta() {
    $('#nombreEtiqueta').val('');
    $('#descripcion').val('');
}

//FUNCIONES AHENAO
//Cambio de estado de los checkbox del plan de accion manual
$(document).on('change', '.checkBoxPlanAccion', function () {
    let checkbox = $('.checkBoxPlanAccion')
    let valor = parseInt($(this).val())

    if ($(this).hasClass('requerido')){
        //Valido si ya existe el valor entonces procedo a modificar el Objecto y activo el requerido
        if(this.checked){
            let exist = existValor(valor, dataPlanAccionManual)
            if(exist != -1){
                dataPlanAccionManual[exist].requerido = 1
            }
            //console.log(dataPlanAccionManual)
        }else{
            let exist = existValor(valor, dataPlanAccionManual)
            if(exist != -1){
                dataPlanAccionManual[exist].requerido = 2
            }
        }
        return 0
    }
       
    let exist = existValor(valor, dataPlanAccionManual) //Me devuleve el indice del elemento en caso que exista
    if (this.checked) {

        if(exist == -1){
            dataPlanAccionManual.push({valor: parseInt(valor), requerido: 2})
        }
            //console.log(dataPlanAccionManual)
        //Funcion para ACTIVAR los checkbox Requeridos que correspondan a la misma fila
        $.each(checkbox, function (index, el) {
            let valorRequerido = $(el).val()
            if (valorRequerido == valor && $(el).hasClass('requerido'))
                $(el).prop('disabled', false)
        })
    } else {
        let valor = $(this).val() //Valor del checkbox padre
        if(exist != -1){
            dataPlanAccionManual.splice(exist,1)
        }
            
        //Funcion para DESACTIVAR los checkbox Requeridos que correspondan a la misma fila
        $.each(checkbox, function (index, el) {
            let valorRequerido = $(el).val()
            if (valorRequerido == valor && $(el).hasClass('requerido')){
                $(el).prop('disabled', true)
                $(el).prop('checked', false)
            }
                
        })
    }
});

function existValor(busq, array){
    let i = -1
    $.each(array, function(index, el){
        if(busq == el.valor)
            i = index
    })
    return i
}
// FIN FUNCIONES AHENAO

$('.empresaSelect').on('change', OnChangeEvaluado);
$('.clickEditar').on('click', OnClickEditar);
$('.cancelarPopUp').on('click', OnClickCancelarEdicionListaChequeo);
$('.actualizarPopUp').on('click', OnClickActualizarNombreLista);
$('.agregarCategoria').on('click', OnClickAgregarCategoria);
$('.cancelarCategoriaPopUp').on('click', OnClickCancelarAgregarCategoria);
$('.guardarPopUp').on('click', OnClickGuardarPopUp);
$('.agregarPregunta').on('click', OnClickAgregarPregunta);
$('.cancelarPreguntaPopUp').on('click', OnClickCancelarPregunta);
$('.Terminar').on('click', OnClickTerminarListaDeChequeo);
$('.linkSelect').on('change', OnChangeLinkTiempo);
$('.copiarLink').on('click', OnClickBotonCopiar);
$('.confirmar').on('click', ConfimarBoton);
$('.crearEtiqueta').on('click', OnClickTraerEtiquetas);
$('.cancelarPopUpCreacionEtiqueta').on('click', OnClickCancelarCreacionEtiqueta);
$('.crearAccionEtiqueta').on('click', OnClickAgregarEtiqueta);
