<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\enviarMensajeEvent;
use App\Http\Models\Usuario;
use App\Http\Models\Pais;
use App\Http\Models\Perfil;
use App\Http\Models\Cargo;
use App\Http\Models\Empresa;
use App\Http\Models\Establecimiento;
use Illuminate\Support\Str;

class AdministracionUsuariosController extends Controller
{
    protected $usuario,$pais,$perfil,$cargo,$establecimiento,$empresa;
    public function __construct(Usuario $usuario,Pais $pais,Perfil $perfil,Cargo $cargo,Establecimiento $establecimiento,Empresa $empresa)
    {
        $this->usuario = $usuario;
        $this->pais = $pais;
        $this->perfil = $perfil;
        $this->cargo = $cargo;
        $this->establecimiento = $establecimiento;
        $this->empresa = $empresa;
        
        $this->middleware('auth');
        $this->middleware('isActive');
    }

    public function Index()
    {
        $paises = $this->pais
        ->select('id','indicativo','nombre',
        \DB::raw('CONCAT(indicativo," (",nombre,")") AS CONCATENACION')
        )
        ->get();

        $perfiles = $this->perfil
        ->select('id','nombre')
        ->where('estado','=','1')
        ->get();

        $cargos = $this->cargo
        ->select('id','nombre')
        ->where([
            ['estado','=','1'],
            ['cuenta_principal_id','=',auth()->user()->cuenta_principal_id]
        ])
        ->get();

        $establecimientos = $this->establecimiento
        ->select('establecimiento.id', \DB::raw('CONCAT(establecimiento.nombre," (",em.nombre,")") AS nombre'))
        ->Join('empresa AS em','em.id','establecimiento.empresa_id');

        $usuario = $this->usuario->select(
            'usuario.id',
            'usuario.usuario AS nombre',
            'usuario.correo',
            'c.nombre AS cargo'
        )
        ->leftJoin('cargo AS c','c.id','usuario.cargo_id')
        ->leftJoin('establecimiento AS e','e.id','usuario.establecimiento_id');
        
        $empresas = $this->empresa->select(
            'empresa.id',
            'empresa.identificacion',
            'empresa.nombre',
            'empresa.direccion'
        );

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $establecimientos = $establecimientos->where([
                    ['establecimiento.estado','=','1'],
                    ['em.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]
                ])->get();

                $usuario = $usuario->where([
                    ['usuario.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]
                ])
                ->get();

                $empresas = $empresas->where([
                    ['empresa.cuenta_principal_id','=',auth()->user()->cuenta_principal_id]
                ])
                ->get();
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                {
                    $establecimientos = $establecimientos->where([
                        ['establecimiento.estado','=','1'],
                        ['em.id','=',$esResponsableEmpresa->id]
                    ])->get();

                    $usuario = $usuario
                    ->leftJoin('empresa AS em','em.id','e.empresa_id')
                    ->where([
                        ['em.id','=',$esResponsableEmpresa->id],
                        ['usuario.perfil_id','!=', 1]
                    ])
                    ->get();

                    $empresas = $empresas->where([
                        ['empresa.id','=',$esResponsableEmpresa->id]
                    ])
                    ->get();
                }

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                {
                    $empresas = [];

                    $establecimientos = $establecimientos->where([
                        ['establecimiento.estado','=','1'],
                        ['establecimiento.id','=',$esResponsableEstablecimiento->id]
                    ])->get();

                    $usuario = $usuario
                    ->where([
                        ['e.id','=',$esResponsableEstablecimiento->id],
                        ['usuario.perfil_id','!=', 1]
                    ])
                    ->get();
                }
                

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                {
                    $empresas = [];
                    
                    $establecimientos = $establecimientos
                    ->Join('usuario AS u','u.establecimiento_id','=','establecimiento.id')
                    ->where([
                        ['establecimiento.estado','=','1'],
                        ['u.id','=',auth()->user()->id]
                    ])->get();

                    $usuario = $usuario
                    ->where([
                        ['usuario.id','=', auth()->user()->id]
                    ])
                    ->get();
                }
                    

                break;
            
            default:

                break;
        };

        return view('Admin.administracion_usuarios',
            compact('usuario','empresas','perfiles','cargos','establecimientos')
        );
    }

    public function CrearUsuario(Request $request)
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



        //VALIDACIÓN SI PUEDE CREAR USUARIO
        $planCrearUsuario = $this->FuncionValidarSiPuedeCrearUsuarios();
        if(!$planCrearUsuario)
        {
            return $this->FinalizarRetorno(
                407,
                $this->MensajeRetorno('',407,'El plan actual ha llegado al límite de creación de usuarios, cambia de plan o comunícate con el administrador de la cuenta'),
                auth()->user()->perfil_id
            );
        }

        $logoImagen = $request->file('file');
        
        if(!is_null($logoImagen))
        {
            $nombreImagen = $objetoRecibido->identificacion . Str::random(10) . '.' . 'png';
            $imagenNuevoTamano = \Image::make($logoImagen->getRealPath());              
            $imagenNuevoTamano->resize(128, 128);
            $imagenNuevoTamano->save(public_path($this->urlImagenesAvatar.$nombreImagen));
        }

        $nombres = $objetoRecibido->nombres;
        $identificacion = ($objetoRecibido->identificacion == '' ? NULL : $objetoRecibido->identificacion);
        $correo = ($objetoRecibido->correo == '' ? NULL : $objetoRecibido->correo);
        // $usuarioName = $objetoRecibido->usuario;
        $password = $objetoRecibido->password;
        $password_visible = $objetoRecibido->password;
        $perfilId = $objetoRecibido->perfilId;
        $telefono = ($objetoRecibido->telefono == '' ? NULL : $objetoRecibido->telefono);
        $idCargo = ($objetoRecibido->idCargo == 0 ? NULL : $objetoRecibido->idCargo);
        $establecimientoId = ($objetoRecibido->establecimientoId == 0 ? NULL : $objetoRecibido->establecimientoId);

        if(!is_null($correo))
        {
            if ($this->usuario
            ->where([['correo', '=', $correo],['cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'El correo electrónico usado ya existe')
                );
            }
        }
        
        if(!is_null($identificacion))
        {
            if ($this->usuario->where([['identificacion', '=', $identificacion],['cuenta_principal_id', '=', auth()->user()->cuenta_principal_id]])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'La identificación usada ya existe')
                );
            }
        }
        

        $arrayInsertar = [
            'nombre_completo' => $nombres, 
            'identificacion' => $identificacion,
            'correo' => $correo,
            'telefono' => $telefono,
            'cargo_id' => $idCargo, 
            'perfil_id' => $perfilId,
            'establecimiento_id' => $establecimientoId,
            // 'usuario' => $usuarioName,
            'password' => $password,
            'password_visible' => $password_visible,
            'cuenta_principal_id' => auth()->user()->cuenta_principal_id
        ];

        if(!is_null($logoImagen))
            $arrayInsertar['url_imagen'] = $nombreImagen;

        $usuario = new $this->usuario;
        $usuario->fill($arrayInsertar);

        if($usuario->save())
        {
            return $this->FinalizarRetorno(
                200,
                $this->MensajeRetorno('El usuario ',200)
            );
        }
    }

