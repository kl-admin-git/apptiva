<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\ListaChequeoPlanAccionCorrectiva;
use App\Http\Models\ListaChequeoPlanAccionEjecucion;
use App\Http\Models\ListaChequeoEjecutadasRespuestas;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use App\Exports\PlanAccionExports;
use App\Exports\PlanAccionHallazgosExports;
use App\Http\Models\PlanAccion;
use App\Http\Models\PlanAccionManual;
use App\Http\Models\PlanAccionSeguimiento;
use App\Http\Models\PlanAccionSeguimientoDetalle;
use App\Mail\MailCambioSeguimientoPlanAccionManual;

use Carbon\Carbon;

class ListaChequeoPlanAccionController extends Controller
{
    protected  $planAccionSeguimientoDetalle,$planAccionSeguimiento,$empresa,$establecimiento,$planAccionEjecucion,$listaChequeoEjecRespuesta,$planDeAccionCorrectiva, $planAccion, $planAccionManual;

    public function __construct(
        ListaChequeoPlanAccionCorrectiva $planDeAccionCorrectiva,
        ListaChequeoPlanAccionEjecucion $planAccionEjecucion,
        ListaChequeoEjecutadasRespuestas $listaChequeoEjecRespuesta,
        Empresa $empresa,
        Establecimiento $establecimiento,
        PlanAccion $planAccion,
        PlanAccionManual $planAccionManual,
        PlanAccionSeguimiento $planAccionSeguimiento,
        PlanAccionSeguimientoDetalle $planAccionSeguimientoDetalle
    )
    {
        $this->planDeAccionCorrectiva = $planDeAccionCorrectiva;
        $this->planAccionEjecucion = $planAccionEjecucion;
        $this->listaChequeoEjecRespuesta = $listaChequeoEjecRespuesta;
        $this->empresa = $empresa;
        $this->establecimiento = $establecimiento;
        $this->planAccion = $planAccion;
        $this->planAccionManual = $planAccionManual;
        $this->planAccionSeguimiento = $planAccionSeguimiento;
        $this->planAccionSeguimientoDetalle = $planAccionSeguimientoDetalle;

        try {
            \DB::statement("SET lc_time_names = 'es_ES'");
        } catch (\Throwable $e) {}
        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        $filtrar = NULL;
        if(!is_null(\Request::segment(3)))
        {
            if($this->planAccionEjecucion->where('id', '=',\Request::segment(3))->exists())
            {
                $filtrar = \Request::segment(3);
            }
        }
        
        $listaChequeo = $this->FuncionParaTraerListasChequeoPlanAccion();
        $evaluados = $this->FuncionParaTraerEvaluadoPlanAccion();
        $evaluadores = $this->FuncionParaTraerEvaluadorPlanAccion();
        $empresas = $this->empresa->select(
            'empresa.id',
            'empresa.identificacion',
            'empresa.nombre',
            'empresa.direccion'
        );

        $empresas = $this->listaChequeoEjecRespuesta
        ->select(
            \DB::raw("(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS id"),
            \DB::raw("(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS nombre")
            // 'empe.id',
            // 'empe.nombre',
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id')
        ->groupByRaw("(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END)");

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $planAccionEnListar = $this->FuncionTraerPlanDeAccionAdministradorEnListar();    
                $empresas = $empresas->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ])->get();    
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                {
                    $planAccionEnListar = $this->FuncionTraerPlanDeAccionColaboradorResponsableEmpresaEnListar($esResponsableEmpresa->id);        

                    $empresas = $empresas->where([
                        ['empe.id','=', $esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();
                }
                    

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                {
                    $empresas = $empresas->where([
                        ['esta.id','=', $esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                    $planAccionEnListar = $this->FuncionTraerPlanDeAccionColaboradorResponsableEstablecimientoEnListar($esResponsableEstablecimiento->id);
                }
                    

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                {
                    $empresas = $empresas->where([
                        ['us.id','=', auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();
                    
                    $planAccionEnListar = $this->FuncionTraerPlanDeAccionColaboradorEnListar(auth()->user()->id);
                    
                }
                    

                break;
            
            default:

                break;
        }
        
        return view('Admin.listachequeo_plan_accion',compact('listaChequeo','evaluados','evaluadores','planAccionEnListar','filtrar','empresas'));
    }

    public function FuncionParaTraerListasChequeoPlanAccion()
    {
        $traerPlanAcciones = $this->listaChequeoEjecRespuesta
        ->select(
            'lc.nombre',
            'lc.id'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id')
        ->groupBy('lc.id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $traerPlanAcciones = $traerPlanAcciones->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ])->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                break;
            
            default:

                break;
        }

        return $traerPlanAcciones;
    }

    public function FuncionParaTraerEvaluadoPlanAccion()
    {
        $traerPlanAcciones = $this->listaChequeoEjecRespuesta
        ->select(
            \DB::raw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as evaluado'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as entidad_evaluada')
            
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id')
        ->groupByRaw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END)');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $traerPlanAcciones = $traerPlanAcciones->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ])->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                break;
            
            default:

                break;
        }

        return $traerPlanAcciones;
    }

    public function FuncionParaTraerEvaluadorPlanAccion()
    {
        $evaluadores = $this->listaChequeoEjecRespuesta
        ->select(
            'us.nombre_completo AS evaluador',
            'us.id'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id')
        ->groupBy('us.id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $evaluadores = $evaluadores->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ])->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $evaluadores = $evaluadores->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                break;
            
            default:

                break;
        }

        return $evaluadores;
    }

    public function CrearAccionCorrectiva(Request $request)
    {
        $titulo = $request->get('titulo');
        $descripcion = $request->get('descripcion');
        $color = $request->get('color');

        $arrayInsertar = [
            'titulo' => $titulo, 
            'descripcion' => $descripcion,
            'color' => $color,
            'cuenta_principal_id' => auth()->user()->cuenta_principal_id
        ];

        $planDeAccionCorrectiva = new $this->planDeAccionCorrectiva;
        $planDeAccionCorrectiva->fill($arrayInsertar);

        if($planDeAccionCorrectiva->save())
        {

            $correctivos = $this->planDeAccionCorrectiva->where('cuenta_principal_id','=',auth()->user()->cuenta_principal_id)->get();

            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('El correctivo ',200),
                array(
                    'idCreatoOpcion' => $planDeAccionCorrectiva->id,
                    'opcionesSelects' => $correctivos
                )
            );
        }
    }

    public function TraerCorrectivos(Request $request)
    {
        $correctivos = $this->planDeAccionCorrectiva->where('cuenta_principal_id','=',auth()->user()->cuenta_principal_id)->get();
        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos ',202),
            $correctivos
        );
    }

    public function EliminarCorrectivo(Request $request)
    {
        $idCorrectivo = $request->get('idCorrectivo');

        if(!$this->planAccionEjecucion->where('accion_correctiva_id','=',$idCorrectivo)->exists())
        {
            $respuesta = $this->planDeAccionCorrectiva->where('id', $idCorrectivo)->delete();

            $correctivos = $this->planDeAccionCorrectiva->where('cuenta_principal_id','=',auth()->user()->cuenta_principal_id)->get();
            
            return $this->FinalizarRetorno(
                206,
                $this->MensajeRetorno('',206,'El correctivo se ha eliminado correctamente'),
                $correctivos
            );
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'El correctivo no se pudo eliminar por que está en usado')
            );
        }
        
        
    }

    public function ConsultarPlanesDeAccion(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $planesAccion = $this->FuncionTraerPlanDeAccionAdministrador($paginacion,$arrayFiltros);        
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $planesAccion = $this->FuncionTraerPlanDeAccionColaboradorResponsableEmpresa($esResponsableEmpresa->id,$paginacion,$arrayFiltros);        

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $planesAccion = $this->FuncionTraerPlanDeAccionColaboradorResponsableEstablecimiento($esResponsableEstablecimiento->id,$paginacion,$arrayFiltros);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $planesAccion = $this->FuncionTraerPlanDeAccionColaborador(auth()->user()->id,$paginacion,$arrayFiltros);

                break;
            
            default:

                break;
        }
        
        

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Planes acción',202),
            $planesAccion
        );
    }

    

    public function FuncionTraerPlanDeAccionAdministrador($paginacion=1,$filtros=[],$manual = 0)
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
        
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];
        $whereRaw = 0;
        $whereRawEvaluado = 0;
        foreach ($filtros as $key => $filtro) 
        {
            switch ($key) {
                case 'filtro_realizacion':
                    if($filtro != '')
                        array_push($filtro_array,['lce.fecha_realizacion', '=', date('Y-m-d', strtotime($filtro))]);
                    break;

                case 'filtro_lista_chequeo':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if($filtro != '')
                        // array_push($filtro_array,['lc.entidad_evaluada', '=', $filtro]);
                        $whereRawEvaluado = $filtro;
                    break;

                case 'filtro_evaluador':
                    if($filtro != '')
                        array_push($filtro_array,['us.id', '=', $filtro]);
                    break;

                case 'filtro_codigo':
                    if($filtro != '')
                        array_push($filtro_array,['lcep.id', '=', $filtro]);
                    break;

                case 'filtro_empresa':
                    if($filtro != '')
                        $whereRaw = $filtro;
                    break;

                default:
                    
                    break;
            }
            
        }
        
