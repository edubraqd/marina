<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<?php $nfPricingUrl = function_exists('nf_url') ? nf_url('/pricing') : '/pricing'; ?>
<?php $nfContactUrl = function_exists('nf_url') ? nf_url('/contact') : '/contact.php'; ?>
<?php $nfAreaLoginUrl = function_exists('nf_url') ? nf_url('/area-login') : '/area-login.php'; ?>
<?php $nfHomeUrl = function_exists('nf_url') ? nf_url('/') : '/'; ?>
<!-- START NAVBAR -->  
<div id="navigation" class="navbar-light bg-faded site-navigation">
  <div class="container-fluid">
    <div class="row">
      <div class="col-20 align-self-center">
        <div class="site-logo">
          <a href="<?php echo htmlspecialchars($nfHomeUrl, ENT_QUOTES, 'UTF-8'); ?>"><img src="assets/img/logo-top.png" alt="NutremFit"></a>
        </div>
      </div><!--- END Col -->
      
      <div class="col-60 d-flex justify-content-center">
        <nav id="main-menu">
          <ul>
            <li><a href="<?php echo htmlspecialchars($nfHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Início</a></li>
            <li><a href="<?php echo htmlspecialchars($nfPricingUrl, ENT_QUOTES, 'UTF-8'); ?>">Planos e preços</a></li>
            <li><a href="<?php echo htmlspecialchars($nfContactUrl, ENT_QUOTES, 'UTF-8'); ?>">Contato</a></li>
            <li><a href="<?php echo htmlspecialchars($nfAreaLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">Área do aluno</a></li>
          </ul>
        </nav>
      </div><!--- END Col -->
      
      <div class="col-20 d-none d-xl-block text-end align-self-center">
        <a href="<?php echo htmlspecialchars($nfPricingUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn_one">Quero meu plano</a>
      </div><!--- END Col -->
      
      <ul class="mobile_menu">
        <li><a href="<?php echo htmlspecialchars($nfHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Início</a></li>
        <li><a href="<?php echo htmlspecialchars($nfPricingUrl, ENT_QUOTES, 'UTF-8'); ?>">Planos e preços</a></li>
        <li><a href="<?php echo htmlspecialchars($nfContactUrl, ENT_QUOTES, 'UTF-8'); ?>">Contato</a></li>
        <li><a href="<?php echo htmlspecialchars($nfAreaLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">Área do aluno</a></li>
      </ul>
    </div><!--- END ROW -->
  </div><!--- END CONTAINER -->
</div>
<!-- END NAVBAR -->
