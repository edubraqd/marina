<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<?php $nfPricingUrl = function_exists('nf_url') ? nf_url('/pricing') : '/pricing'; ?>
<?php $nfContactUrl = function_exists('nf_url') ? nf_url('/contact') : '/contact.php'; ?>
<?php $nfHomeUrl = function_exists('nf_url') ? nf_url('/') : '/'; ?>
<!-- START FOOTER -->
<div class="footer section-padding" style="background-image: url(assets/img/bg/section-3.jpg);  background-size:cover; background-position: center center;">
  <div class="container">
    <div class="row">
      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="single_footer">
          <a href="<?php echo htmlspecialchars($nfHomeUrl, ENT_QUOTES, 'UTF-8'); ?>"><img src="assets/img/logo.png" alt="NutremFit"></a>
          <p>Planos alimentares e treinos personalizados, com ajustes mensais e suporte próximo com profissionais reais.</p>
          <div class="social_profile">
            <ul>
              <li><a href="https://www.instagram.com/nutremfit" class="f_instagram" target="_blank" rel="noopener noreferrer"><i class="ti-instagram" title="Instagram"></i></a></li>
            </ul>
          </div>
        </div>
      </div><!--- END COL -->

      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="single_footer">
          <h4>Sobre</h4>
          <ul>
            <li><a href="<?php echo htmlspecialchars($nfPricingUrl, ENT_QUOTES, 'UTF-8'); ?>">Planos e preços</a></li>
            <li><a href="<?php echo htmlspecialchars($nfContactUrl, ENT_QUOTES, 'UTF-8'); ?>">Contato</a></li>
          </ul>
        </div>
      </div><!--- END COL -->

      <div class="col-lg-3 col-sm-6 col-xs-12">
        <div class="single_footer">
          <h4>Atendimento</h4>
          <p>E-mail: <a href="mailto:suporte@nutremfit.com.br?subject=NutremFit%20|%20Atendimento">suporte@nutremfit.com.br</a><br><small>Retorno em até 24h úteis</small></p>
          <p class="mb-2"><a href="https://wa.me/553173274909" target="_blank" rel="noopener noreferrer">WhatsApp</a><br><small>Retorno rápido em horário comercial</small></p>
  
        </div>
      </div><!--- END COL -->
    </div><!--- END ROW -->

    <div class="row fc">
      <div class="col-lg-6 col-sm-6 col-xs-12">
        <div class="footer_copyright">
          <p>&copy; 2025 NutremFit. Todos os direitos reservados.</p>
        </div>
      </div>
    </div>
  </div><!--- END CONTAINER -->
</div>
<!-- END FOOTER -->
