@extends('template.baseVertical')

@section('css')
    <link rel="stylesheet" href="{{ assets_version('/vertical/assets/css/listachequeo/ejecucion/main.css') }}">
@endsection

@section('breadcrumb')
    <h3 class="page-title">Ejecutando lista chequeo</h1>
    @endsection

    @section('section')
        <div class="d-flex justify-content-end contenedorAtras m-b-10 col-12">
            <a href="{{ route('List_MyList_Excecuted') }}" class="btn btn-primary waves-effect waves-light">Regresar</a>
        </div>

        <div class="row datosLista" idListaChequeo="{{ Request::segment(3) }}"
            idListaChequeoEjecutada="{{ $idListaEjecutada }}">
            <div class="col-lg-12">
                <div class="row m-b-10">
                    <div class="col-lg-12">
                        <div class="contenedorEncabezado">
                            <div class="col-lg-12 m-t-10">
                                <div class="card card-body m-b-10">

                                    <div class="form-group row m-b-15">
                                        <label>Encabezado de la lista de chequeo</label>
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-3">
                                            <div class="form-group">
                                                <label>Lista de chequeo</label>
                                                <p class="listaChequeoNombre"></p>
                                            </div>
                                        </div>

                                        <div class="col-lg-5">
                                            <div class="form-group">
                                                <label>Evaluando a</label>
                                                <div class="col-md-0" style="display: flex;">

                                                    {{-- <input class="form-control py-2 border-left-0 border" type="text" value="" placeholder="" id=""> --}}
                                                    <select disabled
                                                        class="form-control select2 border-rigth-0 selectSearch evaluandoA">

                                                    </select>
                                                    <span class="input-group-append">
                                                        <button class="btn btn-outline-secondary border-left-0 border"
                                                            disabled type="button">
                                                            <i class="ion-checkmark" style="color: green"></i>
                                                        </button>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-3">
                                            <div class="form-group">
                                                <label>Fecha de realización</label>
                                                <div>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control"
                                                            value="{{ $fechaActual }}" placeholder="fecha de realización"
                                                            id="datepicker-autoclose">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text"><i class="mdi mdi-calendar"
                                                                    style="color: green"></i></span>
                                                        </div>
                                                    </div><!-- input-group -->
                                                </div>
                                            </div>
                                        </div>

                                    </div>


                                </div>

                                <div id="accordion" class="m-t-30 contenedorCategorias">


                                </div>

                                <div class="col-lg-12">
                                    <label for="">Observación general</label>
                                    <textarea class="form-control dev-obs-general" rows="4" placeholder="Escribe tu observación general..."></textarea>
                                </div>

                                <div class="col-lg-12 m-t-10">
                                    <button type="button"
                                        class="col-lg-12 btn btn-primary waves-effect waves-light TerminarListaChequeo">FINALIZAR
                                        LISTA DE CHEQUEO</button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!--  MODAL ADJUNTOS -->
        <div class="modal fade bs-example-modal-lg" id="popUpAdjuntos" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog" role="document">

                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Adjuntar Archivos</h5>
                        {{-- <button type="button" class="save" data-toggle="tooltip" data-placement="left" title="Guardar">
                    <span class="mdi mdi-content-save" aria-hidden="true"></span>
                  </button> --}}
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body modal-archivos-adjuntos">
                        <div class="container">
                            <label for="et_pb_contact_brand_file_request_0" class="et_pb_contact_form_label">Enter</label>
                            <input type="file" id="et_pb_contact_brand_file_request_0" class="file-upload" multiple
                                accept=".doc,.pptx,.xlsx,.pdf,.docx,.xls,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/*,video/*">
                        </div>

                    </div>

                    <div class="modal-footer" style="display: block;">

                        <div class="row ">
                            <div class="col-md-12">
                                <h6>Cantidad maxima de archivos (5) o 5MB</h6>
                            </div>
                            <div class="col-md-12">
                                <div class="contenedorNombres">
                                    {{-- <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                        <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                        <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                        <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                        <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt=""> --}}
                                </div>
                            </div>
                        </div>

                        <div class="row ">
                            <div class="col-md-12 d-flex justify-content-around">
                                <button type="button" class="btn btn-primary guardarAdjuntos" style="position:relative;">
                                    Guardar
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        {{-- MODAL ADJUNTOS - FIN --}}

        <!--  MODAL COMENTARIO-->
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
                            <div class="m-b-20">
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
        {{-- MODAL COMENTARIO - FIN --}}

        <!--  MODAL RESPUESTA ABIERTA-->
        <div class="modal fade bs-example-modal-lg" id="popUpRespuestaAbierta" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Guarda respuesta</p>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12">
                            <div class="m-b-20">
                                <div class="card-body">
                                    <div class="">
                                        <textarea id="respuestaAbiertaText" class="form-control" rows="4" placeholder="Escribe tu respuesta..."></textarea>
                                    </div>

                                </div>
                            </div>
                            <div class="form-group m-b-0">
                                <div class="contenedorBotonesPopUp">
                                    <button type="button"
                                        class="btn btn-primary waves-effect waves-light guardarRespuesta">Guardar
                                        respuesta</button>
                                    <button type="button"
                                        class="btn btn-secondary waves-effect m-l-10 cancelarPopUpRespuesta">Cancelar</button>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL RESPUESTA ABIERTA - FIN --}}

        <!--  MODAL NUMÉRICO-->
        <div class="modal fade bs-example-modal-lg" id="popUpRespuestaNumerica" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Guarda respuesta</p>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12">
                            <div class="m-b-20">
                                <div class="card-body">
                                    <div class="">
                                        <input id="respuestaNumericaText" class="form-control input-number-decimal"
                                            placeholder="Campo solo númerico..." />
                                    </div>

                                </div>
                            </div>
                            <div class="form-group m-b-0">
                                <div class="contenedorBotonesPopUp">
                                    <button type="button"
                                        class="btn btn-primary waves-effect waves-light guardarRespuestaNumerica">Guardar
                                        respuesta</button>
                                    <button type="button"
                                        class="btn btn-secondary waves-effect m-l-10 cancelarPopUpRespuestaNumerica">Cancelar</button>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL NUMÉRICO - FIN --}}

        <!--  MODAL TIEMPO-->
        <div class="modal fade bs-example-modal-lg" id="popUpRespuestaTiempo" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Guarda respuesta</p>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12">
                            <div class="m-b-20">
                                <div class="card-body">
                                    <div class="">
                                        <input id="respuestaTiempoText" class="form-control"
                                            placeholder="00:00 - 23:59" />
                                    </div>

                                </div>
                            </div>
                            <div class="form-group m-b-0">
                                <div class="contenedorBotonesPopUp">
                                    <button type="button"
                                        class="btn btn-primary waves-effect waves-light guardarRespuestaTiempo">Guardar
                                        respuesta</button>
                                    <button type="button"
                                        class="btn btn-secondary waves-effect m-l-10 cancelarPopUpRespuestaTiempo">Cancelar</button>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL TIEMPO - FIN --}}

        <!--  MODAL FECHA-->
        <div class="modal fade bs-example-modal-lg" id="popUpRespuestaFecha" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <p class="modal-title mt-0">Guarda respuesta</p>
                    </div>
                    <div class="modal-body">
                        <div class="col-lg-12">
                            <div class="m-b-20">
                                <div class="card-body">
                                    <div class="">
                                        <input id="respuestaFechaText" class="form-control" placeholder="0000-00-00" type="date"/>
                                        <input id="respuestaNum" class="form-control input-number" type="text"/>
                                        <select name="respuestaSelectEquipos" id="respuestaSelectEquipos"
                                            class="form-control select2 ">

                                        </select>
                                        <select name="respuestaSelectProductos" id="respuestaSelectProductos"
                                            class="form-control select2 ">

                                        </select>
                                        <select name="respuestaSelectReferencias" id="respuestaSelectReferencias"
                                            class="form-control select2 ">

                                        </select>
                                        <select name="respuestaSelectNumerico" id="respuestaSelectNumerico"
                                            class="form-control select2 ">
                                        </select>

                                        <select name="respuestaSelectLotes" id="respuestaSelectLotes"
                                            class="form-control select2 ">
                                        </select>
                                        <select name="respuestaSelectAreas" id="respuestaSelectAreas"
                                            class="form-control select2 ">
                                        </select>
                                        <select name="respuestaSelectMotivos" id="respuestaSelectMotivos"
                                            class="form-control select2 ">
                                        </select>
                                    </div>

                                </div>
                            </div>
                            <div class="form-group m-b-0">
                                <div class="contenedorBotonesPopUp">
                                    <button type="button"
                                        class="btn btn-primary waves-effect waves-light guardarRespuestaFecha">Guardar
                                        respuesta</button>
                                    <button type="button"
                                        class="btn btn-secondary waves-effect m-l-10 cancelarPopUpRespuestaFecha">Cancelar</button>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div>
        {{-- MODAL FECHA - FIN --}}

        {{-- MODAL CAMARA --}}
        <div class="modal fade bs-example-modal-lg" id="popUpCamara" tabindex="-1" role="dialog"
            aria-labelledby="myLargeModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body camera-body">
                        <video id="video" width="400" height="338" autoplay="autoplay"></video>
                        <canvas id="canvas" width="400" height="338" style="position:absolute;"></canvas>
                    </div>

                    <div class="modal-footer camara-footer">
                        <div class="row ">
                            <div class="col-md-12 d-flex justify-content-around">
                                <a href="#" class="btn btn-primary foto" style="position:relative;">
                                    Tomar foto
                                </a>
                                <button type="button" class="btn btn-success guardar" style="position:relative;">
                                    Guardar imagenes
                                </button>
                                <button type="button"
                                    class="cerrarPopUpCamara btn btn-secondary waves-effect m-l-10">Cerrar</button>
                            </div>
                            <div class="contenedorImgs">
                                {{-- <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                            <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                            <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                            <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt="">
                            <img src="https://yt3.ggpht.com/a/AGF-l790ZGzS4Qw4FWGGEp6MQHqbWjxxvVeJhF7_sA=s900-mo-c-c0xffffffff-rj-k-no" alt=""> --}}
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
        {{-- MODAL CAMARA - FIN --}}

        {{-- MODAL PLAN ACCION MANUAL --}}
        <div class="modal fade" id="modal-plan-manual" tabindex="-1" role="dialog"
            aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Plan de acción manual</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body form-plan-accion cuerpo-pa-m">


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary guardar-plan-accion-manual">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- MODAL PLAN ACCION MANUAL - FIN --}}
    @endsection

    @section('script')
        <script type="text/javascript" src="{{ assets_version('/vertical/assets/js/listachequeo/ejecucion/main.js') }}">
        </script>
        <script>            
            let equipos_max = @json($equipos);            
            let areas = @json($areas);
        </script>
    @endsection
