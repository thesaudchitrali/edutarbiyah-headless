<?php
/**
 * The template for displaying the EduTarbiyah Global Footer in Hello Elementor Child
 *
 * Ensures Tutor LMS, WooCommerce, and all dynamic WordPress pages display the
 * exact same branded footer and floating WhatsApp widget as the main Astro website.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="edutarbiyah-global-footer">
	<div class="edutarbiyah-footer-inner">
		<div class="edutarbiyah-footer-grid">
			
			<!-- Brand & Mission Column -->
			<div class="edutarbiyah-footer-brand-box">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="EduTarbiyah Institute Home">
					<img
						src="<?php echo esc_url( home_url( '/images/logo.webp' ) ); ?>"
						alt="EduTarbiyah Institute - ایجو تربیت انسٹیٹیوٹ"
						width="120"
						height="44"
						loading="lazy"
						onerror="this.src='https://edutarbiyah.com/wp-content/uploads/2025/07/Logo-Edutarbiyah-1-1.webp';"
					/>
				</a>
				<p class="edutarbiyah-footer-brand-desc">
					A dedicated platform for mature parents, educators, and mentors. We bridge authentic Islamic moral tarbiyah with contemporary developmental psychology for thriving modern families.
				</p>
				<div class="edutarbiyah-footer-contact">
					<a href="https://wa.me/923122370720" target="_blank" rel="noopener noreferrer">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path></svg>
						<span>+92 312 2370720</span>
					</a>
					<a href="mailto:edutarbiyah@gmail.com">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path></svg>
						<span>edutarbiyah@gmail.com</span>
					</a>
				</div>
			</div>

			<!-- Programs & Courses Column -->
			<div class="edutarbiyah-footer-col">
				<h3>Programs &amp; Courses</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/tppc-self-paced-recorded-course/' ) ); ?>">TPPC Recorded Course</a></li>
					<li><a href="<?php echo esc_url( home_url( '/tarbiyah-parenting-mastery-program/' ) ); ?>">Parenting Mastery Program</a></li>
					<li><a href="<?php echo esc_url( home_url( '/edutarbiyah-parent-coaching-program/' ) ); ?>">1-on-1 Parent Coaching</a></li>
					<li><a href="<?php echo esc_url( home_url( '/parent-coaching-readiness-form/' ) ); ?>">Coaching Readiness Form</a></li>
				</ul>
			</div>

			<!-- Self-Assessments Column -->
			<div class="edutarbiyah-footer-col">
				<h3>Self-Assessments</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/audit-your-parenting/' ) ); ?>">Parenting Audit <span style="font-size:10px; background:rgba(245,158,11,0.2); color:#F59E0B; padding:2px 6px; border-radius:4px; font-weight:700;">FREE</span></a></li>
					<li><a href="<?php echo esc_url( home_url( '/family-vision-maker/' ) ); ?>">Family Vision Maker</a></li>
					<li><a href="<?php echo esc_url( home_url( '/know-your-personality-type/' ) ); ?>">Know Your Personality Type</a></li>
					<li><a href="<?php echo esc_url( home_url( '/childs-behavior-improvement-tracker/' ) ); ?>">Behavior Tracker</a></li>
					<li><a href="<?php echo esc_url( home_url( '/islamic-tarbiyah-home-routine-chart/' ) ); ?>">Routine Maker Chart</a></li>
					<li><a href="<?php echo esc_url( home_url( '/audit-your-marriage-relationships/' ) ); ?>">Marriage Audit</a></li>
				</ul>
			</div>

			<!-- Bookstore & Reads Column -->
			<div class="edutarbiyah-footer-col">
				<h3>Bookstore &amp; Reads</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">All Books &amp; Toolkits</a></li>
					<li><a href="<?php echo esc_url( home_url( '/product-category/edutarbiyah-publications/' ) ); ?>">EduTarbiyah Publications</a></li>
					<li><a href="<?php echo esc_url( home_url( '/product-category/parenting-books/' ) ); ?>">Parenting Books</a></li>
					<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Blog Portal (All Articles)</a></li>
					<li><a href="https://edutarbiyah.com/english/" target="_blank" rel="noopener noreferrer">English Blog ↗</a></li>
					<li><a href="https://blog.edutarbiyah.com" target="_blank" rel="noopener noreferrer">اردو بلاگ اور مقالات ↗</a></li>
					<li><a href="<?php echo esc_url( home_url( '/parents-whatsapp-community/' ) ); ?>" style="color:#F59E0B; font-weight:600;">Join WhatsApp Community →</a></li>
				</ul>
			</div>

		</div>

		<!-- Footer Bottom Copyright & Legal Links -->
		<div class="edutarbiyah-footer-bottom">
			<p>© <?php echo date( 'Y' ); ?> EduTarbiyah Institute. All rights reserved.</p>
			<div>
				<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>
				<a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>">Terms of Service</a>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
			</div>
		</div>

	</div>
</footer>

<!-- Floating WhatsApp Assistance Button -->
<aside id="floating-whatsapp" aria-label="Floating WhatsApp Inquiry">
	<div class="whatsapp-pill">
		<span class="pulsing-dot"></span>
		<span>Ask a Tarbiyah Question</span>
	</div>
	<a
		href="https://wa.me/923122370720?text=<?php echo rawurlencode( 'السلام علیکم EduTarbiyah, I would like to inquire about your courses and parenting resources.' ); ?>"
		target="_blank"
		rel="noopener noreferrer"
		class="whatsapp-btn"
		aria-label="Chat with EduTarbiyah on WhatsApp"
	>
		<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"></path></svg>
	</a>
</aside>

<?php wp_footer(); ?>

</body>
</html>
