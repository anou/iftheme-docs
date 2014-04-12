<?php

class WYSIJA_module_view_stats_reportcards_std_view extends WYSIJA_view_back {

    public function hook_newsletter_top($data) {
        if (empty($data['report_cards']))
            return;
        echo '<div class="stats_reportcards_std hook-column">';
        foreach ($data['report_cards'] as $card_id => $card) {
            echo '<div class="report-card">';
            if (!empty($card['title']))
                echo '<span class="card-title">' . $card['title'] . '</span>';
            if (!empty($card['title']) && !empty($card['description']))
                echo ':&nbsp;';
            if (!empty($card['description']))
                echo '<span class="card-content">' . $card['description'] . '</span>';
            echo '<br />';
            echo '</div>';
        }
        echo '</div>';
    }

}