        $traerPlanAcciones = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            \DB::raw("IF(lista_chequeo_ejec_respuestas.respuesta_abierta IS NULL,0,1) AS ES_RESPUESTA_ABIERTA"),
            \DB::raw("lista_chequeo_ejec_respuestas.respuesta_abierta AS RESPUESTA_ABIERTA"),
            'lcep.id AS CODIGO_PLAN_ACCION',
            \DB::raw('IF((SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1) IS NULL, "Abierto",
            (CASE
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=1 THEN "Abierto"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=2 THEN "En proceso"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=3 THEN "Cerrado"
                ELSE "Error"
            END)) AS ESTADO'),
            'lceo.id AS ID_EJECT_OPCIONES',
            \DB::raw('DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION'),
            'pa.id AS ID_PLAN_ACCION',
            'lc.nombre',
            \DB::raw('(CASE
                        WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as EMPRESA'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as evaluado'),
            'us.nombre_completo AS evaluador',
            'lce.id as ejecutada_id',
            'pre.id as pregunta_id',
            'pre.nombre as pregunta',
            \DB::raw("IF(lceo.comentario IS NULL, 'Sin observaciones', lceo.comentario)AS OBSERVACION"),
            \DB::raw('IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta'),
            'pa.tipo_pa as tipo_plan_accion'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
            ->groupBy('lcep.id');

        $traerPlanAcciones = $traerPlanAcciones->where([
            ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
            ['lce.estado','=',2]
        ])
        ->orderBy('lce.id','DESC');
        
