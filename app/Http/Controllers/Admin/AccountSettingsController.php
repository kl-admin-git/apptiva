<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\Usuario;
use App\Http\Models\CuentaPrincipal;
use App\Http\Models\PlanPagos;
use App\Http\Models\Tour;
class AccountSettingsController extends Controller
{
    protected $usuario,$cuentaPrincipal,$planesPagos,$tour;
    public function __construct(Usuario $usuario,CuentaPrincipal $cuentaPrincipal,PlanPagos $planesPagos,Tour $tour)
    {
        $this->usuario = $usuario;
        $this->cuentaPrincipal = $cuentaPrincipal;
        $this->planesPagos = $planesPagos;
        $this->tour = $tour;
        
        try {
            \DB::statement("SET lc_time_names = 'es_ES'");
        } catch (\Throwable $e) {}
        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        $cuentaPrincipal = $this->cuentaPrincipal
        ->select(
            'p.id AS ID_PLAN',
            \DB::raw(
                'CONCAT(p.nombre," ($ ",TRUNCATE(p.valor,0)," mes)") AS PLAN'
            )
        )
        ->Join('plan AS p','p.id','=','cuenta_principal.plan_id')
        ->where('cuenta_principal.id', '=', auth()->user()->cuenta_principal_id)->first();

        return view('Admin.configuracion_cuenta',compact('cuentaPrincipal'));
    }

    public function ActualizarInformacionPersonal(Request $request)
    {
        $nombre = $request->get('nombre');
        $correo = $request->get('correo');
        $password = $request->get('password');

        if(!is_null($correo))
        {
            if ($this->usuario->where([
                ['correo', '=', $correo],
                ['id', '!=', auth()->user()->id],
                ['cuenta_principal_id', '!=', auth()->user()->cuenta_principal_id]
            ])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'El correo electrónico usado ya existe')
                );
            }
        }

        $respuestaUpdateCP = $this->cuentaPrincipal->where('id','=',auth()->user()->cuenta_principal_id)->update(
        [
            'correo_electronico' => $correo
        ]);

        $arrayUpdate = [
            'nombre_completo' => $nombre, 
            'correo' => $correo,
        ];

        if($password != '')
        {
            $arrayUpdate['password'] = bcrypt($password);
            $arrayUpdate['password_visible'] = $password;
        }
        
        $respuestaUpdate = $this->usuario->where('id','=',auth()->user()->id)->update($arrayUpdate);

        $usuarioActualizado = $this->usuario->where('id','=',auth()->user()->id)->first();
        return $this->FinalizarRetorno(
            201,
            $this->MensajeRetorno('El usuario ',201),
            $usuarioActualizado
        );

    }


    public function TraerPagos(Request $request)
    {
        $paginacion = $request->get('paginacion');

        $listapagos = $this->FuncionTraerPagos($paginacion);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Listado de pagos',202),
            $listapagos
        );
    }

    public function FuncionTraerPagos($paginacion=1)
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;

        $traerPlanes = $this->planesPagos
        ->select(
            \DB::raw('DATE_FORMAT(plan_pagos.created_at,"%d-%M-%Y") AS FECHA'),
            'p.nombre AS NOMBRE_PLAN',
            \DB::raw('CONCAT(DATE_FORMAT(plan_pagos.fecha_inicio,"%d %M %y")," - ",DATE_FORMAT(plan_pagos.fecha_fin,"%d %M %y")) AS PERIODO'),
            \DB::raw('(CASE
                        WHEN plan_pagos.tipo_pago = 0 THEN "Pago único"
                        WHEN plan_pagos.tipo_pago = 1 THEN "Pago tarjeta crédito"
                      END) AS PAGO'),
            \DB::raw('TRUNCATE(p.valor,0) AS TOTAL')
        )
        ->Join('plan AS p','p.id','=','plan_pagos.plan_id')
        ->where('plan_pagos.cuenta_principal_id','=',auth()->user()->cuenta_principal_id)
        ->orderBy('plan_pagos.id','DESC');
        
        $rango = $traerPlanes->paginate($cantidadRegistros)->lastPage();
        $traerPlanes = $traerPlanes->skip($desde)->take($hasta)->get();

        return array(
            'cantidadTotal' => $rango,
            'planesPagos' => $traerPlanes
        );
    }

    public function activarTour(Request $request,$usuarioId)
    {
        $tour =  $this->tour->where([
            ['modulo','=',$request->get('tipo')],
            ['usuario_id','=',$usuarioId]
            ])->first();
        $activo = false;
        if(!is_null($tour)){
            $activo= true;
        }

        return response()->json(['data'=>$activo], 200);
        
    }
    public function omitirTour(Request $request)
    {
       
        $modulo = $request->get('tipo');
        $usuarioId = $request->get('usuarioId');

        $tour = new $this->tour;

        $tour->fill([
            'modulo' => $modulo, 
            'usuario_id'=>$usuarioId,
        ]);

        $tour->save();

        return response()->json(['mensaje'=>'Guardado Correctamente'], 200);
    }

    public function reiniciaTour($usuarioId)
    {
        $tour =  $this->tour->where('usuario_id','=',$usuarioId)->delete();
     
        if ($tour) {
            
           
                return $this->FinalizarRetorno(
                    206,
                    $this->MensajeRetorno('',206,'Tour reiniciado con exito')
                );
            
        }else{
        
            return $this->FinalizarRetorno(
                206,
                $this->MensajeRetorno('',206,'Tour activo')
            );
        }

    }

    
}
