<?php

namespace Models;

/**
 * Minimal pure-PHP SMTP mailer. No dependencies.
 * Supports SSL (port 465) and STARTTLS (port 587).
 */
class Mailer
{
    public function __construct(
        private string $host,
        private int    $port,
        private string $user,
        private string $pass,
        private string $fromEmail,
        private string $fromName
    ) {}

    public function send(string $toEmail, string $subject, string $htmlBody): bool
    {
        $useSsl = ($this->port === 465);
        $scheme = $useSsl ? 'ssl' : 'tcp';

        $ctx  = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);

        $sock = @stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$sock) {
            error_log("[Mailer] Connect failed ({$this->host}:{$this->port}): {$errstr}");
            return false;
        }

        stream_set_timeout($sock, 20);

        try {
            $this->read($sock);                           // 220 greeting
            $this->write($sock, 'EHLO localhost');
            $this->read($sock);

            if (!$useSsl) {                               // STARTTLS upgrade
                $this->write($sock, 'STARTTLS');
                $this->read($sock);
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->write($sock, 'EHLO localhost');
                $this->read($sock);
            }

            $this->write($sock, 'AUTH LOGIN');
            $this->read($sock);
            $this->write($sock, base64_encode($this->user));
            $this->read($sock);
            $this->write($sock, base64_encode($this->pass));
            $auth = $this->read($sock);

            if (substr($auth, 0, 3) !== '235') {
                throw new \RuntimeException("SMTP AUTH failed: {$auth}");
            }

            $this->write($sock, "MAIL FROM:<{$this->fromEmail}>");
            $this->read($sock);
            $this->write($sock, "RCPT TO:<{$toEmail}>");
            $this->read($sock);
            $this->write($sock, 'DATA');
            $this->read($sock);

            $enc     = '=?UTF-8?B?' . base64_encode($subject)       . '?=';
            $from    = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
            $message = "From: {$from} <{$this->fromEmail}>\r\n"
                     . "To: <{$toEmail}>\r\n"
                     . "Subject: {$enc}\r\n"
                     . "MIME-Version: 1.0\r\n"
                     . "Content-Type: text/html; charset=UTF-8\r\n"
                     . "Content-Transfer-Encoding: base64\r\n"
                     . "\r\n"
                     . chunk_split(base64_encode($htmlBody))
                     . "\r\n.\r\n";

            fwrite($sock, $message);
            $resp = $this->read($sock);
            $this->write($sock, 'QUIT');
            fclose($sock);

            return substr($resp, 0, 3) === '250';

        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            @fclose($sock);
            return false;
        }
    }

    private function write($sock, string $line): void
    {
        fwrite($sock, $line . "\r\n");
    }

    private function read($sock): string
    {
        $out = '';
        while ($line = fgets($sock, 512)) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return trim($out);
    }
}
