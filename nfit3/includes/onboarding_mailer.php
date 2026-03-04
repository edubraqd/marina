<?php
// includes/onboarding_mailer.php

const OUTBOX_LOG = __DIR__ . '/../storage/outbox.log';
require_once __DIR__ . '/bootstrap.php';
if (!function_exists('user_store_find')) {
    require_once __DIR__ . '/user_store.php';
}

/**
 * Configuração do remetente (pode ser ajustada via variáveis de ambiente):
 * MAIL_FROM, MAIL_FROM_NAME, MAIL_REPLY_TO
 */
function onboard_mail_config(): array
{
    return [
        'from'      => getenv('MAIL_FROM') ?: 'naoresponda@nutremfit.com.br',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'NutremFit',
        'reply_to'  => getenv('MAIL_REPLY_TO') ?: 'suporte@nutremfit.com.br',
    ];
}

/**
 * Destinatário padrão das notificações internas.
 */
function admin_notification_recipient(): string
{
    $envRecipient = trim((string) getenv('ADMIN_NOTIFICATION_EMAIL'));
    if ($envRecipient !== '') {
        return $envRecipient;
    }

    return 'nutremfit@gmail.com';
}

/**
 * Branding padrão dos e-mails.
 *
 * @return array{base:string,logo:string,primary:string,dark:string,card:string}
 */
function nf_branding(): array
{
    $base = rtrim(function_exists('nf_base_url') ? nf_base_url() : (getenv('APP_URL') ?: 'https://nutremfit.com.br'), '/');
    return [
        'base'    => $base,
        'logo'    => $base . '/assets/img/logo.png',
        'primary' => '#ff6b35',
        'dark'    => '#0b0f1a',
        'card'    => '#0f1424',
    ];
}

/**
 * Envia e-mail em texto simples com log na pasta storage/outbox.log.
 *
 * @param array<int,string> $lines
 */
function nf_send_plain_email(string $to, string $subject, array $lines): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mailCfg = onboard_mail_config();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: %s', $mailCfg['reply_to']),
    ]);

    $body = implode(PHP_EOL, $lines);
    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($to, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $to,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $body),
        PHP_EOL
    );
    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);

    return $sent;
}

/**
 * Envia e-mail em HTML com CTA para o aluno acessar a plataforma.
 *
 * @param array<int,string> $lines Texto curto explicando a ação
 */
function nf_send_student_notification(string $to, string $subject, array $lines, string $ctaLabel, string $ctaUrl): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mailCfg = onboard_mail_config();
    $branding = nf_branding();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $boundary = md5((string) microtime(true));
    $listUnsub = sprintf('<mailto:%s>', $mailCfg['reply_to']);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: %s', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'List-Unsubscribe: ' . $listUnsub,
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $bodyText = implode(PHP_EOL, $lines) . PHP_EOL . $ctaLabel . ': ' . $ctaUrl;

    $itemsHtml = '';
    foreach ($lines as $line) {
        $itemsHtml .= '<p style="margin:0 0 10px;font-size:15px;color:rgba(233,236,243,0.9);">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head><meta charset="UTF-8"><title>{$subject}</title></head>
  <body style="margin:0;padding:0;background:{$branding['dark']};font-family:'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['dark']};padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="560" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['card']};border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:18px 22px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <table width="100%" cellspacing="0" cellpadding="0" role="presentation">
                  <tr>
                    <td style="text-align:left;">
                      <img src="{$branding['logo']}" alt="NutremFit" style="height:34px;vertical-align:middle;">
                    </td>
                    <td style="text-align:right;color:rgba(255,255,255,0.7);font-size:13px;">Atualização na sua conta</td>
                  </tr>
                </table>
                <h2 style="margin:8px 0 0;font-size:22px;color:#fff;">{$subject}</h2>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                {$itemsHtml}
                <div style="text-align:center;margin:18px 0;">
                  <a href="{$ctaUrl}" style="display:inline-block;padding:13px 26px;border-radius:14px;background:{$branding['primary']};color:#0b0f1a;text-decoration:none;font-weight:800;letter-spacing:0.2px;box-shadow:0 18px 45px rgba(255,107,53,0.35);">{$ctaLabel}</a>
                </div>
                <p style="margin:0;font-size:12px;color:rgba(233,236,243,0.65);">Se o botão não funcionar, copie e cole o link no navegador: {$ctaUrl}</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . "{$bodyText}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . "{$htmlBody}\r\n"
          . "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($to, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $to,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $bodyText),
        PHP_EOL
    );
    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);

    return $sent;
}

