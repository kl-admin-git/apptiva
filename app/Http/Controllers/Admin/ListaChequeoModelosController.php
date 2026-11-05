<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\ListaChequeoModelos;
use App\Http\Models\ListaChequeo;
use App\Http\Models\Categoria;
use App\Http\Models\Pregunta;
use App\Http\Models\TipoRespuestaCategoria;
use App\Http\Models\TipoRespuestaPonderadoPredeterminado;
use App\Http\Models\PreguntaRespuestaOpciones;
use App\Http\Models\Respuesta;
use App\Http\Models\PreguntaOpcionRespuesta;
use App\Http\Models\PlanDeAccionAutomatico;
use App\Http\Models\TipoRespuesta;
use App\Http\Models\ConfiguracionEjecucion;
use App\Http\Models\ListaChequeoEncabezado;
use App\Http\Models\ListaChequeoConfiguracion;
use App\Http\Models\PlanAccion;
use App\Http\Models\PlanAccionManual;

class ListaChequeoModelosController extends Controller
{
    protected $modelo,$ListaChequeo,$Categoria,$TipoRespuestaCategoria,$TipoRespuestaPonderadoPredeterminado,$PreguntaRespuestaOpciones,$Respuesta,$PreguntaOpcionRespuesta,$PlanDeAccionAutomatico,$TipoRespuesta,$ConfiguracionEjecucion,$ListaChequeoEncabezado,$ListaChequeoConfiguracion;
    public function __construct(
        ListaChequeoModelos $modelo,
        ListaChequeo $listaChequeo,
        Categoria $categoria,
        Pregunta $pregunta,
        TipoRespuestaCategoria $tipoRespuestaCategoria,
        TipoRespuestaPonderadoPredeterminado $respuestaPredeterminada,
        PreguntaRespuestaOpciones $respuestaOpciones,
        Respuesta $respuesta,
        PreguntaOpcionRespuesta $preguntaOpcionRespuesta,
        PlanDeAccionAutomatico $planDeAccionAutomatico,
        TipoRespuesta $tipoRespuesta,
        ConfiguracionEjecucion $configuracionEjecucion,
        ListaChequeoEncabezado $listaEncabezado,
        ListaChequeoConfiguracion $listaChequeoConfiguracion,
        PlanAccion $planAccion,
        PlanAccionManual $planAccionManual
    )
    {

        $this->modelo = $modelo;
        $this->listaChequeos = $listaChequeo;
        $this->categoria = $categoria;
        $this->pregunta = $pregunta;
        $this->tipoRespuestaCategoria = $tipoRespuestaCategoria;
        $this->respuestaPredeterminada = $respuestaPredeterminada;
        $this->respuestaOpciones = $respuestaOpciones;
        $this->respuesta = $respuesta;
        $this->preguntaOpcionRespuesta = $preguntaOpcionRespuesta;
        $this->planDeAccionAutomatico = $planDeAccionAutomatico;
        $this->tipoRespuesta = $tipoRespuesta;
        $this->configuracionEjecucion = $configuracionEjecucion;
        $this->listaEncabezado = $listaEncabezado;
        $this->listaChequeoConfiguracion = $listaChequeoConfiguracion;
        $this->planAccion = $planAccion;
        $this->planAccionManual = $planAccionManual;

        try {
            \DB::statement("SET lc_time_names = 'es_ES'");
        } catch (\Throwable $e) {}
        $this->middleware('auth');
        $this->middleware('isActive');  
    }

    public function Index()
    {
      
        $sector = $this->modelo->verificacionSector();
        $administradorPlataforma = $this->modelo->administradorPlataforma();
      
        $modelos = $this->modelo
        ->join('modelos_sector','modelo.id','=','modelos_sector.modelo_id')
        ->select('modelo.id','modelo.nombre')->where([
            ['modelo.estado','=',1],
            ['modelos_sector.sector_id','=',$sector]
            ])->get();
            
        $modelosAdmin = $this->modelo->get();
        $sectorAdministrador = \DB::table('sector')->get();
        return view('Admin.listachequeo_modelos',compact('modelos','modelosAdmin','sectorAdministrador','administradorPlataforma'));
    }

