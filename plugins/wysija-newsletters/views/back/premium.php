<?php
defined('WYSIJA') or die('Restricted access');

class WYSIJA_view_back_premium extends WYSIJA_view_back{
    function __construct(){
        $this->skip_header =true;

    }

    function defaultDisplay($data){
        $model_config = WYSIJA::get('config','model');
        $time_install = $model_config->getValue('installed_time');

        // we display the even installed_time with kim's version and the odd with ben's
        if($time_install % 2 == 0) $this->premium_kim();
        else $this->premium_ben();

    }

    function premium_kim() {
		?>

        <div class="wrap about-wrap">

	<h1><?php echo __('The Sweeter Experience of our Premium', WYSIJA ); ?></h1>
	<hr>
	<div class="changelog">
		<h2 class="about-headline-callout"><?php _e( 'All Your Stats in a Single Dashboard', WYSIJA ); ?></h2>
		<img class="about-overview-img" src="http://ps.w.org/wysija-newsletters/assets/premium_va/stats-dashboard.png" />

                <p style="text-align:center"><a target="_blank" href="http://www.mailpoet.com/wp-content/uploads/2014/04/stats-page-screenshot.png"><?php _e( 'View full screenshot', WYSIJA) ?></a> (new tab).</p>

		<div class="feature-section col three-col about-updates">
			<div class="col-1">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/stat1.png" />
				<h3><?php _e( 'Have fun improving', WYSIJA ); ?></h3>
				<p><?php _e( 'See your top newsletters, top subscribers, top links, top lists, and top domains on one screen. Can you beat your past newsletters?', WYSIJA ); ?></p>
			</div>
			<div class="col-2">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/stat3.png" />
				<h3><?php _e( 'Increase your subscribers', WYSIJA ); ?></h3>
				<p><?php _e( 'Does my sidebar form get more subscribers than the one in the footer? It\'s time to find out.', WYSIJA ); ?></p>
			</div>
			<div class="col-3 last-feature">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/stat2.png" />
				<h3><?php _e( 'Compare past & present', WYSIJA ); ?></h3>
				<p><?php _e( 'Are you doing better now than 3 months ago? Select a date range to reveal changes in your results.', WYSIJA ); ?></p>
			</div>
		</div>
	</div> <!-- div changelog -->

	<hr>

	<div class="changelog">
		<div class="feature-section col two-col">
			<div>
				<h3><?php _e( 'Reach their inboxes', WYSIJA ); ?></h3>

				<p><?php echo str_replace(array('[link]', '[/link]'), array('<a href="http://www.mail-tester.com/?utm_source=mailpoet&utm_campaign=premiumpage" target="_blank">', '</a>'), __('Get your spam score in 1 click while you\'re designing your newsletter thanks to [link]mail-tester[/link], our own popular in-house tool.', WYSIJA)); ?></p>

				<h4><?php _e( 'Spam filters love DKIM', WYSIJA ); ?></h4>

				<p><?php echo str_replace(array('[link]', '[/link]'), array('<a href=http://support.mailpoet.com/knowledgebase/guide-to-dkim-in-wysija/?utm_source=wpadmin&utm_campaign=premiumpage" target="_blank">', '</a>'), __('Add a DKIM signature to your newsletters to increase the deliverability of your newsletters. [link]Read the setup guide.[/link]', WYSIJA)); ?></p>

			</div>
			<div class="last-feature about-colors-img">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/reach-inbox.png" />
			</div>
		</div>
	</div>


	<hr>
	<div class="changelog">
		<div class="feature-section col three-col about-updates">
			<div class="col-1">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/configuration.png" />
				<h3><?php _e( 'Let us configure it', WYSIJA ); ?></h3>
				<p><?php _e( 'Our international and friendly team will help you optimize your settings in details, and make sure you\'re all good to go. We answer questions under 10h on average. Pretty fast!', WYSIJA ); ?></p>
			</div>
			<div class="col-2">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/auto-bounce.png" />
				<h3><?php _e( 'Automate the boring stuff', WYSIJA ); ?></h3>

				<p><?php echo str_replace(array('[link]', '[/link]'), array('<a href="http://support.wysija.com/knowledgebase/automated-bounce-handling-install-guide/?utm_source=wpadmin&utm_campaign=premiumpage" target="_blank">', '</a>'), __('Let the plugin remove the <strong>bounced and invalid addresses</strong> automatically. [link]See the guide.[/link]', WYSIJA)); ?></p>

				<p><?php _e( 'Moreover, MailPoet checks every 15 minutes your site, <strong>just like a "cron" job</strong>, to make sure your emails are being sent on time. No setup involved!', WYSIJA ); ?></p>

			</div>
			<div class="col-3 last-feature">
				<img src="http://ps.w.org/wysija-newsletters/assets/premium_va/meow.png" />
				<h3><?php _e( '30 day money back', WYSIJA ); ?></h3>
				<p><?php _e( 'A great way to try the Premium. No one should be locked in, ever.', WYSIJA ); ?></p>
				<p><?php _e( 'The kitten is just to fill the blank space. He looks hungry, no? We love kittens.', WYSIJA ); ?></p>
			</div>
		</div>
	</div>


	<hr>

	<div class="changelog">
		<div class="feature-section col two-col">
			<div>
				<h3><?php _e( 'No more limits of 2000 subscribers', WYSIJA ); ?></h3>
				<p><?php _e('The free version blocks the sending of your newsletters when you have 2000 confirmed subscribers. The Premium removes this limit.',WYSIJA); ?></p>
			</div>
		</div>
	</div>

	<hr>

	<div class="changelog">
		<div class="feature-section col two-col">
			<div>
				<h3><?php _e( 'And more...', WYSIJA ); ?></h3>

				<ul>
					<li><?php _e('Find out what happens to your subscribers once they arrive on your site, thanks to Google Analytics campaign tracking', WYSIJA); ?></li>
					<li><?php _e('Detailed stats for each subscriber', WYSIJA); ?></li>
					<li><?php _e('Stats of clicked links for each newsletter', WYSIJA); ?></li>
				</ul>

			</div>
		</div>
	</div>

	<div id="prices_table">
		<div class="fullwidth" id="prices_main">
			<div class="fullwidth" id="prices_names">
				<div class="one-third blogger">
					<h3><?php _e('Blogger',WYSIJA) ?></h3>
				</div>
				<div class="one-third freelance">
					<h3><?php _e('Freelance',WYSIJA) ?></h3>
				</div>
				<div class="one-third agency">
					<h3><?php _e('Agency',WYSIJA) ?></h3>
				</div>
				<div class="clearfix"></div>
			</div><!-- /#prices_names -->
                        <?php
                            $prices = $this->get_prices();
                        ?>
			<div class="fullwidth" id="prices_cost">
				<div class="one-third blogger">
					<p class="dollars"><?php echo $prices['blogger'] ?></p>
					<p class="per_year"><?php _e('per year',WYSIJA) ?></p>
				</div>
				<div class="one-third frelance">
					<p class="dollars"><?php echo $prices['freelancer'] ?></p>
					<p class="per_year"><?php _e('per year',WYSIJA) ?></p>
				</div>
				<div class="one-third agency">
					<p class="dollars"><?php echo $prices['agency'] ?></p>
					<p class="per_year"><?php _e('per year',WYSIJA) ?></p>
				</div>
				<div class="clearfix"></div>
			</div><!-- /#prices_cost -->
			<div class="fullwidth" id="prices_description">
				<div class="one-third blogger">
					<span><?php _e('Single Site',WYSIJA) ?></span>
				</div>
				<div class="one-third frelance">
					<span><?php _e('Four Sites',WYSIJA) ?></span>
				</div>
				<div class="one-third agency">
					<span><?php _e('Unlimited Sites',WYSIJA) ?></span>
					<p><?php _e('Multisite ready.',WYSIJA) ?></p>
				</div>
				<div class="clearfix"></div>
			</div><!-- /#prices_description -->
			<div class="clearfix"></div>
		</div><!-- /#prices_main -->
	</div><!-- /#prices_table -->
	<br>
	<br>
        <?php
            $helper_licence = WYSIJA::get('licence', 'helper');
            $url_checkout = $helper_licence->get_url_checkout('a_buy_now');
        ?>
	<a class="buy-button" target="_blank" href="<?php echo $url_checkout; ?>" title="3 steps checkout"><span><?php _e('Buy Now',WYSIJA) ?></span></a>
	<div class="clearfix"></div>
	<div class="wysija-premium-actions-kim">
            <?php echo $this->messages(); ?>
		<p><?php _e('Already paid?', WYSIJA); ?> <a class="button-primary wysija-premium-activate" href="javascript:;"><?php echo __('Activate now', WYSIJA); ?></a></p>
    </div>
	<div class="clearfix"></div>
	<p><?php echo str_replace(array('[link]', '[/link]'), array('<a href="http://www.mailpoet.com/contact/?utm_source=wpadmin&utm_campaign=premiumpage" target="_blank">', '</a>'), __('Got a sales question? [link]Get in touch[/link].', WYSIJA)); ?></p>

</div><!-- /#about-wrap -->

<?php

    }

