<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use Excel;
use App\Http\Models\Pais;
use App\Http\Models\Sector;
use App\Http\Models\Departamento;
use App\Http\Models\Ciudad;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use App\Http\Models\Usuario;
use Illuminate\Support\Str;

class AdministracionEmpresasController extends Controller
{
    private $excel,$pais,$sector,$departamento,$ciudad,$empresa,$usuario;
    public function __construct(Excel $excel,Pais $pais, Sector $sector,Departamento $departamento,Ciudad $ciudad,Empresa $empresa, Usuario $usuario,Establecimiento $establecimiento)
    {
        $this->excel = $excel;
        $this->pais = $pais;
        $this->sector = $sector;
        $this->departamento = $departamento;
        $this->ciudad = $ciudad;
        $this->empresa = $empresa;
        $this->usuario = $usuario;
        $this->establecimiento = $establecimiento;

        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        $paises = $this->pais->select('id','indicativo','nombre',
            \DB::raw('CONCAT(indicativo," (",nombre,")") AS CONCATENACION')
        )
        ->orderBy('nombre','ASC')
        ->where('estado','=',1)
        ->get();

        $sectores = $this->sector->select('id','nombre')
        ->orderBy('nombre','ASC')
        ->where('estado','=', 1)
        ->get();

        $empresas = $this->empresa->select(
            'empresa.id',
            'empresa.identificacion',
            'empresa.nombre',
            'empresa.direccion'
        );

        $usuariosResponsables = $this->usuario->select('usuario.id','usuario.nombre_completo')
        ->Join('empresa AS em','em.usuario_id','=','usuario.id')
        ->orderBy('usuario.nombre_completo','ASC')
        ->groupBy('usuario.nombre_completo');

        $usuariosPopUp = $this->usuario->select('usuario.id','usuario.nombre_completo')
        ->where([
            ['usuario.estado','=', 1],
            ['usuario.cuenta_principal_id','=', auth()->user()->cuenta_principal_id]            
        ])
        ->orderBy('usuario.nombre_completo','ASC')
        ->get();


        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $empresas = $empresas->where([
                    ['empresa.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]
                ])
                ->get();

                $usuariosResponsables = $usuariosResponsables->where([
                    ['usuario.estado','=', 1],
                    ['usuario.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]          
                ])->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                {
                    $empresas = $empresas->where([
                        ['empresa.id','=', $esResponsableEmpresa->id]
                    ])
                    ->get();

                    $usuariosResponsables = $usuariosResponsables->where([
                        ['usuario.estado','=', 1],
                        ['em.id','=',  $esResponsableEmpresa->id]            
                    ])->get();
                }
                    

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                    return redirect('/dashboard');

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                    return redirect('/dashboard');

                break;
            
            default:

                break;
        };
        
        return view('Admin.administracion_empresas',
            compact(
                'paises',
                'sectores',
                'empresas',
                'usuariosResponsables',
                'usuariosPopUp'
            )
        );
    }

    public function CambioPais(Request $request)
    {
        $idPais = $request->get('idPais');
        $departamentos = $this->departamento->select('id','nombre')
        ->where([
            ['pais_id','=',$idPais],
            ['estado','=',1]
        ])
        ->orderBy('nombre','ASC')
        ->get();

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $departamentos
        );
    }

