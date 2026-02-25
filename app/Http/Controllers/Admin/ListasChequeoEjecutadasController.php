<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\ListaChequeoEjecutadas;
use App\Http\Models\ListaChequeoEjecutadasFotos;
use App\Http\Models\PreguntaOpcionRespuesta;
use App\Http\Models\Respuesta;
use App\Http\Models\ListaChequeoEjecutadasRespuestas;
use App\Http\Models\TipoRespuestaPonderadoPredeterminado;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use App\Exports\DetalleListaChequeo;
use App\Http\Models\ListaChequeoEjecutadasArchivos;
use App\Http\Models\PlanAccionManuDetalle;

class ListasChequeoEjecutadasController extends Controller
{
    protected $empresa,$establecimiento,$listaEjecutada,$ejecutadasFotos,$preguntaOpcionRespuesta,$respuesta,$respuestaPredeterminada, $ejecutadasAdjuntos;
    public function __construct(
        ListaChequeoEjecutadas $listaEjecutada,
        ListaChequeoEjecutadasFotos $ejecutadasFotos,
        PreguntaOpcionRespuesta $preguntaOpcionRespuesta,
        Respuesta $respuesta,
        ListaChequeoEjecutadasRespuestas $ejecutadasRespuestas,
        TipoRespuestaPonderadoPredeterminado $respuestaPredeterminada,
        Empresa $empresa,
        Establecimiento $establecimiento,
        ListaChequeoEjecutadasArchivos $ejecutadasAdjuntos,
        PlanAccionManuDetalle $planAccionManualDetalle
        )
    {
        $this->listaEjecutada = $listaEjecutada;
        $this->ejecutadasFotos = $ejecutadasFotos;
        $this->preguntaOpcionRespuesta = $preguntaOpcionRespuesta;
        $this->respuesta = $respuesta;
        $this->ejecutadasRespuestas = $ejecutadasRespuestas;
        $this->respuestaPredeterminada = $respuestaPredeterminada;
        $this->empresa = $empresa;
        $this->establecimiento = $establecimiento;
        $this->ejecutadasAdjuntos = $ejecutadasAdjuntos;
        $this->planAccionManualDetalle = $planAccionManualDetalle;

        \DB::statement("SET lc_time_names = 'es_ES'");
        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $listasEjecutadas = $this->listaEjecutada
                ->select('lc.*')
                ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
                ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
                ->where([
                    ['u.cuenta_principal_id', '=',auth()->user()->cuenta_principal_id]
                ])
                ->groupBy('lc.nombre')
                ->get();       
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                {
                    $listasEjecutadas = $this->listaEjecutada
                    ->select('lc.*')
                    ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
                    ->Join('usuario AS u','u.id','=','lc.usuario_id')
                    ->Join('establecimiento AS e','e.usuario_id','=','u.id')
                    ->Join('empresa AS em','em.id','=','e.empresa_id')
                    ->where([
                        ['em.id', '=', $esResponsableEmpresa->id]
                    ])
                    ->groupBy('lc.nombre')
                    ->get();
                }
                    

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                {
                    $listasEjecutadas = $this->listaEjecutada
                    ->select('lc.*')
                    ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
                    ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
                    ->Join('establecimiento AS e','e.usuario_id','=','u.id')
                    ->where([
                        ['e.id', '=',$esResponsableEstablecimiento->id]
                    ])
                    ->groupBy('lc.nombre')
                    ->get();
                }
                    

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                {
                    $listasEjecutadas = $this->listaEjecutada
                    ->select('lc.*')
                    ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
                    ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
                    ->where([
                        ['u.id', '=',auth()->user()->id]
                    ])
                    ->groupBy('lc.nombre')
                    ->get();
                }
                    

                break;
            
            default:

                break;
        };   
        return view('Admin.listachequeo_mis_listas_ejecutadas',compact('listasEjecutadas'));
    }

    public function ConsultaListasEjecutadas(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionAdministrado($paginacion,$filtros);        
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionResponsableEmpresa($esResponsableEmpresa->id,$paginacion,$filtros);        

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionResponsableEstablecimiento($esResponsableEstablecimiento->id,$paginacion,$filtros);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionColaborador(auth()->user()->id,$paginacion,$filtros);

                break;
            
            default:

                break;
        }

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $listasEjecutadas
        );
    }

    public function TraerEjecutadasPaginacion(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionAdministrado($paginacion,$filtros);        
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionResponsableEmpresa($esResponsableEmpresa->id,$paginacion,$filtros);        

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionResponsableEstablecimiento($esResponsableEstablecimiento->id,$paginacion,$filtros);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $listasEjecutadas = $this->FuncionTraerEjecutadasPorPaginacionColaborador(auth()->user()->id,$paginacion,$filtros);

                break;
            
            default:

                break;
        };

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $listasEjecutadas
        );
    }   

    public function FuncionTraerEjecutadasPorPaginacionAdministrado($paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion,12);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 12;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_nombre_auditoria':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasEjecutadas = $this->listaEjecutada
        ->select(
            'lc.id AS ID_LISTA_CHEQUEO',
            'lc.nombre AS NOMBRE_LISTA_CHEQUEO',
            'u.nombre_completo AS NOMBRE_USUARIO_EJECUTO',
            'lista_chequeo_ejecutadas.*',
            \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y %h:%i %p") AS FECHA_EJECUCION'),
            \DB::raw('
            IF(lista_chequeo_ejecutadas.evaluado_id IS NULL,"Sin Asignar",
                (CASE    
                    WHEN lc.entidad_evaluada = 1 THEN (SELECT nombre FROM empresa WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 2 THEN (SELECT nombre FROM establecimiento WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 3 THEN (SELECT nombre_completo FROM usuario WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 4 THEN (SELECT nombre FROM areas WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 5 THEN (SELECT nombre FROM equipos WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    ELSE "Sin asignar"
                END)
            ) AS EVALUADO_A
            ')
        )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->where([
            ['u.cuenta_principal_id', '=',auth()->user()->cuenta_principal_id]
        ])
        ->orderBy('lista_chequeo_ejecutadas.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $listasEjecutadas = $listasEjecutadas->where(function($query) use ($filtro_array)
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

        $listasEjecutadas = $listasEjecutadas->skip($desde)->take($hasta)->get();

        return $listasEjecutadas;
    }

    public function FuncionTraerEjecutadasPorPaginacionResponsableEmpresa($idEmpresa,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion,12);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 12;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_nombre_auditoria':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }
        
        $listasEjecutadas = $this->listaEjecutada
        ->select(
            'lc.id AS ID_LISTA_CHEQUEO',
            'lc.nombre AS NOMBRE_LISTA_CHEQUEO',
            'u.nombre_completo AS NOMBRE_USUARIO_EJECUTO',
            'lista_chequeo_ejecutadas.*',
            \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y") AS FECHA_EJECUCION'),
            \DB::raw('
            IF(lista_chequeo_ejecutadas.evaluado_id IS NULL,"Sin Asignar",
                (CASE    
                    WHEN lc.entidad_evaluada = 1 THEN (SELECT nombre FROM empresa WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 2 THEN (SELECT nombre FROM establecimiento WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 3 THEN (SELECT nombre_completo FROM usuario WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 4 THEN (SELECT nombre FROM areas WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 5 THEN (SELECT nombre FROM equipos WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    ELSE "Sin asignar"
                END)
            ) AS EVALUADO_A
            ')
        )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
        ->Join('empresa AS em','em.id','=','e.empresa_id')
        ->orWhere('em.id', '=',$idEmpresa)
        ->orWhere('lista_chequeo_ejecutadas.usuario_id', '=',auth()->user()->id)
        ->orderBy('lista_chequeo_ejecutadas.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $listasEjecutadas = $listasEjecutadas->where(function($query) use ($filtro_array)
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

        $listasEjecutadas = $listasEjecutadas->skip($desde)->take($hasta)->get();

        return $listasEjecutadas;
    }

    public function FuncionTraerEjecutadasPorPaginacionResponsableEstablecimiento($idEstablecimiento,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion,12);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 12;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_nombre_auditoria':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasEjecutadas = $this->listaEjecutada
        ->select(
            'lc.id AS ID_LISTA_CHEQUEO',
            'lc.nombre AS NOMBRE_LISTA_CHEQUEO',
            'u.nombre_completo AS NOMBRE_USUARIO_EJECUTO',
            'lista_chequeo_ejecutadas.*',
            \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y") AS FECHA_EJECUCION'),
            \DB::raw('
            IF(lista_chequeo_ejecutadas.evaluado_id IS NULL,"Sin Asignar",
                (CASE    
                    WHEN lc.entidad_evaluada = 1 THEN (SELECT nombre FROM empresa WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 2 THEN (SELECT nombre FROM establecimiento WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 3 THEN (SELECT nombre_completo FROM usuario WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 4 THEN (SELECT nombre FROM areas WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 5 THEN (SELECT nombre FROM equipos WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    ELSE "Sin asignar"
                END)
            ) AS EVALUADO_A
            ')
        )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
        ->where([
            ['e.id', '=',$idEstablecimiento]
        ])->orderBy('lista_chequeo_ejecutadas.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $listasEjecutadas = $listasEjecutadas->where(function($query) use ($filtro_array)
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

        $listasEjecutadas = $listasEjecutadas->skip($desde)->take($hasta)->get();

        return $listasEjecutadas;
    }

    public function FuncionTraerEjecutadasPorPaginacionColaborador($idUsuario,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion,12);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 12;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_nombre_auditoria':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasEjecutadas = $this->listaEjecutada
        ->select(
            'lc.id AS ID_LISTA_CHEQUEO',
            'lc.nombre AS NOMBRE_LISTA_CHEQUEO',
            'u.nombre_completo AS NOMBRE_USUARIO_EJECUTO',
            'lista_chequeo_ejecutadas.*',
            \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y") AS FECHA_EJECUCION'),
            \DB::raw('
            IF(lista_chequeo_ejecutadas.evaluado_id IS NULL,"Sin Asignar",
                (CASE    
                    WHEN lc.entidad_evaluada = 1 THEN (SELECT nombre FROM empresa WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 2 THEN (SELECT nombre FROM establecimiento WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 3 THEN (SELECT nombre_completo FROM usuario WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 4 THEN (SELECT nombre FROM areas WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 5 THEN (SELECT nombre FROM equipos WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    ELSE "Sin asignar"
                END)
            ) AS EVALUADO_A
            ')
        )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->where([
            ['u.id', '=',$idUsuario]
        ])->orderBy('lista_chequeo_ejecutadas.id','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $listasEjecutadas = $listasEjecutadas->where(function($query) use ($filtro_array)
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

        $listasEjecutadas = $listasEjecutadas->skip($desde)->take($hasta)->get();

        return $listasEjecutadas;
    }

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

    public function CambiarEstadoACancelada(Request $request)
    {
        $idEjecutada = $request->get('idEjecutada');

        $respuestaUpdate = $this->listaEjecutada->where('id','=',$idEjecutada)
        ->update(
        [
            'estado' => 0
        ]);

        $listasEjecutada = $this->listaEjecutada
        ->select(
            'lc.id AS ID_LISTA_CHEQUEO',
            'lc.nombre AS NOMBRE_LISTA_CHEQUEO',
            'u.nombre_completo AS NOMBRE_USUARIO_EJECUTO',
            'lista_chequeo_ejecutadas.*',
            \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y") AS FECHA_EJECUCION'),
            \DB::raw('
            IF(lista_chequeo_ejecutadas.evaluado_id IS NULL,"Sin Asignar",
                (CASE    
                    WHEN lc.entidad_evaluada = 1 THEN (SELECT nombre FROM empresa WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 2 THEN (SELECT nombre FROM establecimiento WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 3 THEN (SELECT nombre_completo FROM usuario WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 4 THEN (SELECT nombre FROM areas WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada = 5 THEN (SELECT nombre FROM equipos WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                    ELSE "Sin asignar"
                END)
            ) AS EVALUADO_A
            ')
        )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->where('lista_chequeo_ejecutadas.id', '=', $idEjecutada)->first();

        return $this->FinalizarRetorno(
            201,
            $this->MensajeRetorno('Lista chequeo actualizada',201),
            $listasEjecutada
        );
    }

    // FUNCIONES PARA EL DETALLE DE LA LISTA EJECUTADA
    
    public function IndexDetalleListaChequeo()
    {
        $idListaEjecutada = \Request::segment(3);
        
        if (!$this->listaEjecutada
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->where([
            ['lista_chequeo_ejecutadas.id', '=',$idListaEjecutada],
            ['lista_chequeo_ejecutadas.estado', '=', 2],
            ['u.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]
        ])->exists()) 
            return back();
        
        $seccionUno = $this->FuncionConsultaEncabezado($idListaEjecutada);
        $seccionDos = $this->FuncionConsultaCategoriaPreguntasSeccionDos($idListaEjecutada);
        $seccionTres = $this->FuncionConsultaCategoriaPreguntasSeccionTres($idListaEjecutada);
        $seccionCuatro = $this->FuncionConsultaCategoriaPreguntasSeccionCuatro($idListaEjecutada);
        $seccionQuinta = $this->FuncionConsultaCategoriaPreguntasSeccionQuinta($idListaEjecutada);
        $observacion_general = $this->listaEjecutada
        ->select(\DB::raw('IF(observacion_general IS NULL, "", observacion_general) AS OBS_GENERAL'))
        ->where('id', '=', $idListaEjecutada)
        ->first()->OBS_GENERAL;

        $revisado = $this->listaEjecutada
        ->select('revisado')
        ->where('id',  $idListaEjecutada)
        ->first()->revisado;

        return view('Admin.listachequeo_detalle',compact('seccionUno','seccionDos','seccionTres','seccionCuatro','seccionQuinta', 'observacion_general', 'idListaEjecutada', 'revisado'));
    }

    public function FuncionConsultaEncabezado($idListaEjecutada)
    {
        $seccionUno = $this->listaEjecutada
        ->select(
            \DB::raw("IF(lc.modelo_id IS NULL, 'Sin información',m.nombre) AS NOMBRE_MODELO"),
            'lc.nombre AS NOMBRE_LISTA_CHEQUEO',
            \DB::raw("(
                CASE
                     WHEN lc.publicacion_destino = 1 THEN 'Mi organización'
                     WHEN lc.publicacion_destino = 2 THEN 'Clientes'
                     WHEN lc.publicacion_destino = 3 THEN 'Organización y clientes'
                END
            ) AS PUBLICADO_EN"),
            \DB::raw("DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,'%d %M %Y %h:%i %p') AS FECHA_REALIZACION"),
            \DB::raw('IF(lista_chequeo_ejecutadas.evaluado_id IS NULL,"Sin Asignar",
                    (CASE    
                        WHEN lc.entidad_evaluada = 1 THEN (SELECT nombre FROM empresa WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada = 2 THEN (SELECT nombre FROM establecimiento WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada = 3 THEN (SELECT nombre_completo FROM usuario WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada = 4 THEN (SELECT nombre FROM areas WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada = 5 THEN (SELECT nombre FROM equipos WHERE id=lista_chequeo_ejecutadas.evaluado_id)
                        ELSE "Sin asignar"
                    END)
                ) AS EVALUADO_A'),
            'u.nombre_completo AS EVALUADOR'
            )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->leftJoin('modelo AS m','m.lista_chequeo_id','=','lc.id')
        ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->where([
            ['lista_chequeo_ejecutadas.estado','=','2'],
            ['lista_chequeo_ejecutadas.id','=', $idListaEjecutada]
        ])
        ->first();

        return $seccionUno; 
    }

    public function FuncionConsultaCategoriaPreguntasSeccionDos($idListaEjecutada)
    {
        $consultaResultadoFinal = \DB::select(\DB::raw("SELECT
        lcer.categoria_id,
        cat.nombre as categoria,
        lcer.no_aplica,
        pre.nombre as pregunta,
        cat.ponderado AS cat_ponderado,
        pre.ponderado AS pre_ponderado,
        res.valor_personalizado AS respuesta,
        res.ponderado AS res_ponderado,
        SUM(IF((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL,pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) AS res_final
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
        INNER JOIN categoria cat ON cat.id=pre.categoria_id
        WHERE  lcer.lista_chequeo_ejec_id=:idEjecutada
        ORDER BY cat.id;"),['idEjecutada' => $idListaEjecutada]);
        
        $suma = (COUNT($consultaResultadoFinal)== 0 ? 0 : $consultaResultadoFinal[0]->res_final);

        $seccionDos = \DB::select(\DB::raw("SELECT
        lcer.categoria_id,
        cat.nombre as categoria,
        lcer.no_aplica,
        pre.nombre as pregunta,
        cat.ponderado AS cat_ponderado,
        pre.ponderado AS pre_ponderado,
        res.valor_personalizado AS respuesta,
        res.ponderado AS res_ponderado,
        SUM(IF((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL,pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) AS porc_cat
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
        INNER JOIN categoria cat ON cat.id=pre.categoria_id
        WHERE  lcer.lista_chequeo_ejec_id=:idEjecutada
        GROUP BY cat.id
        ORDER BY cat.id;"),['idEjecutada' => $idListaEjecutada]);
        
        $arrayFinal = array
        (
            'Categorias' => $seccionDos,
            'ResultadoFinal' => $suma
        );


        return $arrayFinal;
    }
    
    public function FuncionConsultaCategoriaPreguntasSeccionTres($idListaEjecutada)
    {
        // $seccionTres = \DB::select(\DB::raw("SELECT 
        // p.id AS ID_PREGUNTA,
        // c.id AS ID_CATEGORIA,
        // c.nombre AS NOMBRE_CATEGORIA,
        // TRUNCATE(c.ponderado,0) AS PONDERAD_CATEGORIA,
        // p.nombre AS NOMBRE_PREGUNTA,
        // p.orden_lista AS ORDEN_PREGUNTA,
        // p.permitir_noaplica AS PERMITE_NO_APLICA,
        // (
        //     IF((SELECT COUNT(*) FROM lista_chequeo_ejec_fotos lcefs WHERE lcefs.lista_chequeo_ejec_respuestas=lcer.id) = 0,0,1)
        // ) AS HAY_FOTOS,
        // IF(lcer.id IS NULL,0,lcer.id) RESPUESTA_FOTOS,
        // IF(lcer.respuesta_id IS NULL,0,lcer.respuesta_id) RESPUESTA_ID,
        // IF(lcer.respuesta_id IS NULL,'NA',r.valor_personalizado) RESPUESTA_NOMBRE,
        // IF(lceo.comentario IS NULL,'Sin observación',lceo.comentario) AS COMENTARIO,
        // IF(lceo.plan_accion_id IS NULL,'Sin plan acción', (SELECT paas.plan_accion_descripcion FROM plan_accion_automatico paas WHERE paas.id=lceo.plan_accion_id)) AS PLAN_ACCION,
        // TRUNCATE(p.ponderado,0) AS PONDERADO_PREGUNTA
        // FROM lista_chequeo_ejecutadas lce
        // INNER JOIN lista_chequeo AS lc ON lc.id=lce.lista_chequeo_id
        // INNER JOIN categoria AS c ON c.lista_chequeo_id=lc.id
        // INNER JOIN pregunta AS p ON p.categoria_id=c.id
        // INNER JOIN lista_chequeo_ejec_respuestas AS lcer ON (lcer.lista_chequeo_ejec_id=lce.id AND lcer.pregunta_id=p.id)
        // LEFT JOIN respuesta AS r ON r.id=lcer.respuesta_id
        // LEFT JOIN lista_chequeo_ejec_opciones AS lceo ON lceo.lista_chequeo_ejec_respuestas_id=r.id
        // WHERE lce.estado = :idEstado 
        // AND lce.id = :idEjecutada 
        // ORDER BY p.orden_lista ASC"),['idEstado' => 2, 'idEjecutada' => $idListaEjecutada]);

         $seccionTres = \DB::select(\DB::raw("SELECT 
         pre.id AS ID_PREGUNTA,
         cat.id AS ID_CATEGORIA,
         cat.nombre as NOMBRE_CATEGORIA, 
         pre.orden_lista AS ORDEN_PREGUNTA,
         IF(lcer.respuesta_id IS NULL,0,lcer.respuesta_id) RESPUESTA_ID,
         lcer.no_aplica AS PERMITE_NO_APLICA,
         IF(lcer.respuesta_abierta IS NULL,0,1) AS ES_RESPUESTA_ABIERTA,
         lcer.respuesta_abierta AS RESPUESTA_ABIERTA,
         IF(lceo.comentario IS NULL,'Sin observación',lceo.comentario) AS COMENTARIO,
         -- IF(lceo.plan_accion_id IS NULL,'Sin plan acción', (SELECT paas.plan_accion_descripcion FROM plan_accion_automatico paas WHERE paas.id=lceo.plan_accion_id)) AS PLAN_ACCION,
         pre.nombre as NOMBRE_PREGUNTA,
        (
             IF((SELECT COUNT(*) FROM lista_chequeo_ejec_fotos lcefs WHERE lcefs.lista_chequeo_ejec_respuestas=lcer.id) = 0,0,1)
        ) AS HAY_FOTOS,
        (
             IF((SELECT COUNT(*) FROM lista_chequeo_ejec_archivos lcea WHERE lcea.lista_chequeo_ejec_respuesta_id=lcer.id) = 0,0,1)
        ) AS HAY_ADJUNTOS,
        (
             IF((SELECT COUNT(*) FROM plan_accion_manu_det pamd WHERE pamd.lista_cheq_ejec_respuesta_id=lcer.id) = 0,0,1)
        ) AS HAY_PLAN_ACCION,
        IF(lcer.id IS NULL,0,lcer.id) RESPUESTA_FOTOS,
         IF(res.valor_personalizado IS NULL, 'No aplica',res.valor_personalizado) as respuesta, 
         cat.ponderado as PONDERAD_CATEGORIA,
         pre.ponderado as PONDERADO_PREGUNTA,
         IF(res.ponderado IS NULL,100,res.ponderado ) as res_ponderado,
         lcer.no_aplica,
         IF(lcer.no_aplica=1,'',TRUNCATE((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado ))/100),2))as porcentaje_pregunta,
         IF((SELECT 
            paass.plan_accion_descripcion as plan_accion
            FROM lista_chequeo_ejec_respuestas lcerss
            LEFT JOIN lista_chequeo_ejecutadas lcess ON lcess.id=lcerss.lista_chequeo_ejec_id
            LEFT JOIN categoria catss ON catss.id=lcerss.categoria_id
            LEFT JOIN respuesta resss ON resss.id=lcerss.respuesta_id
            LEFT JOIN pregunta press ON press.id=lcerss.pregunta_id
            LEFT JOIN plan_accion pa ON pa.respuesta_id=lcerss.respuesta_id
            INNER JOIN plan_accion_automatico paass ON paass.plan_accion_id=pa.id
            WHERE lcess.id=lce.id AND press.id= pre.id
            GROUP BY catss.id, press.id) IS NULL,'Sin plan de acción',(SELECT 
            paass.plan_accion_descripcion as plan_accion
            FROM lista_chequeo_ejec_respuestas lcerss
            LEFT JOIN lista_chequeo_ejecutadas lcess ON lcess.id=lcerss.lista_chequeo_ejec_id
            LEFT JOIN categoria catss ON catss.id=lcerss.categoria_id
            LEFT JOIN respuesta resss ON resss.id=lcerss.respuesta_id
            LEFT JOIN pregunta press ON press.id=lcerss.pregunta_id
            LEFT JOIN plan_accion pa ON pa.respuesta_id=lcerss.respuesta_id
            INNER JOIN plan_accion_automatico paass ON paass.plan_accion_id=pa.id
            WHERE lcess.id=lce.id AND press.id= pre.id
            GROUP BY catss.id, press.id)) AS PLAN_ACCION

         FROM lista_chequeo_ejec_respuestas lcer
         LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
         LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
         LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
         LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
         LEFT JOIN lista_chequeo_ejec_opciones AS lceo ON lceo.lista_chequeo_ejec_respuestas_id=lcer.id
         WHERE lce.id=:idEjecutada AND lce.estado = :idEstado
         GROUP BY cat.id, pre.id
         ORDER BY pre.orden_lista"),['idEstado' => 2, 'idEjecutada' => $idListaEjecutada]);

        foreach ($seccionTres as $key => $itemPregunta) 
        {
            if($itemPregunta->HAY_FOTOS != 0 )
            {
                $fotos = $this->ejecutadasFotos
                ->select(
                    \DB::raw('CONCAT("imagenes/listas_chequeo/",foto) AS FOTO')
                    )
                ->where('lista_chequeo_ejec_respuestas','=',$itemPregunta->RESPUESTA_FOTOS)
                ->get();

                $seccionTres[$key]->FOTOS = $fotos->toArray();
            }

            if($itemPregunta->HAY_ADJUNTOS != 0 )
            {
                $adjuntos = $this->ejecutadasAdjuntos
                ->select(
                    'archivo_codificado as file',
                    'archivo_alias as alias',
                    'created_at as fecha_creacion'
                )
                ->where('lista_chequeo_ejec_respuesta_id','=',$itemPregunta->RESPUESTA_FOTOS)
                ->get();

                $seccionTres[$key]->ADJUNTOS = $adjuntos->toArray();
            }

             // TRAER RESPUESTAS ESCOGIDAS POR USUARIO
             $opcionesTipoRespuesta = $this->respuesta
             ->where('pregunta_id','=',$itemPregunta->ID_PREGUNTA)
             ->get();

             $seccionTres[$key]->TIPOS_RESPUESTA = $opcionesTipoRespuesta->toArray();
        }

        $arrayFinal = [];
        foreach ($seccionTres as $key => $seccion) 
        {
            $arrayFinal[$seccion->NOMBRE_CATEGORIA]['ID_CATEGORIA'] = $seccion->ID_CATEGORIA;
            $arrayFinal[$seccion->NOMBRE_CATEGORIA]['NOMBRE_CATEGORIA'] = $seccion->NOMBRE_CATEGORIA;
            $arrayFinal[$seccion->NOMBRE_CATEGORIA]['PONDERADO_CATEGORIA'] = $seccion->PONDERAD_CATEGORIA;
            $arrayFinal[$seccion->NOMBRE_CATEGORIA]['PREGUNTAS'][] = $seccion;
        }

        return $arrayFinal;
    }

    public function FuncionConsultaCategoriaPreguntasSeccionCuatro($idListaEjecutada)
    {
        $seccionCuatro = \DB::select(\DB::raw("SELECT
        lcer.no_aplica, 
        IF(res.valor_personalizado IS NULL, 'No aplica',res.valor_personalizado) as respuesta, 
        count(*) as cant,
        SUM(IF(lcer.no_aplica=1,'',(pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado ))/100)))as porcentaje_pregunta,
        (SELECT COUNT(*) FROM categoria AS cs WHERE cs.lista_chequeo_id=lc.id) AS CANTIDAD_CATEGORIAS
        FROM lista_chequeo_ejec_respuestas lcer
        LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
        LEFT JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
        WHERE lce.id=:idEjecutada
        GROUP BY respuesta"),['idEjecutada' => $idListaEjecutada]);

        return $seccionCuatro;
    }

    public function FuncionConsultaCategoriaPreguntasSeccionQuinta($idListaEjecutada)
    {
        $seccionQuinta = \DB::select(\DB::raw("SELECT 
        pre.nombre as pregunta,
        IF(res.valor_personalizado IS NULL, 'No aplica',res.valor_personalizado) as respuesta, 
        paa.plan_accion_descripcion as plan_accion,
        (SELECT 
            (SELECT 
				CONCAT(sacs.titulo,':',' ',IF(sacs.descripcion IS NULL,'Sin descripción',sacs.descripcion)) 
				FROM accion_correctiva sacs 
				INNER JOIN lista_chequeo_ejec_planaccion slcep ON slcep.accion_correctiva_id = sacs.id
				INNER JOIN lista_chequeo_ejec_opciones slceo ON slceo.id = slcep.lista_chequeo_ejec_opciones
				INNER JOIN lista_chequeo_ejec_respuestas slcer ON slcer.id = slceo.lista_chequeo_ejec_respuestas_id
				WHERE slcer.lista_chequeo_ejec_id = lcess.id AND slcer.pregunta_id = press.id
			) AS titulo
            
            FROM lista_chequeo_ejec_respuestas lcerss
            LEFT JOIN lista_chequeo_ejecutadas lcess ON lcess.id=lcerss.lista_chequeo_ejec_id
            INNER JOIN lista_chequeo lcss ON lcss.id=lcess.lista_chequeo_id
            INNER JOIN usuario ususs ON ususs.id=lcss.usuario_id
            INNER JOIN establecimiento estass ON estass.id=ususs.establecimiento_id
            INNER JOIN empresa empess on empess.id=estass.empresa_id
            INNER JOIN cuenta_principal ctass ON ctass.id=ususs.cuenta_principal_id
            LEFT JOIN categoria catss ON catss.id=lcerss.categoria_id
            LEFT JOIN respuesta resss ON resss.id=lcerss.respuesta_id
            LEFT JOIN pregunta press ON press.id=lcerss.pregunta_id
            LEFT JOIN plan_accion pa ON pa.pregunta_id=press.id
            INNER JOIN plan_accion_automatico paass ON paass.plan_accion_id=pa.id
            INNER JOIN lista_chequeo_ejec_opciones lceoss ON lceoss.lista_chequeo_ejec_respuestas_id=lcerss.id
            LEFT JOIN lista_chequeo_ejec_planaccion lcepss ON lcepss.lista_chequeo_ejec_opciones=lceoss.id
            WHERE press.id=pre.id AND lcess.id = lce.id
        ) AS ACCION_CORRECTIVA
                
        FROM lista_chequeo_ejec_respuestas lcer
        LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
        LEFT JOIN plan_accion pa ON pa.respuesta_id=res.id
        INNER JOIN plan_accion_automatico paa ON paa.plan_accion_id=pa.id
        WHERE lce.id=:idEjecutada
        GROUP BY cat.id, pre.id"),['idEjecutada' => $idListaEjecutada]);
        //dd($seccionQuinta);
        return $seccionQuinta;
    }

    public function descargaListaChequeoExcel(Request $request)
    {
        $idListaEjecutada = $request->get('listaId');
        $tipo = $request->get('tipo');
      
        if (!$this->listaEjecutada
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS u','u.id','=','lc.usuario_id')
        ->where([
            ['lista_chequeo_ejecutadas.id', '=',$idListaEjecutada],
            ['lista_chequeo_ejecutadas.estado', '=', 2],
            ['u.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]
        ])->exists()) 
            return back();
        
        $seccionUno = $this->FuncionConsultaEncabezado($idListaEjecutada);
        $seccionDos = $this->FuncionConsultaCategoriaPreguntasSeccionDos($idListaEjecutada);
        $seccionTres = $this->FuncionConsultaCategoriaPreguntasSeccionTres($idListaEjecutada);
        $seccionCuatro = $this->FuncionConsultaCategoriaPreguntasSeccionCuatro($idListaEjecutada);
        $seccionQuinta = $this->FuncionConsultaCategoriaPreguntasSeccionQuinta($idListaEjecutada);
        
        // dd($seccionTres);
        // return view('exports.listaChequeo');
        if ($tipo === 'excel') {
            return \Excel::download(new DetalleListaChequeo($seccionUno,$seccionDos,$seccionTres), 'lista_detalle.xlsx');
           
        }else{
            $pdf = \PDF::loadView('exports.listaChequeoPdf', compact(
            'seccionUno',
            'seccionDos',
            'seccionTres'
            ))->setOptions([
                'tempDir' => public_path(),
                'chroot'  => '/var/www',
            ])
            ->setPaper('a4', 'landscape');
            return $pdf->download('report.pdf');

        }
    }

    public function consultarAdjuntos(Request $request){
        $idRespuestaEjecutadas = $request->get('idResp');
        $adjuntos = $this->ejecutadasAdjuntos->select(
            'id',
            'archivo_codificado as file',
            'archivo_alias as nombre',
            \DB::raw('DATE_FORMAT(created_at, "%M %d %Y") as fecha_subida')
        )->where('lista_chequeo_ejec_respuesta_id', '=', $idRespuestaEjecutadas)->get();
        $arrayAdjuntos = [];
        foreach($adjuntos as $key => $value){
            $link = \Storage::url($value->file);
            array_push($arrayAdjuntos, [
                'id' => $value->id,
                'file' => $value->file,
                'nombre' => $value->nombre,
                'fecha_subida' => $value->fecha_subida,
                'urlDescarga' => $link
            ]);
        }
        return response()->json(['datos' => $arrayAdjuntos]);
    }

    public function descargarAdjunto($id){
        $idFile = $id;
        $adjunto = $this->ejecutadasAdjuntos->find($idFile);
        $exists = \Storage::disk('public')->exists($adjunto->archivo_codificado);
        if($exists){
            $path = \Storage::disk('public')->path($adjunto->archivo_codificado);
            return response()->download($path, $adjunto->archivo_alias);
        }else{

        }

        
    }

    public function traer_data_plan_accion_manual(Request $request){
        $idpregunta = $request->idpregunta;
        //dd($request->idlistachequeo);
        $planAccionM = \DB::select(\DB::raw("
        SELECT pamd.plan_accio_man_opc_id as id_opcion, pamo.opcion as opcion, 
        IF((pamd.plan_accio_man_opc_id = 8 OR pamd.plan_accio_man_opc_id = 5),
            (SELECT uss.nombre_completo FROM usuario AS uss WHERE uss.id=pamd.respuesta),
        pamd.respuesta 
        ) AS respuesta
        FROM plan_accion_manu_det pamd
        JOIN lista_chequeo_ejec_respuestas lcer ON pamd.lista_cheq_ejec_respuesta_id = lcer.id
        INNER JOIN plan_accion_man_opc pamo ON pamd.plan_accio_man_opc_id = pamo.id 
        WHERE lcer.pregunta_id =:idpregunta AND lcer.lista_chequeo_ejec_id =:idlistaejec;
        "), ['idpregunta' => $idpregunta, 'idlistaejec' => $request->idlistachequeo]);

        return response()->json([
            'data' => $planAccionM
        ]);
    }

    public function setRevisado(Request $request){
        $revisado = $request->get('revisado');
        $idListaEjecutada = $request->get('idListaEjecutada');
        
        $usuario = $revisado ? auth()->user()->id : null;

        ListaChequeoEjecutadas::where('id', $idListaEjecutada)
        ->update(['revisado' => $usuario]);

        return response()->json([
            'status' => 200
        ]);
    }

}
