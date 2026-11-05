<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\ListaChequeoEjecutadas;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use App\Http\Models\ListaChequeoEjecutadasRespuestas;
use App\Http\Models\ListaChequeo;
use App\Exports\EvaluacionExports;
use App\Exports\DotacionPracticasExports;
use App\Exports\VerificacionBalanzasExports;
use App\Exports\TemperaturaFriosExports;
use App\Exports\PlanillaTitulacionExports;
use App\Exports\PlanillaTitulacionExportsGonvanrri;
use Carbon\Carbon;

class InformesController extends Controller
{
    protected $listaEjecutada, $ejecutadasRespuestas, $listaChequeo;
    public function __construct(
        ListaChequeoEjecutadas $listaEjecutada,
        Empresa $empresa,
        Establecimiento $establecimiento,
        ListaChequeoEjecutadasRespuestas $ejecutadasRespuestas,
        ListaChequeo $listaChequeo
    ) {
        $this->listaEjecutada = $listaEjecutada;
        $this->empresa = $empresa;
        $this->establecimiento = $establecimiento;
        $this->ejecutadasRespuestas = $ejecutadasRespuestas;
        $this->listaChequeo = $listaChequeo;

        try {
            \DB::statement("SET lc_time_names = 'es_ES'");
        } catch (\Throwable $e) {}
        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        $listaChequeo = $this->FuncionParaTraerListasChequeoInformes();
        $evaluados = $this->FuncionParaTraerEvaluadoInformes();
        $evaluadores = $this->FuncionParaTraerEvaluadoresInformes();
        $estados = $this->FuncionParaTraerEstadosInformes();
        $entidadEvaluada = $this->FuncionParaTraerEntidadEvaluadaInformes();

        return view('Admin.informes_ejecutadas', compact('listaChequeo', 'evaluados', 'evaluadores', 'estados', 'entidadEvaluada'));
    }

