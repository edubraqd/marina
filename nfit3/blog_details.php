<!DOCTYPE html>
<html lang="pt-BR">
<?php $title='NutremFit | Conteúdo'?>
<?php include './partials/head.php'?>
	
    <body data-spy="scroll" data-offset="80">

		<?php include './partials/preloader.php'?>
		<?php include './partials/header.php'?>	

		<!-- START SECTION TOP -->
		<section class="section-top">
			<div class="container">
				<div class="col-lg-10 offset-lg-1 text-center">
					<div class="section-top-title wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.3s" data-wow-offset="0">
						<h1>Detalhes do blog</h1>
						<ul>
							<li><a href="/">Início</a></li>
							<li> / Detalhes do blog</li>
						</ul>
					</div><!-- //.HERO-TEXT -->
				</div><!--- END COL -->
			</div><!--- END CONTAINER -->
		</section>	
		<!-- END SECTION TOP -->
		
		<!-- START SERVICE -->
		<section class="service_area section-padding">
			<div class="container">	
				<div class="row">								
					<div class="col-lg-10 offset-lg-1 col-sm-12 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="single_blog_details">
						   <img src="assets/img/blog/blog_details.jpg" class="img-fluid sbd" alt="Artigo NutremFit">
						   <span><img src="assets/img/blog/b_icon1.png" alt="" /> Marina Alves</span>
						   <span><img src="assets/img/blog/b_icon2.png" alt="" /> 30 abril, 2025</span>
						   <span><img src="assets/img/blog/b_icon3.png" alt="" /> 05 min de leitura</span>
							<h2>Como planejar refeições inteligentes para semanas corridas</h2>
							<p>Organizar o cardápio antes da semana começar diminui decisões diárias e ajuda a manter o déficit calórico sem sofrimento. Escolha proteínas principais, defina acompanhamentos versáteis e deixe temperos prontos na geladeira. Assim, você monta pratos completos em menos de 15 minutos.</p>
							<p>Quando bate a vontade de fugir do plano, tenha versões equilibradas das suas comidas favoritas e combine com a nutricionista ajustes pontuais. Flexibilidade com estratégia evita o efeito sanfona e mantém o metabolismo ativo.</p>
						</div>
						<div class="single_ssd_info">
							<h4>Como organizamos os conteúdos</h4>
							<p>Cada tema nasce das dúvidas dos alunos e dos dados coletados nos check-ins. Assim, garantimos que os artigos sejam práticos, aplicáveis e baseados em ciência — nada de promessas milagrosas.</p>
						</div>
						<img src="assets/img/blog/blog_details2.jpg" class="img-fluid" alt="Organização NutremFit">
						<div class="comment_form">
							<h3 class="blog_head_title">Deixe seu comentário</h3>
							<div class="contact comment-box">
								<form id="contact-form" method="post" enctype="multipart/form-data">
									<div class="row">
										<div class="form-group col-md-6">
											<input type="text" name="name" class="form-control" id="first-name" placeholder="Nome" required="required">
										</div>
										<div class="form-group col-md-6">
											<input type="email" name="email" class="form-control" id="first-email" placeholder="E-mail" required="required">
										</div>
										<div class="form-group col-md-12">
											<input type="text" name="subject" class="form-control" id="subject" placeholder="Assunto" required="required">
										</div>
										<div class="form-group col-md-12">
											<textarea rows="6" name="message" class="form-control" id="description" placeholder="Sua mensagem" required="required"></textarea>
										</div>
										<div class="col-md-12">
											<div class="actions">
												<button type="submit" value="Enviar mensagem" name="submit" id="submitButton" class="btn btn_one" title="Enviar seu comentário">Enviar comentário</button>
											</div>
										</div>
									</div>
								</form>
							</div>
						</div><!--- END COMMENT FORM -->	
					</div><!--- END COL -->													  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END SERVICE -->

	<!--<< Footer Section Start >>-->
	<?php include './partials/footer.php'?>

	<!--<< All JS Plugins >>-->
	<?php include './partials/script.php'?>	
    </body>
</html>
