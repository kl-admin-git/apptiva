<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Finalilzación de lista de chequeo</title>
</head>

<body>
    <div class="page-content-wrapper">
        <div class="container-fluid">
            <div class="row text-center" style="text-align:center;just-content:center;">
                <div class="col-12">
                    <table class="body-wrap"
                        style="text-align:center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; width: 100%; background-color: #f6f6f6; margin: 0;"
                        bgcolor="#f6f6f6">
                        <tr
                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                            <td style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0;"
                                valign="top"></td>
                            <td class="container"
                                style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; display: block !important; max-width: 600px !important; clear: both !important; margin: 0 auto;"
                                valign="top">
                                <div class="content"
                                    style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; max-width: 600px; display: block; margin: 0 auto; padding: 20px;">
                                    <table class="main" width="100%" cellpadding="0" cellspacing="0"
                                        style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; border-radius: 3px; background-color: #fff; margin: 0; border: 1px solid #e9e9e9;"
                                        bgcolor="#fff">
                                        <tr
                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif;text-align:center; box-sizing: border-box; font-size: 14px; margin: 0;">
                                            <td class="alert alert-warning"
                                                style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; color: #fff; font-weight: 500; text-align:center;  border-radius: 3px 3px 0 0; background-color: #FFF; margin: 0; padding: 20px;"
                                                align="center" bgcolor="#71b6f9" valign="top">
                                                <img src="{{ URL::asset('/vertical/assets/images/logo_new_2023.png') }}"
                                                    height="70">
                                            </td>
                                        </tr>
                                        <tr
                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                            <td class="content-wrap"
                                                style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 20px;"
                                                valign="top">
                                                <table width="100%" cellpadding="0" cellspacing="0"
                                                    style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                    <tr
                                                        style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                        <td class="content-block"
                                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                                                            valign="top">
                                                            <h3 style="color:#F3A03F">Lista de chequeo terminada</h3>
                                                        </td>
                                                    </tr>
                                                    <tr
                                                        style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                        <td class="content-block"
                                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                                                            valign="top">
                                                            <p>Hola {{ $usuarioNombre }},</p>
                                                        </td>
                                                    </tr>
                                                    <tr
                                                        style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                        <td class="content-block"
                                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                                                            valign="top">
                                                            <p>
                                                                Se ha completado la lista de chequeo de
                                                                <b>{{ $nombre }}</b>, en la cual se identificaron
                                                                las siguientes respuestas
                                                                que no cumplen con los valores mínimos y máximos
                                                                establecidos para cada pregunta:
                                                            </p>
                                                        </td>
                                                    </tr>
                                                    @foreach ($data as $item)
                                                        <tr>
                                                            <td style="padding:10px 0;">

                                                                <div
                                                                    style="
            border:1px solid #ddd;
            border-radius:8px;
            font-family:Arial, sans-serif;
            background:#fafafa;
            padding:12px;
        ">

                                                                    <div style="font-size:14px; margin-bottom:6px;">
                                                                        <strong>Categoría:</strong>
                                                                        {{ $item->categoria }}
                                                                    </div>

                                                                    <div style="font-size:14px; margin-bottom:6px;">
                                                                        <strong>Pregunta:</strong> {{ $item->pregunta }}
                                                                    </div>

                                                                    <div style="font-size:14px; margin-bottom:6px;">
                                                                        <strong>Rango:</strong> {{ $item->valor_min }} -
                                                                        {{ $item->valor_max }}
                                                                    </div>

                                                                    <div
                                                                        style="font-size:14px; padding:6px; border-radius:4px;">
                                                                        <strong>Respuesta:</strong>
                                                                        {{ $item->respuesta }}
                                                                    </div>

                                                                </div>

                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    <tr
                                                        style="text-align:center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                        <td class="content-block"
                                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                                                            valign="top">
                                                            <p> </p>
                                                        </td>
                                                    </tr>

                                                    <tr
                                                        style="text-align:center;font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                        <td class="content-block"
                                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;"
                                                            valign="top">
                                                            <p><b>Apptiva</b></p>
                                                        </td>
                                                    </tr>

                                                    <tr
                                                        style=" text-align:center; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                                        <td class="content-block"
                                                            style="font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0;"
                                                            valign="top">
                                                            <p>© {{ date('Y') }} Apptiva <i class="mdi mdi-heart"
                                                                    style="color:#F3A03F"></i></p>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div><!-- container -->
    </div> <!-- Page content Wrapper -->
</body>

</html>
