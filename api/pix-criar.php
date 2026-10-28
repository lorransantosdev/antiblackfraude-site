<?php
// POST api/pix-criar  { anuncio, contato, valor } -> QR Code do Pix (AbacatePay)
require __DIR__ . '/_abacatepay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['erros' => ['Use POST.']], 405);

[$pedido, $erros] = validar_pedido(json_decode(file_get_contents('php://input') ?: 'null', true));
if ($erros) responder(['erros' => $erros], 400);

$a = $pedido['anuncio'];
$cobranca = abacatepay('POST', '/transparents/create', [
    'method' => 'PIX',
    'data' => [
        'amount' => $pedido['valor'],
        'description' => mb_substr("Oferta Verificada · {$a['marca']} · {$a['produto']} · peso {$a['peso_rotacao']}×", 0, 500),
        'expiresIn' => 3600,
        'externalId' => mb_substr(($a['id'] ?? 'anuncio') . '-' . time(), 0, 120),
        'metadata' => [
            'tipo' => 'oferta_verificada',
            'marca' => $a['marca'],
            'produto' => $a['produto'],
            'peso' => (string) $a['peso_rotacao'],
            'contato_email' => $pedido['contato']['email'],
            'contato_whatsapp' => $pedido['contato']['whatsapp'],
            'historico_link' => $a['historico_link'] ?? '',
            'anuncio' => json_encode($a, JSON_UNESCAPED_UNICODE),
        ],
    ],
]);

responder([
    'id' => $cobranca['id'],
    'valor' => $cobranca['amount'],
    'brCode' => $cobranca['brCode'],
    'brCodeBase64' => $cobranca['brCodeBase64'],
    'expiraEm' => $cobranca['expiresAt'],
    'teste' => !empty($cobranca['devMode']),
]);
