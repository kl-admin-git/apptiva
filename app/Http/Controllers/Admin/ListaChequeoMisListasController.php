<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
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
use App\Http\Models\ListaChequeoEjecutadas;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use App\Http\Models\ListaChequeoModelos;
use App\Http\Models\CategoriaEtiquetas;
use App\Http\Models\PlanAccion;
use App\Http\Models\PlanAccionManual;
use Illuminate\Support\Facades\DB;

class ListaChequeoMisListasController extends Controller
{
    protected $listaEjecutada,$listaChequeoConfiguracion,$listaEncabezado,$listaChequeo,$categoria,$pregunta,$tipoRespuestaCategoria,$respuestaPredeterminada,$respuestaOpciones,$respuesta,$preguntaOpcionRespuesta,$planDeAccionAutomatico,$tipoRespuesta,$configuracionEjecucion, $planAccion, $planAccionManual;
    public function __construct(
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
        ListaChequeoEjecutadas $listaEjecutada,
        Empresa $empresa,
        Establecimiento $establecimiento,
        ListaChequeoModelos $modelo,
        CategoriaEtiquetas $categoriaEtiquetas,
        PlanAccion $planAccion,
        PlanAccionManual $planAccionManual
        )
    {
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
        $this->listaEjecutada = $listaEjecutada;
        $this->empresa = $empresa;
        $this->establecimiento = $establecimiento;
        $this->modelo = $modelo;
        $this->categoriaEtiquetas = $categoriaEtiquetas;
        $this->planAccion = $planAccion;
        $this->planAccionManual = $planAccionManual;
        
        \DB::statement("SET lc_time_names = 'es_ES'");
        $this->middleware('auth');
        $this->middleware('isActive');        
    }

    public function Index()
    {
        $cantidad = $this->listaChequeos
                ->Join('usuario AS us','us.id','=','lista_chequeo.usuario_id')
                ->where('us.cuenta_principal_id', '=',auth()->user()->cuenta_principal_id)->count();

        $cantidadEjecutadas = $this->listaEjecutada
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $cantidadEjecutadas = $cantidadEjecutadas
                ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
                ->where([
                    ['u.cuenta_principal_id', '=',auth()->user()->cuenta_principal_id]
                ])->count();

                // $cantidad = $cantidad->where('us.cuenta_principal_id', '=',auth()->user()->cuenta_principal_id)->count();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                {
                    $cantidadEjecutadas = $cantidadEjecutadas
                    ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
                    ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
                    ->Join('empresa AS em','em.id','=','e.empresa_id')
                    ->orWhere('em.id', '=',$esResponsableEmpresa->id)
                    ->orWhere('lista_chequeo_ejecutadas.usuario_id', '=',auth()->user()->id)
                    ->count();

                    // $cantidad = $cantidad
                    // ->Join('usuario AS us','us.id','=','lista_chequeo_ejecutadas.usuario_id')
                    // ->Join('establecimiento AS e','e.id','=','us.establecimiento_id')
                    // ->Join('empresa AS em','em.id','=','e.empresa_id')
                    // ->orWhere('em.id', '=',$esResponsableEmpresa->id)
                    // ->orWhere('lista_chequeo_ejecutadas.usuario_id', '=',auth()->user()->id)->count();
                }

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                {
                    $cantidadEjecutadas = $cantidadEjecutadas
                    ->Join('usuario AS u','u.id','=','lista_chequeo_ejecutadas.usuario_id')
                    ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
                    ->where([
                        ['e.id', '=',$esResponsableEstablecimiento->id]
                    ])->count();

                    // $cantidad = $this->listaChequeos
                    // ->Join('usuario AS us','us.id','=','lista_chequeo.usuario_id')
                    // ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
                    // ->where([
                    //     ['e.id', '=',$esResponsableEstablecimiento->id]
                    // ])
                    // ->count();
                }

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                {
                    $cantidadEjecutadas = $cantidadEjecutadas
                    ->where([
                        ['lista_chequeo_ejecutadas.usuario_id', '=',auth()->user()->id]
                    ])->count();

                    // $cantidad = $this->listaChequeos
                    // ->where([
                    //     ['lista_chequeo.usuario_id', '=',auth()->user()->id]
                    // ])
                    // ->count();
                }

                break;
            
            default:

