<!DOCTYPE html>
<html lang="en">

<?php $title='NutremFit'?>
<?php include './partials/head.php'?>
	
    <body data-spy="scroll" data-offset="80">

		<?php include './partials/preloader.php'?>
		<?php include './partials/header.php'?>		

		<!-- START SECTION TOP -->
		<section class="section-top">
			<div class="container">
				<div class="col-lg-10 offset-lg-1 text-center">
					<div class="section-top-title wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.3s" data-wow-offset="0">
						<h1>Contact</h1>
						<ul>
							<li><a href="index.php">Home</a></li>
							<li> / Contact</li>
						</ul>
					</div><!-- //.HERO-TEXT -->
				</div><!--- END COL -->
			</div><!--- END CONTAINER -->
		</section>	
		<!-- END SECTION TOP -->
		
		<!-- START ADDRESS -->
		<section class="address_area section-padding">
			<div class="container">
				<div class="row text-center">
					<div class="col-lg-4 col-sm-4 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
						<div class="single_address">
							<i class="ti-map"></i>
							<h4>Our Location</h4>
							<p>3481 Melrose Place, Beverly Hills <br /> CA 90210</p>
						</div>
					</div><!-- END COL -->
					<div class="col-lg-4 col-sm-4 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="single_address sabr">
							<i class="ti-mobile"></i>
							<h4>Telephone</h4>
							<p>(+1) 517 397 7100</p>
							<p>(+1) 411 315 8138</p>
						</div>
					</div><!-- END COL -->
					<div class="col-lg-4 col-sm-4 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.3s" data-wow-offset="0">
						<div class="single_address">
							<i class="ti-email"></i>
							<h4>Send email</h4>
							<p>Info@example.com</p>
							<p>admin@example.com</p>
						</div>
					</div><!-- END COL -->			  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END ADDRESS -->	

		<!-- CONTACT -->
		<div id="contact" class="contact_area section-padding">
			<div class="container">
				<div class="section-title text-center">
					<span>Get in touch</span>
					<h2>Contact us for any kind  <br />of inquire</h2>
				</div>			
				<div class="row">					
					<div class="offset-lg-1 col-lg-10 col-sm-12 col-xs-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="contact">
							<form class="form" name="enq" method="post" action="contact.php" onsubmit="return validation();">
								<div class="row">
									<div class="form-group col-md-6">
										<label for="">Name</label>
										<input type="text" name="name" class="form-control" required="required">
									</div>
									<div class="form-group col-md-6">
										<label for="">Your Email</label>
										<input type="email" name="email" class="form-control" required="required">
									</div>
									<div class="form-group col-md-12">
										<label for="">Your Subject</label>
										<input type="text" name="subject" class="form-control" required="required">
									</div>
									<div class="form-group col-md-12">
										<label for="">Your Message</label>
										<textarea rows="6" name="message" class="form-control" required="required"></textarea>
									</div>
									<div class="col-md-12 text-center">
										<button type="submit" value="Send message" name="submit" id="submitButton" class="btn_one" title="Submit Your Message!">Send Message</button>
									</div>
								</div>
							</form>
						</div>
					</div><!-- END COL  -->				
				</div><!-- END ROW -->				
			</div><!--- END CONTAINER -->
		</div>
		<!-- END CONTACT -->
		
	<!--<< Footer Section Start >>-->
	<?php include './partials/footer.php'?>

	<!--<< All JS Plugins >>-->
	<?php include './partials/script.php'?>		
    </body>
</html>