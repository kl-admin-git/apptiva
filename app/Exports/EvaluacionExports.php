<?php

namespace App\Exports;

use App\Http\Models\Usuario;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
class EvaluacionExports implements  FromView,ShouldAutoSize
{
    use Exportable;

    protected $filtros;
    public function __construct($filtros)
    {
        $this->filtros = $filtros;
    }


    public function view(): View
    {
        $filtro_array = [];
       
        foreach ($this->filtros as $key => $filtro) 
        {
          
            switch ($key) {
                
                case 'filtro_lista_chequeo':
                    if($filtro != '')
                        array_push($filtro_array,['lc.id', '=', $filtro]);
                    break;

                case 'filtro_realizacion':
                    if($filtro != '')
                        array_push($filtro_array,['lista_chequeo_ejecutadas.fecha_realizacion', '=', Carbon::createFromFormat('d/m/Y', $filtro)->format('Y-m-d')]);
                    break;

                case 'filtro_estado':
                    if($filtro != '')
                        array_push($filtro_array,['lista_chequeo_ejecutadas.estado', '=', $filtro]);
                    break;

                case 'filtro_entidad':
                    if($filtro != '')
                        array_push($filtro_array,['lc.entidad_evaluada', '=', $filtro]);
                    break;

                case 'filtro_evaluado':
                    if($filtro != '')
                        array_push($filtro_array,['lista_chequeo_ejecutadas.evaluado_id', '=', $filtro]);
                    break;

                case 'filtro_evaluador':
                    if($filtro != '')
                        array_push($filtro_array,['usu.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

      

        $traerInformeEjecutadas = \DB::table('lista_chequeo_ejecutadas')
        ->select(
            'lista_chequeo_ejecutadas.id AS ID_EJECUTADA',
            'lc.nombre AS lista_chequeo',
            \DB::raw('DATE_FORMAT(lista_chequeo_ejecutadas.fecha_realizacion,"%d de %M %Y") AS FECHA_REALIZACION'),
            'lista_chequeo_ejecutadas.latitud',
            'lista_chequeo_ejecutadas.longitud',
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
                WHEN lc.entidad_evaluada=2 THEN (SELECT semp.nombre FROM establecimiento sest 
                                                INNER JOIN empresa semp ON semp.id = sest.empresa_id WHERE sest.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=3 THEN (SELECT semp.nombre FROM usuario susu
                                                INNER JOIN establecimiento sesta ON sesta.id = susu.establecimiento_id
                                                INNER JOIN empresa semp ON semp.id = sesta.empresa_id
                                                WHERE susu.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lista_chequeo_ejecutadas.evaluado_id)
                WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lista_chequeo_ejecutadas.evaluado_id)
                ELSE "Error"
            END) as empresa'),
            \DB::raw('(CASE
            WHEN lc.entidad_evaluada=1 THEN (SELECT semp.nombre FROM empresa semp WHERE semp.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=2 THEN (SELECT sest.nombre FROM establecimiento sest WHERE sest.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=3 THEN (SELECT susu.nombre_completo FROM usuario susu WHERE susu.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=4 THEN (SELECT ars.nombre FROM areas ars WHERE ars.id=lista_chequeo_ejecutadas.evaluado_id)
              WHEN lc.entidad_evaluada=5 THEN (SELECT eqs.nombre FROM equipos eqs WHERE eqs.id=lista_chequeo_ejecutadas.evaluado_id)
              ELSE "Error"
            END) as evaluado'),
            'usu.nombre_completo AS evaluador',
            \DB::raw('TRUNCATE ((SELECT 
                (SUM((spre.ponderado*(IF(sres.ponderado IS NULL,100,sres.ponderado))/100)))*scat.ponderado/100 
                FROM lista_chequeo_ejec_respuestas slcer
                INNER JOIN respuesta sres ON sres.id=slcer.respuesta_id
                INNER JOIN pregunta spre ON spre.id=slcer.pregunta_id
                INNER JOIN categoria scat ON scat.id=spre.categoria_id
                WHERE  slcer.lista_chequeo_ejec_id=lista_chequeo_ejecutadas.id),2) AS resultado_final')
        )
        ->Join('lista_chequeo AS lc','lc.id','=','lista_chequeo_ejecutadas.lista_chequeo_id')
        ->Join('usuario AS usu','usu.id','=','lista_chequeo_ejecutadas.usuario_id')
        ->Join('establecimiento AS est','est.id','=','usu.establecimiento_id')
        ->Join('empresa AS emp','emp.id','=','est.empresa_id')
        ->orderBy('lista_chequeo_ejecutadas.id','DESC');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $traerInformeEjecutadas = $traerInformeEjecutadas->where('usu.cuenta_principal_id','=',auth()->user()->cuenta_principal_id);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $traerInformeEjecutadas = $traerInformeEjecutadas->where('emp.id','=',$esResponsableEmpresa->id);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    $traerInformeEjecutadas = $traerInformeEjecutadas->where('est.id','=',$esResponsableEstablecimiento->id);

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    $traerInformeEjecutadas = $traerInformeEjecutadas->where('lista_chequeo_ejecutadas.usuario_id','=',auth()->user()->id);

                break;
            
            default:

                break;
        };
        
        if(COUNT($filtro_array) != 0)
        {
            $traerInformeEjecutadas = $traerInformeEjecutadas->where(function($query) use ($filtro_array)
            {
                // $contador = 0;
                foreach ($filtro_array as $keys => $oW) 
                {
                    $query->where($oW[0], '=', $oW[2]);
                }

                return $query;
            });
            
        }
        $traerInformeEjecutadas = $traerInformeEjecutadas->get();
      

        foreach ($traerInformeEjecutadas as $keyss => $ejecutada)
        {
            $categorias = \DB::select("SELECT
                lcer.categoria_id,
                cat.nombre as categoria,
                lcer.no_aplica,
                IF(COUNT(lcer.id) = lcer.no_aplica,1,0) AS todas_no_aplican,
                pre.nombre as pregunta,
                cat.ponderado AS cat_ponderado,
                pre.ponderado AS pre_ponderado,
                res.valor_personalizado AS respuesta,
                res.ponderado AS res_ponderado,
                (pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100) AS pordentaje_pregunta,
                SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100)) AS sum_pordentaje_pregunta,
                (SUM((pre.ponderado*(IF(res.ponderado IS NULL,100,res.ponderado))/100)))*cat.ponderado/100 AS porc_cat
                FROM lista_chequeo_ejec_respuestas lcer
                INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
                INNER JOIN respuesta res ON res.id=lcer.respuesta_id
                INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
                INNER JOIN categoria cat ON cat.id=pre.categoria_id
                WHERE  lcer.lista_chequeo_ejec_id=:idEjecutada
                GROUP BY lcer.categoria_id
                ORDER BY cat.id;",['idEjecutada' => $ejecutada->ID_EJECUTADA]);

                $suma = 0;
                $todas_no_aplican = 0;
                foreach ($categorias as $keysss => $item) 
                {
                    $suma += floatval($item->porc_cat);
                    $todas_no_aplican = $item->todas_no_aplican;
                }

                if($todas_no_aplican == 0)
                    $traerInformeEjecutadas[$keyss]->resultado_final = number_format($suma,2);
                else
                    $traerInformeEjecutadas[$keyss]->resultado_final = "";

        }
        // remuevo los campos que no voy a utilizar en el excel
        foreach ($traerInformeEjecutadas as $key => $value) {
            # code...
            unset($traerInformeEjecutadas[$key]->ID_EJECUTADA);
            unset($traerInformeEjecutadas[$key]->latitud);
            unset($traerInformeEjecutadas[$key]->longitud);
            unset($traerInformeEjecutadas[$key]->ID_ESTADO);
            
        }
      

       //dd($traerInformeEjecutadas);
        return view('exports.listaChequeoEjecutadas', [
            'traerInformeEjecutadas' => $traerInformeEjecutadas,
        ]);
    }



    public function FuncionParaSaberSiEsResponsableEmpresa($idUsuario)
    {
        $esResponsableEmpresa = \DB::table('empresa')->where('usuario_id','=',$idUsuario)->first();

        return $esResponsableEmpresa;
    }

    public function FuncionParaSaberSiEsResponsableEstablecimiento($idUsuario)
    {
        $esResponsableEstablecimiento = \DB::table('establecimiento')->where('usuario_id','=',$idUsuario)->first();

        return $esResponsableEstablecimiento;
    }
}
