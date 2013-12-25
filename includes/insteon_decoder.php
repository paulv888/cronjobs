<?php

/*B<Insteon::MessageDecoder> - Static class for decoding Insteon PLM messages

=head1 SYNOPSIS

    use Insteon::MessageDecoder;
    $decodedMessage = Insteon::MessageDecoder::plm_decode($plm_string);

=head1 DESCRIPTION

Insteon::MessageDecoder will decode Insteon PLM messages. Functions are 
provided to decode the PLM envelope, X10 commands, Insteon flags, and 
Insteon Cmd1/Cmd2 bytes.  User data (D1-D14) of extended messages is not 
decoded but will be displayed.

=head1 EXAMPLE

    use Insteon::MessageDecoder;
    $plm_string;
    
    $plm_string = '02621f058c1f2e000100000000000000000000000000';
    print( "PLM Message: $plm_string\n");
    print( Insteon::MessageDecoder::plm_decode($plm_string)."\n");
    
    $plm_string = '02511f058c1edc30112e000101000020201cfe3f0001000000';
    print( "PLM Message: $plm_string\n");
    print( Insteon::MessageDecoder::plm_decode($plm_string)."\n");

=head1 LIMITATIONS

The message decoder is not perfect.  It does not keep message state and 
several of the Insteon ACK message formats can "only" be decoded relative
to the most recent command sent to the ACKing device.  Some ACK messages 
will be incorrectly decoded as part of another Insteon message. For 
example in the ACK for a "Light Status Request", cmd1 is the ALDB serial 
number.  This serial number will be interpreted as another Insteon message
where the serial number matches the cmd1 value.  Be aware of this and you 
should still be able to intrepret the decided messages.

Extended messages are not decoded and only display the D1-D14 hexdec data.  
You will need to manually decode the extended data.  Patches for decoding 
one or more extended messages are welcome.

=head1 BUGS

There are probably many bugs.  The decoder was designed by transcribing 
Insteon documentation, Jonathan Dale's excellent command reference, and 
reviewing many message board discussions.  The decoder has not been 
tested with all devices (of all firmware revs) or all PLM combinations 
(of all firmware revs).  There are bugs; please report them in the 
misterhouse GitHub issues list.

=head1 METHODS

=over

=cut

#These constants are intentionally copied here from other Insteon modules
#This is so any changes to those structures do not introduce errors
#in the decoders.  These constants should only be modified to extend 
#the decoders or correct defects in the decoders.

#PLM Serial Commands
*/
$plmcmd = array (
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

//#create a backwards lookup on hexdec code
$plmcmd2string = array_flip($plmcmd);

$plmcmdlen = array (
	'0250' => array (11, 11),
	'0251' => array (25, 25),
	'0252' => array (4, 4),
	'0253' => array (10, 10),
	'0254' => array (3, 3),
	'0255' => array (2, 2),
	'0256' => array (6, 6),
	'0257' => array (10, 10),
	'0258' => array (3, 3),
	'0260' => array (2, 9),
	'0261' => array (5, 6),
	'0262' => array (8, 9, 22, 23), # could get 9 or 23 array (Standard or Extended Message received)
	'0263' => array (4, 5),
	'0264' => array (4, 5),
	'0265' => array (2, 3),
	'0266' => array (5, 6),
	'0267' => array (2, 3),
	'0268' => array (3, 4),
	'0269' => array (2, 3),
	'026A' => array (2, 3),
	'026B' => array (3, 4),
	'026C' => array (2, 3),
	'026D' => array (2, 3),
	'026E' => array (2, 3),
	'026F' => array (11, 12),
	'0270' => array (3, 4),
	'0271' => array (4, 5),
	'0272' => array (2, 3),
	'0273' => array (5, 6),
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
$insteonCmd = array (
'SD01' => array ('Cmd1Name'=>'Assign to All-Link Group','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SB01' => array ('Cmd1Name'=>'SET Button Pressed Respond','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD02' => array ('Cmd1Name'=>'Delete from All-Link Group','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SB02' => array ('Cmd1Name'=>'SET Button Pressed Controller','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD03' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD0300' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Product Data Request'),
'SD0301' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'FxName Request'),
'SD0302' => array ('Cmd1Name'=>'Device Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Device Text String Request'),
'ED03' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED0300' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Product Data Response'),
'ED0301' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'FX Username Response'),
'ED0302' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Device Text String Response'),
'ED0303' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Set Device Text String'),
'ED0304' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Set ALL-Link Command Alias'),
'ED0305' => array ('Cmd1Name'=>'Device Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Set ALL-Link Command Alias ED'),
'SB03' => array ('Cmd1Name'=>'Test Powerline Phase','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SB0300' => array ('Cmd1Name'=>'Test Powerline Phase','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Phase A'),
'SB0301' => array ('Cmd1Name'=>'Test Powerline Phase','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Phase B'),
'SB04' => array ('Cmd1Name'=>'Heartbeat','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Battery Level'),
'SA06' => array ('Cmd1Name'=>'All-Link Cleanup Report','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Fail Count'),
'SD09' => array ('Cmd1Name'=>'Enter Linking Mode','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD0a' => array ('Cmd1Name'=>'Enter Unlinking Mode','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD0d' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SDA0d' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SDA0d00' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'i1'),
'SDA0d01' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'i2'),
'SDA0d02' => array ('Cmd1Name'=>'Get INSTEON Engine Version','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'i2CS'),
'SD0f' => array ('Cmd1Name'=>'Ping','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD10' => array ('Cmd1Name'=>'ID Request','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD11' => array ('Cmd1Name'=>'Light ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Level'),
'SA11' => array ('Cmd1Name'=>'ALL-Link Recall','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC11' => array ('Cmd1Name'=>'ALL-Link Recall','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD12' => array ('Cmd1Name'=>'Light ON Fast','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Level'),
'SA12' => array ('Cmd1Name'=>'ALL-Link Alias 2 High','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC12' => array ('Cmd1Name'=>'ALL-Link Alias 2 High','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD13' => array ('Cmd1Name'=>'Light OFF','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SA13' => array ('Cmd1Name'=>'ALL-Link Alias 1 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC13' => array ('Cmd1Name'=>'ALL-Link Alias 1 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD14' => array ('Cmd1Name'=>'Light OFF Fast','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SA14' => array ('Cmd1Name'=>'ALL-Link Alias 2 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC14' => array ('Cmd1Name'=>'ALL-Link Alias 2 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD15' => array ('Cmd1Name'=>'Light Brighten One Step','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SA15' => array ('Cmd1Name'=>'ALL-Link Alias 3 High','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC15' => array ('Cmd1Name'=>'ALL-Link Alias 3 High','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD16' => array ('Cmd1Name'=>'Light Dim One Step','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SA16' => array ('Cmd1Name'=>'ALL-Link Alias 3 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC16' => array ('Cmd1Name'=>'ALL-Link Alias 3 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD17' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD1700' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Down'),
'SD1701' => array ('Cmd1Name'=>'Light Start Manual Change','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Up'),
'SA17' => array ('Cmd1Name'=>'ALL-Link Alias 4 High','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC17' => array ('Cmd1Name'=>'ALL-Link Alias 4 High','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD18' => array ('Cmd1Name'=>'Light Stop Manual Change','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SA18' => array ('Cmd1Name'=>'ALL-Link Alias 4 Low','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC18' => array ('Cmd1Name'=>'ALL-Link Alias 4 Low','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD19' => array ('Cmd1Name'=>'Light Status Request','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD1900' => array ('Cmd1Name'=>'Light Status Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'On Level'),
'SD1901' => array ('Cmd1Name'=>'Light Status Request','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'LED Bit Flags'),
'SD1f' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD1f00' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Request Flags'),
'SD1f01' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'All-Link Database Delta Number'),
'SD1f02' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Signal-to-Noise'),
'SDA1f' => array ('Cmd1Name'=>'Get Operating Flags','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Config Flags'),
'SD20' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD2000' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Program Lock On'),
'SD2001' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Program Lock Off'),
'SD2002' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Deveice Dependent'),
'SD2003' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Deveice Dependent'),
'SD2004' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Deveice Dependent'),
'SD2005' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Deveice Dependent'),
'SD2006' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Deveice Dependent'),
'SD2007' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'Deveice Dependent'),
'SD2008' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'Deveice Dependent'),
'SD2009' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Deveice Dependent'),
'SD200a' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Deveice Dependent'),
'SD200b' => array ('Cmd1Name'=>'Set Operating Flags','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Deveice Dependent'),
'SD21' => array ('Cmd1Name'=>'Light Instant Change','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'On Level'),
'SA21' => array ('Cmd1Name'=>'ALL-Link Alias 5','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SC21' => array ('Cmd1Name'=>'ALL-Link Alias 5','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Group'),
'SD22' => array ('Cmd1Name'=>'Light Manually Turned Off','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD23' => array ('Cmd1Name'=>'Light Manually Turned On','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD24' => array ('Cmd1Name'=>'Reread Init Values(Deprecated)','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD25' => array ('Cmd1Name'=>'Remote SET Button Tap','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD2501' => array ('Cmd1Name'=>'Remote SET Button Tap','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'1 Tap'),
'SD2502' => array ('Cmd1Name'=>'Remote SET Button Tap','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'2 Taps'),
'SD27' => array ('Cmd1Name'=>'Light Set Status','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'On Level'),
'SB27' => array ('Cmd1Name'=>'Status Change','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data'),
'SD28' => array ('Cmd1Name'=>'Set Address MSB(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'MSB'),
'SD29' => array ('Cmd1Name'=>'Poke One Byte(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data'),
'ED2a' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED2a00' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Transfer Failure'),
'ED2a01' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Complete (1 byte)'),
'ED2a02' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Complete (2 bytes)'),
'ED2a03' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Complete (3 bytes)'),
'ED2a04' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Complete (4 bytes)'),
'ED2a05' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Complete (5 bytes)'),
'ED2a06' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Complete (6 bytes)'),
'ED2a07' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'Complete (7 bytes)'),
'ED2a08' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'Complete (8 bytes)'),
'ED2a09' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Complete (9 bytes)'),
'ED2a0a' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Complete (10 bytes)'),
'ED2a0b' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Complete (11 bytes)'),
'ED2a0c' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Complete (12 bytes)'),
'ED2a0d' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Complete (13 bytes)'),
'ED2aff' => array ('Cmd1Name'=>'Block Data Transfer','Cmd2Flag'=>'Command','Cmd2Value'=>'0xff','Cmd2Name'=>'Request Block Data Transfer'),
'SD2b' => array ('Cmd1Name'=>'Peek One Byte(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'LSB of Address'),
'SDA2b' => array ('Cmd1Name'=>'Peek One Byte(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Peeked Byte'),
'SD2c' => array ('Cmd1Name'=>'Peek One Byte Internal(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'LSB of Address'),
'SDA2c' => array ('Cmd1Name'=>'Peek One Byte Internal(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Peeked Byte'),
'SD2d' => array ('Cmd1Name'=>'Poke One Byte Internal(Deprecated)','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data'),
'SD2e' => array ('Cmd1Name'=>'Light ON at Ramp Rate','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Level and Rate'),
'ED2e' => array ('Cmd1Name'=>'Extended Set/Get','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED2e00' => array ('Cmd1Name'=>'Extended Set/Get','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Command in D2'),
'SD2f' => array ('Cmd1Name'=>'Light OFF at Ramp Rate','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Ramp Rate'),
'ED2f' => array ('Cmd1Name'=>'Read/Write ALL-Link Database','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED2f00' => array ('Cmd1Name'=>'Read/Write ALL-Link Database','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Command in D2'),
'SD30' => array ('Cmd1Name'=>'Beep','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Duration'),
'ED30' => array ('Cmd1Name'=>'Trigger ALL-Link Command','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED3000' => array ('Cmd1Name'=>'Trigger ALL-Link Command','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Trigger Command'),
'SD40' => array ('Cmd1Name'=>'Sprinkler Valve On','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Valve Number'),
'ED40' => array ('Cmd1Name'=>'Set Sprinkler Program','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number'),
'SD41' => array ('Cmd1Name'=>'Sprinkler Valve Off','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Valve Number'),
'ED41' => array ('Cmd1Name'=>'Sprinkler Get Program Response','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number'),
'SD42' => array ('Cmd1Name'=>'Sprinkler Program ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number'),
'SD43' => array ('Cmd1Name'=>'Sprinkler Program OFF','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Program Number'),
'SD44' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD4400' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values'),
'SD4401' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM From RAM'),
'SD4402' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Get Valve Status'),
'SD4403' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Inhibit Command Acceptance'),
'SD4404' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Resume Command Acceptance'),
'SD4405' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Skip Forward'),
'SD4406' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Skip Backwards'),
'SD4407' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'Enable Pump on V8'),
'SD4408' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'Disable Pump on V8'),
'SD4409' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Broadcast ON'),
'SD440a' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Broadcast OFF'),
'SD440b' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Load RAM from EEPROM'),
'SD440c' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Sensor ON'),
'SD440d' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Sensor OFF'),
'SD440e' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0e','Cmd2Name'=>'Diagnostics ON'),
'SD440f' => array ('Cmd1Name'=>'Sprinkler Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0f','Cmd2Name'=>'Diagnostics OFF'),
'SD45' => array ('Cmd1Name'=>'I/O Output ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Output Number'),
'SD46' => array ('Cmd1Name'=>'I/O Output OFF','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Output Number'),
'SD47' => array ('Cmd1Name'=>'I/O Alarm Data Request','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD48' => array ('Cmd1Name'=>'I/O Write Output Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data'),
'SDA48' => array ('Cmd1Name'=>'I/O Write Output Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data Written'),
'SD49' => array ('Cmd1Name'=>'I/O Read Input Port','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SDA49' => array ('Cmd1Name'=>'I/O Read Input Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Data Read'),
'SD4a' => array ('Cmd1Name'=>'Get Sensor Value','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Sensor Number'),
'SDA4a' => array ('Cmd1Name'=>'Get Sensor Value','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Sensor Value'),
'SD4b' => array ('Cmd1Name'=>'Set Sensor 1 Alarm Trigger OFF->ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Nominal Value'),
'ED4b' => array ('Cmd1Name'=>'I/O Set Sensor Nominal','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Sensor Number'),
'SD4c' => array ('Cmd1Name'=>'I/O Get Sensor Alarm Delta','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field'),
'ED4c' => array ('Cmd1Name'=>'I/O Alarm Data Response','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED4c00' => array ('Cmd1Name'=>'I/O Alarm Data Response','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Response'),
'SD4d' => array ('Cmd1Name'=>'I/O Write Configuration Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field'),
'SD4e' => array ('Cmd1Name'=>'I/O Read Configuration Port','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'SD4e' => array ('Cmd1Name'=>'I/O Read Configuration Port','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'I/O Port Config'),
'SD4f' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD4f00' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values'),
'SD4f01' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM from RAM'),
'SD4f02' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Status Request'),
'SD4f03' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Read Analog once'),
'SD4f04' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Read Analog Always'),
'SD4f09' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'Enable status change message'),
'SD4f0a' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Disable status change message'),
'SD4f0b' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Load RAM from EEPROM'),
'SD4f0c' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Sensor On'),
'SD4f0d' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Sensor Off'),
'SD4f0e' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0e','Cmd2Name'=>'Diagnostics On'),
'SD4f0f' => array ('Cmd1Name'=>'I/O Module Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0f','Cmd2Name'=>'Diagnostics Off'),
'SD50' => array ('Cmd1Name'=>'Pool Device ON','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Device Number'),
'ED50' => array ('Cmd1Name'=>'Pool Set Device Temperature','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'ED5000' => array ('Cmd1Name'=>'Pool Set Device Temperature','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Set Temperature'),
'ED5001' => array ('Cmd1Name'=>'Pool Set Device Temperature','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Set Hysteresis'),
'SD51' => array ('Cmd1Name'=>'Pool Device OFF','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Device Number'),
'SD52' => array ('Cmd1Name'=>'Pool Temperature Up','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Increment Count'),
'SD53' => array ('Cmd1Name'=>'Pool Temperature Down','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Decrement Count'),
'SD54' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD5400' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values'),
'SD5401' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM From RAM'),
'SD5402' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Get Pool Mode'),
'SD5403' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Get Ambient Temp'),
'SD5404' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Get Water Temp'),
'SD5405' => array ('Cmd1Name'=>'Pool Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Get pH'),
'SD58' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD5800' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Raise Door'),
'SD5801' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Lower Door'),
'SD5802' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Open Door'),
'SD5803' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Close Door'),
'SD5804' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Stop Door'),
'SD5805' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Single Door Open'),
'SD5806' => array ('Cmd1Name'=>'Door Move','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Single Door Close'),
'SD59' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD5900' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Raise Door'),
'SD5901' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Lower Door'),
'SD5902' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Open Door'),
'SD5903' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Close Door'),
'SD5904' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'Stop Door'),
'SD5905' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'Single Door Open'),
'SD5906' => array ('Cmd1Name'=>'Door Status Report','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'Single Door Close'),
'SD60' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD6000' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Open'),
'SD6001' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Close'),
'SD6002' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Stop'),
'SD6003' => array ('Cmd1Name'=>'Window Covering','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Program'),
'SD61' => array ('Cmd1Name'=>'Window Covering Position','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Position'),
'SD68' => array ('Cmd1Name'=>'Thermostat Temp Up','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Increment Count'),
'ED68' => array ('Cmd1Name'=>'Thermostat Zone Temp Up','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number'),
'SD69' => array ('Cmd1Name'=>'Thermostat Temp Down','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Decrement Count'),
'ED69' => array ('Cmd1Name'=>'Thermostat Zone Temp Down','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number'),
'SD6a' => array ('Cmd1Name'=>'Thermostat Get Zone Info','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field'),
'SDA6a' => array ('Cmd1Name'=>'Thermostat Get Zone Info','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Requested Data'),
'SD6b' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD6b00' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values'),
'SD6b01' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Load EEPROM from RAM'),
'SD6b02' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Get Thermostat Mode'),
'SD6b03' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Get ambient temperature'),
'SD6b04' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x04','Cmd2Name'=>'ON Heat'),
'SD6b05' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x05','Cmd2Name'=>'ON Cool'),
'SD6b06' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x06','Cmd2Name'=>'ON Auto'),
'SD6b07' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x07','Cmd2Name'=>'ON Fan'),
'SD6b08' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x08','Cmd2Name'=>'OFF Fan'),
'SD6b09' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x09','Cmd2Name'=>'OFF All'),
'SD6b0a' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0a','Cmd2Name'=>'Program Heat'),
'SD6b0b' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0b','Cmd2Name'=>'Program Cool'),
'SD6b0c' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0c','Cmd2Name'=>'Program Auto'),
'SD6b0d' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0d','Cmd2Name'=>'Get Equipment State'),
'SD6b0e' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0e','Cmd2Name'=>'Set Equipment State'),
'SD6b0f' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x0f','Cmd2Name'=>'Get Temperature Units'),
'SD6b10' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x10','Cmd2Name'=>'Set Fahrenheit'),
'SD6b11' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x11','Cmd2Name'=>'Set Celsius'),
'SD6b12' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x12','Cmd2Name'=>'Get Fan-On Speed'),
'SD6b13' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x13','Cmd2Name'=>'Set Fan-On Speed Low'),
'SD6b14' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x14','Cmd2Name'=>'Set Fan-On Speed Medium'),
'SD6b15' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x15','Cmd2Name'=>'Set Fan-On Speed High'),
'SD6b16' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x16','Cmd2Name'=>'Enable status change message'),
'SD6b17' => array ('Cmd1Name'=>'Thermostat Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x17','Cmd2Name'=>'Disable status change message'),
'SD6c' => array ('Cmd1Name'=>'Thermostat Set Cool Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Setpoint Value'),
'ED6c' => array ('Cmd1Name'=>'Thermostat Set Zone Cool Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number'),
'SD6d' => array ('Cmd1Name'=>'Thermostat Set Heat Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Setpoint Value'),
'ED6d' => array ('Cmd1Name'=>'Thermostat Set Zone Heat Setpoint','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Zone Number'),
'SD6e' => array ('Cmd1Name'=>'Thermostat Set or Read Mode','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field'),
'SD70' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SD7000' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Leak Detected'),
'SD7001' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'No Leak Detected'),
'SD7002' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Battery Low'),
'SD7003' => array ('Cmd1Name'=>'Leak Detector Announce','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Battery OK'),
'SD81' => array ('Cmd1Name'=>'Assign to Companion Group(Deprecated)','Cmd2Flag'=>'NA','Cmd2Value'=>'','Cmd2Name'=>''),
'EDf0' => array ('Cmd1Name'=>'Read or Write Registers','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field'),
'SDf0' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'','Cmd2Name'=>''),
'SDf000' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x00','Cmd2Name'=>'Load Initialization Values'),
'SDf001' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x01','Cmd2Name'=>'Write a Code Record'),
'SDf002' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x02','Cmd2Name'=>'Read a Code Record'),
'SDf003' => array ('Cmd1Name'=>'EZSnsRF Control','Cmd2Flag'=>'Command','Cmd2Value'=>'0x03','Cmd2Name'=>'Get a Code Record'),
'SDf1' => array ('Cmd1Name'=>'Specific Code Record Read','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Record Number'),
'EDf1' => array ('Cmd1Name'=>'Response to Read Registers','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Bit Field'),
'EDf1' => array ('Cmd1Name'=>'Code Record Request Respon','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Record Number'),
'EDf2' => array ('Cmd1Name'=>'Specific Code Record Write','Cmd2Flag'=>'Value','Cmd2Value'=>'','Cmd2Name'=>'Record Number'),
);


#X10 PLM codes
$x10_house_codes = array (
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

$x10_unit_codes = array (
	'6' => '1',
	'e' => '2',
	'2' => '3',
	'a' => '4',
	'1' => '5',
	'9' => '6',
	'5' => '7',
	'd' => '8',
	'7' => '9',
	'f' => 'a',
	'3' => 'b',
	'b' => 'c',
	'0' => 'd',
	'8' => 'e',
	'4' => 'f',
	'c' => 'g'
);

$x10_commands = array (
	'2' => 'On(J)',
	'3' => 'Off(K)',
	'5' => 'Bright(L)',
	'4' => 'Dim(M)',
	'a' => 'preset_dim1',
	'b' => 'preset_dim2',
	'0' => 'all_units_off(P)',
	'1' => 'all_lights_on(O)',
	'6' => 'all_lights_off',
	'f' => 'status',
	'd' => 'status_on',
	'e' => 'status_off',
	'9' => 'hail_ack',
	'7' => 'ext_code',
	'c' => 'ext_data',
	'8' => 'hail_request'
);


/*=item plm_decode(plm_string)

Returns a string containing a decoded PLM data packet

=cut
*/

function plm_decode($plm_string) {

	global $plmcmd;
	global $plmcmdlen;
	global $insteonCmd;
	global $plmcmd2string;
	
	$plm_string = strtolower($plm_string);

#0262 1e5d8e 0f 0d00
#0262 1e5d8e 0f 0d00 06

#FSM:0 - Look for PLM STX
#FSM:1 - Parse PLM command category
#FSM:2 - Parse command from PLM (50-58)
#FSM:3 - Parse command to PLM (60-73) and response 

	$plm_message = '';
	$plm_cmd_id;

	$FSM = 0;
	$abort = 0;
	$finished = 0;
	while(!$abort and !$finished) {
		if($FSM==0) {
			#FSM:0 - Look for PLM STX
			#Must start with STX or it is garbage
			if(substr($plm_string,0,2) != '02') {
				$plm_message .= "Missing (02)STX: Invalid message\n";
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
				$plm_cmd_id = substr($plm_string,0,4);
				$plm_message .= sprintf("%20s: (","PLM Command").$plm_cmd_id.") ".$plmcmd2string[$plm_cmd_id]."\n";
				if(strlen($plm_string) < $plmcmdlen[strtoupper($plm_cmd_id)][0] * 2) {
					$plm_message .= "        Message strlen too short for PLM command.  Not parsed\n";
					$abort++;
				} elseif(strlen($plm_string) > $plmcmdlen[strtoupper($plm_cmd_id)][0] * 2 
						and strlen($plm_string) < $plmcmdlen[strtoupper($plm_cmd_id)][1] * 2) {
					$plm_message .= "        Message strlen too short for PLM command.  Not parsed\n";
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
			if($plm_cmd_id == '0250') {
				$plm_message .= sprintf("%24s: ",'From Address').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
				$plm_message .= sprintf("%24s: ",'To Address').substr($plm_string,10,2).":".substr($plm_string,12,2).":".substr($plm_string,14,2)."\n";
                                $plm_message .= sprintf("%24s: ",'Message Flags').substr($plm_string,16,2)."\n";
                                $plm_message .= insteon_message_flags_decode(substr($plm_string,16,2));
				$flag_ext = hexdec(substr($plm_string,16,1))&0x1;
                                $plm_message .= sprintf("%24s: ",'Insteon Message').substr($plm_string,18,($flag_ext ? 32 : 4))."\n";
				$plm_message .= insteon_decode(substr($plm_string,16));
			} elseif($plm_cmd_id == '0251'){
				$plm_message .= sprintf("%24s: ",'From Address').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
				$plm_message .= sprintf("%24s: ",'To Address').substr($plm_string,10,2).":".substr($plm_string,12,2).":".substr($plm_string,14,2)."\n";
                                $plm_message .= sprintf("%24s: ",'Message Flags').substr($plm_string,16,2)."\n";
                                $plm_message .= insteon_message_flags_decode(substr($plm_string,16,2));
				$flag_ext = hexdec(substr($plm_string,16,1))&0x1;
                                $plm_message .= sprintf("%24s: ",'Insteon Message').substr($plm_string,18,($flag_ext ? 32 : 4))."\n";
				$plm_message .= insteon_decode(substr($plm_string,16));
			} elseif($plm_cmd_id == '0252'){
                                $plm_message .= sprintf("%20s: ",'X10 Message').substr($plm_string,4,4)."\n";
                                $plm_message .= plm_x10_decode(substr($plm_string,4,4));
			} elseif($plm_cmd_id == '0253'){
				$link_string = array ('PLM is Responder', 'PLM is Controller', 'All-Link deleted');
				$plm_message .= sprintf("%20s: (",'Link Code').substr($plm_string,4,2).") ".$link_string[substr($plm_string,4,2)]."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'Linked Device').substr($plm_string,8,2).":".substr($plm_string,10,2).":".substr($plm_string,12,2)."\n";
				$plm_message .= sprintf("%20s: ",'Device Category').substr($plm_string,14,2).":".substr($plm_string,16,2)."\n";
				$plm_message .= sprintf("%20s: ",'Firmware').substr($plm_string,18,2)."\n";
			} elseif($plm_cmd_id == '0254'){
				$buttons = array ('SET Button ','Button 2 ','Button 3 ');
				$button_event = array('','','Tapped','Held 3 seconds','Released');
				$plm_message .= sprintf("%20s: (",'Button Event').substr($plm_string,4,2).") ".$buttons[substr($plm_string,4,1)].$button_event[substr($plm_string,5,1)]."\n";
			} elseif($plm_cmd_id == '0255'){
				#Nothing else to do
			} elseif($plm_cmd_id == '0256'){
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,4,2)."\n";
				$plm_message .= sprintf("%20s: ",'Device').substr($plm_string,6,2).":".substr($plm_string,8,2).":".substr($plm_string,10,2)."\n";
			} elseif($plm_cmd_id == '0257'){
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
			} elseif($plm_cmd_id == '0258'){
				$plm_message .= sprintf("%20s: (",'Status Byte').substr($plm_string,4,2).") ".(substr($plm_string,4,2) == '06' ? "ACK" : "NACK")."\n";
			} else {
				$plm_message .= sprintf("%20s: (",'Undefined Cmd Data').substr($plm_string,4).")\n";
			}
			$finished++;
		} elseif($FSM==3) {
			#FSM:3 - Parse command to PLM (60-73) and response
			$plm_ack_pos;
			if($plm_cmd_id == '0260') {
				if(strlen($plm_string)>4) {
					$plm_message .= sprintf("%20s: ",'PLM Device ID').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
					$plm_message .= sprintf("%20s: ",'Device Category').substr($plm_string,10,2).":".substr($plm_string,12,2)."\n";
					$plm_message .= sprintf("%20s: ",'Firmware').substr($plm_string,14,2)."\n";
				}
				$plm_ack_pos = 16;
			} elseif($plm_cmd_id == '0261'){
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,4,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command1').substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Command2').substr($plm_string,8,2)."\n";
				$plm_ack_pos = 10;
				#TODO:  look up insteon information for all-link command1 / command2 decode
			} elseif($plm_cmd_id == '0262'){
				$plm_message .= sprintf("%24s: ",'To Address').substr($plm_string,4,2).":".substr($plm_string,6,2).":".substr($plm_string,8,2)."\n";
                                $plm_message .= sprintf("%24s: ",'Message Flags').substr($plm_string,10,2)."\n";
                                $plm_message .= insteon_message_flags_decode(substr($plm_string,10,2));
				$flag_ext = hexdec(substr($plm_string,10,1))&0x1;
                                $plm_message .= sprintf("%24s: ",'Insteon Message').substr($plm_string,12,($flag_ext ? 32 : 4))."\n";
				$plm_message .= insteon_decode(substr($plm_string,10));
                                $plm_ack_pos = $flag_ext ? 44 : 16;
			} elseif($plm_cmd_id == '0263'){
                                $plm_message .= sprintf("%20s: ",'X10 Message').substr($plm_string,4,4)."\n";
                                $plm_message .= plm_x10_decode(substr($plm_string,4,4));
                                $plm_ack_pos = 8;
			} elseif($plm_cmd_id == '0264'){
				$link_string = array('00'=>'PLM is Responder',
						   '01'=>'PLM is Controller',
						   '03'=>'PLM is either Responder or Controller',
						   'ff'=>'Delete All-Link');
				$plm_message .= sprintf("%20s: (",'Link Code').substr($plm_string,4,2).") ".$link_string[substr($plm_string,4,2)]."\n";
				$plm_message .= sprintf("%20s: ",'All-Link Group').substr($plm_string,6,2)."\n";
				$plm_ack_pos = 8;
			} elseif($plm_cmd_id == '0265'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '0266'){
				$plm_message .= sprintf("%20s: ",'Device Category').substr($plm_string,4,2).":".substr($plm_string,6,2)."\n";
				$plm_message .= sprintf("%20s: ",'Firmware').substr($plm_string,8,2)."\n";
				$plm_ack_pos = 10;
			} elseif($plm_cmd_id == '0267'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '0268'){
				$plm_message .= sprintf("%20s: ",'Command2 Data').substr($plm_string,4,2)."\n";
				$plm_ack_pos = 6;
			} elseif($plm_cmd_id == '0269'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '026a'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '026b'){
				$plm_message .= sprintf("%20s: (",'PLM Config Flags').substr($plm_string,4,2).")\n";
				$flags = hexdec(substr($plm_string,4,2));
				$plm_message .= sprintf("%20s: Automatic Linking ",'Bit 7').($flags&0x80?'Disabled':'Enabled')."\n";
				$plm_message .= sprintf("%20s: Monitor Mode ",'Bit 6').($flags&0x40?'Enabled':'Disabled')."\n";
				$plm_message .= sprintf("%20s: Automatic LED ",'Bit 5').($flags&0x20?'Disabled':'Enabled')."\n";
				$plm_message .= sprintf("%20s: Deadman Feature ",'Bit 4').($flags&0x10?'Disabled':'Enabled')."\n";
				$plm_ack_pos = 6;
			} elseif($plm_cmd_id == '026c'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '026d'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '026e'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '026f'){
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
			} elseif($plm_cmd_id == '0270'){
				$plm_message .= sprintf("%20s: ",'Command2 Data').substr($plm_string,4,2)."\n";
				$plm_ack_pos = 6;
			} elseif($plm_cmd_id == '0271'){
				$plm_message .= sprintf("%20s: ",'Command1 Data').substr($plm_string,4,2)."\n";
				$plm_message .= sprintf("%20s: ",'Command2 Data').substr($plm_string,6,2)."\n";
				$plm_ack_pos = 8;
			} elseif($plm_cmd_id == '0272'){
				$plm_ack_pos = 4;
			} elseif($plm_cmd_id == '0273'){
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
			}
			$finished++;
		} #if($FSM==)
	} #while(!$abort)
	return $plm_message;
}


/*=item plm_x10_decode(x10_string)

Returns a string containing a decoded PLM X10 data packet

=cut*/

function plm_x10_decode($x10_string) {

	global $x10_house_codes;
	global $x10_unit_codes;
	global $x10_commands;
	
	$x10_string = strtolower($x10_string);

	$x10_message = '';
	$x10_message .= sprintf("%24s: (",'X10 House Code').substr($x10_string,0,1).") ".strtoupper($x10_house_codes[substr($x10_string,0,1)])."\n";
	if(substr($x10_string,2,1) == '8') {
		$x10_message .= sprintf("%24s: (",'X10 Command').substr($x10_string,1,1).") ".$x10_commands[substr($x10_string,1,1)]."\n";
	} else {
		$x10_message .= sprintf("%24s: (",'X10 Unit Code').substr($x10_string,1,1).") ".strtoupper($x10_unit_codes[substr($x10_string,1,1)])."\n";
	}
	return($x10_message);
}

/*=item insteon_message_flags_decode(flags_string)

Returns a string containing decoded Insteon message flags

=cut*/

function insteon_message_flags_decode($flags_string) {
	
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

function insteon_decode($command_string) {
	 
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

	global $plmcmd;
	global $plmcmdlen;
	global $insteonCmd;
	
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
		$insteon_message .= insteon_decode_cmd(($extended ? 'ED' : 'SD'), $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 1 or $msg_type == 5) {
		#SDA/EDA: Standard/Extended Direct ACK/NACK
		$insteon_message .= insteon_decode_cmd(($extended ? 'EDA' : 'SDA'), $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 6) {
		#SA: Standard All-Link Broadcast
		$insteon_message .= insteon_decode_cmd('SA', $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 2) {
		#SC: Standard Direct Cleanup
		$insteon_message .= insteon_decode_cmd('SC', $cmd1, $cmd2, $extended, $data);
	} elseif( $msg_type == 3 or $msg_type == 7) {
		#SCA: Standard Direct Cleanup ACK/NACK
		$insteon_message .= insteon_decode_cmd('SCA', $cmd1, $cmd2, $extended, $data);
	} else {
		$insteon_message .= sprintf("%28s: ",'')."Insteon message type not decoded\n";
	}

	return $insteon_message;
}


function insteon_decode_cmd($cmdLookup, $cmd1, $cmd2, $extended, $Data) {

	global $plmcmd;
	global $plmcmdlen;
	global $insteonCmd;

	$insteon_message='';
//	($cmdDecoder1, $cmdDecoder2);

	#lookup 1st without using Cmd2
	$cmdDecoder1 = $insteonCmd[$cmdLookup.$cmd1];

	if(!isset($cmdDecoder1)) {
		#lookup failed, if this is an ACK/NACK retry w/ direct version
		if( $cmdLookup == 'SDA') {
			$cmdDecoder1 = $insteonCmd['SD'.$cmd1];
		} elseif( $cmdLookup == 'EDA')  {
			$cmdDecoder1 = $insteonCmd['ED'.$cmd1];
		} elseif( $cmdLookup == 'SCA') {
			$cmdDecoder1 = $insteonCmd['SC'.$cmd1];
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
		#2nd lookup with Cmd2
		$cmdDecoder2 = $insteonCmd[$cmdLookup.$cmd1.$cmd2];
		if(!isset($cmdDecoder2)) {
			#lookup failed, if this is an ACK/NACK retry w/ direct version
			if( $cmdLookup == 'SDA') {
				$cmdDecoder2 = $insteonCmd['SD'.$cmd1];
			} elseif( $cmdLookup == 'EDA') {
				$cmdDecoder2 = $insteonCmd['ED'.$cmd1];
			} elseif( $cmdLookup == 'SCA') {
				$cmdDecoder2 = $insteonCmd['SC'.$cmd1];
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
		$insteon_message .= "Parse database has undefined Cmd2Flag: ".$cmdDecoder1['Cmd2Flag'];
	}

	return $insteon_message;
}


#$plm_cmd is 2 byte hexdec cmd; $send_rec is 0 for send, 1, for rec; $is_extended is 1 if extended send
#returns expected byte strlen
function insteon_cmd_len ($plm_cmd, $send_rec, $is_extended) {
	 
	global $plmcmdlen;
	
	if ($is_extended && $plmcmdlen[strtoupper($plm_cmd)] > 2) {
		return $plmcmdlen[strtoupper($plm_cmd)][($send_rec+2)];
	} else {
		return $plmcmdlen[strtoupper($plm_cmd)][$send_rec];
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
?>
