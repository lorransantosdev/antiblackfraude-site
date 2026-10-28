<?php
// GET api/pix-status?id=pix_char_... -> { status }
require __DIR__ . '/_abacatepay.php';

$id = (string) ($_GET['id'] ?? '');
if (!preg_match('/^pix_char_[A-Za-z0-9]{6,64}$/', $id)) responder(['erros' => ['Cobrança inválida.']], 400);

$dados = abacatepay('GET', '/transparents/check?id=' . urlencode($id));
responder(['status' => $dados['status'] ?? 'PENDING', 'expiraEm' => $dados['expiresAt'] ?? null]);
