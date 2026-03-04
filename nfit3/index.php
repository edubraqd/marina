<!DOCTYPE html>
<html lang="pt-BR">

<?php
$title="NutremFit | Experiência completa em nutrição + tecnologia";
$preloadImage = 'assets/img/bg/home-bg.jpg';
// Corrige acessos que chegam com caminho físico do servidor
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/home2/edua0932/public_html/index') !== false) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'nutremfit.com.br';
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = str_replace('\\', '/', dirname($scriptName));
    if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
        $basePath = '';
    } else {
        $basePath = rtrim($basePath, '/');
    }
    header('Location: ' . $scheme . '://' . $host . $basePath . '/index.php', true, 301);
    exit;
}
require_once __DIR__ . '/includes/mpago.php';
$catalog = mpago_plan_catalog();

// filtra planos ativos (exceto testes) e ordena por prioridade
$catalog = array_filter($catalog, function($p){
    return empty($p['is_test']);
});
$order = ['essencial', 'performance', 'vip'];
$orderedCatalog = [];
$pricesBySlug = [];
foreach ($order as $slug) {
    if (isset($catalog[$slug])) {
        $orderedCatalog[] = $catalog[$slug];
        $pricesBySlug[$slug] = (float) ($catalog[$slug]['amount'] ?? 0);
        unset($catalog[$slug]);
    }
}
// acrescenta eventuais extras ao final
foreach ($catalog as $extra) {
    $orderedCatalog[] = $extra;
    if (isset($extra['slug'])) {
        $pricesBySlug[$extra['slug']] = (float) ($extra['amount'] ?? 0);
    }
}
$catalog = $orderedCatalog;

// features padrão por plano (ajuste aqui se quiser personalizar por slug)
$defaultFeatures = [
    'essencial' => [
        'Treino personalizado + plano alimentar',
        'Vídeos de execução para cada exercício',
        'Acesso total à plataforma',
        'Atualização mediante renovação',
        'Pagamento mensal sem fidelidade',
    ],
    'performance' => [
        '1 plano alimentar por mês',
        '1 treino personalizado por mês',
        'Vídeos de execução para cada exercício',
        'Acesso total à plataforma + histórico',
        'Ajustes programados a cada 30 dias por 6 meses',
    ],
    'vip' => [
        '1 plano alimentar por mês',
        '1 treino personalizado por mês',
        'Vídeos de execução para cada exercício',
        'Acesso total à plataforma + histórico completo',
        'Ajustes programados a cada 30 dias por 12 meses',
    ],
    'plano' => [
        'Treino personalizado + plano alimentar',
        'Vídeos de execução para cada exercício',
        'Acesso total à plataforma',
        'Ajustes programados conforme ciclo',
    ],
];

function nf_plan_features(array $plan): array {
    global $defaultFeatures;
    $slug = $plan['slug'] ?? 'plano';
    return $defaultFeatures[$slug] ?? $defaultFeatures['plano'];
}

