<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/mpago.php';
$config = mpago_config();

$plans = mpago_plan_catalog();
$order = ['essencial', 'performance', 'vip'];
$aliasOrderMap = [
    'essencial'   => ['mensal'],
    'performance' => ['semestral'],
    'vip'         => ['anual'],
];

function find_plan_by_cycle_or_keyword(array $plans, ?string $cycle, array $keywords): ?string
{
    $cycle = $cycle ? strtolower(trim($cycle)) : null;
    foreach ($plans as $slug => $plan) {
        $planCycle = strtolower(trim((string) ($plan['cycle'] ?? '')));
        if ($cycle && $planCycle === $cycle) {
            return $slug;
        }
        $hay = mb_strtolower(
            trim((string) ($plan['slug'] ?? $slug) . ' ' . ($plan['name'] ?? '') . ' ' . ($plan['description'] ?? ''))
        );
        if ($hay === '') {
            continue;
        }
        foreach ($keywords as $kw) {
            if ($kw !== '' && strpos($hay, $kw) !== false) {
                return $slug;
            }
        }
    }
    return null;
}

// Monta a lista de planos a exibir, evitando duplicações
$planOptions = [];
foreach ($order as $slug) {
    if (isset($plans[$slug])) {
        $planOptions[$slug] = $plans[$slug];
        continue;
    }
    foreach ($aliasOrderMap[$slug] ?? [] as $alias) {
        if (isset($plans[$alias])) {
            $planOptions[$alias] = $plans[$alias];
            break;
        }
    }
}
if (!$planOptions) {
    $planOptions = $plans;
}

$selected = isset($_GET['plan']) ? strtolower((string) $_GET['plan']) : '';
if ($selected === '') {
    $selected = array_key_first($planOptions) ?: 'essencial';
}
if (!isset($planOptions[$selected])) {
    foreach ($aliasOrderMap[$selected] ?? [] as $alias) {
        if (isset($planOptions[$alias])) {
            $selected = $alias;
            break;
        }
    }
}
// tenta localizar diretamente no catálogo completo (ex.: ?plan=trimestral)
if (!isset($planOptions[$selected]) && isset($plans[$selected])) {
    $planOptions[$selected] = $plans[$selected];
}
// tenta localizar por ciclo/palavras-chave (ex.: ?plan=trimestral)
if (!isset($planOptions[$selected])) {
    $match = null;
    switch ($selected) {
        case 'trimestral':
        case 'trimestre':
        case '3meses':
        case '3-meses':
        case '3m':
            $match = find_plan_by_cycle_or_keyword($plans, 'quarterly', ['trimestral', '3 meses', '3 mês']);
            break;
        case 'semestral':
        case '6meses':
        case '6-meses':
        case '6m':
            $match = find_plan_by_cycle_or_keyword($plans, 'semiannual', ['semestral', '6 meses', '6 mês']);
            break;
        case 'anual':
        case '12meses':
        case '12-meses':
        case '12m':
            $match = find_plan_by_cycle_or_keyword($plans, 'yearly', ['anual', '12 meses', '12 mês']);
            break;
        case 'mensal':
        default:
            $match = find_plan_by_cycle_or_keyword($plans, 'monthly', ['mensal']);
            break;
    }
    if ($match !== null && isset($plans[$match])) {
        $planOptions[$match] = $plans[$match];
        $selected = $match;
    }
}
if (!isset($planOptions[$selected])) {
    foreach ($aliasOrderMap as $canonical => $aliases) {
        if (in_array($selected, $aliases, true) && isset($planOptions[$canonical])) {
            $selected = $canonical;
            break;
        }
    }
}
if (!isset($planOptions[$selected])) {
    $selected = array_key_first($planOptions);
}
$current = $planOptions[$selected] ?? reset($planOptions);
$currentMonthly = (float) ($current['amount'] ?? 0);
$currentMonths = mpago_plan_duration_months($current);
$currentCharge = mpago_plan_charge_amount($current);
$currentDisplay = $currentMonths > 1
    ? ('<span class="installments">' . $currentMonths . 'x de</span> R$ ' . number_format($currentMonthly, 2, ',', '.'))
    : ('R$ ' . number_format($currentMonthly, 2, ',', '.'));

