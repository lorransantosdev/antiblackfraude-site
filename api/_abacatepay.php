<?php
// Cliente mínimo da AbacatePay (API v2) pra HostGator. Mesmas regras de src/game/anuncio.ts.
// A chave vem da variável de ambiente ABACATEPAY_API_KEY ou de api/config.php (criado à mão no cPanel).

const ABACATE_BASE = 'https://api.abacatepay.com/v2';
const ANUNCIO_MINIMO = 9700;            // R$ 97,00
const ANUNCIO_MAXIMO = ANUNCIO_MINIMO * 100;

function responder($dados, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function chave_abacatepay(): string {
    $chave = getenv('ABACATEPAY_API_KEY') ?: '';
    if (!$chave && is_file(__DIR__ . '/config.php')) {
        $config = require __DIR__ . '/config.php';
        $chave = is_array($config) ? ($config['ABACATEPAY_API_KEY'] ?? '') : '';
    }
    return $chave;
}

function abacatepay(string $metodo, string $caminho, ?array $corpo = null): array {
    $chave = chave_abacatepay();
    if (!$chave) responder(['erros' => ['Pagamento ainda não configurado.']], 503);
    $ch = curl_init(ABACATE_BASE . $caminho);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $chave, 'Content-Type: application/json'],
    ]);
    if ($corpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
    $resposta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($resposta ?: 'null', true);
    if ($codigo >= 400 || !is_array($json) || empty($json['success']) || empty($json['data'])) {
        responder(['erros' => [is_array($json) && !empty($json['error']) ? $json['error'] : 'Não deu pra falar com o banco agora.']], 502);
    }
    return $json['data'];
}

function texto($v, int $max): string {
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function link_https(string $v): bool {
    return (bool) preg_match('#^https://[^\s/.]+\.[^\s]+$#', $v);
}

function peso_do_valor(int $centavos): float {
    return max(1, floor(($centavos / ANUNCIO_MINIMO) * 100) / 100);
}

/** Mesma validação de validarPedido() (src/game/anuncio.ts). */
function validar_pedido($dados): array {
    if (!is_array($dados)) return [null, ['Pedido vazio.']];
    $a = is_array($dados['anuncio'] ?? null) ? $dados['anuncio'] : [];
    $c = is_array($dados['contato'] ?? null) ? $dados['contato'] : [];
    $valor = $dados['valor'] ?? null;
    $erros = [];

    if (!texto($a['produto'] ?? '', 60)) $erros[] = 'Coloque o nome do produto.';
    if (!texto($a['marca'] ?? '', 40)) $erros[] = 'Coloque o nome da marca ou loja.';
    if (!link_https(texto($a['url'] ?? '', 500))) $erros[] = 'O link da oferta tem que começar com https://';
    $oferta = is_numeric($a['preco_oferta'] ?? null) ? (float) $a['preco_oferta'] : 0;
    $menor = is_numeric($a['menor_preco_60d'] ?? null) ? (float) $a['menor_preco_60d'] : 0;
    if ($oferta <= 0) $erros[] = 'Coloque o preço da oferta.';
    if ($menor <= 0) $erros[] = 'Coloque o menor preço dos últimos 60 dias.';
    if ($oferta > 0 && $menor > 0 && $oferta >= $menor) $erros[] = 'A oferta tem que ser menor que o menor preço dos últimos 60 dias.';
    $historico = texto($a['historico_link'] ?? '', 500);
    if ($historico && !link_https($historico)) $erros[] = 'O link do histórico tem que começar com https://';

    $email = texto($c['email'] ?? '', 120);
    $whats = preg_replace('/\D/', '', (string) ($c['whatsapp'] ?? ''));
    if (!$email && !$whats) $erros[] = 'Deixe um e-mail ou WhatsApp pra gente falar com você.';
    elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
    elseif ($whats && (strlen($whats) < 10 || strlen($whats) > 13)) $erros[] = 'WhatsApp com DDD, só números.';

    if (!is_int($valor) || $valor < ANUNCIO_MINIMO) $erros[] = 'O valor mínimo é R$ 97,00.';
    elseif ($valor > ANUNCIO_MAXIMO) $erros[] = 'Valor acima do limite. Fale com a gente.';

    if ($erros) return [null, $erros];
    $a['ativo'] = false;
    $a['peso_rotacao'] = peso_do_valor($valor);
    return [['anuncio' => $a, 'contato' => ['email' => $email, 'whatsapp' => $whats], 'valor' => $valor], []];
}
