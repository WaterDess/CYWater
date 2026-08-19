<?php
/**
 * Site footer.
 *
 * @package CYWater
 */
?>
<footer class="site-footer">
	<div class="container">
		<div class="footer-grid">
			<div class="footer-brand">
				<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<img class="brand-logo brand-logo--footer" src="<?php echo esc_url( cywater_asset_uri( 'img/logo.png' ) ); ?>" alt="CYWater" width="41" height="50">
				</a>
				<p class="footer-tag">A non-profit international association advancing water sciences education, research, and professional development. Founded in 2011.</p>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Explore', 'cywater' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
					<li><a href="<?php echo esc_url( home_url( '/membership/' ) ); ?>">Membership</a></li>
					<li><a href="<?php echo esc_url( home_url( '/events/' ) ); ?>">Events</a></li>
					<li><a href="<?php echo esc_url( home_url( '/awards/' ) ); ?>">Awards</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Association', 'cywater' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/board/' ) ); ?>">Board</a></li>
					<li><a href="<?php echo esc_url( home_url( '/bylaws/' ) ); ?>">Bylaws</a></li>
					<li><a href="<?php echo esc_url( home_url( '/news/' ) ); ?>">News</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
				</ul>
			</div>
			<div class="footer-col">
				<h4><?php esc_html_e( 'Mailing address', 'cywater' ); ?></h4>
				<p class="footer-tag">202 E. Green St. Suite 2<br>Champaign, IL 61820, USA</p>
				<a class="link footer-contact-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact CYWater</a>
			</div>
		</div>
		<div class="footer-bottom">
			<span>&copy; 2011&ndash;<?php echo esc_html( gmdate( 'Y' ) ); ?> CYWater. All rights reserved.</span>
			<a href="<?php echo esc_url( home_url( '/bylaws/' ) ); ?>">Bylaws</a>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