/**
 * Envia aviso interno para o responsável da operação.
 *
 * @param string              $subject
 * @param array<int,string>   $lines
 */
function send_admin_notification(string $subject, array $lines): void
{
    $to = admin_notification_recipient();
    if ($to === '') {
        return;
    }

    $mailCfg = onboard_mail_config();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $branding = nf_branding();
    $boundary = md5((string) microtime(true));
    $listUnsub = sprintf('<mailto:%s>', $mailCfg['reply_to']);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: NutremFit <%s>', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'List-Unsubscribe: ' . $listUnsub,
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $bodyText = implode(PHP_EOL, $lines);

    $itemsHtml = '';
    foreach ($lines as $line) {
        $itemsHtml .= '<li style="margin:0 0 6px;color:#e9ecf3;font-size:14px;">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head><meta charset="UTF-8"><title>{$subject}</title></head>
  <body style="margin:0;padding:0;background:{$branding['dark']};font-family:'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['dark']};padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="560" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['card']};border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:18px 22px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <table width="100%" cellspacing="0" cellpadding="0" role="presentation">
                  <tr>
                    <td style="text-align:left;">
                      <img src="{$branding['logo']}" alt="NutremFit" style="height:34px;vertical-align:middle;">
                    </td>
                    <td style="text-align:right;color:rgba(255,255,255,0.7);font-size:13px;">Aviso interno</td>
                  </tr>
                </table>
                <h2 style="margin:8px 0 0;font-size:22px;color:#fff;">{$subject}</h2>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                <ul style="padding-left:18px;margin:0 0 12px;">{$itemsHtml}</ul>
                <p style="margin:8px 0 0;font-size:12px;color:rgba(233,236,243,0.65);">NutremFit • Monitoramento automático</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . "{$bodyText}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . "{$htmlBody}\r\n"
          . "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($to, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $to,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $bodyText),
        PHP_EOL
    );

    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);
}

/**
 * Envia um lembrete de acesso imediato após o clique em "Finalizar pagamento".
 */
function send_access_link_email(string $email, string $name = ''): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $mailCfg = onboard_mail_config();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $subject = 'Continue seu acesso na NutremFit';
    $firstName = trim($name) !== '' ? explode(' ', trim($name))[0] : 'Olá';
    $link = function_exists('nf_url') ? nf_url('/area-login') : 'https://nutremfit.com.br/area-login';

    $bodyLines = [
        "Olá {$firstName}, verifique que já está na etapa final.",
        "Assim que for aprovado, liberamos seu acesso automaticamente.",
        "Se precisar recuperar ou ativar acesso agora, use: {$link}",
    ];
    $bodyText = implode(PHP_EOL, $bodyLines);

    $branding = nf_branding();
    $boundary = md5((string) microtime(true));
    $listUnsub = sprintf('<mailto:%s>', $mailCfg['reply_to']);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: NutremFit <%s>', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'List-Unsubscribe: ' . $listUnsub,
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head><meta charset="UTF-8"><title>{$subject}</title></head>
  <body style="margin:0;padding:0;background:{$branding['dark']};font-family:'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['dark']};padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="560" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['card']};border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:18px 22px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <img src="{$branding['logo']}" alt="NutremFit" style="height:34px;vertical-align:middle;">
                <p style="margin:6px 0 0;font-size:14px;color:#ffb47a;">Seu acesso está a caminho</p>
                <h2 style="margin:4px 0 0;font-size:22px;color:#fff;">{$firstName}, siga para o painel</h2>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                <p style="margin:0 0 12px;font-size:15px;color:rgba(233,236,243,0.9);">Assim que o pagamento for aprovado, liberamos automaticamente seu acesso.</p>
                <p style="margin:0 0 16px;font-size:15px;color:rgba(233,236,243,0.8);">Se precisar resgatar ou ativar agora, use o botão abaixo:</p>
                <div style="text-align:center;margin:0 0 20px;">
                  <a href="{$link}" style="display:inline-block;padding:12px 24px;border-radius:12px;background:{$branding['primary']};color:#0b0f1a;text-decoration:none;font-weight:700;box-shadow:0 18px 45px rgba(255,107,53,0.3);">Ir para acesso</a>
                </div>
                <p style="margin:0;font-size:12px;color:rgba(233,236,243,0.65);">Em caso de dúvida, responda este e-mail ou acione o suporte.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . "{$bodyText}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . "{$htmlBody}\r\n"
          . "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($email, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $email,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $textBody),
        PHP_EOL
    );
    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $email,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $bodyText),
        PHP_EOL
    );

    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);
}