    public function ConsultaUsuarios(Request $request)
    {
        $idCuentaPrincipal = $request->get('idCuentaPrincipal');
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        $usuarios = $this->FuncionTraerUsuariosPorPaginacion($idCuentaPrincipal,$paginacion,$filtros);
        
        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $usuarios
        );
    }

    public function EliminarUsuario(Request $request)
    {
        $idUsuario = $request->get('idUsuario');
        $respuesta = $this->usuario->where('id', $idUsuario)->delete();

        if($respuesta)
        {
            return $this->FinalizarRetorno(
                203,
                $this->MensajeRetorno('El usuario ',203)
            );  
        }
        else
        {
            return $this->FinalizarRetorno(
                406,
                $this->MensajeRetorno('',406,'El usuario no pudo eliminarse')
            ); 
        }
        
    }

    public function ConsultaEditarUsuario(Request $request)
    {
        $idUsuario = $request->get('idUsuario');
        $usuarios = $this->usuario->select(
            'usuario.id',
            'usuario.nombre_completo',
            \DB::raw('IF(usuario.identificacion IS NULL,"",usuario.identificacion) AS identificacion'),
            \DB::raw('IF(usuario.correo IS NULL,"",usuario.correo) AS correo'),
            \DB::raw('IF(usuario.telefono IS NULL,"0",usuario.telefono) AS TELEFONO'),
            \DB::raw('IF(cg.id IS NULL,"0",cg.id) AS CARGO'),
            'pe.id AS PERFIL',
            \DB::raw('IF(em.nombre IS NULL,"0",em.nombre) AS EMPRESA'),
            \DB::raw('IF(e.id IS NULL,"0",e.id) AS ESTABLECIMIENTO'),
            'usuario.estado',
            \DB::raw('IF(c.nombre IS NULL,"0",CONCAT(c.nombre,", ",p.nombre)) AS CIUDAD'),
            \DB::raw('IF(usuario.usuario IS NULL,"",usuario.usuario) AS USUARIO'),
            'usuario.password_visible AS PASSWORD'
        )
        ->leftJoin('establecimiento AS e','e.id','=','usuario.establecimiento_id')
        ->leftJoin('ciudad AS c','c.id','=','e.ciudad_id')
        ->leftJoin('departamento AS d','d.id','=','c.departamento_id')
        ->leftJoin('pais AS p','p.id','=','d.pais_id')
        ->leftJoin('cargo AS cg','cg.id','=','usuario.cargo_id')
        ->Join('perfil AS pe','pe.id','=','usuario.perfil_id')
        ->leftJoin('empresa AS em','em.id','=','e.empresa_id')
        ->where('usuario.id','=',$idUsuario)->first();
        
        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $usuarios
        );
    }

    public function ActualizarUsuario(Request $request)
    {
        $objetoRecibido = json_decode($request->get('objetoEnviar'));
        $logoImagen = $request->file('file');
        
        if(!is_null($logoImagen))
        {
            $nombreImagen = $objetoRecibido->identificacion . Str::random(10) . '.' . 'png';
            $imagenNuevoTamano = \Image::make($logoImagen->getRealPath());              
            $imagenNuevoTamano->resize(128, 128);
            $imagenNuevoTamano->save(public_path($this->urlImagenesAvatar.$nombreImagen));
        }

        $idUsuario = $objetoRecibido->idUsuario;
        $nombres = $objetoRecibido->nombres;
        $identificacion = ($objetoRecibido->identificacion == '' ? NULL : $objetoRecibido->identificacion);
        $correo = ($objetoRecibido->correo == '' ? NULL : $objetoRecibido->correo);
        // $usuarioName = $objetoRecibido->usuario;
        $password = $objetoRecibido->password;
        $password_visible = $objetoRecibido->password;
        $perfilId = $objetoRecibido->perfilId;
        $telefono = ($objetoRecibido->telefono == '' ? NULL : $objetoRecibido->telefono);
        $idCargo = ($objetoRecibido->idCargo == 0 ? NULL : $objetoRecibido->idCargo);
        $establecimientoId = ($objetoRecibido->establecimientoId == 0 ? NULL : $objetoRecibido->establecimientoId);

        if(!is_null($identificacion))
        {
            if ($this->usuario->where([
                ['identificacion', '=', $identificacion],
                ['id', '!=', $idUsuario],
                ['cuenta_principal_id', '==', auth()->user()->cuenta_principal_id]
            ])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'La identificación usada ya existe')
                );
            }
        }
        

        if(!is_null($correo))
        {
            if ($this->usuario->where([
                ['correo', '=', $correo],
                ['id', '!=', $idUsuario],
                ['cuenta_principal_id', '!=', auth()->user()->cuenta_principal_id]
            ])->exists()) 
            {
                return $this->FinalizarRetorno(
                    406,
                    $this->MensajeRetorno('',406,'El correo electrónico usado ya existe')
                );
            }
        }
        
        
        $arrayUpdate = [
            'nombre_completo' => $nombres, 
            'identificacion' => $identificacion,
            'correo' => $correo,
            'telefono' => $telefono,
            'cargo_id' => $idCargo, 
            'perfil_id' => $perfilId,
            'establecimiento_id' => $establecimientoId,
            // 'usuario' => $usuarioName,
            'cuenta_principal_id' => auth()->user()->cuenta_principal_id
        ];

        if(!is_null($logoImagen))
        {
            $arrayUpdate['url_imagen'] = $nombreImagen;
            $usuarioActualizado = $this->FuncionTraerUsuarioPorId($idUsuario);
            if(\File::exists($this->urlImagenesAvatar.$usuarioActualizado->url_imagen)) 
                \File::delete($this->urlImagenesAvatar.$usuarioActualizado->url_imagen);
        }

        if($password != '')
        {
            $arrayUpdate['password'] = bcrypt($password);
            $arrayUpdate['password_visible'] = $password_visible;
        }
            
        
        $respuestaUpdate = $this->usuario->where('id','=',$idUsuario)
        ->update($arrayUpdate);

        if($respuestaUpdate)
        {
            $usuarioActualizado = $this->FuncionTraerUsuarioPorId($idUsuario);
            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('El usuario ',201),
                $usuarioActualizado
            );
        }
    }

    public function ActualizarEstadoUsuario(Request $request)
    {
        $idUsuario = $request->get('idUsuario');
        $estadoActual = $request->get('estadoActual');

        $estadoCambiado = 0;
        if($estadoActual == 0)
            $estadoCambiado = 1;
        else if($estadoActual == 1)
            $estadoCambiado = 0;
        
        $respuestaUpdate = $this->usuario->where('id','=',$idUsuario)
        ->update(
        [
            'estado' => $estadoCambiado
        ]);

        if($respuestaUpdate)
        {
            return $this->FinalizarRetorno(
                201,
                $this->MensajeRetorno('El usuario ',201),
                $estadoCambiado
            );
        }
    }

    public function TraerUsuariosPaginacion(Request $request)
    {
        $idCuentaPrincipal = $request->get('idCuentaPrincipal');
        $paginacion = $request->get('paginacion');
        $filtros = json_decode($request->get('arrayFiltros'));

        $usuarios = $this->FuncionTraerUsuariosPorPaginacion($idCuentaPrincipal,$paginacion,$filtros);

        return $this->FinalizarRetorno(
            202,
            $this->MensajeRetorno('Datos',202),
            $usuarios
        );
    }
    
    public function FuncionTraerUsuarioPorId($idUsuario)
    {
        $usuario = $this->usuario->select(
            'usuario.id',
            'usuario.nombre_completo',
            \DB::raw('IF(usuario.identificacion IS NULL,"Sin identificación",usuario.identificacion) AS identificacion'),
            \DB::raw('IF(usuario.correo IS NULL,"Sin correo electrónico",usuario.correo) AS correo'),
            \DB::raw('IF(usuario.telefono IS NULL,"Sin teléfono",usuario.telefono) AS TELEFONO'),
            \DB::raw('IF(cg.nombre IS NULL,"Sin cargo",cg.nombre) AS CARGO'),
            'pe.nombre AS PERFIL',
            \DB::raw('IF(em.nombre IS NULL,"Sin empresa",em.nombre) AS EMPRESA'),
            \DB::raw('IF(e.nombre IS NULL,"Sin establecimiento",e.nombre) AS ESTABLECIMIENTO'),
            'usuario.estado',
            \DB::raw('IF(c.nombre IS NULL,"Sin ciudad",CONCAT(c.nombre,", ",p.nombre)) AS CIUDAD'),
            \DB::raw('IF(usuario.url_imagen IS NULL,"/vertical/assets/images/users/circle_logo_audiid.png",CONCAT("/imagenes/usuarios/",usuario.url_imagen)) AS FOTO'),
            'usuario.usuario AS USUARIO',
            'usuario.password_visible AS PASSWORD',
            \DB::raw('IF((SELECT COUNT(*) FROM empresa ems WHERE ems.usuario_id=usuario.id) != 0,(SELECT ems.nombre FROM empresa ems WHERE ems.usuario_id=usuario.id),"") AS ES_RESPONSABLE_EMPRESA'),
            \DB::raw('IF((SELECT COUNT(*) FROM establecimiento ests WHERE ests.usuario_id=usuario.id) != 0,(SELECT ests.nombre FROM establecimiento ests WHERE ests.usuario_id=usuario.id),"") AS ES_RESPONSABLE_ESTABLECIMIENTO')
        )
        ->leftJoin('establecimiento AS e','e.id','=','usuario.establecimiento_id')
        ->leftJoin('ciudad AS c','c.id','=','e.ciudad_id')
        ->leftJoin('departamento AS d','d.id','=','c.departamento_id')
        ->leftJoin('pais AS p','p.id','=','d.pais_id')
        ->leftJoin('cargo AS cg','cg.id','=','usuario.cargo_id')
        ->Join('perfil AS pe','pe.id','=','usuario.perfil_id')
        ->leftJoin('empresa AS em','em.id','=','e.empresa_id')
        ->where('usuario.id','=',$idUsuario)->first();
        
        return $usuario;
    }

    public function FuncionTraerUsuariosPorPaginacion($idCuentaPrincipal,$paginacion=1,$filtros=[])
    {
        $resultadoLimit = $this->CalculoPaginacion($paginacion);
    
        $desde = $resultadoLimit['desde'];
        $hasta = $resultadoLimit['hasta'];
        $cantidadRegistros = 9;
        $filtro_array = [];

        foreach ($filtros as $key => $filtro) 
        {

            switch ($key) {
                case 'filtro_nombre_usuario':
                    if($filtro != '')
                        array_push($filtro_array,['usuario.usuario', '=', $filtro]);
                    break;

                case 'filtro_correo':
                    if($filtro != '')
                        array_push($filtro_array,['usuario.correo', '=', $filtro]);
                    break;

                case 'filtro_cargo':
                    if($filtro != '')
                        array_push($filtro_array,['c.nombre', '=', $filtro]);
                    break;

                case 'filtro_empresa':
                    if($filtro != '')
                        array_push($filtro_array,['em.id', '=', $filtro]);
                    break;

                default:
                    
                    break;
            }
            
        }

        $usuarios = $this->usuario->select(
            'usuario.id',
            'usuario.nombre_completo',
            \DB::raw('IF(usuario.identificacion IS NULL,"Sin identificación",usuario.identificacion) AS identificacion'),
            \DB::raw('IF(usuario.correo IS NULL,"Sin correo electrónico",usuario.correo) AS correo'),
            \DB::raw('IF(usuario.telefono IS NULL,"Sin teléfono",usuario.telefono) AS TELEFONO'),
            \DB::raw('IF(cg.nombre IS NULL,"Sin cargo",cg.nombre) AS CARGO'),
            'pe.nombre AS PERFIL',
            \DB::raw('IF(em.nombre IS NULL,"Sin empresa",em.nombre) AS EMPRESA'),
            \DB::raw('IF(e.nombre IS NULL,"Sin establecimiento",e.nombre) AS ESTABLECIMIENTO'),
            'usuario.estado',
            \DB::raw('IF(c.nombre IS NULL,"Sin ciudad",CONCAT(c.nombre,", ",p.nombre)) AS CIUDAD'),
            \DB::raw('IF(usuario.url_imagen IS NULL,"/vertical/assets/images/users/circle_logo_audiid.png",CONCAT("/imagenes/usuarios/",usuario.url_imagen)) AS FOTO'),
            \DB::raw('IF(usuario.usuario IS NULL,"Sin usuario",usuario.usuario) AS USUARIO'),
            'usuario.password_visible AS PASSWORD',
            \DB::raw('IF((SELECT COUNT(*) FROM empresa ems WHERE ems.usuario_id=usuario.id) != 0,(SELECT ems.nombre FROM empresa ems WHERE ems.usuario_id=usuario.id),"") AS ES_RESPONSABLE_EMPRESA'),
            \DB::raw('IF((SELECT COUNT(*) FROM establecimiento ests WHERE ests.usuario_id=usuario.id) != 0,(SELECT ests.nombre FROM establecimiento ests WHERE ests.usuario_id=usuario.id),"") AS ES_RESPONSABLE_ESTABLECIMIENTO')
        )
        ->leftJoin('establecimiento AS e','e.id','=','usuario.establecimiento_id')
        ->leftJoin('ciudad AS c','c.id','=','e.ciudad_id')
        ->leftJoin('departamento AS d','d.id','=','c.departamento_id')
        ->leftJoin('pais AS p','p.id','=','d.pais_id')
        ->leftJoin('cargo AS cg','cg.id','=','usuario.cargo_id')
        ->Join('perfil AS pe','pe.id','=','usuario.perfil_id')
        ->leftJoin('empresa AS em','em.id','=','e.empresa_id');

        switch (auth()->user()->perfil_id) {
            case 1: // ADMINISTRADOR
                $usuarios = $usuarios->where([
                    ['usuario.cuenta_principal_id','=',$idCuentaPrincipal]
                ]);
                break;

            case 2: // COLABORADOR
                //VERIFICAR SI ES RESPONSABLE DE EMPRESA
                $esResponsableEmpresa = $this->FuncionParaSaberSiEsResponsableEmpresa(auth()->user()->id);

                if(!is_null($esResponsableEmpresa))
                    $usuarios = $usuarios->where([
                        ['em.id','=',$esResponsableEmpresa->id],
                        ['usuario.perfil_id','!=', 1]
                    ]);

                //VERIFICAR SI ES RESPONSABLE DE ESTABLECIMIENTO
                $esResponsableEstablecimiento = $this->FuncionParaSaberSiEsResponsableEstablecimiento(auth()->user()->id);

                if(!is_null($esResponsableEstablecimiento))
                {
                    $usuarios = $usuarios->where([
                        ['e.id','=',$esResponsableEstablecimiento->id],
                        ['usuario.perfil_id','!=', 1]
                    ]);
                }
                

                if(is_null($esResponsableEmpresa) && is_null($esResponsableEstablecimiento))
                {
                    $usuarios = $usuarios->where([
                        ['usuario.id','=',auth()->user()->id]
                    ]);
                }
                    

                break;
            
            default:

                break;
        };

        if(COUNT($filtro_array) != 0)
        {
            $usuarios = $usuarios->where(function($query) use ($filtro_array)
            {
                foreach ($filtro_array as $keys => $oW) 
                {
                    $query->where($oW[0], '=', $oW[2]);
                }

                return $query;
            });
            
        }

        $usuarios = $usuarios->skip($desde)->take($hasta)->get();


        return $usuarios;
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

    public function FuncionValidarSiPuedeCrearUsuarios()
    {

        $puedeEjecutar = \DB::select("SELECT
        (CASE
            WHEN pl.id=1 THEN '1'
            WHEN pl.id=2 THEN '2'
            WHEN pl.id=3 THEN '3'
            ELSE 'contacto'
        END) AS plan,
        COUNT(*) AS cta_usuarios_creados,
        (SELECT spp.valor FROM plan_parametros spp
        INNER JOIN plan spl ON spl.id= spp.plan_id
        WHERE spp.id=
        (CASE
            WHEN pl.id=1 THEN 3
            WHEN pl.id=2 THEN 8
            WHEN pl.id=3 THEN 13
            WHEN pl.id=4 THEN 18
            ELSE 'contacto'
        END) AND spl.id=pl.id) AS plan_usuarios,
        (
        IF (COUNT(*)<(SELECT spp.valor FROM plan_parametros spp
        INNER JOIN plan spl ON spl.id= spp.plan_id
        WHERE spp.id=
        (CASE
            WHEN pl.id=1 THEN 3
            WHEN pl.id=2 THEN 8
            WHEN pl.id=3 THEN 13
            WHEN pl.id=4 THEN 18
            ELSE 'contacto'
        END) AND spl.id=pl.id),'SI','NO')
        ) AS puede_crear
        FROM usuario us
        INNER JOIN cuenta_principal cp ON cp.id=us.cuenta_principal_id
        INNER JOIN plan pl ON pl.id=cp.plan_id
        WHERE us.cuenta_principal_id=:idCuentaPrincipal;",['idCuentaPrincipal' => auth()->user()->cuenta_principal_id]);

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