include './partials/head.php'?>
	
    <body data-spy="scroll" data-offset="80">

		<?php include './partials/preloader.php'?>
		<?php include './partials/header.php'?>	

        <div class="mobile-cta d-md-none">
          <a href="https://nutremfit.com.br/pricing" class="btn_one">Quero meu plano agora</a>
        </div>

		<?php include './partials/banner-one.php'?>	

        <style>
          .neon-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 40px;
            background: rgba(255,107,53,0.18);
            border: 1px solid rgba(255,107,53,0.4);
            color: #fff;
            backdrop-filter: blur(12px);
            font-size: 14px;
            letter-spacing: .3px;
            animation: glow 4s ease-in-out infinite;
          }
          .neon-pill i {font-size: 16px; color:#ff6b35;}
          .smart-highlight {
            position: relative;
            margin-top: -70px;
            z-index: 3;
          }
          .smart-highlight .glass-card {
            border-radius: 24px;
            padding: 36px;
            background: linear-gradient(135deg,#07090f,#1c100c 70%,rgba(255,107,53,0.08));
            border: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 45px 110px rgba(0,0,0,0.6);
            color: #fff5ee;
          }
          .smart-highlight h4 {color: #ffb37a; letter-spacing: .6px;}
          .smart-highlight p {color: rgba(255,255,255,0.8);}
          .stack-grid .single_service {
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
            transform: translateY(0);
            transition: transform .4s ease, box-shadow .4s ease;
            background: #101423;
            color: rgba(255,255,255,0.84);
          }
          .stack-grid .single_service:hover {
            transform: translateY(-14px);
            box-shadow: 0 45px 110px rgba(0,0,0,0.4);
          }
          .stack-grid .single_service h2,
          .stack-grid .single_service h4 {color:#ffb47a;}
          .pulse-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #ff6b35;
            position: relative;
          }
          .pulse-dot::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: rgba(255,107,53,0.4);
            animation: pulse 2s infinite;
          }
          .journey_steps li {
            list-style: none;
            padding: 18px 22px;
            border-left: 3px solid #ff6b35;
            background: #11131c;
            margin-bottom: 14px;
            box-shadow: 0 14px 40px rgba(0,0,0,0.25);
            color: rgba(255,255,255,0.85);
          }
          .cta-gradient {
            background: linear-gradient(120deg,#0b0c10,#1d120f 55%,#ff6b35);
            border-radius: 26px;
            padding: 52px;
            color: #fff5ed;
            box-shadow: 0 50px 120px rgba(0,0,0,0.45);
          }
          .cta-gradient .btn_one {box-shadow: 0 18px 50px rgba(0,0,0,0.45);}

          @keyframes glow {
            0%,100% {box-shadow: 0 0 20px rgba(255,107,53,0.25);}
            50% {box-shadow: 0 0 35px rgba(255,179,71,0.45);}
          }
          @keyframes pulse {
            0% {transform: scale(1); opacity: .9;}
            100% {transform: scale(2.4); opacity: 0;}
          }
          @media (max-width: 767px) {
            .smart-highlight {
              display: none;
            }
            .mobile-cta {
              position: fixed;
              left: 12px;
              right: 12px;
              bottom: 12px;
              z-index: 9999;
              display: block;
            }
            .mobile-cta .btn_one {
              width: 100%;
              text-align: center;
              padding: 14px 16px;
              font-size: 16px;
              box-shadow: 0 14px 34px rgba(0,0,0,0.35);
            }
            body {
              padding-bottom: 90px;
            }
          }
        </style>

<section class="smart-highlight">
  <div class="container">
    <div class="glass-card wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h4 class="mt-3 mb-2">Painel automatizado com entregas mensais</h4>
          <p>Assinou, acessou: seu login é liberado na hora e, todo mês, novos treinos e plano alimentar personalizados chegam direto ao painel. Check-ins rápidos garantem ajustes sem atrasos.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="#como-funciona" class="btn_one">Ver como funciona <i class="ti-arrow-top-right"></i></a>
      
        </div>
      </div>
    </div>
  </div>
</section>

	

		

		<!-- START COUNTER -->
<section class="counter_feature">
  <div class="container">
    <div class="row text-center">

      <div class="col-lg-3 col-sm-6 col-xs-12 no-padding">
        <div class="single-project">
          <h2 class="counter-heading">
            <span class="prefix">+</span><span class="counter-num">857</span>
          </h2>
          <h4>vidas transformadas com planos autorais</h4>
        </div>
      </div>

      <div class="col-lg-3 col-sm-6 col-xs-12 no-padding">
        <div class="single-project">
          <h2 class="counter-heading">
            <span class="counter-num">97</span><span class="suffix">%</span>
          </h2>
          <h4>de aprovação dos clientes acompanhados</h4>
        </div>
      </div><!-- END COL -->

      <div class="col-lg-3 col-sm-6 col-xs-12 no-padding">
        <div class="single-project">
          <h2 class="counter-heading">
            <span class="prefix">+</span><span class="counter-num">934</span>
          </h2>
          <h4>Planos alimentares + treinos entregues </h4>
        </div>
      </div><!-- END COL -->

      <div class="col-lg-3 col-sm-6 col-xs-12 no-padding">
        <div class="single-project">
          <h2 class="counter-heading">
            <span class="prefix">+</span><span class="counter-num">800</span>
          </h2>
          <h4>vídeos de exercícios para garantir uma execução correta</h4>
        </div>
      </div><!-- END COL -->

    </div><!--- END ROW -->
  </div><!--- END CONTAINER -->
</section>
<!-- END COUNTER -->

		<!-- START Sobre US -->
<section class="ab_one section-padding" id="como-funciona">
  <div class="container">
    <div class="row">
      <div class="col-lg-6 col-sm-12 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
        <div class="ab_img">
          <img src="https://nutremfit.com.br/assets/img/about1.png?v=2" class="img-fluid" alt="NutremFit — plano alimentar e emagrecimento">
        </div>
      </div><!--- END COL -->
      <div class="col-lg-6 col-sm-12 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
        <div class="ab_content">
          <div class="section-title section-title--left">
            <span>Sobre a NutremFit</span>
          </div>
        </div>
        <div class="abmv">
          <h4><img src="assets/img/check.png" alt="NutremFit — plano alimentar e emagrecimento" /> Como funciona?</h4>
          <p>A NutremFit é um programa completo que une plano alimentar e treino de musculação, desenvolvidos de forma 100% personalizada para você.</p>
        </div>
        <div class="abmv">
          <h4><img src="assets/img/check.png" alt="NutremFit — plano alimentar e emagrecimento" /> Suporte humano e ajustes mensais</h4>
          <p>Seja para emagrecer, ganhar massa muscular, cuidar da saúde ou melhorar a performance, você terá ajustes mensais e suporte direto com a equipe Nutremfit pelo WhatsApp, para nunca se sentir sozinho no processo</p>
        </div>
      </div><!--- END COL -->
    </div><!--- END ROW -->
  </div><!--- END CONTAINER -->
</section>
<!-- END Sobre US  -->

<script>
  (function() {
    document.querySelectorAll('a[href="#como-funciona"]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var target = document.getElementById('como-funciona');
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          window.location.hash = 'como-funciona';
        }
      });
    });
  })();
