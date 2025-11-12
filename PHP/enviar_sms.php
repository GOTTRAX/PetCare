<?php
function enviarSMS($numero, $mensagem) {
    $url = 'https://api.exemplo.com/send';
    $token = 'SEU_TOKEN_AQUI';

    $dados = [
        'phone' => $numero,
        'message' => $mensagem
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . 'Bearer ' . $token
    ]);

    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($erro) {
        return "Erro: " . $erro;
    } else {
        return $resposta;
    }
}
?>