$title = 'Pagamento seguro | NutremFit';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>
<body class="area-theme" data-spy="scroll" data-offset="80">

  <?php include './partials/preloader.php'; ?>
  <?php include './partials/header.php'; ?>

  <style>
    :root {
      --accent: #ff6b35;
      --accent-2: #e358ff;
      --dark-1: #0a0d14;
      --dark-2: #111728;
      --card: #0f1320;
    }
    .checkout-hero {
      background: radial-gradient(circle at 10% 20%, rgba(255,107,53,0.25), transparent 25%),
                  radial-gradient(circle at 85% 10%, rgba(227,88,255,0.22), transparent 26%),
                  linear-gradient(135deg,#0a0d14,#0c1221 55%,#0a0d14);
      color:#fff;
      padding: 90px 0 70px;
      position: relative;
      overflow: hidden;
    }
    .checkout-hero::after {
      content:'';
      position:absolute;
      right:-120px;top:-160px;
      width:320px;height:320px;
      background: radial-gradient(circle, rgba(255,255,255,0.06), transparent 50%);
      transform: rotate(18deg);
    }
    .checkout-card {
      background: var(--card);
      border: 1px solid rgba(255,255,255,0.06);
      box-shadow: 0 30px 120px rgba(0,0,0,0.55);
      border-radius: 22px;
      padding: 26px 26px 30px;
    }
    .plan-pill {
      border: 1px solid rgba(255,255,255,0.08);
      background: rgba(255,255,255,0.02);
      border-radius: 50px;
      padding: 10px 16px;
      color:#fff;
      display:inline-flex;
      align-items:center;
      gap:10px;
      font-size:14px;
    }
    .plan-option {
      background: #0c111d;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 18px;
      padding: 18px;
      color:#fff;
      transition: all 0.25s ease;
      cursor: pointer;
      height: 100%;
    }
    .plan-option input { position:absolute; opacity:0; pointer-events:none; }
    .plan-option.active {
      border-color: rgba(255,107,53,0.6);
      box-shadow: 0 12px 30px rgba(255,107,53,0.25);
      transform: translateY(-3px);
    }
    .plan-option small {color: rgba(255,255,255,0.65);}
    .price-tag { font-size: 26px; font-weight: 700; color: #fff; }
    .btn-mp {
      background: linear-gradient(120deg,var(--accent),#ff864f);
      border: none;
      border-radius: 16px;
      color: #fff;
      font-weight: 700;
      padding: 14px 18px;
      width: 100%;
      box-shadow: 0 16px 45px rgba(255,107,53,0.32);
    }
    .btn-mp:hover {background: linear-gradient(120deg,#ff7f4c,#ff9d73);}
    .badge-test {
      background: rgba(255,255,255,0.1);
      color: #fff;
      padding: 4px 10px;
      border-radius: 10px;
      font-size: 12px;
      border: 1px dashed rgba(255,255,255,0.35);
    }
    .installments {
      font-size: 11px;
      opacity: 0.65;
      letter-spacing: 0.2px;
      color: rgba(255,255,255,0.7);
    }
    @media (max-width: 767px) {
      .checkout-hero {padding: 64px 0 46px;}
      .checkout-card {padding: 18px 16px 22px;}
      .plan-option {height: auto;}
      .plan-option strong {font-size: 15px;}
      .plan-option small {display: inline-block; margin-top: 4px;}
      .price-tag {font-size: 22px;}
      .checkout-hero h1 {font-size: 28px; line-height: 1.25;}
      .plan-pill {width: 100%; justify-content: center;}
    }
  </style>

  <section class="checkout-hero">
    <div class="container">
      <div class="row align-items-start g-4">
        <div class="col-lg-6">
          <div class="wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
            <span class="plan-pill"><i class="ti-shield"></i> Checkout seguro e autenticado</span>
            <h1 class="mt-3" style="font-weight:800;letter-spacing:-0.5px;">Escolha seu plano para liberar o seu acesso</h1>
            <p style="color:rgba(255,255,255,0.8);max-width:520px;">Assim que o pagamento for confirmado, você receberá acesso imediato à Área do Aluno e um e-mail de boas-vindas com todas as orientações para começar.</p>
            <ul style="padding-left:18px;color:rgba(255,255,255,0.78);">
              <li>Pagamento 100% seguro</li>
              <li>Acesso liberado assim que a compra for confirmada</li>
            </ul>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="checkout-card wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s">
            <form autocomplete="on" id="mp-checkout-form">
              <input type="hidden" name="plan" id="plan-input" value="<?php echo htmlspecialchars($current['slug'], ENT_QUOTES, 'UTF-8'); ?>">
              <div class="mb-3 d-flex justify-content-between align-items-center">
                <div>
                  <p class="mb-1" style="color:rgba(255,255,255,0.65);">Plano selecionado</p>
                  <div class="price-tag" id="price-tag"><?php echo $currentDisplay; ?></div>
                  <small id="plan-desc" style="color:rgba(255,255,255,0.65);"><?php echo htmlspecialchars($current['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  <small id="plan-cycle-note" style="color:rgba(255,255,255,0.55);">
                    <?php if ($currentMonths > 1): ?>
                      <?php echo 'Total do plano: R$ ' . number_format($currentCharge, 2, ',', '.'); ?>
                    <?php else: ?>
                      Pagamento mensal.
                    <?php endif; ?>
                  </small>
                </div>
              </div>
              <div class="row g-3 mb-3">
                <?php foreach ($planOptions as $slug => $p): ?>
                  <?php
                    $pMonthly = (float) ($p['amount'] ?? 0);
                    $pMonths = mpago_plan_duration_months($p);
                    $pCharge = mpago_plan_charge_amount($p);
                    $pDisplay = $pMonths > 1
                      ? ('<span class="installments">' . $pMonths . 'x de</span> R$ ' . number_format($pMonthly, 2, ',', '.'))
                      : ('R$ ' . number_format($pMonthly, 2, ',', '.'));
                  ?>
                  <div class="col-md-6">
                    <label class="plan-option <?php echo $slug === $current['slug'] ? 'active' : ''; ?>">
                      <input type="radio" name="plan_radio" value="<?php echo $slug; ?>" <?php echo $slug === $current['slug'] ? 'checked' : ''; ?> data-monthly="<?php echo number_format($pMonthly, 2, '.', ''); ?>" data-months="<?php echo (int) $pMonths; ?>" data-total="<?php echo number_format($pCharge, 2, '.', ''); ?>" data-desc="<?php echo htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-slug="<?php echo $slug; ?>">
                      <div class="d-flex justify-content-between">
                        <div>
                          <strong style="font-size:16px;"><?php echo htmlspecialchars($p['name'] ?? $slug, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                          <small><?php echo htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <div class="text-end">
                          <span class="price-tag" style="font-size:18px;"><?php echo $pDisplay; ?></span>
                          <?php if ($pMonths > 1): ?>
                            <small class="d-block" style="color:rgba(255,255,255,0.6);">Total R$ <?php echo number_format($pCharge, 2, ',', '.'); ?></small>
                          <?php endif; ?>
                          <?php if (!empty($p['is_test'])): ?>
                            <div class="badge-test mt-1">Teste</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label for="name">Nome completo</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="Como aparecerá no acesso" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-2">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="usuario@email.com" required>
                  </div>
                </div>
                <div class="col-md-12">
                  <div class="form-group mb-2">
                    <label for="phone">Telefone/contato (opcional)</label>
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="+55 31 90000-0000">
                  </div>
                </div>
              </div>

              <div class="mt-3">
                <div class="form-group mb-3" style="color:rgba(255,255,255,0.85);">
                  <label class="d-flex align-items-start gap-2" style="cursor:pointer;">
                    <input type="checkbox" name="terms_accept" value="1" required style="margin-top:4px;">
                    <span>Aceito o <a href="<?php echo htmlspecialchars(function_exists('nf_url') ? nf_url('/termos-consentimento') : '/termos-consentimento.php', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-warning" style="text-decoration:underline;">Termo de Consentimento, Responsabilidade e Declaração de Saúde</a>.</span>
                  </label>
                  <small class="d-block mt-1" style="color:rgba(255,255,255,0.75);">Clique para ler antes de finalizar — o documento abrirá em outra aba.</small>
                  <small class="d-block" style="color:rgba(255,255,255,0.75);">Você será direcionado para a página de pagamento segura.</small>
                  <small class="d-block" style="color:rgba(255,255,255,0.75);">O acesso à Área do Aluno é liberado automaticamente assim que o pagamento for confirmado.</small>
                </div>
                <button type="submit" class="btn-mp" id="btn-submit">Finalizar pagamento</button>
                <p class="small mt-2 mb-0" style="color:rgba(255,255,255,0.65);">O checkout abrirá em nova aba. Esta tela continua acompanhando o pagamento.</p>
                <div id="payment-status" class="alert alert-info mt-2" role="alert" style="display:none;background:rgba(255,255,255,0.08);border:none;color:#fff;">
                  Aguardando pagamento...
                </div>
                <button type="button" id="btn-refresh" class="btn btn-sm btn-secondary mt-2" style="display:none;">Pago? Verificar agora</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include './partials/footer.php'; ?>
  <?php include './partials/script.php'; ?>

  <script>
    // Atualiza UI ao trocar de plano
    document.querySelectorAll('.plan-option input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        document.querySelectorAll('.plan-option').forEach(function (el) { el.classList.remove('active'); });
        var wrapper = this.closest('.plan-option');
        if (wrapper) wrapper.classList.add('active');

        var monthly = Number(this.dataset.monthly || '0');
        var months = Number(this.dataset.months || '1');
        var total = Number(this.dataset.total || monthly);
        var desc  = this.dataset.desc || '';
        var slug  = this.dataset.slug || 'essencial';
        document.getElementById('plan-input').value = slug;
        var display = months > 1
          ? ('<span class="installments">' + months + 'x de</span> R$ ' + monthly.toLocaleString('pt-BR', {minimumFractionDigits:2}))
          : ('R$ ' + monthly.toLocaleString('pt-BR', {minimumFractionDigits:2}));
        document.getElementById('price-tag').innerHTML = display;
        document.getElementById('plan-desc').innerText = desc;
        var cycleNote = document.getElementById('plan-cycle-note');
        if (cycleNote) {
          if (months > 1) {
            cycleNote.innerText = 'Total do plano: R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2});
          } else {
            cycleNote.innerText = 'Pagamento mensal.';
          }
        }
      });
    });

    // Cria preferência, abre checkout em nova aba e acompanha pelo preference_id
    (function() {
      var form = document.getElementById('mp-checkout-form');
      var statusBox = document.getElementById('payment-status');
      var submitBtn = document.getElementById('btn-submit');
      var refreshBtn = document.getElementById('btn-refresh');
      var poll = null;
      var pollTick = 0;
      var pollingInFlight = false;
      var prefUrl = '/mp-create-preference';
      var prefUrlAlt = '/mp-create-preference.php';
      var prefUrlAbs = <?php echo json_encode(function_exists('nf_url') ? nf_url('/mp-create-preference') : (rtrim($config['app_url'], '/') . '/mp-create-preference.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      var checkUrl = '/mp-check-preference';
      var checkUrlAlt = '/mp-check-preference.php';
      var checkUrlAbs = <?php echo json_encode(function_exists('nf_url') ? nf_url('/mp-check-preference') : (rtrim($config['app_url'], '/') . '/mp-check-preference.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      var checkEmailUrl = '/mp-check-payment';
      var checkEmailUrlAlt = '/mp-check-payment.php';
      var checkEmailUrlAbs = <?php echo json_encode(function_exists('nf_url') ? nf_url('/mp-check-payment') : (rtrim($config['app_url'], '/') . '/mp-check-payment.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      var lastPref = null;
      var lastEmail = null;
      var termsCheckbox = document.querySelector('input[name="terms_accept"]');

      if (termsCheckbox) {
        termsCheckbox.addEventListener('invalid', function (e) {
          e.target.setCustomValidity('Selecione essa caixa para continuar.');
        });
        termsCheckbox.addEventListener('change', function (e) {
          if (e.target.checked) {
            e.target.setCustomValidity('');
          }
        });
      }

      function updateStatus(text) {
        statusBox.style.display = 'block';
        statusBox.innerText = text;
      }

      function setLoading(state) {
        if (state) {
          submitBtn.setAttribute('disabled', 'disabled');
          submitBtn.innerText = 'Gerando checkout...';
        } else {
          submitBtn.removeAttribute('disabled');
          submitBtn.innerText = 'Finalizar pagamento';
        }
      }

      form.addEventListener('submit', async function(e) {
        e.preventDefault();
        var email = (document.getElementById('email').value || '').trim();
        if (!email) { alert('Informe um e-mail.'); return; }
        if (!document.querySelector('input[name="terms_accept"]:checked')) { alert('Aceite o termo.'); return; }

        var name = (document.getElementById('name').value || '').trim();
        var phone = (document.getElementById('phone').value || '').trim();
        var plan = (document.getElementById('plan-input').value || 'essencial');
        lastEmail = email;

        var formData = new FormData();
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('plan', plan);
        formData.append('terms_accept', '1');

        setLoading(true);
        try {
          var resp = await fetch(prefUrl, { method: 'POST', body: formData });
          console.log('[checkout] create-preference fetch', { url: prefUrl, status: resp.status, responseURL: resp.url });
          var needsRetry = (!resp.ok || (resp.url && resp.url.indexOf('/home2/') !== -1));
          if (needsRetry && prefUrlAlt !== prefUrl) {
            resp = await fetch(prefUrlAlt, { method: 'POST', body: formData });
            console.log('[checkout] create-preference retry', { url: prefUrlAlt, status: resp.status, responseURL: resp.url });
            needsRetry = (!resp.ok || (resp.url && resp.url.indexOf('/home2/') !== -1));
          }
          if (needsRetry) {
            resp = await fetch(prefUrlAbs, { method: 'POST', body: formData });
            console.log('[checkout] create-preference retry-abs', { url: prefUrlAbs, status: resp.status, responseURL: resp.url });
          }
          if (!resp.ok) {
            var text = await resp.text().catch(() => '');
            console.error('[checkout] erro HTTP ao criar preferencia', resp.status, text);
            throw new Error('HTTP ' + resp.status);
          }
          var data = await resp.json();
          console.log('[checkout] create-preference response', data);
          if (data && data.preference_id && data.init_point) {
            lastPref = data.preference_id;
            updateStatus('Aguardando pagamento...');
            if (refreshBtn) {
              refreshBtn.style.display = 'inline-block';
              refreshBtn.setAttribute('data-pref', data.preference_id);
              refreshBtn.innerText = 'Pago? Verificar agora';
            }
            var checkoutLink = document.createElement('a');
            checkoutLink.href = data.init_point;
            checkoutLink.target = '_blank';
            checkoutLink.rel = 'noopener';
            checkoutLink.click();
            startPolling(data.preference_id);
          } else {
            console.error('[checkout] missing preference/init_point', data);
            alert('Não foi possível criar o checkout. Tente novamente.');
          }
        } catch (err) {
          console.error('[checkout] erro ao criar preferencia', { error: err, url: prefUrl });
          alert('Falha ao criar o checkout. Verifique sua conexão e tente novamente.');
        } finally {
          setLoading(false);
        }
      });

      function startPolling(prefId) {
        if (poll) clearInterval(poll);
        lastPref = prefId;
        pollTick = 0;
        updateStatus('Aguardando pagamento...');
        var body = 'preference_id=' + encodeURIComponent(prefId);

        async function doPoll() {
          if (pollingInFlight) return;
          pollingInFlight = true;
          pollTick += 1;
          try {
            async function doCheck(url) {
              try {
                var r = await fetch(url, {
                  method: 'POST',
                  headers: {'Content-Type':'application/x-www-form-urlencoded'},
                  body: body
                });
                console.log('[checkout] poll status', r.status, url);
                return r;
              } catch (e) {
                console.error('[checkout] poll fetch error', url, e);
                return null;
              }
            }

            var resp = await doCheck(checkUrl);
            if ((!resp || !resp.ok) && checkUrlAlt !== checkUrl) {
              resp = await doCheck(checkUrlAlt);
            }
            if ((!resp || !resp.ok) && checkUrlAbs !== checkUrl) {
              resp = await doCheck(checkUrlAbs);
            }

            if (resp.ok) {
              var data = await resp.json();
              console.log('[checkout] poll response', data);
              if (data && data.status === 'approved') {
                clearInterval(poll);
                updateStatus('Pagamento confirmado! Redirecionando...');
                var redirect = data.redirect || (data.payment_id ? '/payment-return?payment_id=' + encodeURIComponent(data.payment_id) + '&status=approved' : null);
                if (redirect) window.location.href = redirect;
                pollingInFlight = false;
                return;
              }
              var ts = new Date().toLocaleTimeString('pt-BR');
              updateStatus('Aguardando pagamento... (status: ' + (data.status || 'pending') + ' às ' + ts + ')');
            } else {
              updateStatus('Aguardando pagamento... (checagem falhou, tentando novamente)');
            }
            // fallback por e-mail
            if (lastEmail) {
              var respEmail = await doCheck(checkEmailUrl);
              if ((!respEmail || !respEmail.ok) && checkEmailUrlAlt !== checkEmailUrl) {
                respEmail = await doCheck(checkEmailUrlAlt);
              }
              if ((!respEmail || !respEmail.ok) && checkEmailUrlAbs !== checkEmailUrl) {
                respEmail = await doCheck(checkEmailUrlAbs);
              }
              if (respEmail && respEmail.ok) {
                var dataEmail = await respEmail.json();
                console.log('[checkout] poll email response', dataEmail);
                if (dataEmail && dataEmail.status === 'approved') {
                  clearInterval(poll);
                  updateStatus('Pagamento confirmado! Redirecionando...');
                  var redirect2 = dataEmail.redirect || (dataEmail.payment_id ? '/payment-return?payment_id=' + encodeURIComponent(dataEmail.payment_id) + '&status=approved' : null);
                  if (redirect2) window.location.href = redirect2;
                  pollingInFlight = false;
                  return;
                }
                var ts2 = new Date().toLocaleTimeString('pt-BR');
                updateStatus('Aguardando pagamento... (última verificação às ' + ts2 + ')');
              }
            }
          } catch (e) {
            console.error('[checkout] erro no polling', e);
          }
          pollingInFlight = false;
        }

        doPoll(); // primeira checagem imediata
        poll = setInterval(doPoll, 5000);
      }

      if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
          var manualPref = refreshBtn.getAttribute('data-pref') || lastPref;
          if (!manualPref) {
            alert('Nenhum pagamento em processamento.');
            return;
          }
          updateStatus('Confirmando pagamento...');
          startPolling(manualPref);
        });
      }
    })();
  </script>

</body>
</html>