</script>


		<!-- START Serviço -->
		<section class="service_area section-padding">
			<div class="container">	
				<div class="row">
					<div class="col-lg-6 col-sm-6 col-xs-12">
						<div class="section-title">
							<span>Como funciona na prática</span>
							<h2>Planos que se moldam à sua vida</h2>
						</div>					
					</div>
					<div class="col-lg-6 col-sm-6 col-xs-12">
						<div class="ser_btn">
							<a href="/services" class="btn_two">Ver planos <i class="ti-arrow-top-right"></i></a>
						</div>
					</div>
				</div>
				<div class="row">								
					<div class="col-lg-4 col-sm-4 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="single_service">
							<img src="assets/img/service1.png" class="img-fluid" alt="NutremFit - plano alimentar e emagrecimento">
							<h2>Plano alimentar com substituições</h2>
							<p>Sem radicalismos: você recebe um plano que respeita sua rotina, preferências e contexto.</p>
							<a href="https://nutremfit.com.br/plano-alimentar">Leia mais <i class="ti-arrow-top-right"></i></a>
						</div>
					</div><!--- END COL -->						
					<div class="col-lg-4 col-sm-4 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="single_service">
							<img src="assets/img/service2.png" class="img-fluid" alt="NutremFit - plano alimentar e emagrecimento">
							<h2>Treino de musculação personalizado (com vídeos)</h2>
							<p>Treinos prescritos por personal trainer, com vídeos de execução correta para treinar com segurança.</p>
							<a href="https://nutremfit.com.br/treino-personalizado">Leia mais <i class="ti-arrow-top-right"></i></a>
						</div>
					</div><!--- END COL -->						
					<div class="col-lg-4 col-sm-4 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="single_service">
							<img src="assets/img/service3.png" class="img-fluid" alt="NutremFit - plano alimentar e emagrecimento">
							<h2>Atualização mensal e suporte no WhatsApp</h2>
							<p>Treinos novos e plano alimentar ajustados mensalmente, 100% personalizados. Suporte via WhatsApp para dúvidas.</p>
							<a href="https://nutremfit.com.br/atualizacao-suporte">Leia mais <i class="ti-arrow-top-right"></i></a>
						</div>
					</div><!--- END COL -->												  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END Serviço -->

		  <div class="marq_text">
			<div id="supermarquee1"></div>
		  </div>

       
		  
		<!-- START Sobre US --> 
