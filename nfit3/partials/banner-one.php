<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<?php $nfPricingUrl = function_exists('nf_url') ? nf_url('/pricing') : '/pricing'; ?>
<!-- START HOME -->
<section class="home_bg hb_height" style="background-image: url(assets/img/bg/home-bg.jpg);  background-size:cover; background-position: center center;">
  <div class="container">
    <div class="row">
      <div class="col-lg-7 col-sm-12 col-xs-12">
        <div class="hero-text ht_top">
          <h1>Nutremfit <br>Treinos e planos alimentares 100% personalizados,</h1>
          <p>elaborados por profissionais de forma integrada para potencializar os seus resultados</p>
                   </div>
        <div class="home_btns">
          <a
            href="<?php echo htmlspecialchars($nfPricingUrl, ENT_QUOTES, 'UTF-8'); ?>"
            class="btn_one"
            data-hero-plan-btn>
            Quero meu plano agora
          </a>
        </div>
      </div><!--- END COL -->
    </div><!--- END ROW -->
  </div><!--- END CONTAINER -->
</section>
<!-- END HOME -->
<script>
  (function() {
    var targetUrl = <?php echo json_encode($nfPricingUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    // Se já estiver na URL quebrada, corrige imediatamente
    var bad = '/home2/edua0932/public_html/pricing';
    if (location.pathname.indexOf(bad) !== -1) {
      location.replace(targetUrl);
      return;
    }

    function lockHeroButton() {
      var btn = document.querySelector('[data-hero-plan-btn]');
      if (!btn) return;
      btn.setAttribute('href', targetUrl);
      btn.href = targetUrl; // força o href absoluto
    }
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest('[data-hero-plan-btn]');
      if (!btn) return;
      ev.preventDefault();
      window.location.assign(targetUrl);
    }, true);
    document.addEventListener('DOMContentLoaded', function () {
      lockHeroButton();
      // reforço para ambientes com cache ou reescrita
      setTimeout(lockHeroButton, 500);
      setTimeout(lockHeroButton, 1500);
    });
  })();
</script>