/**
 * Confirmação de pagamento aprovado para o aluno.
 */
function send_payment_confirmation_email(
    string $email,
    string $name,
    string $plan,
    float $amount,
    string $currency,
    ?string $paidAt = null
): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $mailCfg = onboard_mail_config();
    $branding = nf_branding();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $subject = 'Pagamento aprovado e acesso liberado';
    $firstName = trim($name) !== '' ? explode(' ', trim($name))[0] : 'Olá';
    $loginLink = $branding['base'] . '/acesso';
    $paidAtFmt = $paidAt ? date('d/m/Y H:i', strtotime($paidAt)) : date('d/m/Y H:i');
    $amountText = number_format($amount, 2, ',', '.') . ' ' . strtoupper($currency ?: 'BRL');

    $bodyText = implode(PHP_EOL, [
        "{$firstName}, seu pagamento foi aprovado.",
        "Plano: {$plan}",
        "Valor: {$amountText}",
        "Data: {$paidAtFmt}",
        "Acesse: {$loginLink}",
        "Se precisar reenviar senha, use a tela de acesso.",
    ]);

    $boundary = md5((string) microtime(true));
    $listUnsub = sprintf('<mailto:%s>', $mailCfg['reply_to']);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: NutremFit <%s>', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'List-Unsubscribe: ' . $listUnsub,
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head><meta charset="UTF-8"><title>{$subject}</title></head>
  <body style="margin:0;padding:0;background:{$branding['dark']};font-family:'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['dark']};padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="560" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['card']};border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:18px 22px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <img src="{$branding['logo']}" alt="NutremFit" style="height:34px;vertical-align:middle;">
                <p style="margin:6px 0 0;font-size:14px;color:#ffb47a;">Pagamento confirmado</p>
                <h2 style="margin:4px 0 0;font-size:22px;color:#fff;">{$firstName}, seu acesso está liberado</h2>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                <p style="margin:0 0 12px;font-size:15px;color:rgba(233,236,243,0.9);">Plano: <strong>{$plan}</strong></p>
                <p style="margin:0 0 8px;font-size:14px;color:rgba(233,236,243,0.82);">Valor: {$amountText}</p>
                <p style="margin:0 0 16px;font-size:14px;color:rgba(233,236,243,0.82);">Data: {$paidAtFmt}</p>
                <div style="text-align:center;margin:0 0 20px;">
                  <a href="{$loginLink}" style="display:inline-block;padding:12px 24px;border-radius:12px;background:{$branding['primary']};color:#0b0f1a;text-decoration:none;font-weight:700;box-shadow:0 18px 45px rgba(255,107,53,0.3);">Ir para a Área do Aluno</a>
                </div>
                <p style="margin:0;font-size:12px;color:rgba(233,236,243,0.65);">Se precisar reenviar senha, use a tela de acesso ou responda este e-mail.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . "{$bodyText}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . "{$htmlBody}\r\n"
          . "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($email, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $email,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $bodyText),
        PHP_EOL
    );

    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);
}

/**
 * Boas-vindas no clique de checkout (sem senha): orienta a validar acesso em /acesso.
 */