    public function FuncionParaTraerListasChequeoInformes()
    {
        $listraChequeoFiltro = $this->listaEjecutada
            ->select(
                'lc.id',
                'lc.nombre AS lista_chequeo'
            )
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS usu', 'usu.id', '=', 'lc.usuario_id')
            ->Join('establecimiento AS est', 'est.id', '=', 'usu.establecimiento_id')
            ->Join('empresa AS emp', 'emp.id', '=', 'est.empresa_id')
            ->groupBy('lc.nombre');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $listraChequeoFiltro = $listraChequeoFiltro->where('usu.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if (!is_null($esResponsableEmpresa))
                    $listraChequeoFiltro = $listraChequeoFiltro->where('emp.id', '=', $esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if (!is_null($esResponsableEstablecimiento))
                    $listraChequeoFiltro = $listraChequeoFiltro->where('est.id', '=', $esResponsableEstablecimiento->id);

                if (is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $listraChequeoFiltro = $listraChequeoFiltro->where('lista_chequeo_ejecutadas.usuario_id', '=', auth()->user()->id);

                break;

            default:

                break;
        };

        return $listraChequeoFiltro->get();
    }

    public function FuncionParaTraerEvaluadoInformes()
    {
        $evaluados = $this->listaEjecutada
            ->select(
                'lista_chequeo_ejecutadas.evaluado_id as id',
                \DB::raw(
                    '(CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lista_chequeo_ejecutadas.evaluado_id)
                ELSE "Error"
            END) as evaluado',
                    'lc.entidad_evaluada'
                )
            )
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS usu', 'usu.id', '=', 'lista_chequeo_ejecutadas.usuario_id')
            ->Join('establecimiento AS est', 'est.id', '=', 'usu.establecimiento_id')
            ->Join('empresa AS emp', 'emp.id', '=', 'est.empresa_id')
            ->groupBy('lista_chequeo_ejecutadas.evaluado_id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $evaluados = $evaluados->where('usu.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if (!is_null($esResponsableEmpresa))
                    $evaluados = $evaluados->where('emp.id', '=', $esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if (!is_null($esResponsableEstablecimiento))
                    $evaluados = $evaluados->where('est.id', '=', $esResponsableEstablecimiento->id);

                if (is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $evaluados = $evaluados->where('lista_chequeo_ejecutadas.usuario_id', '=', auth()->user()->id);

                break;

            default:

                break;
        };

        return $evaluados->get();
    }

    public function FuncionParaTraerEvaluadoresInformes()
    {
        $evaluadores = $this->listaEjecutada
            ->select(
                'usu.nombre_completo AS evaluador',
                'usu.id'
            )
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS usu', 'usu.id', '=', 'lista_chequeo_ejecutadas.usuario_id')
            ->Join('establecimiento AS est', 'est.id', '=', 'usu.establecimiento_id')
            ->Join('empresa AS emp', 'emp.id', '=', 'est.empresa_id')
            ->groupBy('usu.id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $evaluadores = $evaluadores->where('usu.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if (!is_null($esResponsableEmpresa))
                    $evaluadores = $evaluadores->where('emp.id', '=', $esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if (!is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where('est.id', '=', $esResponsableEstablecimiento->id);

                if (is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where('lista_chequeo_ejecutadas.usuario_id', '=', auth()->user()->id);

                break;

            default:

                break;
        };

        return $evaluadores->get();
    }

    public function FuncionParaTraerEstadosInformes()
    {
        $evaluadores = $this->listaEjecutada
            ->select(
                \DB::raw('(
                CASE
                    WHEN lista_chequeo_ejecutadas.estado = 0 THEN "Cancelada"
                    WHEN lista_chequeo_ejecutadas.estado = 1 THEN "Proceso"
                    WHEN lista_chequeo_ejecutadas.estado = 2 THEN "Terminada"
                END
            ) AS ESTADO_NOMBRE'),
                'lista_chequeo_ejecutadas.estado AS ID_ESTADO'
            )
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS usu', 'usu.id', '=', 'lc.usuario_id')
            ->Join('establecimiento AS est', 'est.id', '=', 'usu.establecimiento_id')
            ->Join('empresa AS emp', 'emp.id', '=', 'est.empresa_id')
            ->groupBy('lista_chequeo_ejecutadas.estado');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $evaluadores = $evaluadores->where('usu.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if (!is_null($esResponsableEmpresa))
                    $evaluadores = $evaluadores->where('emp.id', '=', $esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if (!is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where('est.id', '=', $esResponsableEstablecimiento->id);

                if (is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $evaluadores = $evaluadores->where('lista_chequeo_ejecutadas.usuario_id', '=', auth()->user()->id);

                break;

            default:

                break;
        };

        return $evaluadores->get();
    }

    public function FuncionParaTraerEntidadEvaluadaInformes()
    {
        $entidadEvaluada = $this->listaEjecutada
            ->select(
                \DB::raw('(CASE
                    WHEN lc.entidad_evaluada=1 THEN "Empresa"
                    WHEN lc.entidad_evaluada=2 THEN "Establecimiento"
                    WHEN lc.entidad_evaluada=3 THEN "Usuario"
                    WHEN lc.entidad_evaluada=4 THEN "Areas"
                    WHEN lc.entidad_evaluada=5 THEN "Equipos"
                    ELSE "Error"
            END) as entidad_evaluada'),
                'lc.entidad_evaluada AS ID_ENTIDAD_EVALUADA'
            )
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS usu', 'usu.id', '=', 'lc.usuario_id')
            ->Join('establecimiento AS est', 'est.id', '=', 'usu.establecimiento_id')
            ->Join('empresa AS emp', 'emp.id', '=', 'est.empresa_id')
            ->groupBy('lc.entidad_evaluada');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $entidadEvaluada = $entidadEvaluada->where('usu.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if (!is_null($esResponsableEmpresa))
                    $entidadEvaluada = $entidadEvaluada->where('emp.id', '=', $esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if (!is_null($esResponsableEstablecimiento))
                    $entidadEvaluada = $entidadEvaluada->where('est.id', '=', $esResponsableEstablecimiento->id);

                if (is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $entidadEvaluada = $entidadEvaluada->where('lista_chequeo_ejecutadas.usuario_id', '=', auth()->user()->id);

                break;

            default:

                break;
        };

        return $entidadEvaluada->get();
    }

    public function TraerInformacionInformeEjecutadas(Request $request)
    {
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        $informeEjecutadas = $this->FuncionTraerInformesEjecutadas($paginacion, $filtros);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos', 202),
            $informeEjecutadas
        );
    }

    public function FuncionTraerInformesEjecutadas($paginacion = 1, $filtros = [])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);

        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) {

            switch ($key) {

                case 'filtro_lista_chequeo':
                    if ($filtro != '')
                        array_push($filtro_array, ['lc.id', '=', $filtro]);
                    break;

                case 'filtro_realizacion':
                    if ($filtro != '')
                        array_push($filtro_array, ['lista_chequeo_ejecutadas.fecha_realizacion', '=', Carbon::createFromFormat('d/m/Y', $filtro)->format('Y-m-d')]);
                    break;

                case 'filtro_estado':
                    if ($filtro != '')
                        array_push($filtro_array, ['lista_chequeo_ejecutadas.estado', '=', $filtro]);
                    break;

                case 'filtro_entidad':
                    if ($filtro != '')
                        array_push($filtro_array, ['lc.entidad_evaluada', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if ($filtro != '')
                        array_push($filtro_array, ['lista_chequeo_ejecutadas.evaluado_id', '=', $filtro]);
                    break;

                case 'filtro_evaluador':
                    if ($filtro != '')
                        array_push($filtro_array, ['usu.id', '=', $filtro]);
                    break;

                default:

                    break;
            }
        }

        $traerInformeEjecutadas = $this->listaEjecutada
            ->select(
                'lista_chequeo_ejecutadas.id AS ID_EJECUTADA',
                'lc.nombre AS lista_chequeo',
                \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y %h:%i:%s %p") AS FECHA_REALIZACION'),
                'lista_chequeo_ejecutadas.latitud',
                'lista_chequeo_ejecutadas.longitud',
                \DB::raw('IF((CASE
                        WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lista_chequeo_ejecutadas.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lista_chequeo_ejecutadas.evaluado_id)
                        ELSE "Error"
                    END) IS NULL ,"Sin asignar", (CASE
                    WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                    WHERE susu.id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lista_chequeo_ejecutadas.evaluado_id)
                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lista_chequeo_ejecutadas.evaluado_id)
                    ELSE "Error"
                END)) as empresa'),
                \DB::raw('IF(lista_chequeo_ejecutadas.direccion IS NULL, "", lista_chequeo_ejecutadas.direccion) AS DIRECCION'),
                'lista_chequeo_ejecutadas.estado AS ID_ESTADO',
                \DB::raw('(CASE 
                WHEN lista_chequeo_ejecutadas.estado=0 THEN "Cancelada"
                WHEN lista_chequeo_ejecutadas.estado=1 THEN "Proceso"
                WHEN lista_chequeo_ejecutadas.estado=2 THEN "Terminada"
            END) AS estado'),
                \DB::raw('(CASE
              WHEN lc.entidad_evaluada=1 THEN "Empresa"
              WHEN lc.entidad_evaluada=2 THEN "Establecimiento"
              WHEN lc.entidad_evaluada=3 THEN "Usuario"
              WHEN lc.entidad_evaluada=4 THEN "Areas"
              WHEN lc.entidad_evaluada=5 THEN "Equipos"
              ELSE "Error"
            END) as entidad_evaluada'),
                \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lista_chequeo_ejecutadas.evaluado_id)
              ELSE "Error"
            END) as evaluado'),
                'usu.nombre_completo AS evaluador',
                \DB::raw('(SELECT SUM(IF ((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL , pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) AS res_final
            FROM lista_chequeo_ejec_respuestas lcer
            INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
            LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
            INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
            INNER JOIN categoria cat ON cat.id=pre.categoria_id
            WHERE  lcer.lista_chequeo_ejec_id=lista_chequeo_ejecutadas.id
            ORDER BY cat.id) AS resultado_final')
            )
            ->Join('lista_chequeo AS lc', 'lc.id', '=', 'lista_chequeo_ejecutadas.lista_chequeo_id')
            ->Join('usuario AS usu', 'usu.id', '=', 'lista_chequeo_ejecutadas.usuario_id')
            ->Join('establecimiento AS est', 'est.id', '=', 'usu.establecimiento_id')
            ->Join('empresa AS emp', 'emp.id', '=', 'est.empresa_id')
            ->orderBy('lista_chequeo_ejecutadas.id', 'DESC');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $traerInformeEjecutadas = $traerInformeEjecutadas->where('usu.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if (!is_null($esResponsableEmpresa))
                    $traerInformeEjecutadas = $traerInformeEjecutadas->where('emp.id', '=', $esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if (!is_null($esResponsableEstablecimiento))
                    $traerInformeEjecutadas = $traerInformeEjecutadas->where('est.id', '=', $esResponsableEstablecimiento->id);

                if (is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $traerInformeEjecutadas = $traerInformeEjecutadas->where('lista_chequeo_ejecutadas.usuario_id', '=', auth()->user()->id);

                break;

            default:

                break;
        };

        if (COUNT($filtro_array) != 0) {
            $traerInformeEjecutadas = $traerInformeEjecutadas->where(function ($query) use ($filtro_array) {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) {
                    $query->where($oW[0], '=', $oW[2]);
                }

                return $query;
            });
        }
        $rango = $traerInformeEjecutadas->paginate($cantidadRegistros)->lastPage();
        $traerInformeEjecutadas = $traerInformeEjecutadas->skip($desde)->take($hasta)->get();

        #region CODIOG FUNCIONAL PARA RESULTADOS FINALES CON AUDEED GLOBAL VERSIÓN 1
        // foreach ($traerInformeEjecutadas as $keyss => $ejecutada)
        // {
        //     $categorias = \DB::select("SELECT
        //         lcer.categoria_id,
        //         cat.nombre as categoria,
        //         lcer.no_aplica,
        //         IF(COUNT(lcer.id) = lcer.no_aplica,1,0) AS todas_no_aplican,
        //         pre.nombre as pregunta,
        //         cat.ponderado AS cat_ponderado,
        //         pre.ponderado AS pre_ponderado,
        //         res.valor_personalizado AS respuesta,
        //         res.ponderado AS res_ponderado,
        //         (pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100) AS pordentaje_pregunta,
        //         SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100)) AS sum_pordentaje_pregunta,
        //         (SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100)))*cat.ponderado/100 AS porc_cat
        //         FROM lista_chequeo_ejec_respuestas lcer
        //         INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        //         INNER JOIN respuesta res ON res.id=lcer.respuesta_id
        //         INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
        //         INNER JOIN categoria cat ON cat.id=pre.categoria_id
        //         WHERE  lcer.lista_chequeo_ejec_id=:idEjecutada
        //         GROUP BY lcer.categoria_id
        //         ORDER BY cat.id;",['idEjecutada' => $ejecutada->ID_EJECUTADA]);

        //         $suma = 0;
        //         $todas_no_aplican = 0;
        //         foreach ($categorias as $keysss => $item) 
        //         {
        //             $suma += floatval($item->porc_cat);
        //             $todas_no_aplican = $item->todas_no_aplican;
        //         }

        //         if($todas_no_aplican == 0)
        //             $traerInformeEjecutadas[$keyss]->resultado_final = number_format($suma,2);
        //         else
        //             $traerInformeEjecutadas[$keyss]->resultado_final = "";

        // }

        #endregion CODIOG FUNCIONAL PARA RESULTADOS FINALES CON AUDEED GLOBAL VERSIÓN 1

        return array(
            'cantidadTotal' => $rango,
            'informeEjecutadas' => $traerInformeEjecutadas
        );
    }

    public function FuncionParaSaberSiEsResponsableEmpresa($idUsuario)
    {
        $esResponsableEmpresa = $this->empresa->where('usuario_id', '=', $idUsuario)->first();

        return $esResponsableEmpresa;
    }

    public function FuncionParaSaberSiEsResponsableEstablecimiento($idUsuario)
    {
        $esResponsableEstablecimiento = $this->establecimiento->where('usuario_id', '=', $idUsuario)->first();

        return $esResponsableEstablecimiento;
    }

    public function descargaExcel(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');
        $filtros = json_decode($request->get('filtros_busqueda'));
        return \Excel::download(new EvaluacionExports($filtros), 'informe.xlsx');
    }

    //INFORME CUMPLIMIENTO LISTA
    public function indexCumplimientoLista()
    {
        $listasDeChequeo = $this->listaChequeo
            ->select('lista_chequeo.*')
            ->Join('usuario AS u', 'u.id', '=', 'lista_chequeo.usuario_id')
            ->where('u.cuenta_principal_id', '=', auth()->user()->cuenta_principal_id)
            ->orderBy('favorita', "DESC")
            ->get();


        $empresas = \DB::select("SELECT 
        (CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END) AS id,
        (CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END) AS nombre
        FROM lista_chequeo_ejecutadas lce
        INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id=lc.id
        INNER JOIN usuario usu ON usu.id = lce.usuario_id
        INNER JOIN establecimiento esta ON esta.id = usu.establecimiento_id
        INNER JOIN empresa empe ON empe.id = esta.empresa_id
        WHERE lce.estado = 2 AND usu.cuenta_principal_id = :idCuentaPrincipal
        GROUP BY (CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END);", ['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        return view('Admin.informe_cumplimiento_lista', compact('listasDeChequeo', 'empresas'));
    }

    public function consultaPromedioFinal(Request $request)
    {
        $objetoRecibido = $request->get('objetoEnviar');
        $idListaChequeoSearch = $objetoRecibido['idListaChequeo'];
        $idEmpresa = $objetoRecibido['idEmpresa'];


        $desde = '';
        $hasta = '';

        switch ($objetoRecibido['serachRealizada']) {
            case 1: // ESTE MES
                $desde = '';
                $hasta = '';
                break;

            case 2: // HOY
                $con = Carbon::now();
                $desde = $con->format('Y-m-d') . ' 00:00:00';
                $hasta = $con->format('Y-m-d') . ' 23:59:59';
                break;

            case 3: // RANGO DE MESES
                $desde = Carbon::createFromFormat('d/m/Y', $objetoRecibido['desde']);
                $hasta = Carbon::createFromFormat('d/m/Y', $objetoRecibido['hasta']);
                $desde = $desde->format('Y-m-d') . ' 00:00:00';
                $hasta = $hasta->format('Y-m-d') . ' 23:59:59';
                break;

            default:
                break;
        }

        $arrayPrimeraSeccionGrafica = $this->DatosPrimeraSeccionGrafica($idEmpresa, $idListaChequeoSearch, $desde, $hasta);
        $arrayPrimeraSeccionPromedioGeneral = $this->DatosPimeraSeccionPromedioGeneral($arrayPrimeraSeccionGrafica);

        $arraySegundaSeccionGrafica = $this->DatosSegundaSeccionGraficaCategorias($idEmpresa, $idListaChequeoSearch, $desde, $hasta);

        $arrayterceraSeccionTabla = $this->DatosTercerSeccionTabla($idEmpresa, $idListaChequeoSearch, $desde, $hasta);

        $arrayCuartaSeccionTabla = $this->DatosCuartaSeccionTabla($idEmpresa, $idListaChequeoSearch, $desde, $hasta);

        return response()->json(['datos' => array(
            'PrimeraSeccionGrafica' => $arrayPrimeraSeccionGrafica,
            'PrimeraSeccionPromedioGeneral' => $arrayPrimeraSeccionPromedioGeneral,
            'SegundaSeccionGraficaCategoria' => $arraySegundaSeccionGrafica,
            'TerceraSeccionTabla' => $arrayterceraSeccionTabla,
            'CuartaSeccionTabla' => $arrayCuartaSeccionTabla,
        )]);

        // $traerDatosSeccionUnoGraficaBarras = \DB::select(
        // \DB::raw("SELECT
        // lce.id as id_lista_chequeo,
        // empe.id as id_empresa,
        // empe.nombre as empresa,
        // lcer.categoria_id,
        // cat.nombre as categoria,
        // FORMAT((SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado)) / 100)))*cat.ponderado/100, 0) AS porc_cat,
        // (SELECT COUNT(*) FROM lista_chequeo_ejecutadas lces 
        //             INNER JOIN usuario usus ON usus.id=lces.usuario_id
        //             INNER JOIN establecimiento estas ON estas.id=usus.establecimiento_id
        //             INNER JOIN empresa empes on empes.id=estas.empresa_id
        //             WHERE empes.id=empe.id AND lces.estado = 2) AS CANTIDAD
        // FROM lista_chequeo_ejec_respuestas lcer
        // INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        // INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
        // INNER JOIN respuesta res ON res.id=lcer.respuesta_id
        // INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
        // INNER JOIN categoria cat ON cat.id=pre.categoria_id
        // INNER JOIN usuario usu ON usu.id=lce.usuario_id
        // INNER JOIN establecimiento esta ON esta.id=usu.establecimiento_id
        // INNER JOIN empresa empe on empe.id=esta.empresa_id
        // WHERE lce.fecha_realizacion BETWEEN '2020-11-01' AND '2020-11-31'
        // GROUP BY empe.id,lcer.categoria_id
        // ORDER BY empe.id;"));

        //Organizo el array
        // $empresas = [];
        // foreach($result as $item){
        //     $empresas[$item->empresa][] = array(
        //         $item->categoria_id => $item->categoria,
        //         'porce_cat' => $item->porc_cat
        //     );
        // }

        //obtengo el ponderaro
        // $result = $this->ponderado($empresas);
    }


    //INFORME CUMPLIMIENTO LISTAS

    #region PRIMERA SECCION
    public function  DatosPrimeraSeccionGrafica($idEmpresa, $idListaChequeo, $desde = '', $hasta = '')
    {
        if ($desde == '' || $hasta == '') {
            $con = Carbon::now();
            $monthStart = $con->startOfMonth()->format('Y-m-d');
            $monthEnd = $con->endOfMonth()->format('Y-m-d');

            $desde = $monthStart . ' 00:00:00';
            $hasta = $monthEnd . ' 23:59:59';
        } else {
            $desde = $desde . ' 00:00:00';
            $hasta = $hasta . ' 23:59:59';
        }

        $queryComplemento = '';
        $valoresQueryUno = ['idListaChequeo' => $idListaChequeo, "desde" => $desde, "hasta" => $hasta];
        switch ($idEmpresa) {
            case 0: // TODAS LAS EMPRESAS
                break;

            default:
                $queryComplemento = ' AND (CASE
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
                                            END) = :idEmpresa';
                $valoresQueryUno['idEmpresa'] = $idEmpresa;
                break;
        }

        $laravelSQL = \DB::select("SELECT 
        lce.id as id_lista_chequeo,
        empe.id as id_empresa,
        empe.nombre as empresa,
        lcer.categoria_id,
        cat.nombre as categoria,
        (CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END) AS EMPRESA_EVALUADA,
        TRUNCATE(SUM(IF ((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL , pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) /
                    (SELECT COUNT(*)
						FROM lista_chequeo_ejecutadas slce
						INNER JOIN lista_chequeo slc ON slc.id=slce.lista_chequeo_id 
						WHERE 
						slce.estado=2 AND 
						slce.lista_chequeo_id=lc.id AND 
						(CASE
									WHEN slc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=slce.evaluado_id)
									
									
									WHEN slc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
																	INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=slce.evaluado_id)
																	
									WHEN slc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
																	INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
																	INNER JOIN empresa semp ON semp.id = sesta.empresa_id
																	WHERE susu.id=slce.evaluado_id)
																	
									WHEN slc.entidad_evaluada=4 THEN 'provisional'
									ELSE 'Error'
								END)=(CASE
									WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lce.evaluado_id)
									
									
									WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
																	INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
																	
									WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
																	INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
																	INNER JOIN empresa semp ON semp.id = sesta.empresa_id
																	WHERE susu.id=lce.evaluado_id)
																	
									WHEN lc.entidad_evaluada=4 THEN 'provisional'
									ELSE 'Error'
								END)),2) AS TotalPorEmpresa
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
        INNER JOIN lista_chequeo lc ON lc.id = lce.lista_chequeo_id
        LEFT JOIN respuesta res ON res.id = lcer.respuesta_id
        INNER JOIN pregunta pre ON pre.id = lcer.pregunta_id
        INNER JOIN categoria cat ON cat.id = pre.categoria_id
        INNER JOIN usuario usu ON usu.id = lce.usuario_id
        INNER JOIN establecimiento esta ON esta.id = usu.establecimiento_id
        INNER JOIN empresa empe ON empe.id = esta.empresa_id
        WHERE lce.estado = 2 AND lce.lista_chequeo_id = :idListaChequeo AND lce.fecha_realizacion BETWEEN :desde AND :hasta $queryComplemento
        GROUP BY EMPRESA_EVALUADA;", $valoresQueryUno);


        $arrayOrdenado = [];

        // ORGANIZAR PARA EL FRONT AGREGARLO A LA GRAFICA
        foreach ($laravelSQL as $key => $itemCalculoFinal)
            $arrayOrdenado[$itemCalculoFinal->EMPRESA_EVALUADA]['TotalPorEmpresa'] = $itemCalculoFinal->TotalPorEmpresa;


        return $arrayOrdenado;
    }

    public function DatosPimeraSeccionPromedioGeneral($arrayResultados)
    {
        $cantidadRegistros = COUNT($arrayResultados);
        $sumaResultadosPorEmpresa = 0;
        foreach ($arrayResultados as $key => $item) {
            $sumaResultadosPorEmpresa += number_format($item['TotalPorEmpresa'], 2);
        }

        $resultado = number_format(($sumaResultadosPorEmpresa / ($cantidadRegistros == 0 ? 1 : $cantidadRegistros)), 2);

        return $resultado;
    }
    #endregion FIN PRIMERA SECCIÓN

    #region SEGUNDA SECCIÓN
    public function  DatosSegundaSeccionGraficaCategorias($idEmpresa, $idListaChequeo, $desde = '', $hasta = '')
    {
        if ($desde == '' || $hasta == '') {
            $con = Carbon::now();
            $monthStart = $con->startOfMonth()->format('Y-m-d');
            $monthEnd = $con->endOfMonth()->format('Y-m-d');

            $desde = $monthStart . ' 00:00:00';
            $hasta = $monthEnd . ' 23:59:59';
        } else {
            $desde = $desde . ' 00:00:00';
            $hasta = $hasta . ' 23:59:59';
        }

        $querySubConsulta = '';
        $queryPrincipal = '';
        $valoresQueryUno = ['idListaChequeo' => $idListaChequeo, "desde" => $desde, "hasta" => $hasta];
        switch ($idEmpresa) {
            case 0: // TODAS LAS EMPRESAS
                break;

            default:
                $querySubConsulta = ' AND 
                                        (CASE
                                                    WHEN slc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=slce.evaluado_id)
                                                    
                                                    
                                                    WHEN slc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                                                    INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=slce.evaluado_id)
                                                                                    
                                                    WHEN slc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                                                    INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                                                    INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                                                    WHERE susu.id=slce.evaluado_id)
                                                                                    
                                                    WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=slce.evaluado_id)
                                                    WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=slce.evaluado_id)
                                                    ELSE "Error"
                                                END) = :idEmpresaSubConsulta';

                $valoresQueryUno['idEmpresaSubConsulta'] = $idEmpresa;

                $queryPrincipal = ' AND 
                                        (CASE
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
                                        END) = :idEmpresaPrincipal';

                $valoresQueryUno['idEmpresaPrincipal'] = $idEmpresa;
                break;
        }

        $laravelSQL = \DB::select("SELECT 
        lce.id as id_lista_chequeo,
        empe.id as id_empresa,
        empe.nombre as empresa,
        lcer.categoria_id,
        cat.nombre as categoria,
        (CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            ELSE 'Error'
        END) AS EMPRESA_EVALUADA,
         TRUNCATE(SUM(IF ((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL , pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) /
                    (SELECT COUNT(*)
						FROM lista_chequeo_ejecutadas slce
						INNER JOIN lista_chequeo slc ON slc.id=slce.lista_chequeo_id 
						WHERE 
						slce.estado=2 AND 
						slce.lista_chequeo_id=lc.id $querySubConsulta),2) AS promedio_total_por_categoria
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id = lcer.lista_chequeo_ejec_id
        INNER JOIN lista_chequeo lc ON lc.id = lce.lista_chequeo_id
        LEFT JOIN respuesta res ON res.id = lcer.respuesta_id
        INNER JOIN pregunta pre ON pre.id = lcer.pregunta_id
        INNER JOIN categoria cat ON cat.id = pre.categoria_id
        INNER JOIN usuario usu ON usu.id = lce.usuario_id
        INNER JOIN establecimiento esta ON esta.id = usu.establecimiento_id
        INNER JOIN empresa empe ON empe.id = esta.empresa_id
        WHERE lce.lista_chequeo_id = :idListaChequeo AND lce.fecha_realizacion BETWEEN :desde AND :hasta $queryPrincipal
        GROUP BY cat.id;", $valoresQueryUno);

        // $laravelSQL = $this->ejecutadasRespuestas
        // ->select(
        //     'lce.id as id_lista_chequeo',
        //     'lista_chequeo_ejec_respuestas.categoria_id',
        //     'cat.nombre as categoria',
        //     \DB::raw('(TRUNCATE(SUM(IF ((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL,pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) / 
        //     (SELECT COUNT(*) FROM lista_chequeo_ejecutadas slce 
        //      INNER JOIN usuario sus ON  sus.id=slce.usuario_id
        //      INNER JOIN establecimiento ses ON ses.id=sus.establecimiento_id
        //      INNER JOIN empresa sem ON sem.id=ses.empresa_id
        //      WHERE slce.estado=2 AND slce.lista_chequeo_id=lce.lista_chequeo_id ),2))
        //      as promedio_total_por_categoria')
        // )
        // ->Join('lista_chequeo_ejecutadas AS lce','lce.id','=','lista_chequeo_ejec_respuestas.lista_chequeo_ejec_id')
        // ->leftJoin('respuesta AS res','res.id','=','lista_chequeo_ejec_respuestas.respuesta_id')
        // ->Join('pregunta AS pre','pre.id','=','lista_chequeo_ejec_respuestas.pregunta_id')
        // ->Join('categoria AS cat','cat.id','=','pre.categoria_id')
        // ->Join('usuario AS usu','usu.id','=','lce.usuario_id')
        // ->Join('establecimiento AS esta','esta.id','=','usu.establecimiento_id')
        // ->Join('empresa AS empe','empe.id','=','esta.empresa_id')
        // ->whereBetween('lce.fecha_realizacion', [$desde, $hasta])
        // ->where([['lce.lista_chequeo_id','=',$idListaChequeo],['lce.estado','=','2'],['usu.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]])
        // ->groupBy('cat.id');

        // switch ($idEmpresa) {
        //     case 0: // TODAS LAS EMPRESAS
        //         break;

        //     default:
        //         $laravelSQL = $laravelSQL->where('empe.id','=',$idEmpresa);
        //         break;
        // }

        // $laravelSQL = $laravelSQL->get();

        // ORGANIZAR LA RESPUESTA
        // foreach ($laravelSQL as $key => $item) 
        // {
        //     $laravelSQL[$key]['promedio_total_por_categoria'] = number_format((floatval($item->porc_cat) / intval(($item->CANTIDAD == 0 ? 1 : $item->CANTIDAD))),2);
        //     // $laravelSQL[$key]['promedio_total_por_categoria'] = floatval($item->porc_cat);
        // }
        // dd($laravelSQL);
        // $laravelSQL = $laravelSQL->sortByDesc('promedio_total_por_categoria');

        return $laravelSQL;
    }
    #endregion FIN SEGUNDA SECCIÓN

    #region TERCERA SECCIÓN

    public function DatosTercerSeccionTabla($idEmpresa, $idListaChequeoSearch, $desde, $hasta)
    {
        if ($desde == '' || $hasta == '') {
            $con = Carbon::now();
            $monthStart = $con->startOfMonth()->format('Y-m-d');
            $monthEnd = $con->endOfMonth()->format('Y-m-d');

            $desde = $monthStart . ' 00:00:00';
            $hasta = $monthEnd . ' 23:59:59';
        } else {
            $desde = $desde . ' 00:00:00';
            $hasta = $hasta . ' 23:59:59';
        }


        $queryComplemento = '';
        $valoresQuery = ['idListaChequeo' => $idListaChequeoSearch, 'idCuentaPrincipal' => auth()->user()->cuenta_principal_id, "desde" => $desde, "hasta" => $hasta];
        switch ($idEmpresa) {
            case 0: // TODAS LAS EMPRESAS
                break;

            default: // UNA EMPRESA EN ESPECIAL
                $queryComplemento = ' AND (CASE
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
                                    END) = :idEmpresa';
                $valoresQuery['idEmpresa'] = $idEmpresa;
                break;
        }
        $laravelSQL = \DB::select("SELECT 
        lc.entidad_evaluada,
        pr.nombre AS PREGUNTA,
        lcer.pregunta_id,
        lcer.respuesta_id,
        re.valor_personalizado,
        re.ponderado,
        (CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lce.evaluado_id)
            
            
            WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                            INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                            INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                            INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                            WHERE susu.id=lce.evaluado_id)
                                            
            WHEN lc.entidad_evaluada=4 THEN 'provisional'
            WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lce.evaluado_id)
            ELSE 'Error'
        END) AS ENTIDAD,
        COUNT(lcer.pregunta_id) AS EMPRESA_PREGUNTA
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lcer.lista_chequeo_ejec_id = lce.id
        INNER JOIN lista_chequeo lc ON lc.id = lce.lista_chequeo_id
        INNER JOIN respuesta re ON re.id = lcer.respuesta_id
        INNER JOIN usuario usu ON usu.id = lce.usuario_id
        INNER JOIN pregunta pr ON pr.id= lcer.pregunta_id
        WHERE usu.cuenta_principal_id=:idCuentaPrincipal AND re.ponderado = 0 AND lce.fecha_realizacion BETWEEN :desde AND :hasta AND lc.id=:idListaChequeo $queryComplemento
        GROUP BY lcer.pregunta_id,ENTIDAD", $valoresQuery);


        $arrayLimpio = [];
        foreach ($laravelSQL as $key => $item) {
            if ($item->EMPRESA_PREGUNTA > 1) {
                $arrayLimpio[$item->PREGUNTA][$item->ENTIDAD]['Muestra'] = ($item->ENTIDAD . " (" . $item->EMPRESA_PREGUNTA . ")");
                // $arrayLimpio[$item->PREGUNTA][$item->ENTIDAD]['Suma'] += floatval($item->EMPRESA_PREGUNTA);

                // $arrayLimpio[$item->PREGUNTA]["ENTIDAD"]['cantidad'] = $item->EMPRESA_PREGUNTA;
            }
        }

        return $arrayLimpio;
    }

    public function ValidarEmpresasPorPregunta($idPregunta)
    {
        $empresas = \DB::select("SELECT  empe.id,
            (CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lces.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lces.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lces.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END) AS nombre
            FROM lista_chequeo_ejecutadas lces
            INNER JOIN lista_chequeo_ejec_respuestas lcers ON lcers.lista_chequeo_ejec_id = lces.id
            INNER JOIN lista_chequeo lc ON lc.id = lces.lista_chequeo_id
            INNER JOIN usuario usu ON usu.id=lces.usuario_id
            INNER JOIN establecimiento esta ON esta.id=usu.establecimiento_id
            INNER JOIN empresa empe on empe.id=esta.empresa_id
            INNER JOIN pregunta ps ON ps.id = lcers.pregunta_id
            INNER JOIN respuesta rs ON rs.id = lcers.respuesta_id
            INNER JOIN tipo_respuesta_ponderado_pred trpds ON trpds.id = rs.tipo_respuesta_ponderado_pred_id
            WHERE ps.id = :idPregunta AND trpds.ponderado=0 GROUP BY (CASE
                WHEN lc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=lces.evaluado_id)
                
                
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lces.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lces.evaluado_id)
                                                
                WHEN lc.entidad_evaluada=4 THEN 'provisional'
                ELSE 'Error'
            END);", ['idPregunta' => $idPregunta]);

        return $empresas;
    }

    #endregion FIN - TERCERA SECCIÓN

    #region CUARTAS SECCiÖN TABLA

    public function DatosCuartaSeccionTabla($idEmpresa, $idListaChequeo, $desde = '', $hasta = '')
    {
        if ($desde == '' || $hasta == '') {
            $con = Carbon::now();
            $monthStart = $con->startOfMonth()->format('Y-m-d');
            $monthEnd = $con->endOfMonth()->format('Y-m-d');

            $desde = $monthStart . ' 00:00:00';
            $hasta = $monthEnd . ' 23:59:59';
        } else {
            $desde = $desde . ' 00:00:00';
            $hasta = $hasta . ' 23:59:59';
        }

        $querySubConsulta = '';
        $queryPrincipal = '';
        $valoresQueryUno = ['idListaChequeo' => $idListaChequeo, "desde" => $desde, 'idCuentaPrincipal' => auth()->user()->cuenta_principal_id, "hasta" => $hasta];
        switch ($idEmpresa) {
            case 0: // TODAS LAS EMPRESAS
                break;

            default:
                $querySubConsulta = ' AND 
            (CASE
                        WHEN slc.entidad_evaluada=1 THEN (SELECT semp.id FROM empresa semp WHERE semp.id=slce.evaluado_id)
                        
                        
                        WHEN slc.entidad_evaluada=2 THEN (SELECT semp.id FROM establecimiento sest 
                                                        INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=slce.evaluado_id)
                                                        
                        WHEN slc.entidad_evaluada=3 THEN (SELECT semp.id FROM usuario susu
                                                        INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                        INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                        WHERE susu.id=slce.evaluado_id)
                                                        
                        WHEN lc.entidad_evaluada=4 THEN (SELECT ars.id FROM areas ars WHERE ars.id=slce.evaluado_id)
                        WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.id FROM equipos eqs WHERE eqs.id=slce.evaluado_id)
                        ELSE "Error"
                    END) = :idEmpresaSubConsulta';

                $valoresQueryUno['idEmpresaSubConsulta'] = $idEmpresa;

                $queryPrincipal = ' AND 
                                (CASE
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
                                END) = :idEmpresaPrincipal';

                $valoresQueryUno['idEmpresaPrincipal'] = $idEmpresa;
                break;
        }

        //PUNTAJE SACADO DE LAS RESPUESTAS DE LISTA DE CHEQUEO POR ETIQUETA
        $laravelSQL = \DB::select("SELECT 
        ce.nombre AS ETIQUETA,
        TRUNCATE(SUM(IF ((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL , pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) / 
        (SELECT COUNT(*)
						FROM lista_chequeo_ejecutadas slce
						INNER JOIN lista_chequeo slc ON slc.id=slce.lista_chequeo_id 
						WHERE 
						slce.estado=2 AND 
						slce.lista_chequeo_id=lc.id $querySubConsulta),2)
        as PONDERADO_PREGUNTA
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        INNER JOIN lista_chequeo lc ON lc.id = lce.lista_chequeo_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
        INNER JOIN categoria cat ON cat.id=pre.categoria_id
        INNER JOIN categoria_etiquetas ce ON cat.id_etiqueta=ce.id
        INNER JOIN usuario us ON us.id = lce.usuario_id
        INNER JOIN establecimiento es ON es.id = us.establecimiento_id
        INNER JOIN empresa em ON em.id = es.empresa_id
        WHERE us.cuenta_principal_id = :idCuentaPrincipal AND lce.estado = 2 AND lce.lista_chequeo_id=:idListaChequeo $queryPrincipal
        AND lce.fecha_realizacion BETWEEN :desde AND :hasta
        GROUP BY cat.id_etiqueta;", $valoresQueryUno);


        //PUNTAJE ORIGINAL QUE DEBERÍA DE SACAR POR ETIQUETA
        $laravelSQLDos = \DB::select("SELECT 
        ce.nombre AS ETIQUETA,
        SUM(cat.ponderado) TOTAL_ETIQUETA
        FROM categoria cat
        INNER JOIN categoria_etiquetas ce ON cat.id_etiqueta=ce.id
        WHERE cat.lista_chequeo_id=:idListaChequeo
        GROUP BY cat.id_etiqueta;", ['idListaChequeo' => $idListaChequeo]);

        $arrayOrdenado = [];

        // FINALIZAR CALCULO PARA MOSTRAR ETIQUETAS Y SU RESPECTIVO RESULTADO PARA PONDERADO PREGUNTAS
        foreach ($laravelSQL as $key => $itemCalculoFinal) {
            // $arrayOrdenado[$key]['TotalPorEtiqueta'] = number_format((floatval($itemCalculoFinal['suma_etiquetas']) / intval(($itemCalculoFinal['cantidadEjecutadas'] == 0 ? COUNT($laravelSQL) : $itemCalculoFinal['cantidadEjecutadas']))),2);
            $arrayOrdenado[$itemCalculoFinal->ETIQUETA]['TotalPorEtiqueta'] = $itemCalculoFinal->PONDERADO_PREGUNTA;
        }

        $arrayOrdenadoCategorias = [];

        // FINALIZAR CALCULO PARA MOSTRAR RESULTADO PARA PONDERADO CATEGORIAS
        foreach ($laravelSQLDos as $key => $itemCalculoFinalCategorias) {
            // $arrayOrdenadoCategorias[$key]['TotalPorCategoriaEtiqueta'] = number_format((floatval($itemCalculoFinalCategorias['suma_etiquetas']) / intval(($itemCalculoFinalCategorias['cantidadEjecutadas'] == 0 ? COUNT($laravelSQLCategorias) : $itemCalculoFinalCategorias['cantidadEjecutadas']))),2);
            $arrayOrdenadoCategorias[$itemCalculoFinalCategorias->ETIQUETA]['TotalPorCategoriaEtiqueta'] = $itemCalculoFinalCategorias->TOTAL_ETIQUETA;
        }

        foreach ($arrayOrdenado as $key => $itemPreguntas) {

            foreach ($arrayOrdenadoCategorias as $keys => $itemCategoria) {
                if ($key == $keys)
                    $arrayOrdenado[$key]['categoriaResultado'] = $itemCategoria['TotalPorCategoriaEtiqueta'];
            }
        }

        return $arrayOrdenado;
    }

    #endregion FIN - CUARTA SECCIÓN

    private function ponderado($array)
    {
        $sumCategorias = 0;
        $cantidad = 0;
        $empresas = [];
        foreach ($array as $key => $item) {

            foreach ($item as $cate) {
                $sumCategorias +=  $cate['porce_cat'];
            }
            $empresas[$key]['ponderado'] = number_format($sumCategorias / count($item), 1);
            $sumCategorias = 0;
        }

        return $empresas;
    }

    // FIN - INFORME CUMPLIMIENTO LISTAS


    // INFORMES DOTACIÓN Y PRACTICAS HIGIENICAS
    public function IndexDotacionPracticasHigienicas(Request $request)
    {
        return view('Admin.informes_dotacion_higienicas');
    }

    public function GetDataInit(Request $request)
    {
        $token = $request->get('_token');
        $pagination = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        $idCheckList = 189;

        $clearArray = $this->FunctionGetDataPrincipal($arrayFiltros, $idCheckList);

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'data' => $clearArray
        ]);
    }

    public function FunctionGetDataPrincipal($arrayFiltros, $idCheckList)
    {
        $where = '';
        if ($arrayFiltros->filtro_realizacion != '')
            $where = " AND lce.fecha_realizacion BETWEEN '" . $arrayFiltros->filtro_realizacion . " 00:00:00' AND '" . $arrayFiltros->filtro_realizacion . " 23:59:59'";
        else {
            $con = Carbon::now();
            $desde = $con->format('Y-m-d');
            $hasta = $con->format('Y-m-d');
            $where = " AND lce.fecha_realizacion BETWEEN '" . $desde . " 00:00:00' AND '" . $hasta . " 23:59:59'";
        }

        $dataEmployees = \DB::select("SELECT
                lce.id AS LISTA_EJECT,
                pr.nombre AS PREGUNTA,
                DATE_FORMAT(lce.fecha_realizacion, '%d %M de %Y') AS FECHA_REALIZACION,
                us_ev.nombre_completo AS EVALUADO,
                us_ev.id AS ID_EVALUADO,
                IF(re.tipo_respuesta_ponderado_pred_id = 4, lcer.respuesta_abierta, re.valor_personalizado) AS RESPUESTA,
                lcer.id AS RESPUESTA_ID,
                IF(lceo.comentario IS NULL, '', lceo.comentario) AS OBSERVACION,
                us.nombre_completo AS DILIGENCIADO,
                IF(lce.observacion_general IS NULL, '', lce.observacion_general) AS OBS_GENERAL
                FROM lista_chequeo_ejecutadas lce
                INNER JOIN lista_chequeo_ejec_respuestas lcer ON lce.id = lcer.lista_chequeo_ejec_id
                INNER JOIN pregunta pr ON lcer.pregunta_id = pr.id
                INNER JOIN respuesta re ON lcer.respuesta_id = re.id
                LEFT JOIN lista_chequeo_ejec_opciones lceo ON lcer.id = lceo.lista_chequeo_ejec_respuestas_id
                INNER JOIN usuario us ON lce.usuario_id = us.id
                INNER JOIN usuario us_ev ON lce.evaluado_id = us_ev.id
                WHERE lce.lista_chequeo_id = $idCheckList
                $where
                ORDER BY pr.id ASC;"
        );

        $orderArray = [];
        foreach ($dataEmployees as $key => $employee) {
            $orderArray[$employee->LISTA_EJECT]['PREGUNTA'] = $employee->PREGUNTA;
            $orderArray[$employee->LISTA_EJECT]['FECHA_REALIZACION'] = $employee->FECHA_REALIZACION;
            $orderArray[$employee->LISTA_EJECT]['EVALUADO'] = $employee->EVALUADO;
            $orderArray[$employee->LISTA_EJECT]['RESPUESTA'][] = $employee->RESPUESTA;
            $orderArray[$employee->LISTA_EJECT]['RESPUESTA_ID'][] = $employee->RESPUESTA_ID;
            $orderArray[$employee->LISTA_EJECT]['OBSERVACION'][] = $employee->OBSERVACION;
            $orderArray[$employee->LISTA_EJECT]['DILIGENCIADO'] = $employee->DILIGENCIADO;
            $orderArray[$employee->LISTA_EJECT]['OBSERVACION_GENERAL'] = $employee->OBS_GENERAL;
        }

        $clearArray = [];
        foreach ($orderArray as $keys => $order) {
            array_push($clearArray, $order);
        }

        return $clearArray;
    }

    public function GetDataObsRta(Request $request)
    {
        $idRta = $request->get('idRta');

        $rta = \DB::table('lista_chequeo_ejec_opciones')
            ->select('comentario')
            ->where('lista_chequeo_ejec_respuestas_id', '=', $idRta)
            ->first();

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'data' => $rta->comentario
        ]);
    }

    public function DownloadExcel(Request $request)
    {
        $pagination = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        $idCheckList = 189;

        $clearArray = $this->FunctionGetDataPrincipal($arrayFiltros, $idCheckList);

        return \Excel::download(new DotacionPracticasExports($clearArray), 'dotacion_practicas.xlsx');
    }


    //INFORME VERIFICACIÓN BALANZAS
    public function IndexVerificacionBalanzas()
    {
        return view('Admin.informe_verificacion_balanzas');
    }

    public function GetDataInitVerificacion(Request $request)
    {
        $token = $request->get('_token');
        $pagination = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        $idCheckList = 190;

        $clearArray = $this->FunctionGetDataVerificacionBalanzas($arrayFiltros, $idCheckList);

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'data' => $clearArray
        ]);
    }

    public function FunctionGetDataVerificacionBalanzas($arrayFiltros, $idCheckList)
    {
        $where = '';
        if ($arrayFiltros->filtro_realizacion != '')
            $where = " AND lce.fecha_realizacion BETWEEN '" . $arrayFiltros->filtro_realizacion . " 00:00:00' AND '" . $arrayFiltros->filtro_realizacion . " 23:59:59'";
        else {
            $con = Carbon::now();
            $desde = $con->format('Y-m-d');
            $hasta = $con->format('Y-m-d');
            $where = " AND lce.fecha_realizacion BETWEEN '" . $desde . " 00:00:00' AND '" . $hasta . " 23:59:59'";
        }

        $data = \DB::select("SELECT
                lce.id AS LISTA_EJECT,
                pr.nombre AS PREGUNTA,
                DATE_FORMAT(lce.fecha_realizacion, '%d %M de %Y') AS FECHA_REALIZACION,
                eq.nombre AS EVALUADO,
                eq.id AS ID_EVALUADO,
                eq.descripcion AS DESCRIPCION_EQUIPO,
                IF(re.tipo_respuesta_ponderado_pred_id = 4, lcer.respuesta_abierta, re.valor_personalizado) AS RESPUESTA,
                lcer.id AS RESPUESTA_ID,
                IF(lceo.comentario IS NULL, '', lceo.comentario) AS OBSERVACION,
                us.nombre_completo AS DILIGENCIADO,
                IF(lce.observacion_general IS NULL, '', lce.observacion_general) AS OBS_GENERAL
                FROM lista_chequeo_ejecutadas lce
                INNER JOIN lista_chequeo_ejec_respuestas lcer ON lce.id = lcer.lista_chequeo_ejec_id
                INNER JOIN pregunta pr ON lcer.pregunta_id = pr.id
                INNER JOIN respuesta re ON lcer.respuesta_id = re.id
                LEFT JOIN lista_chequeo_ejec_opciones lceo ON lcer.id = lceo.lista_chequeo_ejec_respuestas_id
                INNER JOIN usuario us ON lce.usuario_id = us.id
                INNER JOIN equipos eq ON lce.evaluado_id = eq.id
                WHERE lce.lista_chequeo_id = $idCheckList
                $where
                ORDER BY pr.id ASC;"
        );

        $orderArray = [];
        foreach ($data as $key => $balanza) {
            $orderArray[$balanza->LISTA_EJECT]['PREGUNTA'] = $balanza->PREGUNTA;
            $orderArray[$balanza->LISTA_EJECT]['DESCRIPCION_EQUIPO'] = $balanza->DESCRIPCION_EQUIPO;
            $orderArray[$balanza->LISTA_EJECT]['FECHA_REALIZACION'] = $balanza->FECHA_REALIZACION;
            $orderArray[$balanza->LISTA_EJECT]['EVALUADO'] = $balanza->EVALUADO;
            $orderArray[$balanza->LISTA_EJECT]['RESPUESTA'][] = $balanza->RESPUESTA;
            $orderArray[$balanza->LISTA_EJECT]['RESPUESTA_ID'][] = $balanza->RESPUESTA_ID;
            $orderArray[$balanza->LISTA_EJECT]['OBSERVACION'][] = $balanza->OBSERVACION;
            $orderArray[$balanza->LISTA_EJECT]['DILIGENCIADO'] = $balanza->DILIGENCIADO;
            $orderArray[$balanza->LISTA_EJECT]['OBSERVACION_GENERAL'] = $balanza->OBS_GENERAL;
        }

        $clearArray = [];
        foreach ($orderArray as $keys => $order) {
            array_push($clearArray, $order);
        }

        return $clearArray;
    }

    public function DownloadExcelVerificacion(Request $request)
    {
        $pagination = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        $idCheckList = 190;

        $clearArray = $this->FunctionGetDataVerificacionBalanzas($arrayFiltros, $idCheckList);

        return \Excel::download(new VerificacionBalanzasExports($clearArray), 'verificacion_balanzas.xlsx');
    }

    public function GetDataObsRtaVerificacion(Request $request)
    {
        $idRta = $request->get('idRta');

        $rta = \DB::table('lista_chequeo_ejec_opciones')
            ->select('comentario')
            ->where('lista_chequeo_ejec_respuestas_id', '=', $idRta)
            ->first();

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'data' => $rta->comentario
        ]);
    }

    //INFORME EQUIPOS FRIOS
    public function IndexEquiposFrio()
    {
        return view('Admin.informe_temperatura_equipos');
    }

    public function GetDataInitTemperatura(Request $request)
    {
        $token = $request->get('_token');
        $pagination = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        $idCheckList = 188;

        $clearArray = $this->FunctionGetDataTemperaturaFrios($arrayFiltros, $idCheckList);

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'data' => $clearArray['data'],
            'aditional' => $clearArray
        ]);
    }

    public function FunctionGetDataTemperaturaFrios($arrayFiltros, $idCheckList)
    {
        $where = '';
        if ($arrayFiltros->filtro_realizacion != '') {
            $dateTime = $this->FuncionInicioYFinDeSemana($arrayFiltros->filtro_realizacion);
            $where = " AND lce.fecha_realizacion BETWEEN '" . $dateTime['fechaInicio'] . " 00:00:00' AND '" . $dateTime['fechaFin'] . " 23:59:59'";
        } else {
            $con = Carbon::now();
            $now = $con->format('Y-m-d');

            $dateTime = $this->FuncionInicioYFinDeSemana($now);
            $where = " AND lce.fecha_realizacion BETWEEN '" . $dateTime['fechaInicio'] . " 00:00:00' AND '" . $dateTime['fechaFin'] . " 23:59:59'";
        }

        $data = \DB::select("SELECT
            lce.id AS LISTA_EJECT,
            ca.id AS ID_CATEGORIA,
            ca.nombre AS CATEGORIA,
            pr.id AS ID_PREGUNTA,
            pr.nombre AS PREGUNTA,
            DATE_FORMAT(lce.fecha_realizacion, '%d %M de %Y') AS FECHA_REALIZACION,
            DAYNAME(lce.fecha_realizacion) AS DIA,
            IF(lcer.respuesta_abierta IS NULL,'N/A',lcer.respuesta_abierta) AS RTA, 
            IF(re.tipo_respuesta_ponderado_pred_id = 5, lcer.respuesta_abierta, re.valor_personalizado) AS RESPUESTA,
            lcer.id AS RESPUESTA_ID,
            IF(lceo.comentario IS NULL, '', lceo.comentario) AS OBSERVACION,
            us.nombre_completo AS DILIGENCIADO,
            IF(lce.observacion_general IS NULL, '', lce.observacion_general) AS OBS_GENERAL
            FROM lista_chequeo_ejecutadas lce
            INNER JOIN lista_chequeo_ejec_respuestas lcer ON lce.id = lcer.lista_chequeo_ejec_id
            INNER JOIN pregunta pr ON lcer.pregunta_id = pr.id
            INNER JOIN categoria ca ON pr.categoria_id = ca.id
            LEFT JOIN respuesta re ON lcer.respuesta_id = re.id
            LEFT JOIN lista_chequeo_ejec_opciones lceo ON lcer.id = lceo.lista_chequeo_ejec_respuestas_id
            INNER JOIN usuario us ON lce.usuario_id = us.id
            WHERE lce.lista_chequeo_id = $idCheckList
            $where
            ORDER BY pr.id ASC;"
        );


        $semana = Carbon::parse($dateTime['fechaInicio'])->format('d M Y') . ' al ' . Carbon::parse($dateTime['fechaFin'])->format('d M Y');
        $diligenciado = "";
        $orderArray = [];
        foreach ($data as $key => $employee) {
            $diligenciado = $employee->DILIGENCIADO;
            $orderArray[$employee->CATEGORIA][($employee->ID_PREGUNTA . '-' . $employee->PREGUNTA)][$employee->DIA][] = array('respuesta' => $employee->RTA, 'id_respuesta' => $employee->RESPUESTA_ID, 'obs' => $employee->OBSERVACION);
        }

        return [
            'data' => $orderArray,
            'SEMANA_DEL' => $semana,
            'DILIGENCIADO' => $diligenciado
        ];
    }

    public function FuncionInicioYFinDeSemana($fecha)
    {
        $diaInicio = "Monday";
        $diaFin = "Sunday";

        $strFecha = strtotime($fecha);

        $fechaInicio = date('Y-m-d', strtotime('last ' . $diaInicio, $strFecha));
        $fechaFin = date('Y-m-d', strtotime('next ' . $diaFin, $strFecha));

        if (date("l", $strFecha) == $diaInicio)
            $fechaInicio = date("Y-m-d", $strFecha);

        if (date("l", $strFecha) == $diaFin)
            $fechaFin = date("Y-m-d", $strFecha);

        return [
            "fechaInicio" => $fechaInicio,
            "fechaFin" => $fechaFin
        ];
    }

    public function GetDataObsRtaTemperatura(Request $request)
    {
        $idRta = $request->get('idRta');

        $rta = \DB::table('lista_chequeo_ejec_opciones')
            ->select('comentario')
            ->where('lista_chequeo_ejec_respuestas_id', '=', $idRta)
            ->first();

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'data' => ($rta == null ? "" : $rta->comentario)
        ]);
    }

    public function DownloadExcelTemperatura(Request $request)
    {
        $pagination = $request->get('paginacion');
        $arrayFiltros = json_decode($request->get('arrayFiltros'));
        $idCheckList = 188;

        $clearArray = $this->FunctionGetDataTemperaturaFrios($arrayFiltros, $idCheckList);

        return \Excel::download(new TemperaturaFriosExports($clearArray), 'temperatura_frios.xlsx');
    }


    public function IndexPlanillaTitulacion()
    {
        $datosPlanilla = $this->SimularDatosPlanillaTitulacion();

        $listaChequeo = $this->FuncionParaTraerListasChequeoInformes();
        $evaluados = $this->FuncionParaTraerEvaluadoInformes();
        $evaluadores = $this->FuncionParaTraerEvaluadoresInformes();
        $estados = $this->FuncionParaTraerEstadosInformes();
        $entidadEvaluada = $this->FuncionParaTraerEntidadEvaluadaInformes();

        return view('Admin.informe_planilla_titulacion', compact('datosPlanilla', 'listaChequeo', 'evaluados', 'evaluadores', 'estados', 'entidadEvaluada'));
    }

    

    

    public function GetDataInitPlanillaTitulacion(Request $request)
    {
        $pagination = $request->get('paginacion', 1);
        $arrayFiltros = json_decode($request->get('arrayFiltros'));

        $datosPlanilla = $this->SimularDatosPlanillaTitulacion($pagination, $arrayFiltros);

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'totalPaginas' => $datosPlanilla['totalPaginas'],
            'totalRegistros' => $datosPlanilla['totalRegistros'],
            'data' => $datosPlanilla // Agregamos los datos reales para la tabla
        ]);
    }


    private function SimularDatosPlanillaTitulacion($paginacion = null, $filtros = null)
    {
        $id_auditoria = 153;
        if (isset($filtros) && !empty($filtros->filtro_lista_chequeo)) {
            $id_auditoria = $filtros->filtro_lista_chequeo;
        }

        $data_auditoria = \DB::table('lista_chequeo')
            ->where('id', '=', $id_auditoria)
            ->first();

        // Configuración de paginación
        $registrosPorPagina = 10;

        $whereFiltros = "";

        if (isset($filtros)) {
            if (!empty($filtros->filtro_inicio_realizacion)) {
                $whereFiltros .= " AND DATE(lce.finished_at) >= '{$filtros->filtro_inicio_realizacion}' ";
            }

            if (!empty($filtros->filtro_fin_realizacion)) {
                $whereFiltros .= " AND DATE(lce.finished_at) <= '{$filtros->filtro_fin_realizacion}' ";
            }

            if (!empty($filtros->filtro_evaluado)) {
                $whereFiltros .= " AND lce.evaluado_id = '{$filtros->filtro_evaluado}' ";
            }

            if (!empty($filtros->filtro_evaluador)) {
                $whereFiltros .= " AND lce.usuario_id = '{$filtros->filtro_evaluador}' ";
            }
        }

        // Primero, obtener el total de registros únicos (listas ejecutadas)
        $totalRegistrosQuery = \DB::select("
            SELECT COUNT(DISTINCT lce.id) as total
            FROM lista_chequeo_ejecutadas lce 
            INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
            WHERE lc.id = {$id_auditoria} {$whereFiltros}
        ");

        $totalRegistros = $totalRegistrosQuery[0]->total ?? 0;
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        // Obtener los IDs de las listas ejecutadas para esta página/totalidad
        if ($paginacion === null) {
            $listaEjectIdsQuery = \DB::select("
                SELECT DISTINCT lce.id
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
                WHERE lc.id = {$id_auditoria} {$whereFiltros}
                ORDER BY lce.id ASC
            ");
        } else {
            $paginaActual = $paginacion;
            $offset = ($paginaActual - 1) * $registrosPorPagina;

            $listaEjectIdsQuery = \DB::select("
                SELECT DISTINCT lce.id
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
                WHERE lc.id = {$id_auditoria} {$whereFiltros}
                ORDER BY lce.id ASC
                LIMIT {$registrosPorPagina} OFFSET {$offset}
            ");
        }

        $listaEjectIds = array_map(function ($item) {
            return $item->id;
        }, $listaEjectIdsQuery);

        // Si no hay IDs, retornar estructura vacía
        if (empty($listaEjectIds)) {
            return [
                'lista_chequeo' => [
                    'id' => $data_auditoria->id,
                    'nombre' => $data_auditoria->nombre,
                    'color' => '#4472C4'
                ],
                'categorias' => [],
                'registros' => [],
                'totalPaginas' => $totalPaginas > 0 ? $totalPaginas : 1,
                'totalRegistros' => $totalRegistros
            ];
        }

        // Convertir array de IDs a string para la consulta
        $idsString = implode(',', $listaEjectIds);

        // Ahora obtener todos los datos de esas listas ejecutadas
        $datosQuery = \DB::select("
            SELECT
                DATE_FORMAT(lce.fecha_realizacion,'%d/%m/%Y') AS FECHA,
                DATE_FORMAT(lce.fecha_realizacion,'%H:%i') AS HORA,
                p.id AS PREGUNTA_ID,
                p.nombre AS PREGUNTA,
                u.nombre_completo AS FIRMA_OPERARIO,
                IF(lce.revisado IS NULL, 'Sin revisión', us.nombre_completo) AS FIRMA_LIDER,
                lce.id AS LISTA_EJECT_ID,
                cat.id AS CATEGORIA_ID,
                cat.nombre AS CATEGORIA_NOMBRE,
                cet.id AS ETIQUETA_ID,
                cet.nombre AS ETIQUETA_NOMBRE,
                lcer.respuesta_abierta AS RESPUESTA
            FROM lista_chequeo_ejecutadas lce 
            INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
            INNER JOIN lista_chequeo_ejec_respuestas lcer ON lce.id = lcer.lista_chequeo_ejec_id
            INNER JOIN pregunta p ON lcer.pregunta_id = p.id
            INNER JOIN categoria cat ON p.categoria_id = cat.id
            LEFT JOIN categoria_etiquetas cet ON cat.id_etiqueta = cet.id
            INNER JOIN usuario u ON lce.usuario_id = u.id
            LEFT JOIN usuario us ON lce.revisado = us.id
            WHERE lc.id = {$id_auditoria} AND lce.id IN ({$idsString})
            ORDER BY lce.id ASC, p.id ASC
        ");

        $registrosAgrupados = [];
        $preguntasUnicas = [];

        foreach ($datosQuery as $row) {
            $listaEjectId = $row->LISTA_EJECT_ID;

            if (!isset($registrosAgrupados[$listaEjectId])) {
                $registrosAgrupados[$listaEjectId] = [
                    'fecha' => $row->FECHA,
                    'hora' => $row->HORA,
                    'firma_operario' => $row->FIRMA_OPERARIO,
                    'firma_lider' => $row->FIRMA_LIDER,
                    'respuestas' => []
                ];
            }

            $registrosAgrupados[$listaEjectId]['respuestas'][$row->PREGUNTA_ID] = $row->RESPUESTA ?? '';

            if (!isset($preguntasUnicas[$row->PREGUNTA_ID])) {
                $preguntasUnicas[$row->PREGUNTA_ID] = [
                    'id' => $row->PREGUNTA_ID,
                    'nombre' => $row->PREGUNTA,
                    'categoria_id' => $row->CATEGORIA_ID,
                    'categoria_nombre' => $row->CATEGORIA_NOMBRE,
                    'etiqueta_id' => $row->ETIQUETA_ID,
                    'etiqueta_nombre' => $row->ETIQUETA_NOMBRE
                ];
            }
        }

        $categorias = [];
        $categoriasMap = [];

        foreach ($preguntasUnicas as $pregunta) {
            $catId = $pregunta['categoria_id'];
            $etiqId = $pregunta['etiqueta_id'];

            if (!isset($categoriasMap[$catId])) {
                $categoriasMap[$catId] = [
                    'id' => $catId,
                    'nombre' => $pregunta['categoria_nombre'],
                    'color' => '#FFC000',
                    'colspan' => 0,
                    'etiquetas' => []
                ];
            }

            if (!isset($categoriasMap[$catId]['etiquetas'][$etiqId])) {
                $categoriasMap[$catId]['etiquetas'][$etiqId] = [
                    'id' => $etiqId,
                    'nombre' => $pregunta['etiqueta_nombre'],
                    'color' => '#888681',
                    'colspan' => 0,
                    'info_adicional' => '',
                    'preguntas' => []
                ];
            }

            $categoriasMap[$catId]['etiquetas'][$etiqId]['preguntas'][] = [
                'id' => $pregunta['id'],
                'nombre' => $pregunta['nombre'],
                'color' => '#fc9425'
            ];

            $categoriasMap[$catId]['etiquetas'][$etiqId]['colspan']++;
            $categoriasMap[$catId]['colspan']++;
        }

        foreach ($categoriasMap as $cat) {
            $cat['etiquetas'] = array_values($cat['etiquetas']);
            $categorias[] = $cat;
        }

        $registros = array_values($registrosAgrupados);

        return [
            'lista_chequeo' => [
                'id' => $data_auditoria->id,
                'nombre' => $data_auditoria->nombre,
                'color' => '#4472C4'
            ],
            'categorias' => $categorias,
            'registros' => $registros,
            'totalPaginas' => $totalPaginas > 0 ? $totalPaginas : 1,
            'totalRegistros' => $totalRegistros
        ];
    }

    public function descargarExcelPlanillaTitulacion(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');

        $filtros = json_decode($request->get('filtros_busqueda'));
        $data = $this->SimularDatosPlanillaTitulacion(null, $filtros);

        return \Excel::download(new PlanillaTitulacionExports($data), 'planilla_titulacion.xlsx');
    }

    public function IndexPlanillaTitulacionGonvarri()
    {
        $datosPlanilla = $this->SimularDatosPlanillaTitulacionGonvarri();

        $listaChequeo = $this->FuncionParaTraerListasChequeoInformes();
       
        $evaluados = $this->FuncionParaTraerEvaluadoInformes();
        
        $evaluadores = $this->FuncionParaTraerEvaluadoresInformes();
        $estados = $this->FuncionParaTraerEstadosInformes();
        $entidadEvaluada = $this->FuncionParaTraerEntidadEvaluadaInformes();
       

        return view('Admin.informe_planilla_titulacion_gonvarri', compact('datosPlanilla', 'listaChequeo', 'evaluados', 'evaluadores', 'estados', 'entidadEvaluada'));
    }


    public function GetDataInitPlanillaTitulacionGonvarri(Request $request)
    {
        $pagination = $request->get('paginacion', 1);
        $arrayFiltros = json_decode($request->get('arrayFiltros'));

        $datosPlanilla = $this->SimularDatosPlanillaTitulacionGonvarri($pagination, $arrayFiltros);

        return response()->json([
            'success' => 1,
            'responseCode' => 202,
            'message' => "Data found.",
            'totalPaginas' => $datosPlanilla['totalPaginas'],
            'totalRegistros' => $datosPlanilla['totalRegistros'],
            'data' => $datosPlanilla // Agregamos los datos reales para la tabla
        ]);
    }


    private function SimularDatosPlanillaTitulacionGonvarri($paginacion = null, $filtros = null)
    {
        $id_auditoria = 158;
       
        if (isset($filtros) && !empty($filtros->filtro_lista_chequeo)) {
            $id_auditoria = $filtros->filtro_lista_chequeo;
        }

        $data_auditoria = \DB::table('lista_chequeo')
            ->where('id', '=', $id_auditoria)
            ->first();
 
        // Configuración de paginación
        $registrosPorPagina = 10;

        $whereFiltros = "";

        if (isset($filtros)) {
            if (!empty($filtros->filtro_inicio_realizacion)) {
                $whereFiltros .= " AND DATE(lce.finished_at) >= '{$filtros->filtro_inicio_realizacion}' ";
            }

            if (!empty($filtros->filtro_fin_realizacion)) {
                $whereFiltros .= " AND DATE(lce.finished_at) <= '{$filtros->filtro_fin_realizacion}' ";
            }

            if (!empty($filtros->filtro_evaluado)) {
                $whereFiltros .= " AND lce.evaluado_id = '{$filtros->filtro_evaluado}' ";
            }

            if (!empty($filtros->filtro_evaluador)) {
                $whereFiltros .= " AND lce.usuario_id = '{$filtros->filtro_evaluador}' ";
            }
        }

        // Primero, obtener el total de registros únicos (listas ejecutadas)
        $totalRegistrosQuery = \DB::select("
            SELECT COUNT(DISTINCT lce.id) as total
            FROM lista_chequeo_ejecutadas lce 
            INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
            WHERE lc.id = {$id_auditoria} {$whereFiltros}
        ");

        $totalRegistros = $totalRegistrosQuery[0]->total ?? 0;
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

        // Obtener los IDs de las listas ejecutadas para esta página/totalidad
        if ($paginacion === null) {
            $listaEjectIdsQuery = \DB::select("
                SELECT DISTINCT lce.id
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
                WHERE lc.id = {$id_auditoria} {$whereFiltros}
                ORDER BY lce.id ASC
            ");
        } else {
            $paginaActual = $paginacion;
            $offset = ($paginaActual - 1) * $registrosPorPagina;

            $listaEjectIdsQuery = \DB::select("
                SELECT DISTINCT lce.id
                FROM lista_chequeo_ejecutadas lce 
                INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
                WHERE lc.id = {$id_auditoria} {$whereFiltros}
                ORDER BY lce.id ASC
                LIMIT {$registrosPorPagina} OFFSET {$offset}
            ");
        }

        $listaEjectIds = array_map(function ($item) {
            return $item->id;
        }, $listaEjectIdsQuery);

        // Si no hay IDs, retornar estructura vacía
        if (empty($listaEjectIds)) {
            return [
                'lista_chequeo' => [
                    'id' => $data_auditoria->id,
                    'nombre' => $data_auditoria->nombre,
                    'color' => '#4472C4'
                ],
                'categorias' => [],
                'registros' => [],
                'totalPaginas' => $totalPaginas > 0 ? $totalPaginas : 1,
                'totalRegistros' => $totalRegistros
            ];
        }

        // Convertir array de IDs a string para la consulta
        $idsString = implode(',', $listaEjectIds);

        // Ahora obtener todos los datos de esas listas ejecutadas
        $datosQuery = \DB::select("
            SELECT
                DATE_FORMAT(lce.fecha_realizacion,'%d/%m/%Y') AS FECHA,
                DATE_FORMAT(lce.fecha_realizacion,'%H:%i') AS HORA,
                p.id AS PREGUNTA_ID,
                p.nombre AS PREGUNTA,
                u.nombre_completo AS FIRMA_OPERARIO,
                IF(lce.revisado IS NULL, 'Sin revisión', us.nombre_completo) AS FIRMA_LIDER,
                lce.id AS LISTA_EJECT_ID,
                cat.id AS CATEGORIA_ID,
                cat.nombre AS CATEGORIA_NOMBRE,
                cet.id AS ETIQUETA_ID,
                cet.nombre AS ETIQUETA_NOMBRE,
                lcer.respuesta_abierta AS RESPUESTA
            FROM lista_chequeo_ejecutadas lce 
            INNER JOIN lista_chequeo lc ON lce.lista_chequeo_id = lc.id
            INNER JOIN lista_chequeo_ejec_respuestas lcer ON lce.id = lcer.lista_chequeo_ejec_id
            INNER JOIN pregunta p ON lcer.pregunta_id = p.id
            INNER JOIN categoria cat ON p.categoria_id = cat.id
            LEFT JOIN categoria_etiquetas cet ON cat.id_etiqueta = cet.id
            INNER JOIN usuario u ON lce.usuario_id = u.id
            LEFT JOIN usuario us ON lce.revisado = us.id
            WHERE lc.id = {$id_auditoria} AND lce.id IN ({$idsString})
            ORDER BY lce.id ASC, p.id ASC
        ");

        $registrosAgrupados = [];
        $preguntasUnicas = [];

        foreach ($datosQuery as $row) {
            $listaEjectId = $row->LISTA_EJECT_ID;

            if (!isset($registrosAgrupados[$listaEjectId])) {
                $registrosAgrupados[$listaEjectId] = [
                    'fecha' => $row->FECHA,
                    'hora' => $row->HORA,
                    'firma_operario' => $row->FIRMA_OPERARIO,
                    'firma_lider' => $row->FIRMA_LIDER,
                    'respuestas' => []
                ];
            }

            $registrosAgrupados[$listaEjectId]['respuestas'][$row->PREGUNTA_ID] = $row->RESPUESTA ?? '';

            if (!isset($preguntasUnicas[$row->PREGUNTA_ID])) {
                $preguntasUnicas[$row->PREGUNTA_ID] = [
                    'id' => $row->PREGUNTA_ID,
                    'nombre' => $row->PREGUNTA,
                    'categoria_id' => $row->CATEGORIA_ID,
                    'categoria_nombre' => $row->CATEGORIA_NOMBRE,
                    'etiqueta_id' => $row->ETIQUETA_ID,
                    'etiqueta_nombre' => $row->ETIQUETA_NOMBRE
                ];
            }
        }

        $categorias = [];
        $categoriasMap = [];

        foreach ($preguntasUnicas as $pregunta) {
            $catId = $pregunta['categoria_id'];
            $etiqId = $pregunta['etiqueta_id'];

            if (!isset($categoriasMap[$catId])) {
                $categoriasMap[$catId] = [
                    'id' => $catId,
                    'nombre' => $pregunta['categoria_nombre'],
                    'color' => '#FFC000',
                    'colspan' => 0,
                    'etiquetas' => []
                ];
            }

            if (!isset($categoriasMap[$catId]['etiquetas'][$etiqId])) {
                $categoriasMap[$catId]['etiquetas'][$etiqId] = [
                    'id' => $etiqId,
                    'nombre' => $pregunta['etiqueta_nombre'],
                    'color' => '#888681',
                    'colspan' => 0,
                    'info_adicional' => '',
                    'preguntas' => []
                ];
            }

            $categoriasMap[$catId]['etiquetas'][$etiqId]['preguntas'][] = [
                'id' => $pregunta['id'],
                'nombre' => $pregunta['nombre'],
                'color' => '#fc9425'
            ];

            $categoriasMap[$catId]['etiquetas'][$etiqId]['colspan']++;
            $categoriasMap[$catId]['colspan']++;
        }

        foreach ($categoriasMap as $cat) {
            $cat['etiquetas'] = array_values($cat['etiquetas']);
            $categorias[] = $cat;
        }

        $registros = array_values($registrosAgrupados);

        return [
            'lista_chequeo' => [
                'id' => $data_auditoria->id,
                'nombre' => $data_auditoria->nombre,
                'color' => '#4472C4'
            ],
            'categorias' => $categorias,
            'registros' => $registros,
            'totalPaginas' => $totalPaginas > 0 ? $totalPaginas : 1,
            'totalRegistros' => $totalRegistros
        ];
    }

    public function descargarExcelPlanillaTitulacionGonvarri(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');

        $filtros = json_decode($request->get('filtros_busqueda'));
        $data = $this->SimularDatosPlanillaTitulacionGonvarri(null, $filtros);

        return \Excel::download(new PlanillaTitulacionExportsGonvanrri($data), 'planilla_titulacion_gonvanrri.xlsx');
    }
}