        if(COUNT($filtro_array) != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->where(function($query) use ($filtro_array)
            {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) 
                {
                    // if( $contador == 0)
                    //     $query->where($oW[0], '=', $oW[2]);
                    // else
                    // {
                    //     $query->orWhere($oW[0], '=', $oW[2]);
                    // }
                    $query->where($oW[0], '=', $oW[2]);

                    // $contador = $contador + 1;
                }

                return $query;
            });
            
        }

        if($whereRaw != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                    WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) = ?',[$whereRaw]);

        }

        if($whereRawEvaluado != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) = ?',[$whereRawEvaluado]);
        }
        
        $rango = $traerPlanAcciones->paginate($cantidadRegistros)->lastPage();
        $traerPlanAcciones = $traerPlanAcciones->skip($desde)->take($hasta)->get();
        
        return array(
            'cantidadTotal' => $rango,
            'planesAccion' => $traerPlanAcciones
        );
    }

    public function FuncionTraerPlanDeAccionColaboradorResponsableEmpresa($idEmpresa,$paginacion=1,$filtros=[],$manual = 0)
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];
        $whereRaw = 0;
        $whereRawEvaluado = 0;
        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_realizacion':
                    if($filtro != '')
                        array_push($filtro_array,['lce.fecha_realizacion', '=', date('Y-m-d', strtotime($filtro))]);
                    break;

                case 'filtro_lista_chequeo':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if($filtro != '')
                        // array_push($filtro_array,['lc.entidad_evaluada', '=', $filtro]);
                        $whereRawEvaluado = $filtro;
                    break;

                case 'filtro_evaluador':
                    if($filtro != '')
                        array_push($filtro_array,['us.id', '=', $filtro]);
                    break;

                case 'filtro_codigo':
                    if($filtro != '')
                        array_push($filtro_array,['lcep.id', '=', $filtro]);
                    break;

                case 'filtro_empresa':
                    if($filtro != '')
                        $whereRaw = $filtro;
                    break;

                default:
                    
                    break;
            }
            
        }


        $traerPlanAcciones = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            \DB::raw("IF(lista_chequeo_ejec_respuestas.respuesta_abierta IS NULL,0,1) AS ES_RESPUESTA_ABIERTA"),
            \DB::raw("lista_chequeo_ejec_respuestas.respuesta_abierta AS RESPUESTA_ABIERTA"),
            'lcep.id AS CODIGO_PLAN_ACCION',
            \DB::raw('IF((SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1) IS NULL, "Abierto",
            (CASE
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=1 THEN "Abierto"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=2 THEN "En proceso"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=3 THEN "Cerrado"
                ELSE "Error"
            END)) AS ESTADO'),
            'lceo.id AS ID_EJECT_OPCIONES',
            \DB::raw('DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION'),
            'pa.id AS ID_PLAN_ACCION',
            'lc.nombre',
            \DB::raw('(CASE
                        WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as EMPRESA'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as evaluado'),
            'us.nombre_completo AS evaluador',
            'lce.id as ejecutada_id',
            'pre.id as pregunta_id',
            'pre.nombre as pregunta',
            \DB::raw("IF(lceo.comentario IS NULL, 'Sin observaciones', lceo.comentario)AS OBSERVACION"),
            \DB::raw('IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta'),
            'pa.tipo_pa as tipo_plan_accion'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
            ->groupBy('lcep.id');

        $traerPlanAcciones = $traerPlanAcciones->where([
            ['empe.id','=',$idEmpresa],
            ['lce.estado','=',2]
        ])
        ->groupBy('cat.id','pre.id','lce.id')
        ->orderBy('lce.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->where(function($query) use ($filtro_array)
            {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) 
                {
                    // if( $contador == 0)
                    //     $query->where($oW[0], '=', $oW[2]);
                    // else
                    // {
                    //     $query->orWhere($oW[0], '=', $oW[2]);
                    // }
                    $query->where($oW[0], '=', $oW[2]);

                    // $contador = $contador + 1;
                }

                return $query;
            });
            
        }

        if($whereRaw != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                    WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) = ?',[$whereRaw]);

        }

        if($whereRawEvaluado != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) = ?',[$whereRawEvaluado]);
        }

        $rango = $traerPlanAcciones->paginate($cantidadRegistros)->lastPage();
        $traerPlanAcciones = $traerPlanAcciones->skip($desde)->take($hasta)->get();

        return array(
            'cantidadTotal' => $rango,
            'planesAccion' => $traerPlanAcciones
        );
    }

    public function FuncionTraerPlanDeAccionColaboradorResponsableEstablecimiento($idEstablecimiento,$paginacion=1,$filtros=[],$manual = 0)
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];
        $whereRaw = 0;
        $whereRawEvaluado = 0;
        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_realizacion':
                    if($filtro != '')
                        array_push($filtro_array,['lce.fecha_realizacion', '=', date('Y-m-d', strtotime($filtro))]);
                    break;

                case 'filtro_lista_chequeo':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if($filtro != '')
                        // array_push($filtro_array,['lc.entidad_evaluada', '=', $filtro]);
                        $whereRawEvaluado = $filtro;
                    break;

                case 'filtro_evaluador':
                    if($filtro != '')
                        array_push($filtro_array,['us.id', '=', $filtro]);
                    break;

                case 'filtro_codigo':
                    if($filtro != '')
                        array_push($filtro_array,['lcep.id', '=', $filtro]);
                    break;

                case 'filtro_empresa':
                    if($filtro != '')
                        $whereRaw = $filtro;
                    break;

                default:
                    
                    break;
            }
            
        }

        $traerPlanAcciones = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            \DB::raw("IF(lista_chequeo_ejec_respuestas.respuesta_abierta IS NULL,0,1) AS ES_RESPUESTA_ABIERTA"),
            \DB::raw("lista_chequeo_ejec_respuestas.respuesta_abierta AS RESPUESTA_ABIERTA"),
            'lcep.id AS CODIGO_PLAN_ACCION',
            \DB::raw('IF((SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1) IS NULL, "Abierto",
            (CASE
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=1 THEN "Abierto"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=2 THEN "En proceso"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=3 THEN "Cerrado"
                ELSE "Error"
            END)) AS ESTADO'),
            'lceo.id AS ID_EJECT_OPCIONES',
            \DB::raw('DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION'),
            'pa.id AS ID_PLAN_ACCION',
            'lc.nombre',
            \DB::raw('(CASE
                        WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as EMPRESA'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as evaluado'),
            'us.nombre_completo AS evaluador',
            'lce.id as ejecutada_id',
            'pre.id as pregunta_id',
            'pre.nombre as pregunta',
            \DB::raw("IF(lceo.comentario IS NULL, 'Sin observaciones', lceo.comentario)AS OBSERVACION"),
            \DB::raw('IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta'),
            'pa.tipo_pa as tipo_plan_accion'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
            ->groupBy('lcep.id');

        $traerPlanAcciones = $traerPlanAcciones->where([
            ['esta.id','=',$idEstablecimiento],
            ['lce.estado','=',2]
        ])
        ->orderBy('lce.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->where(function($query) use ($filtro_array)
            {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) 
                {
                    // if( $contador == 0)
                    //     $query->where($oW[0], '=', $oW[2]);
                    // else
                    // {
                    //     $query->orWhere($oW[0], '=', $oW[2]);
                    // }
                    $query->where($oW[0], '=', $oW[2]);

                    // $contador = $contador + 1;
                }

                return $query;
            });
            
        }

        if($whereRaw != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                    WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) = ?',[$whereRaw]);

        }

        if($whereRawEvaluado != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) = ?',[$whereRawEvaluado]);
        }

        $rango = $traerPlanAcciones->paginate($cantidadRegistros)->lastPage();
        $traerPlanAcciones = $traerPlanAcciones->skip($desde)->take($hasta)->get();

        
        return array(
            'cantidadTotal' => $rango,
            'planesAccion' => $traerPlanAcciones
        );
    }

    public function FuncionTraerPlanDeAccionColaborador($idUsuario,$paginacion=1,$filtros=[],$manual = 0)
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];
        $whereRaw = 0;
        $whereRawEvaluado = 0;
        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_realizacion':
                    if($filtro != '')
                        array_push($filtro_array,['lce.fecha_realizacion', '=', date('Y-m-d', strtotime($filtro))]);
                    break;

                case 'filtro_lista_chequeo':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if($filtro != '')
                        // array_push($filtro_array,['lc.entidad_evaluada', '=', $filtro]);
                        $whereRawEvaluado = $filtro;
                    break;

                case 'filtro_evaluador':
                    if($filtro != '')
                        array_push($filtro_array,['us.id', '=', $filtro]);
                    break;

                case 'filtro_codigo':
                    if($filtro != '')
                        array_push($filtro_array,['lcep.id', '=', $filtro]);
                    break;

                case 'filtro_empresa':
                    if($filtro != '')
                        $whereRaw = $filtro;
                    break;

                default:
                    
                    break;
            }
            
        }

        $traerPlanAcciones = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            \DB::raw("IF(lista_chequeo_ejec_respuestas.respuesta_abierta IS NULL,0,1) AS ES_RESPUESTA_ABIERTA"),
            \DB::raw("lista_chequeo_ejec_respuestas.respuesta_abierta AS RESPUESTA_ABIERTA"),
            'lcep.id AS CODIGO_PLAN_ACCION',
            \DB::raw('IF((SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1) IS NULL, "Abierto",
            (CASE
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=1 THEN "Abierto"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=2 THEN "En proceso"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=3 THEN "Cerrado"
                ELSE "Error"
            END)) AS ESTADO'),
            'lceo.id AS ID_EJECT_OPCIONES',
            \DB::raw('DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION'),
            'pa.id AS ID_PLAN_ACCION',
            'lc.nombre',
            \DB::raw('(CASE
                        WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as EMPRESA'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as evaluado'),
            'us.nombre_completo AS evaluador',
            'lce.id as ejecutada_id',
            'pre.id as pregunta_id',
            'pre.nombre as pregunta',
            \DB::raw("IF(lceo.comentario IS NULL, 'Sin observaciones', lceo.comentario)AS OBSERVACION"),
            \DB::raw('IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta'),
            'pa.tipo_pa as tipo_plan_accion'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerPlanAcciones = $traerPlanAcciones->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
            ->groupBy('lcep.id');

        $traerPlanAcciones = $traerPlanAcciones->where([
            ['lce.usuario_id','=',$idUsuario],
            ['lce.estado','=',2]
        ])
        ->orderBy('lce.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->where(function($query) use ($filtro_array)
            {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) 
                {
                    // if( $contador == 0)
                    //     $query->where($oW[0], '=', $oW[2]);
                    // else
                    // {
                    //     $query->orWhere($oW[0], '=', $oW[2]);
                    // }
                    $query->where($oW[0], '=', $oW[2]);

                    // $contador = $contador + 1;
                }

                return $query;
            });
            
        }

        if($whereRaw != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                    WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) = ?',[$whereRaw]);

        }

        if($whereRawEvaluado != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) = ?',[$whereRawEvaluado]);
        }

        $rango = $traerPlanAcciones->paginate($cantidadRegistros)->lastPage();
        $traerPlanAcciones = $traerPlanAcciones->skip($desde)->take($hasta)->get();

        return array(
            'cantidadTotal' => $rango,
            'planesAccion' => $traerPlanAcciones
        );
    }

    // GROUP BY 
    public function FuncionTraerPlanDeAccionAdministradorEnListar($manual = 0)
    {
        $traerListas = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            'lcep.id AS CODIGO_PLAN_ACCION'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerListas = $traerListas->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerListas = $traerListas->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id');

        $traerListas = $traerListas->where(
            [
                ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                ['lce.estado','=',2]
            ]
        )
        ->groupBy('lcep.id')->get();
        
        return $traerListas;
    }

    public function FuncionTraerPlanDeAccionColaboradorResponsableEmpresaEnListar($idEmpresa,$manual=0)
    {
        $traerListas = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            'lcep.id AS CODIGO_PLAN_ACCION'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerListas = $traerListas->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerListas = $traerListas->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id');

        $traerListas = $traerListas->where(
            [
                ['empe.id','=',$idEmpresa],
                ['lce.estado','=',2]
            ]
        )
        ->groupBy('lcep.id')->get();

        return $traerListas;
    }

    public function FuncionTraerPlanDeAccionColaboradorResponsableEstablecimientoEnListar($idEstablecimiento,$manual=0)
    {
        $traerListas = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            'lcep.id AS CODIGO_PLAN_ACCION'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerListas = $traerListas->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerListas = $traerListas->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id');

        $traerListas = $traerListas->where(
            [
                ['esta.id','=',$idEstablecimiento],
                ['lce.estado','=',2]
            ]
        )
        ->groupBy('lcep.id')->get();

        return $traerListas;
    }

    public function FuncionTraerPlanDeAccionColaboradorEnListar($idUsuario,$manual=0)
    {
        $traerListas = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            'lcep.id AS CODIGO_PLAN_ACCION'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id');

        if($manual == 0)
            $traerListas = $traerListas->Join('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id');
        else
            $traerListas = $traerListas->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id');
            
        $traerListas = $traerListas->where(
            [
                ['us.id','=',$idUsuario],
                ['lce.estado','=',2]
            ]
        )
        ->groupBy('lcep.id')->get();

        return$traerListas;
    }

    // FIN - GROUP BY

    public function FuncionParaSaberSiEsResponsableEmpresa($idUsuario)
    {
        $esResponsableEmpresa = $this->empresa->where('usuario_id','=',$idUsuario)->first();

        return $esResponsableEmpresa;
    }

    public function FuncionParaSaberSiEsResponsableEstablecimiento($idUsuario)
    {
        $esResponsableEstablecimiento = $this->establecimiento->where('usuario_id','=',$idUsuario)->first();

        return $esResponsableEstablecimiento;
    }

    public function AsignarCriticoPlanAccion(Request $request)
    {
         $idOpcPlanAccion = $request->get('idOpcPlanAccion');
         $idCritico = $request->get('idCritico');

         $arrayActualizar = [
            'accion_correctiva_id' => $idCritico
        ];
        
        $respuestaUpdate = $this->planAccionEjecucion->where('lista_chequeo_ejec_opciones','=',$idOpcPlanAccion)->update($arrayActualizar);

        $consultaEstadoCorrectivo = $this->planDeAccionCorrectiva->where('id', $idCritico)->first();

        return $this->FinalizarRetorno(
            201,
            $this->MensajeRetorno('Planes acción ',201),
            $consultaEstadoCorrectivo
        );
    }

    public function FuncionTraerPlanAccionCantidadTotal($cantidadDividir) // SE CAMBIO, SIRVE PERO NO SE USO
    {
        $traerPlanAcciones = \DB::select('SELECT
        lceo.id AS ID_EJECT_OPCIONES,
        DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION,
        lc.nombre,
        (CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) as evaluado,
        usu.nombre_completo AS evaluador,
        lce.id as ejecutada_id,
        pre.id as pregunta_id,
        pre.nombre as pregunta,
        IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta,
        paa.plan_accion_descripcion as plan_accion,
        IF((SELECT sac.titulo FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id ) IS NULL,"",(SELECT sac.titulo FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id )) AS TITULO,
        IF((SELECT sac.color FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id ) IS NULL,"",(SELECT sac.color FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id )) AS COLOR
        FROM lista_chequeo_ejec_respuestas lcer
        LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
        INNER JOIN usuario usu ON usu.id=lc.usuario_id
        INNER JOIN establecimiento esta ON esta.id=usu.establecimiento_id
        INNER JOIN empresa empe on empe.id=esta.empresa_id
        INNER JOIN cuenta_principal cta ON cta.id=usu.cuenta_principal_id
        LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
        LEFT JOIN plan_accion pa ON pa.respuesta_id=lcer.respuesta_id
        INNER JOIN plan_accion_automatico paa ON paa.plan_accion_id=pa.id
        INNER JOIN lista_chequeo_ejec_opciones lceo ON lceo.lista_chequeo_ejec_respuestas_id=lcer.id
        LEFT JOIN lista_chequeo_ejec_planaccion lcep ON lcep.lista_chequeo_ejec_opciones=lceo.id
        WHERE cta.id=:idCuentaPrincipal',['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        $array = array_chunk($traerPlanAcciones, $cantidadDividir);

        return COUNT($array);
    }

    public function FuncionTraerPlanDeAccionAdministradorOLD($paginacion=1) // SE CAMBIO, SIRVE PERO NO SE USO
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
        
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;

        $traerPlanAcciones = \DB::select('SELECT
        lceo.id AS ID_EJECT_OPCIONES,
        DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION,
        lc.nombre,
        (CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) as evaluado,
        usu.nombre_completo AS evaluador,
        lce.id as ejecutada_id,
        pre.id as pregunta_id,
        pre.nombre as pregunta,
        IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta,
        paa.plan_accion_descripcion as plan_accion,
        IF((SELECT sac.titulo FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id ) IS NULL,"",(SELECT sac.titulo FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id )) AS TITULO,
        IF((SELECT sac.color FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id ) IS NULL,"",(SELECT sac.color FROM accion_correctiva sac WHERE  sac.id=lcep.accion_correctiva_id )) AS COLOR
        FROM lista_chequeo_ejec_respuestas lcer
        LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
        INNER JOIN usuario usu ON usu.id=lc.usuario_id
        INNER JOIN establecimiento esta ON esta.id=usu.establecimiento_id
        INNER JOIN empresa empe on empe.id=esta.empresa_id
        INNER JOIN cuenta_principal cta ON cta.id=usu.cuenta_principal_id
        LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
        LEFT JOIN plan_accion pa ON pa.pegunta_id=pre.id 
        INNER JOIN plan_accion_automatico paa ON paa.plan_accion_id=pa.id
        INNER JOIN lista_chequeo_ejec_opciones lceo ON lceo.lista_chequeo_ejec_respuestas_id=lcer.id
        LEFT JOIN lista_chequeo_ejec_planaccion lcep ON lcep.lista_chequeo_ejec_opciones=lceo.id
        WHERE cta.id=:idCuentaPrincipal LIMIT :desde,:hasta',['idCuentaPrincipal' => auth()->user()->cuenta_principal_id,'desde'=> $desde,'hasta'=> $hasta]);

        return array(
            'cantidadTotal' => $this->FuncionTraerPlanAccionCantidadTotal(9),
            'planesAccion' => $traerPlanAcciones
        ) ;
    }
    
    public function descargaExcelPlanAccion(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');
        $filtros = json_decode($request->get('filtros_busqueda'));
        return \Excel::download(new PlanAccionExports($filtros,$this->listaChequeoEjecRespuesta), 'informe_plan_accion.xlsx');
    }

    //SEGUIMIENTO PLAN DE ACCIÓN
    public function vista_seguimiento($idlistEject,$idPlanAccion,$tipoPlanAccion)
    {
        //REALIZAR VALIDACIONES PARA QUE NO CUALQUIERA PUEDA ENTRAR ACÁ POR LA URL

        // FIN 
        $DatosDevueltos = [];
        switch ($tipoPlanAccion) {
            case '1': // PLAN DE ACCIÓN AUTOMATICO
                $informacionListaChequeoSeccion = $this->TraerInformacionListaChequeoPlanAccion($idlistEject,$idPlanAccion);
                $trarInformacionPlanAccionSegundaSeccion = $this->TraerInformacionPlanAccionAutomatico($idlistEject,$idPlanAccion);
                $trarInformacionFotosPlanAccionTerceraSeccion = $this->TraerEvidenciasFotosPlanAccion($idlistEject,$idPlanAccion);
                $trarInformacionAdjuntosPlanAccionCuartaSeccion = $this->TraerEvidenciasAdjuntosPlanAccion($idlistEject,$idPlanAccion);

                break;

            case '2': // PLAN DE ACCIÓN MANUAL
                $informacionListaChequeoSeccion = $this->TraerInformacionListaChequeoPlanAccion($idlistEject,$idPlanAccion);
                $trarInformacionPlanAccionSegundaSeccion = $this->TraerInformacionPlanAccionManual($idlistEject,$idPlanAccion);
                $trarInformacionFotosPlanAccionTerceraSeccion = $this->TraerEvidenciasFotosPlanAccion($idlistEject,$idPlanAccion);
                $trarInformacionAdjuntosPlanAccionCuartaSeccion = $this->TraerEvidenciasAdjuntosPlanAccion($idlistEject,$idPlanAccion);

                break;
                
            default:
                return redirect('/listachequeo/planaccion');
                break;
        }

        $DatosDevueltos = [
            'informacionListaChequeoSeccion' => $informacionListaChequeoSeccion,
            'trarInformacionPlanAccionSegundaSeccion' => $trarInformacionPlanAccionSegundaSeccion,
            'trarInformacionFotosPlanAccionTerceraSeccion' => $trarInformacionFotosPlanAccionTerceraSeccion,
            'trarInformacionAdjuntosPlanAccionCuartaSeccion' => $trarInformacionAdjuntosPlanAccionCuartaSeccion,
            'tipoDePlanAccion' => \Request::segment(6)
        ];

        return view('Admin.listachequeo_seguimiento_planaccion',compact('DatosDevueltos'));
    }

    public function TraerInformacionListaChequeoPlanAccion($idlistEject,$idPlanAccion)
    {
        $trarInformacionListaChequeo = \DB::select('SELECT 
            DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION,
            em.nombre AS EMPRESA,
            esta.nombre AS ESTABLECIMIENTO,
            us.nombre_completo AS EVALUADOR,
            pr.nombre AS ITEM
            FROM plan_accion pa
            INNER JOIN lista_chequeo_ejec_respuestas lcer ON lcer.pregunta_id = pa.pregunta_id
            INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
            INNER JOIN usuario us ON us.id = lce.usuario_id
            INNER JOIN establecimiento esta ON esta.id = us.establecimiento_id
            INNER JOIN empresa em ON em.id = esta.empresa_id
            INNER JOIN pregunta pr ON pr.id = pa.pregunta_id
            WHERE pa.id = :idPlanAccion AND lce.id = :idlistEject;',
            ['idPlanAccion' => $idPlanAccion,
            'idlistEject' => $idlistEject]
        );

        return $trarInformacionListaChequeo[0];
    }

    public function TraerInformacionPlanAccionManual($idlistEject,$idPlanAccion)
    {
            $trarInformacionPlanAccionManual = \DB::select('SELECT 
            pamo.opcion AS OPCION,
            IF((pamd.plan_accio_man_opc_id = 8 OR pamd.plan_accio_man_opc_id = 5),
                (SELECT uss.nombre_completo FROM usuario AS uss WHERE uss.id=pamd.respuesta),
            pamd.respuesta 
            ) AS RESPUESTA_OPCION
            FROM plan_accion pa
            INNER JOIN lista_chequeo_ejec_respuestas lcer ON lcer.pregunta_id = pa.pregunta_id
            INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
            INNER JOIN pregunta pr ON pr.id = pa.pregunta_id
            INNER JOIN plan_accion_manu_det pamd ON pamd.lista_cheq_ejec_respuesta_id = lcer.id
            INNER JOIN plan_accion_man_opc pamo ON pamo.id = pamd.plan_accio_man_opc_id
            WHERE pa.id = :idPlanAccion AND lce.id = :idlistEject;',
            ['idPlanAccion' => $idPlanAccion,
            'idlistEject' => $idlistEject]
        );

        return $trarInformacionPlanAccionManual;
    }

    public function TraerEvidenciasFotosPlanAccion($idlistEject,$idPlanAccion)
    {
        $trarInformacionFotosPlanAccionManual = \DB::select('SELECT 
        CONCAT("imagenes/listas_chequeo/",lcef.foto) AS FOTO
        FROM plan_accion pa
        INNER JOIN lista_chequeo_ejec_respuestas lcer ON lcer.pregunta_id = pa.pregunta_id
        INNER JOIN lista_chequeo_ejec_fotos lcef ON lcef.lista_chequeo_ejec_respuestas = lcer.id
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
        INNER JOIN pregunta pr ON pr.id = pa.pregunta_id
        WHERE pa.id = :idPlanAccion AND lce.id = :idlistEject;',
            ['idPlanAccion' => $idPlanAccion,
            'idlistEject' => $idlistEject]
        );

        return $trarInformacionFotosPlanAccionManual;
    }

    public function TraerEvidenciasAdjuntosPlanAccion($idlistEject,$idPlanAccion)
    {
        $trarInformacionAdjuntosPlanAccionManual = \DB::select('SELECT 
        lcea.id ID_ADJUNTO,
        lcea.archivo_codificado AS ADJUNTO,
        lcea.archivo_alias AS ALIAS
        FROM plan_accion pa
        INNER JOIN lista_chequeo_ejec_respuestas lcer ON lcer.pregunta_id = pa.pregunta_id
        INNER JOIN lista_chequeo_ejec_archivos lcea ON lcea.lista_chequeo_ejec_respuesta_id = lcer.id
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
        INNER JOIN pregunta pr ON pr.id = pa.pregunta_id
        WHERE pa.id = :idPlanAccion AND lce.id = :idlistEject;',
            ['idPlanAccion' => $idPlanAccion,
            'idlistEject' => $idlistEject]
        );

        foreach ($trarInformacionAdjuntosPlanAccionManual as $key => $itemAdjunto) 
        {
            $path = \Storage::disk('public')->path($itemAdjunto->ADJUNTO);
            $extensions = explode('.', $path);
            switch (end($extensions)) 
            {
                case 'pdf':
                    $itemAdjunto->ICONO = "mdi-file-pdf";
                    break;

                case 'docx':
                    $itemAdjunto->ICONO = "mdi-file-word-box";
                    break;

                case 'jpeg':
                case 'png':
                case 'jpg':
                    $itemAdjunto->ICONO = "mdi-image";
                    break;
                
                case 'xlsx':
                case 'xls':
                    $itemAdjunto->ICONO = "mdi-file-excel-box";
                    break;
                    
                default:
                    $itemAdjunto->ICONO = "mdi-file";
                    break;
            }
        }

        return $trarInformacionAdjuntosPlanAccionManual;
    }

    public function TraerInformacionPlanAccionAutomatico($idlistEject,$idPlanAccion)
    {
        $trarInformacionPlanAccionAutomatico = \DB::select('SELECT 
            paa.plan_accion_descripcion AS RESPUESTA_PLAN_ACCION
            FROM plan_accion pa
            INNER JOIN lista_chequeo_ejec_respuestas lcer ON lcer.pregunta_id = pa.pregunta_id
            INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
            INNER JOIN plan_accion_automatico paa ON paa.plan_accion_id = pa.id
            WHERE pa.id = :idPlanAccion AND lce.id = :idlistEject;',
            ['idPlanAccion' => $idPlanAccion,
            'idlistEject' => $idlistEject]
        );

        return $trarInformacionPlanAccionAutomatico;
    }

    public function guardarSeguimiento(Request $request)
    {
        $idListaEject = $request->get('idListaEject');
        $idPlanDeAccion = $request->get('idPlanDeAccion');
        $tipoPlanAccion = $request->get('tipoPlanAccion');
        $estado = $request->get('estado');
        $descripcion = $request->get('descripcion');

        $fileName = ''; 
        $originalName = ''; 

        $arrayInsertar = [
            'usuario_id' => auth()->user()->id,
            'observacion' => $descripcion,
            'estado' => $estado,
            'plan_accion_id' => $idPlanDeAccion
        ];

        $planAccionSeguimiento = new $this->planAccionSeguimiento;
        $planAccionSeguimiento->fill($arrayInsertar);

        if($planAccionSeguimiento->save())
        {
            if($tipoPlanAccion == 2) // PLAN ACCIÓN MANUAL
            {
                $estadoTexto = '';
                switch ($estado) {
                    case 1: // ABIERTO
                        $estadoTexto = 'Abierto';
                        break;

                    case 2: // EN PROCESO
                        $estadoTexto = 'En proceso';
                        break;

                    case 3: // CERRADO
                        $estadoTexto = 'Cerrado';
                        break;
                    
                    default:
                        break;
                }

                $arrayCorreos = [];

                $planDeAccion = \DB::table('lista_chequeo_ejec_respuestas')
                ->select('pamd.*')
                ->Join('plan_accion_manu_det AS pamd','pamd.lista_cheq_ejec_respuesta_id','=','lista_chequeo_ejec_respuestas.id')
                ->Join('plan_accion AS pa','pa.pregunta_id','=','lista_chequeo_ejec_respuestas.pregunta_id')
                ->where([
                    ['lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id','=', $idListaEject],
                    ['pa.tipo_pa','=', 2],
                    ['pamd.plan_accio_man_opc_id','=', 8],
                    ['pa.id','=', $idPlanDeAccion]
                ])
                ->first();

                if(!is_null($planDeAccion))
                {
                    $usuario = \DB::table('usuario')->where('id','=',$planDeAccion->respuesta)->first();
                    array_push($arrayCorreos, $usuario->correo);
                }

                $planDeAccionQuienHara = \DB::table('lista_chequeo_ejec_respuestas')
                ->select('pamd.*')
                ->Join('plan_accion_manu_det AS pamd','pamd.lista_cheq_ejec_respuesta_id','=','lista_chequeo_ejec_respuestas.id')
                ->Join('plan_accion AS pa','pa.pregunta_id','=','lista_chequeo_ejec_respuestas.pregunta_id')
                ->where([
                    ['lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id','=', $idListaEject],
                    ['pa.tipo_pa','=', 2],
                    ['pamd.plan_accio_man_opc_id','=', 5],
                    ['pa.id','=', $idPlanDeAccion]
                ])
                ->first();

                if(!is_null($planDeAccionQuienHara))
                {
                    $usuarioQuienHara = \DB::table('usuario')->where('id','=',$planDeAccionQuienHara->respuesta)->first();
                    array_push($arrayCorreos, $usuarioQuienHara->correo);
                }

                $datosEvaluador = \DB::table('lista_chequeo_ejecutadas')
                ->select('us.*')
                ->Join('usuario AS us','us.id','=','lista_chequeo_ejecutadas.usuario_id')
                ->where([
                    ['lista_chequeo_ejecutadas.id','=', $idListaEject],
                ])
                ->first();

                array_push($arrayCorreos, $datosEvaluador->correo);
                
                if(COUNT($arrayCorreos) != 0)
                    \Mail::to($arrayCorreos)->send(new MailCambioSeguimientoPlanAccionManual($estadoTexto,auth()->user()->id));
            }
            
            return $this->FinalizarRetorno(
                205,
                $this->MensajeRetorno('',205,'Seguimiento guardado correctamente'),
                $planAccionSeguimiento->id
            );
        }

        
    }

    public function guardarSeguimientoDetalle(Request $request)
    {
        $archivos = $request->file();
        $idSeguimiento = $request->get('idSeguimiento');

        foreach($archivos as $key => $archivo)
        {
            $fileName = $archivo->hashName();
            $originalName = $archivo->getclientOriginalName();

            $arrayInsertarDetalle = [
                'archivo' => $fileName,
                'descripcion' => $originalName,
                'id_plan_accion_seguimiento' => $idSeguimiento
            ];
    
            $planAccionSeguimientoDetalle = new $this->planAccionSeguimientoDetalle;
            $planAccionSeguimientoDetalle->fill($arrayInsertarDetalle);
    
            if($planAccionSeguimientoDetalle->save())
                $path = \Storage::putFile('public', $archivo);
        }

        // $this->depurarArchivosAdjuntosServer();

        return $this->FinalizarRetorno(
            204,
            $this->MensajeRetorno('',204,'Detalle seguimiento guardado correctamente')
        );
    }

    public function cargarSeguimientos(Request $request)
    {
        $idPlanDeAccion = $request->get('idPlanDeAccion');

        $traerSeguimientos = \DB::select('SELECT 
        pas.id AS ID_SEGUIMIENTO,
        (CASE
            WHEN pas.estado = 1 THEN "Abierto"
            WHEN pas.estado = 2 THEN "En proceso"
            WHEN pas.estado = 3 THEN "Cerrado"
            ELSE ""
        END) AS ESTADO,
        pas.estado AS ESTADO_NUMERO,
        DATE_FORMAT(pas.fecha_registro,"%d %M del %Y") AS FECHA,
        us.nombre_completo AS USUARIO,
        IF(ca.nombre IS NULL, "Sin cargo",ca.nombre) AS CARGO,
        IF(pas.observacion IS NULL, "Sin observación",pas.observacion) AS OBSERVACION,
        pasd.id AS ID_ADJUNTO,
        pasd.archivo AS ARCHIVO,
        pasd.descripcion AS NOMBRE_REAL
        FROM plan_accion_seguimiento pas
        INNER JOIN plan_accion pa ON pa.id = pas.plan_accion_id
        INNER JOIN usuario us ON us.id = pas.usuario_id
        LEFT JOIN cargo ca ON ca.id = us.cargo_id
        LEFT JOIN plan_accion_seguimiento_detalle pasd ON pasd.id_plan_accion_seguimiento = pas.id
        WHERE pas.plan_accion_id = :idPlanDeAccion;',
        ['idPlanDeAccion' => $idPlanDeAccion]);

        $arrayLimpio = [];
        foreach ($traerSeguimientos as $key => $itemSeguimiento) 
        {
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['ID_SEGUIMIENTO'] = $itemSeguimiento->ID_SEGUIMIENTO;
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['ESTADO'] = $itemSeguimiento->ESTADO;
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['ESTADO_NUMERO'] = $itemSeguimiento->ESTADO_NUMERO;
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['FECHA'] = $itemSeguimiento->FECHA;
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['USUARIO'] = $itemSeguimiento->USUARIO;
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['CARGO'] = $itemSeguimiento->CARGO;
            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['OBSERVACION'] = $itemSeguimiento->OBSERVACION;

            $path = \Storage::disk('public')->path($itemSeguimiento->ARCHIVO);
            $iconoClase = $this->ValidarExtension($path);

            $arrayArchivos = [
                'NOMBRE_ARCHIVO' => $itemSeguimiento->ARCHIVO, 
                'ICONO' => $iconoClase,
                'NOMBRE_REAL' => explode('.', $itemSeguimiento->NOMBRE_REAL)[0],
                'ID_ADJUNTO' => $itemSeguimiento->ID_ADJUNTO
            ];

            $arrayLimpio[$itemSeguimiento->ID_SEGUIMIENTO]['ADJUNTOS'][] = $arrayArchivos;
        }

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Seguimientos',202),
            $arrayLimpio
        );
    }

    private function depurarArchivosAdjuntosServer()
    {
        $adjuntos = $this->planAccionSeguimientoDetalle->all();
        foreach($adjuntos as $kek => $value)
        {
            $exists = \Storage::disk('public')->exists($value->archivo);

                if($exists == false)
                    $adjuntos->find($value->id)->delete();
        }
    }

    public function descargarAdjuntoSeguimiento($id){

        $idFile = $id;
        $adjunto = $this->planAccionSeguimientoDetalle->find($idFile);

        $exists = \Storage::disk('public')->exists($adjunto->archivo);

        if($exists){

            $path = \Storage::disk('public')->path($adjunto->archivo);

            return response()->download($path, $adjunto->descripcion);

        }else{


        }
    }

    private function ValidarExtension($path)
    {
        $extensions = explode('.', $path);
        $extension = "";
        switch (end($extensions)) 
        {
            case 'pdf':
                $extension = "mdi-file-pdf";
                break;

            case 'docx':
                $extension = "mdi-file-word-box";
                break;

            case 'jpeg':
            case 'png':
            case 'jpg':
                $extension = "mdi-image";
                break;
            
            case 'xlsx':
            case 'xls':
                $extension = "mdi-file-excel-box";
                break;
                
            default:
                $extension = "mdi-file";
                break;
        }

        return $extension;
    }

    //PLAN DE ACCIÓN MANUAL
    public function IndexPlanAccionManual()
    {
        
        $filtrar = NULL;
        if(!is_null(\Request::segment(3)))
        {
            if($this->planAccionEjecucion->where('id', '=',\Request::segment(3))->exists())
            {
                $filtrar = \Request::segment(3);
            }
        }
        

        $listaChequeo = $this->FuncionParaTraerListasChequeoPlanAccionManual();
        $evaluados = $this->FuncionParaTraerEvaluadoPlanAccionManual();
        $evaluadores = $this->FuncionParaTraerEvaluadorPlanAccionManual();
        $empresas = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            \DB::raw("(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS id"),
            \DB::raw("(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS nombre")
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
        ->groupByRaw("(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END)");

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $planAccionEnListar = $this->FuncionTraerPlanDeAccionAdministradorEnListar(1);    
                $empresas = $empresas->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ]
                )->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                {
                    $planAccionEnListar = $this->FuncionTraerPlanDeAccionColaboradorResponsableEmpresaEnListar($esResponsableEmpresa->id,1);

                    $empresas = $empresas->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();
                }
                    

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                {
                    $empresas = $empresas->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                    $planAccionEnListar = $this->FuncionTraerPlanDeAccionColaboradorResponsableEstablecimientoEnListar($esResponsableEstablecimiento->id,1);
                }
                    

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                {
                    $empresas = $empresas->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                    $planAccionEnListar = $this->FuncionTraerPlanDeAccionColaboradorEnListar(auth()->user()->id,1);
                }
                break;
            
            default:

                break;
        }


        return view('Admin.listachequeo_plan_accion_manual',compact('listaChequeo','evaluados','evaluadores','planAccionEnListar','filtrar','empresas'));
    }

    public function ConsultarPlanesDeAccionManual(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        
        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $planesAccion = $this->FuncionTraerPlanDeAccionAdministrador($paginacion,$arrayFiltros,1);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $planesAccion = $this->FuncionTraerPlanDeAccionColaboradorResponsableEmpresa($esResponsableEmpresa->id,$paginacion,$arrayFiltros,1);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $planesAccion = $this->FuncionTraerPlanDeAccionColaboradorResponsableEstablecimiento($esResponsableEstablecimiento->id,$paginacion,$arrayFiltros,1);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $planesAccion = $this->FuncionTraerPlanDeAccionColaborador(auth()->user()->id,$paginacion,$arrayFiltros,1);

                break;
            
            default:

                break;
        }

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Planes acción',202),
            $planesAccion
        );
    }

    public function descargaExcelPlanAccionManual(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');
        $filtros = json_decode($request->get('filtros_busqueda'));
        return \Excel::download(new PlanAccionExports($filtros,$this->listaChequeoEjecRespuesta,1), 'informe_plan_accion_manual.xlsx');
    }

    public function FuncionParaTraerListasChequeoPlanAccionManual()
    {
        $traerPlanAcciones = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
           'lc.id',
           'lc.nombre'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
        ->groupBy('lc.id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $traerPlanAcciones = $traerPlanAcciones->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ]
                )->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                break;
            
            default:

                break;
        }

        return $traerPlanAcciones;
    }
    
    public function FuncionParaTraerEvaluadoPlanAccionManual()
    {
        $traerPlanAcciones = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
           \DB::raw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as evaluado'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as entidad_evaluada')
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
        ->groupByRaw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END)');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $traerPlanAcciones = $traerPlanAcciones->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ]
                )->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $traerPlanAcciones = $traerPlanAcciones->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                break;
            
            default:

                break;
        }

        return $traerPlanAcciones;
    }

    public function FuncionParaTraerEvaluadorPlanAccionManual()
    {
        $evaluadores = \DB::table('lista_chequeo_ejec_respuestas')
        ->select(
            'us.nombre_completo AS evaluador',
            'us.id'
        )
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lista_chequeo_ejec_respuestas.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->Join('plan_accion_manual AS pam','pam.plan_accion_id','=','pa.id')
        ->groupBy('us.id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $evaluadores = $evaluadores->where([
                    ['us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id],
                    ['lce.estado','=',2]
                ]
                )->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $evaluadores = $evaluadores->where([
                        ['empe.id','=',$esResponsableEmpresa->id],
                        ['lce.estado','=',2]
                    ])->get();

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where([
                        ['esta.id','=',$esResponsableEstablecimiento->id],
                        ['lce.estado','=',2]
                    ])->get();

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where([
                        ['us.id','=',auth()->user()->id],
                        ['lce.estado','=',2]
                    ])->get();

                break;
            
            default:

                break;
        }

        return $evaluadores;
    }


    //PLAN ACCIÓN HALLAZGOS
    public function IndexHallazgosPlanAccion()
    {
        $listaPlanAccionHallazgos = $this->FuncionParaTraerListasChequeoPlanAccionHallazgos();
        $evaluados = $this->FuncionParaTraerHallazgosEvaluadoPlanAccion();
        $evaluadores = $this->FuncionParaTraerHallazgosEvaluadorPlanAccion();

        $empresas = \DB::table('plan_accion_manu_det')
        ->select(
            \DB::raw("(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS id"),
            \DB::raw("(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lce.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS nombre")
        )
        ->Join('lista_chequeo_ejec_respuestas AS lcer','lcer.id','=','plan_accion_manu_det.lista_cheq_ejec_respuesta_id')
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lcer.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lcer.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lcer.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lcer.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->groupByRaw("(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END)")
        ->where([
            ['plan_accion_manu_det.respuesta','=', auth()->user()->id],
            ['lce.estado','=',2],
            ['plan_accion_manu_det.plan_accio_man_opc_id','=',8]
        ])
        ->get();

        
        return view('Admin.plan_accion_hallazgos',compact('listaPlanAccionHallazgos','evaluados','evaluadores','empresas'));
    }

    public function TraerHallazgos(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));

        // $planesAccionHallazgos = $this->FuncionTraerHallazgosPlanDeAccionColaborador(auth()->user()->id,$paginacion,$arrayFiltros);
        $planesAccionAuto = $this->FuncionTraerPlanDeAccionAdministrador($paginacion,$arrayFiltros, 0); //AUTO
        $planesAccionManu = $this->FuncionTraerPlanDeAccionAdministrador($paginacion,$arrayFiltros, 1); //MANUAL

        $planesAccionHallazgos = array(
            'cantidadTotal' => $planesAccionAuto['cantidadTotal'] + $planesAccionManu['cantidadTotal'],
            'planesAccion' => array_merge($planesAccionAuto['planesAccion']->toArray(), $planesAccionManu['planesAccion']->toArray())
        );

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Planes acción hallazgos',202),
            $planesAccionHallazgos
        );
    }

    public function FuncionTraerHallazgosPlanDeAccionColaborador($idUsuario,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];
        $whereRaw = 0;
        $whereRawEvaluado = 0;
        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_realizacion':
                    if($filtro != '')
                    {
                        $dato = Carbon::createFromFormat('d/m/Y', $filtro);
                        $dato = $dato->format('Y-m-d');
                        array_push($filtro_array,['lce.fecha_realizacion', '=', $dato]);
                    }
                    break;

                case 'filtro_lista_chequeo':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if($filtro != '')
                        // array_push($filtro_array,['lc.entidad_evaluada', '=', $filtro]);
                        $whereRawEvaluado = $filtro;
                    break;

                case 'filtro_evaluador':
                    if($filtro != '')
                        array_push($filtro_array,['us.id', '=', $filtro]);
                    break;

                case 'filtro_codigo':
                    if($filtro != '')
                        array_push($filtro_array,['lcep.id', '=', $filtro]);
                    break;

                case 'filtro_empresa':
                    if($filtro != '')
                        $whereRaw = $filtro;
                    break;

                default:
                    
                    break;
            }
            
        }

        $traerPlanAcciones = \DB::table('plan_accion_manu_det')
        ->select(
            'lcep.id AS CODIGO_PLAN_ACCION',
            \DB::raw('IF((SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1) IS NULL, "Abierto",
            (CASE
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=1 THEN "Abierto"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=2 THEN "En proceso"
                WHEN (SELECT pass.estado FROM plan_accion_seguimiento pass WHERE pass.plan_accion_id = pa.id ORDER BY pass.id DESC LIMIT 1)=3 THEN "Cerrado"
                ELSE "Error"
            END)) AS ESTADO'),
            'lceo.id AS ID_EJECT_OPCIONES',
            \DB::raw('DATE_FORMAT(lce.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION'),
            'pa.id AS ID_PLAN_ACCION',
            'lc.nombre',
            \DB::raw('(CASE
                        WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) as EMPRESA'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                ELSE "Error"
            END) as evaluado'),
            'us.nombre_completo AS evaluador',
            'lce.id as ejecutada_id',
            'pre.id as pregunta_id',
            'pre.nombre as pregunta',
            \DB::raw("IF(lceo.comentario IS NULL, 'Sin observaciones', lceo.comentario)AS OBSERVACION"),
            \DB::raw('IF(res.valor_personalizado IS NULL, "No aplica",res.valor_personalizado) as respuesta'),
            'pa.tipo_pa as tipo_plan_accion'
        )
        ->Join('lista_chequeo_ejec_respuestas AS lcer','lcer.id','=','plan_accion_manu_det.lista_cheq_ejec_respuesta_id')
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lcer.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lcer.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lcer.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lcer.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->where([
            ['plan_accion_manu_det.respuesta','=',$idUsuario],
            ['lce.estado','=',2],
            ['plan_accion_manu_det.plan_accio_man_opc_id','=',8]
        ])
        ->orderBy('lce.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->where(function($query) use ($filtro_array)
            {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) 
                {
                    // if( $contador == 0)
                    //     $query->where($oW[0], '=', $oW[2]);
                    // else
                    // {
                    //     $query->orWhere($oW[0], '=', $oW[2]);
                    // }
                    $query->where($oW[0], '=', $oW[2]);

                    // $contador = $contador + 1;
                }

                return $query;
            });
            
        }

        if($whereRaw != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                    WHERE susu.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                    ELSE "Error"
                END) = ?',[$whereRaw]);

        }

        if($whereRawEvaluado != 0)
        {
            $traerPlanAcciones = $traerPlanAcciones->whereRaw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                        ELSE "Error"
                    END) = ?',[$whereRawEvaluado]);
        }

        $rango = $traerPlanAcciones->paginate($cantidadRegistros)->lastPage();
        $traerPlanAcciones = $traerPlanAcciones->skip($desde)->take($hasta)->get();

        return array(
            'cantidadTotal' => $rango,
            'planesAccion' => $traerPlanAcciones
        );
    }

    public function FuncionParaTraerListasChequeoPlanAccionHallazgos()
    {
        $traerPlanAcciones = \DB::table('plan_accion_manu_det')
        ->select(
            'lc.nombre',
            'lc.id'
        )
        ->Join('lista_chequeo_ejec_respuestas AS lcer','lcer.id','=','plan_accion_manu_det.lista_cheq_ejec_respuesta_id')
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lcer.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lcer.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lcer.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lcer.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->where([
            ['plan_accion_manu_det.respuesta','=', auth()->user()->id],
            ['lce.estado','=',2],
            ['plan_accion_manu_det.plan_accio_man_opc_id','=',8]
        ])
        ->groupBy('lc.id')
        ->get();

        return $traerPlanAcciones;
    }

    public function FuncionParaTraerHallazgosEvaluadoPlanAccion()
    {
        $traerPlanAcciones = \DB::table('plan_accion_manu_det')
        ->select(
            \DB::raw('(CASE
                     WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
                         WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                         WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lce.evaluado_id)
                         WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lce.evaluado_id)
                         WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                         ELSE "Error"
                     END) as evaluado'),
             \DB::raw('(CASE
             WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
                 WHEN lc.entidad_evaluada=2 THEN (SELECT sest.id FROM establecimiento sest WHERE sest.id=lce.evaluado_id)
                 WHEN lc.entidad_evaluada=3 THEN (SELECT susu.id FROM usuario susu WHERE susu.id=lce.evaluado_id)
                 WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=lce.evaluado_id)
                 WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
                 ELSE "Error"
             END) as entidad_evaluada')
         )
        ->Join('lista_chequeo_ejec_respuestas AS lcer','lcer.id','=','plan_accion_manu_det.lista_cheq_ejec_respuesta_id')
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lcer.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lcer.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lcer.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lcer.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->where([
            ['plan_accion_manu_det.respuesta','=', auth()->user()->id],
            ['lce.estado','=',2],
            ['plan_accion_manu_det.plan_accio_man_opc_id','=',8]
        ])
        ->groupBy('lc.id')
        ->get();

        return $traerPlanAcciones;
    }

    public function FuncionParaTraerHallazgosEvaluadorPlanAccion()
    {
        $traerPlanAcciones = \DB::table('plan_accion_manu_det')
        ->select(
            'us.nombre_completo AS evaluador',
            'us.id'
        )
        ->Join('lista_chequeo_ejec_respuestas AS lcer','lcer.id','=','plan_accion_manu_det.lista_cheq_ejec_respuesta_id')
        ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lcer.lista_chequeo_ejec_id')
        ->Join('lista_chequeo AS lc','lc.id','=','lce.lista_chequeo_id')
        ->Join('usuario AS us','us.id','=','lce.usuario_id')
        ->Join('establecimiento AS esta','esta.id','=','us.establecimiento_id')
        ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        ->Join('pregunta AS pre','pre.id','=','lcer.pregunta_id')
        ->Join('respuesta AS res','res.id','=','lcer.respuesta_id')
        ->Join('lista_chequeo_ejec_opciones AS lceo','lceo.lista_chequeo_ejec_respuestas_id','=','lcer.id')
        ->Join('lista_chequeo_ejec_planaccion AS lcep','lcep.lista_chequeo_ejec_opciones','=','lceo.id')
        ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
        ->where([
            ['plan_accion_manu_det.respuesta','=', auth()->user()->id],
            ['lce.estado','=',2],
            ['plan_accion_manu_det.plan_accio_man_opc_id','=',8]
        ])
        ->groupBy('lc.id')
        ->get();

        return $traerPlanAcciones;
    }

    public function descargaExcelHallazgos(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');
        $filtros = json_decode($request->get('filtros_busqueda'));

        return \Excel::download(new PlanAccionHallazgosExports($filtros,auth()->user()->id), 'informe_plan_accion_hallazgos.xlsx');
    }
}
