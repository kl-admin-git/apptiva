<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MailRangoListaChequeo extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $listaDeChequeo, $data, $nombre, $cantidadPlanAccion, $usuarioNombre, $idListaChequeo, $resultado;
    public function __construct($data, $listaDeChequeo)
    {
        $this->listaDeChequeo = $listaDeChequeo;
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        $nombreResultado = $this->NombreResultadoFinalAuditoriaEjecucion($this->listaDeChequeo->id);
        $cantidadPlanAccion = $this->CantidadPlanAccionEjecutada($this->listaDeChequeo->id);
        $usuarioNombre = $this->listaDeChequeo->NOMBRE_USUARIO;

        $resultado = 0;
        foreach ($nombreResultado as $key => $item) {
            $this->nombre = $item->nombre;
            $resultado = $item->res_final;
        }

        $this->resultado = $resultado;
        $this->cantidadPlanAccion = $cantidadPlanAccion;
        $this->usuarioNombre = $usuarioNombre;
        $this->idListaChequeo = $this->listaDeChequeo->id;

        $address = 'audiid@audiid.co';
        $name = "Audiid";
        return $this
            ->from($address, $name)
            ->subject('Rangos no permitidos en lista de chequeo')
            ->view('Mails.rango_lista_chequeo');
    }

    public function NombreResultadoFinalAuditoriaEjecucion($idEjecutada)
    {
        $retorno = DB::select('SELECT
        lc.nombre,
        SUM(IF((TRUNCATE(((pre.ponderado*res.ponderado)/100),2)) IS NULL,pre.ponderado, (TRUNCATE(((pre.ponderado*res.ponderado)/100),2)))) AS res_final
        FROM lista_chequeo_ejec_respuestas lcer
        INNER JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        INNER JOIN lista_chequeo lc ON lc.id=lce.lista_chequeo_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        INNER JOIN pregunta pre ON pre.id=lcer.pregunta_id
        INNER JOIN categoria cat ON cat.id=pre.categoria_id
        WHERE  lcer.lista_chequeo_ejec_id=:idEjecutada
        ORDER BY cat.id;', ['idEjecutada' => $idEjecutada]);

        return $retorno;
    }

    public function CantidadPlanAccionEjecutada($idEjecutada)
    {
        $retorno = DB::select(DB::raw("SELECT 
        pre.nombre as pregunta,
        IF(res.valor_personalizado IS NULL, 'No aplica',res.valor_personalizado) as respuesta 
        -- paa.plan_accion_descripcion as plan_accion
        FROM lista_chequeo_ejec_respuestas lcer
        LEFT JOIN lista_chequeo_ejecutadas lce ON lce.id=lcer.lista_chequeo_ejec_id
        LEFT JOIN categoria cat ON cat.id=lcer.categoria_id
        LEFT JOIN respuesta res ON res.id=lcer.respuesta_id
        LEFT JOIN pregunta pre ON pre.id=lcer.pregunta_id
        LEFT JOIN plan_accion pa ON pa.respuesta_id=lcer.respuesta_id
        -- INNER JOIN plan_accion_automatico paa ON paa.plan_accion_id=pa.id
        WHERE lce.id=:idEjecutada
        GROUP BY cat.id, pre.id"), ['idEjecutada' => $idEjecutada]);

        return COUNT($retorno);
    }
}