function send_welcome_pending_email(string $email, string $name, string $plan): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $mailCfg = onboard_mail_config();
    $branding = nf_branding();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $subject = 'Pronto para validar seu acesso na NutremFit';
    $firstName = trim($name) !== '' ? explode(' ', trim($name))[0] : 'Olá';
    $accessLink = $branding['base'] . '/acesso';

    $bodyText = implode(PHP_EOL, [
        "{$firstName}, recebemos seu pedido de pagamento para o plano {$plan}.",
        "Valide seu e-mail e acompanhe o status aqui: {$accessLink}",
        "Assim que o pagamento for aprovado, liberamos e enviamos sua senha temporária.",
    ]);

    $boundary = md5((string) microtime(true));
    $listUnsub = sprintf('<mailto:%s>', $mailCfg['reply_to']);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: NutremFit <%s>', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'List-Unsubscribe: ' . $listUnsub,
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head><meta charset="UTF-8"><title>{$subject}</title></head>
  <body style="margin:0;padding:0;background:{$branding['dark']};font-family:'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['dark']};padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="560" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['card']};border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:18px 22px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <img src="{$branding['logo']}" alt="NutremFit" style="height:34px;vertical-align:middle;">
                <p style="margin:6px 0 0;font-size:14px;color:#ffb47a;">Quase lá</p>
                <h2 style="margin:4px 0 0;font-size:22px;color:#fff;">{$firstName}, valide seu acesso</h2>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                <p style="margin:0 0 12px;font-size:15px;color:rgba(233,236,243,0.9);">Plano: <strong>{$plan}</strong></p>
                <p style="margin:0 0 14px;font-size:14px;color:rgba(233,236,243,0.82);">Use o botão abaixo para validar seu e-mail e acompanhar a aprovação. Assim que o pagamento for confirmado, enviaremos sua senha temporária.</p>
                <div style="text-align:center;margin:0 0 20px;">
                  <a href="{$accessLink}" style="display:inline-block;padding:12px 24px;border-radius:12px;background:{$branding['primary']};color:#0b0f1a;text-decoration:none;font-weight:700;box-shadow:0 18px 45px rgba(255,107,53,0.3);">Validar acesso</a>
                </div>
                <p style="margin:0;font-size:12px;color:rgba(233,236,243,0.65);">Qualquer dúvida, responda este e-mail. Estamos acompanhando seu pedido.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . "{$bodyText}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . "{$htmlBody}\r\n"
          . "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($email, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $email,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $bodyText),
        PHP_EOL
    );

    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);
}

/**
 * Dispara o e-mail de primeiro acesso ou registra a mensagem para envio manual.
 *
 * @param string      $email
 * @param string      $name
 * @param string      $password
 * @param string      $plan
 * @param string|null $expiresAt Data de expiração do acesso (Y-m-d H:i:s) opcional
 * @param bool        $force     Envia mesmo se já houver onboarding enviado (usar em recuperação de senha)
 */
