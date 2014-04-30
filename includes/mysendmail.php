<?php
function createMail($mailtype, $params, &$subject, &$message){
// $type == TRADE_ALERT, HA_ALERT, SCHEME_STEPS

	switch ($mailtype) {
		case MAIL_TYPE_TRADE:				// Not in use
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
				`trd_pos_performance___updatedate`
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
				`trd_pos_performance___updatedate_raw`
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
			'`trd_pos_performance`.`pricedirection` AS `trd_pos_performance___pricedirection_raw`, `trd_pos_performance`.`updatedate` '. 
			' AS `trd_pos_performance___updatedate`, `trd_pos_performance`.`updatedate` AS `trd_pos_performance___updatedate_raw`, '. 
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
			'WHERE `trd_pos_alerts`.`id` = "'.$params['tradealert ar something'].'"' ;
		//	'LEFT JOIN `trd_alert_actions` AS `trd_alert_actions` ON `trd_alerts_dd`.`actionid` = `trd_alert_actions`.`id`  '. 
			break;
		case MAIL_TYPE_SCHEME:
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
					 `ha_remote_scheme_steps___updatedate_raw` 
					 `ha_remote_scheme_steps___updatedate`
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
					 `ha_remote_schemes___updatedate_raw` 
					 `ha_remote_schemes___updatedate`
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
/*			$mysql='SELECT SQL_CALC_FOUND_ROWS DISTINCT `ha_remote_schemes`.`id` AS `ha_remote_schemes___id`, '.
			'`ha_remote_schemes`.`id` AS `ha_remote_schemes___id_raw`, '.
			'`ha_remote_schemes`.`updatedate` AS `ha_remote_schemes___updatedate`, '.
			'`ha_remote_schemes`.`updatedate` AS `ha_remote_schemes___updatedate_raw`, '.
			'`ha_remote_schemes`.`name` AS `ha_remote_schemes___name`, '.
			'`ha_remote_schemes`.`name` AS `ha_remote_schemes___name_raw`, '.
			'`ha_remote_schemes`.`group` AS `ha_remote_schemes___group`, '.
			'`ha_remote_schemes`.`group` AS `ha_remote_schemes___group_raw`, '.
			'`ha_remote_schemes`.`rkey` AS `ha_remote_schemes___rkey`, `ha_remote_schemes`.`rkey` AS `ha_remote_schemes___rkey_raw`, '.
			'`ha_remote_schemes`.`sort` AS `ha_remote_schemes___sort`, `ha_remote_schemes`.`sort` AS `ha_remote_schemes___sort_raw`, '.
			'`ha_remote_scheme_steps`.`id` AS `ha_remote_scheme_steps___id`, '.
			'`ha_remote_scheme_steps`.`id` AS `ha_remote_scheme_steps___id_raw`, '.
			'`ha_remote_scheme_steps`.`updatedate` AS `ha_remote_scheme_steps___updatedate`, '.
			'`ha_remote_scheme_steps`.`updatedate` AS `ha_remote_scheme_steps___updatedate_raw`,'.
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
 */			
			if ($params['deviceID'] != null) {

			$mysql = ' SELECT DISTINCT `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate`, `ha_mf_devices`.`updatedate` AS `ha_mf_devices___updatedate_raw`, 
		`ha_mf_devices`.`id` AS `ha_mf_devices___id`, `ha_mf_devices`.`id` AS `ha_mf_devices___id_raw`, `ha_mf_devices`.`description` AS `ha_mf_devices___description`, 
		`ha_mf_devices`.`description` AS `ha_mf_devices___description_raw`, `ha_mf_devices`.`shortdesc` AS `ha_mf_devices___shortdesc`, `ha_mf_devices`.`shortdesc` AS 
		`ha_mf_devices___shortdesc_raw`, `ha_mf_devices`.`monitortypeID` AS `ha_mf_devices___monitortypeID_raw`, `ha_mi_monitor_type`.`description` AS `ha_mf_devices___monitortypeID`, 
		`ha_mf_devices`.`code` AS `ha_mf_devices___code`, `ha_mf_devices`.`code` AS `ha_mf_devices___code_raw`, `ha_mf_devices`.`unit` AS `ha_mf_devices___unit`, 
		`ha_mf_devices`.`unit` AS `ha_mf_devices___unit_raw`, 
		`ha_mf_devices`.`typeID` AS `ha_mf_devices___typeID_raw`, 
		`ha_mf_device_types`.`display_icon` AS `ha_mf_device_types___display_icon`,
		`ha_mf_device_types`.`description` AS  `ha_mf_device_types___description`,
		`ha_mf_devices`.`locationID` AS `ha_mf_devices___locationID_raw`, 
		`ha_mf_locations`.`display_icon` AS `ha_mf_locations___display_icon`, 
		`ha_mf_locations`.`description` AS `ha_mf_locations___description`, 
		`ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse`, 
		`ha_mf_devices`.`inuse` AS `ha_mf_devices___inuse_raw`, `ha_mf_devices`.`ipaddressID` AS `ha_mf_devices___ipaddressID_raw`, 
		`ha_mf_device_ipaddress`.`name` AS `ha_mf_devices___ipaddressID`, `ha_mf_devices`.`devicelinkID` AS `ha_mf_devices___devicelinkID_raw`, 
		`ha_mf_device_links`.`name` AS `ha_mf_devices___devicelinkID`, `ha_mf_devices`.`commandclassID` AS `ha_mf_devices___commandclassID_raw`, 
		`ha_mf_commands_class_dd`.`description` AS `ha_mf_devices___commandclassID`, `ha_mf_devices`.`sort` AS `ha_mf_devices___sort`, 
		`ha_mf_devices`.`sort` AS `ha_mf_devices___sort_raw`, `ha_mf_monitor_status`.`updatedate` AS `ha_mf_monitor_status___updatedate`, 
		`ha_mf_monitor_status`.`updatedate` AS `ha_mf_monitor_status___updatedate_raw`, `ha_mf_monitor_status`.`deviceID` AS `ha_mf_monitor_status___deviceID`, 
		`ha_mf_monitor_status`.`deviceID` AS `ha_mf_monitor_status___deviceID_raw`, `ha_mf_monitor_status`.`toggleignore` AS `ha_mf_monitor_status___toggleignore`, 
		`ha_mf_monitor_status`.`toggleignore` AS `ha_mf_monitor_status___toggleignore_raw`, `ha_mf_monitor_status`.`id` AS `ha_mf_monitor_status___id`, 
		`ha_mf_monitor_status`.`id` AS `ha_mf_monitor_status___id_raw`, `ha_mf_monitor_status`.`status` AS `ha_mf_monitor_status___status`, `ha_mf_monitor_status`.`status` AS 
		`ha_mf_monitor_status___status_raw`, `ha_mf_monitor_status`.`statusDate` AS `ha_mf_monitor_status___statusDate`, `ha_mf_monitor_status`.`statusDate` AS 
		`ha_mf_monitor_status___statusDate_raw`, `ha_mf_monitor_status`.`invertstatus` AS `ha_mf_monitor_status___invertstatus`, `ha_mf_monitor_status`.`invertstatus` AS 
		`ha_mf_monitor_status___invertstatus_raw`, `ha_mf_monitor_link`.`linkmonitor` AS `ha_mf_monitor_link___linkmonitor`, `ha_mf_monitor_link`.`linkmonitor` AS 
		`ha_mf_monitor_link___linkmonitor_raw`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id`, `ha_mf_monitor_link`.`id` AS `ha_mf_monitor_link___id_raw`, 
		`ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate`, `ha_mf_monitor_link`.`updatedate` AS `ha_mf_monitor_link___updatedate_raw`, 
		`ha_mf_monitor_link`.`listenfor1` AS `ha_mf_monitor_link___listenfor1_raw`, `ha_vw_commands_1`.`Description` AS `ha_mf_monitor_link___listenfor1`, `ha_mf_monitor_link`.`listenfor2` AS 
		`ha_mf_monitor_link___listenfor2_raw`, `ha_vw_commands`.`Description` AS `ha_mf_monitor_link___listenfor2`, `ha_mf_monitor_link`.`listenfor3` AS `ha_mf_monitor_link___listenfor3_raw`,
		`ha_vw_commands_0`.`Description` AS `ha_mf_monitor_link___listenfor3`, `ha_mf_monitor_link`.`frequency1` AS `ha_mf_monitor_link___frequency1`, `ha_mf_monitor_link`.`frequency1` AS
		`ha_mf_monitor_link___frequency1_raw`, `ha_mf_monitor_link`.`frequency2` AS `ha_mf_monitor_link___frequency2`, `ha_mf_monitor_link`.`frequency2` AS
		`ha_mf_monitor_link___frequency2_raw`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport`, `ha_mf_monitor_link`.`pingport` AS `ha_mf_monitor_link___pingport_raw`, 
		`ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout`, `ha_mf_monitor_link`.`link_timeout` AS `ha_mf_monitor_link___link_timeout_raw`, `ha_mf_monitor_link`.`link`
		AS `ha_mf_monitor_link___link`, `ha_mf_monitor_link`.`link` AS `ha_mf_monitor_link___link_raw`, `ha_mf_monitor_link`.`mdate` AS `ha_mf_monitor_link___mdate`, `ha_mf_monitor_link`.`mdate`
		AS `ha_mf_monitor_link___mdate_raw`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID`, `ha_mf_monitor_link`.`deviceID` AS `ha_mf_monitor_link___deviceID_raw`, 
		`ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id`, `ha_mf_devices_thermostat`.`id` AS `ha_mf_devices_thermostat___id_raw`, `ha_mf_devices_thermostat`.`site1` AS 
		`ha_mf_devices_thermostat___site1`, `ha_mf_devices_thermostat`.`site1` AS `ha_mf_devices_thermostat___site1_raw`, `ha_mf_devices_thermostat`.`empty1` AS 
		`ha_mf_devices_thermostat___empty1`, `ha_mf_devices_thermostat`.`empty1` AS `ha_mf_devices_thermostat___empty1_raw`, `ha_mf_devices_thermostat`.`deviceID` AS 
		`ha_mf_devices_thermostat___deviceID`, `ha_mf_devices_thermostat`.`deviceID` AS `ha_mf_devices_thermostat___deviceID_raw`, `ha_mf_devices_thermostat`.`tstat_uuid` 
		AS `ha_mf_devices_thermostat___tstat_uuid`, `ha_mf_devices_thermostat`.`tstat_uuid` AS `ha_mf_devices_thermostat___tstat_uuid_raw`, `ha_mf_devices_thermostat`.`model` 
		AS `ha_mf_devices_thermostat___model`, `ha_mf_devices_thermostat`.`model` AS `ha_mf_devices_thermostat___model_raw`, `ha_mf_devices_thermostat`.`fw_version` AS 
		`ha_mf_devices_thermostat___fw_version`, `ha_mf_devices_thermostat`.`fw_version` AS `ha_mf_devices_thermostat___fw_version_raw`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS 
		`ha_mf_devices_thermostat___wlan_fw_version`, `ha_mf_devices_thermostat`.`wlan_fw_version` AS `ha_mf_devices_thermostat___wlan_fw_version_raw`, `ha_mf_devices_thermostat`.`name` AS 
		`ha_mf_devices_thermostat___name`, `ha_mf_devices_thermostat`.`name` AS `ha_mf_devices_thermostat___name_raw`, `ha_mf_devices_thermostat`.`description` AS
		`ha_mf_devices_thermostat___description`, `ha_mf_devices_thermostat`.`description` AS `ha_mf_devices_thermostat___description_raw`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS
		`ha_mf_devices_thermostat___away_heat_temp_c`, `ha_mf_devices_thermostat`.`away_heat_temp_c` AS `ha_mf_devices_thermostat___away_heat_temp_c_raw`, 
		`ha_mf_devices_thermostat`.`here_temp_heat_c` AS `ha_mf_devices_thermostat___here_temp_heat_c`, `ha_mf_devices_thermostat`.`here_temp_heat_c` AS 
		`ha_mf_devices_thermostat___here_temp_heat_c_raw`, `ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c`, 
		`ha_mf_devices_thermostat`.`away_cool_temp_c` AS `ha_mf_devices_thermostat___away_cool_temp_c_raw`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS 
		`ha_mf_devices_thermostat___here_temp_cool_c`, `ha_mf_devices_thermostat`.`here_temp_cool_c` AS `ha_mf_devices_thermostat___here_temp_cool_c_raw`, `ha_mf_monitor_triggers`.`id` 
		AS `ha_mf_monitor_triggers___id`, `ha_mf_monitor_triggers`.`id` AS `ha_mf_monitor_triggers___id_raw`, `ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID`,
		`ha_mf_monitor_triggers`.`deviceID` AS `ha_mf_monitor_triggers___deviceID_raw`, `ha_mf_monitor_triggers`.`statuslink` AS `ha_mf_monitor_triggers___statuslink`,
		`ha_mf_monitor_triggers`.`statuslink` AS `ha_mf_monitor_triggers___statuslink_raw`, `ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype`,
		`ha_mf_monitor_triggers`.`triggertype` AS `ha_mf_monitor_triggers___triggertype_raw`, `ha_mf_monitor_triggers`.`schemeID` AS `ha_mf_monitor_triggers___schemeID_raw`, 
		`ha_remote_schemes`.`name` AS `ha_mf_monitor_triggers___schemeID`, `ha_mf_devices`.`id` AS slug , `ha_mf_devices`.`id` AS `__pk_val` FROM `ha_mf_devices` LEFT JOIN `ha_mf_locations` 
		AS `ha_mf_locations` ON `ha_mf_locations`.`id` = `ha_mf_devices`.`locationID` LEFT JOIN `ha_mi_monitor_type` AS `ha_mi_monitor_type` ON `ha_mi_monitor_type`.
		`id` = `ha_mf_devices`.`monitortypeID` LEFT JOIN `ha_mf_monitor_link` AS `ha_mf_monitor_link` ON `ha_mf_monitor_link`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_vw_commands`
		AS `ha_vw_commands` ON `ha_vw_commands`.`id` = `ha_mf_monitor_link`.`listenfor2` LEFT JOIN `ha_vw_commands` AS `ha_vw_commands_0` ON `ha_vw_commands_0`.`id` = 
		`ha_mf_monitor_link`.`listenfor3` LEFT JOIN `ha_mf_monitor_status` AS `ha_mf_monitor_status` ON `ha_mf_monitor_status`.`deviceID` = `ha_mf_devices`.`id` 
		LEFT JOIN `ha_mf_devices_thermostat` AS `ha_mf_devices_thermostat` ON `ha_mf_devices_thermostat`.`deviceID` = `ha_mf_devices`.`id` LEFT JOIN `ha_vw_commands` AS `ha_vw_commands_1` 
		ON `ha_vw_commands_1`.`id` = `ha_mf_monitor_link`.`listenfor1` LEFT JOIN `ha_mf_device_types` AS `ha_mf_device_types` ON `ha_mf_device_types`.`id` = `ha_mf_devices`.`typeID` 
		LEFT JOIN `ha_mf_commands_class_dd` AS `ha_mf_commands_class_dd` ON `ha_mf_commands_class_dd`.`id` = `ha_mf_devices`.`commandclassID` LEFT JOIN `ha_mf_device_ipaddress` AS 
		`ha_mf_device_ipaddress` ON `ha_mf_device_ipaddress`.`id` = `ha_mf_devices`.`ipaddressID` LEFT JOIN `ha_mf_device_links` AS `ha_mf_device_links` ON `ha_mf_device_links`.`id` = 
		`ha_mf_devices`.`devicelinkID` LEFT JOIN `ha_mf_monitor_triggers` AS `ha_mf_monitor_triggers` ON `ha_mf_monitor_triggers`.`deviceID` = `ha_mf_devices`.`id`
		LEFT JOIN `ha_remote_schemes` AS `ha_remote_schemes` ON `ha_remote_schemes`.`id` = `ha_mf_monitor_triggers`.`schemeID` WHERE ha_mf_devices.id = '.$params['deviceID'];
			}
			break;
			
	}
 
	if (isset($mysql)) {

		if ($data = FetchRow($mysql)) {
		//echo "<pre>"; print_r ($data); echo "</pre>";
			foreach ($data as $key => $value) {
				$pattern[$key]="/\{".$key."\}/";
			}
			$subject=preg_replace($pattern, $data, $subject);
			$subject=preg_replace($pattern, $data, $subject); // twice to support tag in tag
			$message=preg_replace($pattern, $data, $message); // twice to support tag in tag
			$message=preg_replace($pattern, $data, $message);
		}
/*		if ($data = FetchRow($mysqldev)) {
echo 'here2';
	//	echo "<pre>"; print_r ($data); echo "</pre>";
			foreach ($data as $key => $value) {
				$pattern[$key]="/\{".$key."\}/";
			}
			$subject=preg_replace($pattern, $data, $subject);
			$subject=preg_replace($pattern, $data, $subject); // twice to support tag in tag
			$message=preg_replace($pattern, $data, $message); // twice to support tag in tag
			$message=preg_replace($pattern, $data, $message);
		} */
	}


	return true;
}


function sendmail($to, $subject, $message, $fromname) {

	$mailer = new PHPMailer();
	$mailer->IsSMTP();
	$mailer->Host = 'ssl://smtp.gmail.com:465';
	$mailer->SMTPAuth = true;
	
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
	    return false;
	}
	else {
		return true;
	}
}
?>