<?PHP
require_once 'myclasses/class.phpmailer.php' ;
require_once 'logins.php' ;
include_once 'defines.php';

function createMail($callsource, $id, &$subject, &$message){
// $type == TRADE_ALERT, HA_ALERT

	switch ($callsource) {
		case SIGNAL_SOURCE_TRADE_ALERT:
			/*trd_positions:
				`trd_positions___account_raw`
				`trd_positions___buy_sell_raw`
				`trd_positions___buy_sell`
				`trd_positions___id_raw`
				`trd_positions___id`
				`trd_positions___name_raw`
				`trd_positions___name`
				`trd_positions___status_raw`
				`trd_positions___status`
				`trd_positions___ticker`
				`trd_positions___type`
				`trd_positions___ticker_raw`
				`trd_positions___type_raw`
			
			trd_pos_close:
				`trd_pos_close___scomm_raw`
				`trd_pos_close___sorderid`
				`trd_pos_errors___error`
				`trd_pos_open___bcomm`
				`trd_pos_performance___current_lastupdate`
				`trd_pos_performance___today_difference_perc`
				`trd_pos_performance___unr_profit`
				`trd_pos_strategy___posid`
				`trd_pos_strategy___pot_loss_raw`
				`trd_pos_strategy___pot_winn_raw`
				`trd_pos_strategy___target_raw`
				`trd_pos_strategy___win_ratio_raw`
			
			trd_alerts_dd:	
				`trd_alerts_dd___description`
			
			trd_pos_alerts:
				`trd_pos_alerts___alert_date_raw`
				`trd_pos_alerts___alert_date`
				`trd_pos_alerts___alert_raw`
				`trd_pos_alerts___id_raw`
				`trd_pos_alerts___id`
				`trd_pos_alerts___l1_raw`
				`trd_pos_alerts___l1`
				`trd_pos_alerts___l2_raw`
				`trd_pos_alerts___l2`
				`trd_pos_alerts___l3_raw`
				`trd_pos_alerts___l3`
				`trd_pos_alerts___l4_raw`
				`trd_pos_alerts___l4`
				`trd_pos_alerts___l5_raw`
				`trd_pos_alerts___l5`
				`trd_pos_alerts___posid_raw`
				`trd_pos_alerts___posid`
				`trd_pos_alerts___processed_raw`
				`trd_pos_alerts___processed`
				`trd_pos_alerts___v1_raw`
				`trd_pos_alerts___v1`
				`trd_pos_alerts___v2_raw`
				`trd_pos_alerts___v2`
				`trd_pos_alerts___v3_raw`
				`trd_pos_alerts___v3`
				`trd_pos_alerts___v4_raw`
				`trd_pos_alerts___v4`
				`trd_pos_alerts___v5_raw`
				`trd_pos_alerts___v5`
			
			trd_pos_close:
				`trd_pos_close___id_raw`
				`trd_pos_close___id`
				`trd_pos_close___posid_raw`
				`trd_pos_close___posid`
				`trd_pos_close___scomm`
				`trd_pos_close___sdate_raw`
				`trd_pos_close___sdate`
				`trd_pos_close___sorderid_raw`
				`trd_pos_close___sprice_raw`
				`trd_pos_close___sprice`
				`trd_pos_close___sqty_raw`
				`trd_pos_close___sqty`
				`trd_pos_close___stotal_raw`
				`trd_pos_close___stotal`
			
			trd_pos_errors:
				`trd_pos_errors___error_raw`
				`trd_pos_errors___id_raw`
				`trd_pos_errors___id`
				`trd_pos_errors___posid_raw`
				`trd_pos_errors___posid`
			
			trd_pos_notes:
				`trd_pos_notes___id_raw`
				`trd_pos_notes___id`
				`trd_pos_notes___notes_raw`
				`trd_pos_notes___notes`
				`trd_pos_notes___posid_raw`
				`trd_pos_notes___posid`
			
			trd_pos_open:
				`trd_pos_open___bcomm_raw`
				`trd_pos_open___bdate_raw`
				`trd_pos_open___bdate`
				`trd_pos_open___borderid_raw`
				`trd_pos_open___borderid`
				`trd_pos_open___bprice_raw`
				`trd_pos_open___bprice`
				`trd_pos_open___bqty_raw`
				`trd_pos_open___bqty`
				`trd_pos_open___btotal_raw`
				`trd_pos_open___btotal`
				`trd_pos_open___id_raw`
				`trd_pos_open___id`
				`trd_pos_open___posid_raw`
				`trd_pos_open___posid`
			
			trd_pos_performance:	
				`trd_pos_performance___bcomm_raw`
				`trd_pos_performance___bcomm`
				`trd_pos_performance___bprice_raw`
				`trd_pos_performance___bprice`
				`trd_pos_performance___bqty_raw`
				`trd_pos_performance___bqty`
				`trd_pos_performance___btotal_raw`
				`trd_pos_performance___btotal`
				`trd_pos_performance___current_lastupdate_raw`
				`trd_pos_performance___first_bdate_raw`
				`trd_pos_performance___first_bdate`
				`trd_pos_performance___id_raw`
				`trd_pos_performance___id`
				`trd_pos_performance___last_raw`
				`trd_pos_performance___last`
				`trd_pos_performance___posid_raw`
				`trd_pos_performance___posid`
				`trd_pos_performance___prev_close_raw`
				`trd_pos_performance___prev_close`
				`trd_pos_performance___pricedirection_raw`
				`trd_pos_performance___pricedirection`
				`trd_pos_performance___real_profit_perc_raw`
				`trd_pos_performance___real_profit_perc`
				`trd_pos_performance___real_profit_raw`
				`trd_pos_performance___real_profit`
				`trd_pos_performance___scomm_raw`
				`trd_pos_performance___scomm`
				`trd_pos_performance___sprice_raw`
				`trd_pos_performance___sprice`
				`trd_pos_performance___sqty_raw`
				`trd_pos_performance___sqty`
				`trd_pos_performance___stotal_raw`
				`trd_pos_performance___stotal`
				`trd_pos_performance___today_difference_perc_raw`
				`trd_pos_performance___today_difference_raw`
				`trd_pos_performance___today_difference`
				`trd_pos_performance___today_unreal_perc_raw`
				`trd_pos_performance___today_unreal_perc`
				`trd_pos_performance___today_unreal_raw`
				`trd_pos_performance___today_unreal`
				`trd_pos_performance___unr_profit_perc_raw`
				`trd_pos_performance___unr_profit_perc`
				`trd_pos_performance___unr_profit_raw`
				`trd_pos_performance___win_loss_raw`
				`trd_pos_performance___win_loss`
			
				
			trd_pos_strategy:
				`trd_pos_strategy___entry_strategy_raw`
				`trd_pos_strategy___entry_strategy`
				`trd_pos_strategy___exit_strategy_raw`
				`trd_pos_strategy___exit_strategy`
				`trd_pos_strategy___id_raw`
				`trd_pos_strategy___id`
				`trd_pos_strategy___next_action_raw`
				`trd_pos_strategy___next_action`
				`trd_pos_strategy___next_earning_date_raw`
				`trd_pos_strategy___next_earning_date`
				`trd_pos_strategy___posid_raw`
				`trd_pos_strategy___pot_loss`
				`trd_pos_strategy___pot_winn`
				`trd_pos_strategy___source_raw`
				`trd_pos_strategy___source`
				`trd_pos_strategy___stop_raw`
				`trd_pos_strategy___stop`
				`trd_pos_strategy___target`
				`trd_pos_strategy___trend_raw`
				`trd_pos_strategy___trend`
				`trd_pos_strategy___win_ratio`
*/
			$mysql='SELECT SQL_CALC_FOUND_ROWS DISTINCT `trd_positions`.`ticker` AS `trd_positions___ticker`, `trd_positions`.`ticker` '. 
			' AS `trd_positions___ticker_raw`, `trd_positions`.`empty3` AS `trd_positions___empty3`, `trd_positions`.`empty3` AS `trd_positions___empty3_raw`, '. 
			' `trd_positions`.`name` AS `trd_positions___name`, `trd_positions`.`name` AS `trd_positions___name_raw`, `trd_positions`.`empty4`  '. 
			' AS `trd_positions___empty4`, `trd_positions`.`empty4` AS `trd_positions___empty4_raw`, `trd_positions`.`account` AS  '. 
			'`trd_positions___account_raw`, trd_accounts.name AS `trd_positions___account`, `trd_positions`.`status` AS `trd_positions___status`,  '. 
			'`trd_positions`.`status` AS `trd_positions___status_raw`, `trd_positions`.`buy_sell` AS `trd_positions___buy_sell`,  '. 
			'`trd_positions`.`buy_sell` AS `trd_positions___buy_sell_raw`, `trd_positions`.`type` AS `trd_positions___type`, `trd_positions`.`type`  '. 
			'AS `trd_positions___type_raw`, `trd_positions`.`empty1` AS `trd_positions___empty1`, `trd_positions`.`empty1` AS `trd_positions___empty1_raw`, '. 
			' `trd_positions`.`id` AS `trd_positions___id`, `trd_positions`.`id` AS `trd_positions___id_raw`, `trd_pos_strategy`.`source` AS  '. 
			'`trd_pos_strategy___source_raw`, `trd_source_dd`.`description` AS `trd_pos_strategy___source`, `trd_pos_strategy`.`trend` AS  '. 
			'`trd_pos_strategy___trend`, `trd_pos_strategy`.`trend` AS `trd_pos_strategy___trend_raw`, `trd_pos_strategy`.`entry_strategy` AS  '. 
			'`trd_pos_strategy___entry_strategy_raw`, `trd_strategy`.`description` AS `trd_pos_strategy___entry_strategy`,  '. 
			'`trd_pos_strategy`.`exit_strategy` AS `trd_pos_strategy___exit_strategy_raw`, `trd_exit_strategy`.`description` AS  '. 
			'`trd_pos_strategy___exit_strategy`, `trd_pos_strategy`.`target` AS `trd_pos_strategy___target`, `trd_pos_strategy`.`target`  '. 
			'AS `trd_pos_strategy___target_raw`, `trd_pos_strategy`.`stop` AS `trd_pos_strategy___stop`, `trd_pos_strategy`.`stop` AS  '. 
			'`trd_pos_strategy___stop_raw`, `trd_pos_strategy`.`pot_winn` AS `trd_pos_strategy___pot_winn`, `trd_pos_strategy`.`pot_winn`  '. 
			'AS `trd_pos_strategy___pot_winn_raw`, `trd_pos_strategy`.`pot_loss` AS `trd_pos_strategy___pot_loss`, `trd_pos_strategy`.`pot_loss` '. 
			' AS `trd_pos_strategy___pot_loss_raw`, `trd_pos_strategy`.`win_ratio` AS `trd_pos_strategy___win_ratio`, `trd_pos_strategy`.`win_ratio` '. 
			' AS `trd_pos_strategy___win_ratio_raw`, `trd_pos_strategy`.`next_earning_date` AS `trd_pos_strategy___next_earning_date`,  '. 
			'`trd_pos_strategy`.`next_earning_date` AS `trd_pos_strategy___next_earning_date_raw`, `trd_pos_strategy`.`next_action` AS  '. 
			'`trd_pos_strategy___next_action_raw`, CONCAT("",trd_next_action.description,"") AS `trd_pos_strategy___next_action`,  '. 
			'`trd_pos_strategy`.`id` AS `trd_pos_strategy___id`, `trd_pos_strategy`.`id` AS `trd_pos_strategy___id_raw`, `trd_pos_strategy`.`posid` '. 
			' AS `trd_pos_strategy___posid`, `trd_pos_strategy`.`posid` AS `trd_pos_strategy___posid_raw`, `trd_pos_alerts`.`id` AS  '. 
			'`trd_pos_alerts___id`, `trd_pos_alerts`.`id` AS `trd_pos_alerts___id_raw`, `trd_pos_alerts`.`posid` AS `trd_pos_alerts___posid_raw`,  '. 
			'`trd_positions_1`.`ticker` AS `trd_pos_alerts___posid`, `trd_pos_alerts`.`alert` AS `trd_pos_alerts___alert_raw`,  '. 
			'`trd_alerts_dd`.`description` AS `trd_alerts_dd___description`, `trd_pos_alerts`.`processed` AS `trd_pos_alerts___processed`,  '. 
			'`trd_pos_alerts`.`processed` AS `trd_pos_alerts___processed_raw`, `trd_pos_alerts`.`alert_date` AS `trd_pos_alerts___alert_date`, '. 
			' `trd_pos_alerts`.`alert_date` AS `trd_pos_alerts___alert_date_raw`, `trd_pos_alerts`.`l1` AS `trd_pos_alerts___l1`,  '. 
			'`trd_pos_alerts`.`l1` AS `trd_pos_alerts___l1_raw`, `trd_pos_alerts`.`v1` AS `trd_pos_alerts___v1`, `trd_pos_alerts`.`v1` AS  '. 
			'`trd_pos_alerts___v1_raw`, `trd_pos_alerts`.`l2` AS `trd_pos_alerts___l2`, `trd_pos_alerts`.`l2` AS `trd_pos_alerts___l2_raw`,  '. 
			'`trd_pos_alerts`.`v2` AS `trd_pos_alerts___v2`, `trd_pos_alerts`.`v2` AS `trd_pos_alerts___v2_raw`, `trd_pos_alerts`.`l3` AS  '. 
			'`trd_pos_alerts___l3`, `trd_pos_alerts`.`l3` AS `trd_pos_alerts___l3_raw`, `trd_pos_alerts`.`v3` AS `trd_pos_alerts___v3`,  '. 
			'`trd_pos_alerts`.`v3` AS `trd_pos_alerts___v3_raw`, `trd_pos_alerts`.`l4` AS `trd_pos_alerts___l4`, `trd_pos_alerts`.`l4` AS  '. 
			'`trd_pos_alerts___l4_raw`, `trd_pos_alerts`.`v4` AS `trd_pos_alerts___v4`, `trd_pos_alerts`.`v4` AS `trd_pos_alerts___v4_raw`,  '. 
			'`trd_pos_alerts`.`l5` AS `trd_pos_alerts___l5`, `trd_pos_alerts`.`l5` AS `trd_pos_alerts___l5_raw`, `trd_pos_alerts`.`v5` AS  '. 
			'`trd_pos_alerts___v5`, `trd_pos_alerts`.`v5` AS `trd_pos_alerts___v5_raw`, `trd_pos_open`.`id` AS `trd_pos_open___id`,  '. 
			'`trd_pos_open`.`id` AS `trd_pos_open___id_raw`, `trd_pos_open`.`posid` AS `trd_pos_open___posid`, `trd_pos_open`.`posid` AS  '. 
			'`trd_pos_open___posid_raw`, `trd_pos_open`.`borderid` AS `trd_pos_open___borderid`, `trd_pos_open`.`borderid` AS  '. 
			'`trd_pos_open___borderid_raw`, `trd_pos_open`.`bqty` AS `trd_pos_open___bqty`, `trd_pos_open`.`bqty` AS `trd_pos_open___bqty_raw`,  '. 
			'`trd_pos_open`.`bprice` AS `trd_pos_open___bprice`, `trd_pos_open`.`bprice` AS `trd_pos_open___bprice_raw`, `trd_pos_open`.`bcomm`  '. 
			'AS `trd_pos_open___bcomm`, `trd_pos_open`.`bcomm` AS `trd_pos_open___bcomm_raw`, `trd_pos_open`.`btotal` AS `trd_pos_open___btotal`,  '. 
			'`trd_pos_open`.`btotal` AS `trd_pos_open___btotal_raw`, `trd_pos_open`.`bdate` AS `trd_pos_open___bdate`, `trd_pos_open`.`bdate` AS  '. 
			'`trd_pos_open___bdate_raw`, `trd_pos_close`.`id` AS `trd_pos_close___id`, `trd_pos_close`.`id` AS `trd_pos_close___id_raw`,  '. 
			'`trd_pos_close`.`posid` AS `trd_pos_close___posid`, `trd_pos_close`.`posid` AS `trd_pos_close___posid_raw`, `trd_pos_close`.`sorderid` '. 
			' AS `trd_pos_close___sorderid`, `trd_pos_close`.`sorderid` AS `trd_pos_close___sorderid_raw`, `trd_pos_close`.`sqty` AS  '. 
			'`trd_pos_close___sqty`, `trd_pos_close`.`sqty` AS `trd_pos_close___sqty_raw`, `trd_pos_close`.`sprice` AS `trd_pos_close___sprice`,  '. 
			'`trd_pos_close`.`sprice` AS `trd_pos_close___sprice_raw`, `trd_pos_close`.`scomm` AS `trd_pos_close___scomm`, `trd_pos_close`.`scomm` '. 
			' AS `trd_pos_close___scomm_raw`, `trd_pos_close`.`stotal` AS `trd_pos_close___stotal`, `trd_pos_close`.`stotal` AS  '. 
			'`trd_pos_close___stotal_raw`, `trd_pos_close`.`sdate` AS `trd_pos_close___sdate`, `trd_pos_close`.`sdate` AS `trd_pos_close___sdate_raw`, '. 
			' `trd_pos_notes`.`notes` AS `trd_pos_notes___notes`, `trd_pos_notes`.`notes` AS `trd_pos_notes___notes_raw`, `trd_pos_notes`.`id` AS  '. 
			'`trd_pos_notes___id`, `trd_pos_notes`.`id` AS `trd_pos_notes___id_raw`, `trd_pos_notes`.`posid` AS `trd_pos_notes___posid`,  '. 
			'`trd_pos_notes`.`posid` AS `trd_pos_notes___posid_raw`, `trd_pos_errors`.`error` AS `trd_pos_errors___error_raw`, `trd_errors_dd`.`description` '. 
			' AS `trd_pos_errors___error`, `trd_pos_errors`.`id` AS `trd_pos_errors___id`, `trd_pos_errors`.`id` AS `trd_pos_errors___id_raw`,  '. 
			'`trd_pos_errors`.`posid` AS `trd_pos_errors___posid_raw`, `trd_positions_0`.`ticker` AS `trd_pos_errors___posid`,  '. 
			'`trd_pos_performance`.`id` AS `trd_pos_performance___id`, `trd_pos_performance`.`id` AS `trd_pos_performance___id_raw`,  '. 
			'`trd_pos_performance`.`posid` AS `trd_pos_performance___posid`, `trd_pos_performance`.`posid` AS `trd_pos_performance___posid_raw`, '. 
			' `trd_pos_performance`.`last` AS `trd_pos_performance___last`, `trd_pos_performance`.`last` AS `trd_pos_performance___last_raw`,  '. 
			'`trd_pos_performance`.`prev_close` AS `trd_pos_performance___prev_close`, `trd_pos_performance`.`prev_close` AS  '. 
			'`trd_pos_performance___prev_close_raw`, `trd_pos_performance`.`today_difference` AS `trd_pos_performance___today_difference`,  '. 
			'`trd_pos_performance`.`today_difference` AS `trd_pos_performance___today_difference_raw`, `trd_pos_performance`.`today_difference_perc`  '. 
			'AS `trd_pos_performance___today_difference_perc`, `trd_pos_performance`.`today_difference_perc` AS  '. 
			'`trd_pos_performance___today_difference_perc_raw`, `trd_pos_performance`.`today_unreal` AS `trd_pos_performance___today_unreal`, '. 
			' `trd_pos_performance`.`today_unreal` AS `trd_pos_performance___today_unreal_raw`, `trd_pos_performance`.`today_unreal_perc` AS  '. 
			'`trd_pos_performance___today_unreal_perc`, `trd_pos_performance`.`today_unreal_perc` AS `trd_pos_performance___today_unreal_perc_raw`,  '. 
			'`trd_pos_performance`.`first_bdate` AS `trd_pos_performance___first_bdate`, `trd_pos_performance`.`first_bdate` AS  '. 
			'`trd_pos_performance___first_bdate_raw`, `trd_pos_performance`.`pricedirection` AS `trd_pos_performance___pricedirection`,  '. 
			'`trd_pos_performance`.`pricedirection` AS `trd_pos_performance___pricedirection_raw`, `trd_pos_performance`.`current_lastupdate` '. 
			' AS `trd_pos_performance___current_lastupdate`, `trd_pos_performance`.`current_lastupdate` AS `trd_pos_performance___current_lastupdate_raw`, '. 
			' `trd_pos_performance`.`real_profit` AS `trd_pos_performance___real_profit`, `trd_pos_performance`.`real_profit` AS  '. 
			'`trd_pos_performance___real_profit_raw`, `trd_pos_performance`.`real_profit_perc` AS `trd_pos_performance___real_profit_perc`,  '. 
			'`trd_pos_performance`.`real_profit_perc` AS `trd_pos_performance___real_profit_perc_raw`, `trd_pos_performance`.`unr_profit` AS '. 
			' `trd_pos_performance___unr_profit`, `trd_pos_performance`.`unr_profit` AS `trd_pos_performance___unr_profit_raw`,  '. 
			'`trd_pos_performance`.`unr_profit_perc` AS `trd_pos_performance___unr_profit_perc`, `trd_pos_performance`.`unr_profit_perc` AS  '. 
			'`trd_pos_performance___unr_profit_perc_raw`, `trd_pos_performance`.`win_loss` AS `trd_pos_performance___win_loss`,  '. 
			'`trd_pos_performance`.`win_loss` AS `trd_pos_performance___win_loss_raw`, `trd_pos_performance`.`bqty` AS `trd_pos_performance___bqty`, '. 
			' `trd_pos_performance`.`bqty` AS `trd_pos_performance___bqty_raw`, `trd_pos_performance`.`bprice` AS `trd_pos_performance___bprice`,  '. 
			'`trd_pos_performance`.`bprice` AS `trd_pos_performance___bprice_raw`, `trd_pos_performance`.`bcomm` AS `trd_pos_performance___bcomm`,  '. 
			'`trd_pos_performance`.`bcomm` AS `trd_pos_performance___bcomm_raw`, `trd_pos_performance`.`btotal` AS `trd_pos_performance___btotal`, '. 
			' `trd_pos_performance`.`btotal` AS `trd_pos_performance___btotal_raw`, `trd_pos_performance`.`sqty` AS `trd_pos_performance___sqty`,  '. 
			'`trd_pos_performance`.`sqty` AS `trd_pos_performance___sqty_raw`, `trd_pos_performance`.`sprice` AS `trd_pos_performance___sprice`,  '. 
			'`trd_pos_performance`.`sprice` AS `trd_pos_performance___sprice_raw`, `trd_pos_performance`.`scomm` AS `trd_pos_performance___scomm`,  '. 
			'`trd_pos_performance`.`scomm` AS `trd_pos_performance___scomm_raw`, `trd_pos_performance`.`stotal` AS `trd_pos_performance___stotal`,  '. 
			'`trd_pos_performance`.`stotal` AS `trd_pos_performance___stotal_raw`, `trd_positions`.`id` AS slug , `trd_positions`.`id` AS `__pk_val`  '. 
			'FROM `trd_positions` LEFT JOIN `trd_pos_notes` AS `trd_pos_notes` ON `trd_pos_notes`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_pos_errors` AS `trd_pos_errors` ON `trd_pos_errors`.`posid` = `trd_positions`.`id` '. 
			'LEFT JOIN `trd_errors_dd` AS `trd_errors_dd` ON `trd_errors_dd`.`id` = `trd_pos_errors`.`error`  '. 
			'LEFT JOIN `trd_pos_close` AS `trd_pos_close` ON `trd_pos_close`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_pos_performance` AS `trd_pos_performance` ON `trd_pos_performance`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_pos_open` AS `trd_pos_open` ON `trd_pos_open`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_accounts` AS `trd_accounts` ON `trd_accounts`.`account` = `trd_positions`.`account`  '. 
			'LEFT JOIN `trd_pos_strategy` AS `trd_pos_strategy` ON `trd_pos_strategy`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_source_dd` AS `trd_source_dd` ON `trd_source_dd`.`id` = `trd_pos_strategy`.`source`  '. 
			'LEFT JOIN `trd_strategy` AS `trd_strategy` ON `trd_strategy`.`id` = `trd_pos_strategy`.`entry_strategy`  '. 
			'LEFT JOIN `trd_exit_strategy` AS `trd_exit_strategy` ON `trd_exit_strategy`.`id` = `trd_pos_strategy`.`exit_strategy`  '. 
			'LEFT JOIN `trd_next_action` AS `trd_next_action` ON `trd_next_action`.`id` = `trd_pos_strategy`.`next_action`  '. 
			'LEFT JOIN `trd_pos_links` AS `trd_pos_links` ON `trd_pos_links`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_positions` AS `trd_positions_0` ON `trd_positions_0`.`id` = `trd_pos_errors`.`posid`  '. 
			'LEFT JOIN `trd_pos_alerts` AS `trd_pos_alerts` ON `trd_pos_alerts`.`posid` = `trd_positions`.`id`  '. 
			'LEFT JOIN `trd_positions` AS `trd_positions_1` ON `trd_positions_1`.`id` = `trd_pos_alerts`.`posid`  '. 
			'LEFT JOIN `trd_alerts_dd` AS `trd_alerts_dd` ON `trd_alerts_dd`.`id` = `trd_pos_alerts`.`alert`  '. 
			'WHERE `trd_pos_alerts`.`id` = "'.$id.'"' ;
		//	'LEFT JOIN `trd_alert_actions` AS `trd_alert_actions` ON `trd_alerts_dd`.`actionid` = `trd_alert_actions`.`id`  '. 
			break;
		case SIGNAL_SOURCE_HA_ALERT:
			/* ha_alerts:
				`ha_alerts___id`
				`ha_alerts___deviceID`
				`ha_alerts___date_key`
				`ha_alerts___processed`
				`ha_alerts___alert_date`
				`ha_alerts___action_date`
				`ha_alerts___l1`
				`ha_alerts___v1`
				`ha_alerts___l2`
				`ha_alerts___v2`
				`ha_alerts___l3`
				`ha_alerts___v3`
				`ha_alerts___l4`
				`ha_alerts___v4`
				`ha_alerts___l5`
				`ha_alerts___v6`
			
			ha_alerts_dd:
				`ha_alerts_dd___description`
			
			ha_mf_devices:
				`ha_mf_devices___code`
				`ha_mf_devices___unit`
				`ha_alerts___description`
				
			ha_mf_locations:
				`ha_mf_locations___description`
			*/			
			$mysql='SELECT SQL_CALC_FOUND_ROWS DISTINCT `ha_alerts`.`id` AS `ha_alerts___id`,  '.
				'`ha_alerts`.`deviceID` AS `ha_alerts___deviceID`, '.
				'`ha_mf_devices`.`code` AS `ha_mf_devices___code`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, '.
				'`ha_mf_locations`.`description` AS `ha_mf_locations___description`, '.
				'`ha_mf_devices`.`description` AS `ha_mf_devices___description`, `ha_alerts`.`alertid` AS `ha_alerts___alertid`, '.
				'`ha_alerts_dd`.`description` AS `ha_alerts_dd___description`, `ha_alerts`.`date_key` AS `ha_alerts___date_key`, '.
				'`ha_alerts`.`processed` AS `ha_alerts___processed`, '.
				'`ha_alerts`.`alert_date` AS `ha_alerts___alert_date`, '.
				'`ha_alerts`.`action_date` AS `ha_alerts___action_date`, '.
				'`ha_alerts`.`l1` AS `ha_alerts___l1`, `ha_alerts`.`v1` AS `ha_alerts___v1`, '.
				'`ha_alerts`.`l2` AS `ha_alerts___l2`, `ha_alerts`.`v2` AS `ha_alerts___v2`, '.
				'`ha_alerts`.`l3` AS `ha_alerts___l3`, `ha_alerts`.`v3` AS `ha_alerts___v3`, '.
				'`ha_alerts`.`l4` AS `ha_alerts___l4`, `ha_alerts`.`v4` AS `ha_alerts___v4`, '.
				'`ha_alerts`.`l5` AS `ha_alerts___l5`, `ha_alerts`.`v5` AS `ha_alerts___v5` '.
				' FROM `ha_alerts`'.
				' LEFT JOIN `ha_alerts_dd` AS `ha_alerts_dd` ON `ha_alerts_dd`.`id` = `ha_alerts`.`alertid` '.
				' LEFT JOIN `ha_mf_devices` AS `ha_mf_devices` ON `ha_mf_devices`.`id` = `ha_alerts`.`deviceID`'.
				' LEFT JOIN `ha_mf_locations` AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID`'.
				' WHERE `ha_alerts`.`id` = "'.$id.'"';	

			break;
		case SIGNAL_SOURCE_REMOTE_SCHEME:
			/*  ha_mf_devices:
					 `ha_mf_devices___code`
					 `ha_mf_devices___description`
					 `ha_mf_devices___unit`
					 
					 ha_mf_locations:
					 `ha_mf_locations___description`
					 `ha_mf_locations___id`
					 
				ha_remote_scheme_steps:
					 `ha_remote_scheme_steps___alert_textID_raw` 
					 `ha_remote_scheme_steps___alert_textID`
					 `ha_remote_scheme_steps___commandID_raw`
					 `ha_remote_scheme_steps___commandID`
					 `ha_remote_scheme_steps___date_time_raw` 
					 `ha_remote_scheme_steps___date_time`
					 `ha_remote_scheme_steps___deviceID_raw`
					 `ha_remote_scheme_steps___id_raw` 
					 `ha_remote_scheme_steps___id`
					 `ha_remote_scheme_steps___schemesID_raw`
					 `ha_remote_scheme_steps___schemesID` 
					 `ha_remote_scheme_steps___sort_raw` 
					 `ha_remote_scheme_steps___sort`
					 `ha_remote_scheme_steps___value_raw`
					 `ha_remote_scheme_steps___value` 
					 
				ha_remote_schemes:
					 `ha_remote_schemes___date_time_raw` 
					 `ha_remote_schemes___date_time`
					 `ha_remote_schemes___group_raw` 
					 `ha_remote_schemes___group`
					 `ha_remote_schemes___id_raw`
					 `ha_remote_schemes___id`
					 `ha_remote_schemes___id`
					 `ha_remote_schemes___name_raw` 
					 `ha_remote_schemes___name`
					 `ha_remote_schemes___rkey_raw` 
					 `ha_remote_schemes___rkey`
					 `ha_remote_schemes___sort_raw` 
					 `ha_remote_schemes___sort`
				*/
			$mysql='SELECT SQL_CALC_FOUND_ROWS DISTINCT `ha_remote_schemes`.`id` AS `ha_remote_schemes___id`, '.
			'`ha_remote_schemes`.`id` AS `ha_remote_schemes___id_raw`, '.
			'`ha_remote_schemes`.`date_time` AS `ha_remote_schemes___date_time`, '.
			'`ha_remote_schemes`.`date_time` AS `ha_remote_schemes___date_time_raw`, '.
			'`ha_remote_schemes`.`name` AS `ha_remote_schemes___name`, '.
			'`ha_remote_schemes`.`name` AS `ha_remote_schemes___name_raw`, '.
			'`ha_remote_schemes`.`group` AS `ha_remote_schemes___group`, '.
			'`ha_remote_schemes`.`group` AS `ha_remote_schemes___group_raw`, '.
			'`ha_remote_schemes`.`rkey` AS `ha_remote_schemes___rkey`, `ha_remote_schemes`.`rkey` AS `ha_remote_schemes___rkey_raw`, '.
			'`ha_remote_schemes`.`sort` AS `ha_remote_schemes___sort`, `ha_remote_schemes`.`sort` AS `ha_remote_schemes___sort_raw`, '.
			'`ha_remote_scheme_steps`.`id` AS `ha_remote_scheme_steps___id`, '.
			'`ha_remote_scheme_steps`.`id` AS `ha_remote_scheme_steps___id_raw`, '.
			'`ha_remote_scheme_steps`.`date_time` AS `ha_remote_scheme_steps___date_time`, '.
			'`ha_remote_scheme_steps`.`date_time` AS `ha_remote_scheme_steps___date_time_raw`,'.
			'`ha_remote_scheme_steps`.`schemesID` AS `ha_remote_scheme_steps___schemesID_raw`, '.
			'`ha_remote_schemes_0`.`name` AS `ha_remote_scheme_steps___schemesID`, '.
			'`ha_remote_scheme_steps`.`sort` AS `ha_remote_scheme_steps___sort`, '.
			'`ha_remote_scheme_steps`.`sort` AS `ha_remote_scheme_steps___sort_raw`, '.
			'`ha_remote_scheme_steps`.`deviceID` AS `ha_remote_scheme_steps___deviceID_raw`, `ha_mf_devices`.`code` AS `ha_mf_devices___code`, '.
			'`ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, `ha_mf_devices`.`description` AS `ha_mf_devices___description`, '.
			'`ha_remote_scheme_steps`.`commandID` AS `ha_remote_scheme_steps___commandID_raw`, '.
			'`ha_mf_commands`.`description` AS `ha_remote_scheme_steps___commandID`, '.
			'`ha_remote_scheme_steps`.`value` AS `ha_remote_scheme_steps___value`,  '.
			'`ha_remote_scheme_steps`.`value` AS `ha_remote_scheme_steps___value_raw`, '.
			'`ha_remote_scheme_steps`.`alert_textID` AS `ha_remote_scheme_steps___alert_textID_raw`, '.
			'`ha_alert_text`.`description` AS `ha_remote_scheme_steps___alert_textID`, '.
			'`ha_remote_schemes`.`id` AS `ha_remote_schemes___id`, `ha_mf_locations`.`id` AS `ha_mf_locations___id`, '.
			'`ha_mf_locations`.`description` AS `ha_mf_locations___description`'.
			' FROM `ha_remote_schemes` '.
			' LEFT JOIN `ha_remote_scheme_steps` AS `ha_remote_scheme_steps` ON `ha_remote_scheme_steps`.`schemesID` = `ha_remote_schemes`.`id` '.
			' LEFT JOIN `ha_mf_commands` AS `ha_mf_commands` ON `ha_mf_commands`.`id` = `ha_remote_scheme_steps`.`commandID` '.
			' LEFT JOIN `ha_mf_devices` AS `ha_mf_devices` ON `ha_mf_devices`.`id` = `ha_remote_scheme_steps`.`deviceID` '.
			' LEFT JOIN `ha_alert_text` AS `ha_alert_text` ON `ha_alert_text`.`id` = `ha_remote_scheme_steps`.`alert_textID` '.
			' LEFT JOIN `ha_remote_schemes` AS `ha_remote_schemes_0` ON `ha_remote_schemes_0`.`id` = `ha_remote_scheme_steps`.`schemesID` '.
			' LEFT JOIN `ha_mf_locations` AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID` '.
			' WHERE `ha_remote_scheme_steps`.`id` = "'.$id.'"';
 			break;
	}
 
	if (!$resdata = mysql_query($mysql)) {	
		mySqlError($mysql);
		return false;
	}

	
	if ($data = mysql_fetch_assoc($resdata)) {
//	echo "<pre>"; print_r ($data); echo "</pre>";
		foreach ($data as $key => $value) {
			$pattern[$key]="/\{".$key."\}/";
		}
		$subject=preg_replace($pattern, $data, $subject);
		$subject=preg_replace($pattern, $data, $subject); // twice to support tag in tag
		$message=preg_replace($pattern, $data, $message); // twice to support tag in tag
		$message=preg_replace($pattern, $data, $message);
		return TRUE;
	} else {
		return FALSE;
	}
	
}


function sendmail($to, $subject, $message, $fromname) {

	$mailer = new PHPMailer();
	$mailer->IsSMTP();
	$mailer->Host = 'ssl://smtp.gmail.com:465';
	$mailer->SMTPAuth = TRUE;
	
	$mailer->Username = GMAIL_USER;
	$mailer->Password = GMAIL_PASSWORD;
	
	$mailer->From = GMAIL_USER;
	$mailer->FromName = $fromname;
	$mailer->Body = $message;
	$mailer->Subject = $subject;
	
	$mailer->AddAddress($to);
				
	$send = 0;
	
	if(!$mailer->Send()) {
	    error_log("Mailer :  error ".$mailer->ErrorInfo)." : $to";
	    return FALSE;
	}
	else {
		return TRUE;
	}
}
?>
