<?php
// Redireciona URLs quebradas que trazem o caminho de servidor
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/home2/edua0932/public_html') !== false) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'nutremfit.com.br';
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = str_replace('\\', '/', dirname($scriptName));
    if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
        $basePath = '';
    } else {
        $basePath = rtrim($basePath, '/');
    }
    header('Location: ' . $scheme . '://' . $host . $basePath . '/pricing.php', true, 301);
    exit;
}

require_once __DIR__ . '/includes/mpago.php';
$title = 'NutremFit | Planos e preços';
$cssInline = '';
$catalog = mpago_plan_catalog();
$getPlan = function(string $slug, float $fallbackAmount, string $fallbackName, string $fallbackDesc = '', string $cycle = 'monthly') use ($catalog) {
    if (isset($catalog[$slug])) {
        $p = $catalog[$slug];
        return [
            'name' => $p['name'] ?? $fallbackName,
            'amount' => (float) ($p['amount'] ?? $fallbackAmount),
            'desc' => $p['description'] ?? $fallbackDesc,
            'cycle' => $p['cycle'] ?? $cycle,
        ];
    }
    return ['name' => $fallbackName, 'amount' => $fallbackAmount, 'desc' => $fallbackDesc, 'cycle' => $cycle];
};
$pEss = $getPlan('essencial', 189.90, 'Mensal - Plano starter', 'Mensal — Plano starter. Atualização mediante renovação e pagamento mensal sem fidelidade.');
$pPerf = $getPlan('performance', 169.90, 'Semestral — Plano contínuo', 'Semestral — Plano contínuo (mais vendido). Ajustes programados a cada 30 dias por 6 meses.');
$pVip = $getPlan('vip', 149.90, 'Anual - Plano completo', 'Anual — Plano completo. Ajustes programados a cada 30 dias por 12 meses.');
$pPerfMonths = 6;
$pVipMonths = 12;
$pPerfTotal = $pPerf['amount'] * $pPerfMonths;
$pVipTotal = $pVip['amount'] * $pVipMonths;
$economySemestral = 240.00;
$economyAnual = 480.00;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'?>

    <body data-spy="scroll" data-offset="80">

		<?php include './partials/preloader.php'?>
		<?php include './partials/header.php'?>	

		<!-- START SECTION TOP -->
		<section class="section-top">
			<div class="container">
				<div class="col-lg-10 offset-lg-1 text-center">
					<div class="section-top-title wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.3s" data-wow-offset="0">
						<h1>Planos e preços</h1>
						<ul>
							<li><a href="/">Início</a></li>
							<li> / Planos e preços</li>
						</ul>
					</div><!-- //.HERO-TEXT -->
				</div><!--- END COL -->
			</div><!--- END CONTAINER -->
		</section>	
		<!-- END SECTION TOP -->
		
		<!-- START PRICING-->
		<section class="plan_home_area section-padding">
		   <div class="container">	
				<div class="section-title text-center">
					<span>Planos de Assinatura</span>
					<h2>Escolha a forma mais vantajosa de receber seus programas mensais</h2>
					<p>Nos planos semestral e anual, você recebe ajustes automáticos a cada 30 dias — a forma mais eficiente, econômica e estruturada de evoluir.</p>
				</div>			
				<div class="row">								
					<div class="col-lg-4 col-sm-4 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
						<div class="pricingTable">
							<div class="pricingTable-header">
								<h3 class="title"><?php echo htmlspecialchars($pEss['name'], ENT_QUOTES, 'UTF-8');?></h3>
							</div>
							<div class="pricing-icon">
								<i class="ti-medall"></i>
							</div>
							<ul class="pricing-content">
								<li><?php echo htmlspecialchars($pEss['desc'], ENT_QUOTES, 'UTF-8');?></li>
								<li>Treino personalizado + plano alimentar</li>
								<li>Vídeos de execução para cada exercício</li>
								<li>Acesso total à plataforma</li>
								<li>Atualização mediante renovação</li>
								<li>Pagamento mensal sem fidelidade</li>
							</ul>
							<div class="price-value">
								<span class="amount">R$ <?php echo number_format($pEss['amount'], 2, ',', '.');?></span>
								<span class="duration">/mês</span>
							</div>
							<div>
                                <a href="/pagamento?plan=essencial" class="btn_one">Assinar agora</a>
							</div>
						</div>
					</div><!-- END COL-->												
					<div class="col-lg-4 col-sm-4 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
						<div class="pricingTable">
							<div class="pricingTable-header">
								<h3 class="title"><?php echo htmlspecialchars($pPerf['name'], ENT_QUOTES, 'UTF-8');?></h3>
								<span class="badge bg-light text-dark">Mais vendido</span>
							</div>
							<div class="pricing-icon">
								<i class="ti-server"></i>
							</div>
							<ul class="pricing-content">
								<li><?php echo htmlspecialchars($pPerf['desc'], ENT_QUOTES, 'UTF-8');?></li>
								<li>1 plano alimentar por mês</li>
								<li>1 treino personalizado por mês</li>
								<li>Vídeos de execução para cada exercício</li>
								<li>Acesso total à plataforma + histórico</li>
								<li>Ajustes programados a cada 30 dias por 6 meses</li>
							</ul>
							<div class="price-value">
								<span class="amount"><span style="font-size:12px;opacity:.75;"><?php echo $pPerfMonths; ?>x de</span> R$ <?php echo number_format($pPerf['amount'], 2, ',', '.');?></span>
								<span class="duration">/mês</span>
							</div>
                            <p class="mb-1" style="color:rgba(255,255,255,0.8);">Total R$ <?php echo number_format($pPerfTotal, 2, ',', '.');?></p>
                            <p class="mb-1" style="color:rgba(255,255,255,0.8);">R$ <?php echo number_format($economySemestral, 2, ',', '.');?> de economia comparado ao plano mensal</p>
							<div>
                                <a href="/pagamento?plan=performance" class="btn_one">Assinar agora</a>
							</div>
						</div>
					</div><!-- END COL-->												
					<div class="col-lg-4 col-sm-4 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
						<div class="pricingTable">
							<div class="pricingTable-header">
								<h3 class="title"><?php echo htmlspecialchars($pVip['name'], ENT_QUOTES, 'UTF-8');?></h3>
							</div>
							<div class="pricing-icon">
								<i class="ti-cup"></i>
							</div>
							<ul class="pricing-content">
								<li><?php echo htmlspecialchars($pVip['desc'], ENT_QUOTES, 'UTF-8');?></li>
								<li>1 plano alimentar por mês</li>
								<li>1 treino personalizado por mês</li>
								<li>Vídeos de execução para cada exercício</li>
								<li>Acesso total à plataforma + histórico completo</li>
								<li>Ajustes programados a cada 30 dias por 12 meses</li>
							</ul>
							<div class="price-value">
								<span class="amount"><span style="font-size:12px;opacity:.75;"><?php echo $pVipMonths; ?>x de</span> R$ <?php echo number_format($pVip['amount'], 2, ',', '.');?></span>
								<span class="duration">/mês</span>
							</div>
                            <p class="mb-1" style="color:rgba(255,255,255,0.8);">Total R$ <?php echo number_format($pVipTotal, 2, ',', '.');?></p>
                            <p class="mb-1" style="color:rgba(255,255,255,0.8);">R$ <?php echo number_format($economyAnual, 2, ',', '.');?> de economia comparado ao plano mensal</p>
							<div>
                                <a href="/pagamento?plan=vip" class="btn_one">Assinar agora</a>
							</div>
						</div>
					</div><!-- END COL-->																				
				</div><!-- END ROW -->
			</div><!-- END CONTAINER -->
		</section>
		<!-- END PRICING -->	
	
	<!--<< Footer Section Start >>-->
	<?php include './partials/footer.php'?>

	<!--<< All JS Plugins >>-->
	<?php include './partials/script.php'?>	
    </body>
</html>
