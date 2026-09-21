@extends('template.baseVertical')

@section('css')
    <link rel="stylesheet" href="{{ assets_version('/vertical/assets/css/listachequeo/detalle/main.css') }}">
@endsection



@section('breadcrumb')
    <h3 class="page-title">Detalle de la lista chequeo</h1>
    @endsection

    @section('section')

        <div class="contenedorAtras contenedorAtras--espacio m-b-10 col-12">
            <div class="acciones-descargar">
                <i class="mdi mdi-file-excel descarga-excel-pdf" tipo="excel"></i>
                <i class="mdi mdi-file-pdf descarga-excel-pdf" tipo="pdf"></i>
                <form action="{{ url('/informes/descargar-excel-lista') }}" method="GET" style="display: none;"
                    id="descargar-excel">
                    @csrf
                    <input type="hidden" name="listaId" id="listaId" />
                    <input type="hidden" name="tipo" id="tipo" />
                </form>
            </div>
            <a href="{{ route('List_MyList_Excecuted') }}" class="btn btn-primary waves-effect waves-light">Regresar</a>
        </div>

        <div class="row datosLista" idListaChequeoEjecutada="{{ Request::segment(3) }}">
            <div class="col-lg-12">
                <div class="row m-b-10">
                    <div class="col-lg-12">
                        {{-- PRIMERA SECCION --}}
                        <div class="contenedorEncabezado">
                            <div id="accordion">
                                <div class="card">

                                    <div class="card-header p-3 informacionPersonalEncabezado">
                                        <h6 class="m-0 text-center">
                                            <a class="">
                                                INFORME DE LA LISTA DE CHEQUEO
                                            </a>
                                        </h6>
                                    </div>

                                    <div id="" class="collapse show contenedorTextosEncabezado">

                                        <div class="card-header subtituloEncabezado">
                                            <p class="m-0">
                                                <a class="">
                                                    INFORMACIÓN GENERAL
                                                </a>
                                            </p>
                                        </div>

                                        <div class="row contenedorGeneralInformacion m-t-10">

                                            <div class="col-md-6">
                                                <div class="form-group grupoBorder">
                                                    <label class="col-lg-12 col-form-label">Modelo </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-center">{{ $seccionUno->NOMBRE_MODELO }}</label>
                                                </div>

                                                <div class="form-group grupoBorder">
                                                    <label class="col-lg-12 col-form-label">Publicado en </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-center">{{ $seccionUno->PUBLICADO_EN }}</label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group grupoBorder">
                                                    <label class="col-lg-12 col-form-label">Lista de chequeo </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-center">{{ $seccionUno->NOMBRE_LISTA_CHEQUEO }}</label>
                                                </div>

                                                <div class="form-group grupoBorder">
                                                    <label class="col-lg-12 col-form-label">Fecha de realización </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-center">{{ $seccionUno->FECHA_REALIZACION }}</label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group grupoBorder">
                                                    <label class="col-lg-12 col-form-label">Evaluado </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-center">{{ $seccionUno->EVALUADO_A }}</label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group grupoBorder">
                                                    <label class="col-lg-12 col-form-label">Evaluador </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-center">{{ $seccionUno->EVALUADOR }}</label>
                                                </div>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>
                        </div>
                        {{-- FIN PRIMERA SECCION --}}

                        {{-- SEGUNDA SECCION --}}
                        @if ($maneja_ponderado == 1)
                            <div class="segundaSeccion">
                                <div id="accordion">
                                    <div class="card">

                                        <div id="" class="collapse show contenedorTextosEncabezado ">

                                            <div class="card-header subtituloEncabezado">
                                                <p class="m-0">
                                                    <a class="">
                                                        RESULTADO FINAL
                                                    </a>
                                                </p>
                                            </div>

                                            <div class="row col-lg-12">

                                                <div class="row m-t-10 col-md-9 m-r-10 justify-content-center">

                                                    @foreach ($seccionDos['Categorias'] as $categoria)
                                                        <div class="col-md-6 col-lg-6 col-xl-3">
                                                            <div class="card m-b-20 tarjetaGeneral">
                                                                <div class="">
                                                                    <p class="mt-0 text-center">{{ $categoria->categoria }}
                                                                    </p>
                                                                    <p class="card-text text-center">
                                                                        {{ number_format($categoria->porc_cat, 2) }}%
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach

                                                </div>

                                                <div class="row m-t-10 col-md-3 contenedoResultado">
                                                    <div class="card-body">
                                                        <h1 class="card-title mt-0 text-center">Resultado final</h1>
                                                        <h1 class="card-text text-center">
                                                            {{ number_format($seccionDos['ResultadoFinal'], 2) }}%
                                                        </h1>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        @endif
                        {{-- FIN SEGUNDA SECCION --}}

                        {{-- TERCERA SECCION --}}
                        <div class="terceraSeccion">
                            <div id="accordion">
                                <div class="card">

                                    <div id="" class="collapse show contenedorTextosEncabezado ">

                                        <div class="card-header subtituloEncabezado">
                                            <p class="m-0">
                                                <a class="">
                                                    PREGUNTAS
                                                </a>
                                            </p>
                                        </div>

                                        @foreach ($seccionTres as $key => $categoriaPregunta)
                                            {{-- CATEGORIA --}}
                                            <div class="col-lg-12">
                                                <div id="accordionPreguntas">
                                                    <div class="card">
                                                        <div class="card-header p-3 categoriaItem">
                                                            <div class="row col-lg-12">
                                                                <h6 class="m-0 col-md-10 alineacionTexto">
                                                                    <a href="" class="categoriaItem">
                                                                        {{ $categoriaPregunta['NOMBRE_CATEGORIA'] }}
                                                                    </a>
                                                                </h6>
                                                                @if ($maneja_ponderado == 1)
                                                                    <h6 class="m-0 col-md-2" style="text-align: center;">
                                                                        <a href="#collapseOne" class="">
                                                                            Puntaje de categoría
                                                                            {{ $categoriaPregunta['PONDERADO_CATEGORIA'] }}%
                                                                        </a>
                                                                    </h6>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-12 animacionColl">
                                                            @foreach ($categoriaPregunta['PREGUNTAS'] as $keys => $pregunta)
                                                                {{-- PREGUNTA
                                                        --}}
                                                                <div class="preguntaEstilo" aria-labelledby="headingOne"
                                                                    data-parent="#accordion">
                                                                    <div class="card-body">
                                                                        <div class="row col-lg-12">
                                                                            <p><span
                                                                                    class="font-32">{{ $pregunta->ORDEN_PREGUNTA }}.</span>
                                                                                {{ $pregunta->NOMBRE_PREGUNTA }}
                                                                            </p>
                                                                        </div>

                                                                        <div class="row col-lg-12">
                                                                            <div
                                                                                class="row m-t-10 col-md-6 m-r-10 justify-content-center">

                                                                                @if ($pregunta->ES_RESPUESTA_ABIERTA == 1)
                                                                                    {{-- RESPUESTA ABIERTA --}}
                                                                                    <p>{{ $pregunta->RESPUESTA_ABIERTA }}
                                                                                    </p>
                                                                                    {{-- RESPUESTA ABIERTA - FIN --}}
                                                                                @else
                                                                                    @foreach ($pregunta->TIPOS_RESPUESTA as $opcionTipoRespuesta)
                                                                                        {{-- OPCIONES RESPUESTA --}}
                                                                                        <div
                                                                                            class="col-md-6 col-lg-6 col-xl-2 m-b-30">
                                                                                            <p class="mt-0 text-center">
                                                                                                {{ $opcionTipoRespuesta['valor_personalizado'] }}
                                                                                            </p>
                                                                                            <div
                                                                                                class="card-text text-center">
                                                                                                <span
                                                                                                    class="contenedorRespuesta ">
                                                                                                    @if ($pregunta->RESPUESTA_ID == $opcionTipoRespuesta['id'])
                                                                                                        <i
                                                                                                            class="ion-checkmark font-20 text-center"></i>
                                                                                                    @else
                                                                                                        <i class="ion-checkmark font-20 text-center"
                                                                                                            style="opacity:0;"></i>
                                                                                                    @endif
                                                                                                </span>
                                                                                            </div>
                                                                                        </div>
                                                                                        {{-- OPCIONES RESPUESTA --}}
                                                                                    @endforeach
                                                                                @endif



                                                                                @if ($pregunta->PERMITE_NO_APLICA == 1)
                                                                                    {{-- OPCIONES RESPUESTA NO APLICA --}}
                                                                                    <div
                                                                                        class="col-md-6 col-lg-6 col-xl-2 m-b-30">
                                                                                        <p class="mt-0 text-center">NA</p>
                                                                                        <div class="card-text text-center">
                                                                                            <span
                                                                                                class="contenedorRespuesta ">
                                                                                                @if ($pregunta->RESPUESTA_ID == 0)
                                                                                                    <i
                                                                                                        class="ion-checkmark font-20 text-center"></i>
                                                                                                @else
                                                                                                    <i class="ion-checkmark font-20 text-center"
                                                                                                        style="opacity:0;"></i>
                                                                                                @endif
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                    {{-- OPCIONES RESPUESTA NO APLICA --}}
                                                                                @endif

                                                                            </div>

                                                                            <div class="row m-t-10 col-md-6 m-r-10">
                                                                                <div class="form-group ">
                                                                                    <label
                                                                                        class="col-lg-12 col-form-label">Observación
                                                                                    </label>
                                                                                    <label
                                                                                        class="col-lg-12 col-form-label font-light ">{{ $pregunta->COMENTARIO }}</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        @if ($pregunta->HAY_FOTOS == 1)
                                                                            <div class="row col-lg-12">
                                                                                <div
                                                                                    class="row m-t-10 col-md-12 m-r-10 justify-content-center">

                                                                                    @foreach ($pregunta->FOTOS as $foto)
                                                                                        {{--
                                                                                CARGA IMAGENES
                                                                                --}}
                                                                                        <div
                                                                                            class="col-md-6 col-lg-6 col-xl-3 m-b-10">
                                                                                            <img src="{{ Request::root() . '/' . $foto['FOTO'] }}"
                                                                                                class="imagenesReportes"
                                                                                                alt="logo">
                                                                                        </div>
                                                                                        {{--
                                                                                CARGA IMAGENES
                                                                                --}}
                                                                                    @endforeach

                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        <div class="row col-lg-12">

                                                                            @if ($pregunta->HAY_ADJUNTOS == 1)
                                                                                <div
                                                                                    class="row m-t-10 col-md-4 m-r-10 justify-content-center">
                                                                                    <div
                                                                                        class="row m-t-10 col-md-12 m-r-10">
                                                                                        <div class="form-group ">
                                                                                            <label
                                                                                                class="col-lg-12 col-form-label">Archivos
                                                                                                Adjuntos</label>
                                                                                            <a href="#"
                                                                                                class="col-lg-12 col-form-label font-light adjuntosAuditoria"
                                                                                                resp="{{ $pregunta->RESPUESTA_FOTOS }}"
                                                                                                OnClick="AbrirPopUpAdjuntos(this,event)"><span
                                                                                                    class="badge badge-primary">{{ count($pregunta->ADJUNTOS) }}</a></span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            @if ($pregunta->HAY_PLAN_ACCION != 1)
                                                                                <div class="row m-t-10 col-md-4 m-r-10">
                                                                                    <div
                                                                                        class="row m-t-10 col-md-6 m-r-10">
                                                                                        <div class="form-group ">
                                                                                            <label
                                                                                                class="col-lg-12 col-form-label">Plan
                                                                                                de acción automatico</label>
                                                                                            <label
                                                                                                class="col-lg-12 col-form-label font-light ">{{ $pregunta->PLAN_ACCION }}</label>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @elseif($pregunta->HAY_PLAN_ACCION == 1)
                                                                                <div class="row m-t-10 col-md-4 m-r-10">
                                                                                    <div
                                                                                        class="row m-t-10 col-md-6 m-r-10">
                                                                                        <div class="form-group ">
                                                                                            <label
                                                                                                class="col-lg-12 col-form-label">Plan
                                                                                                de acción manual</label>
                                                                                            <div class="col-lg-12 col-form-label text-center"
                                                                                                onclick="clickPlanAccionManual(this)"
                                                                                                idpregunta="{{ $pregunta->ID_PREGUNTA }}">
                                                                                                <i class="mdi mdi-file-document"
                                                                                                    style="font-size: 20px"></i>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                {{-- FIN PREGUNTA
                                                        --}}
                                                            @endforeach
                                                        </div>


                                                    </div>

                                                </div>

                                            </div>
                                            {{-- FIN CATEGORIA
                                    --}}
                                        @endforeach

                                    </div>

                                </div>

                            </div>
                        </div>
                        {{-- FIN TERCERA SECCION --}}

                        {{-- CUARTA SECCION --}}
                        @if ($maneja_ponderado == 1)
                            <div class="cuartaSeccion">
                                <div id="accordion">
                                    <div class="card">

                                        <div id="" class="collapse show contenedorTextosEncabezado ">

                                            <div class="card-header subtituloEncabezado">
                                                <p class="m-0">
                                                    <a class="">
                                                        RESUMEN GENERAL
                                                    </a>
                                                </p>
                                            </div>

                                            <div class="row col-lg-12">

                                                <div class="row m-t-10 col-md-8 m-r-10 justify-content-center">

                                                    @foreach ($seccionCuatro as $resumenGeneral)
                                                        <div class="col-md-6 col-lg-6 col-xl-3">
                                                            <div class="card m-b-20 plan-accion">
                                                                <div class="card-body">
                                                                    <h4 class="card-title font-20 mt-0 text-center">
                                                                        {{ $resumenGeneral->respuesta }}
                                                                    </h4>
                                                                    <p class="card-text text-center">
                                                                        {{ number_format($resumenGeneral->cant) }}
                                                                    </p>
                                                                    <p class="card-text text-center">
                                                                        {{ number_format($resumenGeneral->porcentaje_pregunta, 2) /* / $resumenGeneral->cant) */ }}%
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach


                                                </div>

                                                <div class="row m-t-10 col-md-4">
                                                    <div class="col-lg-12">
                                                        <div class="card m-b-20 plan-accion">
                                                            <div class="card m-b-20">
                                                                <div class="card-body">
                                                                    <div class="chartjs-size-monitor"
                                                                        style="position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px; overflow: hidden; pointer-events: none; visibility: hidden; z-index: -1;">
                                                                        <div class="chartjs-size-monitor-expand"
                                                                            style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                                                            <div
                                                                                style="position:absolute;width:1000000px;height:1000000px;left:0;top:0">
                                                                            </div>
                                                                        </div>
                                                                        <div class="chartjs-size-monitor-shrink"
                                                                            style="position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;visibility:hidden;z-index:-1;">
                                                                            <div
                                                                                style="position:absolute;width:200%;height:200%;left:0; top:0">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <canvas id="myChart" width="327" height="327"
                                                                        class="chartjs-render-monitor"
                                                                        style="display: block; height: 262px; width: 262px;"></canvas>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        @endif
                        {{-- FIN CUARTA SECCION --}}


                        @if (COUNT($seccionQuinta) != 0)
                            {{-- QUINTA SECCION --}}
                            <div class="quintaSeccion">
                                <div id="accordion">
                                    <div class="card">

                                        <div id="" class="collapse show contenedorTextosEncabezado ">

                                            <div class="card-header subtituloEncabezado">
                                                <p class="m-0">
                                                    <a class="">
                                                        ITEMS CON PLAN DE ACCIÓN AUTOMATICO
                                                    </a>
                                                </p>
                                            </div>


                                            <div class="row col-lg-12">

                                                @foreach ($seccionQuinta as $seccion)
                                                    <div class="row m-t-10 col-md-4 m-r-10">
                                                        <div class="form-group col-lg-12">
                                                            <label class="col-lg-12 col-form-label">Pregunta </label>
                                                            <label
                                                                class="col-lg-12 col-form-label font-light text-bg-white plan-accion">{{ $seccion->pregunta }}</label>
                                                        </div>
                                                    </div>

                                                    <div class="row m-t-10 col-md-2 m-r-10 justify-content-center">

                                                        <div class="col-md-6 col-xl-12 text-center">
                                                            <label class="text-center">Respuesta</label>
                                                            <p class="mt-0 text-center">{{ $seccion->respuesta }}</p>
                                                            <div class="card-text text-center">
                                                                <span class="contenedorRespuesta ">
                                                                    <i class="ion-checkmark font-20 text-center"></i>
                                                                </span>
                                                            </div>
                                                        </div>

                                                    </div>


                                                    <div class="row m-t-10 col-md-6 m-r-10">
                                                        <div class="form-group col-lg-12">
                                                            <label class="col-lg-12 col-form-label">Plan de Acción</label>
                                                            <label
                                                                class="col-lg-12 col-form-label font-light text-bg-white plan-accion">{{ $seccion->plan_accion }}</label>
                                                        </div>
                                                    </div>



                                                    {{-- <div class="row m-t-10 col-md-3 m-r-10">
                                                <div class="form-group col-lg-12">
                                                    <label class="col-lg-12 col-form-label">Acción correctiva
                                                    </label>
                                                    <label
                                                        class="col-lg-12 col-form-label font-light text-bg-white plan-accion">{{ $seccion->ACCION_CORRECTIVA }}</label>
                                                </div>
                                            </div> --}}
                                                @endforeach

                                            </div>



                                        </div>

                                    </div>

                                </div>
                            </div>
                            {{-- FIN QUINTA SECCION --}}
                        @endif


                        <div class="col-lg-12">
                            <div class="card-header subtituloEncabezado">
                                <p class="m-0">
                                    <a class="">
                                        OBSERVACIÓN GENERAL
                                    </a>
                                </p>
                            </div>
                            <div class="col-lg-12 card">
                                <p class="mt-3">{{ $observacion_general }}</p>
                            </div>
                        </div>

                        @if(auth()->user()->cuenta_principal_id == 147 || auth()->user()->cuenta_principal_id == 148)
                        <div class="col-lg-12">
                            <div class="card-header subtituloEncabezado">
                                <p class="m-0">
                                    <a class="">
                                        REVISIÓN DE LA LISTA
                                    </a>
                                </p>
                            </div>
                            <div class="col-lg-12 card">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="" id="revisadoCheck"
                                        {{ $revisado ? 'checked' : '' }}>
                                    <label class="form-check-label" for="revisadoCheck">
                                        Revisado
                                    </label>
                                </div>
                            </div>
                        </div>
                        @endif


                    </div>
                </div>
            </div>
        </div>

        <!--  MODAL LINK-->
        <div class="modal fade bs-example-modal-lg" id="popUpComentario" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Guarda tu comentario </p>
                        <button type="button" class="close cancelarPopUp" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12">
                            <div class="card m-b-20">
                                <div class="card-body">
                                    <div class="">
                                        <textarea id="comentarioText" class="form-control" rows="4" placeholder="Escribe tu comentario..."></textarea>
                                    </div>

                                </div>
                            </div>
                            <div class="form-group m-b-0">
                                <div class="contenedorBotonesPopUp">
                                    <button type="button"
                                        class="btn btn-primary waves-effect waves-light guardarComentario">Guardar</button>
                                    <button type="button"
                                        class="btn btn-secondary waves-effect m-l-10 cancelarPopUp">Cancelar</button>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL LINK - FIN --}}

        <!--  MODAL IMAGEN AMPLIA -->
        <div class="modal fade bs-example-modal-lg" id="popUpImagenAmplia" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Imagen detalle</p>
                        <button type="button" class="close" aria-label="Close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12 contenedorImagenAmplia">
                            <img src="" class="imagenAmpliaDetalle" alt="logo">

                        </div> <!-- end col -->
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL IMAGEN AMPLIA - FIN --}}

        <!--  MODAL ADJUNTOS-->
        <div class="modal fade bs-example-modal-lg" id="popUpAdjuntos" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Archivos Adjuntos</p>
                        <button type="button" class="close cancelarPopUpAdjuntos" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12">
                            <div class="card m-b-20">
                                <div class="card-body">
                                    <table class="table tablaAdjuntos">
                                        <thead>
                                            <tr>
                                                <th scope="col">Nombre</th>
                                                <th scope="col">Fecha de carga</th>
                                                <th scope="col">Opciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="form-group m-b-0">

                            </div>
                        </div> <!-- end col -->

                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL ADJUNTOS - FIN --}}

        {{-- MODAL PLAN ACCION MANUAL --}}
        <div class="modal fade" id="modal-plan-manual" tabindex="-1" role="dialog"
            aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Plan de accíon manual</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body form-plan-accion">


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>


    @endsection

    @section('script')
        <script type="text/javascript">
            let seccionCuatro = {!! json_encode($seccionCuatro) !!};
            let idListaEjecutada = {!! json_encode($idListaEjecutada) !!};
        </script>
        <script type="text/javascript" src="{{ assets_version('/vertical/assets/js/listachequeo/detalle/main.js') }}"></script>
    @endsection
