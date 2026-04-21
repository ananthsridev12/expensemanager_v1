<?php

namespace Models;

/**
 * Minimal pure-PHP SMTP mailer. No dependencies.
 * Supports SSL (port 465) and STARTTLS (port 587).
 */
class Mailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;
    private string $lastError = '';

    public function __construct(
        string $host,
        int    $port,
        string $user,
        string $pass,
        string $fromEmail,
        string $fromName
    ) {
        $this->host      = $host;
        $this->port      = $port;
        $this->user      = $user;
        $this->pass      = $pass;
        $this->fromEmail = $fromEmail;
        $this->fromName  = $fromName;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function send(string $toEmail, string $subject, string $htmlBody): bool
    {
        $this->lastError = '';
        $useSsl  = ($this->port === 465);
        $scheme  = $useSsl ? 'ssl' : 'tcp';

        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);

        $sock = @stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$sock) {
            $this->lastError = "Cannot connect to {$this->host}:{$this->port} — {$errstr} (#{$errno}). Check SMTP host and port.";
            error_log('[Mailer] ' . $this->lastError);
            return false;
        }

        stream_set_timeout($sock, 20);

        try {
            $greeting = $this->read($sock);
            if (substr($greeting, 0, 3) !== '220') {
                throw new \RuntimeException("Unexpected greeting: {$greeting}");
            }

            $this->write($sock, "EHLO {$this->host}");
            $ehlo = $this->read($sock);
            if (substr($ehlo, 0, 3) !== '250') {
                throw new \RuntimeException("EHLO rejected: {$ehlo}");
            }

            if (!$useSsl) {
                $this->write($sock, 'STARTTLS');
                $stls = $this->read($sock);
                if (substr($stls, 0, 3) !== '220') {
                    throw new \RuntimeException("STARTTLS not accepted: {$stls}");
                }
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException("TLS handshake failed. Try port 465 (SSL) instead of 587.");
                }
                $this->write($sock, "EHLO {$this->host}");
                $this->read($sock);
            }

            $this->write($sock, 'AUTH LOGIN');
            $this->read($sock);
            $this->write($sock, base64_encode($this->user));
            $this->read($sock);
            $this->write($sock, base64_encode($this->pass));
            $auth = $this->read($sock);
            if (substr($auth, 0, 3) !== '235') {
                throw new \RuntimeException("Authentication failed — wrong username or password. Server said: {$auth}");
            }

            $this->write($sock, "MAIL FROM:<{$this->fromEmail}>");
            $mf = $this->read($sock);
            if (substr($mf, 0, 3) !== '250') {
                throw new \RuntimeException("MAIL FROM rejected — smtp_from must match smtp_user on cPanel. Server said: {$mf}");
            }

            $this->write($sock, "RCPT TO:<{$toEmail}>");
            $rt = $this->read($sock);
            if (substr($rt, 0, 1) !== '2') {
                throw new \RuntimeException("Recipient rejected: {$rt}");
            }

            $this->write($sock, 'DATA');
            $this->read($sock);

            $encSubject = '=?UTF-8?B?' . base64_encode($subject)       . '?=';
            $encFrom    = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
            $message    = "From: {$encFrom} <{$this->fromEmail}>\r\n"
                        . "To: <{$toEmail}>\r\n"
                        . "Subject: {$encSubject}\r\n"
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

            if (substr($resp, 0, 3) !== '250') {
                $this->lastError = "Server rejected message after DATA: {$resp}";
                error_log('[Mailer] ' . $this->lastError);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
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
        while ($line = fgets($sock, 1024)) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return trim($out);
    }
}
