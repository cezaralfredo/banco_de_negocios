<?php
declare(strict_types=1);

/* ==========================================================================
   Envio de e-mails via SMTP (sem bibliotecas externas)
   Configuração em Admin › Configurações globais › E-mail (SMTP).
   Sem SMTP configurado, usa a função mail() do PHP.
   ========================================================================== */

function mail_settings(): array
{
    global $pdo;
    return [
        'host' => setting($pdo, 'smtp_host', ''),
        'port' => (int)setting($pdo, 'smtp_porta', '587'),
        'secure' => setting($pdo, 'smtp_seguranca', 'tls'), // tls (STARTTLS), ssl, none
        'user' => setting($pdo, 'smtp_usuario', ''),
        'pass' => secret_setting('smtp_senha'),
        'from' => setting($pdo, 'smtp_remetente', '') ?: setting($pdo, 'footer_email', 'no-reply@localhost'),
        'from_name' => setting($pdo, 'smtp_remetente_nome', '') ?: setting($pdo, 'site_name', 'CMS'),
    ];
}

/** Modelo HTML simples com a identidade do cliente. */
function mail_template(string $title, string $bodyHtml, string $buttonUrl = '', string $buttonText = ''): string
{
    global $pdo;
    $color = theme_var('cor_primaria', '#9f1549');
    $site = e(setting($pdo, 'site_name', 'CMS'));
    $btn = $buttonUrl ? '<p style="margin:24px 0"><a href="' . e($buttonUrl) . '" style="background:' . e($color) . ';color:#fff;padding:12px 22px;border-radius:24px;text-decoration:none;font-weight:bold">' . e($buttonText ?: 'Abrir') . '</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#f4f5f7;font-family:Arial,sans-serif;color:#14243b"><div style="max-width:560px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden"><div style="background:' . e($color) . ';color:#fff;padding:18px 24px;font-weight:bold">' . $site . '</div><div style="padding:24px"><h2 style="margin-top:0">' . e($title) . '</h2>' . $bodyHtml . $btn . '</div><div style="padding:14px 24px;font-size:12px;color:#7a8597;border-top:1px solid #eee">Mensagem automática de ' . $site . '.</div></div></body></html>';
}

/** Envia um e-mail. Retorna [ok, mensagem]. */
function send_mail(string $to, string $subject, string $html, ?array $cfg = null): array
{
    $cfg = $cfg ?? mail_settings();
    $mail = apply_filters('mail_before_send', ['to' => $to, 'subject' => $subject, 'html' => $html]);
    if (empty($mail['to']) || !filter_var($mail['to'], FILTER_VALIDATE_EMAIL)) return [false, 'Destinatário inválido.'];
    $text = trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/h\d|/div)>#i', "\n", $mail['html'])), ENT_QUOTES, 'UTF-8'));
    $boundary = 'b' . bin2hex(random_bytes(8));
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $enc($cfg['from_name']) . ' <' . $cfg['from'] . '>',
        'To: <' . $mail['to'] . '>',
        'Subject: ' . $enc($mail['subject']),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $cfg['from'])[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($mail['html'])) . "--$boundary--\r\n";

    if ($cfg['host'] === '') {
        // mail() já adiciona To: e Subject:
        $extra = array_filter($headers, fn($h) => !str_starts_with($h, 'To:') && !str_starts_with($h, 'Subject:') && !str_starts_with($h, 'Date:'));
        $ok = @mail($mail['to'], $enc($mail['subject']), $body, implode("\r\n", $extra));
        mail_log($mail['to'], $mail['subject'], $ok ? 'enviado (mail)' : 'falhou (mail)');
        return [$ok, $ok ? 'Enviado pela função mail() do servidor.' : 'O servidor não conseguiu enviar (configure o SMTP).'];
    }
    try {
        smtp_send($cfg, $mail['to'], implode("\r\n", $headers) . "\r\n\r\n" . $body);
        mail_log($mail['to'], $mail['subject'], 'enviado');
        return [true, 'E-mail enviado.'];
    } catch (Throwable $e) {
        mail_log($mail['to'], $mail['subject'], 'erro: ' . $e->getMessage());
        return [false, 'Falha no SMTP: ' . $e->getMessage()];
    }
}

function smtp_send(array $c, string $to, string $data): void
{
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("não conectou em {$c['host']}:{$c['port']} ($errstr)");
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $command, array $expect) use ($fp, $read): string {
        if ($command !== '') fwrite($fp, $command . "\r\n");
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $expect, true)) throw new RuntimeException(trim($resp) ?: 'sem resposta do servidor');
        return $resp;
    };
    $cmd('', [220]);
    $helo = gethostname() ?: 'localhost';
    $ehlo = $cmd("EHLO $helo", [250]);
    if ($c['secure'] === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('falha ao iniciar TLS');
        $ehlo = $cmd("EHLO $helo", [250]);
    }
    if ($c['user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
    }
    $cmd('MAIL FROM:<' . $c['from'] . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
    $cmd($data . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}

function mail_log(string $to, string $subject, string $status): void
{
    @file_put_contents(storage_path('logs') . '/email.log', date('c') . " | $status | $to | $subject\n", FILE_APPEND | LOCK_EX);
}
