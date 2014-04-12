<?php

class WYSIJA_model_stats_reportcards_std extends WYSIJA_module_statistics_model {
    /**
     * @todo: move all kinds of these constants into a global class
     */

    const EMAIL_STATUS_QUEUE = -3;
    const EMAIL_STATUS_NOT_SENT = -2;
    const EMAIL_STATUS_BOUNCE = -1;
    const EMAIL_STATUS_SENT = 0;
    const EMAIL_STATUS_OPENED = 1;
    const EMAIL_STATUS_CLICKED = 2;
    const EMAIL_STATUS_UNSUBSCRIBED = 3;

    /**
     * Store email status (output of $this->get_email_status())
     * @var array 
     */
    protected static $emails_status = array();

    /**
     * Store email count (output of $this->get_email_status())
     * @var int 
     */
    protected static $emails_count;

    /**
     * Get and group by status of a specific newsletter which was sent to subscribers
     * @param int $user_id
     * @return array list of emails, group by status. It contains an empy list, or list of one or more status
     * array(
     *  status => emails count, // status: -3: inqueue, -2:notsent, -1: bounced, 0: sent, 1: open, 2: clicked, 3: unsubscribed
     *  status => emails count,
     *  ...
     *  status => emails count
     * )
     */
    public function get_email_status($email_id) {
        if (!isset(self::$emails_status[$email_id])) {
            // get stats email status
            $query = '
                SELECT 
                    count(`email_id`) as emails, 
                    `status` 
                FROM 
                    `[wysija]email_user_stat`
                WHERE `email_id` = ' . (int) $email_id . '
                GROUP BY `status`'
            ;
            self::$emails_status[$email_id] = $this->indexing_dataset_by_field('status', $this->get_results($query), false, 'emails');
        }
        return self::$emails_status[$email_id];
    }

    /**
     * 
     * @param int $user_id 
     * @return int a number of received / sent newsletters to a specific user
     */
    public function get_emails_count($email_id) {
        if (!isset(self::$emails_count)) {
            // get emails group by status
            $count = 0;
            $emails_status = $this->get_email_status($email_id); // we don't need to write a separated sql query here, reduce 1 sql request
            if (empty($emails_status))
                return $count;
            foreach ($emails_status as $emails)
                $count += $emails;
            self::$emails_count = $count;
        }
        return self::$emails_count;
    }

    /**
     * Check if we are sending the first email
     * @return type
     */
    public function is_the_first_newsletter() {
        $query = '
            SELECT 
                COUNT(`email_id`) as `count` 
            FROM 
                [wysija]email 
            WHERE 
                `status` > 0';
        $result = $this->get_results($query);
        return (int) $result[0]['count'] >= 1;
    }

    public function get_previous_newsletter($email_id) {
        $email_id;
        return 0;
    }

    /**
     * Get rate by email status
     * @param int $email_id
     * @param int $status usually we pass constants such as WYSIJA_model_stats_reportcards_std::EMAIL_STATUS_QUEUE, etc...
     * @return null
     */
    public function get_rate($email_id, $status) {
        $email_status = $this->get_email_status($email_id);
        $email_count = $this->get_emails_count($email_id);
        if (empty($email_count))
            return NULL;
        $real_emails = !empty($email_status[$status]) ? (int) $email_status[$status] : 0;
        return round($real_emails / $email_count, 2);
    }

}