<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/onboarding_mailer.php';

$current_user = area_guard_require_login();
$title = 'NutremFit | Formulario inicial';
$formMessage = '';
$formError = '';
$preferences = is_array($current_user['preferences'] ?? null) ? $current_user['preferences'] : [];
$formCompleted = !empty($preferences['initial_form_completed']);
$formLocked = $formCompleted;

/**
 * Gera um PDF simples (texto) com as respostas.
 *
 * @param string $title
 * @param array<int,string> $lines
 * @return string|null Caminho do arquivo PDF
 */
function nf_generate_simple_pdf(string $title, array $lines): ?string
{
    $escape = static function (string $text): string {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text) ?? '';
    };

    $streamLines = [];
    $streamLines[] = 'BT';
    $streamLines[] = '/F1 14 Tf';
    $streamLines[] = '70 770 Td';
    $streamLines[] = sprintf('(%s) Tj', $escape($title));
    $streamLines[] = '0 -22 Td';
    $streamLines[] = '/F1 11 Tf';
    $streamLines[] = '14 TL';
    foreach ($lines as $line) {
        $clean = trim($line);
        if ($clean === '') {
            $streamLines[] = 'T*';
            continue;
        }
        $streamLines[] = sprintf('(%s) Tj', $escape($clean));
        $streamLines[] = 'T*';
    }
    $streamLines[] = 'ET';
    $stream = implode("\n", $streamLines);
    $len = strlen($stream);

    $objects = [
        '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        '2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj',
        '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
        "4 0 obj << /Length {$len} >> stream\n{$stream}\nendstream endobj",
        '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
    ];

    $out = ['%PDF-1.4'];
    $offsets = ['0000000000 65535 f '];
    $pos = strlen($out[0]) + 1;
    foreach ($objects as $obj) {
        $offsets[] = sprintf('%010d 00000 n ', $pos);
        $out[] = $obj;
        $pos += strlen($obj) + 1;
    }
    $xrefStart = $pos;
    $out[] = 'xref';
    $out[] = '0 ' . count($offsets);
    foreach ($offsets as $off) {
        $out[] = $off;
    }
    $out[] = 'trailer << /Size ' . count($offsets) . ' /Root 1 0 R >>';
    $out[] = 'startxref';
    $out[] = (string) $xrefStart;
    $out[] = '%%EOF';

    $pdfContent = implode("\n", $out);
    $tmp = tempnam(sys_get_temp_dir(), 'nf_pdf_');
    if (!$tmp) {
        return null;
    }
    file_put_contents($tmp, $pdfContent);
    return $tmp;
}

/**
 * Envia e-mail com PDF em anexo (multipart/mixed).
 */