<section class="ab_one section-padding">
  <div class="container">									
    <div class="row align-items-center">											 
      <div class="col-lg-6 col-sm-12 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
        <div class="ab_img">
          <img src="assets/img/personal.jpg" class="img-fluid" alt="NutremFit — acompanhamento integrado de nutrição e treino">
        </div>
      </div><!--- END COL -->						
      <div class="col-lg-6 col-sm-12 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
        <div class="ab_content">
          <div class="section-title section-title--left">
            <span>Sobre a NutremFit</span>
            <h2>Um único acompanhamento, duas especialidades</h2>
          </div>
        </div>

        <div class="abmv">
          <h4>Duas especialidades em um só lugar</h4>
          <p>Você tem o melhor de dois mundos — <strong>Nutrição</strong> e <strong>Educação Física</strong> integradas em um único acompanhamento.</p>
          <p>Ao invés de investir em dois profissionais diferentes, você tem um <strong>método unificado</strong>, com <strong>comunicação fluida</strong>, <strong>estratégias alinhadas</strong> e <strong>resultados muito mais consistentes</strong> — além de <strong>economizar tempo e dinheiro</strong>.</p>
        </div>
      </div><!--- END COL -->						
    </div><!--- END ROW -->
  </div><!--- END CONTAINER -->
</section>
<!-- END Sobre US  -->


	  
		
		
	
		<!-- START CHOOSE -->
		<section class="why_area section-padding" style="background-image: url(https://nutremfit.com.br/assets/img/bg/section-2.jpg?v=2);  background-size:cover; background-position: center center;">
			<div class="container">									
				<div class="row">								
					<div class="col-lg-6 col-sm-12 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
						<div class="ab_content">
							<span>Por que escolher a NutremFit</span>
							 
							<p>Na NutremFit, você recebe treino + plano alimentar 100% personalizados, criados por profissionais com registro ativo — tudo no mesmo lugar, seguindo a mesma estratégia.</p>
                            <p>E o melhor: essa integração te garante uma economia de 70% em relação ao que você pagaria contratando um nutricionista e um personal trainer separadamente.</p>
							<p>Com os dois profissionais trabalhando juntos dentro da plataforma, treino e alimentação se complementam, aceleram sua evolução e tornam o processo muito mais simples, eficiente e consistente.</p>
						</div>
						<div class="skill_btn mt-3"> 
							<a href="/pagamento" class="btn_one">
								Contratar meu plano agora <i class="ti-arrow-top-right"></i>
							</a>
						</div>
					</div><!--- END COL -->	
					 <!--- END COL -->							
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END CHOOSE -->	

<!-- START Planos (dinâmico com banco) -->
<section id="planos" class="plan_home_area section-padding">
  <div class="container">
    <div class="section-title text-center wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
      <span>Planos de Assinatura</span>
      <h2>Escolha a forma mais vantajosa de receber seus programas mensais</h2>
      <p>Nos planos semestral e anual, você recebe ajustes automáticos a cada 30 dias — a forma mais eficiente, econômica e estruturada de evoluir.</p>
    </div>
    <div class="row">
      <?php if (empty($catalog)): ?>
        <div class="col-12"><p class="text-center">Nenhum plano disponível no momento.</p></div>
      <?php else: ?>
        <?php $i = 0; foreach ($catalog as $planItem): $i++; ?>
          <?php
            $slug = $planItem['slug'] ?? 'plano';
            $name = $planItem['name'] ?? ucfirst($slug);
            $price = (float) ($planItem['amount'] ?? 0);
            $desc = $planItem['description'] ?? '';
            $cycle = $planItem['cycle'] ?? 'monthly';
            $months = mpago_plan_duration_months($planItem);
            $total = $price * max(1, $months);
            $features = nf_plan_features($planItem);
            if ($slug === 'performance' && isset($pricesBySlug['essencial'])) {
                $economy = max(0, ($pricesBySlug['essencial'] * 6) - (($planItem['amount'] ?? 0) * 6));
                $features[] = 'Economia de R$ ' . number_format($economy, 2, ',', '.') . ' comparado ao mensal';
            }
            if ($slug === 'vip' && isset($pricesBySlug['essencial'])) {
                $economy = max(0, ($pricesBySlug['essencial'] * 12) - (($planItem['amount'] ?? 0) * 12));
                $features[] = 'Economia de R$ ' . number_format($economy, 2, ',', '.') . ' comparado ao mensal';
            }
            $highlight = ($slug === 'performance'); // marca o plano contínuo (semestral) como mais vendido
          ?>
          <div class="col-lg-4 col-sm-4 col-xs-12 wow FadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
            <div class="pricingTable <?php echo $highlight ? 'popular' : ''; ?>">
              <div class="pricingTable-header">
                <h3 class="title"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h3>
                <?php if ($highlight): ?><span class="badge bg-light text-dark">Mais vendido</span><?php endif; ?>
              </div>
              <div class="pricing-icon">
                <i class="ti-<?php echo $highlight ? 'server' : 'medall'; ?>"></i>
              </div>
              <ul class="pricing-content">
                <?php if ($desc): ?><li><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                <?php foreach ($features as $f): ?>
                  <li><?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
              </ul>
              <div class="price-value">
                <?php if ($months > 1): ?>
                  <span class="amount"><span style="font-size:12px;opacity:.75;"><?php echo (int) $months; ?>x de</span> R$ <?php echo number_format($price, 2, ',', '.'); ?></span>
                  <span class="duration">/mês</span>
                  <div style="color:rgba(255,255,255,0.7);font-size:13px;margin-top:6px;">Total R$ <?php echo number_format($total, 2, ',', '.'); ?></div>
                <?php else: ?>
                  <span class="amount">R$ <?php echo number_format($price, 2, ',', '.'); ?></span>
                  <span class="duration">/<?php echo $cycle === 'oneoff' ? 'único' : 'mês'; ?></span>
                <?php endif; ?>
              </div>
              <div>
                <a href="/pagamento?plan=<?php echo urlencode($slug); ?>" class="btn_one">Assinar agora</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<!-- END Planos (dinâmico com banco) -->

				
		
		<!-- START Depoimentos-->
