<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\ListaChequeo;
use App\Http\Models\Categoria;
use App\Http\Models\Pregunta;
use App\Http\Models\PreguntaOpcionRespuesta;
use App\Http\Models\Respuesta;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use App\Http\Models\Usuario;
use App\Http\Models\Areas;
use App\Http\Models\Equipos;
use App\Http\Models\ListaChequeoEjecutadas;
use App\Http\Models\ListaChequeoEjecutadasRespuestas;
use App\Http\Models\ListaChequeoEjecutadasOpcionesRespuesta;
use App\Http\Models\PlanDeAccionAutomatico;
use App\Http\Models\ListaChequeoEjecutadasFotos;
use App\Http\Models\ListaChequeoEjecutadasArchivos;
use App\Http\Models\ListaChequeoPlanAccionEjecucion;
use App\Http\Models\CuentaPrincipal;
use App\Http\Models\ListaChequeoConfiguracion;
use App\Http\Models\PlanAccion;
use App\Http\Models\PlanAccionManual;
use App\Http\Models\PlanAccionManuDetalle;

use Carbon\Carbon;
use App\Mail\MailFinalizarListaChequeo;
use App\Mail\MailResponsablesPlanAccionManual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListaChequeoEjecucionController extends Controller
{
    protected $configuracionListaChequeo, $establecimiento, $cuentaPrincipal, $ejecucionPlanAccion, $listaChequeoEjecutadasFotos, $planAccionAutomatico, $opcRespuesta, $listaChequeo, $categoria, $pregunta, $preguntaOpcionRespuesta, $respuesta, $empresa, $usuario, $listaEjecutada, $respuestaEjecucion, $listaChequeoEjecutadasArchivos, $planAccion, $planAccionManual, $areas, $equipos;
    public function __construct(
        ListaChequeo $listaChequeo,
        Categoria $categoria,
        Pregunta $pregunta,
        PreguntaOpcionRespuesta $preguntaOpcionRespuesta,
        Respuesta $respuesta,
        Empresa $empresa,
        Usuario $usuario,
        Areas $areas,
        Equipos $equipos,
        ListaChequeoEjecutadas $listaEjecutada,
        ListaChequeoEjecutadasRespuestas $respuestaEjecucion,
        ListaChequeoEjecutadasOpcionesRespuesta $opcRespuesta,
        PlanDeAccionAutomatico $planAccionAutomatico,
        ListaChequeoEjecutadasFotos $listaChequeoEjecutadasFotos,
        ListaChequeoPlanAccionEjecucion $ejecucionPlanAccion,
        CuentaPrincipal $cuentaPrincipal,
        Establecimiento $establecimiento,
        ListaChequeoConfiguracion $configuracionListaChequeo,
        ListaChequeoEjecutadasArchivos $listaChequeoEjecutadasArchivos,
        PlanAccion $planAccion,
        PlanAccionManual $planAccionManual,
        PlanAccionManuDetalle $planAccionManualDetalle
    ) {
        $this->listaChequeo = $listaChequeo;
        $this->categoria = $categoria;
        $this->pregunta = $pregunta;
        $this->preguntaOpcionRespuesta = $preguntaOpcionRespuesta;
        $this->respuesta = $respuesta;
        $this->empresa = $empresa;
        $this->usuario = $usuario;
        $this->listaEjecutada = $listaEjecutada;
        $this->respuestaEjecucion = $respuestaEjecucion;
        $this->opcRespuesta = $opcRespuesta;
        $this->planAccionAutomatico = $planAccionAutomatico;
        $this->listaChequeoEjecutadasFotos = $listaChequeoEjecutadasFotos;
        $this->ejecucionPlanAccion = $ejecucionPlanAccion;
        $this->cuentaPrincipal = $cuentaPrincipal;
        $this->establecimiento = $establecimiento;
        $this->configuracionListaChequeo = $configuracionListaChequeo;
        $this->listaChequeoEjecutadasArchivos = $listaChequeoEjecutadasArchivos;
        $this->planAccion = $planAccion;
        $this->planAccionManual = $planAccionManual;
        $this->planAccionManualDetalle = $planAccionManualDetalle;
        $this->areas = $areas;
        $this->equipos = $equipos;
        if (STRLEN(\Request::segment(3)) > 150) {
            $idListaChequeo = decrypt(\Request::segment(3));
            $datosListaChequeo = $this->listaChequeo->where('id', '=', $idListaChequeo)->first();
            $idCuentaPrincipal = $datosListaChequeo->usuario_id;

            \Redirect::to('/registro_colaborador/' . encrypt($idCuentaPrincipal) . '/' . encrypt($idListaChequeo))->send();
        }

        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        $fechaActual = date('d-m-Y H:i:s');

        $idListaEjecutada = \Request::segment(4);
        if (!$this->listaEjecutada->where('id', '=', $idListaEjecutada)->exists())
            return redirect('/listachequeo/mislistas');

        if ($this->listaEjecutada->where([
            ['id', '=', $idListaEjecutada],
            ['estado', '=', 2]
        ])->exists())
            return redirect('/listachequeo/mislistas');


        $equipos = DB::table('equipos')
            ->select(DB::raw("nombre AS equipo"))
            ->pluck('equipo');


        $areas = DB::table('areas')
            ->select('id', 'nombre')
            ->distinct()
            ->get();


        return view('Admin.listachequeo_ejecucion', compact('fechaActual', 'idListaEjecutada', 'equipos', 'areas'));
    }

    public function EjecutarPorLinkUrl()
    {
        $idListaChequeo = \Request::segment(3);
        if (!$this->listaChequeo
            ->Join('usuario AS u', 'u.id', '=', 'lista_chequeo.usuario_id')
            ->where([['lista_chequeo.id', '=', $idListaChequeo], ['lista_chequeo.estado', '=', 1], ['u.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]])->exists())
            return redirect('/listachequeo/mislistas');

        $arrayInsertar = [
            'lista_chequeo_id' => $idListaChequeo,
            'usuario_id' => auth()->user()->id,
            'fecha_realizacion' => date('Y-m-d')
        ];

        // VALIDACIÓN SI ESTÁ AL DÍA CON EL PAGO
        $planAlDia = $this->FuncionValidarSiEstaAlDia();
        if (!$planAlDia) {
            return redirect('/listachequeo/mislistas');
        }

        //EJECUTAR POR LINK VALIDAD SI PUEDE POR CANTIDADES
        $planPuedeEjecutar = $this->FuncionValidadSiPuedeEjecutar();
        if (!$planPuedeEjecutar) {
            return redirect('/listachequeo/mislistas');
        }

        $resultadoDeValidacion = $this->ValidarSiPuedeRealizarEjecucion($idListaChequeo, auth()->user()->id);
        if (!$resultadoDeValidacion) {
            // $this->listaEjecutada->where('id','=',$idListaEjecucion)->delete();

            // return $this->FinalizarRetorno(
            //     402,
            //     $this->MensajeRetorno('Datos ',402)
            // );

            return redirect('/listachequeo/mislistas');
        }

        $listaEjecutada = new $this->listaEjecutada;
        $listaEjecutada->fill($arrayInsertar);

        if ($listaEjecutada->save()) {
            $idListaEjecutada = $listaEjecutada->id;

            return redirect('/listachequeo/ejecucion/' . $idListaChequeo . '/' . $idListaEjecutada);
        }
    }

    public function EnlistarListaChequeo(Request $request)
    {
        $idListaChequeo = $request->get('idListaChequeo');
        $idListaEjecucion = $request->get('idListaEjecucion');

        $encabezado = $this->listaChequeo
            ->select(
                'lista_chequeo.nombre AS NOMBRE_LISTA_CHEQUEO',
                \DB::raw('(
                CASE
                    WHEN lista_chequeo.entidad_evaluada = 1 THEN "Empresa"
                    WHEN lista_chequeo.entidad_evaluada = 2 THEN "Establecimiento"
                    WHEN lista_chequeo.entidad_evaluada = 3 THEN "Usuario"
                    WHEN lista_chequeo.entidad_evaluada = 4 THEN "Áreas"
                    WHEN lista_chequeo.entidad_evaluada = 5 THEN "Equipos"
                END
                ) AS EVALUANDO_A'),
                \DB::raw('(
                CASE
                    WHEN lce.entidad_evaluada_opcion = 1 THEN 0
                    WHEN lce.entidad_evaluada_opcion = 2 THEN 1
                END
                ) AS HABILITA_SELECT'),
                \DB::raw('IF(lce.fecha = 1, 0,1) HABILITA_FECHA')
            )
            ->leftJoin('lista_chequeo_encabezado AS lce', 'lce.lista_chequeo_id', '=', 'lista_chequeo.id')
            ->where([
                // ['lista_chequeo.modelo_id','=', 0],
                ['lista_chequeo.estado', '=', 1],
                ['lista_chequeo.id', '=', $idListaChequeo]
            ])
            ->first();

        $llenadoDeSelect = [];

        if (!is_null($encabezado)) {
            switch ($encabezado->EVALUANDO_A) {
                case 'Empresa':

                    if ($encabezado->HABILITA_SELECT == 0) {
                        $llenadoDeSelect = $this->usuario
                            ->select('em.id AS ID', 'em.nombre AS NOMBRE')
                            ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                            ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                            ->where('usuario.id', '=', auth()->user()->id)
                            ->get();
                    } else {
                        $llenadoDeSelect = $this->empresa
                            ->select('empresa.id AS ID', 'empresa.nombre AS NOMBRE')
                            ->where('empresa.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id)
                            ->get();
                    }

                    break;

                case 'Establecimiento':

                    if ($encabezado->HABILITA_SELECT == 0) {
                        $llenadoDeSelect = $this->usuario
                            ->select('e.id AS ID', 'e.nombre AS NOMBRE')
                            ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                            ->where('usuario.id', '=', auth()->user()->id)
                            ->get();
                    } else {
                        $llenadoDeSelect = $this->establecimiento
                            ->select('establecimiento.id AS ID', 'establecimiento.nombre AS NOMBRE')
                            ->Join('empresa AS em', 'em.id', 'establecimiento.empresa_id')
                            ->where('em.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id)
                            ->get();
                    }
                    break;

                case 'Usuario':

                    if ($encabezado->HABILITA_SELECT == 0) {
                        $llenadoDeSelect = $this->usuario
                            ->select('usuario.id AS ID', \DB::raw('CONCAT(usuario.nombre_completo," (",em.nombre,")") AS NOMBRE'))
                            ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                            ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                            ->where('usuario.id', '=', auth()->user()->id)
                            ->get();
                    } else {
                        $llenadoDeSelect = $this->usuario
                            ->select('usuario.id AS ID', \DB::raw('CONCAT(usuario.nombre_completo," (",em.nombre,")") AS NOMBRE'))
                            ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                            ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                            ->where('usuario.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id)
                            ->get();
                    }

                    break;

                case 'Proceso':

                    break;
                case 'Áreas':
                    if ($encabezado->HABILITA_SELECT == 0) {
                        $llenadoDeSelect = [];
                    } else {
                        $llenadoDeSelect = $this->areas
                            ->select(
                                'id AS ID',
                                'nombre AS NOMBRE'
                            )
                            ->where([
                                ['cuenta_principal_id', '=', auth()->user()->cuenta_principal_id],
                                ['estado', '=', 1]
                            ])
                            ->get();
                    }
                    break;

                case 'Equipos':
                    if ($encabezado->HABILITA_SELECT == 0) {
                        $llenadoDeSelect = [];
                    } else {
                        $llenadoDeSelect = $this->equipos
                            ->select(
                                'id AS ID',
                                'nombre AS NOMBRE'
                            )
                            ->where([
                                ['cuenta_principal_id', '=', auth()->user()->cuenta_principal_id],
                                ['estado', '=', 1]
                            ])
                            ->get();
                    }
                    break;

                default:
                    # code...
                    break;
            }

            $encabezado['SelectLlenado'] = $llenadoDeSelect;
            $encabezado['OBSERVACION_GENERAL'] = \DB::table('lista_chequeo_ejecutadas')
                ->select(
                    \DB::raw('IF(observacion_general IS NULL, "", observacion_general) AS OBS_GENERAL')
                )
                ->where('id', '=', $idListaEjecucion)
                ->first()->OBS_GENERAL;
        }

        $categoriasPreguntas = $this->ConsultaCategoriasPreguntasPorListaChequeo($idListaChequeo, $idListaEjecucion);

        $arrayEnviar = array(
            'encabezado' => $encabezado,
            'categoriasPreguntas' => $categoriasPreguntas,
        );

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos ', 202),
            $arrayEnviar
        );
    }

    public function ValidarSiPuedeRealizarEjecucion($idListaChequeo, $idUsuario)
    {

        // $configuracion = $this->listaEjecutada
        // ->select('lcce.*')
        // ->Join('lista_chequeo_configuracion_ejecucion AS lcce','lcce.lista_chequeo_id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        // ->where('lista_chequeo_ejecutadas.id','=',$idEjecucion)->first();

        $configuracion = $this->configuracionListaChequeo
            ->where('lista_chequeo_id', '=', $idListaChequeo)
            ->first();


        $idChequeo = $idListaChequeo;

        $puedeEjecutar = true;
        switch ($configuracion->frecuencia_ejecucion) {
            case 0: // INDEFINIDA
                $puedeEjecutar = true;
                break;

            case 1: // DIARIO

                $resultado = \DB::select(
                    'SELECT 
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
                    ]
                );

                if (COUNT($resultado) == 0)
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
                 GROUP BY lcce.frecuencia_ejecucion', [
                    'idUsuario' => $idUsuario,
                    'idChequeo' => $idChequeo,
                    'idUsuarioUno' => $idUsuario,
                    'idUsuarioDos' => $idUsuario,
                    'idListaChequeoUno' => $idChequeo,
                    'idListaChequeoDos' => $idChequeo
                ]);

                if (COUNT($resultado) == 0)
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
                 GROUP BY lcce.frecuencia_ejecucion', [
                    'idUsuario' => $idUsuario,
                    'idChequeo' => $idChequeo,
                    'idUsuarioUno' => $idUsuario,
                    'idUsuarioDos' => $idUsuario,
                    'idListaChequeoUno' => $idChequeo,
                    'idListaChequeoDos' => $idChequeo
                ]);

                if (COUNT($resultado) == 0)
                    $puedeEjecutar = true;
                else
                    $puedeEjecutar = ($resultado[0]->puede_ejecutar == "TRUE" ? true : false);
                break;

            default:

                break;
        }


        return $puedeEjecutar;
    }

    public function ConsultaCategoriasPreguntasPorListaChequeo($lista_chequeo_id, $idListaEjecucion)
    {
        $consultaListaChequeo = $this->categoria
            ->select(
                'categoria.*',
                \DB::raw('IF(ce.nombre IS NULL,"",ce.nombre) AS ETIQUETA')
            )
            ->leftJoin('categoria_etiquetas AS ce', 'ce.id', '=', 'categoria.id_etiqueta')
            ->where([
                ['categoria.lista_chequeo_id', '=', $lista_chequeo_id]
            ])
            ->orderBy('categoria.orden_lista', 'ASC')
            ->get();


        $arrayFinal = [];
        foreach ($consultaListaChequeo as $key => $categoria) {
            $objeto = new \stdClass();

            $preguntas = $this->pregunta
                ->Join('tipo_respuesta AS tr', 'tr.id', 'pregunta.tipo_respuesta_id')
                ->Join('tipo_respuesta_categoria AS trc', 'trc.id', 'tr.tipo_respuesta_categoria')
                ->select(
                    'pregunta.*',
                    'trc.nombre AS NOMBRE_CATEGORIA',
                    'tr.icono AS ICONO_TIPO_RESPUESTA'
                )->where('pregunta.categoria_id', '=', $categoria->id)
                ->orderBy('pregunta.orden_lista', 'ASC')
                ->get();

            $objeto->CATEGORIA_ID = $categoria->id;
            $objeto->NOMBRE_CATEGORIA = $categoria->nombre;
            $objeto->PONDERADO = number_format($categoria->ponderado, 0);
            $objeto->ORDEN_LISTA = $categoria->orden_lista;
            $objeto->LISTA_CHEQUEO_ID = $categoria->lista_chequeo_id;
            $objeto->ETIQUETA = $categoria->ETIQUETA;
            foreach ($preguntas as $key => $pregunta) {
                // TRAER OPCIONES GENERALES ESCOGIDAS POR USUARIO
                $opcionesGenerales = $this->preguntaOpcionRespuesta
                    ->Join('pregunta_respuesta_opcion AS pro', 'pro.id', 'pregunta_preguntarespuestaopcion.pregunta_respuesta_opcion')
                    ->where([
                        ['pregunta_preguntarespuestaopcion.pregunta_id', '=', $pregunta->id],
                        ['pro.id', '!=', 4]
                    ])
                    ->get();

                //CONSULTO EL PLAN DE ACCION MANUAL PARA LUEGO PINTARLO EN LA VISTA COMO UN BOTON
                $planManual = \DB::table('plan_accion')->select(
                    "plan_accion.tipo_pa as tipo",
                    "plan_accion.obligatorio as obligatorio",
                    "plan_accion.alerta as alerta",
                    "pam.plan_accion_man_opc_id as opcion_id",
                    "pamo.opcion as nom_opcion"
                )
                    ->join("plan_accion_manual as pam", "plan_accion.id", "=", "pam.plan_accion_id")
                    ->leftJoin("plan_accion_man_opc as pamo", "pam.plan_accion_man_opc_id", "=", "pamo.id")
                    ->where("pregunta_id", "=", $pregunta->id)->get();


                // TRAER LOS QUE TIENEN AGREGADOS EN OPCIONES DE RESPUESTA
                $datoEjecucion = $this->respuestaEjecucion->where([
                    ['lista_chequeo_ejec_id', '=', $idListaEjecucion],
                    ['pregunta_id', '=', $pregunta->id]
                ])->first();

                $OpcionesGeneralesLlenas = NULL;
                if (!is_null($datoEjecucion)) {
                    $OpcionesGeneralesLlenas = $this->opcRespuesta
                        ->select(
                            // \DB::raw('(SELECT COUNT(*) FROM lista_chequeo_ejec_fotos AS lcef WHERE lcef.lista_chequeo_ejec_respuestas = lista_chequeo_ejec_opciones.lista_chequeo_ejec_respuestas_id) AS FOTO'),
                            // \DB::raw('IF(lista_chequeo_ejec_opciones.foto IS NULL,0,1) AS FOTO'),
                            \DB::raw('IF(lista_chequeo_ejec_opciones.comentario IS NULL,0,1) AS COMENTARIO'),
                            'lista_chequeo_ejec_opciones.comentario AS TEXTO_COMENTARIO'
                        )
                        ->where('lista_chequeo_ejec_respuestas_id', '=', $datoEjecucion->id)->first();
                }

                $cantidadPreguntas = NULL;
                if (!is_null($datoEjecucion))
                    $cantidadPreguntas = $this->listaChequeoEjecutadasFotos->where('lista_chequeo_ejec_respuestas', '=', $datoEjecucion->id)->count();

                $opcGeneralesAdjuntos = NULL;
                if (!is_null($datoEjecucion))
                    $opcGeneralesAdjuntos = $this->listaChequeoEjecutadasArchivos->where('lista_chequeo_ejec_respuesta_id', '=', $datoEjecucion->id)->count();

                $opcGeneralesPlanAccionM = NULL;
                if (!is_null($datoEjecucion))
                    $opcGeneralesPlanAccionM = $this->planAccionManualDetalle->where('lista_cheq_ejec_respuesta_id', '=', $datoEjecucion->id)->exists();

                $tiposRespuestas = \DB::select(
                    "SELECT 
                respuesta.*,
                respuesta.tipo_respuesta_ponderado_pred_id TIPO_RESPUESTA,
                IF(
                (
					SELECT COUNT(*) FROM lista_chequeo_ejec_respuestas lcer 
                    WHERE (lcer.pregunta_id = respuesta.pregunta_id
					AND lcer.lista_chequeo_ejec_id = :idEjecu)
                ) != 0,true,false) AS EXISTE_REGISTRO,
                
                (SELECT lcers.respuesta_id FROM lista_chequeo_ejec_respuestas lcers 
                    WHERE (lcers.pregunta_id = respuesta.pregunta_id
					AND lcers.lista_chequeo_ejec_id = :idEj)) AS rta,

                (SELECT lcers.respuesta_abierta FROM lista_chequeo_ejec_respuestas lcers 
                    WHERE (lcers.pregunta_id = respuesta.pregunta_id
					AND lcers.lista_chequeo_ejec_id = :idEjects)) AS rta_abierta,
                    
                    (SELECT lcerss.no_aplica FROM lista_chequeo_ejec_respuestas lcerss
                    WHERE (lcerss.pregunta_id = respuesta.pregunta_id
					AND lcerss.lista_chequeo_ejec_id = :idEjecutada)) AS NA
                    
                FROM respuesta
                WHERE respuesta.pregunta_id = :idPregunta",
                    [
                        'idPregunta' => $pregunta->id,
                        'idEjecutada' => $idListaEjecucion,
                        'idEj' => $idListaEjecucion,
                        'idEjecu' => $idListaEjecucion,
                        'idEjects' => $idListaEjecucion,
                    ]
                );

                // array_push($opcionesGenerales,$OpcionesGeneralesLlenas);
                $preguntas[$key]['OpcionesGenerales'] = $opcionesGenerales;
                $preguntas[$key]['plan_accion_manu'] = $planManual;
                $preguntas[$key]['tiposRespuestas'] = $tiposRespuestas;
                $preguntas[$key]['opcionesGeneralesLlenas'] = $OpcionesGeneralesLlenas;
                $preguntas[$key]['opcionesGeneralesLlenasFotos'] = $cantidadPreguntas;
                $preguntas[$key]['opcionesGeneralesLlenasAdjuntos'] = $opcGeneralesAdjuntos;
                $preguntas[$key]['opcionesGeneralesLlenasPlanAccionM'] = $opcGeneralesPlanAccionM;
            }

            $objeto->PREGUNTAS = $preguntas;

            array_push($arrayFinal, $objeto);
        }

        return $arrayFinal;
    }

    public function AgregarRespuesta(Request $request)
    {
        $idPregunta = $request->get('idPregunta');
        $ponderadoPregunta = $request->get('ponderadoPregunta');
        $idCategoria = $request->get('idCategoria');
        $ponderadoCategoria = $request->get('ponderadoCategoria');
        $idRespuesta = $request->get('idRespuesta');
        $idListaChequeoEjec = $request->get('idListaChequeoEjec');
        $tipoRespuesta = $request->get('tipoRespuesta');
        $respuestaAbierta = ($request->get('respuestaAbierta') == '' ? NULL : $request->get('respuestaAbierta'));

        if ($idRespuesta == 0) // SIGNIFICA QUE VIENE EL N/A
        {
            $idRespuesta = NULL;
            $noAplica = 1;
        } else
            $noAplica = 0;

        if ($this->respuestaEjecucion->where([
            ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
            ['pregunta_id', '=', $idPregunta]
        ])->exists()) {
            //ACTUALIZAR RESPUESTA
            $arrayActualizar = [
                'respuesta_id' => $idRespuesta,
                'no_aplica' => $noAplica,
                'respuesta_abierta' => $respuestaAbierta
            ];

            $respuestaUpdate = $this->respuestaEjecucion->where([
                ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
                ['pregunta_id', '=', $idPregunta]
            ])->update($arrayActualizar);

            //ACTUALIZANDO PLAN DE ACCIÓN
            $datosEjecucion = $this->respuestaEjecucion->where([
                ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
                ['pregunta_id', '=', $idPregunta]
            ])->first();

            if ($this->opcRespuesta->where(
                'lista_chequeo_ejec_respuestas_id',
                '=',
                $datosEjecucion->id
            )->exists()) {
                $idPlanDeAccion = NULL;
                if ($datosEjecucion->no_aplica == 0) {
                    $idPlanDeAccion = $this->planAccion->where('respuesta_id', '=', $datosEjecucion->respuesta_id)->first();
                    //$idPlanDeAccion = $this->planAccionAutomatico->where('respuesta_id','=',$datosEjecucion->respuesta_id)->first();
                    if (!is_null($idPlanDeAccion))
                        $idPlanDeAccion = $idPlanDeAccion->id;
                }

                $arrayActualizar = [
                    'plan_accion_id' => $idPlanDeAccion
                ];

                $respuestaUpdate = $this->opcRespuesta->where('lista_chequeo_ejec_respuestas_id', '=', $datosEjecucion->id)->update($arrayActualizar);

                //ELIMINAR CORRECTIVO
                $opc = $this->opcRespuesta->where(
                    'lista_chequeo_ejec_respuestas_id',
                    '=',
                    $datosEjecucion->id
                )->first();
                $respuesta = $this->ejecucionPlanAccion->where('lista_chequeo_ejec_opciones', $opc->id)->delete();

                if (!is_null($idPlanDeAccion)) {
                    $arrayInsertar = [
                        'lista_chequeo_ejec_opciones' => $opc->id
                    ];

                    $ejecucionPlanAccion = new $this->ejecucionPlanAccion;
                    $ejecucionPlanAccion->fill($arrayInsertar);

                    $ejecucionPlanAccion->save();
                }
            }

            return $this->FinalizarRetorno(
                206,
                $this->MensajeRetorno('', 206, 'La respuesta ha sido guardada')
            );
        } else {

            $arrayInsertar = [
                'pregunta_id' => $idPregunta,
                'ponderado_pregunta' => $ponderadoPregunta,
                'categoria_id' => $idCategoria,
                'ponderado_categoria' => $ponderadoCategoria,
                'respuesta_id' => $idRespuesta,
                'no_aplica' => $noAplica,
                'lista_chequeo_ejec_id' => $idListaChequeoEjec,
                'respuesta_abierta' => $respuestaAbierta
            ];

            $respuestaEjecucion = new $this->respuestaEjecucion;
            $respuestaEjecucion->fill($arrayInsertar);

            if ($respuestaEjecucion->save()) {

                $idPlanDeAccion = NULL;
                if ($noAplica == 0) {
                    $idPlanDeAccion = $this->planAccion->where('respuesta_id', '=', $idRespuesta)->first();
                    //$idPlanDeAccion = $this->planAccionAutomatico->where('respuesta_id','=',$idRespuesta)->first();
                    if (!is_null($idPlanDeAccion))
                        $idPlanDeAccion = $idPlanDeAccion->id;
                }
                if ($idPlanDeAccion != null) {
                    $arrayInsertar = [
                        'lista_chequeo_ejec_respuestas_id' => $respuestaEjecucion->id,
                        'plan_accion_id' => $idPlanDeAccion
                    ];

                    $opcRespuesta = new $this->opcRespuesta;
                    $opcRespuesta->fill($arrayInsertar);

                    $opcRespuesta->save();
                }

                //ELIMINAR CORRECTIVO
                $opc = $this->opcRespuesta->where(
                    'lista_chequeo_ejec_respuestas_id',
                    '=',
                    $respuestaEjecucion->id
                )->first();

                if (isset($opc->id))
                    $respuesta = $this->ejecucionPlanAccion->where('lista_chequeo_ejec_opciones', $opc->id)->delete();

                if (!is_null($idPlanDeAccion)) {
                    $arrayInsertar = [
                        'lista_chequeo_ejec_opciones' => $opc->id
                    ];

                    $ejecucionPlanAccion = new $this->ejecucionPlanAccion;
                    $ejecucionPlanAccion->fill($arrayInsertar);

                    $ejecucionPlanAccion->save();
                }

                return $this->FinalizarRetorno(
                    206,
                    $this->MensajeRetorno('', 206, 'La respuesta ha sido guardada')
                );
            } else {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('', 406, 'La respuesta no se pudo guardar')
                );
            }
        }
    }

    public function AgregarComentarioRespuesta(Request $request)
    {
        $idPregunta = $request->get('idPregunta');
        $idListaChequeoEjec = $request->get('idListaChequeoEjec');
        $comentario = $request->get('comentario');

        $respuestaEjecucion = $this->respuestaEjecucion->where([
            ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
            ['pregunta_id', '=', $idPregunta]
        ])->first();


        if ($this->opcRespuesta->where('lista_chequeo_ejec_respuestas_id', '=', $respuestaEjecucion->id)->exists()) {
            //ACTUALIZAR RESPUESTA
            $arrayActualizar = [
                'comentario' => $comentario,
            ];

            $respuestaUpdate = $this->opcRespuesta->where('lista_chequeo_ejec_respuestas_id', '=', $respuestaEjecucion->id)->update($arrayActualizar);

            return $this->FinalizarRetorno(
                206,
                $this->MensajeRetorno('', 206, 'El comentario ha sido actualizado'),
                $comentario
            );
        } else {
            $arrayInsertar = [
                'lista_chequeo_ejec_respuestas_id' => $respuestaEjecucion->id,
                'comentario' => $comentario,
            ];

            $opcRespuesta = new $this->opcRespuesta;
            $opcRespuesta->fill($arrayInsertar);

            if ($opcRespuesta->save()) {
                return $this->FinalizarRetorno(
                    206,
                    $this->MensajeRetorno('', 206, 'El comentario ha sido guardado'),
                    $comentario
                );
            } else {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('', 406, 'No se logró guardar el comentario')
                );
            }
        }
    }

    public function GuardarImagenesListaEjecucion(Request $request)
    {
        $idPregunta = $request->get('dataImagen')['idPregunta'];
        $idListaChequeoEjec = $request->get('dataImagen')['idListaChequeoEjecutada'];
        $imagenes = $request->get('dataImagen')['imagenes'];

        $respuestaEjecucion = $this->respuestaEjecucion->where([
            ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
            ['pregunta_id', '=', $idPregunta]
        ])->first();

        $preguntas = $this->listaChequeoEjecutadasFotos->where('lista_chequeo_ejec_respuestas', '=', $respuestaEjecucion->id)->get();

        foreach ($preguntas as $key => $foto) {
            if (\File::exists($this->urlImagenesListaChequeo . $foto->foto))
                \File::delete($this->urlImagenesListaChequeo . $foto->foto);
        }

        $listaChequeoEjecutadasFotos = $this->listaChequeoEjecutadasFotos->where('lista_chequeo_ejec_respuestas', $respuestaEjecucion->id)->delete();

        foreach ($imagenes as $key => $valueId) {
            foreach ($valueId as $keyImg => $valueImg) {
                $listaChequeoEjecutadasFotos = new $this->listaChequeoEjecutadasFotos;

                $nombreImagen = $idPregunta . '_' . $idListaChequeoEjec . Str::random(10) . '.' . 'png';
                $imagen = $valueImg['img'];
                $implode = explode(',', $imagen);
                $guardado = \File::put($this->urlImagenesListaChequeo . $nombreImagen, base64_decode($implode[1]));

                $listaChequeoEjecutadasFotos->fill([
                    'foto' => $nombreImagen,
                    'lista_chequeo_ejec_respuestas' => $respuestaEjecucion->id
                ]);

                $listaChequeoEjecutadasFotos->save();
            }
        }

        return $this->FinalizarRetorno(
            206,
            $this->MensajeRetorno('', 206, 'Imagenes agregadas correctamente')
        );
    }

    //Esta funcion va a depurar los archivos que no existan fisicamente los eliminara de la BD
    private function depurarArchivosAdjuntosServer()
    {
        $adjuntos = $this->listaChequeoEjecutadasArchivos->all();
        foreach ($adjuntos as $kek => $value) {
            $exists = \Storage::disk('public')->exists($value->archivo_codificado);
            if ($exists == false) {
                $adjuntos->find($value->id)->delete();
                //dd($value->id);
            }
        }
    }


    public function guardarAdjuntos(Request $request)
    {
        $archivos = $request->file('adjuntos');
        //dd($archivos);
        $idPregunta = $request->get('idPregunta');
        $idListaChequeoEjec = $request->get('idListaChequeoEjec');
        $respuestaEjecucion = $this->respuestaEjecucion->where([
            ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
            ['pregunta_id', '=', $idPregunta]
        ])->first();


        $fileName = ''; //Nombre con el que se va a guardar el archivo
        $originalName = ''; //Nombre original cuando se subio el archivo
        foreach ($archivos as $key => $archivo) {
            $fileName = $archivo->hashName();
            $originalName = $archivo->getclientOriginalName();
            $this->listaChequeoEjecutadasArchivos->create([
                'archivo_codificado' => $fileName,
                'archivo_alias' => $originalName,
                'lista_chequeo_ejec_respuesta_id' => $respuestaEjecucion->id
            ]);
            $path = \Storage::putFile('public', $archivo);
        }
        $this->depurarArchivosAdjuntosServer(); //Depuro para no dejar registros en la BD que no existan en la carpeta storage
        return response()->json(['msg' => 'Los archivos han sido cargados.']);
    }

    public function traerArchivosAdjuntos(Request $request)
    {
        $idPregunta = $request->get('idPregunta');
        $idListaChequeoEjec = $request->get('idListaChequeoEjec');
        $respuestaEjecucion = $this->respuestaEjecucion->where([
            ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
            ['pregunta_id', '=', $idPregunta]
        ])->first();

        $adjuntos = $this->listaChequeoEjecutadasArchivos->where('lista_chequeo_ejec_respuesta_id', '=', $respuestaEjecucion->id)->get();
        $arrayAdjuntos = [];
        if (count($adjuntos) != 0) {
            foreach ($adjuntos as $key => $value) {

                $alias = $value->archivo_alias;
                array_push($arrayAdjuntos, ['id' => $value->id, 'nombre' => $alias]);
            }
        } else {
            $arrayAdjuntos = [];
        }
        return response()->json(['adjuntos' => $arrayAdjuntos]);
    }

    public function elimnarArchivoAdjunto(Request $request)
    {
        $idFile = $request->get('idFile');
        $archivo = $this->listaChequeoEjecutadasArchivos->findOrFail($idFile);
        $archivoAlias = $archivo->archivo_alias;
        //Valido si existe el archivo y lo elimino
        $exists = \Storage::disk('public')->exists($archivo->archivo_codificado);
        if ($exists)
            \Storage::disk('public')->delete($archivo->archivo_codificado);
        $archivo->delete();
        return response()->json(['msg' => 'Se elimino el archivo ' . $archivoAlias]);
    }

    public function TraerImagenesGuardadas(Request $request)
    {
        $idPregunta = $request->get('idPregunta');
        $idListaChequeoEjec = $request->get('idListaChequeoEjec');

        $respuestaEjecucion = $this->respuestaEjecucion->where([
            ['lista_chequeo_ejec_id', '=', $idListaChequeoEjec],
            ['pregunta_id', '=', $idPregunta]
        ])->first();

        $fotos = $this->listaChequeoEjecutadasFotos->where('lista_chequeo_ejec_respuestas', '=', $respuestaEjecucion->id)->get();

        $arrayImage = [];
        if (COUNT($fotos) != 0) {
            foreach ($fotos as $key => $value) {

                $url = $this->urlImagenesListaChequeo . $value->foto;
                $content = file_get_contents($url);
                $imdata = base64_encode($content);

                $arrayImage[] = 'data:image/jpg;base64,' . $imdata;
            }
        } else {
            $arrayImage = [];
        }

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('', 202),
            $arrayImage
        );
    }

    public function FinalizarListaChequeo(Request $request)
    {
        $evaluadoId = $request->get('evaluadoId');
        $latitud = $request->get('latitud');
        $longitud = $request->get('longitud');
        $direccion = $request->get('direccion');
        $estado = $request->get('estado');
        $fechaRealizacion = $request->get('fechaRealizacion');
        $idListaChequeoEjec = $request->get('idListaChequeoEjec');
        $obsgeneral = $request->get('obsgeneral');
        $finished = date('Y-m-d');
        $fechaRealizacion = date('Y-m-d', strtotime($fechaRealizacion));
        $fechaRealizacion = Carbon::createFromFormat('Y-m-d', $fechaRealizacion);

        $arrayActualizar = [
            'evaluado_id' => $evaluadoId,
            'latitud' => ($latitud == 0 ? NULL : $latitud),
            'longitud' => ($longitud == 0 ? NULL : $longitud),
            'direccion' => ($direccion == '' ? NULL : $direccion),
            'estado' => $estado,
            'fecha_realizacion' => $fechaRealizacion,
            'finished_at' => $finished,
            'observacion_general' => $obsgeneral
        ];

        $respuestaUpdate = $this->listaEjecutada->where('id', '=', $idListaChequeoEjec)->update($arrayActualizar);

        $this->FuncionEnvioDeCorreoListaTerminada($idListaChequeoEjec);

        return $this->FinalizarRetorno(
            206,
            $this->MensajeRetorno('', 206, 'Lista de chequeo finalizada')
        );
    }

    public function FuncionEnvioDeCorreoListaTerminada($idListaChequeoEjec)
    {
        $listaDeChequeo = $this->listaEjecutada
            ->select(
                'lista_chequeo_ejecutadas.*',
                'u.nombre_completo AS NOMBRE_USUARIO'
            )
            ->Join('usuario AS u', 'u.id', '=', 'lista_chequeo_ejecutadas.usuario_id')
            ->where('lista_chequeo_ejecutadas.id', '=', $idListaChequeoEjec)->first();

        $arrayCorreos = [];
        //ENVIAR CORREO A ÉL MISMO
        array_push($arrayCorreos, auth()->user()->correo);

        //ENVIAR CORREO AL ADMINISTRADOR
        $cuentaPrincipal = $this->cuentaPrincipal->where('id', '=', auth()->user()->cuenta_principal_id)->first();
        array_push($arrayCorreos, $cuentaPrincipal->correo_electronico);

        //ENVIAR CORREO A RESPONSABLE EMPRESA
        $idUsuarioResponsable = $this->listaEjecutada
            ->select(
                'em.usuario_id AS ID_USUARIO_EMPRESA',
                'e.usuario_id AS ID_USUARIO_ESTABLECIMIENTO'
            )
            ->Join('usuario AS u', 'u.id', '=', 'lista_chequeo_ejecutadas.usuario_id')
            ->Join('establecimiento AS e', 'e.id', '=', 'u.establecimiento_id')
            ->Join('empresa AS em', 'em.id', '=', 'e.empresa_id')
            ->where('lista_chequeo_ejecutadas.id', '=', $idListaChequeoEjec)->first();

        if (!is_null($idUsuarioResponsable->ID_USUARIO_EMPRESA)) {
            // SI EXISTE RESPONSABLE EMPRESA
            $usuarioResponsableEmpresa = $this->usuario->where('id', '=', $idUsuarioResponsable->ID_USUARIO_EMPRESA)->first();
            array_push($arrayCorreos, $usuarioResponsableEmpresa->correo);
        }

        if (!is_null($idUsuarioResponsable->ID_USUARIO_ESTABLECIMIENTO)) {
            // SI EXISTE RESPONSABLE ESTABLECIMIENTO
            $usuarioResponsableEmpresa = $this->usuario->where('id', '=', $idUsuarioResponsable->ID_USUARIO_ESTABLECIMIENTO)->first();
            array_push($arrayCorreos, $usuarioResponsableEmpresa->correo);
        }

        \Mail::to($arrayCorreos)->send(new MailFinalizarListaChequeo($listaDeChequeo));

        $respuesta = $this->ValidarSiTieneResponsable($idListaChequeoEjec);
        $respuestaQuien = $this->ValidarSiTieneResponsable($idListaChequeoEjec, 5);

        $arrayCorreoUsuarioResponsable = [];
        if (COUNT($respuesta) != 0) {
            foreach ($respuesta as $key => $usuarioEncontrado) {
                $usuario = $this->usuario
                    ->where('id', '=', $usuarioEncontrado->ID_USUARIO_RESPONSABLE)
                    ->first();

                if (isset($usuario->correo))
                    array_push($arrayCorreoUsuarioResponsable, $usuario->correo);
            }
        } else if (COUNT($respuestaQuien) != 0) {
            foreach ($respuestaQuien as $key => $usuarioEncontradoQuien) {
                $usuarioQuien = $this->usuario
                    ->where('id', '=', $usuarioEncontradoQuien->ID_USUARIO_RESPONSABLE)
                    ->first();

                if (isset($usuarioQuien->correo))
                    array_push($arrayCorreoUsuarioResponsable, $usuarioQuien->correo);
            }
        }

        if (COUNT($arrayCorreoUsuarioResponsable) != 0)
            \Mail::to($arrayCorreoUsuarioResponsable)->send(new MailResponsablesPlanAccionManual($listaDeChequeo));
    }

    public function ValidarSiTieneResponsable($idListaChequeoEjecutada, $opcion = 8)
    {
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
                'pa.tipo_pa as tipo_plan_accion',
                'plan_accion_manu_det.respuesta AS ID_USUARIO_RESPONSABLE'
            )
            ->Join('lista_chequeo_ejec_respuestas AS lcer', 'lcer.id', '=', 'plan_accion_manu_det.lista_cheq_ejec_respuesta_id')
            ->Join('lista_chequeo_ejecutadas AS lce', 'lce.id', '=', 'lcer.lista_chequeo_ejec_id')
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lce.lista_chequeo_id')
            ->Join('usuario AS us', 'us.id', '=', 'lce.usuario_id')
            ->Join('establecimiento AS esta', 'esta.id', '=', 'us.establecimiento_id')
            ->Join('empresa AS empe', 'empe.id', '=', 'esta.empresa_id')
            ->Join('pregunta AS pre', 'pre.id', '=', 'lcer.pregunta_id')
            ->Join('respuesta AS res', 'res.id', '=', 'lcer.respuesta_id')
            ->Join('lista_chequeo_ejec_opciones AS lceo', 'lceo.lista_chequeo_ejec_respuestas_id', '=', 'lcer.id')
            ->Join('lista_chequeo_ejec_planaccion AS lcep', 'lcep.lista_chequeo_ejec_opciones', '=', 'lceo.id')
            ->Join('plan_accion as pa', 'pa.id', '=', 'lceo.plan_accion_id')
            ->where([
                ['lce.id', '=', $idListaChequeoEjecutada],
                ['lce.estado', '=', 2],
                ['plan_accion_manu_det.plan_accio_man_opc_id', '=', $opcion]
            ])
            ->get();

        return $traerPlanAcciones;
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
        WHERE us.cuenta_principal_id=:idCuentaPrincipal;"), ['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        if (isset($puedeEjecutar)) {
            if (COUNT($puedeEjecutar) != 0) {
                if ($puedeEjecutar[0]->puede_ejecutar == 'SI')
                    return true;
                else
                    return false;
            } else
                return false;
        } else
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
        ORDER BY pp.id DESC limit 1;"), ['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        if (isset($puedeEjecutar)) {
            if (COUNT($puedeEjecutar) != 0) {
                if ($puedeEjecutar[0]->aldia == 'SI')
                    return true;
                else
                    return false;
            } else
                return true; //ES GRATIS
        } else
            return false;
    }

    public function lista_opc_plan_accion_manual(Request $request)
    {
        $planManual = \DB::table('plan_accion')->select(
            "plan_accion.tipo_pa as tipo",
            "pam.requerido as obligatorio",
            "plan_accion.alerta as alerta",
            "pam.plan_accion_man_opc_id as opcion_id",
            "pamo.opcion as nom_opcion"
        )
            ->join("plan_accion_manual as pam", "plan_accion.id", "=", "pam.plan_accion_id")
            ->leftJoin("plan_accion_man_opc as pamo", "pam.plan_accion_man_opc_id", "=", "pamo.id")
            ->where("pregunta_id", "=", $request->idpregunta)->get();

        $idListaChequeo = $request->get('idListaChequeo');
        $idEvaluado = $request->get('evaluadoId');

        $busqueda = $this->listaChequeo
            ->select(
                \DB::raw('(
                CASE
                    WHEN lista_chequeo.entidad_evaluada = 1 THEN "Empresa"
                    WHEN lista_chequeo.entidad_evaluada = 2 THEN "Establecimiento"
                    WHEN lista_chequeo.entidad_evaluada = 3 THEN "Usuario"
                    WHEN lista_chequeo.entidad_evaluada = 4 THEN "Proceso"
                    WHEN lista_chequeo.entidad_evaluada = 5 THEN "Equipos"
                END
                ) AS EVALUANDO_A')
            )
            ->leftJoin('lista_chequeo_encabezado AS lce', 'lce.lista_chequeo_id', '=', 'lista_chequeo.id')
            ->where([
                ['lista_chequeo.estado', '=', 1],
                ['lista_chequeo.id', '=', $idListaChequeo]
            ])
            ->first();

        $llenadoDeSelect = [];


        if (!is_null($busqueda)) {
            switch ($busqueda->EVALUANDO_A) {
                case 'Empresa':

                    $llenadoDeSelect = $this->usuario
                        ->select(
                            'usuario.*',
                            \DB::raw('IF(ca.nombre IS NULL, "Sin cargo",ca.nombre) AS CARGO')
                        )
                        ->leftJoin('cargo AS ca', 'ca.id', 'usuario.cargo_id')
                        ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                        ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                        ->where('em.id', '=', $idEvaluado)
                        ->get();

                    break;

                case 'Establecimiento':


                    $sacarIdEmpresa = $this->usuario
                        ->select(
                            'e.id AS ID',
                            'e.nombre AS NOMBRE',
                            'e.empresa_id AS ID_EMPRESA'
                        )
                        ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                        ->where('e.id', '=', $idEvaluado)
                        ->first();

                    $llenadoDeSelect = $this->usuario
                        ->select(
                            'usuario.*',
                            \DB::raw('IF(ca.nombre IS NULL, "Sin cargo",ca.nombre) AS CARGO')
                        )
                        ->leftJoin('cargo AS ca', 'ca.id', 'usuario.cargo_id')
                        ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                        ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                        ->where('em.id', '=', $sacarIdEmpresa->ID_EMPRESA)
                        ->get();

                    break;

                case 'Usuario':

                    $sacarIdEmpresa = $this->usuario
                        ->select(
                            'em.id AS ID_EMPRESA'
                        )
                        ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                        ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                        ->where('usuario.id', '=', $idEvaluado)
                        ->first();

                    $llenadoDeSelect = $this->usuario
                        ->select(
                            'usuario.*',
                            \DB::raw('IF(ca.nombre IS NULL, "Sin cargo",ca.nombre) AS CARGO')
                        )
                        ->leftJoin('cargo AS ca', 'ca.id', 'usuario.cargo_id')
                        ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                        ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                        ->where('em.id', '=', $sacarIdEmpresa->ID_EMPRESA)
                        ->get();

                    break;

                case 'Proceso':

                    break;

                case 'Equipos':
                    $llenadoDeSelect = $this->usuario
                        ->select(
                            'usuario.*',
                            \DB::raw('IF(ca.nombre IS NULL, "Sin cargo",ca.nombre) AS CARGO')
                        )
                        ->leftJoin('cargo AS ca', 'ca.id', 'usuario.cargo_id')
                        ->Join('establecimiento AS e', 'e.id', 'usuario.establecimiento_id')
                        ->Join('empresa AS em', 'em.id', 'e.empresa_id')
                        ->where('usuario.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id)
                        ->get();
                    break;

                default:
                    # code...
                    break;
            }
        }


        return response()->json([
            'datos' => $planManual,
            'Responsables' => $llenadoDeSelect
        ]);
    }

    public function guardar_plan_accion_manual(Request $request)
    {
        $listaChequeoEjec = \DB::table('lista_chequeo_ejecutadas')->select('*')->where('lista_chequeo_id', '=', $request->idListaChequeo)
            ->max('id');

        $idpregunta = $request->idpregunta;
        $lista_cheque_ejec_resp = \DB::table('lista_chequeo_ejec_respuestas')
            ->select('*')->where('pregunta_id', '=', $idpregunta)
            ->where('lista_chequeo_ejec_id', '=', $listaChequeoEjec)
            ->first();

        $eliminarUnaVez = 0;

        foreach ($request->all() as $key => $valor) {
            if ($key != 'idpregunta' and $valor != '' and $lista_cheque_ejec_resp != null and $key != 'idListaChequeo') {
                $data = [
                    'plan_accio_man_opc_id' => $key,
                    'lista_cheq_ejec_respuesta_id' => $lista_cheque_ejec_resp->id,
                    'respuesta' => $valor
                ];

                if ($eliminarUnaVez == 0) {
                    $this->planAccionManualDetalle->where('lista_cheq_ejec_respuesta_id', '=', $lista_cheque_ejec_resp->id)->delete();
                    $eliminarUnaVez = 1;
                }


                $this->planAccionManualDetalle->updateOrCreate($data);
            }
        }

        //GUARDO EN LISTA DE CHEQUEO EJEC OPCIONES PARA PODER APLICAR UNA ACCION CORRECTIVA                
        $idPlanDeAccion = $this->planAccion->where('pregunta_id', '=', $idpregunta)->first();
        if ($idPlanDeAccion != null) {
            $arrayInsertar = [
                'lista_chequeo_ejec_respuestas_id' => $lista_cheque_ejec_resp->id,
                'plan_accion_id' => $idPlanDeAccion->id
            ];
            $this->opcRespuesta->updateOrCreate(
                ['lista_chequeo_ejec_respuestas_id' => $lista_cheque_ejec_resp->id],
                $arrayInsertar
            );
        }


        //ELIMINAR CORRECTIVO
        $opc = $this->opcRespuesta->where(
            'lista_chequeo_ejec_respuestas_id',
            '=',
            $lista_cheque_ejec_resp->id
        )->first();

        $respuesta = $this->ejecucionPlanAccion->where('lista_chequeo_ejec_opciones', $opc->id)->delete();


        $arrayInsertar = [
            'lista_chequeo_ejec_opciones' => $opc->id
        ];

        $ejecucionPlanAccion = new $this->ejecucionPlanAccion;
        $ejecucionPlanAccion->fill($arrayInsertar);
        $ejecucionPlanAccion->save();


        return response()->json(['res' => 'Se guardo']);
    }

    public function traer_datos_plan_accion_manual(Request $request)
    {
        $idpregunta = $request->idpregunta;
        $lista_cheque_ejec_resp = \DB::table('lista_chequeo_ejec_respuestas')
            ->select('*')->where('pregunta_id', '=', $idpregunta)->first();

        $planAccionM = $this->planAccionManualDetalle->where('lista_cheq_ejec_respuesta_id', '=', $lista_cheque_ejec_resp->id)->get();

        return response()->json([
            'data' => $planAccionM
        ]);
    }
}