function nf_mail_with_pdf(string $to, string $subject, string $textBody, string $htmlBody, string $pdfPath, string $pdfFilename): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !is_file($pdfPath)) {
        return;
    }

    $mailCfg = onboard_mail_config();
    $envelopeFrom = getenv('MAIL_ENVELOPE_FROM') ?: $mailCfg['from'];
    $boundary = md5((string) microtime(true));
    $boundaryAlt = md5((string) microtime(true) . '_alt');

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        sprintf('From: %s <%s>', $mailCfg['from_name'], $mailCfg['from']),
        sprintf('Reply-To: %s', $mailCfg['reply_to']),
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ]);

    $pdfContent = chunk_split(base64_encode((string) file_get_contents($pdfPath)));

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";
    $body .= "--{$boundaryAlt}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "{$textBody}\r\n";
    $body .= "--{$boundaryAlt}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= "{$htmlBody}\r\n";
    $body .= "--{$boundaryAlt}--\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
    $body .= "{$pdfContent}\r\n";
    $body .= "--{$boundary}--";

    $params = $envelopeFrom ? sprintf('-f %s', $envelopeFrom) : '';
    @mail($to, $subject, $body, $headers, $params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($formLocked) {
        $formError = 'Você já enviou este formulário. Para atualizar dados, fale com o suporte.';
    } else {
    $cleanData = [];
    foreach ($_POST as $key => $value) {
        if (is_array($value)) {
            $cleanData[$key] = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $value);
        } else {
            $cleanData[$key] = is_string($value) ? trim($value) : $value;
        }
    }

    $payload = [
        'user_email'   => $current_user['email'],
        'user_name'    => $current_user['name'] ?? '',
        'submitted_at' => date('c'),
        'data'         => $cleanData,
    ];

    $dir = __DIR__ . '/storage/forms';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $formError = 'Nao foi possivel salvar as respostas agora. Tente novamente em instantes.';
    } else {
        $filename = sprintf(
            '%s/initial_%s_%s.json',
            $dir,
            date('Ymd_His'),
            preg_replace('/[^a-z0-9]/i', '_', $current_user['email'])
        );
        $saved = @file_put_contents($filename, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($saved === false) {
            $formError = 'Nao foi possivel salvar as respostas agora. Tente novamente em instantes.';
        } else {
            $preferences['initial_form_completed'] = true;
            $preferences['initial_form_completed_at'] = date('Y-m-d H:i:s');
            $updated = user_store_update_fields($current_user['email'], ['preferences' => $preferences]);
            if (is_array($updated)) {
                $current_user = $updated;
            }
            $formCompleted = true;
            $formMessage = 'Formulario enviado! Vou analisar e ajustar seu plano.';

            $adminLines = [
                'Evento: formulario inicial enviado',
                'Aluno: ' . ($current_user['name'] ?? 'n/d'),
                'E-mail: ' . ($current_user['email'] ?? 'n/d'),
                'Plano: ' . ($current_user['plan'] ?? 'n/d'),
                'Arquivo salvo: ' . basename($filename),
                'Horario do envio: ' . date('d/m/Y H:i'),
            ];
            if (!empty($cleanData['objetivo'])) {
                $adminLines[] = 'Objetivo declarado: ' . $cleanData['objetivo'];
            }

            $lines = [];
            $lines[] = 'Formulario inicial - NutremFit';
            $lines[] = 'Aluno: ' . ($current_user['name'] ?? 'n/d');
            $lines[] = 'E-mail: ' . ($current_user['email'] ?? 'n/d');
            $lines[] = 'Plano: ' . ($current_user['plan'] ?? 'n/d');
            $lines[] = 'Enviado em: ' . date('d/m/Y H:i');
            $lines[] = '';
            foreach ($cleanData as $k => $v) {
                if (is_array($v)) {
                    $v = implode(', ', array_map('strval', $v));
                }
                $label = ucwords(str_replace('_', ' ', $k));
                $lines[] = "{$label}: {$v}";
            }

            $pdf = nf_generate_simple_pdf('Formulario inicial NutremFit', $lines);

            $adminEmail = admin_notification_recipient();
            if ($pdf && $adminEmail) {
                $htmlAdmin = '<p>Nova submissao do formulario inicial.</p><p>Aluno: <strong>' . htmlspecialchars($current_user['name'] ?? 'n/d', ENT_QUOTES, 'UTF-8') . '</strong><br>E-mail: ' . htmlspecialchars($current_user['email'] ?? 'n/d', ENT_QUOTES, 'UTF-8') . '</p>';
                nf_mail_with_pdf(
                    $adminEmail,
                    'Novo formulario inicial - NutremFit',
                    implode(PHP_EOL, $adminLines),
                    $htmlAdmin,
                    $pdf,
                    'formulario-inicial.pdf'
                );
            }

            $userEmail = $current_user['email'] ?? '';
            if ($pdf && $userEmail) {
                $htmlUser = '<p>Recebemos seu formulario inicial e ja estamos analisando.</p><p>Suas respostas seguem em anexo (PDF) para voce conferir.</p>';
                nf_mail_with_pdf(
                    $userEmail,
                    'Recebemos seu formulario inicial - NutremFit',
                    "Recebemos seu formulario inicial.\nVamos revisar e liberar os ajustes na plataforma.\nQualquer duvida, responda este e-mail.",
                    $htmlUser,
                    $pdf,
                    'formulario-inicial.pdf'
                );
            }

            send_admin_notification('Novo formulario inicial enviado', $adminLines);
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>
<body data-spy="scroll" data-offset="80">

  <?php include './partials/preloader.php'; ?>
  <?php include './partials/header.php'; ?>

  <style>
    :root {
      --nf-bg: #0a0d14;
      --nf-card: #0f1422;
      --nf-accent: #ff6b35;
      --nf-soft: rgba(255,255,255,0.08);
    }
    body { background: var(--nf-bg); color: #e8ecf6; }
    .nf-hero {
      background: radial-gradient(circle at 15% 20%, rgba(255,107,53,0.25), transparent 30%),
                  radial-gradient(circle at 85% 10%, rgba(227,88,255,0.22), transparent 26%),
                  linear-gradient(135deg,#0b0f1a,#0c1426 60%,#0b0f1a);
      padding: 90px 0 60px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .nf-hero h1 { font-weight: 800; letter-spacing: -0.5px; color: #fff; }
    .nf-hero p { color: rgba(232,236,246,0.8); max-width: 720px; margin: 12px auto 0; }
    .nf-shell {
      background: var(--nf-card);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 22px;
      padding: 28px;
      box-shadow: 0 30px 120px rgba(0,0,0,0.45);
      position: relative;
      overflow: hidden;
    }
    .nf-steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 10px;
      margin-bottom: 18px;
    }
    .nf-step-dot {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      color: #b9c2d8;
      padding: 12px 14px;
      border-radius: 16px;
      text-align: center;
      font-weight: 600;
      transition: all .25s ease;
      position: relative;
      overflow: hidden;
    }
    .nf-step-dot.active {
      color: #fff;
      border-color: rgba(255,107,53,0.6);
      box-shadow: 0 12px 30px rgba(255,107,53,0.2);
    }
    .nf-step-dot::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, rgba(255,107,53,0.16), transparent);
      opacity: 0;
      transition: opacity .25s ease;
    }
    .nf-step-dot.active::after { opacity: 1; }
    .nf-step {
      display: none;
      animation: fadeIn .35s ease;
    }
    .nf-step.active { display: block; }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .nf-nav {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      margin-top: 12px;
    }
    .nf-btn {
      border: none;
      border-radius: 14px;
      padding: 12px 18px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s ease;
    }
    .nf-btn.primary {
      background: linear-gradient(120deg,var(--nf-accent),#ff864f);
      color: #0b0f1a;
      box-shadow: 0 18px 45px rgba(255,107,53,0.28);
    }
    .nf-btn.secondary {
      background: rgba(255,255,255,0.08);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.12);
    }
    .nf-btn:disabled { opacity: .5; cursor: not-allowed; }
    .nf-fieldset {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 18px;
      margin-bottom: 16px;
    }
    .nf-fieldset h5 { margin: 0 0 8px; }
    label { color: #d5d9e6; font-weight: 600; }
    .form-control, .form-select {
      background: #0c111d;
      border: 1px solid rgba(255,255,255,0.08);
      color: #e8ecf6;
    }
    .badge-test { background: rgba(255,255,255,0.08); border-radius: 12px; padding: 6px 10px; }
    @media (max-width: 768px) {
      .nf-hero { padding: 70px 0 50px; }
      .nf-shell { padding: 18px; }
    }
  </style>

  <section class="nf-hero">
    <div class="container">
      <h1>Formulario inicial</h1>
      <p>Complete em poucos minutos. Ao enviar, liberamos os ajustes personalizados e confirmamos por e-mail com um PDF das suas respostas.</p>
    </div>
  </section>

  <section class="py-5" style="background:#0b1020;">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <?php if ($formMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($formMessage, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php elseif ($formError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <div class="nf-shell">
            <?php if ($formLocked): ?>
              <div class="alert alert-info mb-0">Você já enviou este formulário. Para qualquer ajuste, fale com o suporte.</div>
            <?php else: ?>
            <div class="nf-steps mb-3" id="nf-progress">
              <div class="nf-step-dot active" data-step="0">1. Dados</div>
              <div class="nf-step-dot" data-step="1">2. Saude</div>
              <div class="nf-step-dot" data-step="2">3. Alimentacao</div>
              <div class="nf-step-dot" data-step="3">4. Treino</div>
              <div class="nf-step-dot" data-step="4">5. Objetivo</div>
            </div>

            <form id="nf-form" method="post" action="/formulario-inicial">
              <div class="nf-step active" data-step="0">
                <div class="nf-fieldset">
                  <h5>Dados gerais</h5>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label>Nome</label>
                      <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label>Sexo biologico</label>
                      <div class="d-flex gap-3 flex-wrap">
                        <label><input type="radio" name="sexo" value="feminino" required> Feminino</label>
                        <label><input type="radio" name="sexo" value="masculino"> Masculino</label>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label>Idade</label>
                      <input type="number" name="idade" class="form-control" min="10" max="100">
                    </div>
                    <div class="col-md-4">
                      <label>Peso</label>
                      <input type="text" name="peso" class="form-control" placeholder="Ex: 72,5 kg">
                    </div>
                    <div class="col-md-4">
                      <label>Altura</label>
                      <input type="text" name="altura" class="form-control" placeholder="Ex: 1,70 m">
                    </div>
                    <div class="col-md-6">
                      <label>Percentual de gordura</label>
                      <input type="text" name="gordura" class="form-control" placeholder="Ex: 22% ou nao sei">
                    </div>
                    <div class="col-md-6">
                      <label>Profissao</label>
                      <input type="text" name="profissao" class="form-control">
                    </div>
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="1">
                <div class="nf-fieldset">
                  <h5>Saude</h5>
                  <div class="mb-3">
                    <label>Possui alguma doenca diagnosticada?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="doenca" value="nao"> Nao</label>
                      <label><input type="radio" name="doenca" value="sim"> Sim. Qual?</label>
                    </div>
                    <input type="text" name="doenca_qual" class="form-control mt-2">
                  </div>
                  <div class="mb-3">
                    <label>Uso continuo de medicamentos?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="medicamentos" value="nao"> Nao</label>
                      <label><input type="radio" name="medicamentos" value="sim"> Sim. Quais?</label>
                    </div>
                    <input type="text" name="medicamentos_quais" class="form-control mt-2">
                  </div>
                  <div class="mb-3">
                    <label>Ja apresentou mal-estar em esforco fisico?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="malestar" value="nao"> Nao</label>
                      <label><input type="radio" name="malestar" value="sim"> Sim. Descreva</label>
                    </div>
                    <textarea name="malestar_desc" class="form-control mt-2" rows="2"></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Historico de cirurgia ou condicao medica relevante?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="cirurgia" value="nao"> Nao</label>
                      <label><input type="radio" name="cirurgia" value="sim"> Sim. Qual?</label>
                    </div>
                    <input type="text" name="cirurgia_qual" class="form-control mt-2">
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="2">
                <div class="nf-fieldset">
                  <h5>Alimentacao</h5>
                  <div class="mb-3">
                    <label>Alergia ou intolerancia alimentar?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="alergia" value="nao"> Nao</label>
                      <label><input type="radio" name="alergia" value="sim"> Sim. Quais?</label>
                    </div>
                    <input type="text" name="alergia_quais" class="form-control mt-2">
                  </div>
                  <div class="mb-3">
                    <label>Alimentos que prefere evitar</label>
                    <textarea name="evitar" class="form-control" rows="2" placeholder="Por gosto pessoal ou motivos culturais/religiosos."></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Alimentos que gosta muito e quer incluir</label>
                    <textarea name="incluir" class="form-control" rows="2"></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Refeicoes principais por dia</label>
                    <div class="d-flex gap-3 flex-wrap">
                      <label><input type="radio" name="refeicoes" value="2"> 2</label>
                      <label><input type="radio" name="refeicoes" value="3"> 3</label>
                      <label><input type="radio" name="refeicoes" value="4+"> 4 ou mais</label>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label>Alimentacao em um dia tipico</label>
                    <textarea name="dia_tipico" class="form-control" rows="3" placeholder="Conte manha, tarde e noite rapidamente."></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Horario de sono</label>
                    <textarea name="sono" class="form-control" rows="2" placeholder="Ex: acordo 6h30 - durmo 23h00"></textarea>
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="3">
                <div class="nf-fieldset">
                  <h5>Treino</h5>
                  <div class="mb-3">
                    <label>Onde pretende treinar?</label>
                    <div class="d-flex gap-3 flex-wrap">
                      <label><input type="radio" name="local_treino" value="academia"> Academia</label>
                      <label><input type="radio" name="local_treino" value="casa"> Casa</label>
                      <label><input type="radio" name="local_treino" value="outro"> Outro</label>
                    </div>
                    <input type="text" name="local_treino_outro" class="form-control mt-2" placeholder="Descreva se marcou Outro">
                  </div>
                  <div class="mb-3">
                    <label>Ja praticou atividade fisica regularmente?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="historico_atividade" value="nao"> Nao</label>
                      <label><input type="radio" name="historico_atividade" value="sim"> Sim. Qual/quais:</label>
                    </div>
                    <input type="text" name="historico_atividade_quais" class="form-control mt-2">
                  </div>
                  <div class="mb-3">
                    <label>Historico de lesoes ou limitacoes?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="lesoes" value="nao"> Nao</label>
                      <label><input type="radio" name="lesoes" value="sim"> Sim. Quais:</label>
                    </div>
                    <input type="text" name="lesoes_quais" class="form-control mt-2">
                  </div>
                  <div class="mb-3">
                    <label>Sente dor ou desconforto em algum exercicio?</label>
                    <div class="d-flex gap-3">
                      <label><input type="radio" name="dor" value="nao"> Nao</label>
                      <label><input type="radio" name="dor" value="sim"> Sim. Quais:</label>
                    </div>
                    <input type="text" name="dor_quais" class="form-control mt-2">
                  </div>
                  <div class="mb-3">
                    <label>Modalidades ou tipos de treino preferidos</label>
                    <div class="d-flex gap-3 flex-wrap">
                      <label><input type="checkbox" name="modalidades[]" value="musculacao"> Musculacao</label>
                      <label><input type="checkbox" name="modalidades[]" value="aerobico"> Aerobico</label>
                      <label><input type="checkbox" name="modalidades[]" value="funcional"> Funcional</label>
                      <label><input type="checkbox" name="modalidades[]" value="outro"> Outro</label>
                    </div>
                    <input type="text" name="modalidades_outro" class="form-control mt-2" placeholder="Descreva se marcou Outro">
                  </div>
                  <div class="mb-3">
                    <label>Dias por semana</label>
                    <div class="d-flex gap-3 flex-wrap">
                      <label><input type="radio" name="frequencia" value="2"> 2</label>
                      <label><input type="radio" name="frequencia" value="3"> 3</label>
                      <label><input type="radio" name="frequencia" value="4"> 4</label>
                      <label><input type="radio" name="frequencia" value="5+"> 5+</label>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label>Tempo medio por sessao</label>
                    <div class="d-flex gap-3 flex-wrap">
                      <label><input type="radio" name="tempo_treino" value="30"> 30 min</label>
                      <label><input type="radio" name="tempo_treino" value="45"> 45 min</label>
                      <label><input type="radio" name="tempo_treino" value="60"> 60 min</label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="4">
                <div class="nf-fieldset">
                  <h5>Objetivo</h5>
                  <div class="mb-3">
                    <label>Principal objetivo</label>
                    <div class="d-flex gap-3 flex-wrap">
                      <label><input type="radio" name="objetivo" value="emagrecimento" required> Emagrecimento</label>
                      <label><input type="radio" name="objetivo" value="massa"> Ganho de massa</label>
                      <label><input type="radio" name="objetivo" value="condicionamento"> Condicionamento</label>
                      <label><input type="radio" name="objetivo" value="saude"> Saude geral</label>
                      <label><input type="radio" name="objetivo" value="outro"> Outro</label>
                    </div>
                    <input type="text" name="objetivo_outro" class="form-control mt-2" placeholder="Descreva se marcou Outro">
                  </div>
                  <div class="alert alert-secondary" style="background: rgba(255,255,255,0.06); border: none; color: #fff;">
                    Finalize para enviar e receber seu PDF com as respostas. Liberamos os ajustes em ate 24h uteis.
                  </div>
                </div>
              </div>

              <div class="nf-nav">
                <button type="button" class="nf-btn secondary" id="btn-prev" disabled>Voltar</button>
                <div class="d-flex gap-2">
                  <button type="button" class="nf-btn secondary" id="btn-next">Proximo</button>
                  <button type="submit" class="nf-btn primary" id="btn-submit" style="display:none;">Enviar formulario</button>
                </div>
              </div>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include './partials/footer.php'; ?>
  <?php include './partials/script.php'; ?>

  <script>
    (function() {
      var steps = Array.from(document.querySelectorAll('.nf-step'));
      var dots = Array.from(document.querySelectorAll('.nf-step-dot'));
      var nextBtn = document.getElementById('btn-next');
      var prevBtn = document.getElementById('btn-prev');
      var submitBtn = document.getElementById('btn-submit');
      var form = document.getElementById('nf-form');
      var idx = 0;

      function updateUI() {
        steps.forEach(function(step, i) {
          step.classList.toggle('active', i === idx);
        });
        dots.forEach(function(dot, i) {
          dot.classList.toggle('active', i <= idx);
        });
        prevBtn.disabled = idx === 0;
        nextBtn.style.display = idx === steps.length - 1 ? 'none' : 'inline-block';
        submitBtn.style.display = idx === steps.length - 1 ? 'inline-block' : 'none';
      }

      function validateStep(stepEl) {
        var inputs = stepEl.querySelectorAll('input[required], textarea[required], select[required]');
        for (var i = 0; i < inputs.length; i++) {
          if (!inputs[i].value) {
            inputs[i].focus();
            return false;
          }
        }
        return true;
      }

      nextBtn.addEventListener('click', function() {
        if (!validateStep(steps[idx])) return;
        if (idx < steps.length - 1) {
          idx += 1;
          updateUI();
        }
      });

      prevBtn.addEventListener('click', function() {
        if (idx > 0) {
          idx -= 1;
          updateUI();
        }
      });

      form.addEventListener('submit', function() {
        nextBtn.disabled = true;
        prevBtn.disabled = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
      });

      updateUI();
    })();
  </script>
</body>
</html>