                break;
        };

        return view('Admin.listachequeo_mis_listas',compact('cantidad','cantidadEjecutadas'));
    }

    public function IndexCrearListaChequeo()
    {
        $idListaChequeo = \Request::segment(3);
        if (!$this->listaChequeos->where('id', '=',$idListaChequeo)->exists()) 
            return redirect('/listachequeo/mislistas');

        $url = (\Request::root().'/listachequeo/ejecucion/'.encrypt($idListaChequeo));
        if(!$this->configuracionEjecucion->where('lista_chequeo_id', '=',$idListaChequeo)->exists())
        {
            $arrayInsertar = [
                'link' => $url, 
                'lista_chequeo_id' => $idListaChequeo
            ];
    
            $configuracionEjecucion = new $this->configuracionEjecucion;
            $configuracionEjecucion->fill($arrayInsertar);
    
            $configuracionEjecucion->save();
        }

        $datosCategoria = $this->categoria->where('lista_chequeo_id', '=',$idListaChequeo)->orderBy('orden_lista','ASC')->get();
        $datosListaChequeo = $this->listaChequeos
        ->select('lista_chequeo.*','lce.entidad_evaluada_opcion AS ID_EVALUADO')
        ->leftJoin('lista_chequeo_encabezado AS lce','lce.lista_chequeo_id','lista_chequeo.id')
        ->where('lista_chequeo.id', '=',$idListaChequeo)->first();

        $disabled = ($datosListaChequeo->tipo_ponderados == 1 ? 'disabled' : '');
        // dd($datosListaChequeo);
        return view('Admin.listachequeo_crear',compact('datosListaChequeo','datosCategoria','url','disabled'));
    }

    public function CrearListaChequeoMia(Request $request)
    {
        $nombreMiLista = $request->get('nombre');
        $entidad_evaluada = $request->get('entidad_evaluada');
        $publicacion_destino = $request->get('publicacion_destino');
        $estadoInicial = $request->get('estadoInicial');
        $checkAutomatico = $request->get('checkAutomatico');
        
        if($checkAutomatico == 'true')
            $checkAutomatico = 1;
        else
            $checkAutomatico = 0;
        
        $arrayInsertar = [
            'nombre' => $nombreMiLista, 
            'publicacion_destino' => $publicacion_destino,
            'entidad_evaluada' => $entidad_evaluada,
            'usuario_id' => auth()->user()->id,
            // 'modelo_id' => 0, // NO MODELO
            'estado'=> $estadoInicial,
            'tipo_ponderados' => $checkAutomatico
        ];

        $listaChequeo = new $this->listaChequeos;
        $listaChequeo->fill($arrayInsertar);

        if($listaChequeo->save())
        {
            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('Lista chequeo ',200),
                $listaChequeo->id
            );
        }
    }

    public function ActualizarListaChequeo(Request $request)
    {
        $nombreLista = $request->get('nombreLista');
        $idListaChequeoId = $request->get('idListaChequeoId');

        $arrayUpdate = [
            'nombre' => $nombreLista
        ];

        $respuestaUpdate = $this->listaChequeos->where('id','=',$idListaChequeoId)->update($arrayUpdate);

        if($respuestaUpdate)
        {
            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('El nombre de la lista ',201)
            );
        }
    }

    public function CrearCategoria(Request $request)
    {
        $nombre = $request->get('nombre');
        $ponderado = $request->get('ponderado');
        $orden_categoria = $request->get('orden_categoria');
        $orden_lista = $request->get('orden_lista');
        $lista_chequeo_id = $request->get('lista_chequeo_id');
        $idEtiqueta = $request->get('idEtiqueta');

        $arrayInsertar = [
            'nombre' => $nombre, 
            'ponderado' => $ponderado,
            'orden_categoria' => $orden_categoria,
            'orden_lista' => $orden_lista,
            'lista_chequeo_id' => $lista_chequeo_id,
            'id_etiqueta' => $idEtiqueta
        ];
        
        if($orden_lista == 'final')
        {
            $consultaListaChequeo = $this->categoria
            ->where([
                ['lista_chequeo_id','=',$lista_chequeo_id]
            ])->max('orden_lista');

            if (is_null($consultaListaChequeo)) 
                $consultaListaChequeo = 1;
            else 
                $consultaListaChequeo = $consultaListaChequeo + 1;

            $cantidad = $consultaListaChequeo;

            $arrayInsertar['orden_lista'] = $cantidad;

        }
        else if($orden_lista == 'principio')
        {
            $arrayInsertar['orden_lista'] = 0;
        }

        $categoria = new $this->categoria;
        $categoria->fill($arrayInsertar);

        if($categoria->save())
        {
            //CONSULTA LISTA DE CHEQUEO PARA VERIFICAR SI ES AUTOMATICO
            $datosListaChequeo = $this->listaChequeos->where('lista_chequeo.id', '=',$lista_chequeo_id)->first();
            $esAutomatico = ($datosListaChequeo->tipo_ponderados == 1 ? true : false);

            if($orden_lista != 'final')
            {
                $this->ActualizarCategorias($lista_chequeo_id,$orden_lista,$ponderado);

                $arrayUpdate = [
                    'orden_lista' => 1
                ];

                if($orden_lista == 'principio')
                {
                    $respuestaUpdate = $this->categoria->where('id','=',$categoria->id)
                    ->update($arrayUpdate);
                }
                else
                {
                    
                    $arrayUpdate = [
                        'orden_lista' => $orden_lista
                    ];

                    $respuestaUpdate = $this->categoria->where('id','=',$categoria->id)
                    ->update($arrayUpdate);
                }
                
            }

            if($esAutomatico)
            {
                $this->PonderadoCalculadoCategorias($lista_chequeo_id);
            }

            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('La categoría',200)
            );
        }
        else
        {
            return $this->FinalizarRetorno(
                402,
                $this->MensajeRetorno('La categoría ',402)            
            );
        }
    }

    public function PonderadoCalculadoCategorias($listaDeChequeo)
    {

        $categoriasPorListaChequeo = $this->categoria
        ->where('categoria.lista_chequeo_id','=',$listaDeChequeo)
        ->get();

        foreach ($categoriasPorListaChequeo as $key => $itemCategoria) 
        {
            $consultaPreguntasPorCategoria = $this->pregunta
            ->select(
                \DB::raw('IF(SUM(pregunta.ponderado) IS NULL, 0, SUM(pregunta.ponderado)) AS SUMA_PONDERADO_PREGUNTA')
            )
            ->where([
                ['pregunta.categoria_id','=',$itemCategoria->id]
            ])
            ->orderBy('orden_lista','DESC');

            $sumaPonderadoPRegunta = $consultaPreguntasPorCategoria->get();
            
            $actualizarCategoria = $this->categoria
            ->where('id','=',$itemCategoria->id)
            ->update([
                'ponderado' => $sumaPonderadoPRegunta[0]->SUMA_PONDERADO_PREGUNTA
            ]);

        }
        
        
        
        // if(COUNT($consultaPreguntasPorCategoria->get()) != 0)
        // {
        //     $totalCategorias = COUNT($consultaPreguntasPorCategoria->get());
        //     $valorParaCadaCategoria = number_format((100 /$totalCategorias),0);
        //     if($totalCategorias != 1)
        //     {
        //         $valorTotal = ($totalCategorias * $valorParaCadaCategoria);
        //         $valorRestante = (100 - ($valorTotal - $valorParaCadaCategoria));
        //         $respuestaUpdate = $this->categoria
        //         ->where('lista_chequeo_id','=',$lista_chequeo_id)
        //         ->update([
        //             'ponderado' => $valorParaCadaCategoria
        //         ]);

        //         $ultimoElemento = $consultaPreguntasPorCategoria->latest('orden_lista')->first();

        //         $actualizarUltimo = $this->categoria
        //         ->where('id','=',$ultimoElemento->id)
        //         ->update([
        //             'ponderado' => $valorRestante
        //         ]);

        //     }else
        //     {
        //         $respuestaUpdate = $this->categoria
        //         ->where('lista_chequeo_id','=',$lista_chequeo_id)
        //         ->update([
        //             'ponderado' => $valorParaCadaCategoria
        //         ]);
        //     }

        // }
        
    }

    public function EditarCategoria(Request $request)
    {
        $nombre = $request->get('nombre');
        $ponderado = $request->get('ponderado');
        $orden_categoria = $request->get('orden_categoria');
        $orden_lista = $request->get('orden_lista');
        $lista_chequeo_id = $request->get('lista_chequeo_id');
        $idCategoria = $request->get('idCategoria');
        $idEtiqueta = $request->get('idEtiqueta');
        
        switch ($orden_lista) {
            case 'final':
                
                $consultaListaChequeo = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                    
                ])
                ->orderBy('orden_lista','DESC')
                ->get();
                
                $respuestaUpdate = $this->categoria->where('id','=', $idCategoria)
                ->update([
                    'orden_lista' => (COUNT($consultaListaChequeo) + 1)
                ]);

                $consultaListaChequeo = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                    
                ])
                ->orderBy('orden_lista','DESC')
                ->get();
    
                $contador = COUNT($consultaListaChequeo);
                
                foreach ($consultaListaChequeo as $key => $itemCategoria) 
                {
                   
                    $respuestaUpdate = $this->categoria->where('id','=',$itemCategoria->id)
                    ->update([
                        'orden_lista' => $contador
                    ]);
    
                    $contador = $contador - 1;
                }

                break;

            case 'principio':
                $consultaListaChequeo = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                ])
                ->orderBy('orden_lista','ASC')
                ->get();
    
                $respuestaUpdate = $this->categoria->where('id','=', $idCategoria)
                ->update([
                    'orden_lista' => 0
                ]);

                $consultaListaChequeo = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                ])
                ->orderBy('orden_lista','ASC')
                ->get();
    
                $contador = 1;
    
                foreach ($consultaListaChequeo as $key => $itemCategoria) 
                {
                    $respuestaUpdate = $this->categoria->where('id','=',$itemCategoria->id)
                    ->update([
                        'orden_lista' => $contador
                    ]);
    
                    $contador = $contador + 1;
                }
                break;
            
            default:
                $consultaListaChequeo = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                    ['orden_lista','>',$orden_lista],
                    ['id','!=',$idCategoria],
                ])
                ->orderBy('orden_lista','ASC')
                ->get();

                $consultaMenores = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                    ['orden_lista','<=',$orden_lista]
                ])
                ->orderBy('orden_lista','ASC')
                ->get();

                $contador = (COUNT($consultaMenores) + 1);

                $respuestaUpdate = $this->categoria->where('id','=',$idCategoria)
                ->update([
                    'orden_lista' => $contador
                ]);

                foreach ($consultaListaChequeo as $key => $itemCategoria) 
                {
                    $contador = $contador + 1;

                    $respuestaUpdate = $this->categoria->where('id','=',$itemCategoria->id)
                    ->update([
                        'orden_lista' => $contador
                    ]);

                    
                }
                break;
        }
      

        $arrayUpdate = [
            'nombre' => $nombre, 
            'ponderado' => $ponderado,
            'id_etiqueta' => $idEtiqueta
        ];
        
        $respuestaUpdate = $this->categoria->where('id','=',$idCategoria)->update($arrayUpdate);
        
        return $this->FinalizarRetorno(
            201,
            $this->MensajeRetorno('La categoría',201)
        );
        
    }    

    public function ActualizarCategorias($idListaChequeo,$despuesDe,$ponderado)
    {
        if($despuesDe == 'principio')
        {
            $consultaListaChequeo = $this->categoria
            ->where([
                ['lista_chequeo_id','=',$idListaChequeo],
                ['orden_lista','>',0]
            ])
            ->orderBy('orden_lista','ASC')
            ->get();

            $orden = 2;
            foreach ($consultaListaChequeo as $key => $itemCategoria) 
            {
                $respuestaUpdate = $this->categoria->where('id','=',$itemCategoria->id)
                ->update([
                    'orden_lista' => $orden,
                    // 'ponderado' => $ponderado,
                ]);
                
                $orden = $orden + 1;
            }
        }
        else
        {
            $consultaListaChequeo = $this->categoria
            ->where([
                ['lista_chequeo_id','=',$idListaChequeo],
                ['orden_lista','>=',$despuesDe]
            ])
            ->orderBy('orden_lista','ASC')
            ->get();

            $orden = ($despuesDe - 1);

            foreach ($consultaListaChequeo as $key => $itemCategoria) 
            {
                $orden = $orden + 1;

                $respuestaUpdate = $this->categoria->where('id','=',$itemCategoria->id)
                ->update([
                    'orden_lista' => $orden,
                    // 'ponderado' => $ponderado,
                ]);
            }
    
            
        }

        

    }

    public function ConsultarCategoriasConSusPreguntas(Request $request)
    {
        $lista_chequeo_id = $request->get('lista_chequeo_id');
        $final = $this->ConsultaCategoriasPreguntasPorListaChequeo($lista_chequeo_id);
        
        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $final
        );

    }

    public function ConsultaCategoriasPorListaChequeo($idListaChequeo)
    {
        $consultaListaChequeo = $this->categoria
        ->select(
            'categoria.*'
        )
        ->where([
            ['categoria.lista_chequeo_id','=',$idListaChequeo]
        ])
        ->orderBy('categoria.orden_lista','ASC')
        ->get();

        return $consultaListaChequeo;
    }
    
    public function ConsultaCategoriasPreguntasPorListaChequeo($lista_chequeo_id)
    {
        $consultaListaChequeo = $this->categoria
        ->select(
            \DB::raw('(SELECT SUM(sp.ponderado) FROM pregunta sp WHERE sp.categoria_id = categoria.id) AS SUMA_PONDERADO'),
            'categoria.*',
            'ce.nombre AS ETIQUETA',
            'ce.id AS IDETIQUETA'
        )
        ->leftJoin('categoria_etiquetas AS ce','ce.id','categoria.id_etiqueta')
        ->where([
            ['categoria.lista_chequeo_id','=',$lista_chequeo_id]
        ])
        ->orderBy('categoria.orden_lista','ASC')
        ->get();

        $arrayFinal = [];
        foreach ($consultaListaChequeo as $key => $categoria) 
        {
            $objeto = new \stdClass();
            
            $preguntas = $this->pregunta
            ->Join('tipo_respuesta AS tr','tr.id','pregunta.tipo_respuesta_id')
            ->Join('tipo_respuesta_categoria AS trc','trc.id','tr.tipo_respuesta_categoria')
            ->select(
                'pregunta.*',
                'trc.nombre AS NOMBRE_CATEGORIA',
                'tr.icono AS ICONO_TIPO_RESPUESTA'
            )->where('pregunta.categoria_id','=',$categoria->id)
            ->orderBy('pregunta.orden_lista','ASC')
            ->get();

            $objeto->CATEGORIA_ID = $categoria->id;
            $objeto->SUMA_PONDERADO = $categoria->SUMA_PONDERADO;
            $objeto->NOMBRE_CATEGORIA = $categoria->nombre;
            $objeto->PONDERADO = number_format($categoria->ponderado,2);
            $objeto->ORDEN_LISTA = $categoria->orden_lista;
            $objeto->LISTA_CHEQUEO_ID = $categoria->lista_chequeo_id;
            $objeto->ETIQUETANOMBRE = $categoria->ETIQUETA;
            $objeto->IDETIQUETA = $categoria->IDETIQUETA;
            foreach ($preguntas as $key => $pregunta) 
            {
                $opcionesGenerales = $this->preguntaOpcionRespuesta
                ->Join('pregunta_respuesta_opcion AS pro','pro.id','pregunta_preguntarespuestaopcion.pregunta_respuesta_opcion')
                ->where('pregunta_preguntarespuestaopcion.pregunta_id','=',$pregunta->id)
                ->get();

                $preguntas[$key]['OpcionesGenerales'] = $opcionesGenerales;
            }

            $objeto->PREGUNTAS = $preguntas;

            array_push($arrayFinal,$objeto);
        }

        $datosCategoria = $this->categoria->where('lista_chequeo_id', '=',$lista_chequeo_id)->orderBy('orden_lista','ASC')->get();
        $final = array
        (
            'arrayCategoriasPreguntas' => $arrayFinal,
            'arrayListadoNuevoPopUp' => $datosCategoria
        );

        return $final;
    }

    public function ConsultaDetallePreguntaPorIdPregunta(Request $request)
    {
        $idPregunta = $request->get('idPregunta');
        $idListaChequeo = $request->get('idListaChequeo');

        $pregunta = $this->pregunta
        ->Join('tipo_respuesta AS tr','tr.id','pregunta.tipo_respuesta_id')
        ->Join('tipo_respuesta_categoria AS trc','trc.id','tr.tipo_respuesta_categoria')
        ->select(
            'pregunta.*',
            'trc.nombre AS NOMBRE_CATEGORIA',
            'tr.icono AS ICONO_TIPO_RESPUESTA'
        )->where('pregunta.id','=',$idPregunta)->first();
            
        $opcionesGenerales = $this->preguntaOpcionRespuesta
        ->select(
            'pro.*',
            \DB::raw('IF(r.tipo_respuesta_ponderado_pred_id IS NULL,"0",r.tipo_respuesta_ponderado_pred_id) AS RESPUESTA_ID'),
            \DB::raw('IF(r.valor_personalizado IS NULL,"",r.valor_personalizado) AS RESPUESTA_VALOR'),
            \DB::raw('IF(paa.plan_accion_descripcion IS NULL,"0",paa.plan_accion_descripcion) AS PLAN_ACCION'),
            'pa.tipo_pa as TIPO_PLAN_ACCION'
        )
        ->Join('pregunta_respuesta_opcion AS pro','pro.id','=','pregunta_preguntarespuestaopcion.pregunta_respuesta_opcion')
        //->leftJoin('plan_accion_automatico AS paa','paa.pregunta_preguntarespuestaopcion_id','=','pregunta_preguntarespuestaopcion.id')
        ->leftJoin('pregunta', 'pregunta.id', '=', 'pregunta_preguntarespuestaopcion.pregunta_id')
        ->leftJoin('plan_accion as pa', 'pa.pregunta_id', '=', 'pregunta.id')
        ->leftJoin('respuesta AS r','r.id','=','pa.respuesta_id')
        ->leftJoin('plan_accion_automatico AS paa','paa.plan_accion_id','=','pa.id')
        ->where('pregunta_preguntarespuestaopcion.pregunta_id','=',$idPregunta)
        ->get();
        $pregunta['OpcionesGenerales'] = $opcionesGenerales;
        
        $opcionesPlanAccionManual = $this->planAccion->select(
            'pam.plan_accion_man_opc_id as OPCIONES',
            'pam.requerido as REQUERIDO'
        )->join('plan_accion_manual AS pam', 'pam.plan_accion_id', '=', 'plan_accion.id')
        ->where('plan_accion.pregunta_id', '=', $idPregunta)->get();
        $pregunta['OpcionesPlanAccionManual'] = $opcionesPlanAccionManual;

        $planAccion = false;
        foreach ($opcionesGenerales as $key => $itemGeneral) 
        {
            if($itemGeneral->id == 4)
            {
                $planAccion = true;

            }
        }

        $pregunta['planAccion'] = $planAccion;

        $arrayFinal = $this->FuncionInformacionSteps($idListaChequeo);
        $arrayFinal['preguntaDetalle'] = $pregunta;

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $arrayFinal
        );
    }

    public function EliminarCategoria(Request $request)
    {
        $idCategoria = $request->get('idCategoria');

        //SE DEBE VALIDAR SI TIENE REGISTROS  PARA SABER SI SE PEUDE BORRAR O NO
        $cantidad = $this->categoria->Join('lista_chequeo_ejecutadas AS lce','lce.lista_chequeo_id','=','categoria.lista_chequeo_id')->where('categoria.id','=',$idCategoria)->count();
        
        if($cantidad != 0)
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La categoría no puede eliminarse porque ya tiene registros de ejecución')
            ); 
        }

        $datosCategoria = $this->categoria->where('categoria.id','=',$idCategoria)->first();

        $lista_chequeo_id = $datosCategoria->lista_chequeo_id;
        $respuesta = $this->categoria->where('id', $idCategoria)->delete();

        if($respuesta)
        {
            //CONSULTA LISTA DE CHEQUEO PARA VERIFICAR SI ES AUTOMATICO
            $datosListaChequeo = $this->listaChequeos->where('lista_chequeo.id', '=',$lista_chequeo_id)->first();
            $esAutomatico = ($datosListaChequeo->tipo_ponderados == 1 ? true : false);

            if($esAutomatico)
            {
                $consultaPreguntas = $this->pregunta
                ->where([
                    ['pregunta.lista_chequeo_id','=',$lista_chequeo_id],
                    // ['pregunta.categoria_id', '=',$categoriaId]
                ])
                ->orderBy('orden_lista','DESC');

                $this->PonderadoCalculadoPregunta($consultaPreguntas,$lista_chequeo_id);

                $this->PonderadoCalculadoCategorias($lista_chequeo_id);
            }
            
            return $this->FinalizarRetorno(
                203,
                $this->MensajeRetorno('La categoría ',203)
            );  
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La categoría no pudo eliminarse')
            ); 
        }
    }

    public function EliminarPregunta(Request $request)
    {
        $idPregunta = $request->get('idPregunta');

        //SE DEBE VALIDAR SI TIENE REGISTROS  PARA SABER SI SE PEUDE BORRAR O NO

        $cantidad = $this->pregunta->Join('lista_chequeo_ejecutadas AS lce','lce.lista_chequeo_id','=','pregunta.lista_chequeo_id')->where('pregunta.id','=',$idPregunta)->count();
        
        if($cantidad != 0)
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La pregunta no puede eliminarse porque ya tiene registros de ejecución')
            ); 
        }

        $datosPregunta = $this->pregunta->where('pregunta.id','=',$idPregunta)->first();

        $lista_chequeo_id = $datosPregunta->lista_chequeo_id;
        $categoriaId = $datosPregunta->categoria_id;
        $planAccion = $this->planAccion->where('pregunta_id', '=', $idPregunta)->first();
        
        if($planAccion != null){
            $planAccionManual = $this->planAccionManual->where('plan_accion_id', '=', $planAccion->id);
            //Valido si existe datos relacionados en plan_Accion y plan_accion_automatio
            $planAccionAutomatico = $this->planDeAccionAutomatico->where('plan_accion_id', '=', $planAccion->id);
            //Valido si tiene datos en la tabla plan_accion_manual
            if($planAccionManual->count() >= 1){
                $planAccionManual->delete();
                $planAccion->delete();
            }else if($planAccionAutomatico->count() >= 1){
                $planAccionAutomatico->delete();
                $planAccion->delete();
            }
        }
        
            
        $respuesta = $this->pregunta->where('id', $idPregunta)->delete();

        if($respuesta)
        {
            //CONSULTA LISTA DE CHEQUEO PARA VERIFICAR SI ES AUTOMATICO
            $datosListaChequeo = $this->listaChequeos->where('lista_chequeo.id', '=',$lista_chequeo_id)->first();
            $esAutomatico = ($datosListaChequeo->tipo_ponderados == 1 ? true : false);

            if($esAutomatico)
            {
                $consultaPreguntas = $this->pregunta
                ->where([
                    ['pregunta.lista_chequeo_id','=',$lista_chequeo_id],
                    // ['pregunta.categoria_id', '=',$categoriaId]
                ])
                ->orderBy('orden_lista','DESC');

                $this->PonderadoCalculadoPregunta($consultaPreguntas,$lista_chequeo_id);

                //ACTUALIZACIÓN PREGUNTA
                $this->PonderadoCalculadoCategorias($lista_chequeo_id);
            }

            // RECALCULAR ORDEN DE LAS PREGUNTAS
            $this->OrdenPreguntasCalculo($categoriaId);

            return $this->FinalizarRetorno(
                203,
                $this->MensajeRetorno('La pregunta ',203)
            );  
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La pregunta no pudo eliminarse')
            ); 
        }
    }

    public function TraerInformacionSteps(Request $request)
    {
        $idListaChequeo = $request->get('idListaChequeo');

        $arrayFinal = $this->FuncionInformacionSteps($idListaChequeo);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $arrayFinal
        );
    }

    public function FuncionInformacionSteps($idListaChequeo)
    {
        $categorias = $this->ConsultaCategoriasPorListaChequeo($idListaChequeo);
        $categoriasPreguntas = $this->ConsultaCategoriasPreguntasPorListaChequeo($idListaChequeo);
        $tipoRespuestas = $this->tipoRespuestaCategoria
        ->select(
            'tipo_respuesta_categoria.id AS ID_CATEGORIA',
            'tipo_respuesta_categoria.nombre AS NOMBRE_CATEGORIA',
            'tr.id AS ID_TIPO_RESPUESTA',
            'tr.nombre AS NOMBRE_TIPO_RESPUESTA',
            'tr.icono AS ICONO'
        )
        ->Join('tipo_respuesta AS tr','tr.tipo_respuesta_categoria','tipo_respuesta_categoria.id')
        ->where('tr.estado','=',1)
        ->get();

        $arrayTipoRespuesta = [];
        foreach ($tipoRespuestas as $key => $value) 
        {
            $arrayTipoRespuesta[$value->NOMBRE_CATEGORIA][] = $value;
        }

        $respuestasOpciones = $this->respuestaOpciones->where('estado','=',1)->get();

        $arrayFinal = array
        (
            'categorias' => $categorias,
            'orden' => $categoriasPreguntas,
            'tipoRespuesta' => $arrayTipoRespuesta,
            'opcionesRespuesta' => $respuestasOpciones
        );

        return $arrayFinal;
    }

    public function TraerRespuestasTipo(Request $request)
    {
        $idRespuesta = $request->get('idRespuesta');

        // $respuestas = $this->respuestaPredeterminada
        // ->select(
        //     'tipo_respuesta_ponderado_pred.*',
        //     \DB::raw('IF(r.valor_personalizado IS NULL,"",r.valor_personalizado) AS VALOR_PERSONALIZADO'),
        //     'tipo_respuesta_ponderado_pred.valor_original'
        // ) 
        // ->leftJoin('respuesta AS r','r.tipo_respuesta_ponderado_pred_id','=','tipo_respuesta_ponderado_pred.id')
        // ->where('tipo_respuesta_ponderado_pred.tipo_respuesta_id','=',$idRespuesta)
        // ->get();

        $respuestas = $this->respuestaPredeterminada
        ->select(
            'tipo_respuesta_ponderado_pred.*'
        ) 
        ->where('tipo_respuesta_ponderado_pred.tipo_respuesta_id','=',$idRespuesta)
        ->get();

        $tipoRespuestaDescripcion = $this->tipoRespuesta->where('id','=',$idRespuesta)->first();
        $nombreDescripcion = '';
        $tipoRespuesta = '';
        if(!is_null($tipoRespuestaDescripcion))
        {
            $nombreDescripcion = $tipoRespuestaDescripcion->descripcion;
            $tipoRespuesta = $tipoRespuestaDescripcion->id;
        }
        $arraFinal = array
        (
            'respuestas' => $respuestas,
            'descripcion' => $nombreDescripcion,
            'tipoRespuesta' =>$tipoRespuesta
        );

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $arraFinal
        );
    }

    public function TraerRespuestasTipoModoEdicion(Request $request)
    {
        $idRespuesta = $request->get('idRespuesta');
        $idPregunta = $request->get('idPregunta');

        $respuestas = $this->respuestaPredeterminada
        ->select(
            'tipo_respuesta_ponderado_pred.*',
            \DB::raw('IF(r.valor_personalizado IS NULL,"",r.valor_personalizado) AS VALOR_PERSONALIZADO'),
            'tipo_respuesta_ponderado_pred.valor_original',
            \DB::raw('IF(r.ponderado IS NULL,"",r.ponderado) AS PONDERADO')
        ) 
        ->leftJoin('respuesta AS r','tipo_respuesta_ponderado_pred.id','=','r.tipo_respuesta_ponderado_pred_id')
        ->where([
            ['tipo_respuesta_ponderado_pred.tipo_respuesta_id','=',$idRespuesta],
            ['r.pregunta_id','=',$idPregunta]
        ])
        ->get();

        if(COUNT($respuestas) == 0)
        {
            $respuestas = $this->respuestaPredeterminada
            ->select(
                'tipo_respuesta_ponderado_pred.*',
                \DB::raw('(SELECT "") AS VALOR_PERSONALIZADO'),
                'tipo_respuesta_ponderado_pred.valor_original',
                \DB::raw('(SELECT "") AS PONDERADO')
            ) 
            ->where('tipo_respuesta_ponderado_pred.tipo_respuesta_id','=',$idRespuesta)
            ->get();
        }

        // if(COUNT($respuestas) == 0)
        // {
        //     $respuestas = $this->respuestaPredeterminada
        //     ->select(
        //         'tipo_respuesta_ponderado_pred.*'
        //     ) 
        //     ->where('tipo_respuesta_ponderado_pred.tipo_respuesta_id','=',$idRespuesta)
        //     ->get();
        // }
        
        $tipoRespuestaDescripcion = $this->tipoRespuesta->where('id','=',$idRespuesta)->first();
        $nombreDescripcion = '';
        if(!is_null($tipoRespuestaDescripcion))
        {
            $nombreDescripcion = $tipoRespuestaDescripcion->descripcion;
        }

        $tipoRespuesta = $idRespuesta;

        $arraFinal = array
        (
            'respuestas' => $respuestas,
            'descripcion' => $nombreDescripcion,
            'permitirNA' => (COUNT($respuestas) != 0 ? ($respuestas[0]->APLICANA == 1 ? true : false) : false ),
            'tipoRespuesta' => $tipoRespuesta
        );

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $arraFinal
        );
    }

    public function PonderadoCalculadoPregunta($consultaPreguntas,$lista_chequeo_id)
    {
        if(COUNT($consultaPreguntas->get()) != 0)
        {
            $totalPreguntas = COUNT($consultaPreguntas->get());
            $valorParaCadaPregunta = FLOOR(((100 / $totalPreguntas) * 100)) / 100;
            
            if($totalPreguntas != 1)
            {
                $valorTotal = ($totalPreguntas * $valorParaCadaPregunta);
                // $valorRestante = (100 - ($valorTotal - $valorParaCadaPregunta));
                $respuestaUpdate = $this->pregunta
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                    // ['categoria_id','=',$idCategoria]
                ])
                ->update([
                    'ponderado' => $valorParaCadaPregunta
                ]);

                $ultimoElemento = $consultaPreguntas->latest('orden_lista')->first();

                // $actualizarUltimo = $this->pregunta
                // ->where('id','=',$ultimoElemento->id)
                // ->update([
                //     'ponderado' => $valorRestante
                // ]);

            }else
            {
                $respuestaUpdate = $this->pregunta
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id],
                    // ['categoria_id','=',$idCategoria]
                ])
                ->update([
                    'ponderado' => $valorParaCadaPregunta
                ]);
            }

        }
        
    }

    public function OrdenPreguntasCalculo($idCategoria)
    {
        $preguntasCategoria = $this->pregunta->where('categoria_id','=',$idCategoria)->orderby('id','ASC')->get();

        $numeroPregunta = 1;
        foreach ($preguntasCategoria as $key => $pregunta) 
        {
            $arrayActualizar = [
                'orden_lista' => $numeroPregunta,
            ];
            
            $respuestaUpdate = $this->pregunta->where('id','=',$pregunta->id)->update($arrayActualizar);
            $numeroPregunta = $numeroPregunta + 1;
        }
    }

    public function CrearPregunta(Request $request)
    {
        $objetoRecibido = json_decode($request->get('objetoEnviar'));
        //dd($objetoRecibido);
        $nombre = $objetoRecibido->stepsEnviar->stepUno->pregunta;
        $ponderado = $objetoRecibido->stepsEnviar->stepUno->ponderado;
        $permiteNoAplica = $objetoRecibido->stepsEnviar->stepUno->permiteNoAplica;
        $categoriaId = ($objetoRecibido->stepsEnviar->stepUno->preguntaEnCategoria == false ? NULL : $objetoRecibido->stepsEnviar->stepUno->preguntaEnCategoria); 
        $lista_chequeo_id = $objetoRecibido->idListaChequeo;
        $IdtipoRespuesta = $objetoRecibido->stepsEnviar->stepDos->idRespuesta;

        $ordenMaximo = 0;
        if(is_null($categoriaId))
        {
            $ordenMaximo = $this->pregunta->where('lista_chequeo_id','=',$lista_chequeo_id)->max('orden_lista');
            if(is_null($ordenMaximo)) $ordenMaximo = 0;
        }
        else
        {
            $categoriaId = $objetoRecibido->stepsEnviar->stepUno->categoriaId;
            $ordenMaximo = $this->pregunta->where([
                ['lista_chequeo_id','=',$lista_chequeo_id],
                ['categoria_id','=',$categoriaId]
            ])->max('orden_lista');
        }

        $orden_lista = $ordenMaximo + 1;
        
        $arrayInsertar = [
            'nombre' => $nombre, 
            'ponderado' => $ponderado,
            'categoria_id' => $categoriaId,
            'orden_lista' => $orden_lista,
            'lista_chequeo_id' => $lista_chequeo_id,
            'tipo_respuesta_id' => $IdtipoRespuesta,
            'permitir_noaplica' => $permiteNoAplica
        ];
        
        $pregunta = new $this->pregunta;
        $pregunta->fill($arrayInsertar);

        if($pregunta->save())
        {
            //GUARDAR PERSONALIZADA (SI APLICA) SI NO SE GUARDA CON LO PREDETERMINADO
            $idPregunta = $pregunta->id;
            $respuestasPersonalizadas = $objetoRecibido->stepsEnviar->stepTres->personalizadas;

            foreach ($respuestasPersonalizadas as $key => $itemPersonalizado) 
            {
                $ponderadoConsulta = $this->respuestaPredeterminada->where('id','=',$itemPersonalizado->idPredeterminado)->first();
                if($itemPersonalizado->valorPersonalizado != '')
                {
                    
                    if($IdtipoRespuesta == 4) //SI ES MULTIPLE SIEMPRE TIENE UN VALOR PERSONALIZADO
                    {
                        $ponderadoConsulta = $this->respuestaPredeterminada->where('tipo_respuesta_id','=',$itemPersonalizado->idPredeterminado)->first();

                        $respuesta = new $this->respuesta;
                        $respuesta->fill(
                        [
                            'tipo_respuesta_ponderado_pred_id' => $ponderadoConsulta->id, 
                            'valor_personalizado' => $itemPersonalizado->valorPersonalizado,
                            'ponderado' => $itemPersonalizado->valorPersonalizadoPonderado,
                            'pregunta_id' => $idPregunta
                        ]);

                        $respuesta->save();
                    }
                    else
                    {
                        $respuesta = new $this->respuesta;
                        $respuesta->fill(
                        [
                            'tipo_respuesta_ponderado_pred_id' => $itemPersonalizado->idPredeterminado, 
                            'valor_personalizado' => $itemPersonalizado->valorPersonalizado,
                            'ponderado' => $ponderadoConsulta->ponderado,
                            'pregunta_id' => $idPregunta
                        ]);
                        $respuesta->save();


                        if ($IdtipoRespuesta == 15) {
                            DB::table('rangos_pregunta')->where('id_pregunta',$idPregunta)->delete();

                            DB::table('rangos_pregunta')->insert([
                                'id_pregunta' => $idPregunta,
                                'valor_min'    => $itemPersonalizado->valorMin,
                                'valor_max' => $itemPersonalizado->valorMax,
                            ]);
                        }
                    }
                    
                }
                else
                {
                    $respuesta = new $this->respuesta;
                    $respuesta->fill(
                    [
                        'tipo_respuesta_ponderado_pred_id' => $itemPersonalizado->idPredeterminado, 
                        'valor_personalizado' => $itemPersonalizado->valorPredeterminado,
                        'ponderado' => $ponderadoConsulta->ponderado,
                        'pregunta_id' => $idPregunta
                    ]);

                    $respuesta->save();
                }
            }

            // INGRESO DE PREGUNTA OPCIÓN RESPUESTA
            $opcionesRespuestaArray = $objetoRecibido->stepsEnviar->stepCuatro->opcionesRespuesta;

            foreach ($opcionesRespuestaArray as $key => $opcRespuesta) 
            {

                //Valido si ya hay preguntas en la tabla pregunta_preguntarespuestaopcion
                $preguntaOpcionRespuesta = NULL;
                if($this->preguntaOpcionRespuesta->where('pregunta_id','=', $pregunta)->exists()){
                    $preguntaOpcionRespuesta = $this->preguntaOpcionRespuesta->where('pregunta_id','=', $pregunta);
                    $preguntaOpcionRespuesta->pregunta_id = $idPregunta;
                    $preguntaOpcionRespuesta->pregunta_respuesta_opcion = $opcRespuesta->idopcionrespuesta;
                }else{
                    $preguntaOpcionRespuesta = new $this->preguntaOpcionRespuesta;
                    $preguntaOpcionRespuesta->fill(
                    [
                        'pregunta_id' => $idPregunta, 
                        'pregunta_respuesta_opcion' => $opcRespuesta->idopcionrespuesta
                    ]);
                }

                if($preguntaOpcionRespuesta->save())
                {
                    // INGRESO DE PLAN DE ACCIÓN
                    $aplicaPlanDeAccion = $objetoRecibido->stepsEnviar->stepCuatro->aplicaPlanAccion;
                    
                    if($aplicaPlanDeAccion){ 
                        
                        $tipoPlanAccion = $objetoRecibido->stepsEnviar->stepCuatro->tipoPlanAccion;
                        if($tipoPlanAccion == 'automatico') //PLAN DE ACCION AUTOMATICO
                        {
                            $idRespuesta = $objetoRecibido->stepsEnviar->stepCuatro->idRespuesta;
                            $planDeAccionDescripcion = $objetoRecibido->stepsEnviar->stepCuatro->planDeAccion;
                            if($opcRespuesta->idopcionrespuesta == 4) //ID PLAN DE ACCIÓN
                            {
                                if($IdtipoRespuesta == 4) //SI ES MULTIPLE 
                                {
                                    $consultaRespuesta = $this->respuesta->where([
                                        ['valor_personalizado','=', $objetoRecibido->stepsEnviar->stepCuatro->idRespuesta],
                                        ['pregunta_id','=',$idPregunta]
                                    ])->first();
    
                                    $idRespuesta = $consultaRespuesta->id;
                                }
                                else
                                {
                                    $idRespuestaTabla = $this->respuesta->where([
                                        ['tipo_respuesta_ponderado_pred_id','=',$idRespuesta],
                                        ['pregunta_id','=',$idPregunta]
                                    ])->first();

                                    $idRespuesta = $idRespuestaTabla->id;
                                }
                                
                                $planAccion = new $this->planAccion;
                                $planAccion->fill([
                                    'tipo_pa' => 1,
                                    'obligatorio' => 1,
                                    'alerta' => 1,
                                    'pregunta_id' => $idPregunta,
                                    'respuesta_id' => $idRespuesta
                                ]);

                                $planAccion->save();


                                $planDeAccionAutomatico = new $this->planDeAccionAutomatico;
                                $planDeAccionAutomatico->fill(
                                [
                                    'plan_accion_id' => $planAccion->id,
                                    'plan_accion_descripcion' => $planDeAccionDescripcion
                                ]);

                                $planDeAccionAutomatico->save();
                                
                            }

                        }else if($tipoPlanAccion == 'manual'){ //PLAN DE ACCION MANUAL
                            if($opcRespuesta->idopcionrespuesta == 4 OR $opcRespuesta->idopcionrespuesta == '4') //ID PLAN DE ACCIÓN
                            {
                                $planAccion = new $this->planAccion;
                                $planAccion->fill([
                                    'tipo_pa' => 2,
                                    'obligatorio' => 1,
                                    'alerta' => 1,
                                    'pregunta_id' => $idPregunta,
                                    'respuesta_id' => null
                                ]);
                                $planAccion->save();

                                //AGREGO LAS OPCIONES DEL PLAN DE ACCION MANUAL
                                $dataRecibidaPlanAccionManual = $objetoRecibido->stepsEnviar->stepCuatro->planAccionData;
                                foreach($dataRecibidaPlanAccionManual as $key => $value){
                                    $planAccionManual = new $this->planAccionManual;
                                    $planAccionManual->fill([
                                        'plan_accion_id' => $planAccion->id,
                                        'requerido' => $value->requerido,
                                        'plan_accion_man_opc_id' => $value->valor
                                    ]);
                                    $planAccionManual->save();
                                }
                                
                            }
                        }
                    }
                    
                }
            }

            //CONSULTA LISTA DE CHEQUEO PARA VERIFICAR SI ES AUTOMATICO
            $datosListaChequeo = $this->listaChequeos
            ->where([
                ['lista_chequeo.id', '=',$lista_chequeo_id]
            ])->first();
            
            $esAutomatico = ($datosListaChequeo->tipo_ponderados == 1 ? true : false);

            if($esAutomatico)
            {
                $consultaPreguntas = $this->pregunta
                ->where([
                    ['pregunta.lista_chequeo_id','=',$lista_chequeo_id],
                    // ['pregunta.categoria_id', '=',$categoriaId]
                ])
                ->orderBy('orden_lista','DESC');

                $this->PonderadoCalculadoPregunta($consultaPreguntas,$lista_chequeo_id);

                //CALCULO PARA CATEGORÍAS
                $this->PonderadoCalculadoCategorias($lista_chequeo_id);
            }

            // RECALCULAR ORDEN DE LAS PREGUNTAS
            $this->OrdenPreguntasCalculo($categoriaId);

            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('La pregunta ',200)
            );
        }
        else
        {
            return $this->FinalizarRetorno(
                402,
                $this->MensajeRetorno('La pregunta  ',402)            
            );
        }
    }

    public function ActualizarPregunta(Request $request)
    {
        $objetoRecibido = json_decode($request->get('objetoEnviar'));
        $idPregunta = $request->get('idPregunta');
        //dd($objetoRecibido);
        $nombre = $objetoRecibido->stepsEnviar->stepUno->pregunta;
        $ponderado = $objetoRecibido->stepsEnviar->stepUno->ponderado;
        $permiteNoAplica = $objetoRecibido->stepsEnviar->stepUno->permiteNoAplica;
        $categoriaId = ($objetoRecibido->stepsEnviar->stepUno->preguntaEnCategoria == false ? NULL : $objetoRecibido->stepsEnviar->stepUno->categoriaId); 
        $lista_chequeo_id = $objetoRecibido->idListaChequeo;
        $IdtipoRespuesta = $objetoRecibido->stepsEnviar->stepDos->idRespuesta;

        $cantidad = $this->pregunta->Join('lista_chequeo_ejecutadas AS lce','lce.lista_chequeo_id','=','pregunta.lista_chequeo_id')->where('pregunta.id','=',$idPregunta)->count();
        
        if($cantidad != 0)
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La pregunta no puede actualizarse porque ya tiene registros de ejecución')
            ); 
        }

        $arrayActualizar = [
            'nombre' => $nombre, 
            'ponderado' => $ponderado,
            'categoria_id' => $categoriaId,
            // 'orden_lista' => $orden_lista,
            'lista_chequeo_id' => $lista_chequeo_id,
            'tipo_respuesta_id' => $IdtipoRespuesta,
            'permitir_noaplica' => $permiteNoAplica
        ];
        
        $respuestaUpdate = $this->pregunta->where('id','=',$idPregunta)->update($arrayActualizar);
        if($respuestaUpdate)
        {
            //GUARDAR PERSONALIZADA (SI APLICA) SI NO SE GUARDA CON LO PREDETERMINADO
            $respuestasPersonalizadas = $objetoRecibido->stepsEnviar->stepTres->personalizadas;
            $respuestaExist = $this->respuesta->where([['pregunta_id', $idPregunta]]);

            if($respuestaExist->count() >= 1)
                $respuestaExist->delete();

            foreach ($respuestasPersonalizadas as $key => $itemPersonalizado) 
            {
                $ponderadoConsulta = $this->respuestaPredeterminada->where('id','=',$itemPersonalizado->idPredeterminado)->first();
                if($itemPersonalizado->valorPersonalizado != '')
                {
                    if($IdtipoRespuesta == 4) //SI ES MULTIPLE SIEMPRE TIENE UN VALOR PERSONALIZADO
                    {
                        $ponderadoConsulta = $this->respuestaPredeterminada->where('tipo_respuesta_id','=',$itemPersonalizado->idPredeterminado)->first();

                        $respuesta = new $this->respuesta;
                        $respuesta->fill(
                        [
                            'tipo_respuesta_ponderado_pred_id' => $ponderadoConsulta->id, 
                            'valor_personalizado' => $itemPersonalizado->valorPersonalizado,
                            'ponderado' => $itemPersonalizado->valorPersonalizadoPonderado,
                            'pregunta_id' => $idPregunta
                        ]);

                        $respuesta->save();
                    }
                    else
                    {
                        DB::table('respuesta')->where('pregunta_id',$idPregunta)->delete(); 
                        $respuesta = new $this->respuesta;
                        $respuesta->fill(
                        [
                            'tipo_respuesta_ponderado_pred_id' => $itemPersonalizado->idPredeterminado, 
                            'valor_personalizado' => $itemPersonalizado->valorPersonalizado,
                            'ponderado' => $ponderadoConsulta->ponderado,
                            'pregunta_id' => $idPregunta
                        ]);
                        $respuesta->save();

                        if ($IdtipoRespuesta == 15) {
                            DB::table('rangos_pregunta')->where('id_pregunta',$idPregunta)->delete();

                            DB::table('rangos_pregunta')->insert([
                                'id_pregunta' => $idPregunta,
                                'valor_min'    => $itemPersonalizado->valorMin,
                                'valor_max' => $itemPersonalizado->valorMax,
                            ]);
                        }
                    }
                    
                    
                }
                else
                {
                    $respuesta = new $this->respuesta;
                    $respuesta->fill(
                    [
                        'tipo_respuesta_ponderado_pred_id' => $itemPersonalizado->idPredeterminado, 
                        'valor_personalizado' => $itemPersonalizado->valorPredeterminado,
                        'ponderado' => $ponderadoConsulta->ponderado,
                        'pregunta_id' => $idPregunta
                    ]);

                    $respuesta->save();
                }
            }
            
            // INGRESO DE PREGUNTA OPCIÓN RESPUESTA
            $opcionesRespuestaArray = $objetoRecibido->stepsEnviar->stepCuatro->opcionesRespuesta;

            $this->preguntaOpcionRespuesta->where('pregunta_id', $idPregunta)->delete();

            foreach ($opcionesRespuestaArray as $key => $opcRespuesta) 
            {
                $preguntaOpcionRespuesta = new $this->preguntaOpcionRespuesta;
                $preguntaOpcionRespuesta->fill(
                [
                    'pregunta_id' => $idPregunta, 
                    'pregunta_respuesta_opcion' => $opcRespuesta->idopcionrespuesta
                ]);

                if($preguntaOpcionRespuesta->save())
                {
                    // INGRESO DE PLAN DE ACCIÓN
                    $aplicaPlanDeAccion = $objetoRecibido->stepsEnviar->stepCuatro->aplicaPlanAccion;

                    //FUNCION PARA ELIMINAR PLAN DE ACCION EN CASO DE QUE EXISTA EL REGISTRO EN LA BD Y EL USUARIO HAYA DESMARCADO LA OPCION DE PLAN DE ACCION
                    if($aplicaPlanDeAccion == false)
                        $this->limpiarTablaPlanAccion($idPregunta);

                    if($aplicaPlanDeAccion)
                    {
                        if($objetoRecibido->stepsEnviar->stepCuatro->tipoPlanAccion == 'automatico'){
                            
                            //VALIDO QUE NO EXISTA PLAN DE ACCION MANUAL
                            $existPlanAccionManual = $this->planAccion->where('pregunta_id', '=', $idPregunta);
                            if($existPlanAccionManual->count() >= 1 && $existPlanAccionManual->first()->tipo_pa == 2){
                                //Borro los datos en plan_accion_manual
                                
                                $planAccionManualTabla = $this->planAccionManual->where('plan_accion_id','=',$existPlanAccionManual->first()->id);
                                $existPlanAccionManual->delete(); //Borro plan de accion
                                $planAccionManualTabla->delete(); //Borro los registros en la tabla plan_accion_manual
                            }

                            if($IdtipoRespuesta == 4) //SI ES MULTIPLE 
                            {
                                $consultaRespuesta = $this->respuesta->where([
                                    ['valor_personalizado','=', $objetoRecibido->stepsEnviar->stepCuatro->idRespuesta],
                                    ['pregunta_id','=',$idPregunta]
                                ])->first();

                                $idRespuesta = $consultaRespuesta->id;
                            }
                            else
                                $idRespuesta = $objetoRecibido->stepsEnviar->stepCuatro->idRespuesta;
                            
                            $planDeAccionDescripcion = $objetoRecibido->stepsEnviar->stepCuatro->planDeAccion;
                            
                            if($opcRespuesta->idopcionrespuesta == 4) //ID PLAN DE ACCIÓN
                            {
                                
                                if($IdtipoRespuesta == 4) //SI ES MULTIPLE
                                    $respuestaId = $idRespuesta;
                                else
                                {
                                    $idRespuestaTabla = $this->respuesta->where([
                                        ['tipo_respuesta_ponderado_pred_id','=',$idRespuesta],
                                        ['pregunta_id','=',$idPregunta]
                                    ])->first();

                                    $respuestaId = $idRespuestaTabla->id;
                                }
                                //Agrego los nuevos datos para Plan de Accion Manual
                                $planAccion = new $this->planAccion;
                                $planAccion->fill([
                                    'tipo_pa' => 1,
                                    'obligatorio' => 1,
                                    'alerta' => 1,
                                    'pregunta_id' => $idPregunta,
                                    'respuesta_id' => $respuestaId
                                ]);
                                $planAccion->save();

                                $planDeAccionAutomatico = new $this->planDeAccionAutomatico;
                                $planDeAccionAutomatico->fill(
                                [
                                    'plan_accion_id' => $planAccion->id, 
                                    'plan_accion_descripcion' => $planDeAccionDescripcion
                                ]);
    
                                $planDeAccionAutomatico->save();
                                
                            }
                        }else{ //SI ES MANUAL ENTONCES HAGO LO SIGUIENTE
                            
                             //VALIDO QUE NO EXISTA PLAN DE ACCION MANUAL
                             $existPlanAccion = $this->planAccion->where('pregunta_id', '=', $idPregunta);
                             if($existPlanAccion->count() > 0 ){
                                $existPlanAccion->delete(); //Borro plan de accion
                                 
                             }

                             //Agrego los nuevos datos para Plan de Accion Manual
                             $planAccion = new $this->planAccion;
                                $planAccion->fill([
                                    'tipo_pa' => 2,
                                    'obligatorio' => 1,
                                    'alerta' => 1,
                                    'pregunta_id' => $idPregunta,
                                    'respuesta_id' => null
                                ]);
                                $planAccion->save();

                                //AGREGO LAS OPCIONES DEL PLAN DE ACCION MANUAL tabla plan_accion_manual
                                $dataRecibidaPlanAccionManual = $objetoRecibido->stepsEnviar->stepCuatro->planAccionData;
                                foreach($dataRecibidaPlanAccionManual as $key => $value){
                                    $planAccionManual = new $this->planAccionManual;
                                    $planAccionManual->fill([
                                        'plan_accion_id' => $planAccion->id,
                                        'requerido' => $value->requerido,
                                        'plan_accion_man_opc_id' => $value->valor
                                    ]);
                                    $planAccionManual->save();
                                }


                        }
                       

                    }
                }
            }

            //CONSULTA LISTA DE CHEQUEO PARA VERIFICAR SI ES AUTOMATICO
            $datosListaChequeo = $this->listaChequeos
            ->where([
                ['lista_chequeo.id', '=',$lista_chequeo_id]
            ])->first();

            $esAutomatico = ($datosListaChequeo->tipo_ponderados == 1 ? true : false);

            if($esAutomatico)
            {
                $consultaPreguntasCategorias = $this->categoria
                ->where([
                    ['lista_chequeo_id','=',$lista_chequeo_id]
                ])
                ->orderBy('orden_lista','DESC')->get();
                
                foreach ($consultaPreguntasCategorias as $key => $itemCategoria) 
                {
                    $consultaPreguntas = $this->pregunta
                    ->where([
                        ['pregunta.lista_chequeo_id','=',$lista_chequeo_id],
                        // ['pregunta.categoria_id', '=',$itemCategoria->id]
                    ])
                    ->orderBy('orden_lista','DESC');
    
                    $this->PonderadoCalculadoPregunta($consultaPreguntas,$lista_chequeo_id);    

                    //CALCULO PARA CATEGORÍAS
                    $this->PonderadoCalculadoCategorias($lista_chequeo_id);
                }
                
            }

            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('La pregunta ',201)
            );
        }
        else
        {
            return $this->FinalizarRetorno(
                402,
                $this->MensajeRetorno('La pregunta  ',402)            
            );
        }
    }

    private function limpiarTablaPlanAccion($idpregunta){
        $planAccion = $this->planAccion->where('pregunta_id', '=', $idpregunta)->first();
        //dd($planAccion);
        if($planAccion != null){
            $planAccion->delete();
        }
    }

    public function InsertarEncabezado(Request $request)
    {
        $fecha = $request->get('fecha');
        $asociado = $request->get('asociado');
        $listaChequeo = $request->get('idListaChequeo');
        $entidad_evaluada_opcion = $request->get('entidad_evaluada_opcion');
        
        $configuracion = $this->configuracionEjecucion->where('lista_chequeo_id','=',$listaChequeo)->first();

        $arrayActualizar = [
            'entidad_evaluada' => $asociado
        ];

        $respuestaUpdate = $this->listaChequeos->where('id','=',$listaChequeo)->update($arrayActualizar);

        if(!$this->listaEncabezado->where('lista_chequeo_id', '=',$listaChequeo)->exists())
        {
            $arrayInsertar = [
                'fecha' => $fecha, 
                'entidad_evaluada_opcion' => $entidad_evaluada_opcion,
                'lista_chequeo_id' => $listaChequeo
            ];
            
            $listaEncabezado = new $this->listaEncabezado;
            $listaEncabezado->fill($arrayInsertar);
    
            if($listaEncabezado->save())
            {
                $respuestaUpdate = $this->listaChequeos->where('id','=',$listaChequeo)->update([
                    'tipo_ponderados' => 0
                ]);

                return $this->FinalizarRetorno(
                    206,
                    $this->MensajeRetorno('',206,'Lista de chequeo finalizada'),
                    $configuracion
                );
            }else
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'No se logró finaliza la lista de chequeo')
                );
            }
        }else
        {
            $arrayActualizar = [
                'fecha' => $fecha, 
                'entidad_evaluada_opcion' => $entidad_evaluada_opcion
            ];

            $respuestaUpdate = $this->listaEncabezado->where('lista_chequeo_id','=',$listaChequeo)->update($arrayActualizar);
            $respuestaUpdate = $this->listaChequeos->where('id','=',$listaChequeo)->update([
                'tipo_ponderados' => 0
            ]);

            return $this->FinalizarRetorno(
                206,
                $this->MensajeRetorno('',206,'Lista de chequeo finalizada'),
                $configuracion
            );
        }
        
        
    }

    public function ActualizarConfiguracion(Request $request)
    {
        $frecuencia = $request->get('frecuencia');
        $cantidad = ($request->get('cantidad') == 0 ? NULL : $request->get('cantidad'));
        $idListaChequeo = $request->get('idListaChequeo');
        $favorito = $request->get('favorito');
        if($favorito == 1)
        {
             //CONSULTA FAVORITO
            $updateListaFavorito = $this->listaChequeos
            ->Join('usuario AS us','us.id','=','lista_chequeo.usuario_id')
            ->where('us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id)
            ->update(
            [
                'favorita' => 0
            ]);

            
            $updateListaFavorito = $this->listaChequeos->where('id','=',$idListaChequeo)
            ->update(
            [
                'favorita' => $favorito
            ]);
        }

        $url = (\Request::root().'/listachequeo/ejecucion/'.encrypt($idListaChequeo));
        $arrayActualizar = [
            'link' => $url,
            'frecuencia_ejecucion' => $frecuencia, 
            'cant_ejecucion' => $cantidad
        ];
        
        $respuestaUpdate = $this->configuracionEjecucion->where('lista_chequeo_id','=',$idListaChequeo)->update($arrayActualizar);
        if($respuestaUpdate)
        {
            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('La configuración ',201)
            );
        }
    }

    public function CrearEtiqueta(Request $request)
    {
        $nombreEtiqueta = $request->get('nombreEtiqueta');
        $descripcion = $request->get('descripcion');

        $arrayInsertar = [
            'nombre' => $nombreEtiqueta, 
            'descripcion' => $descripcion,
            'cuenta_principal_id' => auth()->user()->cuenta_principal_id
        ];

        $categoriaEtiquetas = new $this->categoriaEtiquetas;
        $categoriaEtiquetas->fill($arrayInsertar);

        if($categoriaEtiquetas->save())
        {

            $etiquetas = $this->categoriaEtiquetas->where('cuenta_principal_id','=',auth()->user()->cuenta_principal_id)->get();

            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('La etiqueta ',200),
                array(
                    'idCreatoOpcion' => $categoriaEtiquetas->id,
                    'etiquetas' => $etiquetas
                )
            );
        }
    }

    public function TraerEtiquetas(Request $request)
    {
        $etiquetas = $this->categoriaEtiquetas->where('cuenta_principal_id','=',auth()->user()->cuenta_principal_id)->get();
        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos ',202),
            $etiquetas
        );
    }

    public function EliminarEtiqueta(Request $request)
    {
        $idEtiqueta = $request->get('idEtiqueta');

        if(!$this->categoria->where('id_etiqueta','=',$idEtiqueta)->exists())
        {
            $respuesta = $this->categoriaEtiquetas->where('id', $idEtiqueta)->delete();

            $etiquetas = $this->categoriaEtiquetas->where('cuenta_principal_id','=',auth()->user()->cuenta_principal_id)->get();
            
            return $this->FinalizarRetorno(
                206,
                $this->MensajeRetorno('',206,'La etiqueta se ha eliminado correctamente'),
                $etiquetas
            );
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La etiqueta no se pudo eliminar por que está en usado')
            );
        }
        
        
    }
    
    // PAGINA PRINCIPAL LISTAS DE CHEQUEO
    public function ConsultarListasDeChequeo(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = []; // ;json_decode($request->get('arrayFiltros'));
        
        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionAdministrador($paginacion,$filtros);        
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionResponsableEmpresa($esResponsableEmpresa->id,$paginacion,$filtros);        

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionResponsablEstablecimiento($esResponsableEstablecimiento->id,$paginacion,$filtros);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionColaborador(auth()->user()->id,$paginacion,$filtros);

                break;
            
            default:

                break;
        };

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $listasDeChequeo
        );
    }

    public function EliminarListaChequeo(Request $request)
    {
        $idListaChequeo = $request->get('idListaChequeo');

        //SE DEBE VALIDAR SI TIENE REGISTROS  PARA SABER SI SE PEUDE BORRAR O NO
        $cantidad = $this->listaEjecutada->where('lista_chequeo_id','=',$idListaChequeo)->count();
        if($cantidad != 0)
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La lista de chequeo no pudo eliminarse por que ya existen registros en ejecución')
            ); 
        }

        $cantidadModelo = $this->modelo->where('lista_chequeo_id','=',$idListaChequeo)->count();
        if($cantidadModelo != 0)
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La lista de chequeo no pudo eliminarse por que está siendo usada por un modelo')
            ); 
        }

        $respuesta = $this->listaChequeos->where('id', $idListaChequeo)->delete();

        if($respuesta)
        {
            return $this->FinalizarRetorno(
                203,
                $this->MensajeRetorno('La lista de chequeo ',203)
            );  
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La lista de chequeo no pudo eliminarse')
            ); 
        }
    }

    public function ConsultaLinkInformacionTarjeta(Request $request)
    {
        $idListaChequeo = $request->get('idListaChequeo');

        $informacionAuditoria = $this->listaChequeoConfiguracion->where('lista_chequeo_id','=', $idListaChequeo)->first();

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $informacionAuditoria
        );
    }

    public function ConsultaListaChequeoScroll(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = []; // ;json_decode($request->get('arrayFiltros'));

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionAdministrador($paginacion,$filtros);        
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionResponsableEmpresa($esResponsableEmpresa->id,$paginacion,$filtros);        

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionResponsablEstablecimiento($esResponsableEstablecimiento->id,$paginacion,$filtros);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $listasDeChequeo = $this->FuncionTraerListasDeChequeoPorPaginacionColaborador(auth()->user()->id,$paginacion,$filtros);

                break;
            
            default:

                break;
        };

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $listasDeChequeo
        );
    }

    public function EjecutarListaDeChequeo(Request $request)
    {
        $idListaChequeo = $request->get('idListaChequeo');

        // VALIDACIÓN SI ESTÁ AL DÍA CON EL PAGO
        $planAlDia = $this->FuncionValidarSiEstaAlDia();
        if(!$planAlDia)
        {
            return $this->FinalizarRetorno(
                407,
                $this->MensajeRetorno('',407,'El plan del administrador no se encuentra al día con el pago, comunícate con el administrador de la cuenta'),
                auth()->user()->perfil_id
            );
        }


        //VALIDACIÓN SI CUMPLE CON EL ALMACENAMIENTO



        //VALIDACIÓN SI PUEDE EJECUTAR LA LISTA DE CHEQUEO
        $planPuedeEjecutar = $this->FuncionValidadSiPuedeEjecutar();
        if(!$planPuedeEjecutar)
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'El plan actual ha llegado al límite de ejecución de listas, cambia de plan o comunícate con el administrador de la cuenta'),
                auth()->user()->perfil_id
            );
        }
        
        $resultadoDeValidacion = $this->ValidarSiPuedeRealizarEjecucion($idListaChequeo,auth()->user()->id);
        if(!$resultadoDeValidacion)
        {
            return $this->FinalizarRetorno(
                402,
                $this->MensajeRetorno('Datos ',402)
            );
        }
        
         $arrayInsertar = [
            'lista_chequeo_id' => $idListaChequeo, 
            'usuario_id' => auth()->user()->id,
            'fecha_realizacion' => date('Y-m-d')
        ];

        $listaEjecutada = new $this->listaEjecutada;
        $listaEjecutada->fill($arrayInsertar);
        
        if($listaEjecutada->save())
        {
            $idListaEjecutada = $listaEjecutada->id;

            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('La lista de chequeo',200),
                $idListaEjecutada
            );
        }
        
    }

    public function ValidarSiPuedeRealizarEjecucion($idListaChequeo,$idUsuario)
    {
        
        // $configuracion = $this->listaEjecutada
        // ->select('lcce.*')
        // ->Join('lista_chequeo_configuracion_ejecucion AS lcce','lcce.lista_chequeo_id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        // ->where('lista_chequeo_ejecutadas.id','=',$idEjecucion)->first();

        $configuracion = $this->listaChequeoConfiguracion
        ->where('lista_chequeo_id','=',$idListaChequeo)
        ->first();
        
        
        $idChequeo = $idListaChequeo;

        $puedeEjecutar = true;
        switch ($configuracion->frecuencia_ejecucion) {
            case 0: // INDEFINIDA
                $puedeEjecutar = true;
                break;

            case 1: // DIARIO

                $resultado = \DB::select('SELECT 
                (CASE WHEN  lcce.frecuencia_ejecucion=0 THEN "Indefinida" 
                WHEN  lcce.frecuencia_ejecucion=1 THEN "Diaria" 
                WHEN  lcce.frecuencia_ejecucion=2 THEN "Mensual" 
                WHEN  lcce.frecuencia_ejecucion=3 THEN "Anual" 
                END) as frecuencia_nombre,
                lcce.frecuencia_ejecucion AS conf_frecuencia_ejecucion,
                lcce.cant_ejecucion AS conf_cantidad_ejecucion,
                CURDATE() AS hoy_sistema,
                lce.fecha_realizacion AS hoy_fecha_realizacion,
                (SELECT COUNT(*)  FROM lista_chequeo_ejecutadas lce INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id WHERE (lce.fecha_realizacion=curdate()) AND  lce.usuario_id=:idUsuarioUno AND lce.lista_chequeo_id=:idListaChequeoUno AND  (lce.estado=1 OR lce.estado=2)) AS ejec_cant_dia,
                IF((SELECT COUNT(*)  FROM lista_chequeo_ejecutadas lce INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id WHERE (lce.fecha_realizacion=curdate()) AND  lce.usuario_id=:idUsuarioDos AND lce.lista_chequeo_id=:idListaChequeoDos AND  (lce.estado=1 OR lce.estado=2))<lcce.cant_ejecucion,"TRUE","FALSE") AS puede_ejecutar
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id
                 WHERE 
                lce.usuario_id=:idUsuario 
                 AND lce.lista_chequeo_id=:idChequeo
                 AND  (lce.estado=1 
                 OR lce.estado=2) 
                 GROUP BY lcce.frecuencia_ejecucion',
                 [
                     'idUsuario' => $idUsuario,
                     'idChequeo' => $idChequeo,
                     'idUsuarioUno' => $idUsuario,
                     'idUsuarioDos' => $idUsuario,
                     'idListaChequeoUno' => $idChequeo,
                     'idListaChequeoDos' => $idChequeo
                 ]);
                
                if(COUNT($resultado) == 0)
                    $puedeEjecutar = true;
                else
                    $puedeEjecutar = ($resultado[0]->puede_ejecutar == "TRUE" ? true : false);
                
                break;

            case 2: // MENSUAL
                
                $resultado = \DB::select('SELECT 
                (CASE WHEN  lcce.frecuencia_ejecucion=0 THEN "Indefinida" 
                WHEN  lcce.frecuencia_ejecucion=1 THEN "Diaria" 
                WHEN  lcce.frecuencia_ejecucion=2 THEN "Mensual" 
                WHEN  lcce.frecuencia_ejecucion=3 THEN "Anual" 
                END) as frecuencia_nombre,
                lcce.frecuencia_ejecucion AS conf_frecuencia_ejecucion,
                lcce.cant_ejecucion AS conf_cantidad_ejecucion,
                MONTH(CURRENT_DATE()) AS mes_sistema,
                MONTH(lce.fecha_realizacion) AS mes_fecha_realizacion,
                (SELECT COUNT(*)  FROM lista_chequeo_ejecutadas lce INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id WHERE (MONTH(lce.fecha_realizacion)=MONTH(lce.fecha_realizacion)) AND  lce.usuario_id=:idUsuarioUno AND lce.lista_chequeo_id=:idListaChequeoUno AND  (lce.estado=1 OR lce.estado=2) ) AS ejec_cant_mes,
                IF((SELECT COUNT(*)  FROM lista_chequeo_ejecutadas lce INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id WHERE (MONTH(lce.fecha_realizacion)=MONTH(lce.fecha_realizacion)) AND  lce.usuario_id=:idUsuarioDos AND lce.lista_chequeo_id=:idListaChequeoDos AND  (lce.estado=1 OR lce.estado=2) 
                )<lcce.cant_ejecucion,"TRUE","FALSE") AS puede_ejecutar
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id
                 WHERE 
                lce.usuario_id=:idUsuario 
                 AND lce.lista_chequeo_id=:idChequeo
                 AND  (lce.estado=1 
                 OR lce.estado=2) 
                 GROUP BY lcce.frecuencia_ejecucion',[
                     'idUsuario' => $idUsuario,
                     'idChequeo' => $idChequeo,
                     'idUsuarioUno' => $idUsuario,
                     'idUsuarioDos' => $idUsuario,
                     'idListaChequeoUno' => $idChequeo,
                     'idListaChequeoDos' => $idChequeo
                 ]);
                
                 if(COUNT($resultado) == 0)
                    $puedeEjecutar = true;
                else
                    $puedeEjecutar = ($resultado[0]->puede_ejecutar == "TRUE" ? true : false);

                break;

            case 3: // ANUAL
                
                $resultado = \DB::select('SELECT 
                (CASE WHEN  lcce.frecuencia_ejecucion=0 THEN "Indefinida" 
                WHEN  lcce.frecuencia_ejecucion=1 THEN "Diaria" 
                WHEN  lcce.frecuencia_ejecucion=2 THEN "Mensual" 
                WHEN  lcce.frecuencia_ejecucion=3 THEN "Anual" 
                END) as frecuencia_nombre,
                lcce.frecuencia_ejecucion AS conf_frecuencia_ejecucion,
                lcce.cant_ejecucion AS conf_cantidad_ejecucion,
                
                
                YEAR(CURRENT_DATE()) AS anual_sistema,
                YEAR(lce.fecha_realizacion) AS anual_fecha_realizacion,
                
                (SELECT COUNT(*)  FROM lista_chequeo_ejecutadas lce INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id WHERE (YEAR(lce.fecha_realizacion)=YEAR(lce.fecha_realizacion)) AND  lce.usuario_id=:idUsuarioUno AND lce.lista_chequeo_id=:idListaChequeoUno AND  (lce.estado=1 OR lce.estado=2) ) AS ejec_cant_ano,
                IF(
                (SELECT COUNT(*)  FROM lista_chequeo_ejecutadas lce INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id WHERE (YEAR(lce.fecha_realizacion)=YEAR(lce.fecha_realizacion)) AND  lce.usuario_id=:idUsuarioDos AND lce.lista_chequeo_id=:idListaChequeoDos AND  (lce.estado=1 OR lce.estado=2) 
                )<lcce.cant_ejecucion,"TRUE","FALSE") AS puede_ejecutar
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo_configuracion_ejecucion lcce on lcce.lista_chequeo_id=lce.lista_chequeo_id
                 WHERE 
                lce.usuario_id=:idUsuario 
                 AND lce.lista_chequeo_id=:idChequeo
                 AND  (lce.estado=1 
                 OR lce.estado=2) 
                 GROUP BY lcce.frecuencia_ejecucion',[
                     'idUsuario' => $idUsuario,
                     'idChequeo' => $idChequeo,
                     'idUsuarioUno' => $idUsuario,
                     'idUsuarioDos' => $idUsuario,
                     'idListaChequeoUno' => $idChequeo,
                     'idListaChequeoDos' => $idChequeo
                 ]);
                
                 if(COUNT($resultado) == 0)
                    $puedeEjecutar = true;
                else
                    $puedeEjecutar = ($resultado[0]->puede_ejecutar == "TRUE" ? true : false);
                break;
            
            default:
                
                break;
        }


        return $puedeEjecutar;
    }

    public function CambiarEstadoListaChequeo(Request $request)
    {
        
        $idListaChequeo = $request->get('idListaChequeo');
        $estadoActual = $request->get('estadoActual');

        $estadoCambiado = 0;
        if($estadoActual == 0)
            $estadoCambiado = 1;
        else if($estadoActual == 1)
            $estadoCambiado = 0;
        
        $respuestaUpdate = $this->listaChequeos->where('id','=',$idListaChequeo)
        ->update(
        [
            'estado' => $estadoCambiado
        ]);
        
        if($respuestaUpdate)
        {
            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('El estado ',201),
                $estadoCambiado
            );
        }
    }

    public function CambiarFavorito(Request $request)
    {
        
        $idListaChequeo = $request->get('idListaChequeo');
        $idFavoritoActual = $request->get('idFavoritoActual');

        $estadoCambiado = 0;
        if($idFavoritoActual == 0)
            $estadoCambiado = 1;
        else if($idFavoritoActual == 1)
            $estadoCambiado = 0;


        //CONSULTA FAVORITO
        $respuestaUpdate = $this->listaChequeos
        ->Join('usuario AS us','us.id','=','lista_chequeo.usuario_id')
        ->where('us.cuenta_principal_id','=',auth()->user()->cuenta_principal_id)
        ->update(
        [
            'favorita' => 0
        ]);

        
        $respuestaUpdate = $this->listaChequeos->where('id','=',$idListaChequeo)
        ->update(
        [
            'favorita' => $estadoCambiado
        ]);
        
        return $this->FinalizarRetorno(
            201,
            $this->MensajeRetorno('Tu favorito ',201),
            $estadoCambiado
        );
    }

    public function FuncionTraerListasDeChequeoPorPaginacionAdministrador($paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_empresa':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.nombre', '=', $filtro]);
                    break;

                case 'filtro_nit':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.identificacion', '=', $filtro]);
                    break;

                case 'filtro_direccion':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.direccion', '=', $filtro]);
                    break;

                case 'filtro_pais':
                    if($filtro != '')
                        array_push($filtro_array,['p.id', '=', $filtro]);
                    break;

                case 'filtro_responsable':
                    if($filtro != '')
                        array_push($filtro_array,['u.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasDeChequeo = $this->listaChequeos
        ->select(
            'lista_chequeo.id AS ID_LISTA_CHEQUEO',
            'lista_chequeo.nombre AS NOMBRE',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN "Mi organización"
                        WHEN lista_chequeo.publicacion_destino = 2 THEN "Clientes"
                        WHEN lista_chequeo.publicacion_destino = 3 THEN "Organización y clientes"
                     END) AS PUBLICADO_EN'),
            \DB::raw('DATE_FORMAT(DATE_SUB(lista_chequeo.created_at, INTERVAL -5 HOUR),"%d %M %Y %h:%i %p") CREADO'),
            \DB::raw('(CASE
                        WHEN lista_chequeo.estado = 0 THEN "Despublicada"
                        WHEN lista_chequeo.estado = 1 THEN "Publicada"
                     END) AS ESTADO'),
            'lista_chequeo.estado AS ID_ESTADO',
            'lista_chequeo.favorita AS ID_FAVORITO',
            'u.nombre_completo AS CREADO_POR',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces WHERE lces.estado = 2 AND lces.lista_chequeo_id=lista_chequeo.id)
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                     END) AS CANTIDAD_TERMINADAS'),

            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces WHERE lces.estado = 1 AND lces.lista_chequeo_id=lista_chequeo.id)
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                END) AS CANTIDAD_PROCESO'),
            \DB::raw('(CASE
                        WHEN lcce.frecuencia_ejecucion = 0 THEN "Indefinida"
                        WHEN lcce.frecuencia_ejecucion = 1 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Día")
                        WHEN lcce.frecuencia_ejecucion = 2 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Mes")
                        WHEN lcce.frecuencia_ejecucion = 3 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Año")
                END) AS FRECUENCIA')
        ) 
        ->Join('usuario AS u','u.id','=','lista_chequeo.usuario_id')
        ->Join('lista_chequeo_configuracion_ejecucion AS lcce','lcce.lista_chequeo_id','=','lista_chequeo.id')
        
        ->where('u.cuenta_principal_id','=', auth()->user()->cuenta_principal_id)
        ->orderBy('lista_chequeo.created_at','DESC');
        // ->where('lista_chequeo.usuario_id','=', auth()->user()->id);

        if(COUNT($filtro_array) != 0)
        {
            $listasDeChequeo = $listasDeChequeo->where(function($query) use ($filtro_array)
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

        $listasDeChequeo = $listasDeChequeo->skip($desde)->take($hasta)->get();
        $arrayPorcentajes = [];
        foreach ($listasDeChequeo as $keyss => $itemListaChequeo)  
        {
            
            $listaEjecutadas = $this->listaEjecutada->where([
                ['estado','=', 2],
                ['lista_chequeo_id','=',$itemListaChequeo->ID_LISTA_CHEQUEO]
            ])
            ->limit(10)
            ->orderBy('id','DESC')
            ->get();
            $arrayPorcentajes = [];
            foreach ($listaEjecutadas as $keys => $itemEjecutada) 
            {
                $sumaListaChequeo = 0;

                $retorno = \DB::select('SELECT
                lc.nombre,
				(SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100)))*cat.ponderado/100 AS porc_cat
				FROM lista_chequeo_ejec_respuestas lcer
				INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
                INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
				INNER JOIN respuesta res ON res.id=lcer.respuesta_id
				INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
				INNER JOIN categoria cat ON cat.id=pre.categoria_id
				WHERE  lcer.lista_chequeo_ejec_id=:idEjecutada
				GROUP BY lcer.categoria_id
				ORDER BY cat.id',['idEjecutada' => $itemEjecutada->id]);

                foreach ($retorno as $key => $sumaPorcentajes) 
                {
                    $sumaListaChequeo = $sumaListaChequeo + floatval($sumaPorcentajes->porc_cat);
                }

                array_unshift($arrayPorcentajes, number_format($sumaListaChequeo,2));
            }

            $listasDeChequeo[$keyss]->ArrayBarra = $arrayPorcentajes;
            
        }

        return  array('listasChequeo' => $listasDeChequeo,'arrayGrafica' => $arrayPorcentajes);
    }

    public function FuncionTraerListasDeChequeoPorPaginacionResponsableEmpresa($idEmpresa,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_empresa':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.nombre', '=', $filtro]);
                    break;

                case 'filtro_nit':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.identificacion', '=', $filtro]);
                    break;

                case 'filtro_direccion':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.direccion', '=', $filtro]);
                    break;

                case 'filtro_pais':
                    if($filtro != '')
                        array_push($filtro_array,['p.id', '=', $filtro]);
                    break;

                case 'filtro_responsable':
                    if($filtro != '')
                        array_push($filtro_array,['u.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasDeChequeo = $this->listaChequeos
        ->select(
            'lista_chequeo.id AS ID_LISTA_CHEQUEO',
            'lista_chequeo.nombre AS NOMBRE',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN "Mi organización"
                        WHEN lista_chequeo.publicacion_destino = 2 THEN "Clientes"
                        WHEN lista_chequeo.publicacion_destino = 3 THEN "Organización y clientes"
                     END) AS PUBLICADO_EN'),
            \DB::raw('DATE_FORMAT(DATE_SUB(lista_chequeo.created_at, INTERVAL -5 HOUR),"%d %M %Y %h:%i %p") CREADO'),
            \DB::raw('(CASE
                        WHEN lista_chequeo.estado = 0 THEN "Despublicada"
                        WHEN lista_chequeo.estado = 1 THEN "Publicada"
                     END) AS ESTADO'),
            'lista_chequeo.estado AS ID_ESTADO',
            'lista_chequeo.favorita AS ID_FAVORITO',
            'u.nombre_completo AS CREADO_POR',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces 
                        INNER JOIN lista_chequeo AS lcs ON lcs.id=lces.lista_chequeo_id
                        INNER JOIN usuario AS us ON us.id=lces.usuario_id
                        INNER JOIN establecimiento AS es ON es.id=us.establecimiento_id
                        INNER JOIN empresa AS ems ON ems.id=es.empresa_id
                        WHERE lces.estado = 2 AND lces.lista_chequeo_id=lista_chequeo.id AND (ems.id='.$idEmpresa.' OR lces.usuario_id='.auth()->user()->id.'))
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                     END) AS CANTIDAD_TERMINADAS'),

            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces 
                        INNER JOIN lista_chequeo AS lcs ON lcs.id=lces.lista_chequeo_id
                        INNER JOIN usuario AS us ON us.id=lces.usuario_id
                        INNER JOIN establecimiento AS es ON es.id=us.establecimiento_id
                        INNER JOIN empresa AS ems ON ems.id=es.empresa_id
                        WHERE lces.estado = 1 AND lces.lista_chequeo_id=lista_chequeo.id AND (ems.id='.$idEmpresa.' OR lces.usuario_id='.auth()->user()->id.'))
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                END) AS CANTIDAD_PROCESO'),
            \DB::raw('(CASE
                        WHEN lcce.frecuencia_ejecucion = 0 THEN "Indefinida"
                        WHEN lcce.frecuencia_ejecucion = 1 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Día")
                        WHEN lcce.frecuencia_ejecucion = 2 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Mes")
                        WHEN lcce.frecuencia_ejecucion = 3 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Año")
                END) AS FRECUENCIA')
        ) 
        ->Join('usuario AS u','u.id','=','lista_chequeo.usuario_id')
        ->Join('lista_chequeo_configuracion_ejecucion AS lcce','lcce.lista_chequeo_id','=','lista_chequeo.id')
        ->leftJoin('empresa AS em','em.usuario_id','=','u.id')
        ->where('u.cuenta_principal_id','=', auth()->user()->cuenta_principal_id)
        ->orderBy('lista_chequeo.created_at','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $listasDeChequeo = $listasDeChequeo->where(function($query) use ($filtro_array)
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

        $listasDeChequeo = $listasDeChequeo->skip($desde)->take($hasta)->get();
        
        foreach ($listasDeChequeo as $keyss => $itemListaChequeo)  
        {
            
            $listaEjecutadas = $this->listaEjecutada
            ->select('lista_chequeo_ejecutadas.*')
            ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS u','u.id','=','lc.usuario_id')
            ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
            ->Join('empresa AS em','em.id','=','e.empresa_id')
            ->where([
                ['lista_chequeo_ejecutadas.estado','=', 2],
                ['lista_chequeo_ejecutadas.lista_chequeo_id','=',$itemListaChequeo->ID_LISTA_CHEQUEO],
                ['em.id','=',$idEmpresa]
            ])
            ->limit(10)
            ->orderBy('lista_chequeo_ejecutadas.id','DESC')
            ->get();
            $arrayPorcentajes = [];
            foreach ($listaEjecutadas as $key => $itemEjecutada) 
            {
                $sumaListaChequeo = 0;

                $retorno = \DB::select('SELECT 
                lc.nombre,
                TRUNCATE(((cat.ponderado*(SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado ))/100))))/100 ),2)  as porc_cat
                FROM lista_chequeo_ejec_respuestas lcer
                LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
                INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
                LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
                LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
                LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
                WHERE lce.id=:idEjecutada
                GROUP BY cat.id',['idEjecutada' => $itemEjecutada->id]);

                foreach ($retorno as $key => $sumaPorcentajes) 
                {
                    $sumaListaChequeo = $sumaListaChequeo + floatval($sumaPorcentajes->porc_cat);
                }

                array_unshift($arrayPorcentajes,number_format($sumaListaChequeo,2));
            }

            $listasDeChequeo[$keyss]->ArrayBarra = $arrayPorcentajes;
        }
        
        return  array('listasChequeo' => $listasDeChequeo,'arrayGrafica' => $arrayPorcentajes);
    }

    public function FuncionTraerListasDeChequeoPorPaginacionResponsablEstablecimiento($idEstablecimiento,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_empresa':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.nombre', '=', $filtro]);
                    break;

                case 'filtro_nit':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.identificacion', '=', $filtro]);
                    break;

                case 'filtro_direccion':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.direccion', '=', $filtro]);
                    break;

                case 'filtro_pais':
                    if($filtro != '')
                        array_push($filtro_array,['p.id', '=', $filtro]);
                    break;

                case 'filtro_responsable':
                    if($filtro != '')
                        array_push($filtro_array,['u.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasDeChequeo = $this->listaChequeos
        ->select(
            'lista_chequeo.id AS ID_LISTA_CHEQUEO',
            'lista_chequeo.nombre AS NOMBRE',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN "Mi organización"
                        WHEN lista_chequeo.publicacion_destino = 2 THEN "Clientes"
                        WHEN lista_chequeo.publicacion_destino = 3 THEN "Organización y clientes"
                     END) AS PUBLICADO_EN'),
            \DB::raw('DATE_FORMAT(DATE_SUB(lista_chequeo.created_at, INTERVAL -5 HOUR),"%d %M %Y %h:%i %p") CREADO'),
            \DB::raw('(CASE
                        WHEN lista_chequeo.estado = 0 THEN "Despublicada"
                        WHEN lista_chequeo.estado = 1 THEN "Publicada"
                     END) AS ESTADO'),
            'lista_chequeo.estado AS ID_ESTADO',
            'lista_chequeo.favorita AS ID_FAVORITO',
            'u.nombre_completo AS CREADO_POR',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces 
                        INNER JOIN lista_chequeo AS lcs ON lcs.id=lces.lista_chequeo_id
                        INNER JOIN usuario AS us ON us.id=lces.usuario_id
                        INNER JOIN establecimiento AS ess ON ess.id=us.establecimiento_id
                        WHERE lces.estado = 2 AND lces.lista_chequeo_id=lista_chequeo.id AND ess.id='.$idEstablecimiento.')
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                     END) AS CANTIDAD_TERMINADAS'),

            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces 
                        INNER JOIN lista_chequeo AS lcs ON lcs.id=lces.lista_chequeo_id
                        INNER JOIN usuario AS us ON us.id=lces.usuario_id
                        INNER JOIN establecimiento AS ess ON ess.id=us.establecimiento_id
                        WHERE lces.estado = 1 AND lces.lista_chequeo_id=lista_chequeo.id AND ess.id='.$idEstablecimiento.')
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                END) AS CANTIDAD_PROCESO'),
            \DB::raw('(CASE
                        WHEN lcce.frecuencia_ejecucion = 0 THEN "Indefinida"
                        WHEN lcce.frecuencia_ejecucion = 1 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Día")
                        WHEN lcce.frecuencia_ejecucion = 2 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Mes")
                        WHEN lcce.frecuencia_ejecucion = 3 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Año")
                END) AS FRECUENCIA')
        ) 
        ->Join('usuario AS u','u.id','=','lista_chequeo.usuario_id')
        ->Join('lista_chequeo_configuracion_ejecucion AS lcce','lcce.lista_chequeo_id','=','lista_chequeo.id')
        ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
        ->where('u.cuenta_principal_id','=', auth()->user()->cuenta_principal_id)
        ->orderBy('lista_chequeo.created_at','DESC');
        // ->where('lista_chequeo.usuario_id','=', auth()->user()->id);

        if(COUNT($filtro_array) != 0)
        {
            $listasDeChequeo = $listasDeChequeo->where(function($query) use ($filtro_array)
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

        $listasDeChequeo = $listasDeChequeo->skip($desde)->take($hasta)->get();
        
        foreach ($listasDeChequeo as $keyss => $itemListaChequeo)  
        {
            
            $listaEjecutadas = $this->listaEjecutada
            ->select('lista_chequeo_ejecutadas.*')
            ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS u','u.id','=','lc.usuario_id')
            ->Join('establecimiento AS e','e.id','=','u.establecimiento_id')
            ->where([
                ['lista_chequeo_ejecutadas.estado','=', 2],
                ['lista_chequeo_ejecutadas.lista_chequeo_id','=',$itemListaChequeo->ID_LISTA_CHEQUEO],
                ['e.id','=',$idEstablecimiento]
            ])
            ->limit(10)
            ->orderBy('lista_chequeo_ejecutadas.id','DESC')
            ->get();
            $arrayPorcentajes = [];
            foreach ($listaEjecutadas as $key => $itemEjecutada) 
            {
                $sumaListaChequeo = 0;

                $retorno = \DB::select('SELECT 
                lc.nombre,
                TRUNCATE(((cat.ponderado*(SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado ))/100))))/100 ),2)  as porc_cat
                FROM lista_chequeo_ejec_respuestas lcer
                LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
                INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
                LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
                LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
                LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
                WHERE lce.id=:idEjecutada
                GROUP BY cat.id',['idEjecutada' => $itemEjecutada->id]);

                foreach ($retorno as $key => $sumaPorcentajes) 
                {
                    $sumaListaChequeo = $sumaListaChequeo + floatval($sumaPorcentajes->porc_cat);
                }

                array_unshift($arrayPorcentajes,number_format($sumaListaChequeo,2));
            }

            $listasDeChequeo[$keyss]->ArrayBarra = $arrayPorcentajes;
        }
        
        return  array('listasChequeo' => $listasDeChequeo,'arrayGrafica' => $arrayPorcentajes);
    }

    public function FuncionTraerListasDeChequeoPorPaginacionColaborador($idUsuario,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_empresa':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.nombre', '=', $filtro]);
                    break;

                case 'filtro_nit':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.identificacion', '=', $filtro]);
                    break;

                case 'filtro_direccion':
                    if($filtro != '')
                        array_push($filtro_array,['empresa.direccion', '=', $filtro]);
                    break;

                case 'filtro_pais':
                    if($filtro != '')
                        array_push($filtro_array,['p.id', '=', $filtro]);
                    break;

                case 'filtro_responsable':
                    if($filtro != '')
                        array_push($filtro_array,['u.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $listasDeChequeo = $this->listaChequeos
        ->select(
            'lista_chequeo.id AS ID_LISTA_CHEQUEO',
            'lista_chequeo.nombre AS NOMBRE',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN "Mi organización"
                        WHEN lista_chequeo.publicacion_destino = 2 THEN "Clientes"
                        WHEN lista_chequeo.publicacion_destino = 3 THEN "Organización y clientes"
                     END) AS PUBLICADO_EN'),
            \DB::raw('DATE_FORMAT(DATE_SUB(lista_chequeo.created_at, INTERVAL -5 HOUR),"%d %M %Y %h:%i %p") CREADO'),
            \DB::raw('(CASE
                        WHEN lista_chequeo.estado = 0 THEN "Despublicada"
                        WHEN lista_chequeo.estado = 1 THEN "Publicada"
                     END) AS ESTADO'),
            'lista_chequeo.estado AS ID_ESTADO',
            'lista_chequeo.favorita AS ID_FAVORITO',
            'u.nombre_completo AS CREADO_POR',
            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces 
                        INNER JOIN lista_chequeo AS lcs ON lcs.id=lces.lista_chequeo_id
                        WHERE lces.estado = 2 AND lces.lista_chequeo_id=lista_chequeo.id AND lces.usuario_id='.$idUsuario.')
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                     END) AS CANTIDAD_TERMINADAS'),

            \DB::raw('(CASE
                        WHEN lista_chequeo.publicacion_destino = 1 THEN (SELECT COUNT(*) FROM lista_chequeo_ejecutadas AS lces 
                        INNER JOIN lista_chequeo AS lcs ON lcs.id=lces.lista_chequeo_id
                        WHERE lces.estado = 1 AND lces.lista_chequeo_id=lista_chequeo.id AND lces.usuario_id='.$idUsuario.')
                        WHEN lista_chequeo.publicacion_destino = 2 THEN 0
                        WHEN lista_chequeo.publicacion_destino = 3 THEN 0
                END) AS CANTIDAD_PROCESO'),
            \DB::raw('(CASE
                        WHEN lcce.frecuencia_ejecucion = 0 THEN "Indefinida"
                        WHEN lcce.frecuencia_ejecucion = 1 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Día")
                        WHEN lcce.frecuencia_ejecucion = 2 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Mes")
                        WHEN lcce.frecuencia_ejecucion = 3 THEN CONCAT(IF(lcce.cant_ejecucion IS NULL,"Infinitas",lcce.cant_ejecucion)," por Año")
                END) AS FRECUENCIA')
        ) 
        ->Join('usuario AS u','u.id','=','lista_chequeo.usuario_id')
        ->Join('lista_chequeo_configuracion_ejecucion AS lcce','lcce.lista_chequeo_id','=','lista_chequeo.id')
        ->where('u.cuenta_principal_id','=', auth()->user()->cuenta_principal_id)
        ->orderBy('lista_chequeo.created_at','DESC');

        if(COUNT($filtro_array) != 0)
        {
            $listasDeChequeo = $listasDeChequeo->where(function($query) use ($filtro_array)
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

        $listasDeChequeo = $listasDeChequeo->skip($desde)->take($hasta)->get();
        
        foreach ($listasDeChequeo as $keyss => $itemListaChequeo)  
        {
            
            $listaEjecutadas = $this->listaEjecutada->where([
                ['estado','=', 2],
                ['lista_chequeo_id','=',$itemListaChequeo->ID_LISTA_CHEQUEO],
                ['usuario_id','=', $idUsuario]
            ])
            ->limit(10)
            ->orderBy('id','DESC')
            ->get();
            $arrayPorcentajes = [];
            foreach ($listaEjecutadas as $key => $itemEjecutada) 
            {
                $sumaListaChequeo = 0;

                $retorno = \DB::select('SELECT 
                lc.nombre,
                TRUNCATE(((cat.ponderado*(SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado ))/100))))/100 ),2)  as porc_cat
                FROM lista_chequeo_ejec_respuestas lcer
                LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
                INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
                LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
                LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
                LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
                WHERE lce.id=:idEjecutada
                GROUP BY cat.id',['idEjecutada' => $itemEjecutada->id]);

                foreach ($retorno as $key => $sumaPorcentajes) 
                {
                    $sumaListaChequeo = $sumaListaChequeo + floatval($sumaPorcentajes->porc_cat);
                }

                array_unshift($arrayPorcentajes,number_format($sumaListaChequeo,2));
            }

            $listasDeChequeo[$keyss]->ArrayBarra = $arrayPorcentajes;
        }
        
        return  array('listasChequeo' => $listasDeChequeo,'arrayGrafica' => $arrayPorcentajes);
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

    public function PrevisualizacionListaChequeo(Request $request)
    {
        $idListaChequeo = $request->get('idListaChequeo');
        
        $listas = $this->ConsultaCategoriasPreguntasPorListaChequeoMisListas($idListaChequeo);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',200),
            $listas
        );
    }

    public function ConsultaCategoriasPreguntasPorListaChequeoMisListas($lista_chequeo_id)
    {
        $consultaListaChequeo = $this->categoria
        ->select(
            'categoria.*'
        )
        ->where([
            ['categoria.lista_chequeo_id','=',$lista_chequeo_id]
        ])
        ->orderBy('categoria.orden_lista','ASC')
        ->get();

        
        $arrayFinal = [];
        foreach ($consultaListaChequeo as $key => $categoria) 
        {
            $objeto = new \stdClass();
            
            $preguntas = $this->pregunta
            ->Join('tipo_respuesta AS tr','tr.id','pregunta.tipo_respuesta_id')
            ->Join('tipo_respuesta_categoria AS trc','trc.id','tr.tipo_respuesta_categoria')
            ->select(
                'pregunta.*',
                'trc.nombre AS NOMBRE_CATEGORIA',
                'tr.icono AS ICONO_TIPO_RESPUESTA'
            )->where('pregunta.categoria_id','=',$categoria->id)
            ->orderBy('pregunta.orden_lista','ASC')
            ->get();

            $objeto->CATEGORIA_ID = $categoria->id;
            $objeto->NOMBRE_CATEGORIA = $categoria->nombre;
            $objeto->PONDERADO = number_format($categoria->ponderado,0);
            $objeto->ORDEN_LISTA = $categoria->orden_lista;
            $objeto->LISTA_CHEQUEO_ID = $categoria->lista_chequeo_id;
            foreach ($preguntas as $key => $pregunta) 
            {
                // TRAER OPCIONES GENERALES ESCOGIDAS POR USUARIO
                $opcionesGenerales = $this->preguntaOpcionRespuesta
                ->Join('pregunta_respuesta_opcion AS pro','pro.id','pregunta_preguntarespuestaopcion.pregunta_respuesta_opcion')
                ->where([
                    ['pregunta_preguntarespuestaopcion.pregunta_id','=',$pregunta->id],
                    ['pro.id','!=',4]
                ])
                ->get();
                
                $tiposRespuestas = \DB::select("SELECT 
                respuesta.*,
                respuesta.tipo_respuesta_ponderado_pred_id TIPO_RESPUESTA
                FROM respuesta
                WHERE respuesta.pregunta_id = :idPregunta",
                [
                    'idPregunta' => $pregunta->id
                ]);

                $preguntas[$key]['OpcionesGenerales'] = $opcionesGenerales;
                $preguntas[$key]['tiposRespuestas'] = $tiposRespuestas;
            }

            $objeto->PREGUNTAS = $preguntas;

            array_push($arrayFinal,$objeto);
        }

        return $arrayFinal;
    }

    public function FuncionValidadSiPuedeEjecutar()
    {

        $puedeEjecutar = \DB::select(\DB::raw("SELECT
        (CASE
            WHEN pl.id=1 THEN '1'
            WHEN pl.id=2 THEN '2'
            WHEN pl.id=3 THEN '3'
            WHEN pl.id=4 THEN '4'
            ELSE 'contacto'
        END) AS plan,
        COUNT(*) AS cta_cant_ejecuciones,
        (SELECT spp.valor FROM plan_parametros spp
        INNER JOIN plan spl ON spl.id= spp.plan_id
        WHERE spp.id=
        (CASE
            WHEN pl.id=1 THEN 1
            WHEN pl.id=2 THEN 6
            WHEN pl.id=3 THEN 11
            WHEN pl.id=4 THEN 16
            ELSE 'contacto'
        END) AND spl.id=pl.id) AS plan_ejecuciones,
        (
        IF (COUNT(*)<(SELECT spp.valor FROM plan_parametros spp
        INNER JOIN plan spl ON spl.id= spp.plan_id
        WHERE spp.id=
        (CASE
            WHEN pl.id=1 THEN 1
            WHEN pl.id=2 THEN 6
            WHEN pl.id=3 THEN 11
            WHEN pl.id=4 THEN 16
            ELSE 'contacto'
        END) AND spl.id=pl.id),'SI','NO')
        ) AS puede_ejecutar
        FROM lista_chequeo_ejecutadas lce
        INNER JOIN usuario us ON us.id=lce.usuario_id
        INNER JOIN cuenta_principal cp ON cp.id=us.cuenta_principal_id
        INNER JOIN plan pl ON pl.id=cp.plan_id
        WHERE us.cuenta_principal_id=:idCuentaPrincipal;"),['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        if(ISSET($puedeEjecutar))
        {
            if(COUNT($puedeEjecutar) != 0)
            {
                if($puedeEjecutar[0]->puede_ejecutar == 'SI')
                    return true;
                else
                    return false;
            }
            else
                return false;
        }
        else
            return false;
        
    }

    public function FuncionValidarSiEstaAlDia()
    {

        $puedeEjecutar = \DB::select(\DB::raw("SELECT
        pp.id,
        date(NOW()) AS hoy,
        pp.fecha_inicio,
        pp.fecha_fin,
        IF(((date(NOW()))>=pp.fecha_inicio AND ((date(NOW()))<=pp.fecha_fin )),'SI','NO' ) AS aldia
        FROM plan_pagos pp
        INNER JOIN cuenta_principal cp ON cp.id=pp.cuenta_principal_id
        WHERE cp.id=:idCuentaPrincipal
        ORDER BY pp.id DESC limit 1;"),['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        if(ISSET($puedeEjecutar))
        {
            if(COUNT($puedeEjecutar) != 0)
            {
                if($puedeEjecutar[0]->aldia == 'SI')
                    return true;
                else
                    return false;
            }
            else
                return true; //ES GRATIS
        }
        else
            return false;
        
    }

    public function validarDuplicar($idListaChequeo){
        $estado = false;
       $consulta =  $this->listaEncabezado->where('lista_chequeo_id', '=', $idListaChequeo)->first();
        if($consulta != null){
            $estado = true;
        }else{
            $estado = false;
        }

        return response()->json(['datos' => $estado]);
    }

}