    public function ConsultarModelos(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));
     
        $modelos = $this->FuncionTraerModelosPorPaginacion($paginacion,$filtros);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $modelos
        );
    }

    public function CreateListModel(Request $request)
    {
        
        if ($request->has('listaId')) {
           
            $listaId = $request->get('listaId');
            $nombreLista = $request->get('nombreLista');
          
            $listaChequeo = $this->listaChequeos
            ->select('lista_chequeo.*')
            
            ->where('id','=',$listaId)
            ->first();
          
            //INSERCIÓN LISTA DE CHEQUEO
            $arrayInsertar = [
                'nombre' => $nombreLista . ' - Copia', 
                'publicacion_destino' => $listaChequeo->publicacion_destino, 
                'entidad_evaluada' => $listaChequeo->entidad_evaluada, 
                'estado' => 1, 
                'usuario_id' => auth()->user()->id, 
                'espacio_mb' => 0, 
                'modelo_id' => null
            ];
           

        }else{

            $idModelo = $request->get('idModelo');
            $nombreModelos = $request->get('nombreModelos');
    
            $listaChequeo = $this->listaChequeos
            ->select('lista_chequeo.*')
            ->Join('modelo AS m','m.lista_chequeo_id','=','lista_chequeo.id')
            ->where('m.id','=',$idModelo)
            ->first();
            
            //INSERCIÓN LISTA DE CHEQUEO
            $arrayInsertar = [
                'nombre' => $nombreModelos, 
                'publicacion_destino' => $listaChequeo->publicacion_destino, 
                'entidad_evaluada' => $listaChequeo->entidad_evaluada, 
                'estado' => 1, 
                'usuario_id' => auth()->user()->id, 
                'espacio_mb' => 0, 
                'modelo_id' => $idModelo
            ];
        }
        $listaChequeosNew = new $this->listaChequeos;
        $listaChequeosNew->fill($arrayInsertar);
        if($listaChequeosNew->save())
        {
            
            $listaEncabezado = $this->listaEncabezado->where('lista_chequeo_id','=', $listaChequeo->id)->first();

            // INSERTAR LISTA CHEQUEO ENCABEZADO
            $arrayInsertar = [
                'fecha' => $listaEncabezado->fecha, 
                'entidad_evaluada_opcion' => $listaEncabezado->entidad_evaluada_opcion, 
                'lista_chequeo_id' => $listaChequeosNew->id
            ];
            $listaEncabezadoNew = new $this->listaEncabezado;
            $listaEncabezadoNew->fill($arrayInsertar);
    
            
            if($listaEncabezadoNew->save())
            {
                $categorias = $this->categoria->where('lista_chequeo_id','=', $listaChequeo->id)->get();
                
                //INSERTAR CATEGORIAS
                foreach ($categorias as $key => $categoria) 
                {
                    $arrayInsertar = [
                        'nombre' => $categoria->nombre, 
                        'ponderado' => $categoria->ponderado, 
                        'orden_categoria' => $categoria->orden_categoria,
                        'orden_lista' => $categoria->orden_lista,
                        'lista_chequeo_id' => $listaChequeosNew->id
                    ];
            
                    $categoriaNew = new $this->categoria;
                    $categoriaNew->fill($arrayInsertar);
            
                    if($categoriaNew->save())
                    {

                        $preguntas = $this->pregunta->where('categoria_id','=', $categoria->id)->get();

                        foreach ($preguntas as $key => $pregunta) 
                        {
                            // INSERTAR PREGUNTA
                            $arrayInsertar = [
                                'nombre' => $pregunta->nombre, 
                                'ponderado' => $pregunta->ponderado, 
                                'categoria_id' => $categoriaNew->id,
                                'orden_lista' => $pregunta->orden_lista,
                                'lista_chequeo_id' => $listaChequeosNew->id,
                                'tipo_respuesta_id' => $pregunta->tipo_respuesta_id,
                                'permitir_noaplica' => $pregunta->permitir_noaplica,
                            ];
                    
                            $preguntaNew = new $this->pregunta;
                            $preguntaNew->fill($arrayInsertar);
                            $respuestaNew = null;
                            if($preguntaNew->save())
                            {
                                $respuestas = $this->respuesta->where('pregunta_id','=', $pregunta->id)->get();

                                $idsArrrayQueTienenPlanesDeAccion = [];
                                foreach ($respuestas as $key => $respuesta) 
                                {
                                     //INSERTAR RESPUESTA
                                    $arrayInsertar = [
                                        'tipo_respuesta_ponderado_pred_id' => $respuesta->tipo_respuesta_ponderado_pred_id, 
                                        'valor_personalizado' => $respuesta->valor_personalizado, 
                                        'pregunta_id' => $preguntaNew->id,
                                        'ponderado' => $respuesta->ponderado
                                    ];
                            
                                    $respuestaNew = new $this->respuesta;
                                    $respuestaNew->fill($arrayInsertar);
                            
                                    $respuestaNew->save();

                                    //GUARDAR LOS ID's NUEVOS CON KEY's DE LOS ID VIEJOS PARA SABER QUIEN CON QUIEN VA EN PLAN DE ACCIÓN
                                    if($this->planAccion->where('respuesta_id', '=',$respuesta->id)->exists())
                                        $idsArrrayQueTienenPlanesDeAccion[$respuesta->id] = $respuestaNew->id;
                                }

                                // INSERTAR OPCIONES PREGUNTAS
                                $opcionesRespuestas = $this->preguntaOpcionRespuesta->where('pregunta_id','=', $pregunta->id)->get();
                                $rspNewAnterior = 0; //La uso para evitar crear plan_accion con respuesta repetida
                                foreach ($opcionesRespuestas as $key => $opcRespuestas) 
                                {
                                    $arrayInsertar = [
                                        'pregunta_id' => $preguntaNew->id, 
                                        'pregunta_respuesta_opcion' => $opcRespuestas->pregunta_respuesta_opcion
                                    ];
                                    $opcionesRespuesta = new $this->preguntaOpcionRespuesta;
                                    $opcionesRespuesta->fill($arrayInsertar);
                                    if($opcionesRespuesta->save())
                                    {
                                        
                                        //INSERTAR PLAN DE ACCIÓN (BUSCAR OPCIONES RESPUESTA Y DESCRIPCION)
                                        $planAccionOriginal = $this->planAccion->where('pregunta_id', '=', $opcRespuestas->pregunta_id)->first(); 
                                        //En caso de que no tenga datos en la tabla principal (plan_accion) retorne error.
                                        // if($planAccionOriginal === null){
                                        //     //Elimino las categoria que se alcanzo a crear
                                        //     $idListaChequeo = $listaChequeosNew->id;
                                        //     $borrarListaChequeo = $listaChequeo->find($idListaChequeo)->delete();
                                        //     return $this->FinalizarRetorno(
                                        //         406,
                                        //         $this->MensajeRetorno('Datos',406, 'Error al duplicar lista de chequeo. Sin datos de origen.')
                                        //     );
                                        // }
                                        $planAccionAutomatico = NULL;
                                        if($planAccionOriginal != null)
                                            $planAccionAutomatico = $this->planDeAccionAutomatico->where('plan_accion_id','=', $planAccionOriginal->id)->first();
                                        if(!is_null($planAccionAutomatico))
                                        {
                                            //LÓGICA PARA PODER SABER QUE RESPUESTA ID TIENE EL NUEVO PLAN DE ACCIÓN
                                            $rtaNew = NULL;
                                            $planAccionNew = NULL;
                                            foreach ($idsArrrayQueTienenPlanesDeAccion as $keyrtaold => $rta) 
                                            {
                                                $existPlanAccion = $this->planAccion->where('pregunta_id', '=', $preguntaNew->id)->exists();
                                                //Solo se debe crear 1 plan de accion x pregunta p respuesta
                                                if($keyrtaold == $planAccionOriginal->respuesta_id && $existPlanAccion == false){
                                                    $rtaNew = $rta;
                                                    //Creo un nuevo plan de accion x cada nueva respuesta_id
                                                    $planAccionNew = new $this->planAccion;
                                                    $planAccionNew->fill([
                                                        'tipo_pa' => $planAccionOriginal->tipo_pa,
                                                        'obligatorio' => $planAccionOriginal->obligatorio,
                                                        'alerta' => $planAccionOriginal->alerta,
                                                        'pregunta_id' => $preguntaNew->id,
                                                        'respuesta_id' => $rtaNew
                                                    ]);
                                                    $planAccionNew->save();

                                                    $arrayInsertar = [
                                                        'plan_accion_id' => $planAccionNew->id, 
                                                        'plan_accion_descripcion' => $planAccionAutomatico->plan_accion_descripcion
                                                    ];
                                                    //Creo un plan de accion automatico por cada respuesta
                                                    $planAccionAutomaticoNew = new $this->planDeAccionAutomatico;
                                                    $planAccionAutomaticoNew->fill($arrayInsertar);
                                            
                                                    $planAccionAutomaticoNew->save();
                                                }
                                                    
                                            }
                                            

                                        }
                                        
                                    }
                                }
                               
                            }
                        }
                        
                    }
                }

                
            }
        }

        $arrayFinal = array
        (
            'idChequeo' => $listaChequeo->id,
            'idNuevaListaChequeo' => $listaChequeosNew->id
        );

        return $this->FinalizarRetorno(
            200,
            $this->MensajeRetorno('Datos',200),
            $arrayFinal
        );

    }

    public function ConsultarModelosScroll(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        $modelos = $this->FuncionTraermodelosPorPaginacion($paginacion,$filtros);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $modelos
        );
    }

    public function FuncionTraerModelosPorPaginacion($paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_modelo':
                    if($filtro != '')
                        array_push($filtro_array,['modelo.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }
        $sector = $this->modelo->verificacionSector();
        $modelos = $this->modelo
        ->join('modelos_sector','modelo.id','=','modelos_sector.modelo_id')
        ->select(
            'modelo.*',
            \DB::raw('IF(modelo.imagen IS NULL,"/vertical/assets/images/users/circle_logo_audiid.png",CONCAT("/imagenes/modelos/",modelo.imagen)) AS FOTO')
        )
        ->where([
            ['modelo.estado','=',1],
            ['modelos_sector.sector_id','=',$sector]
            ]);

        if(COUNT($filtro_array) != 0)
        {
            $modelos = $modelos->where(function($query) use ($filtro_array)
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

        $modelos = $modelos->skip($desde)->take($hasta)->get();

        return $modelos;
    }

    public function sectorConModelo($modelo)
    {
        
        $modelos = \DB::table('modelos_sector')->select('sector_id')->where('modelo_id','=',$modelo)->get();
        return response()->json(['data'=>$modelos ], 200);

    }
    public function asignacionModeloSector(Request $request)
    {
        $tipo = $request->get('tipo');
        $modeloId = $request->get('modeloId');
        $sectoriId = $request->get('sectoriId');
     
        $isArray = is_array($sectoriId);
        if ($tipo === 'agregar') {

           

            if (!$isArray) {
                $tieneAsignado = \DB::table('modelos_sector')->where([
                    ['modelo_id','=',$modeloId],
                    ['sector_id','=',$sectoriId]
                ])->first();
                if (is_null($tieneAsignado)) {
                    $insert = \DB::table('modelos_sector')->insert( 
                        ['modelo_id' => $modeloId, 'sector_id' => $sectoriId]
                    );
                    
                    return response()->json(['mensaje'=>'Asignado Correctamente'], 200);
                }
            }else{
                
                foreach ($sectoriId as $key => $value) {
                   
                    $tieneAsignado = \DB::table('modelos_sector')->where([
                        ['modelo_id','=',$modeloId],
                        ['sector_id','=',$value]
                    ])->first();
                   
                    if (is_null($tieneAsignado)) {
                        $insert = \DB::table('modelos_sector')->insert( 
                            ['modelo_id' => $modeloId, 'sector_id' => $value]
                        );
                    }
                   
                }
                return response()->json(['mensaje'=>'Asignado Correctamente'], 200);
            }
                       
          
          
        }else{
            if (!$isArray) {
               
                $remover = \DB::table('modelos_sector')->where( 
                    ['modelo_id' => $modeloId, 'sector_id' => $sectoriId]
                )->delete();
    
                return response()->json(['mensaje'=>'Removido Correctamente'], 200);
            }else{
                foreach ($sectoriId as $key => $value) {
                    $remover = \DB::table('modelos_sector')->where( 
                        ['modelo_id' => $modeloId, 'sector_id' => $value]
                    )->delete();
                }
                return response()->json(['mensaje'=>'Removido Correctamente'], 200);
            }
        }
    }
}
