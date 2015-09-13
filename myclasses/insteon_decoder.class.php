<?php
/**
 * Class capable of encoding GSM 03.38 default alphabet and packing octets into septets as described by GSM 03.38.
 * Based on mapping: http://www.unicode.org/Public/MAPPINGS/ETSI/GSM0338.TXT
 * 
 * Copyright (C) 2011 OnlineCity
 * Licensed under the MIT license, which can be read at: http://www.opensource.org/licenses/mit-license.php
 * @author hd@onlinecity.dk
 */
class InsteonCoder
{
	private static $plmcmd = array (
	'insteon_something' 	=> '0221',
	'insteon_received' 	=> '0250',
	'insteon_ext_received' 	=> '0251',
	'x10_received' 		=> '0252',
	'all_link_complete' 	=> '0253',
	'plm_button_event' 	=> '0254',
	'user_plm_reset' 		=> '0255',
	'all_link_clean_failed' 	=> '0256',
	'all_link_record' 	=> '0257',
	'all_link_clean_status' 	=> '0258',
	'plm_info' 		=> '0260',
	'all_link_send' 		=> '0261',
	'insteon_send' 		=> '0262',
#	insteon_ext_send	=> '0262',
	'x10_send' 		=> '0263',
	'all_link_start' 		=> '0264',
	'all_link_cancel'		=> '0265',
	'set_host_device_cat'     => '0266',
	'plm_reset' 		=> '0267',
	'set_insteon_ack_cmd2'    => '0268',
	'all_link_first_rec'	=> '0269',
	'all_link_next_rec'	=> '026a',
	'plm_set_config' 		=> '026b',
	'get_sender_all_link_rec' => '026c',
	'plm_led_on' 		=> '026d',
	'plm_led_off' 		=> '026e',
	'all_link_manage_rec'	=> '026f',
	'insteon_nak' 		=> '0270',
	'insteon_ack' 		=> '0271',
	'rf_sleep' 		=> '0272',
	'plm_get_config' 		=> '0273'
);

// Using 0 * 2 
// I am using 0 to feedback length for buffer cut off
private static $plmcmdlen = array (			
	'0204' => array (10, 10), 	// Added 8/16/15
	'020B' => array (10, 10), 	// Added 8/16/15
	'0221' => array (10, 10),	// Fix was grabbing next 02
	'0224' => array (10, 10), 	// Added 8/16/15
	'0250' => array (11, 11),   // OK
	'0251' => array (25, 25),	// Not getting or logging
	'0252' => array (4, 4),     // OK
	'0253' => array (10, 10),	// Not getting or logging
	'0254' => array (3, 3),		// Not getting or logging
	'0255' => array (2, 2),		// Not getting or logging
	'0256' => array (6, 6),		// Not getting or logging
	'0257' => array (10, 10),	// Not getting or logging
	'0258' => array (3, 3),		// Not getting or logging
	'0260' => array (2, 9),		// Not getting or logging
	'0261' => array (5, 6),		// Not getting or logging
	'0262' => array (9, 9 ), 	// Pretty consistent 9
	'0263' => array (5, 5),		// OK
	'0264' => array (4, 5),  	// Not getting or logging
	'0265' => array (2, 3),		// Not getting or logging
	'0266' => array (5, 6),		// Not getting or logging
	'0267' => array (2, 3),		// Not getting or logging
	'0268' => array (3, 4),		// Not getting or logging
	'0269' => array (2, 3),		// Not getting or logging
	'026A' => array (2, 3),		// Not getting or logging
	'026B' => array (3, 4),		// Not getting or logging
	'026C' => array (2, 3),		// Not getting or logging
	'026D' => array (2, 3),		// Not getting or logging
	'026E' => array (2, 3),		// Not getting or logging
	'026F' => array (11, 12),	// Not getting or logging
	'0270' => array (3, 4),		// Not getting or logging
	'0271' => array (4, 5),		// Not getting or logging
	'0272' => array (2, 3),		// Not getting or logging
	'0273' => array (6, 6),		// OK 
);	

private static $inout_a = Array (
		'0204' => COMMAND_IO_RECV,	// Added 8/16/15
		'020B' => COMMAND_IO_RECV,	// Added 8/16/15
		'0221' => COMMAND_IO_RECV,
		'0250' => COMMAND_IO_RECV,
		'0251' => COMMAND_IO_RECV,
		'0252' => COMMAND_IO_RECV,
		'0253' => COMMAND_IO_BOTH,
		'0254' => COMMAND_IO_RECV,
		'0255' => COMMAND_IO_BOTH,
		'0256' => COMMAND_IO_BOTH,
		'0257' => COMMAND_IO_BOTH,
		'0258' => COMMAND_IO_BOTH,
		'0260' => COMMAND_IO_RECV,
		'0261' => COMMAND_IO_SEND,
		'0262' => COMMAND_IO_SEND,
		'0263' => COMMAND_IO_SEND,
		'0264' => COMMAND_IO_BOTH,
		'0265' => COMMAND_IO_BOTH,
		'0266' => COMMAND_IO_BOTH,
		'0267' => COMMAND_IO_BOTH,
		'0268' => COMMAND_IO_BOTH,
		'0269' => COMMAND_IO_BOTH,
		'026A' => COMMAND_IO_BOTH,
		'026B' => COMMAND_IO_BOTH,
		'026C' => COMMAND_IO_BOTH,
		'026D' => COMMAND_IO_BOTH,
		'026E' => COMMAND_IO_BOTH,
		'026F' => COMMAND_IO_BOTH,
		'0270' => COMMAND_IO_RECV,
		'0271' => COMMAND_IO_RECV,
		'0272' => COMMAND_IO_BOTH,
		'0273' => COMMAND_IO_BOTH
);

/*#Mapping from message type bit field to acronyms used in
#  the INSTEON Command Tables documentation
#100 4 - SB: Standard Broadcast

#000 0 - SD or ED: Standard/Extended Direct
#001 1 - SDA or EDA: Standard/Extended Direct ACK
#101 5 - SDN or EDN: Standard/Extended Direct NACK

#110 6 - SA: Standard All-Link Broadcast
#010 2 - SC: Standard Cleanup Direct
#011 3 - SCA: Standard Cleanup Direct ACK
#111 7 - SCN: Standard Cleanup Direct NACK

#List below is maintained in an Excel spreadsheet.  Make 
#changes there and cut-n-paste list to here
#You should understand the parsing logic before attempting
#to modify this table! */
private static $insteonCmd = array (
'SD01' => array ('Cmd1Name'=>'Assign to All-Link Group','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'165'),
'SB01' => array ('Cmd1Name'=>'SET Button Pressed Respond','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'229'),
'SD02' => array ('Cmd1Name'=>'Delete from All-Link Group','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'170'),
'SB02' => array ('Cmd1Name'=>'SET Button Pressed Controller','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'228'),
'SD03' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'171'),
'SD0300' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Product Data Request','commandID'=>'171'),
'SD0301' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'FxName Request','commandID'=>'171'),
'SD0302' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Device Text String Request','commandID'=>'171'),
'ED03' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'172'),
'ED0300' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Product Data Response','commandID'=>'172'),
'ED0301' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'FX Username Response','commandID'=>'172'),
'ED0302' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Device Text String Response','commandID'=>'172'),
'ED0303' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Set Device Text String','commandID'=>'172'),
'ED0304' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Set ALL-Link Command Alias','commandID'=>'172'),
'ED0305' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Set ALL-Link Command Alias ED','commandID'=>'172'),
'SB03' => array ('Cmd1Name'=>'Test Powerline Phase','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'243'),
'SB0300' => array ('Cmd1Name'=>'Test Powerline Phase','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Phase A','commandID'=>'243'),
'SB0301' => array ('Cmd1Name'=>'Test Powerline Phase','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Phase B','commandID'=>'243'),
'SB04' => array ('Cmd1Name'=>'Heartbeat','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Battery Level','commandID'=>'182'),
'SA06' => array ('Cmd1Name'=>'All-Link Cleanup Report','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Fail Count','commandID'=>'164'),
'SD09' => array ('Cmd1Name'=>'Enter Linking Mode','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'176'),
'SD0a' => array ('Cmd1Name'=>'Enter Unlinking Mode','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'177'),
'SD0d' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'179'),
'SDA0d' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'179'),
'SDA0d00' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'i1','commandID'=>'179'),
'SDA0d01' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'i2','commandID'=>'179'),
'SDA0d02' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'i2CS','commandID'=>'179'),
'SD0f' => array ('Cmd1Name'=>'Ping','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'151'),
'SD10' => array ('Cmd1Name'=>'ID Request','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'194'),
'SD11' => array ('Cmd1Name'=>'Light ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Level','commandID'=>'17'),
'SA11' => array ('Cmd1Name'=>'Sensor','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'163'),   // Water sensor
'SA1100' => array ('Cmd1Name'=>'Light ON','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Light ON','commandID'=>'17'),  // Manual Light On
'SA1104' => array ('Cmd1Name'=>'Sensor','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Ping','commandID'=>'151'),  // Water sensor
'SA1101' => array ('Cmd1Name'=>'Sensor','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'ON','commandID'=>'17'), // Water sensor
'SA1102' => array ('Cmd1Name'=>'Sensor','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'OFF','commandID'=>'20'),  // Water sensor
'SC11' => array ('Cmd1Name'=>'ALL-Link Recall','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'163'),
'SD12' => array ('Cmd1Name'=>'Light ON Fast','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Level','commandID'=>'17'),
'SA12' => array ('Cmd1Name'=>'ALL-Link Alias 2 High','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'156'),
'SC12' => array ('Cmd1Name'=>'ALL-Link Alias 2 High','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'156'),
'SD13' => array ('Cmd1Name'=>'Light OFF','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'20'),
//'SA13' => array ('Cmd1Name'=>'ALL-Link Alias 1 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'257'),
'SA13' => array ('Cmd1Name'=>'Light OFF','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'20'),
'SA1300' => array ('Cmd1Name'=>'Manual Off','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'','commandID'=>'20'),
'SA1301' => array ('Cmd1Name'=>'Sensor','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'OFF','commandID'=>'20'), // Door sensor
'SA1304' => array ('Cmd1Name'=>'Sensor','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Ping','commandID'=>'151'),  // Door sensoor
'SC13' => array ('Cmd1Name'=>'ALL-Link Alias 1 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'155'),
'SD14' => array ('Cmd1Name'=>'Light OFF Fast','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'20'),
'SA14' => array ('Cmd1Name'=>'ALL-Link Alias 2 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'157'),
'SC14' => array ('Cmd1Name'=>'ALL-Link Alias 2 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'157'),
'SD15' => array ('Cmd1Name'=>'Light Brighten One Step','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'15'),
'SA15' => array ('Cmd1Name'=>'ALL-Link Alias 3 High','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'158'),
'SC15' => array ('Cmd1Name'=>'ALL-Link Alias 3 High','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'158'),
'SD16' => array ('Cmd1Name'=>'Light Dim One Step','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'13'),
'SA16' => array ('Cmd1Name'=>'ALL-Link Alias 3 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'159'),
'SC16' => array ('Cmd1Name'=>'ALL-Link Alias 3 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'159'),
'SD17' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'17'),
'SD1700' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Down','commandID'=>'17'),
'SD1701' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Up','commandID'=>'17'),
//'SA17' => array ('Cmd1Name'=>'ALL-Link Alias 4 High','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'160'),
'SA17' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'17'),
'SA1700' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Down','commandID'=>'17'),
'SA1701' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Up','commandID'=>'17'),
'SC17' => array ('Cmd1Name'=>'ALL-Link Alias 4 High','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'160'),
'SD18' => array ('Cmd1Name'=>'Light Stop Manual Change','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'17'),
'SA18' => array ('Cmd1Name'=>'ALL-Link Alias 4 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'161'),
'SC18' => array ('Cmd1Name'=>'ALL-Link Alias 4 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'161'),
'SD19' => array ('Cmd1Name'=>'Light Status Request','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'5'),
'SD1900' => array ('Cmd1Name'=>'Light Status Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'On Level','commandID'=>'5'),
'SD1901' => array ('Cmd1Name'=>'Light Status Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'LED Bit Flags','commandID'=>'5'),
'SD1f' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'180'),
'SD1f00' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Request Flags','commandID'=>'180'),
'SD1f01' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'All-Link Database Delta Number','commandID'=>'180'),
'SD1f02' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Signal-to-Noise','commandID'=>'180'),
'SDA1f' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Config Flags','commandID'=>'180'),
'SD20' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'231'),
'SD2000' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Program Lock On','commandID'=>'231'),
'SD2001' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Program Lock Off','commandID'=>'231'),
'SD2002' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2003' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2004' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2005' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2006' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2007' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2008' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD2009' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD200a' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD200b' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Device Dependent','commandID'=>'231'),
'SD21' => array ('Cmd1Name'=>'Light Instant Change','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'On Level','commandID'=>'198'),
'SA21' => array ('Cmd1Name'=>'ALL-Link Alias 5','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'162'),
'SC21' => array ('Cmd1Name'=>'ALL-Link Alias 5','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group','commandID'=>'162'),
'SD22' => array ('Cmd1Name'=>'Light Manually Turned Off','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'20'),
'SD23' => array ('Cmd1Name'=>'Light Manually Turned On','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'17'),
'SD24' => array ('Cmd1Name'=>'Reread Init Values(Deprecated)','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'226'),
'SD25' => array ('Cmd1Name'=>'Remote SET Button Tap','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'225'),
'SD2501' => array ('Cmd1Name'=>'Remote SET Button Tap','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'1 Tap','commandID'=>'225'),
'SD2502' => array ('Cmd1Name'=>'Remote SET Button Tap','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'2 Taps','commandID'=>'225'),
'SD27' => array ('Cmd1Name'=>'Light Set Status','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'On Level','commandID'=>'207'),
'SB27' => array ('Cmd1Name'=>'Status Change','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data','commandID'=>'242'),
'SD28' => array ('Cmd1Name'=>'Set Address MSB(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'MSB','commandID'=>'230'),
'SD29' => array ('Cmd1Name'=>'Poke One Byte(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data','commandID'=>'216'),
'ED2a' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'168'),
'ED2a00' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Transfer Failure','commandID'=>'168'),
'ED2a01' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Complete (1 byte)','commandID'=>'168'),
'ED2a02' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Complete (2 bytes)','commandID'=>'168'),
'ED2a03' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Complete (3 bytes)','commandID'=>'168'),
'ED2a04' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Complete (4 bytes)','commandID'=>'168'),
'ED2a05' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Complete (5 bytes)','commandID'=>'168'),
'ED2a06' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Complete (6 bytes)','commandID'=>'168'),
'ED2a07' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'Complete (7 bytes)','commandID'=>'168'),
'ED2a08' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'Complete (8 bytes)','commandID'=>'168'),
'ED2a09' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Complete (9 bytes)','commandID'=>'168'),
'ED2a0a' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Complete (10 bytes)','commandID'=>'168'),
'ED2a0b' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Complete (11 bytes)','commandID'=>'168'),
'ED2a0c' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Complete (12 bytes)','commandID'=>'168'),
'ED2a0d' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Complete (13 bytes)','commandID'=>'168'),
'ED2aff' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0xff','Cmd2Name'=>'Request Block Data Transfer','commandID'=>'168'),
'SD2b' => array ('Cmd1Name'=>'Peek One Byte(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'LSB of Address','commandID'=>'213'),
'SDA2b' => array ('Cmd1Name'=>'Peek One Byte(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Peeked Byte','commandID'=>'213'),
'SD2c' => array ('Cmd1Name'=>'Peek One Byte Internal(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'LSB of Address','commandID'=>'212'),
'SDA2c' => array ('Cmd1Name'=>'Peek One Byte Internal(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Peeked Byte','commandID'=>'212'),
'SD2d' => array ('Cmd1Name'=>'Poke One Byte Internal(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data','commandID'=>'215'),
'SD2e' => array ('Cmd1Name'=>'Light ON at Ramp Rate','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Level and Rate','commandID'=>'17'),
'ED2e' => array ('Cmd1Name'=>'Extended Set/Get','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'178'),
'ED2e00' => array ('Cmd1Name'=>'Extended Set/Get','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Command in D2','commandID'=>'178'),
'SD2f' => array ('Cmd1Name'=>'Light OFF at Ramp Rate','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Ramp Rate','commandID'=>'20'),
'ED2f' => array ('Cmd1Name'=>'Read/Write ALL-Link Database','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'224'),
'ED2f00' => array ('Cmd1Name'=>'Read/Write ALL-Link Database','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Command in D2','commandID'=>'224'),
'SD30' => array ('Cmd1Name'=>'Beep','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Duration','commandID'=>'167'),
'ED30' => array ('Cmd1Name'=>'Trigger ALL-Link Command','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'255'),
'ED3000' => array ('Cmd1Name'=>'Trigger ALL-Link Command','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Trigger Command','commandID'=>'255'),
'SD40' => array ('Cmd1Name'=>'Sprinkler Valve On','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Valve Number','commandID'=>'17'),
'ED40' => array ('Cmd1Name'=>'Set Sprinkler Program','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number','commandID'=>'233'),
'SD41' => array ('Cmd1Name'=>'Sprinkler Valve Off','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Valve Number','commandID'=>'20'),
'ED41' => array ('Cmd1Name'=>'Sprinkler Get Program Response','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number','commandID'=>'237'),
'SD42' => array ('Cmd1Name'=>'Sprinkler Program ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number','commandID'=>'17'),
'SD43' => array ('Cmd1Name'=>'Sprinkler Program OFF','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number','commandID'=>'20'),
'SD44' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'236'),
'SD4400' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values','commandID'=>'236'),
'SD4401' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM From RAM','commandID'=>'236'),
'SD4402' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Get Valve Status','commandID'=>'236'),
'SD4403' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Inhibit Command Acceptance','commandID'=>'236'),
'SD4404' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Resume Command Acceptance','commandID'=>'236'),
'SD4405' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Skip Forward','commandID'=>'236'),
'SD4406' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Skip Backwards','commandID'=>'236'),
'SD4407' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'Enable Pump on V8','commandID'=>'236'),
'SD4408' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'Disable Pump on V8','commandID'=>'236'),
'SD4409' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Broadcast ON','commandID'=>'236'),
'SD440a' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Broadcast OFF','commandID'=>'236'),
'SD440b' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Load RAM from EEPROM','commandID'=>'236'),
'SD440c' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Sensor ON','commandID'=>'236'),
'SD440d' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Sensor OFF','commandID'=>'236'),
'SD440e' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0e','Cmd2Name'=>'Diagnostics ON','commandID'=>'236'),
'SD440f' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0f','Cmd2Name'=>'Diagnostics OFF','commandID'=>'236'),
'SD45' => array ('Cmd1Name'=>'I/O Output ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Output Number','commandID'=>'17'),
'SD46' => array ('Cmd1Name'=>'I/O Output OFF','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Output Number','commandID'=>'187'),
'SD47' => array ('Cmd1Name'=>'I/O Alarm Data Request','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'183'),
'SD48' => array ('Cmd1Name'=>'I/O Write Output Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data','commandID'=>'193'),
'SDA48' => array ('Cmd1Name'=>'I/O Write Output Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data Written','commandID'=>'193'),
'SD49' => array ('Cmd1Name'=>'I/O Read Input Port','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'190'),
'SDA49' => array ('Cmd1Name'=>'I/O Read Input Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data Read','commandID'=>'190'),
'SD4a' => array ('Cmd1Name'=>'Get Sensor Value','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Sensor Number','commandID'=>'181'),
'SDA4a' => array ('Cmd1Name'=>'Get Sensor Value','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Sensor Value','commandID'=>'181'),
'SD4b' => array ('Cmd1Name'=>'Set Sensor 1 Alarm Trigger OFF->ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Nominal Value','commandID'=>'232'),
'ED4b' => array ('Cmd1Name'=>'I/O Set Sensor Nominal','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Sensor Number','commandID'=>'191'),
'SD4c' => array ('Cmd1Name'=>'I/O Get Sensor Alarm Delta','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field','commandID'=>'185'),
'ED4c' => array ('Cmd1Name'=>'I/O Alarm Data Response','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'184'),
'ED4c00' => array ('Cmd1Name'=>'I/O Alarm Data Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Response','commandID'=>'184'),
'SD4d' => array ('Cmd1Name'=>'I/O Write Configuration Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field','commandID'=>'192'),
'SD4e' => array ('Cmd1Name'=>'I/O Read Configuration Port','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'189'),
'SD4e' => array ('Cmd1Name'=>'I/O Read Configuration Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'I/O Port Config','commandID'=>'189'),
'SD4f' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'186'),
'SD4f00' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values','commandID'=>'186'),
'SD4f01' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM from RAM','commandID'=>'186'),
'SD4f02' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Status Request','commandID'=>'186'),
'SD4f03' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Read Analog once','commandID'=>'186'),
'SD4f04' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Read Analog Always','commandID'=>'186'),
'SD4f09' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Enable status change message','commandID'=>'186'),
'SD4f0a' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Disable status change message','commandID'=>'186'),
'SD4f0b' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Load RAM from EEPROM','commandID'=>'186'),
'SD4f0c' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Sensor On','commandID'=>'186'),
'SD4f0d' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Sensor Off','commandID'=>'186'),
'SD4f0e' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0e','Cmd2Name'=>'Diagnostics On','commandID'=>'186'),
'SD4f0f' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0f','Cmd2Name'=>'Diagnostics Off','commandID'=>'186'),
'SD50' => array ('Cmd1Name'=>'Pool Device ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Device Number','commandID'=>'17'),
'ED50' => array ('Cmd1Name'=>'Pool Set Device Temperature','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'220'),
'ED5000' => array ('Cmd1Name'=>'Pool Set Device Temperature','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Set Temperature','commandID'=>'220'),
'ED5001' => array ('Cmd1Name'=>'Pool Set Device Temperature','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Set Hysteresis','commandID'=>'220'),
'SD51' => array ('Cmd1Name'=>'Pool Device OFF','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Device Number','commandID'=>'20'),
'SD52' => array ('Cmd1Name'=>'Pool Temperature Up','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Increment Count','commandID'=>'222'),
'SD53' => array ('Cmd1Name'=>'Pool Temperature Down','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Decrement Count','commandID'=>'221'),
'SD54' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'217'),
'SD5400' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values','commandID'=>'217'),
'SD5401' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM From RAM','commandID'=>'217'),
'SD5402' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Get Pool Mode','commandID'=>'217'),
'SD5403' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Get Ambient Temp','commandID'=>'217'),
'SD5404' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Get Water Temp','commandID'=>'217'),
'SD5405' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Get pH','commandID'=>'217'),
'SD58' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'173'),
'SD5800' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Raise Door','commandID'=>'173'),
'SD5801' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Lower Door','commandID'=>'173'),
'SD5802' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Open Door','commandID'=>'173'),
'SD5803' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Close Door','commandID'=>'173'),
'SD5804' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Stop Door','commandID'=>'173'),
'SD5805' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Single Door Open','commandID'=>'173'),
'SD5806' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Single Door Close','commandID'=>'173'),
'SD59' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'174'),
'SD5900' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Raise Door','commandID'=>'174'),
'SD5901' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Lower Door','commandID'=>'174'),
'SD5902' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Open Door','commandID'=>'174'),
'SD5903' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Close Door','commandID'=>'174'),
'SD5904' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Stop Door','commandID'=>'174'),
'SD5905' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Single Door Open','commandID'=>'174'),
'SD5906' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Single Door Close','commandID'=>'174'),
'SD60' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'256'),
'SD6000' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Open','commandID'=>'256'),
'SD6001' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Close','commandID'=>'256'),
'SD6002' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Stop','commandID'=>'256'),
'SD6003' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Program','commandID'=>'256'),
'SD61' => array ('Cmd1Name'=>'Window Covering Position','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Position','commandID'=>'257'),
'SD68' => array ('Cmd1Name'=>'Thermostat Temp Up','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Increment Count','commandID'=>'134'),
'ED68' => array ('Cmd1Name'=>'Thermostat Zone Temp Up','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number','commandID'=>'134'),
'SD69' => array ('Cmd1Name'=>'Thermostat Temp Down','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Decrement Count','commandID'=>'135'),
'ED69' => array ('Cmd1Name'=>'Thermostat Zone Temp Down','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number','commandID'=>'135'),
'SD6a' => array ('Cmd1Name'=>'Thermostat Get Zone Info','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field','commandID'=>'245'),
'SDA6a' => array ('Cmd1Name'=>'Thermostat Get Zone Info','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Requested Data','commandID'=>'245'),
'SD6b' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'244'),
'SD6b00' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values','commandID'=>'244'),
'SD6b01' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM from RAM','commandID'=>'244'),
'SD6b02' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Get Thermostat Mode','commandID'=>'244'),
'SD6b03' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Get ambient temperature','commandID'=>'244'),
'SD6b04' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'ON Heat','commandID'=>'244'),
'SD6b05' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'ON Cool','commandID'=>'244'),
'SD6b06' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'ON Auto','commandID'=>'244'),
'SD6b07' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'ON Fan','commandID'=>'244'),
'SD6b08' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'OFF Fan','commandID'=>'244'),
'SD6b09' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'OFF All','commandID'=>'244'),
'SD6b0a' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Program Heat','commandID'=>'244'),
'SD6b0b' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Program Cool','commandID'=>'244'),
'SD6b0c' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Program Auto','commandID'=>'244'),
'SD6b0d' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Get Equipment State','commandID'=>'244'),
'SD6b0e' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0e','Cmd2Name'=>'Set Equipment State','commandID'=>'244'),
'SD6b0f' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0f','Cmd2Name'=>'Get Temperature Units','commandID'=>'244'),
'SD6b10' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x10','Cmd2Name'=>'Set Fahrenheit','commandID'=>'244'),
'SD6b11' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x11','Cmd2Name'=>'Set Celsius','commandID'=>'244'),
'SD6b12' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x12','Cmd2Name'=>'Get Fan-On Speed','commandID'=>'244'),
'SD6b13' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x13','Cmd2Name'=>'Set Fan-On Speed Low','commandID'=>'244'),
'SD6b14' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x14','Cmd2Name'=>'Set Fan-On Speed Medium','commandID'=>'244'),
'SD6b15' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x15','Cmd2Name'=>'Set Fan-On Speed High','commandID'=>'244'),
'SD6b16' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x16','Cmd2Name'=>'Enable status change message','commandID'=>'244'),
'SD6b17' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x17','Cmd2Name'=>'Disable status change message','commandID'=>'244'),
'SD6c' => array ('Cmd1Name'=>'Thermostat Set Cool Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Setpoint Value','commandID'=>'246'),
'ED6c' => array ('Cmd1Name'=>'Thermostat Set Zone Cool Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number','commandID'=>'248'),
'SD6d' => array ('Cmd1Name'=>'Thermostat Set Heat Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Setpoint Value','commandID'=>'247'),
'ED6d' => array ('Cmd1Name'=>'Thermostat Set Zone Heat Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number','commandID'=>'249'),
'SD6e' => array ('Cmd1Name'=>'Thermostat Set or Read Mode','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field','commandID'=>'250'),
'SD70' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'195'),
'SD7000' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Leak Detected','commandID'=>'195'),
'SD7001' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'No Leak Detected','commandID'=>'195'),
'SD7002' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Battery Low','commandID'=>'195'),
'SD7003' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Battery OK','commandID'=>'195'),
'SD81' => array ('Cmd1Name'=>'Assign to Companion Group(Deprecated)','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'166'),
'EDf0' => array ('Cmd1Name'=>'Read or Write Registers','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field','commandID'=>'223'),
'SDf0' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>'','commandID'=>'175'),
'SDf000' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values','commandID'=>'175'),
'SDf001' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Write a Code Record','commandID'=>'175'),
'SDf002' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Read a Code Record','commandID'=>'175'),
'SDf003' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Get a Code Record','commandID'=>'175'),
'SDf1' => array ('Cmd1Name'=>'Specific Code Record Read','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Record Number','commandID'=>'234'),
'EDf1' => array ('Cmd1Name'=>'Response to Read Registers','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field','commandID'=>'227'),
'EDf1' => array ('Cmd1Name'=>'Code Record Request Respon','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Record Number','commandID'=>'169'),
'EDf2' => array ('Cmd1Name'=>'Specific Code Record Write','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Record Number','commandID'=>'235'),
);


#X10 PLM codes
private static $x10_house_codes = array ( 	
	'6' => 'a',
	'e' => 'b',
	'2' => 'c',
	'a' => 'd',
	'1' => 'e',
	'9' => 'f',
	'5' => 'g',
	'd' => 'h',
	'7' => 'i',
	'f' => 'j',
	'3' => 'k',
	'b' => 'l',
	'0' => 'm',
	'8' => 'n',
	'4' => 'o',
	'c' => 'p'
);

private static $x10_unit_codes = array (
	'6' => '1',
	'e' => '2',
	'2' => '3',
	'a' => '4',
	'1' => '5',
	'9' => '6',
	'5' => '7',
	'd' => '8',
	'7' => '9',
	'f' => '10',
	'3' => '11',
	'b' => '12',
	'0' => '13',
	'8' => '14',
	'4' => '15',
	'c' => '16'
);

private static $x10_commands = array (
	'2' => array ( 'On', 17),
	'3' => array ( 'Off', 20), 
	'5' => array ( 'Bright', 15),
	'4' => array ( 'Dim', 13),
	'a' => array ( 'preset_dim1', 259),
	'b' => array ( 'preset_dim2', 260),
	'0' => array ( 'all_units_off', 261),
	'1' => array ( 'all_lights_on', 18),
	'6' => array ( 'all_lights_off', 262),
	'f' => array ( 'status', 5),
	'd' => array ( 'status_on', 8),
	'e' => array ( 'status_off', 6),
	'9' => array ( 'hail_ack', 263),
	'7' => array ( 'ext_code', 265),
	'c' => array ( 'ext_data', 266),
	'8' => array ( 'hail_request', 264)
);

/*Letter Code 	Preset Dim (4) 	Preset Dim (12) 	Output Level (4) 	Output Level (12)
M 	0 	16 	0.00% 	51.61%
N 	1 	17 	3.23% 	54.84%
O 	2 	18 	6.45% 	58.06%
P 	3 	19 	9.68% 	61.29%
C 	4 	20 	12.90% 	64.52%
D 	5 	21 	16.13% 	67.74%
A 	6 	22 	19.35% 	70.97%
B 	7 	23 	22.58% 	74.19%
E 	8 	24 	25.81% 	77.42%
F 	9 	25 	29.03% 	80.65%
G 	10 	26 	32.26% 	83.87%
H 	11 	27 	35.48% 	87.10%
K 	12 	28 	38.71% 	90.32%
L 	13 	29 	41.94% 	93.55%
I 	14 	30 	45.16% 	96.77%
J 	15 	31 	48.39% 	100.00% 
*/

private static $plmcmd2string;
private static $x10_unit_codes_enc;
private static $x10_house_codes_enc;

	function __construct() {
	   //#create a backwards lookup on hexdec code
		self::$plmcmd2string = array_flip(self::$plmcmd);
		self::$x10_unit_codes_enc =	array_flip(self::$x10_unit_codes);
		self::$x10_house_codes_enc = array_flip(self::$x10_house_codes);
   }

   /**
	 * Encode an UTF-8 string into GSM 03.38
	 * Since UTF-8 is largely ASCII compatible, and GSM 03.38 is somewhat compatible, unnecessary conversions are removed.
	 * Specials chars such as € can be encoded by using an escape char \x1B in front of a backwards compatible (similar) char.
	 * UTF-8 chars which doesn't have a GSM 03.38 equivalent is replaced with a question mark. 
	 * UTF-8 continuation bytes (\x08-\xBF) are replaced when encountered in their valid places, but 
	 * any continuation bytes outside of a valid UTF-8 sequence is not processed.
	 *
	 * @param string $string
	 * @return string
	 */
	public static function x10_code_encode($string)
	{
		return self::$x10_house_codes_enc[strtolower($string)];
	}
	
	public static function x10_unit_encode($string)
	{
		return self::$x10_unit_codes_enc[$string];
	}
	
	/**
	 * Count the number of GSM 03.38 chars a conversion would contain.
	 * It's about 3 times faster to count than convert and do strlen() if conversion is not required.
	 * 
	 * @param string $utf8String
	 * @return integer
	 */
	public function plm_decode(&$plm_string) {

	
	/* Return Value:
		$plm_decode_result = Array();
		$plm_decode_result['plm_string'] = $plm_string;
		$plm_decode_result['extdata'] = ERROR_STX_MISSING 		"Missing (02)STX: Invalid message";
		$plm_decode_result['extdata'] = ERROR_MESSAGE_TO_SHORT 	"Message strlen too short for PLM command.  Not parsed";
		$result['extdata'] .= $Data
		$plm_decode_result['plmcmdID'] = $plmcmdID;
		$plm_decode_result['from'] = substr($plm_string,4,6);
		$plm_decode_result['to'] = substr($plm_string,10,6);
		$plm_decode_result['insteon'] = $this->insteon_decode_r(substr($plm_string,16));
		$plm_decode_result['x10'] = $this->plm_x10_decode_r(substr($plm_string,4,4));
		$plm_decode_result['inout'] = self::$inout_a[$plm_decode_result['plmcmdID']];
		return $plm_decode_result;
	*/
	
	
	
	
	$plm_string = strtolower($plm_string);

#0262 1e5d8e 0f 0d00
#0262 1e5d8e 0f 0d00 06

#FSM:0 - Look for PLM STX
#FSM:1 - Parse PLM command category
#FSM:2 - Parse command from PLM (50-58)
#FSM:3 - Parse command to PLM (60-73) and response 

	$plm_message = '';
	$plm_decode_result = Array();
	$plm_decode_result['plm_string'] = $plm_string;
	$plmcmdID;

	$FSM = 0;
	$abort = 0;
	$finished = 0;
	if (strlen($plm_string) < 4) {
		$plm_decode_result['extdata'] = ERROR_MESSAGE_TO_SHORT;
		$abort = 1;
	}
	while(!$abort and !$finished) {
		if($FSM==0) {
			#FSM:0 - Look for PLM STX
			#Must start with STX or it is garbage
			if(substr($plm_string,0,2) != '02') {
				$plm_message .= "Missing (02)STX: Invalid message\n";
				$plm_decode_result['extdata'] = ERROR_STX_MISSING;
				$abort++;
			} else {
				$FSM++;
			}
		} elseif($FSM==1) {
			#FSM:1 - Parse PLM command category
			#Must be at least 2 bytes (4 nibbles) or it is garbage
			if(strlen($plm_string) < 4) {
				$abort++;
			} else {
				#include the STX for historical reasons
				$plmcmdID = substr($plm_string,0,4);
				$plm_message .= sprintf("%20s: (","PLM Command").$plmcmdID.") ".self::$plmcmd2string[$plmcmdID]."\n";
				$plm_decode_result['plmcmdID'] = $plmcmdID;
				if(strlen($plm_string) < self::$plmcmdlen[strtoupper($plmcmdID)][0] * 2) {
					$plm_message .= "        Message strlen too short for PLM command.  Not parsed\n";
					$plm_decode_result['extdata'] = ERROR_MESSAGE_TO_SHORT;
					$abort++;
				} elseif(strlen($plm_string) > self::$plmcmdlen[strtoupper($plmcmdID)][0] * 2 
						and strlen($plm_string) < self::$plmcmdlen[strtoupper($plmcmdID)][1] * 2) {
					$plm_message .= "        Message strlen too short for PLM command.  Not parsed\n";
					$plm_decode_result['extdata'] = ERROR_MESSAGE_TO_SHORT;
					$abort++;
				} elseif(substr($plm_string,2,1) == '5') {
					#commands from PLM are 50-58
					$FSM = 2;
				} else {
					$FSM = 3;
				}
			}
		} elseif($FSM==2) {
			#FSM:2 - Parse command from PLM (50-58)
			if($plmcmdID == '0250') {
				$plm_message .= sprintf("%24s: ",'From Address').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
				$plm_decode_result['from'] = substr($plm_string,4,6);
				$plm_message .= sprintf("%24s: ",'To Address').substr($plm_string,10,2).":".substr($plm_string,12,2).":".substr($plm_string,14,2)."\n";
				$plm_decode_result['to'] = substr($plm_string,10,6);
				$plm_message .= sprintf("%24s: ",'Message Flags').substr($plm_string,16,2)."\n";
                $plm_message .= $this->insteon_message_flags_decode(substr($plm_string,16,2));
				$flag_ext = hexdec(substr($plm_string,16,1))&0x1;
                $plm_message .= sprintf("%24s: ",'Insteon Message').substr($plm_string,18,($flag_ext ? 32 : 4))."\n";
                $plm_message .= $this->insteon_decode(substr($plm_string,16));
				$plm_decode_result['insteon'] = $this->insteon_decode_r(substr($plm_string,16));
			} elseif($plmcmdID == '0251'){
				$plm_message .= sprintf("%24s: ",'From Address').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
				$plm_decode_result['from'] = substr($plm_string,4,6);
				$plm_message .= sprintf("%24s: ",'To Address').substr($plm_string,10,2).":".substr($plm_string,12,2).":".substr($plm_string,14,2)."\n";
				$plm_decode_result['to'] = substr($plm_string,10,6);
				$plm_message .= sprintf("%24s: ",'Message Flags').substr($plm_string,16,2)."\n";
				$plm_message .= $this->insteon_message_flags_decode(substr($plm_string,16,2));
				$flag_ext = hexdec(substr($plm_string,16,1))&0x1;
                $plm_message .= sprintf("%24s: ",'Insteon Message').substr($plm_string,18,($flag_ext ? 32 : 4))."\n";
				$plm_message .= $this->insteon_decode(substr($plm_string,16));
				$plm_decode_result['insteon'] = $this->insteon_decode_r(substr($plm_string,16));
			} elseif($plmcmdID == '0252'){
                $plm_message .= sprintf("%20s: ",'X10 Message').substr($plm_string,4,4)."\n";
                $plm_message .= $this->plm_x10_decode(substr($plm_string,4,4));
                $plm_decode_result['x10'] = $this->plm_x10_decode_r(substr($plm_string,4,4));
			} elseif($plmcmdID == '0253'){
				$link_string = array ('PLM is Responder', 'PLM is Controller', 'All-Link deleted');
				$plm_message .= sprintf("%20s: (",'Link Code').substr($plm_string,4,2).") ".$link_string[substr($plm_string,4,2)]."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'Linked Device').substr($plm_string,8,2).":".substr($plm_string,10,2).":".substr($plm_string,12,2)."\n";
				$plm_message .= sprintf("%20s: ",'Device Category').substr($plm_string,14,2).":".substr($plm_string,16,2)."\n";
				$plm_message .= sprintf("%20s: ",'Firmware').substr($plm_string,18,2)."\n";
			} elseif($plmcmdID == '0254'){
				$buttons = array ('SET Button ','Button 2 ','Button 3 ');
				$button_event = array('','','Tapped','Held 3 seconds','Released');
				$plm_message .= sprintf("%20s: (",'Button Event').substr($plm_string,4,2).") ".$buttons[substr($plm_string,4,1)].$button_event[substr($plm_string,5,1)]."\n";
			} elseif($plmcmdID == '0255'){
				#Nothing else to do
			} elseif($plmcmdID == '0256'){
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,4,2)."\n";
				$plm_message .= sprintf("%20s: ",'Device').substr($plm_string,6,2).":".substr($plm_string,8,2).":".substr($plm_string,10,2)."\n";
			} elseif($plmcmdID == '0257'){
				$plm_message .= sprintf("%20s: ",'All-Link Flags').substr($plm_string,4,2)."\n";
				$flags = hexdec(substr($plm_string,4,2));
				$plm_message .= sprintf("%20s: Record is ",'Bit 7').($flags&0x80?'in use':'available')."\n";
				$plm_message .= sprintf("%20s: PLM is ",'Bit 6').($flags&0x40?'controller':'responder')."\n";
				$plm_message .= sprintf("%20s: ACK is ",'Bit 5').($flags&0x20?'required':'not required')."\n";
				$plm_message .= sprintf("%20s: Record has ",'Bit 1').($flags&0x1?'been used before':'not been used before')."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'Linked Device').substr($plm_string,8,2).":".substr($plm_string,10,2).":".substr($plm_string,12,2)."\n";
#XXXX				$plm_message .= sprintf("%20s: ",'Link Data').substr($plm_string,14,6)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command1').substr($plm_string,14,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command2').substr($plm_string,16,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Data').substr($plm_string,18,2)."\n";
				#TODO:  Find insteon information for link data decode
			} elseif($plmcmdID == '0258'){
				$plm_message .= sprintf("%20s: (",'Status Byte').substr($plm_string,4,2).") ".(substr($plm_string,4,2) == '06' ? "ACK" : "NACK")."\n";
			} else {
				$plm_message .= sprintf("%20s: (",'Undefined Cmd Data').substr($plm_string,4).")\n";
			}
			$finished++;
		} elseif($FSM==3) {
			#FSM:3 - Parse command to PLM (60-73) and response
			$plm_ack_pos;
			if($plmcmdID == '0260') {
				if(strlen($plm_string)>4) {
					$plm_message .= sprintf("%20s: ",'PLM Device ID').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
					$plm_message .= sprintf("%20s: ",'Device Category').substr($plm_string,10,2).":".substr($plm_string,12,2)."\n";
					$plm_message .= sprintf("%20s: ",'Firmware').substr($plm_string,14,2)."\n";
				}
				$plm_ack_pos = 16;
			} elseif($plmcmdID == '0261'){
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,4,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command1').substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command2').substr($plm_string,8,2)."\n";
				$plm_ack_pos = 10;
				#TODO:  look up insteon information for all-link command1 / command2 decode
			} elseif($plmcmdID == '0262'){
				$plm_message .= sprintf("%24s: ",'To Address').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
				$plm_decode_result['to'] = substr($plm_string,4,6);
				$plm_message .= sprintf("%24s: ",'Message Flags').substr($plm_string,10,2)."\n";
				$plm_message .= $this->insteon_message_flags_decode(substr($plm_string,10,2));
				$flag_ext = hexdec(substr($plm_string,10,1))&0x1;
                $plm_message .= sprintf("%24s: ",'Insteon Message').substr($plm_string,12,($flag_ext ? 32 : 4))."\n";
				$plm_message .= $this->insteon_decode(substr($plm_string,10));
				$plm_decode_result['insteon'] = $this->insteon_decode_r(substr($plm_string,10));
				$plm_ack_pos = $flag_ext ? 44 : 16;
			} elseif($plmcmdID == '0263'){
                $plm_message .= sprintf("%20s: ",'X10 Message').substr($plm_string,4,4)."\n";
                $plm_message .= $this->plm_x10_decode(substr($plm_string,4,4));
                $plm_decode_result['x10'] = $this->plm_x10_decode_r(substr($plm_string,4,4));
                $plm_ack_pos = 8;
			} elseif($plmcmdID == '0264'){
				$link_string = array('00'=>'PLM is Responder',
						   '01'=>'PLM is Controller',
						   '03'=>'PLM is either Responder or Controller',
						   'ff'=>'Delete All-Link');
				$plm_message .= sprintf("%20s: (",'Link Code').substr($plm_string,4,2).") ".$link_string[substr($plm_string,4,2)]."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,6,2)."\n";
				$plm_ack_pos = 8;
			} elseif($plmcmdID == '0265'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '0266'){
				$plm_message .= sprintf("%20s: ",'Device Category').substr($plm_string,4,2).":".substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'Firmware').substr($plm_string,8,2)."\n";
				$plm_ack_pos = 10;
			} elseif($plmcmdID == '0267'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '0268'){
				$plm_message .= sprintf("%20s: ",'Command2 Data').substr($plm_string,4,2)."\n";
				$plm_ack_pos = 6;
			} elseif($plmcmdID == '0269'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '026a'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '026b'){
				$plm_message .= sprintf("%20s: (",'PLM Config Flags').substr($plm_string,4,2).")\n";
				$flags = hexdec(substr($plm_string,4,2));
				$plm_message .= sprintf("%20s: Automatic Linking ",'Bit 7').($flags&0x80?'Disabled':'Enabled')."\n";
				$plm_message .= sprintf("%20s: Monitor Mode ",'Bit 6').($flags&0x40?'Enabled':'Disabled')."\n";
				$plm_message .= sprintf("%20s: Automatic LED ",'Bit 5').($flags&0x20?'Disabled':'Enabled')."\n";
				$plm_message .= sprintf("%20s: Deadman Feature ",'Bit 4').($flags&0x10?'Disabled':'Enabled')."\n";
				$plm_ack_pos = 6;
			} elseif($plmcmdID == '026c'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '026d'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '026e'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '026f'){
				$control_string = array ('00'=>'Find All-Link Record',
						      '01'=>'Find Next All-Link Record',
						      '20'=>'Update/Add All-Link Record',
						      '40'=>'Update/Add Controller All-Link Record',
						      '41'=>'Update/Add Responder All-Link Record');
				$plm_message .= sprintf("%20s: (",'Control code').substr($plm_string,4,2).") ".$control_string[substr($plm_string,4,2)]."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Flags').substr($plm_string,6,2)."\n";
				$flags = hexdec(substr($plm_string,6,2));
				$plm_message .= sprintf("%20s: Record is ",'Bit 7').($flags&0x80?'in use':'available')."\n";
				$plm_message .= sprintf("%20s: PLM is ",'Bit 6').($flags&0x40?'controller':'responder')."\n";
				$plm_message .= sprintf("%20s: ACK is ",'Bit 5').($flags&0x20?'required':'not required')."\n";
				$plm_message .= sprintf("%20s: Record has ",'Bit 1').($flags&0x1?'been used before':'not been used before')."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,8,2)."\n";
				$plm_message .= sprintf("%20s: ",'Linked Device').substr($plm_string,10,2).":".substr($plm_string,12,2).":".substr($plm_string,14,2)."\n";
#				$plm_message .= sprintf("%20s: ",'Link Data').substr($plm_string,16,6)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command1').substr($plm_string,16,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command2').substr($plm_string,18,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Data').substr($plm_string,20,2)."\n";
				$plm_ack_pos = 22;
				#TODO:  Find insteon information for link data decode
			} elseif($plmcmdID == '0270'){
				$plm_message .= sprintf("%20s: ",'Command2 Data').substr($plm_string,4,2)."\n";
				$plm_ack_pos = 6;
			} elseif($plmcmdID == '0271'){
				$plm_message .= sprintf("%20s: ",'Command1 Data').substr($plm_string,4,2)."\n";
				$plm_message .= sprintf("%20s: ",'Command2 Data').substr($plm_string,6,2)."\n";
				$plm_ack_pos = 8;
			} elseif($plmcmdID == '0272'){
				$plm_ack_pos = 4;
			} elseif($plmcmdID == '0273'){
				if(strlen($plm_string)>4) {
					$plm_message .= sprintf("%20s: (",'PLM Config Flags').substr($plm_string,4,2).")\n";
					$flags = hexdec(substr($plm_string,4,2));
					$plm_message .= sprintf("%20s: Automatic Linking ",'Bit 7').($flags&0x80?'Disabled':'Enabled')."\n";
					$plm_message .= sprintf("%20s: Monitor Mode ",'Bit 6').($flags&0x40?'Enabled':'Disabled')."\n";
					$plm_message .= sprintf("%20s: Automatic LED ",'Bit 5').($flags&0x20?'Disabled':'Enabled')."\n";
					$plm_message .= sprintf("%20s: Deadman Feature ",'Bit 4').($flags&0x10?'Disabled':'Enabled')."\n";
					$plm_message .= sprintf("%20s: ",'Spare 1').substr($plm_string,6,2)."\n";
					$plm_message .= sprintf("%20s: ",'Spare 2').substr($plm_string,8,2)."\n";
				}
				$plm_ack_pos = 10;
			} else {
				$plm_message .= sprintf("%20s: (",'Undefined Cmd Data').substr($plm_string,4).")\n";
				$plm_ack_pos = 255;
			}

			if(strlen($plm_string)>$plm_ack_pos) {
				$plm_message .= sprintf("%20s: (",'PLM Response').substr($plm_string,$plm_ack_pos,2).") ".(substr($plm_string,$plm_ack_pos,2) == '06' ? "ACK" : "NACK")."\n";
				$plm_string = substr($plm_string, $plm_ack_pos + 2, strlen($plm_string));
			}
			$finished++;
		} #if($FSM==)
	} #while(!$abort)
	$plm_decode_result['plm_message'] = preg_replace('/(?!\n)\s+/', ' ', $plm_message);
	$decode_result= $plm_decode_result['plmcmdID'];
	if (array_key_exists($decode_result, self::$inout_a)) {
		$plm_decode_result['inout'] = self::$inout_a[$decode_result];
		$plm_decode_result['length'] = self::$plmcmdlen[strtoupper($plmcmdID)][0] * 2;
	} else {
		$plm_decode_result['inout'] = COMMAND_IO_NOT;
		$plm_decode_result['length'] = 0;
	}
//	return $plm_message;
	return $plm_decode_result;
}

private function plm_x10_decode($x10_string) {

	$x10_string = strtolower($x10_string);

	$x10_message = '';
	$x10_message .= sprintf("%24s: (",'X10 House Code').substr($x10_string,0,1).") ".strtoupper(self::$x10_house_codes[substr($x10_string,0,1)])."\n";
	if(substr($x10_string,2,1) == '8') {
		$x10_message .= sprintf("%24s: (",'X10 Command').substr($x10_string,1,1).") ".self::$x10_commands[substr($x10_string,1,1)][0]."\n";
	} else {
		$x10_message .= sprintf("%24s: (",'X10 Unit Code').substr($x10_string,1,1).") ".strtoupper(self::$x10_unit_codes[substr($x10_string,1,1)])."\n";
	}
	return($x10_message);
}
private function plm_x10_decode_r($x10_string) {

	$x10_string = strtolower($x10_string);

	$result = Array();
	$result['code']= strtoupper(self::$x10_house_codes[substr($x10_string,0,1)]);
	if(substr($x10_string,2,1) == '8') {
		$result['command'] = self::$x10_commands[substr($x10_string,1,1)][0];
		$result['commandID'] = self::$x10_commands[substr($x10_string,1,1)][1];
	} else {
		$result['unit'] = strtoupper(self::$x10_unit_codes[substr($x10_string,1,1)]);
	}
	return($result);
}

/*=item insteon_message_flags_decode(flags_string)

Returns a string containing decoded Insteon message flags

=cut*/

private function insteon_message_flags_decode($flags_string) {
	
    $flags_string = strtolower($flags_string);

    $flags_message = '';
     
	$message_string =  array ( '4'=>'Broadcast Message',
				'0'=>'Direct Message',
				'1'=>'ACK of Direct Message',
				'5'=>'NAK of Direct Message',
				'6'=>'All-Link Broadcast Message',
				'2'=>'All-Link Cleanup Direct Message',
				'3'=>'ACK of All-Link Cleanup Direct Message',
				'7'=>'NAK of All-Link Cleanup Direct Message');

	$flag_msg = hexdec(substr($flags_string,0,1))>>1;
	$flag_ext = hexdec(substr($flags_string,0,1))&0x01;
	$flags_message .= sprintf("%28s: (%03b) ",'Message Type',$flag_msg).$message_string[$flag_msg]."\n";
	$flags_message .= sprintf("%28s: (%01b) ",'Message strlen',$flag_ext).($flag_ext?'Extended strlen':'Standard strlen')."\n";
	$flags_message .= sprintf("%28s: %d\n",'Hops Left',hexdec(substr($flags_string,1,1))>>2);
	$flags_message .= sprintf("%28s: %d\n",'Max Hops',hexdec(substr($flags_string,1,1))&0x3);
        return($flags_message);
}

/*=item insteon_decode(command_string)

Returns a string containing a decoded Insteon message. Input
string should be the Insteon message starting with the 
message flag byte.

=cut*/

private function insteon_decode($command_string) {
	 
/*#Mapping from message type bit field to acronyms used in
#  the INSTEON Command Tables documentation
#100 4 - SB: Standard Broadcast

#000 0 - SD or ED: Standard/Extended Direct
#001 1 - SDA or EDA: Standard/Extended Direct ACK
#101 5 - SDN or EDN: Standard/Extended Direct NACK

#110 6 - SA: Standard All-Link Broadcast
#010 2 - SC: Standard Cleanup Direct
#011 3 - SCA: Standard Cleanup Direct ACK
#111 7 - SCN: Standard Cleanup Direct NACK

#For SDA parsing 1st look for SDA command entry, if not found
#then lookup SD command entry for parsing information.

#For SDN, EDN, SCN NACK responses, lookup coorespnding
#SD, ED, or SC entry for parsing, but always use the 
#common NACK decoding for Cmd2

#Lookup SB, SD, ED, SA, and SC messages with just the 
#Cmd1 entry appended at the key.  If Cmd2 Flag == "Command" 
#then repeat lookup appending both Cmd1 and Cmd2 for 
#the key.  If Cmd2 Flag != "Command" then use flag value
#to control how Cmd2 is displayed.  If second lookup fails,
#simply print Cmd2 and indicate "not decoded".
*/

	$extended = hexdec(substr($command_string,0,1))&0x1;
	$msg_type = (hexdec(substr($command_string,0,1))&0xE)>>1;
	$cmd1 = substr($command_string,2,2);
	$cmd2 = substr($command_string,4,2);
	$data = '';
	if ($extended) $data = substr($command_string,6) ;

	#Truncate $command_string to remove PLM ACK byte
	$command_string = substr($command_string,0, ($extended ? 34 : 8));
	$insteon_message='';
	if( $msg_type == 0) {
		#SD/ED: Standard/Extended Direct
		$insteon_message .= $this->insteon_decode_cmd(($extended ? 'ED' : 'SD'), $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 1 or $msg_type == 5) {
		#SDA/EDA: Standard/Extended Direct ACK/NACK
		$insteon_message .= $this->insteon_decode_cmd(($extended ? 'EDA' : 'SDA'), $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 6) {
		#SA: Standard All-Link Broadcast
		$insteon_message .= $this->insteon_decode_cmd('SA', $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 2) {
		#SC: Standard Direct Cleanup
		$insteon_message .= $this->insteon_decode_cmd('SC', $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 3 or $msg_type == 7) {
		#SCA: Standard Direct Cleanup ACK/NACK
		$insteon_message .= $this->insteon_decode_cmd('SCA', $cmd1, $cmd2, $extended, $data);
	} else {
		$insteon_message .= sprintf("%28s: ",'')."Insteon message type not decoded\n";
	}

	return $insteon_message;
}
private function insteon_decode_r($command_string) {

	/*#Mapping from message type bit field to acronyms used in
	 #  the INSTEON Command Tables documentation
	#100 4 - SB: Standard Broadcast

	#000 0 - SD or ED: Standard/Extended Direct
	#001 1 - SDA or EDA: Standard/Extended Direct ACK
	#101 5 - SDN or EDN: Standard/Extended Direct NACK

	#110 6 - SA: Standard All-Link Broadcast
	#010 2 - SC: Standard Cleanup Direct
	#011 3 - SCA: Standard Cleanup Direct ACK
	#111 7 - SCN: Standard Cleanup Direct NACK

	#For SDA parsing 1st look for SDA command entry, if not found
	#then lookup SD command entry for parsing information.

	#For SDN, EDN, SCN NACK responses, lookup coorespnding
	#SD, ED, or SC entry for parsing, but always use the
	#common NACK decoding for Cmd2

	#Lookup SB, SD, ED, SA, and SC messages with just the
	#Cmd1 entry appended at the key.  If Cmd2 Flag == "Command"
	#then repeat lookup appending both Cmd1 and Cmd2 for
	#the key.  If Cmd2 Flag != "Command" then use flag value
	#to control how Cmd2 is displayed.  If second lookup fails,
	#simply print Cmd2 and indicate "not decoded".
	*/

	$result = Array();
	$extended = hexdec(substr($command_string,0,1))&0x1;
	$msg_type = (hexdec(substr($command_string,0,1))&0xE)>>1;
	$cmd1 = substr($command_string,2,2);
	$cmd2 = substr($command_string,4,2);
	$data = '';
	if ($extended) $data = substr($command_string,6) ;

	#Truncate $command_string to remove PLM ACK byte
	$command_string = substr($command_string,0, ($extended ? 34 : 8));
	$insteon_message='';
	if( $msg_type == 0) {
		#SD/ED: Standard/Extended Direct
		$result = $this->insteon_decode_cmd_r(($extended ? 'ED' : 'SD'), $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 1 or $msg_type == 5) {
		#SDA/EDA: Standard/Extended Direct ACK/NACK
		$result = $this->insteon_decode_cmd_r(($extended ? 'EDA' : 'SDA'), $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 6) {
		#SA: Standard All-Link Broadcast
		$result = $this->insteon_decode_cmd_r('SA', $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 2) {
		#SC: Standard Direct Cleanup
		$result = $this->insteon_decode_cmd_r('SC', $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 3 or $msg_type == 7) {
		#SCA: Standard Direct Cleanup ACK/NACK
		$result = $this->insteon_decode_cmd_r('SCA', $cmd1, $cmd2, $extended, $data);
	} else {
		$result['extdata'] = "Insteon Message Undefined\n";
	}

	return $result;
}

private function insteon_decode_cmd($cmdLookup, $cmd1, $cmd2, $extended, $Data) {

	$insteon_message='';
	//$cmdDecoder1='';
	//$cmdDecoder2='';

	#lookup 1st without using Cmd2
	if (array_key_exists($cmdLookup.$cmd1,self::$insteonCmd)) $cmdDecoder1 = self::$insteonCmd[$cmdLookup.$cmd1];

	if(!isset($cmdDecoder1)) {
		#lookup failed, if this is an ACK/NACK retry w/ direct version
		if( $cmdLookup == 'SDA') {
			$cmdDecoder1 = self::$insteonCmd['SD'.$cmd1];
		} elseif( $cmdLookup == 'EDA')  {
			$cmdDecoder1 = self::$insteonCmd['ED'.$cmd1];
		} elseif( $cmdLookup == 'SCA') {
			$cmdDecoder1 = self::$insteonCmd['SC'.$cmd1];
		}
		if(!isset($cmdDecoder1)) {
			#still not found so quit trying to decode
			$insteon_message .= sprintf("%28s: ",'Cmd 1').$cmd1." Insteon command not decoded\n";
			$insteon_message .= sprintf("%28s: ",'Cmd 2').$cmd2."\n";
			if( $extended) $insteon_message .= sprintf("%28s: ",'D1-D14').$Data."\n";
			return $insteon_message;
		}
	}

	if($cmdDecoder1['Cmd2Flag'] == 'Command') {
		$insteon_message='';
		#2nd lookup with Cmd2
		if (array_key_exists($cmdLookup.$cmd1.$cmd2,self::$insteonCmd)) $cmdDecoder2 = self::$insteonCmd[$cmdLookup.$cmd1.$cmd2];
		if(!isset($cmdDecoder2)) {
			#lookup failed, if this is an ACK/NACK retry w/ direct version
			if( $cmdLookup == 'SDA') {
				$cmdDecoder2 = self::$insteonCmd['SD'.$cmd1];
			} elseif( $cmdLookup == 'EDA') {
				$cmdDecoder2 = self::$insteonCmd['ED'.$cmd1];
			} elseif( $cmdLookup == 'SCA') {
				$cmdDecoder2 = self::$insteonCmd['SC'.$cmd1];
			}
		}
		if(!isset($cmdDecoder2)) {
			#still not found so don't decode
			$insteon_message .= sprintf("%28s: ",'Cmd 1').$cmd1." Insteon command not decoded\n";
			$insteon_message .= sprintf("%28s: ",'Cmd 2').$cmd2."\n";
			if( $extended) $insteon_message .= sprintf("%28s: ",'D1-D14').$Data."\n" ;
		} else {
			$insteon_message .= sprintf("%28s: (",'Cmd 1').$cmd1.") ".$cmdDecoder2['Cmd1Name']."\n";
			$insteon_message .= sprintf("%28s: (",'Cmd 2').$cmd2.") ".$cmdDecoder2['Cmd2Name']."\n";
			if( $extended) $insteon_message .= sprintf("%28s: ",'D1-D14').$Data."\n" ;
		}
	} elseif( $cmdDecoder1['Cmd2Flag'] == 'Value') {
		$insteon_message .= sprintf("%28s: (",'Cmd 1').$cmd1.") ".$cmdDecoder1['Cmd1Name']."\n";
		$insteon_message .= sprintf("%28s: (",'Cmd 2').$cmd2.") ".$cmdDecoder1['Cmd2Name']."\n";
		 if( $extended) $insteon_message .= sprintf("%28s: ",'D1-D14').$Data."\n";
	} elseif( $cmdDecoder1['Cmd2Flag'] == 'NA') {
		$insteon_message .= sprintf("%28s: (",'Cmd 1').$cmd1.") ".$cmdDecoder1['Cmd1Name']."\n";
		$insteon_message .= sprintf("%28s: ",'Cmd 2').$cmd2."\n";
		 if( $extended) $insteon_message .= sprintf("%28s: ",'D1-D14').$Data."\n";
	} else {
		$insteon_message .= "Parse database has Undefined Cmd2Flag: ".$cmdDecoder1['Cmd2Flag'];
	}

	return $insteon_message;
}
private function insteon_decode_cmd_r($cmdLookup, $cmd1, $cmd2, $extended, $Data) {

	$result = Array();
	//$cmdDecoder1='';
	//$cmdDecoder2='';

	#lookup 1st without using Cmd2
	if (array_key_exists($cmdLookup.$cmd1,self::$insteonCmd)) $cmdDecoder1 = self::$insteonCmd[$cmdLookup.$cmd1];

	if(!isset($cmdDecoder1)) {
		#lookup failed, if this is an ACK/NACK retry w/ direct version
		if( $cmdLookup == 'SDA') {
			$cmdDecoder1 = self::$insteonCmd['SD'.$cmd1];
		} elseif( $cmdLookup == 'EDA')  {
			$cmdDecoder1 = self::$insteonCmd['ED'.$cmd1];
		} elseif( $cmdLookup == 'SCA') {
			$cmdDecoder1 = self::$insteonCmd['SC'.$cmd1];
		}
		if(!isset($cmdDecoder1)) {
			#still not found so quit trying to decode
			$result['extdata'] = sprintf("%s: ",'Cmd 1').$cmd1." Insteon command not decoded";
			$result['commandID'] = COMMAND_UNKNOWN;
			$result['data'] = $cmd2;
			if( $extended) 	$result['extdata']  .= $Data;
			return $result;
		}
	}

	if($cmdDecoder1['Cmd2Flag'] == 'Command') {
		$insteon_message='';
		#2nd lookup with Cmd2
		if (array_key_exists($cmdLookup.$cmd1.$cmd2,self::$insteonCmd)) $cmdDecoder2 = self::$insteonCmd[$cmdLookup.$cmd1.$cmd2];
		if(!isset($cmdDecoder2)) {
			#lookup failed, if this is an ACK/NACK retry w/ direct version
			if( $cmdLookup == 'SDA') {
				$cmdDecoder2 = self::$insteonCmd['SD'.$cmd1];
			} elseif( $cmdLookup == 'EDA') {
				$cmdDecoder2 = self::$insteonCmd['ED'.$cmd1];
			} elseif( $cmdLookup == 'SCA') {
				$cmdDecoder2 = self::$insteonCmd['SC'.$cmd1];
			}
		}
		if(!isset($cmdDecoder2)) {
			#still not found so don't decode
			$result['extdata'] = sprintf("%s: ",'Cmd 1').$cmd1." Insteon command not decoded";
			$result['commandID'] = COMMAND_UNKNOWN;
			$result['data'] = $cmd2;
			if( $extended) 	$result['extdata'] .= $Data ;
		} else {
			$result['command'] = sprintf("%28s: (",'Cmd 1').$cmd1.") ".$cmdDecoder2['Cmd1Name']."";
			$result['commandID'] = $cmdDecoder2['commandID'];
			$result['data'] = $cmdDecoder2['Cmd2Name'];
			if( $extended) $result['extdata'] = $Data;
		}
	} elseif( $cmdDecoder1['Cmd2Flag'] == 'Value') {
		$result['command'] = sprintf("%28s: (",'Cmd 1').$cmd1.") ".$cmdDecoder1['Cmd1Name']."";
		$result['commandID'] = $cmdDecoder1['commandID'];
		$result['commandvalue'] = hexdec($cmd2);
		echo "**************".$cmd2.CRLF;
		if( $extended) $result['extdata'] = $Data;
	} elseif( $cmdDecoder1['Cmd2Flag'] == 'NA') {
		$result['command'] = sprintf("%28s: (",'Cmd 1').$cmd1.") ".$cmdDecoder1['Cmd1Name']."";
		$result['commandID'] = $cmdDecoder1['commandID'];
		$result['data'] = $cmd2;
		if( $extended) $result['extdata'] = $Data;
	} else {
		$result['extdata'] = "Parse database has Undefined Cmd2Flag: ".$cmdDecoder1['Cmd2Flag'];
	}

	return $result;
}

#$plm_cmd is 2 byte hexdec cmd; $send_rec is 0 for send, 1, for rec; $is_extended is 1 if extended send
#returns expected byte strlen
private function insteon_cmd_len($plm_cmd, $send_rec, $is_extended = false) {
	 
	if ($is_extended && self::$plmcmdlen[strtoupper($plm_cmd)] > 2) {
		return self::$plmcmdlen[strtoupper($plm_cmd)][($send_rec+2)];
	} else {
		return self::$plmcmdlen[strtoupper($plm_cmd)][$send_rec];
	}
}


/*=back

=head1 SUPPORT

You can find documentation for this module with the perldoc command.

    perldoc Insteon::MessageDecoder

=head1 SEE ALSO

L<Insteon Command Tables 20070925a|www.insteon.net/pdf/INSTEON_Command_Tables_20070925a.pdf>

PLM command details can be found in the 2412S Developers Guide.  This 
document is not supplied by SmartHome but may be available through an 
internet search.

=head1 AUTHOR

Michael Stovenour

=head1 LICENSE AND COPYRIGHT

Copyright 2012 Michael Stovenour

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, 
MA  02110-1301, USA.

=cut

1;*/
	
}
?>