    public function CambioDepartamento(Request $request)
    {
        $idDepartamento = $request->get('idDepartamento');
        $ciudades = $this->ciudad->select('id','nombre')->where([
            ['departamento_id','=',$idDepartamento],
            ['estado','=',1]
        ])
        ->orderBy('nombre','ASC')
        ->get();

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $ciudades
        );
    }

    public function CrearEmpresa(Request $request)
    {
        $objetoRecibido = json_decode($request->get('objetoEnviar'));

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


        //VALIDACIÓN SI PUEDE CREAR EMPRESA
        $planCrearEmpresa = $this->FuncionValidarSiPuedeCrearEmpresa();
        if(!$planCrearEmpresa)
        {
            return $this->FinalizarRetorno(
                407,
                $this->MensajeRetorno('',407,'El plan actual ha llegado al límite de creación de empresas, cambia de plan o comunícate con el administrador de la cuenta'),
                auth()->user()->perfil_id
            );
        }

        $logoImagen = $request->file('file');

        if(!is_null($logoImagen))
        {
            $nombreImagen = $objetoRecibido->nit . Str::random(10) . '.' . 'png';
            $imagenNuevoTamano = \Image::make($logoImagen->getRealPath());              
            $imagenNuevoTamano->resize(128, 128);
            $imagenNuevoTamano->save(public_path($this->urlImagenesLogo.$nombreImagen));
        }
            
        $nombreEmpresa = $objetoRecibido->nombreEmpresa;
        $nit = ($objetoRecibido->nit == '' ? NULL : $objetoRecibido->nit);
        $correo = ($objetoRecibido->correo == '' ? NULL : $objetoRecibido->correo);
        $direccion = ($objetoRecibido->direccion == '' ? NULL : $objetoRecibido->direccion);
        $telefono = ($objetoRecibido->telefono == '' ? NULL : $objetoRecibido->telefono);
        $idPais = ($objetoRecibido->idPais == 0 ? NULL : $objetoRecibido->idPais);
        $idDepartamento = ($objetoRecibido->idDepartamento == 0 ? NULL : $objetoRecibido->idDepartamento);
        $idCiudad = ($objetoRecibido->idCiudad == 0 ? NULL : $objetoRecibido->idCiudad);
        $idSector = $objetoRecibido->idSector;
        $idResponsable = ($objetoRecibido->idResponsable == 0 ? NULL : $objetoRecibido->idResponsable);

        if(!is_null($nit))
        {
            if ($this->empresa->where([
                ['identificacion', '=', $nit],
                ['cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]
            ])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'El NIT usado ya existe')
                );
            }
        }

        $arrayInsertar = [
            'nombre' => $nombreEmpresa, 
            'identificacion' => $nit,
            'correo' => $correo,
            'telefono' => $telefono,
            'direccion' => $direccion, 
            'ciudad_id' => $idCiudad,
            'sector_id' => $idSector,
            'usuario_id' => $idResponsable,
            'cuenta_principal_id' => auth()->user()->cuenta_principal_id
        ];

        if(!is_null($logoImagen))
            $arrayInsertar['url_imagen'] = $nombreImagen;

        $empresa = new $this->empresa;
        $empresa->fill($arrayInsertar);

        if($empresa->save())
        {
            if(!is_null($idResponsable))
            {
                if($this->empresa->where('usuario_id','=',$idResponsable)->exists())
                    $this->empresa->where('usuario_id','=',$idResponsable)->update(['usuario_id' => NULL]);

                $this->empresa->where('id','=',$empresa->id)->update(['usuario_id' => $idResponsable]);
            }
            $paises = $this->pais->select('id','indicativo','nombre',
            \DB::raw('CONCAT(indicativo," (",nombre,")") AS CONCATENACION')
            )->get();

            $empresas = $this->empresa->select(
                        'empresa.id',
                        'empresa.identificacion',
                        'empresa.nombre',
                        'empresa.direccion'
                    )->where([
                        ['empresa.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]
                    ])
                    ->get();

            $objeto['paises'] = $paises;
            $objeto['empresas'] = $empresas;

            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('La empresa',200),
                json_encode($objeto)
            );
        }
    }

    public function EditarEmpresa(Request $request)
    {
        $objetoRecibido = json_decode($request->get('objetoEnviar'));
        $logoImagen = $request->file('file');

        if(!is_null($logoImagen))
        {
            $nombreImagen = $objetoRecibido->nit . Str::random(10) . '.' . 'png';
            $imagenNuevoTamano = \Image::make($logoImagen->getRealPath());              
            $imagenNuevoTamano->resize(128, 128);
            $imagenNuevoTamano->save(public_path($this->urlImagenesLogo.$nombreImagen));
        }
            
        $nombreEmpresa = $objetoRecibido->nombreEmpresa;
        $nit = ($objetoRecibido->nit == '' ? NULL : $objetoRecibido->nit);
        $correo = ($objetoRecibido->correo == '' ? NULL : $objetoRecibido->correo);
        $direccion = ($objetoRecibido->direccion == '' ? NULL : $objetoRecibido->direccion);
        $telefono = ($objetoRecibido->telefono == '' ? NULL : $objetoRecibido->telefono);
        $idPais = ($objetoRecibido->idPais == 0 ? NULL : $objetoRecibido->idPais);
        $idDepartamento = ($objetoRecibido->idDepartamento == 0 ? NULL : $objetoRecibido->idDepartamento);
        $idCiudad = ($objetoRecibido->idCiudad == 0 ? NULL : $objetoRecibido->idCiudad);
        $idSector = $objetoRecibido->idSector;
        $idEmpresa = $objetoRecibido->idEmpresa;
        $idResponsable = ($objetoRecibido->idResponsable == 0 ? NULL : $objetoRecibido->idResponsable);

        if(!is_null($nit))
        {
            if ($this->empresa->where([
                ['identificacion', '=', $nit],
                ['id', '!=', $idEmpresa],
                ['cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]
            ])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'El NIT usado ya existe')
                );
            }
        }

        $arrayActualizar = [
            'nombre' => $nombreEmpresa, 
            'identificacion' => $nit,
            'correo' => $correo,
            'telefono' => $telefono,
            'direccion' => $direccion, 
            'ciudad_id' => $idCiudad,
            'sector_id' => $idSector,
            'usuario_id' => $idResponsable,
            'cuenta_principal_id' => auth()->user()->cuenta_principal_id
        ];

        if(!is_null($logoImagen))
        {
            $arrayActualizar['url_imagen'] = $nombreImagen;
            $empresaAcutalizada = $this->FuncionTraerEmpresaPorId($idEmpresa);
            if(\File::exists($this->urlImagenesLogo.$empresaAcutalizada->url_imagen)) 
                \File::delete($this->urlImagenesLogo.$empresaAcutalizada->url_imagen);
        }

        if(!is_null($idResponsable))
        {
            if($this->empresa->where('usuario_id','=',$idResponsable)->exists())
                $this->empresa->where('usuario_id','=',$idResponsable)->update(['usuario_id' => NULL]);
        }
        
        $respuestaUpdate = $this->empresa->where('id','=',$idEmpresa)
        ->update($arrayActualizar);

        if($respuestaUpdate)
        {
            $empresaAcutalizada = $this->FuncionTraerEmpresaPorId($idEmpresa);

            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('La empresa',201),
                $empresaAcutalizada
            );
        }
    }

    public function ActualizarEstadoEmpresa(Request $request)
    {
        $idEmpresa = $request->get('idEmpresa');
        $estadoActual = $request->get('estadoActual');

        $estadoCambiado = 0;
        if($estadoActual == 0)
            $estadoCambiado = 1;
        else if($estadoActual == 1)
            $estadoCambiado = 0;
        
        $respuestaUpdate = $this->empresa->where('id','=',$idEmpresa)
        ->update(
        [
            'estado' => $estadoCambiado
        ]);

        if($respuestaUpdate)
        {
            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('La empresa',201),
                $estadoCambiado
            );
        }
    }
    
    public function ConsultaEmpresas(Request $request)
    {
        $idCuentaPrincipal = $request->get('idCuentaPrincipal');
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        $empresas = $this->FuncionTraerEmpresasPorPaginacion($idCuentaPrincipal,$paginacion,$filtros);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $empresas
        );
    }

    public function EliminarEmpresa(Request $request)
    {
        $idEmpresa = $request->get('idEmpresa');
        $respuesta = $this->empresa->where('id', $idEmpresa)->delete();

        if($respuesta)
        {
            return $this->FinalizarRetorno(
                203,
                $this->MensajeRetorno('La empresa ',203)
            );  
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'La empresa no pudo eliminarse')
            ); 
        }
        
    }

    public function ConsultaEditarEmpresa(Request $request)
    {
        $idEmpresa = $request->get('idEmpresa');
        $empresa = $this->empresa->select(
            'empresa.id',
            'empresa.nombre',
            \DB::raw('IF(empresa.identificacion IS NULL,"",empresa.identificacion) AS identificacion'),
            \DB::raw('IF(empresa.correo IS NULL,"",empresa.correo) AS correo'),
            \DB::raw('IF(empresa.direccion IS NULL,"",empresa.direccion) AS direccion'),
            \DB::raw('IF(empresa.telefono IS NULL,"",empresa.telefono) AS TELEFONO'),
            \DB::raw('IF(p.id IS NULL,"0",p.id) AS PAIS_ID'),
            \DB::raw('IF(d.id IS NULL,"0",d.id) AS DEPARTAMENTO_ID'),
            \DB::raw('IF(c.id IS NULL,"0",c.id) AS CIUDAD_ID'),
            's.id AS SECTOR',
            \DB::raw('IF(u.id IS NULL,"0",u.id) AS RESPONSABLE')
        )
        ->leftJoin('ciudad AS c','c.id','=','empresa.ciudad_id')
        ->leftJoin('departamento AS d','d.id','=','c.departamento_id')
        ->leftJoin('pais AS p','p.id','=','d.pais_id')
        ->Join('sector AS s','s.id','=','empresa.sector_id')
        ->leftJoin('usuario AS u','u.id','=','empresa.usuario_id')
        ->where('empresa.id','=',$idEmpresa)->first();
        
        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $empresa
        );
    }

    public function FuncionTraerEmpresaPorId($idEmpresa)
    {
        $empresa = $this->empresa->select(
            'empresa.id',
            'empresa.nombre',
            \DB::raw('IF(empresa.identificacion IS NULL,"Sin NIT",empresa.identificacion) AS identificacion'),
            \DB::raw('IF(empresa.correo IS NULL,"Sin correo electrónico",empresa.correo) AS correo'),
            \DB::raw('IF(empresa.direccion IS NULL,"Sin dirección",empresa.direccion) AS direccion'),
            'empresa.estado',
            'empresa.url_imagen',
            \DB::raw('IF(empresa.telefono IS NULL,"Sin teléfono",empresa.telefono) AS TELEFONO'),
            \DB::raw('IF(empresa.url_imagen IS NULL,"/vertical/assets/images/users/circle_logo_audiid.png",CONCAT("/imagenes/logos_empresariales/",empresa.url_imagen)) AS FOTO'),
            \DB::raw('IF(c.nombre IS NULL,"Sin ciudad",CONCAT(c.nombre,", ",p.nombre)) AS CIUDAD'),
            's.nombre AS SECTOR',
            \DB::raw('IF(u.nombre_completo IS NULL,"Sin responsable",u.nombre_completo) AS RESPONSABLE')
        )
        ->leftJoin('ciudad AS c','c.id','=','empresa.ciudad_id')
        ->leftJoin('departamento AS d','d.id','=','c.departamento_id')
        ->leftJoin('pais AS p','p.id','=','d.pais_id')
        ->Join('sector AS s','s.id','=','empresa.sector_id')
        ->leftJoin('usuario AS u','u.id','=','empresa.usuario_id')
        ->where('empresa.id','=',$idEmpresa)->first();
        
        return $empresa;
    }

    public function DescargarExcelDirectorio(Request $request)
    {
        setlocale(LC_ALL, 'es_ES.utf8');
        // return Excel::download/Excel::store($yourExport);
        // return \Excel::create('Directorio', function ($excel) /*use ($arrayImpresion, $audId)*/
        // {
        //     $excel->sheet('Directorio', function ($sheet) /*use ($arrayImpresion, $audId) */
        //     {

        //     });

        // })->download('xlsx');
    
        return Excel::download(function ($excel)
        {
            $excel->sheet('Directorio', function ($sheet)
            {
                $sheet->setFontFamily('Tahoma');
                        $sheet->setAutoSize(true);

                        /* ENCABEZADO EXCEL */

                        $sheet->mergeCells('A2:C2');
                        $sheet->mergeCells('A3:C3');
                        $sheet->mergeCells('A4:C4');
                        $sheet->mergeCells('A5:C5');
                        $sheet->mergeCells('A6:C6');
                        $sheet->mergeCells('A8:C8');
                        $sheet->mergeCells('A9:N11');

                        $sheet->cell('A2', function ($cell) {
                            $cell->setValue('FECHA');
                            $cell->setFontWeight('bold');
                            $cell->setFontSize(8);
                        });

                        $sheet->cell('A3', function ($cell) {
                            $cell->setValue('EVALUADOR');
                            $cell->setFontWeight('bold');
                            $cell->setFontSize(8);
                        });
            });
        }, 'invoices.xlsx');
            
    }

    public function TraerEmpresasPaginacion(Request $request)
    {
        $idCuentaPrincipal = $request->get('idCuentaPrincipal');
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        $empresas = $this->FuncionTraerEmpresasPorPaginacion($idCuentaPrincipal,$paginacion,$filtros);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $empresas
        );
    }

    public function FuncionTraerEmpresasPorPaginacion($idCuentaPrincipal,$paginacion=1,$filtros=[])
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

        $empresas = $this->empresa->select(
            'empresa.id',
            'empresa.nombre',
            \DB::raw('IF(empresa.identificacion IS NULL,"Sin NIT",empresa.identificacion) AS identificacion'),
            \DB::raw('IF(empresa.correo IS NULL,"Sin correo electrónico",empresa.correo) AS correo'),
            \DB::raw('IF(empresa.direccion IS NULL,"Sin dirección",empresa.direccion) AS direccion'),
            'empresa.estado',
            \DB::raw('IF(empresa.url_imagen IS NULL,"/vertical/assets/images/users/circle_logo_audiid.png",CONCAT("/imagenes/logos_empresariales/",empresa.url_imagen)) AS FOTO'),
            \DB::raw('IF(empresa.telefono IS NULL,"Sin teléfono",empresa.telefono) AS TELEFONO'),
            \DB::raw('IF(c.nombre IS NULL,"Sin ciudad",CONCAT(c.nombre,", ",p.nombre)) AS CIUDAD'),
            's.nombre AS SECTOR',
            \DB::raw('IF(u.nombre_completo IS NULL,"Sin responsable",u.nombre_completo) AS RESPONSABLE')
        )
        ->leftJoin('ciudad AS c','c.id','=','empresa.ciudad_id')
        ->leftJoin('departamento AS d','d.id','=','c.departamento_id')
        ->leftJoin('pais AS p','p.id','=','d.pais_id')
        ->Join('sector AS s','s.id','=','empresa.sector_id')
        ->leftJoin('usuario AS u','u.id','=','empresa.usuario_id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $empresas = $empresas->where('empresa.cuenta_principal_id','=',$idCuentaPrincipal);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $empresas = $empresas->where('empresa.id','=',$esResponsableEmpresa->id);

                break;
            
            default:

                break;
        };

        if(COUNT($filtro_array) != 0)
        {
            $empresas = $empresas->where(function($query) use ($filtro_array)
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

        $empresas = $empresas->skip($desde)->take($hasta)->get();

        return $empresas;
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

    public function FuncionValidarSiPuedeCrearEmpresa()
    {

        $puedeEjecutar = \DB::select("SELECT
        (CASE
            WHEN pl.id=1 THEN '1'
            WHEN pl.id=2 THEN '2'
            WHEN pl.id=3 THEN '3'
            ELSE 'contacto'
        END) AS plan,
        COUNT(*) AS cta_empresa_creados,
        (SELECT spp.valor FROM plan_parametros spp
        INNER JOIN plan spl ON spl.id= spp.plan_id
        WHERE spp.id=
        (CASE
            WHEN pl.id=1 THEN 4
            WHEN pl.id=2 THEN 9
            WHEN pl.id=3 THEN 14
            WHEN pl.id=4 THEN 19
            ELSE 'contacto'
        END) AND spl.id=pl.id) AS plan_empresa,
        (
        IF (COUNT(*)<(SELECT spp.valor FROM plan_parametros spp
        INNER JOIN plan spl ON spl.id= spp.plan_id
        WHERE spp.id=
        (CASE
            WHEN pl.id=1 THEN 4
            WHEN pl.id=2 THEN 9
            WHEN pl.id=3 THEN 14
            WHEN pl.id=4 THEN 19
            ELSE 'contacto'
        END) AND spl.id=pl.id),'SI','NO')
        ) AS puede_crear
        FROM empresa em
        INNER JOIN cuenta_principal cp ON cp.id=em.cuenta_principal_id
        INNER JOIN plan pl ON pl.id=cp.plan_id
        WHERE em.cuenta_principal_id=:idCuentaPrincipal;",['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

        if(ISSET($puedeEjecutar))
        {
            if(COUNT($puedeEjecutar) != 0)
            {
                if($puedeEjecutar[0]->puede_crear == 'SI')
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

        $puedeEjecutar = \DB::select("SELECT
        pp.id,
        date(NOW()) AS hoy,
        pp.fecha_inicio,
        pp.fecha_fin,
        IF(((date(NOW()))>=pp.fecha_inicio AND ((date(NOW()))<=pp.fecha_fin )),'SI','NO' ) AS aldia
        FROM plan_pagos pp
        INNER JOIN cuenta_principal cp ON cp.id=pp.cuenta_principal_id
        WHERE cp.id=:idCuentaPrincipal
        ORDER BY pp.id DESC limit 1;",['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

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

    
}