    function premium_ben() {
        $helper_licence = WYSIJA::get('licence', 'helper');
        $arrayPremiumBullets = array(
            array(
                'key' => 'more_stats',
                'title' => __('Monitor everything with more stats', WYSIJA),
                'desc' => __('Looking for answers? Why such and such campaign worked so well? Which is your most efficient subscription form? Get a clear picture with our brand new stats dashboard! [link]Get the stats dashboard in Premium.[/link]', WYSIJA),
                'class' => 'new',
                'link' => $helper_licence->get_url_checkout('b_more_stats'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/stat-dash.jpg',
            ),
            array(
                'key' => 'bounce_service',
                'title' => __('Clean automatically your mailing list', WYSIJA),
                'desc' => __('Send like a PRO and keep your server\'s sending reputation HIGH. Thanks to our advanced list cleaning tool, you can finally avoid sending to old or invalid email addresses. [link]Keep your list clean with Premium.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_bounce_service'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/automated-bounce.jpg',
            ),
            array(
                'key' => 'cron_service',
                'title' => __('Send at a higher frequency', WYSIJA),
                'desc' => __('Did you know? It\'s better to send fewer emails at higher frequencies. Become Premium and we\'ll ping your site each 15 minutes making sure your emails are sent smoothly at this frequency. [link]Send better with Premium.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_cron_service'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/scheduled-on-time.jpg',
            ),
            array(
                'key' => 'no_more_2000_limit',
                'title' => __('Forget about limits', WYSIJA),
                'desc' => __('Remove the 2000 subscribers limit of the free version, and send to as many subscribers as you wish. [link]Get rid of that limit![/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_no_more_2000_limit'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/no-limit.jpg',
            ),
            array(
                'key' => 'more_user_stats',
                'title' => __('Investigate your top subscribers', WYSIJA),
                'desc' => __('Discover which links are the top hits in each newsletter. Find out which subscribers are your number one fans and get advanced details about them. [link]Get more data with Premium.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_more_user_stats'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/subscriber-stats.jpg',
            ),
            array(
                'key' => 'faster_support',
                'title' => __('Get faster support', WYSIJA),
                'desc' => __('Skip the queue, go straight to our priority inbox. [link]Get faster support with Premium.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_faster_support'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/fast-support.jpg',
            ),
            array(
                'key' => 'more_themes',
                'title' => __('Get more themes', WYSIJA),
                'desc' => __('Not good at matching colors? Don\'t worry, we work with top notch designers to provide you plenty of themes, the newest are Premium exclusives. [link]Get more themes in Premium.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_more_themes'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/more-themes.jpg',
            ),
            array(
                'key' => 'ga_track',
                'title' => __('Track your readers within Google Analytics', WYSIJA),
                'desc' => __('Verify what your subscribers do once you drove them onto your site and improve your retention rate. [link]Track your readers with GA in Premium.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_ga_track'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/google-analytics.jpg',
            ),

             array(
                'key' => '30days_no_stress',
                'title' => __('30 days happiness guarantee', WYSIJA),
                'desc' => __('Still having doubts about our product and our team of 9? No stress, we\'re confident enough to give you 30 days to try it out. We\'ll refund you in an instant if you are not happy. [link]Become Premium now without stress.[/link]', WYSIJA),
                'link' => $helper_licence->get_url_checkout('b_30days_no_stress'),
                'img' => 'http://ps.w.org/wysija-newsletters/assets/premium_vb/money-back.jpg',
            ),
        );

        // BEGIN: premium content
        $output = '<div id="premium-content-b" class="about-wrap mpoet-page">';

        // BEGIN: premium features
        $output.= '<h1>' . __('Be productive thanks to Premium!', WYSIJA) . '</h1>';

        $chunks = array_chunk($arrayPremiumBullets, 3);
        $helper_quick_html = WYSIJA::get('quick_html','helper');

        foreach ($chunks as $array_chunks) {
            $output .= '<div class="changelog"><div class="feature-section col three-col about-updates">';
            $output .= $helper_quick_html->three_arguments($array_chunks,true);
            $output .= '</div></div>';
        }
        $prices = $this->get_prices();

        $output .= '<hr/><h2 class="pick-licence">'.__('We have 3 different licences available to fit your needs:',WYSIJA).'</h2>';
        $output.= '<div class="feature-section col three-col about-updates">
                    <div class="col-1 no_more_2000_limit">
                    <h3>'.__('Blogger',WYSIJA).'</h3>
                    <p>'.sprintf(__('For %s per year, get 1 site covered with Premium.',WYSIJA), '<span class="price">'.$prices['blogger'].'</span>').'</p>
                            <p><a href="'.$helper_licence->get_url_checkout('b_buy_blogger').'" class="licence button-primary wysija-premium-purchase" title="Valid for 1 site">'.__('Get it now!',WYSIJA).'</a></p>
                    </div><div class="col-2 more_user_stats">
                    <h3>'.__('Freelancer',WYSIJA).'</h3>
                    <p>'.sprintf(__('For %s per year, get 4 of your sites covered with Premium.'), '<span class="price">'.$prices['freelancer'].'</span>').'</p>
                    <p><a href="'.$helper_licence->get_url_checkout('b_buy_freelance').'" class="licence button-primary wysija-premium-purchase" title="Valid for 4 sites">'.__('Get it now!',WYSIJA).'</a></p>
                    </div>

                    <div class="col-3 faster_support last-feature">
                    <h3>'.__('Agency',WYSIJA).'</h3>
                    <p>'.sprintf(__('For %s per year, get all of your sites covered with Premium.'), '<span class="price">'.$prices['agency'].'</span>').'</p>
                    <p><a href="'.$helper_licence->get_url_checkout('b_buy_agency').'" class="licence button-primary wysija-premium-purchase" title="Valid for an unlimited number of sites">'.__('Get it now!',WYSIJA).'</a></p>
                    </div></div>';


        // END: premium features
        // BEGIN: premium actions
        $output.= '<div class="wysija-premium-actions">';
        $output .= $this->messages();
        $output.= '<p>';
        $output.=  '<span class="conditions">'.str_replace(array('[link]', '[/link]'), array('<a href="http://support.mailpoet.com/terms-conditions/?utm_source=wpadmin&utm_campaign=premiumtab" target="_blank">', '</a>'), __('Read our simple and easy [link]terms and conditions.[/link]', WYSIJA)).'</span>' ;
        $output .= '<a class="button-primary wysija-premium-activate" href="javascript:;">' . __('Already paid? Click here to activate', WYSIJA) . '</a>';
        $output.= '</p></div>';
        // END: premium actions
        // END: premium content
        $output.= '</div>';

        echo $output;
    }

    function get_prices(){
        $prices = array();
        $helper_toolbox = WYSIJA::get('toolbox' , 'helper');

        if($helper_toolbox->is_european()){
            $prices['blogger'] = '75€';
            $prices['freelancer'] = '189€';
            $prices['agency'] = '299€';
            $this->is_european = true;
        }else{
            $prices['blogger'] = '$99';
            $prices['freelancer'] = '$249';
            $prices['agency'] = '$399';
            $this->is_european = false;
        }
        return $prices;
    }
}