function send_onboarding_email(string $email, string $name, string $password, string $plan, ?string $expiresAt = null, bool $force = false): void
{
    $appUrl = rtrim(function_exists('nf_base_url') ? nf_base_url() : (getenv('APP_URL') ?: 'https://nutremfit.com.br'), '/');
    // Sempre direcionar para a tela de login (senha já enviada)
    $loginUrl = function_exists('nf_url') ? nf_url('/area-login') : 'https://nutremfit.com.br/area-login';
    $formUrl  = $appUrl . '/formulario-inicial';
    $mailCfg = onboard_mail_config();

    // Evita reenvio duplicado, salvo se for forçado (recuperação)
    $user = user_store_find($email);
    if ($user && !$force) {
        $prefs = is_array($user['preferences'] ?? null) ? $user['preferences'] : [];
        if (!empty($prefs['onboarding_sent_at'])) {
            return;
        }
    }
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $subject = 'Seu acesso à Área do Aluno NutremFit';

    $firstName = trim($name);
    if ($firstName === '') {
        $firstName = 'Bem-vinda(o)';
    } else {
        $firstName = explode(' ', $firstName)[0];
    }

    $textBodyLines = [
        "Olá {$firstName},",
        "Seu acesso ao plano {$plan} foi liberado!",
        "Login: {$loginUrl}",
        "E-mail: {$email}",
        "Senha temporária: {$password}",
        "Formulário inicial (primeiro acesso): {$formUrl}",
    ];
    if ($expiresAt) {
        $textBodyLines[] = "Plano válido até: {$expiresAt}";
    }
    $textBodyLines[] = '';
    $textBodyLines[] = 'Parabéns por entrar para o time NutremFit!';
    $textBodyLines[] = 'Você acaba de dar um passo importante rumo a resultados reais, consistentes e verdadeiramente personalizados.';
    $textBodyLines[] = 'Para que possamos construir o seu plano com total precisão, o preenchimento do checklist inicial é essencial: é a partir dele que conseguimos entender profundamente seu histórico, seus objetivos, sua rotina e necessidades reais.';
    $textBodyLines[] = 'O prazo de entrega só começa a contar após o envio completo desse checklist, pois precisamos dessas informações para elaborar algo feito sob medida para você.';
    $textBodyLines[] = 'Seu treino e seu plano alimentar 100% personalizados estarão disponíveis aqui na plataforma em até 24 horas úteis após o preenchimento do checklist, conforme informado nos Termos de Uso.';
    $textBodyLines[] = 'Esse tempo é fundamental para que nossa equipe de especialistas analise cuidadosamente cada dado e desenvolva uma estratégia exclusiva, que potencialize seus resultados e encaixe na sua rotina de forma realista.';
    $textBodyLines[] = 'Nada aqui é automático ou genérico:';
    $textBodyLines[] = '';
    $textBodyLines[] = '. não usamos IA para prescrever';
    $textBodyLines[] = '. não entregamos dietas prontas';
    $textBodyLines[] = '. não trabalhamos com treinos padronizados';
    $textBodyLines[] = '';
    $textBodyLines[] = 'Cada detalhe é criado manualmente por profissionais com registro ativo, garantindo segurança, estratégia e muito mais eficácia.';
    $textBodyLines[] = 'Aproveite esse momento, estamos preparando algo feito sob medida especialmente para você!';
    $textBodyLines[] = '';
    $textBodyLines[] = 'Um abraço,';
    $textBodyLines[] = 'Equipe NutremFit';
    $textBody = implode(PHP_EOL, $textBodyLines);

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
  </head>
  <body style="margin:0;padding:0;background:#0b0f1a;font-family: 'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:#0b0f1a;padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="540" cellspacing="0" cellpadding="0" role="presentation" style="background:#0f1424;border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:22px 24px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <p style="margin:0;font-size:13px;letter-spacing:0.5px;text-transform:uppercase;color:rgba(255,255,255,0.65);">Acesso liberado</p>
                <h1 style="margin:6px 0 4px;font-size:26px;color:#fff;">{$firstName}, seu login está pronto</h1>
                <p style="margin:0;color:#ffb47a;font-weight:600;">Plano {$plan}</p>
              </td>
            </tr>
            <tr>
              <td style="padding:24px 24px 8px;">
                <p style="margin:0 0 12px;font-size:16px;color:#e9ecf3;">Use os dados abaixo para entrar e personalizar seu perfil.</p>
                <table width="100%" cellspacing="0" cellpadding="0" role="presentation" style="border-collapse:collapse;">
                  <tr>
                    <td style="padding:12px 0;font-size:14px;color:rgba(233,236,243,0.92);">Endereço de acesso</td>
                    <td style="padding:12px 0;text-align:right;"><a href="{$loginUrl}" style="color:#ffb47a;text-decoration:none;font-weight:600;">{$loginUrl}</a></td>
                  </tr>
                  <tr>
                    <td style="padding:12px 0;font-size:14px;color:rgba(233,236,243,0.92);">E-mail</td>
                    <td style="padding:12px 0;text-align:right;color:#fff;font-weight:600;">{$email}</td>
                  </tr>
                  <tr>
                    <td style="padding:12px 0;font-size:14px;color:rgba(233,236,243,0.92);">Senha temporária</td>
                    <td style="padding:12px 0;text-align:right;color:#fff;font-weight:600;">{$password}</td>
                  </tr>
HTML;

    if ($expiresAt) {
        $htmlBody .= <<<HTML
                  <tr>
                    <td style="padding:12px 0;font-size:14px;color:rgba(233,236,243,0.92);">Validade</td>
                    <td style="padding:12px 0;text-align:right;color:#ffb47a;font-weight:600;">{$expiresAt}</td>
                  </tr>
HTML;
    }

    $htmlBody .= <<<HTML
                </table>
                <div style="text-align:center;margin:0 0 18px;">
                  <a href="{$formUrl}" style="display:inline-block;padding:12px 24px;border-radius:12px;background:#00c2ff;color:#0b0f1a;text-decoration:none;font-weight:700;box-shadow:0 18px 45px rgba(0,194,255,0.28);">Preencher formulário inicial</a>
                  <p style="margin:8px 0 0;font-size:13px;color:rgba(233,236,243,0.78);">Preencha nas próximas 24h úteis para liberar seu plano personalizado e liberar toda a Área do Aluno.</p>
                </div>
                <div style="text-align:center;margin:24px 0 14px;">
                  <a href="{$loginUrl}" style="display:inline-block;padding:12px 24px;border-radius:12px;background:#ff7a00;color:#0b0f1a;text-decoration:none;font-weight:700;box-shadow:0 18px 45px rgba(255,122,0,0.35);">Entrar na Área do Aluno</a>
                </div>
                <div style="margin:0 0 18px;">
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.88);">Parabéns por entrar para o time NutremFit!</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Você acaba de dar um passo importante rumo a resultados reais, consistentes e verdadeiramente personalizados.</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Para que possamos construir o seu plano com total precisão, o preenchimento do checklist inicial é essencial: é a partir dele que conseguimos entender profundamente seu histórico, seus objetivos, sua rotina e necessidades reais.</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">O prazo de entrega só começa a contar após o envio completo desse checklist, pois precisamos dessas informações para elaborar algo feito sob medida para você.</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Seu treino e seu plano alimentar 100% personalizados estarão disponíveis aqui na plataforma em até 24 horas úteis após o preenchimento do checklist, conforme informado nos Termos de Uso.</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Esse tempo é fundamental para que nossa equipe de especialistas analise cuidadosamente cada dado e desenvolva uma estratégia exclusiva, que potencialize seus resultados e encaixe na sua rotina de forma realista.</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Nada aqui é automático ou genérico:</p>
                  <p style="margin:0 0 4px;font-size:14px;color:rgba(233,236,243,0.82);">. não usamos IA para prescrever</p>
                  <p style="margin:0 0 4px;font-size:14px;color:rgba(233,236,243,0.82);">. não entregamos dietas prontas</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">. não trabalhamos com treinos padronizados</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Cada detalhe é criado manualmente por profissionais com registro ativo, garantindo segurança, estratégia e muito mais eficácia.</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">Aproveite esse momento, estamos preparando algo feito sob medida especialmente para você!</p>
                  <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.88);">Um abraço,<br>Equipe NutremFit</p>
                </div>
                <p style="margin:0 0 12px;font-size:14px;color:rgba(233,236,243,0.82);">A área completa é liberada após o envio do formulário inicial.</p>
                <p style="margin:0 0 10px;font-size:14px;color:rgba(233,236,243,0.82);">Por segurança, altere a senha assim que fizer login.</p>
                <p style="margin:0 0 22px;font-size:14px;color:rgba(233,236,243,0.82);">Se não reconhece este e-mail, responda para bloquear o acesso.</p>
              </td>
            </tr>
            <tr>
              <td style="padding:18px 24px;border-top:1px solid rgba(255,255,255,0.06);background:#0d111d;">
                <p style="margin:0 0 6px;color:#ffb47a;font-weight:600;">Conte comigo em cada ajuste.</p>
                <p style="margin:0;color:rgba(233,236,243,0.75);font-size:13px;">Marina Amancio — NutremFit</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $boundary = md5((string) microtime(true));
    $listUnsub = sprintf('<mailto:%s>', $mailCfg['reply_to']);
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: NutremFit <%s>', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'List-Unsubscribe: ' . $listUnsub,
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
          . "{$textBody}\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
          . "{$htmlBody}\r\n"
          . "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($email, $subject, $body, $headers, $params) : false;

    if ($user && $sent) {
        $prefs = is_array($user['preferences'] ?? null) ? $user['preferences'] : [];
        $prefs['onboarding_sent_at'] = date('Y-m-d H:i:s');
        user_store_update_fields($email, ['preferences' => $prefs]);
    }

    $logLine = sprintf(
        "[%s] %s %s | %s | %s%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $email,
        $subject,
        str_replace(PHP_EOL, ' \\ ', $textBody),
        PHP_EOL
    );

    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);
}

