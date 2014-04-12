<?php

defined('WYSIJA') or die('Restricted access');

require_once(dirname(__FILE__) . DS . 'stats_reportcards_std_model.php');

class WYSIJA_module_stats_reportcards_std extends WYSIJA_module_statistics {

    public $name = 'stats_reportcards_std';
    public $model = 'WYSIJA_model_stats_reportcards_std';
    public $view = 'stats_reportcards_std_view';

    /**
     * Pre-defined cards
     * Format
     * array(
     *  CODE1 => array(
     *          'title' => 'Lorem ipsum', // (optional, but at least one of them - title or description - is declared)
     *          'description' => 'Lorem ipsum', // (optional, but at least one of them - title or description - is declared)
     *          '
     *      ),
     *  CODE2 => array(
     *          'title' => 'Lorem ipsum 1',
     *          'description' => 'Lorem ipsum 2',
     *          '
     *      ),
     * )
     * @var array
     */
    protected $cards = array();

    /**
     *
     * @var array all possible cards for a specific newsletter
     * Format: same as $cards()
     */
    protected $report_cards = array();

    public function __construct() {
        parent::__construct();
        $this->init_cards();
    }

    public function hook_newsletter_top($params) {

        $report_cards = $this->get_report_cards($params);
        $this->data['report_cards'] = $this->populate_cards($report_cards);

        $this->view_show = 'hook_newsletter_top';
        return $this->render();
    }

    protected function get_report_cards($params) {
        //if case 1
        // if case 2
        $params = $params;

        $mail_id = $params['email_id'];

        // first newsletters
        if ($this->model_obj->is_the_first_newsletter())
            $this->add_card('N0001');

        // bounce rate
        $bounce_rate = $this->model_obj->get_rate($mail_id, WYSIJA_model_stats_reportcards_std::EMAIL_STATUS_BOUNCE);
        if ($bounce_rate !== NULL and $bounce_rate > 5.0)
            $this->add_card('N0006');
        else
            $this->add_card('N0007');



        return $this->report_cards;
    }

    protected function add_card($card_id) {
        $this->report_cards[] = $card_id;
    }

    /**
     * Combine card_ids with their content
     * @param type $report_cards
     * @return type
     */
    protected function populate_cards($report_cards) {
        $tmp = array();
        $report_cards = array_unique($report_cards);
        foreach ($report_cards as $card_id) {
            if (!empty($this->cards[$card_id]))
                $tmp[$card_id] = $this->cards[$card_id];
        }
        return $tmp;
    }

    protected function init_cards() {
        // first newsletter
        $this->cards['N0001'] = array(
            'title' => __('This is the first newsletter. Send one more to get useful comparisons.', WYSIJA),
            'description' => false
        );

        // open rate
        $url = '#';
        $this->cards['N0002'] = array(
            'title' => __('Opens', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Over %1$s%% is excellent. %2$s%% to %3$s%% is good. Under that, it is bad. [link]Read why this stat can be unreliable on support.wysija.com[/link]', WYSIJA), 30, 15, 30)
            )
        );

        // click
        $url = '#';
        $this->cards['N0003'] = array(
            'title' => __('Clicks', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Over %1$s%% is great. Between %2$s%% and %3$s%% is good. Under %4$s%% is poor. It is normal if you have no links.', WYSIJA), 15, 15, 30, 5)
            )
        );

        // unsubscribe
        $url = '#';
        $this->cards['N0004'] = array(
            'title' => __('Unsubscribes', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Under %1$s%% is great. %2$s%% - %3$s%% is good. Over %4$s%%, we would assume you are a spammer. [link]Read more on support.wysija.com.[/link]', WYSIJA), 1, 1, 3, 5)
            )
        );

        // bounces
        $url = '#';
        $this->cards['N0004'] = array(
            'title' => __('Unsubscribes', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Excellent under %1$s%%. Under %2$s%% is acceptable. Over that, some servers might consider blocking. [link]Read more on support.wysija.com.[/link]', WYSIJA), 1, 5)
            )
        );

        // bounces
        $url = '#';
        $this->cards['N0005'] = array(
            'title' => __('Unsubscribes', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Excellent under %1$s%%. Under %2$s%% is acceptable. Over that, some servers might consider blocking. [link]Read more on support.wysija.com.[/link]', WYSIJA), 1, 5)
            )
        );

        // bounces
        $url = '#';
        $this->cards['N0006'] = array(
            'title' => __('Bounced', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Ouch. You are over %1$s%%. MailPoet will unsubscribe the invalid emails, thanks to the [link]Automatic Bounce Handler[/link]', WYSIJA), 5)
            )
        );

        // spam score (pendding)
        $url = '#';
        $this->cards['N0007'] = array(
            'title' => __('Bounced', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Excellent under %1$s%%. Under %2$s%%  is acceptable. Over that, some servers might consider blocking. [link]Read more on support.mailpoet.com.[/link]', WYSIJA), 1, 5)
            )
        );

        // spam score (pendding)
        $url = '#';
        $this->cards['N0008'] = array(
            'title' => __('Google campaign', WYSIJA),
            'description' => str_replace(
                    array('[link]', '[/link]'), array('<a target="_blank" href="' . $url . '">', '</a>'), sprintf(__('Excellent under %1$s%%. Under %2$s%% is acceptable. Over that, some servers might consider blocking. [link]Read more on support.wysija.com.[/link]', WYSIJA), 1, 5)
            )
        );
    }

}