<section class="testi_home_area section-padding">
  <div class="container">
    <div class="section-title text-center">
      <span>Depoimentos</span>
      <h2>O que dizem sobre a NutremFit</h2>
    </div>

    <!--
      Nota (não exibida no site):
      Depoimentos: tive que mudar as iniciais dos pacientes por uma questão de privacidade,
      pela profissão poderiam associar. Além disso, tive que colocar o nome do programa ao invés
      de "com a Marina" porque apesar de ser um resultado meu também, é em outro programa e quero fortalecer a marca.
    -->

    <div class="row">
      <div class="col-lg-12">
        <div id="testimonial-slider" class="owl-carousel">

          <!-- TESTIMONIAL 1 -->
          <div class="testimonial">
            <img src="assets/img/quote.png" alt="NutremFit — plano alimentar e emagrecimento" />
            <div class="testimonial_content">
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <p>"Sempre achei difícil conciliar alimentação e treino com a rotina corrida. Na NutremFit consegui um plano
                que realmente cabe no meu dia. Em 3 meses, perdi 7kg sem cortar nada que gosto e hoje treino com muito mais ânimo."</p>
            </div>
            <div class="testi_pic_title">
              <h4>M.R., 29 anos — Advogada</h4>
            </div>
          </div>
          <!-- END TESTIMONIAL -->

          <!-- TESTIMONIAL 2 -->
          <div class="testimonial">
            <img src="assets/img/quote.png" alt="NutremFit — plano alimentar e emagrecimento" />
            <div class="testimonial_content">
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <p>"Já tinha tentado várias vezes treinar por conta própria, mas sempre parava. O que fez diferença aqui foi ter tudo
                personalizado: o treino, o plano alimentar e principalmente os ajustes mensais. Consegui ganhar 5kg de massa muscular em 4 meses."</p>
            </div>
            <div class="testi_pic_title">
              <h4>J.S., 35 anos — Engenheiro</h4>
            </div>
          </div>
          <!-- END TESTIMONIAL -->

          <!-- TESTIMONIAL 3 -->
          <div class="testimonial">
            <img src="assets/img/quote.png" alt="NutremFit — plano alimentar e emagrecimento" />
            <div class="testimonial_content">
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <i class="ti-star"></i>
              <p>"O suporte no painel fez toda a diferença. Qualquer dúvida eu conseguia tirar rápido, e isso me manteve no caminho.
                Já eliminei 12kg desde que comecei e continuo firme."</p>
            </div>
            <div class="testi_pic_title">
              <h4>A.L., 41 anos — Empresária</h4>
            </div>
          </div>
          <!-- END TESTIMONIAL -->

        </div><!-- END TESTIMONIAL SLIDER -->
      </div><!-- END COL -->
    </div><!-- END ROW -->
  </div><!-- END CONTAINER -->
</section>
<!-- END Depoimentos -->


	

		
	
	<!--<< Footer Section Start >>-->
	<?php include './partials/footer.php'?>

	<!--<< All JS Plugins >>-->
	<?php include './partials/script.php'?>		
	
    </body>
</html>