/**
 * Envia e-mail HTML com anexo PDF (para backup de treino ao admin).
 *
 * @param string          $to         Destinatario
 * @param string          $subject    Assunto
 * @param array<int,string> $lines    Linhas de texto no corpo
 * @param string          $attachData Conteudo binario do anexo
 * @param string          $attachName Nome do arquivo anexo
 * @return bool
 */
function nf_send_email_with_attachment(string $to, string $subject, array $lines, string $attachData, string $attachName): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mailCfg = onboard_mail_config();
    $branding = nf_branding();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];

    $mixedBoundary = 'mixed_' . md5((string) microtime(true));
    $altBoundary   = 'alt_' . md5((string) microtime(true) . 'alt');

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: %s', $mailCfg['reply_to']),
        sprintf('Return-Path: %s', $mailCfg['from']),
        'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"',
    ]);

    $bodyText = implode(PHP_EOL, $lines);

    $itemsHtml = '';
    foreach ($lines as $line) {
        $itemsHtml .= '<p style="margin:0 0 10px;font-size:15px;color:rgba(233,236,243,0.9);">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head><meta charset="UTF-8"><title>{$subject}</title></head>
  <body style="margin:0;padding:0;background:{$branding['dark']};font-family:'Inter','Segoe UI',Arial,sans-serif;color:#e9ecf3;">
    <table align="center" width="100%" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['dark']};padding:28px 12px;">
      <tr>
        <td align="center">
          <table width="560" cellspacing="0" cellpadding="0" role="presentation" style="background:{$branding['card']};border:1px solid rgba(255,255,255,0.08);border-radius:18px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,0.45);">
            <tr>
              <td style="padding:18px 22px;background: linear-gradient(135deg,#1a1024,#1d130d);">
                <table width="100%" cellspacing="0" cellpadding="0" role="presentation">
                  <tr>
                    <td style="text-align:left;">
                      <img src="{$branding['logo']}" alt="NutremFit" style="height:34px;vertical-align:middle;">
                    </td>
                    <td style="text-align:right;color:rgba(255,255,255,0.7);font-size:13px;">Backup de treino</td>
                  </tr>
                </table>
                <h2 style="margin:8px 0 0;font-size:22px;color:#fff;">{$subject}</h2>
              </td>
            </tr>
            <tr>
              <td style="padding:22px;">
                {$itemsHtml}
                <p style="margin:12px 0 0;font-size:12px;color:rgba(233,236,243,0.65);">O PDF do treino segue em anexo neste e-mail.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    // Parte alternativa (text + html)
    $altPart = "--{$altBoundary}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
             . "{$bodyText}\r\n"
             . "--{$altBoundary}\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
             . "{$htmlBody}\r\n"
             . "--{$altBoundary}--";

    // Anexo em base64
    $attachBase64 = chunk_split(base64_encode($attachData));
    $safeAttachName = str_replace('"', '', $attachName);

    // Corpo completo: mixed (alternative + attachment)
    $body = "--{$mixedBoundary}\r\n"
          . "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n"
          . "{$altPart}\r\n"
          . "--{$mixedBoundary}\r\n"
          . "Content-Type: application/pdf; name=\"{$safeAttachName}\"\r\n"
          . "Content-Disposition: attachment; filename=\"{$safeAttachName}\"\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . "{$attachBase64}\r\n"
          . "--{$mixedBoundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    $sent = function_exists('mail') ? @mail($to, $subject, $body, $headers, $params) : false;

    $logLine = sprintf(
        "[%s] %s %s | %s | attachment=%s (%d bytes)%s",
        date('c'),
        $sent ? 'SENT' : 'PENDING',
        $to,
        $subject,
        $attachName,
        strlen($attachData),
        PHP_EOL
    );
    file_put_contents(OUTBOX_LOG, $logLine, FILE_APPEND);

    return $sent;
}
