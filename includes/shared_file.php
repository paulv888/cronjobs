<?php
define( 'DEBUG', FALSE );


function openLockFile($lockFileName) {
	global $lock;
	
	$lock = @fopen( $lockFileName, 'w' );
	if( !$lock )
	{
		error( "Could not write to lock file $lockFileName" );
		continue;
	}
	return flock($lock, LOCK_EX);
}

?>
