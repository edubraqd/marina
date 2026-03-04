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
						<h1>Blog details</h1>
						<ul>
							<li><a href="index.php">Home</a></li>
							<li> / Blog details</li>
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
						   <img src="assets/img/blog/blog_details.jpg" class="img-fluid sbd" alt="image">
						   <span><img src="assets/img/blog/b_icon1.png" alt="" /> William Smith</span>
						   <span><img src="assets/img/blog/b_icon2.png" alt="" /> 30 April, 2025</span>
						   <span><img src="assets/img/blog/b_icon3.png" alt="" /> 05 min read</span>
							<h2>Digital solution for your business problem so that you can improve in business</h2>
							<p>The difference between short-form and long-form videos is simple: short-form videos are short, and long-form videos are long. To be more specific, short-form videos are typically under 10 minutes long, while long-form videos exceed that 10-minute mark. You’ll see a lot of short-form videos on social media. Target, for example, uses this video format on Instagram to advertise its products.</p>
							<p>You’ll typically see longer videos on a business’s website or YouTube. Video and podcast hosting provider, Wistia, uses long-form video to educate its audience about the cost of video production.</p>
						</div>
						<div class="single_ssd_info">
							<h4>It includes brainstorming</h4>
							<p>Content is king in the digital world. Agencies produce high-quality content, including blog posts, videos, infographics, and more, to engage and educate the target audience. Content marketing builds trust and authority for the brand. Agencies manage and grow a brand's presence on social media platforms such as Facebook, Twitter, LinkedIn, and Instagram.</p>
						</div>
						<img src="assets/img/blog/blog_details2.jpg" class="img-fluid" alt="image">
						<div class="comment_form">
							<h3 class="blog_head_title">Add a Comment</h3>
							<div class="contact comment-box">
								<form id="contact-form" method="post" enctype="multipart/form-data">
									<div class="row">
										<div class="form-group col-md-6">
											<input type="text" name="name" class="form-control" id="first-name" placeholder="Name" required="required">
										</div>
										<div class="form-group col-md-6">
											<input type="email" name="email" class="form-control" id="first-email" placeholder="Email" required="required">
										</div>
										<div class="form-group col-md-12">
											<input type="text" name="subject" class="form-control" id="subject" placeholder="Subject" required="required">
										</div>
										<div class="form-group col-md-12">
											<textarea rows="6" name="message" class="form-control" id="description" placeholder="Your Message" required="required"></textarea>
										</div>
										<div class="col-md-12">
											<div class="actions">
												<button type="submit" value="Send message" name="submit" id="submitButton" class="btn btn_one" title="Submit Your Message!">Submit Comment</button>
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