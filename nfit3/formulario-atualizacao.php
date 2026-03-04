<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/onboarding_mailer.php';

$current_user = area_guard_require_login();
$title = 'NutremFit | Formulário de atualização';
$css = '<link rel="stylesheet" href="assets/css/area.css">';

$preferences = is_array($current_user['preferences'] ?? null) ? $current_user['preferences'] : [];
$lastUpdateAt = !empty($preferences['last_update_form_at']) ? strtotime((string) $preferences['last_update_form_at']) : null;
$initialCompletedAt = !empty($preferences['initial_form_completed_at']) ? strtotime((string) $preferences['initial_form_completed_at']) : null;
$anchorTs = $lastUpdateAt ?: ($initialCompletedAt ?: strtotime((string) ($current_user['created_at'] ?? 'now')));
$daysSinceAnchor = (int) floor((time() - $anchorTs) / 86400);
$canSubmit = $daysSinceAnchor >= 22 && (!$lastUpdateAt || (time() - $lastUpdateAt) >= 30 * 86400);
$formMessage = '';
$formError = '';

if (!function_exists('nf_mail_with_pdf')) {
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
}

function nf_generate_simple_pdf_update(string $title, array $lines): ?string
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
    $tmp = tempnam(sys_get_temp_dir(), 'nf_pdf_up_');
    if (!$tmp) {
        return null;
    }
    file_put_contents($tmp, $pdfContent);
    return $tmp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canSubmit) {
        $formError = 'Você já enviou uma atualização neste ciclo ou ainda não está no período de atualização.';
    } else {
        $clean = [];
        foreach ($_POST as $k => $v) {
            if (is_array($v)) {
                $clean[$k] = array_map(static fn($vv) => is_string($vv) ? trim($vv) : $vv, $v);
            } else {
                $clean[$k] = is_string($v) ? trim($v) : $v;
            }
        }

        $payload = [
            'user_email'   => $current_user['email'],
            'user_name'    => $current_user['name'] ?? '',
            'submitted_at' => date('c'),
            'data'         => $clean,
        ];

        $dir = __DIR__ . '/storage/forms_updates';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $formError = 'Não foi possível salvar agora. Tente novamente em instantes.';
        } else {
            $filename = sprintf(
                '%s/update_%s_%s.json',
                $dir,
                date('Ymd_His'),
                preg_replace('/[^a-z0-9]/i', '_', $current_user['email'])
            );
            $saved = @file_put_contents($filename, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($saved === false) {
                $formError = 'Não foi possível salvar agora. Tente novamente em instantes.';
            } else {
                $preferences['last_update_form_at'] = date('Y-m-d H:i:s');
                $updated = user_store_update_fields($current_user['email'], ['preferences' => $preferences]);
                if (is_array($updated)) {
                    $current_user = $updated;
                }
                $formMessage = 'Atualização enviada! Vamos ajustar seu plano em até 24h úteis.';
                $canSubmit = false;

                $lines = [
                    'Formulário de atualização - NutremFit',
                    'Aluno: ' . ($current_user['name'] ?? 'n/d'),
                    'E-mail: ' . ($current_user['email'] ?? 'n/d'),
                    'Enviado em: ' . date('d/m/Y H:i'),
                    '',
                ];
                foreach ($clean as $k => $v) {
                    $val = is_array($v) ? implode(', ', array_map('strval', $v)) : $v;
                    $label = ucwords(str_replace('_', ' ', $k));
                    $lines[] = "{$label}: {$val}";
                }

                $pdf = nf_generate_simple_pdf_update('Formulário de atualização', $lines);
                $adminEmail = admin_notification_recipient();
                if ($pdf && $adminEmail) {
                    nf_mail_with_pdf(
                        $adminEmail,
                        'Atualização mensal do aluno - NutremFit',
                        implode(PHP_EOL, $lines),
                        '<p>Nova atualização mensal recebida.</p><p>Aluno: <strong>' . htmlspecialchars($current_user['name'] ?? 'n/d', ENT_QUOTES, 'UTF-8') . '</strong><br>E-mail: ' . htmlspecialchars($current_user['email'] ?? 'n/d', ENT_QUOTES, 'UTF-8') . '</p>',
                        $pdf,
                        'formulario-atualizacao.pdf'
                    );
                }
                $userEmail = $current_user['email'] ?? '';
                if ($pdf && $userEmail) {
                    nf_mail_with_pdf(
                        $userEmail,
                        'Recebemos sua atualização - NutremFit',
                        "Recebemos sua atualização mensal.\nVamos ajustar seu plano em até 24h úteis.\nSegue em anexo o PDF das respostas.",
                        '<p>Recebemos sua atualização mensal.</p><p>Vamos ajustar seu plano em até 24h úteis.</p><p>Segue em anexo o PDF das respostas.</p>',
                        $pdf,
                        'formulario-atualizacao.pdf'
                    );
                }
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
      --nf-card: #0f1322;
      --nf-accent: #ff6b35;
    }
    body { background: var(--nf-bg); color: #e8ecf6; }
    .nf-hero {
      background: radial-gradient(circle at 15% 20%, rgba(255,107,53,0.25), transparent 30%),
                  radial-gradient(circle at 85% 10%, rgba(227,88,255,0.22), transparent 26%),
                  linear-gradient(135deg,#0b0f1a,#0c1426 60%,#0b0f1a);
      padding: 80px 0 50px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .nf-hero h1 { font-weight: 800; letter-spacing: -0.5px; color: #fff; }
    .nf-hero p { color: rgba(232,236,246,0.8); max-width: 720px; margin: 12px auto 0; }
    .nf-shell {
      background: #0f1322;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 22px;
      padding: 28px;
      box-shadow: 0 30px 120px rgba(0,0,0,0.45);
      position: relative;
      overflow: hidden;
    }
    label { color: #d5d9e6; font-weight: 600; }
    .form-control, .form-select {
      background: #0c111d;
      border: 1px solid rgba(255,255,255,0.08);
      color: #e8ecf6;
    }
    .nf-fieldset {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 18px;
      margin-bottom: 16px;
    }
    .nf-steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
  </style>

  <section class="nf-hero">
    <div class="container">
      <h1>Formulário de atualização</h1>
      <p>Use este formulário quando faltar 8 dias para fechar o ciclo (dia 22) para registrarmos ajustes em até 24h úteis.</p>
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
            <?php if (!$canSubmit): ?>
              <div class="alert alert-info mb-0">
                Você já enviou a atualização deste ciclo ou ainda não está no período (dia 22 do ciclo). Para ajustes, fale com o suporte.
              </div>
            <?php else: ?>
            <div class="nf-steps mb-3" id="nf-progress">
              <div class="nf-step-dot active" data-step="0">1. Dados</div>
              <div class="nf-step-dot" data-step="1">2. Progresso</div>
              <div class="nf-step-dot" data-step="2">3. Treino</div>
              <div class="nf-step-dot" data-step="3">4. Alimentação</div>
              <div class="nf-step-dot" data-step="4">5. Objetivos</div>
            </div>

            <form class="form" id="nf-update-form" method="post" action="/formulario-atualizacao" autocomplete="on">
              <div class="nf-step active" data-step="0">
                <div class="nf-fieldset">
                  <h4 class="mb-3">Identificação</h4>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="nome">Nome</label>
                      <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars($current_user['name'] ?? '', ENT_QUOTES, 'UTF-8');?>" required>
                    </div>
                    <div class="col-md-6">
                      <label for="email">E-mail (ou ID de acompanhamento)</label>
                      <input type="text" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_user['email'] ?? '', ENT_QUOTES, 'UTF-8');?>" required>
                    </div>
                    <div class="col-md-6">
                      <label for="data">Data do preenchimento</label>
                      <input type="date" id="data" name="data" class="form-control" value="<?php echo date('Y-m-d');?>">
                    </div>
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="1">
                <div class="nf-fieldset">
                  <h4 class="mb-3">Progresso físico</h4>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="peso_atual">Peso atual</label>
                      <input type="text" id="peso_atual" name="peso_atual" class="form-control" placeholder="Ex: 72,5 kg">
                    </div>
                    <div class="col-md-6">
                      <label for="gordura_atual">Percentual de gordura (se souber)</label>
                      <input type="text" id="gordura_atual" name="gordura_atual" class="form-control" placeholder="Ex: 20% ou “não sei”">
                    </div>
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="2">
                <div class="nf-fieldset">
                  <h4 class="mb-3">Treino</h4>
                  <div class="mb-3">
                    <label>Quantos treinos você conseguiu fazer por semana, em média?</label><br>
                    <label class="me-3"><input type="radio" name="treinos_semana" value="1"> 1</label>
                    <label class="me-3"><input type="radio" name="treinos_semana" value="2"> 2</label>
                    <label class="me-3"><input type="radio" name="treinos_semana" value="3"> 3</label>
                    <label class="me-3"><input type="radio" name="treinos_semana" value="4"> 4</label>
                    <label><input type="radio" name="treinos_semana" value="5+"> 5+</label>
                  </div>
                  <div class="mb-3">
                    <label>Como se sente durante os treinos?</label><br>
                    <label class="me-3"><input type="radio" name="sensaotreino" value="energia"> Com energia</label>
                    <label class="me-3"><input type="radio" name="sensaotreino" value="cansado"> Cansada(o)</label>
                    <label><input type="radio" name="sensaotreino" value="dor"> Dor ou desconforto</label>
                  </div>
                  <div class="mb-3">
                    <label>Tem sentido dor, lesão ou limitação nova? Se sim, descreva.</label>
                    <textarea name="lesao_nova" class="form-control" rows="2"></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Quer mudar algo no tipo de treino?</label><br>
                    <label class="me-3"><input type="checkbox" name="ajuste_treino[]" value="intensidade"> Mais intensidade</label>
                    <label class="me-3"><input type="checkbox" name="ajuste_treino[]" value="forca"> Mais foco em força</label>
                    <label class="me-3"><input type="checkbox" name="ajuste_treino[]" value="aerobico"> Mais aeróbico</label>
                    <label><input type="checkbox" name="ajuste_treino[]" value="manter"> Manter como está</label>
                  </div>
                  <div class="mb-3">
                    <label for="tempo_sessao">Quanto tempo você tem disponível por sessão atualmente?</label>
                    <input type="text" id="tempo_sessao" name="tempo_sessao" class="form-control" placeholder="Ex: 45 min">
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="3">
                <div class="nf-fieldset">
                  <h4 class="mb-3">Alimentação</h4>
                  <div class="mb-3">
                    <label>Está conseguindo seguir o plano alimentar atual?</label><br>
                    <label class="me-3"><input type="radio" name="seguir_plano" value="sim"> Sim, bem</label>
                    <label class="me-3"><input type="radio" name="seguir_plano" value="parcial"> Parcialmente</label>
                    <label><input type="radio" name="seguir_plano" value="nao"> Não</label>
                  </div>
                  <div class="mb-3">
                    <label>O que tem dificultado seguir o plano?</label><br>
                    <label class="me-3"><input type="checkbox" name="dificuldade_plano[]" value="tempo"> Tempo</label>
                    <label class="me-3"><input type="checkbox" name="dificuldade_plano[]" value="fome"> Fome</label>
                    <label class="me-3"><input type="checkbox" name="dificuldade_plano[]" value="doce"> Vontade de doce</label>
                    <label class="me-3"><input type="checkbox" name="dificuldade_plano[]" value="social"> Social</label>
                    <label><input type="checkbox" name="dificuldade_plano[]" value="outro"> Outro:</label>
                    <input type="text" name="dificuldade_outro" class="form-control mt-2" placeholder="Descreva se marcou Outro">
                  </div>
                  <div class="mb-3">
                    <label>Há algum alimento novo que quer incluir ou retirar do plano?</label>
                    <textarea name="alimento_novo" class="form-control" rows="2"></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Alguma mudança significativa na rotina (trabalho, viagens, sono etc.)?</label>
                    <textarea name="mudanca_rotina" class="form-control" rows="2"></textarea>
                  </div>
                </div>
              </div>

              <div class="nf-step" data-step="4">
                <div class="nf-fieldset">
                  <h4 class="mb-3">Próximos objetivos</h4>
                  <div class="mb-3">
                    <label>Está satisfeito(a) com o progresso até aqui? (1–5)</label><br>
                    <label class="me-3"><input type="radio" name="satisfacao" value="1"> 1</label>
                    <label class="me-3"><input type="radio" name="satisfacao" value="2"> 2</label>
                    <label class="me-3"><input type="radio" name="satisfacao" value="3"> 3</label>
                    <label class="me-3"><input type="radio" name="satisfacao" value="4"> 4</label>
                    <label><input type="radio" name="satisfacao" value="5"> 5</label>
                  </div>
                  <div class="mb-3">
                    <label>O que ainda pode melhorar?</label>
                    <textarea name="melhorar" class="form-control" rows="2"></textarea>
                  </div>
                  <div class="mb-3">
                    <label>Quer manter o mesmo foco ou ajustar?</label><br>
                    <label class="me-3"><input type="checkbox" name="foco[]" value="emagrecimento"> Continuar emagrecendo</label>
                    <label class="me-3"><input type="checkbox" name="foco[]" value="definicao"> Focar em definição</label>
                    <label class="me-3"><input type="checkbox" name="foco[]" value="massa"> Ganhar massa</label>
                    <label class="me-3"><input type="checkbox" name="foco[]" value="resistencia"> Melhorar resistência</label>
                    <label><input type="checkbox" name="foco[]" value="outro"> Outro:</label>
                    <input type="text" name="foco_outro" class="form-control mt-2" placeholder="Descreva se marcou Outro">
                  </div>
                </div>
              </div>

              <div class="nf-nav">
                <button type="button" class="nf-btn secondary" id="btn-prev" disabled>Voltar</button>
                <div class="d-flex gap-2">
                  <button type="button" class="nf-btn secondary" id="btn-next">Próximo</button>
                  <button type="submit" class="nf-btn primary" id="btn-submit" style="display:none;">Enviar atualização</button>
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
  <?php if ($canSubmit): ?>
  <script>
    (function() {
      var steps = Array.from(document.querySelectorAll('.nf-step'));
      var dots = Array.from(document.querySelectorAll('.nf-step-dot'));
      var nextBtn = document.getElementById('btn-next');
      var prevBtn = document.getElementById('btn-prev');
      var submitBtn = document.getElementById('btn-submit');
      var form = document.getElementById('nf-update-form');
      var idx = 0;

      function updateUI() {
        steps.forEach(function(step, i) { step.classList.toggle('active', i === idx); });
        dots.forEach(function(dot, i) { dot.classList.toggle('active', i <= idx); });
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
  <?php endif; ?>
</body>
</html>
